import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
  RefreshCw, FileCode, FileText, Rss, Braces, ChevronDown, ExternalLink,
  CheckCircle2, XCircle, ShieldCheck, Globe, AlertTriangle, Eye, Gauge,
} from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, SectionTitle, Badge, Button, StatTile, ScoreGauge, EmptyState } from '@/blogseo/components/Ui';
import { cn, formatNumber, severityMeta, scoreMeta } from '@/blogseo/lib/utils';

function IssueRow({ issue }) {
  const [open, setOpen] = useState(false);
  const sev = severityMeta(issue.severity);

  return (
    <li>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-surface"
      >
        <span className={cn('h-2.5 w-2.5 shrink-0 rounded-full', sev.dot)} />

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-[13.5px] font-semibold text-ink">{issue.label}</span>
            <Badge className={sev.badge}>{sev.label}</Badge>
            {issue.type === 'product' && <Badge className="bg-slate-100 text-slate-600 ring-slate-500/20">Products</Badge>}
          </div>
          <p className="mt-0.5 text-[12px] leading-snug text-muted">{issue.fix}</p>
        </div>

        <span className="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-[13px] font-bold tabular-nums text-ink-2">{issue.count}</span>
        <ChevronDown className={cn('h-4 w-4 shrink-0 text-muted transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <div className="border-t border-line bg-surface/60 px-5 py-3">
          <ul className="space-y-1.5">
            {issue.samples.map((s) => (
              <li key={`${issue.key}-${s.id}`} className="flex items-center justify-between gap-3">
                <a href={s.edit_url} className="min-w-0 truncate text-[13px] font-medium text-emerald-700 hover:underline">{s.title}</a>
                <span className="flex shrink-0 items-center gap-2">
                  {s.score !== null && <span className="text-[11.5px] tabular-nums text-muted">{s.score}/100</span>}
                  <a href={s.url} target="_blank" rel="noopener noreferrer" className="text-muted-2 hover:text-emerald-600" aria-label="Open page">
                    <ExternalLink className="h-3.5 w-3.5" />
                  </a>
                </span>
              </li>
            ))}
          </ul>
          {issue.count > issue.samples.length && (
            <p className="mt-2 text-[11.5px] text-muted">and {issue.count - issue.samples.length} more…</p>
          )}
        </div>
      )}
    </li>
  );
}

function TechRow({ icon: Icon, label, ok = true, value, href }) {
  return (
    <li className="flex items-center justify-between gap-2 py-2">
      <span className="flex items-center gap-2 text-[13px] text-ink-2">
        {ok ? <CheckCircle2 className="h-4 w-4 text-emerald-500" /> : <XCircle className="h-4 w-4 text-rose-500" />}
        <Icon className="h-3.5 w-3.5 text-muted-2" />
        {label}
      </span>
      {href ? (
        <a href={href} target="_blank" rel="noopener noreferrer" className="text-[12px] font-semibold text-emerald-700 hover:underline">{value}</a>
      ) : (
        <span className="text-[12px] text-muted">{value}</span>
      )}
    </li>
  );
}

export default function SeoHealth({ report, technical }) {
  const [busy, setBusy] = useState(null);

  const run = (url, key) => {
    setBusy(key);
    router.post(url, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
  };

  const bands = [
    { key: 'excellent', label: 'Excellent', range: '85–100', bar: 'bg-emerald-500' },
    { key: 'good', label: 'Good', range: '70–84', bar: 'bg-lime-500' },
    { key: 'fair', label: 'Needs work', range: '50–69', bar: 'bg-amber-500' },
    { key: 'poor', label: 'Poor', range: '0–49', bar: 'bg-rose-500' },
  ];
  const distTotal = Math.max(1, Object.values(report.distribution).reduce((a, b) => a + b, 0));

  return (
    <BlogSeoLayout
      title="SEO Health"
      subtitle="Search health across blog articles and storefront product pages"
      actions={
        <Button variant="secondary" onClick={() => run('/blog-seo/seo/reanalyse-all', 'all')} disabled={busy === 'all'}>
          <RefreshCw className={cn('h-4 w-4', busy === 'all' && 'animate-spin')} />
          {busy === 'all' ? 'Scoring…' : 'Re-run all audits'}
        </Button>
      }
    >
      <Head title="SEO Health" />

      {/* Hero */}
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="bg-gradient-to-br from-emerald-50 via-teal-50/60 to-white p-5">
          <ScoreGauge
            score={report.score}
            size={104}
            label="Site health"
            sublabel="Average article score, less a penalty for critical issues."
          />
        </Card>

        <div className="grid grid-cols-2 gap-4 lg:col-span-2">
          <StatTile icon={Globe} tone="brand" label="Indexable pages" value={formatNumber(report.summary.indexable_pages)} sub={`${report.summary.noindex_pages} excluded`} />
          <StatTile icon={AlertTriangle} tone="rose" label="Critical issues" value={formatNumber(report.summary.critical_issues)} sub={`${report.summary.total_issues} total`} />
          <StatTile icon={Gauge} tone="sky" label="Avg article score" value={`${report.summary.avg_post_score}/100`} sub={`${report.summary.published_posts} published`} />
          <StatTile icon={Eye} tone="amber" label="Total views" value={formatNumber(report.summary.total_views)} sub={`${report.summary.active_products} active products`} />
        </div>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        {/* Issues */}
        <div className="lg:col-span-2">
          <Card className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-line px-5 py-4">
              <div>
                <h2 className="text-[15px] font-bold text-ink">Issues to fix</h2>
                <p className="mt-0.5 text-[12.5px] text-muted">Ranked by severity, then by how many pages are affected.</p>
              </div>
              <Badge className={report.issues.length === 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20'}>
                {report.issues.length} type(s)
              </Badge>
            </div>

            {report.issues.length === 0 ? (
              <div className="p-5">
                <EmptyState icon={ShieldCheck} title="No issues detected" hint="Every published page has its meta, images and keywords in place." />
              </div>
            ) : (
              <ul className="divide-y divide-line">
                {report.issues.map((issue) => <IssueRow key={issue.key} issue={issue} />)}
              </ul>
            )}
          </Card>

          {/* Distribution */}
          <Card className="mt-4 p-5">
            <SectionTitle title="Article score distribution" hint="How your published articles spread across score bands" />
            <div className="space-y-3">
              {bands.map((band) => {
                const count = report.distribution[band.key] ?? 0;
                return (
                  <div key={band.key}>
                    <div className="mb-1 flex items-center justify-between text-[12px]">
                      <span className="font-medium text-ink-2">{band.label} <span className="text-muted-2">({band.range})</span></span>
                      <span className="tabular-nums text-muted">{count} article(s)</span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                      <div className={cn('h-full rounded-full transition-all duration-500', band.bar)} style={{ width: `${count > 0 ? Math.max(2, Math.round((count / distTotal) * 100)) : 0}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-4">
          <Card className="p-5">
            <SectionTitle title="Technical SEO" />
            <ul className="divide-y divide-line">
              <TechRow icon={FileCode} label="sitemap.xml" value={`${formatNumber(technical.sitemapCount)} URLs`} href={technical.sitemapUrl} />
              <TechRow icon={FileText} label="robots.txt" ok={technical.robotsExists} value={technical.robotsExists ? 'View' : 'Missing'} href={technical.robotsUrl} />
              <TechRow icon={Rss} label="RSS feed" value="View" href={technical.feedUrl} />
              <TechRow icon={Braces} label="Structured data" value="Article + Product" />
            </ul>

            <div className="mt-3 flex flex-col gap-2">
              <Button className="w-full" onClick={() => run('/blog-seo/seo/sitemap', 'sitemap')} disabled={busy === 'sitemap'}>
                <RefreshCw className={cn('h-4 w-4', busy === 'sitemap' && 'animate-spin')} /> Refresh sitemap
              </Button>
              <Button variant="ghost" className="w-full" onClick={() => run('/blog-seo/seo/robots', 'robots')} disabled={busy === 'robots'}>
                <FileText className="h-4 w-4" /> Regenerate robots.txt
              </Button>
            </div>
          </Card>

          {report.weakest_posts.length > 0 && (
            <Card className="p-5">
              <SectionTitle title="Weakest articles" hint="Biggest wins available — fix these first." />
              <ul className="space-y-2.5">
                {report.weakest_posts.map((p) => (
                  <li key={p.id} className="flex items-start gap-2.5">
                    <Badge className={scoreMeta(p.score).soft}>{p.score}</Badge>
                    <a href={p.edit_url} className="line-clamp-2 text-[12.5px] font-medium text-ink-2 hover:text-emerald-700">{p.title}</a>
                  </li>
                ))}
              </ul>
            </Card>
          )}

          {report.top_posts.length > 0 && (
            <Card className="p-5">
              <SectionTitle title="Most read" />
              <ol className="space-y-2.5">
                {report.top_posts.map((p, i) => (
                  <li key={p.id} className="flex items-start gap-2.5">
                    <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-50 text-[11px] font-bold tabular-nums text-emerald-700">{i + 1}</span>
                    <div className="min-w-0 flex-1">
                      <a href={p.edit_url} className="line-clamp-2 text-[12.5px] font-medium text-ink-2 hover:text-emerald-700">{p.title}</a>
                      <p className="text-[11px] tabular-nums text-muted">{formatNumber(p.views)} views · SEO {p.score}</p>
                    </div>
                  </li>
                ))}
              </ol>
            </Card>
          )}

          <Card className="p-5">
            <SectionTitle title="Content pipeline" />
            <dl className="space-y-2.5 text-[13px]">
              {[
                ['Published', report.summary.published_posts],
                ['Drafts', report.summary.draft_posts],
                ['Scheduled', report.summary.scheduled_posts],
                ['Comments pending', report.summary.pending_comments],
                ['Subscribers', report.summary.subscribers],
              ].map(([label, value]) => (
                <div key={label} className="flex items-center justify-between">
                  <dt className="text-muted">{label}</dt>
                  <dd className="font-bold tabular-nums text-ink">{formatNumber(value)}</dd>
                </div>
              ))}
            </dl>
          </Card>
        </div>
      </div>
    </BlogSeoLayout>
  );
}

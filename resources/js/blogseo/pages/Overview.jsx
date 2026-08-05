import { Head, Link, router } from '@inertiajs/react';
import {
  AreaChart, Area, BarChart, Bar, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import {
  Globe, AlertTriangle, Eye, Mail, FileText, PenLine, MessageSquare,
  ArrowRight, Flame, TrendingDown,
} from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, SectionTitle, Badge, Button, StatTile, ScoreGauge, ScoreBar, EmptyState } from '@/blogseo/components/Ui';
import { cn, formatNumber, timeAgo, postStatusMeta, severityMeta, scoreMeta } from '@/blogseo/lib/utils';

function ChartTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null;

  return (
    <div className="rounded-xl bg-slate-900 px-3 py-2 text-[12px] text-white shadow-lg">
      <p className="font-semibold">{label}</p>
      {payload.map((row) => (
        <p key={row.dataKey} className="mt-0.5 tabular-nums text-white/80">
          {row.name}: <span className="font-semibold text-white">{formatNumber(row.value)}</span>
        </p>
      ))}
    </div>
  );
}

export default function Overview({ health, summary, topIssues, topPosts, weakestPosts, publishing, recentPosts, recentComments, recentSubscribers }) {
  const distribution = [
    { band: 'Excellent', count: health.distribution.excellent, fill: '#10B981' },
    { band: 'Good', count: health.distribution.good, fill: '#84CC16' },
    { band: 'Needs work', count: health.distribution.fair, fill: '#F59E0B' },
    { band: 'Poor', count: health.distribution.poor, fill: '#F43F5E' },
  ];

  return (
    <BlogSeoLayout
      title="Overview"
      subtitle="How your content and search health are tracking"
      actions={<Button variant="primary" href="/blog-seo/posts/create"><PenLine className="h-4 w-4" /> Write a post</Button>}
    >
      <Head title="Overview" />

      {/* ---------------- HERO: health + key numbers ---------------- */}
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="bg-gradient-to-br from-emerald-50 via-teal-50/60 to-white p-5">
          <ScoreGauge
            score={health.score}
            size={104}
            label="Site health"
            sublabel="Average article score, less a penalty for critical issues."
          />
          <Link
            href="/blog-seo/seo"
            className="mt-4 inline-flex items-center gap-1 text-[12.5px] font-semibold text-emerald-700 hover:text-emerald-800"
          >
            Open SEO health <ArrowRight className="h-3.5 w-3.5" />
          </Link>
        </Card>

        <div className="grid grid-cols-2 gap-4 lg:col-span-2">
          <StatTile icon={Globe} tone="brand" label="Indexable pages" value={formatNumber(summary.indexable_pages)} sub={`${summary.noindex_pages} excluded`} />
          <StatTile icon={AlertTriangle} tone="rose" label="Critical issues" value={formatNumber(summary.critical_issues)} sub={`${summary.total_issues} total`} />
          <StatTile icon={Eye} tone="sky" label="Total blog views" value={formatNumber(summary.total_views)} sub={`${summary.published_posts} published`} />
          <StatTile icon={Mail} tone="amber" label="Subscribers" value={formatNumber(summary.subscribers)} sub={`${summary.pending_comments} comments pending`} />
        </div>
      </div>

      {/* ---------------- CHARTS ---------------- */}
      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <Card className="p-5 lg:col-span-2">
          <SectionTitle title="Publishing & readership" hint="Posts published and views earned over the last six months" />
          <div className="h-56 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={publishing} margin={{ top: 4, right: 4, bottom: 0, left: -20 }}>
                <defs>
                  <linearGradient id="viewsFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#10B981" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="#10B981" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid stroke="#E2E8F0" strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#94A3B8' }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: '#94A3B8' }} axisLine={false} tickLine={false} allowDecimals={false} />
                <Tooltip content={<ChartTooltip />} />
                <Area type="monotone" dataKey="views" name="Views" stroke="#10B981" strokeWidth={2} fill="url(#viewsFill)" />
                <Area type="monotone" dataKey="published" name="Published" stroke="#14B8A6" strokeWidth={2} fillOpacity={0} strokeDasharray="4 3" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card>

        <Card className="p-5">
          <SectionTitle title="Score distribution" hint="Published articles by band" />
          <div className="h-56 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={distribution} layout="vertical" margin={{ top: 4, right: 12, bottom: 0, left: 6 }}>
                <CartesianGrid stroke="#E2E8F0" strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 11, fill: '#94A3B8' }} axisLine={false} tickLine={false} allowDecimals={false} />
                <YAxis type="category" dataKey="band" width={78} tick={{ fontSize: 11, fill: '#64748B' }} axisLine={false} tickLine={false} />
                <Tooltip content={<ChartTooltip />} cursor={{ fill: '#F1F5F9' }} />
                <Bar dataKey="count" name="Articles" radius={[0, 6, 6, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Card>
      </div>

      {/* ---------------- ISSUES + RECENT POSTS ---------------- */}
      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <Card className="p-5 lg:col-span-2">
          <SectionTitle
            title="Needs attention"
            hint="The highest-impact fixes available right now"
            action={<Button size="sm" variant="ghost" href="/blog-seo/seo">View all</Button>}
          />

          {topIssues.length === 0 ? (
            <EmptyState title="No issues detected" hint="Every published page has its meta, images and keywords in place." />
          ) : (
            <ul className="divide-y divide-line">
              {topIssues.map((issue) => {
                const sev = severityMeta(issue.severity);
                return (
                  <li key={issue.key} className="flex items-center gap-3 py-3">
                    <span className={cn('h-2.5 w-2.5 shrink-0 rounded-full', sev.dot)} />
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[13.5px] font-semibold text-ink">{issue.label}</span>
                        <Badge className={sev.badge}>{sev.label}</Badge>
                        {issue.type === 'product' && <Badge className="bg-slate-100 text-slate-600 ring-slate-500/20">Products</Badge>}
                      </div>
                      <p className="mt-0.5 text-[12px] leading-snug text-muted">{issue.fix}</p>
                    </div>
                    <span className="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-[13px] font-bold tabular-nums text-ink-2">
                      {issue.count}
                    </span>
                  </li>
                );
              })}
            </ul>
          )}
        </Card>

        <Card className="p-5">
          <SectionTitle title="Moderation queue" hint={`${summary.pending_comments} awaiting review`} />
          {recentComments.length === 0 ? (
            <p className="py-8 text-center text-[13px] text-muted">Nothing to moderate.</p>
          ) : (
            <ul className="space-y-3">
              {recentComments.map((c) => (
                <li key={c.id} className="rounded-xl bg-surface p-3">
                  <div className="flex items-center justify-between gap-2">
                    <span className="truncate text-[12.5px] font-semibold text-ink">{c.author}</span>
                    <span className="shrink-0 text-[11px] text-muted-2">{timeAgo(c.createdAt)}</span>
                  </div>
                  <p className="mt-1 line-clamp-2 text-[12.5px] leading-snug text-ink-2">{c.body}</p>
                  <p className="mt-1 truncate text-[11px] text-muted">on {c.post}</p>
                </li>
              ))}
            </ul>
          )}
          {recentComments.length > 0 && (
            <Button size="sm" variant="secondary" className="mt-3 w-full" href="/blog-seo/comments">
              <MessageSquare className="h-3.5 w-3.5" /> Moderate comments
            </Button>
          )}
        </Card>
      </div>

      {/* ---------------- LISTS ---------------- */}
      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <Card className="p-5 lg:col-span-2">
          <SectionTitle
            title="Recently edited"
            action={<Button size="sm" variant="ghost" href="/blog-seo/posts">All posts</Button>}
          />
          {recentPosts.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="No posts yet"
              hint="Write your first article to start building search presence."
              action={<Button variant="primary" href="/blog-seo/posts/create"><PenLine className="h-4 w-4" /> Write a post</Button>}
            />
          ) : (
            <ul className="divide-y divide-line">
              {recentPosts.map((p) => {
                const st = postStatusMeta(p.status);
                return (
                  <li key={p.id}>
                    <Link href={`/blog-seo/posts/${p.id}/edit`} className="flex items-center gap-3 py-2.5 transition-colors hover:bg-surface">
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-[13.5px] font-semibold text-ink">{p.title}</p>
                        <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11.5px] text-muted">
                          {p.category && (
                            <span className="inline-flex items-center gap-1">
                              <span className="h-2 w-2 rounded-full" style={{ backgroundColor: p.categoryColor || '#94A3B8' }} />
                              {p.category}
                            </span>
                          )}
                          <span className="uppercase">{p.locale}</span>
                          <span>{timeAgo(p.updatedAt)}</span>
                        </div>
                      </div>
                      <Badge className={st.className}>{st.label}</Badge>
                      <div className="hidden sm:block"><ScoreBar score={p.score} /></div>
                    </Link>
                  </li>
                );
              })}
            </ul>
          )}
        </Card>

        <div className="space-y-4">
          <Card className="p-5">
            <SectionTitle title="Most read" />
            {topPosts.length === 0 ? (
              <p className="py-6 text-center text-[13px] text-muted">No traffic yet.</p>
            ) : (
              <ol className="space-y-2.5">
                {topPosts.slice(0, 5).map((p, i) => (
                  <li key={p.id} className="flex items-start gap-2.5">
                    <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-50 text-[11px] font-bold tabular-nums text-emerald-700">{i + 1}</span>
                    <div className="min-w-0 flex-1">
                      <Link href={p.edit_url} className="line-clamp-2 text-[12.5px] font-medium text-ink-2 hover:text-emerald-700">{p.title}</Link>
                      <p className="text-[11px] tabular-nums text-muted">{formatNumber(p.views)} views · SEO {p.score}</p>
                    </div>
                  </li>
                ))}
              </ol>
            )}
          </Card>

          <Card className="p-5">
            <SectionTitle title="Weakest articles" hint="Biggest wins available" />
            {weakestPosts.length === 0 ? (
              <p className="py-6 text-center text-[13px] text-muted">Nothing published yet.</p>
            ) : (
              <ul className="space-y-2.5">
                {weakestPosts.slice(0, 5).map((p) => (
                  <li key={p.id} className="flex items-start gap-2.5">
                    <Badge className={scoreMeta(p.score).soft}>{p.score}</Badge>
                    <Link href={p.edit_url} className="line-clamp-2 text-[12.5px] font-medium text-ink-2 hover:text-emerald-700">{p.title}</Link>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card className="p-5">
            <SectionTitle title="New subscribers" />
            {recentSubscribers.length === 0 ? (
              <p className="py-6 text-center text-[13px] text-muted">No sign-ups yet.</p>
            ) : (
              <ul className="space-y-2.5">
                {recentSubscribers.map((s) => (
                  <li key={s.id} className="min-w-0">
                    <p className="truncate text-[12.5px] font-medium text-ink-2">{s.email}</p>
                    <p className="truncate text-[11px] text-muted">from {s.from} · {timeAgo(s.createdAt)}</p>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>
    </BlogSeoLayout>
  );
}

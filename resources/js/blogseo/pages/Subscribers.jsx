import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Download, Search, Trash2, Mail, TrendingUp, MailOpen, Info } from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, SectionTitle, Button, Input, StatTile, EmptyState, Pagination } from '@/blogseo/components/Ui';
import { cn, formatNumber, formatDate } from '@/blogseo/lib/utils';

const TABS = [
  { value: 'active', label: 'Active' },
  { value: 'unsubscribed', label: 'Unsubscribed' },
  { value: '', label: 'All' },
];

export default function Subscribers({ subscribers, filters, stats, topPosts }) {
  const [search, setSearch] = useState(filters.search || '');

  const apply = (patch) => {
    router.get('/blog-seo/subscribers', { ...filters, ...patch }, { preserveState: true, replace: true, preserveScroll: true });
  };

  const exportUrl = `/blog-seo/subscribers/export?${new URLSearchParams(filters).toString()}`;

  return (
    <BlogSeoLayout
      title="Subscribers"
      subtitle="Readers who signed up from an article"
      actions={<Button variant="secondary" href={exportUrl}><Download className="h-4 w-4" /> Export CSV</Button>}
    >
      <Head title="Subscribers" />

      <div className="mb-4 grid gap-4 sm:grid-cols-3">
        <StatTile icon={Mail} tone="brand" label="Active subscribers" value={formatNumber(stats.active)} />
        <StatTile icon={TrendingUp} tone="sky" label="New this month" value={formatNumber(stats.thisMonth)} />
        <StatTile icon={MailOpen} tone="slate" label="Unsubscribed" value={formatNumber(stats.unsubscribed)} />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="mb-3 flex flex-wrap items-center gap-2">
            {TABS.map((tab) => (
              <button
                key={tab.label}
                type="button"
                onClick={() => apply({ status: tab.value })}
                className={cn(
                  'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition-colors',
                  filters.status === tab.value ? 'bg-slate-900 text-white' : 'bg-surface text-ink-2 ring-1 ring-inset ring-line hover:bg-slate-100'
                )}
              >
                {tab.label}
              </button>
            ))}

            <form onSubmit={(e) => { e.preventDefault(); apply({ search }); }} className="relative ml-auto w-full sm:w-56">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-2" />
              <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search email or name…" className="pl-9" aria-label="Search subscribers" />
            </form>
          </div>

          <Card className="overflow-hidden">
            {subscribers.data.length === 0 ? (
              <div className="p-5">
                <EmptyState icon={Mail} title="No subscribers yet" hint="Sign-up forms appear inside every published article." />
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-[13px]">
                  <thead className="border-b border-line bg-surface text-left">
                    <tr>
                      <th className="px-4 py-3 font-semibold text-ink-2">Subscriber</th>
                      <th className="px-4 py-3 font-semibold text-ink-2">Signed up from</th>
                      <th className="px-4 py-3 font-semibold text-ink-2">Date</th>
                      <th className="px-4 py-3" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-line">
                    {subscribers.data.map((s) => (
                      <tr key={s.id} className="hover:bg-surface">
                        <td className="px-4 py-3">
                          <p className="font-medium text-ink">{s.email}</p>
                          <p className="text-[11.5px] text-muted">
                            {s.name || '—'} <span className="uppercase">· {s.locale}</span>
                            {!s.isActive && <span className="font-semibold text-rose-500"> · unsubscribed</span>}
                          </p>
                        </td>
                        <td className="max-w-[16rem] px-4 py-3">
                          <p className="truncate text-ink-2">{s.from ?? (s.source ? s.source.charAt(0).toUpperCase() + s.source.slice(1) : '—')}</p>
                        </td>
                        <td className="whitespace-nowrap px-4 py-3 text-muted">{formatDate(s.createdAt)}</td>
                        <td className="px-4 py-3 text-right">
                          <button
                            type="button"
                            onClick={() => {
                              if (window.confirm(`Permanently remove ${s.email}?`)) {
                                router.delete(`/blog-seo/subscribers/${s.id}`, { preserveScroll: true });
                              }
                            }}
                            className="grid h-8 w-8 place-items-center rounded-lg text-muted transition-colors hover:bg-rose-50 hover:text-rose-600"
                            aria-label={`Remove ${s.email}`}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>

          <Pagination links={subscribers.links} meta={{ from: subscribers.from, to: subscribers.to, total: subscribers.total }} />
        </div>

        <div className="space-y-4">
          <Card className="p-5">
            <SectionTitle title="Best converting articles" hint="Where your subscribers actually signed up." />
            {topPosts.length === 0 ? (
              <p className="text-[13px] text-muted">No sign-ups attributed to an article yet.</p>
            ) : (
              <ol className="space-y-3">
                {topPosts.map((row, i) => (
                  <li key={row.title + i} className="flex items-start gap-2.5">
                    <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-50 text-[11px] font-bold tabular-nums text-emerald-700">{i + 1}</span>
                    <div className="min-w-0 flex-1">
                      <p className="line-clamp-2 text-[12.5px] font-medium text-ink-2">{row.title}</p>
                      <p className="text-[11px] text-muted">{row.count} sign-up(s)</p>
                    </div>
                  </li>
                ))}
              </ol>
            )}
          </Card>

          <Card className="border-amber-200 bg-amber-50 p-4">
            <div className="flex gap-2.5">
              <Info className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
              <div>
                <p className="text-[13px] font-semibold text-amber-900">Sending to these subscribers</p>
                <p className="mt-1 text-[11.5px] leading-relaxed text-amber-800">
                  CRM Broadcasts send to <strong>Students</strong>, which require a user account. These are anonymous
                  emails, so they are not auto-added to an Audience. Export the CSV and import it deliberately if you
                  want to broadcast to them.
                </p>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </BlogSeoLayout>
  );
}

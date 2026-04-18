import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { deriveInitials } from '@/livehost/lib/format';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';

function statusVariant(status) {
  if (status === 'active') {
    return 'active';
  }
  if (status === 'suspended') {
    return 'suspended';
  }
  return 'inactive';
}

export default function HostsIndex() {
  const { hosts, filters } = usePage().props;
  const [search, setSearch] = useState(filters?.search ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');

  useEffect(() => {
    const initial = filters ?? {};
    if ((initial.search ?? '') === search && (initial.status ?? '') === status) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/hosts',
        {
          search: search || undefined,
          status: status || undefined,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [search, status, filters]);

  const clearFilters = () => {
    setSearch('');
    setStatus('');
  };

  const newHostAction = (
    <Link href="/livehost/hosts/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New host
      </Button>
    </Link>
  );

  return (
    <>
      <Head title="Live Hosts" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Hosts']}
        actions={newHostAction}
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Live Hosts
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {hosts.total} total host{hosts.total === 1 ? '' : 's'}
            </p>
          </div>
        </div>

        {/* Filter bar */}
        <div className="flex items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative max-w-md flex-1">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-[14px] w-[14px] -translate-y-1/2 text-[#737373]"
              strokeWidth={2}
            />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search by name, email, or phone…"
              className="border-[#EAEAEA] bg-[#FAFAFA] pl-9"
            />
          </div>
          <select
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>

        {/* Table */}
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {hosts.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">No hosts found.</div>
              {(search || status) && (
                <button
                  type="button"
                  onClick={clearFilters}
                  className="mt-2 text-sm font-medium text-[#059669] hover:text-[#047857]"
                >
                  Clear filters
                </button>
              )}
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Host</th>
                  <th className="px-5 py-3 text-left">Phone</th>
                  <th className="px-5 py-3 text-right">Accounts</th>
                  <th className="px-5 py-3 text-right">Sessions</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {hosts.data.map((host) => (
                  <tr
                    key={host.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-3">
                        <div className="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-[#10B981] to-[#059669] text-xs font-semibold text-white">
                          {host.initials ?? deriveInitials(host.name)}
                        </div>
                        <div className="min-w-0">
                          <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em]">
                            {host.name}
                          </div>
                          <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                            {host.email} · ID {host.id}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3.5 tabular-nums text-[#0A0A0A]">
                      {host.phone ?? '—'}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums">
                      {host.accounts}
                    </td>
                    <td className="px-5 py-3.5 text-right font-semibold tabular-nums">
                      {host.sessions}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusChip variant={statusVariant(host.status)} />
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex gap-1">
                        <Link
                          href={`/livehost/hosts/${host.id}`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="View"
                        >
                          <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <Link
                          href={`/livehost/hosts/${host.id}/edit`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit"
                        >
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <button
                          type="button"
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                          title="Delete"
                        >
                          <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Pagination */}
        {hosts.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {hosts.from}–{hosts.to} of {hosts.total}
            </div>
            <div className="flex gap-1">
              {hosts.links.map((link, index) => (
                <button
                  key={`${link.label}-${index}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => {
                    if (link.url) {
                      router.visit(link.url, {
                        preserveScroll: true,
                        preserveState: true,
                      });
                    }
                  }}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={[
                    'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                    link.active
                      ? 'bg-[#0A0A0A] text-white'
                      : 'text-[#737373] hover:bg-[#F5F5F5]',
                    !link.url ? 'cursor-default opacity-40' : '',
                  ].join(' ')}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </>
  );
}

HostsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

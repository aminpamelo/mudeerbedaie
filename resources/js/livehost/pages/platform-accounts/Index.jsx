import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';

const PLATFORM_DOT_COLORS = {
  shopee: 'bg-[#F43F5E]',
  'tiktok-shop': 'bg-[#0A0A0A]',
  tiktok: 'bg-[#0A0A0A]',
  facebook: 'bg-[#1877F2]',
  instagram: 'bg-[#E1306C]',
  youtube: 'bg-[#FF0000]',
  lazada: 'bg-[#0F146D]',
};

function platformDotClass(slug) {
  if (!slug) {
    return 'bg-[#737373]';
  }

  return PLATFORM_DOT_COLORS[String(slug).toLowerCase()] || 'bg-[#737373]';
}

function StatusChip({ active }) {
  return (
    <span
      className={[
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-[3px] text-[11px] font-medium',
        active ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F5F5F5] text-[#737373]',
      ].join(' ')}
    >
      <span
        className={['h-[6px] w-[6px] rounded-full', active ? 'bg-[#10B981]' : 'bg-[#A3A3A3]'].join(
          ' '
        )}
        aria-hidden="true"
      />
      {active ? 'Active' : 'Inactive'}
    </span>
  );
}

export default function PlatformAccountsIndex() {
  const { accounts, filters, platforms, users } = usePage().props;
  const [search, setSearch] = useState(filters?.search ?? '');
  const [platformId, setPlatformId] = useState(filters?.platform_id ?? '');
  const [userId, setUserId] = useState(filters?.user_id ?? '');
  const [isActive, setIsActive] = useState(filters?.is_active ?? '');

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.search ?? '') === search &&
      (initial.platform_id ?? '') === platformId &&
      (initial.user_id ?? '') === userId &&
      (initial.is_active ?? '') === isActive
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/platform-accounts',
        {
          search: search || undefined,
          platform_id: platformId || undefined,
          user_id: userId || undefined,
          is_active: isActive === '' ? undefined : isActive,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [search, platformId, userId, isActive, filters]);

  const clearFilters = () => {
    setSearch('');
    setPlatformId('');
    setUserId('');
    setIsActive('');
  };

  const newAccountAction = (
    <Link href="/livehost/platform-accounts/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New account
      </Button>
    </Link>
  );

  return (
    <>
      <Head title="Platform Accounts" />
      <TopBar breadcrumb={['Live Host Desk', 'Platform Accounts']} actions={newAccountAction} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Platform Accounts
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {accounts.total} total account{accounts.total === 1 ? '' : 's'}
            </p>
          </div>
        </div>

        {/* Filter bar */}
        <div className="flex flex-wrap items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative max-w-md flex-1">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-[14px] w-[14px] -translate-y-1/2 text-[#737373]"
              strokeWidth={2}
            />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search by name or account ID…"
              className="border-[#EAEAEA] bg-[#FAFAFA] pl-9"
            />
          </div>
          <select
            value={platformId}
            onChange={(event) => setPlatformId(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All platforms</option>
            {platforms.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
          <select
            value={userId}
            onChange={(event) => setUserId(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All owners</option>
            {users.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
          <select
            value={isActive}
            onChange={(event) => setIsActive(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>

        {/* Table */}
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {accounts.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">No platform accounts found.</div>
              {(search || platformId || userId || isActive) && (
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
                  <th className="px-5 py-3 text-left">Account</th>
                  <th className="px-5 py-3 text-left">Platform</th>
                  <th className="px-5 py-3 text-left">Owner</th>
                  <th className="px-5 py-3 text-right">Schedules</th>
                  <th className="px-5 py-3 text-right">Sessions</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {accounts.data.map((account) => (
                  <tr
                    key={account.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      <div className="min-w-0">
                        <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em]">
                          {account.name}
                        </div>
                        <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                          {account.accountId ? `${account.accountId} · ID ${account.id}` : `ID ${account.id}`}
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-2">
                        <span
                          className={[
                            'h-[8px] w-[8px] rounded-full',
                            platformDotClass(account.platform?.slug),
                          ].join(' ')}
                          aria-hidden="true"
                        />
                        <span className="text-[13px] text-[#0A0A0A]">
                          {account.platform?.displayName ?? account.platform?.name ?? '—'}
                        </span>
                      </div>
                    </td>
                    <td className="px-5 py-3.5 text-[13px] text-[#0A0A0A]">
                      {account.user ? (
                        <div className="min-w-0">
                          <div className="truncate">{account.user.name}</div>
                          <div className="truncate text-[11.5px] text-[#737373]">{account.user.email}</div>
                        </div>
                      ) : (
                        <span className="text-[#737373]">Unassigned</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums">{account.schedules}</td>
                    <td className="px-5 py-3.5 text-right tabular-nums">{account.sessions}</td>
                    <td className="px-5 py-3.5">
                      <StatusChip active={account.isActive} />
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex gap-1">
                        <Link
                          href={`/livehost/platform-accounts/${account.id}`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="View"
                        >
                          <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <Link
                          href={`/livehost/platform-accounts/${account.id}/edit`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit"
                        >
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <button
                          type="button"
                          onClick={() => {
                            if (window.confirm(`Delete ${account.name}?`)) {
                              router.delete(`/livehost/platform-accounts/${account.id}`, {
                                preserveScroll: true,
                              });
                            }
                          }}
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
        {accounts.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {accounts.from}–{accounts.to} of {accounts.total}
            </div>
            <div className="flex gap-1">
              {accounts.links.map((link, index) => (
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

PlatformAccountsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

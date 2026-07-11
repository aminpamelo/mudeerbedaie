import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { BadgeCheck, HelpCircle, Pencil, Plus, Search, Trash2, UserPlus } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/livehost/components/ui/dialog';

function formatHandle(handle) {
  if (!handle) {
    return handle;
  }
  const trimmed = String(handle).trim();
  return trimmed.startsWith('@') ? trimmed : `@${trimmed}`;
}

function PrimaryBadge() {
  return (
    <span className="inline-flex items-center rounded-full bg-[#ECFDF5] px-2 py-[2px] text-[10.5px] font-medium text-[#065F46]">
      Primary
    </span>
  );
}

/**
 * Shows whether the creator's canonical account is a linked TikTok Shop account
 * (the ones the timetable shows) vs still unclassified.
 */
function LinkedBadge({ account }) {
  if (account?.is_linked) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-[#EFF6FF] px-2 py-[2px] text-[10.5px] font-medium text-[#1D4ED8]">
        <BadgeCheck className="h-[12px] w-[12px]" strokeWidth={2.4} />
        Linked
      </span>
    );
  }

  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-[#F5F5F5] px-2 py-[2px] text-[10.5px] font-medium text-[#737373]">
      <HelpCircle className="h-[12px] w-[12px]" strokeWidth={2.2} />
      Not linked
    </span>
  );
}

function relativeTime(iso) {
  if (!iso) {
    return null;
  }
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) {
    return null;
  }
  const diff = Date.now() - then;
  const mins = Math.round(diff / 60000);
  if (mins < 60) {
    return `${Math.max(1, mins)}m ago`;
  }
  const hours = Math.round(mins / 60);
  if (hours < 24) {
    return `${hours}h ago`;
  }
  return `${Math.round(hours / 24)}d ago`;
}

function CreatorFormModal({ open, onOpenChange, mode, creator, hosts, platformAccounts, onSaved }) {
  const isEdit = mode === 'edit';

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    user_id: '',
    platform_account_id: '',
    creator_handle: '',
    creator_platform_user_id: '',
    is_primary: false,
  });

  useEffect(() => {
    if (!open) {
      return;
    }

    clearErrors();

    if (isEdit && creator) {
      setData({
        user_id: String(creator.user_id ?? ''),
        platform_account_id: String(creator.platform_account_id ?? ''),
        creator_handle: creator.creator_handle ?? '',
        creator_platform_user_id: creator.creator_platform_user_id ?? '',
        is_primary: Boolean(creator.is_primary),
      });
    } else {
      reset();
    }
  }, [open, isEdit, creator]);

  const submit = (event) => {
    event.preventDefault();

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false);
        onSaved?.();
      },
    };

    if (isEdit) {
      put(`/livehost/creators/${creator.id}`, options);
    } else {
      post('/livehost/creators', options);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="border-[#EAEAEA] bg-white sm:max-w-[520px]">
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>{isEdit ? 'Edit creator' : 'New creator'}</DialogTitle>
            <DialogDescription>
              Link a live host to a TikTok creator identity so imports match automatically.
              Registering marks this creator as a <span className="font-medium text-[#0A0A0A]">linked TikTok Shop account</span> — its lives then show on the timetable.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            <div className="space-y-1.5">
              <Label htmlFor="user_id">Host</Label>
              <select
                id="user_id"
                value={data.user_id}
                onChange={(event) => setData('user_id', event.target.value)}
                disabled={isEdit}
                className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 disabled:bg-[#F5F5F5]"
              >
                <option value="">Select host…</option>
                {hosts.map((h) => (
                  <option key={h.id} value={h.id}>
                    {h.name}
                  </option>
                ))}
              </select>
              {errors.user_id && <p className="text-[12px] text-[#DC2626]">{errors.user_id}</p>}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="platform_account_id">Platform account</Label>
              <select
                id="platform_account_id"
                value={data.platform_account_id}
                onChange={(event) => setData('platform_account_id', event.target.value)}
                disabled={isEdit}
                className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 disabled:bg-[#F5F5F5]"
              >
                <option value="">Select platform account…</option>
                {platformAccounts.map((pa) => (
                  <option key={pa.id} value={pa.id}>
                    {pa.name}
                    {pa.platform ? ` · ${pa.platform}` : ''}
                  </option>
                ))}
              </select>
              {errors.platform_account_id && (
                <p className="text-[12px] text-[#DC2626]">{errors.platform_account_id}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="creator_handle">Nickname</Label>
              <Input
                id="creator_handle"
                value={data.creator_handle}
                onChange={(event) => setData('creator_handle', event.target.value)}
                placeholder="e.g. BeDaie Ustaz Amar"
              />
              {errors.creator_handle && (
                <p className="text-[12px] text-[#DC2626]">{errors.creator_handle}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="creator_platform_user_id">Creator ID</Label>
              <Input
                id="creator_platform_user_id"
                value={data.creator_platform_user_id}
                onChange={(event) => setData('creator_platform_user_id', event.target.value)}
                placeholder="e.g. 6526684195492729856"
              />
              <p className="text-[11.5px] text-[#737373]">
                The numeric ID from TikTok reports — used to match live sessions.
              </p>
              {errors.creator_platform_user_id && (
                <p className="text-[12px] text-[#DC2626]">{errors.creator_platform_user_id}</p>
              )}
            </div>

            <label className="flex cursor-pointer items-center gap-2 text-sm text-[#0A0A0A]">
              <input
                type="checkbox"
                checked={data.is_primary}
                onChange={(event) => setData('is_primary', event.target.checked)}
                className="h-4 w-4 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/20"
              />
              Mark as primary creator for this host
            </label>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={processing}
              className="shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="bg-ink text-white hover:bg-[#262626]"
              disabled={processing}
            >
              {isEdit ? 'Save changes' : 'Create'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export default function CreatorsIndex() {
  const {
    creators,
    filters,
    hosts,
    platformAccounts,
    flash,
    auth,
    unclassified = [],
    unclassifiedWindowDays = 14,
  } = usePage().props;
  const canManageCreators = Boolean(auth?.permissions?.canManageCreators);

  const [search, setSearch] = useState(filters?.search ?? '');
  const [shopFilter, setShopFilter] = useState(filters?.platform_account ?? '');
  const [createOpen, setCreateOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);
  const [flashMessage, setFlashMessage] = useState(null);
  const [classifyingKey, setClassifyingKey] = useState(null);

  const navigateFilters = (next = {}) => {
    router.get(
      '/livehost/creators',
      {
        search: (next.search ?? search) || undefined,
        platform_account: (next.platform_account ?? shopFilter) || undefined,
      },
      { preserveState: true, preserveScroll: true, replace: true }
    );
  };

  const onShopFilterChange = (value) => {
    setShopFilter(value);
    navigateFilters({ platform_account: value });
  };

  // One-click classify from the "belum diklasifikasi" list. No Creator ID needed
  // — the TikTok API only gives us the handle, so we link/dismiss by handle.
  const classifyUnclassified = (row, accountType) => {
    const key = row.liveAccountId ?? row.creatorHandle ?? row.creatorUserId;
    setClassifyingKey(key);
    router.post(
      '/livehost/live-accounts/classify',
      {
        live_account_id: row.liveAccountId ?? null,
        creator_handle: row.creatorHandle ? String(row.creatorHandle).replace(/^@/, '') : null,
        creator_user_id: row.creatorUserId ?? null,
        account_type: accountType,
        shop_ids: (row.shops ?? []).map((s) => s.id),
      },
      {
        preserveScroll: true,
        onFinish: () => setClassifyingKey(null),
      }
    );
  };

  useEffect(() => {
    if (flash?.success) {
      setFlashMessage({ kind: 'success', message: flash.success });
    }
  }, [flash]);

  useEffect(() => {
    const initial = filters ?? {};
    if ((initial.search ?? '') === search) {
      return undefined;
    }

    const handle = setTimeout(() => {
      navigateFilters({ search });
    }, 300);

    return () => clearTimeout(handle);
  }, [search, filters]);

  const handleDelete = (creator) => {
    const label = (creator.creator_handle ? formatHandle(creator.creator_handle) : null) || creator.creator_platform_user_id;
    if (!window.confirm(`Remove creator "${label}"?`)) {
      return;
    }

    router.delete(`/livehost/creators/${creator.id}`, {
      preserveScroll: true,
    });
  };

  const newAction = canManageCreators ? (
    <Button
      size="sm"
      onClick={() => setCreateOpen(true)}
      className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]"
    >
      <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
      New creator
    </Button>
  ) : null;

  const availableHostsForCreate = useMemo(() => hosts, [hosts]);

  const groupedCreators = useMemo(() => {
    const groups = new Map();
    for (const creator of creators.data) {
      const handle = creator.creator_handle?.trim();
      const key = handle ? handle.toLowerCase() : '__no_nickname__';
      if (!groups.has(key)) {
        groups.set(key, {
          key,
          nickname: handle || null,
          creators: [],
        });
      }
      groups.get(key).creators.push(creator);
    }
    return Array.from(groups.values());
  }, [creators.data]);

  return (
    <>
      <Head title="Creators · Live Host Desk" />
      <TopBar breadcrumb={['Live Host Desk', 'Creators']} actions={newAction} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
          <h1 className="text-2xl sm:text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
            Creators
          </h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            TikTok creator identities linked to your live hosts. Populating the Creator ID lets
            TikTok Import rows auto-match live sessions.
          </p>
        </div>

        {flashMessage && (
          <div
            className={`rounded-lg border px-4 py-2.5 text-sm ${
              flashMessage.kind === 'success'
                ? 'border-[#BBF7D0] bg-[#F0FDF4] text-[#166534]'
                : 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]'
            }`}
          >
            {flashMessage.message}
          </div>
        )}

        {unclassified.length > 0 && (
          <div className="overflow-hidden rounded-[16px] border border-[#FDE68A] bg-[#FFFBEB] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <div className="flex items-center justify-between gap-3 border-b border-[#FDE68A]/70 px-5 py-3.5">
              <div className="flex items-center gap-2">
                <span className="inline-flex items-center rounded-full bg-[#F59E0B] px-2 py-[2px] text-[11px] font-semibold text-white">
                  {unclassified.length}
                </span>
                <div>
                  <div className="text-[13.5px] font-semibold tracking-[-0.01em] text-[#78350F]">
                    Belum diklasifikasi
                  </div>
                  <div className="text-[11.5px] text-[#92400E]">
                    Creator yang live {unclassifiedWindowDays} hari lepas tapi belum diklasifikasi. Klik <span className="font-medium">Daftar sebagai linked</span> untuk yang betul milik anda, atau <span className="font-medium">Bukan kami</span> untuk affiliate luar. Tak perlu Creator ID.
                  </div>
                </div>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-sm">
                <thead>
                  <tr className="text-[11px] font-medium text-[#92400E]">
                    <th className="px-5 py-2 text-left">Creator</th>
                    <th className="px-5 py-2 text-left">TikTok Shop</th>
                    <th className="px-5 py-2 text-right">Lives</th>
                    <th className="px-5 py-2 text-right">GMV attributed</th>
                    <th className="px-5 py-2 text-right">Last live</th>
                    <th className="px-5 py-2 text-right">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {unclassified.map((row, index) => (
                    <tr key={`${row.creatorHandle ?? row.creatorUserId ?? 'x'}-${index}`} className="border-t border-[#FDE68A]/60">
                      <td className="px-5 py-2.5 font-medium text-[#78350F]">
                        {formatHandle(row.creatorHandle) ?? row.creatorUserId ?? 'Unknown creator'}
                      </td>
                      <td className="px-5 py-2.5 text-[#92400E]">
                        {row.platformAccount ? (
                          <span>
                            {row.platformAccount}
                            {row.shops && row.shops.length > 1 && (
                              <span className="ml-1 text-[11px] text-[#B45309]">+{row.shops.length - 1}</span>
                            )}
                          </span>
                        ) : (
                          <span className="text-[#B45309]/60">—</span>
                        )}
                      </td>
                      <td className="px-5 py-2.5 text-right tabular-nums text-[#92400E]">{row.liveCount}</td>
                      <td className="px-5 py-2.5 text-right tabular-nums text-[#92400E]">
                        RM {Number(row.attributedGmv ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-5 py-2.5 text-right text-[11.5px] text-[#B45309]">
                        {relativeTime(row.lastLiveAt) ?? '—'}
                      </td>
                      <td className="px-5 py-2.5 text-right">
                        {canManageCreators ? (
                          <div className="inline-flex items-center gap-1.5">
                            <button
                              type="button"
                              disabled={classifyingKey !== null}
                              onClick={() => classifyUnclassified(row, 'linked')}
                              className="inline-flex items-center gap-1.5 rounded-md border border-[#F59E0B] bg-white px-2.5 py-1 text-[12px] font-medium text-[#B45309] hover:bg-[#FFFBEB] disabled:opacity-50"
                            >
                              <UserPlus className="h-[13px] w-[13px]" strokeWidth={2.2} />
                              Daftar sebagai linked
                            </button>
                            <button
                              type="button"
                              disabled={classifyingKey !== null}
                              onClick={() => classifyUnclassified(row, 'affiliate')}
                              title="Tandakan sebagai affiliate luar (bukan akaun kami) — buang dari senarai"
                              className="inline-flex items-center rounded-md px-2 py-1 text-[12px] font-medium text-[#92400E]/70 hover:bg-[#FEF3C7] disabled:opacity-50"
                            >
                              Bukan kami
                            </button>
                          </div>
                        ) : (
                          <span className="text-[11.5px] text-[#B45309]">—</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative w-full sm:max-w-md sm:flex-1">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-[14px] w-[14px] -translate-y-1/2 text-[#737373]"
              strokeWidth={2}
            />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search by host, nickname, or creator ID…"
              className="border-[#EAEAEA] bg-[#FAFAFA] pl-9"
            />
          </div>
          <select
            value={shopFilter}
            onChange={(event) => onShopFilterChange(event.target.value)}
            className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
          >
            <option value="">All TikTok Shops</option>
            {platformAccounts.map((pa) => (
              <option key={pa.id} value={pa.id}>
                {pa.name}
                {pa.platform ? ` · ${pa.platform}` : ''}
              </option>
            ))}
          </select>
        </div>

        {creators.data.length === 0 ? (
          <div className="rounded-[16px] border border-[#EAEAEA] bg-white py-16 text-center text-sm text-[#737373] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            No creators yet. Click <strong>New creator</strong> to add one.
          </div>
        ) : (
          <div className="space-y-6">
            {groupedCreators.map((group) => (
              <div
                key={group.key}
                className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
              >
                <div className="flex items-center justify-between border-b border-[#F0F0F0] bg-[#FAFAFA] px-5 py-3">
                  <div className="text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                    {group.nickname ? (
                      formatHandle(group.nickname)
                    ) : (
                      <span className="text-[#737373]">No nickname</span>
                    )}
                  </div>
                  <div className="text-[11.5px] font-medium text-[#737373]">
                    {group.creators.length} creator{group.creators.length === 1 ? '' : 's'}
                  </div>
                </div>
                <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-sm">
                  <thead>
                    <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                      <th className="px-5 py-3 text-left">Host</th>
                      <th className="px-5 py-3 text-left">Platform account</th>
                      <th className="px-5 py-3 text-left">Creator ID</th>
                      <th className="px-5 py-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {group.creators.map((creator) => (
                      <tr
                        key={creator.id}
                        className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                      >
                        <td className="px-5 py-3.5">
                          {creator.host ? (
                            <div className="min-w-0">
                              <div className="flex flex-wrap items-center gap-2 text-[13.5px] font-semibold tracking-[-0.01em]">
                                <span className="truncate">{creator.host.name}</span>
                                {creator.is_primary && <PrimaryBadge />}
                                <LinkedBadge account={creator.live_account} />
                              </div>
                              <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                                {creator.host.email}
                              </div>
                            </div>
                          ) : (
                            <div className="flex items-center gap-2">
                              <span className="text-[#737373]">—</span>
                              <LinkedBadge account={creator.live_account} />
                            </div>
                          )}
                        </td>
                        <td className="px-5 py-3.5 text-[13px] text-[#0A0A0A]">
                          {creator.platform_account ? (
                            <div className="min-w-0">
                              <div className="truncate">{creator.platform_account.name}</div>
                              {creator.platform_account.platform && (
                                <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                                  {creator.platform_account.platform}
                                </div>
                              )}
                            </div>
                          ) : (
                            <span className="text-[#737373]">—</span>
                          )}
                        </td>
                        <td className="px-5 py-3.5 font-mono text-[12px] tabular-nums text-[#0A0A0A]">
                          {creator.creator_platform_user_id || (
                            <span className="text-[#737373]">—</span>
                          )}
                        </td>
                        <td className="px-5 py-3.5 text-right">
                          {canManageCreators && (
                            <div className="inline-flex gap-1">
                              <button
                                type="button"
                                onClick={() => setEditTarget(creator)}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                                title="Edit"
                              >
                                <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                              </button>
                              <button
                                type="button"
                                onClick={() => handleDelete(creator)}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                                title="Delete"
                              >
                                <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                </div>
              </div>
            ))}
          </div>
        )}

        {creators.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {creators.from}–{creators.to} of {creators.total}
            </div>
            <div className="flex gap-1">
              {creators.links.map((link, index) => (
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

      {canManageCreators && (
        <>
          <CreatorFormModal
            open={createOpen}
            onOpenChange={setCreateOpen}
            mode="create"
            creator={null}
            hosts={availableHostsForCreate}
            platformAccounts={platformAccounts}
          />
          <CreatorFormModal
            open={editTarget !== null}
            onOpenChange={(next) => {
              if (!next) {
                setEditTarget(null);
              }
            }}
            mode="edit"
            creator={editTarget}
            hosts={hosts}
            platformAccounts={platformAccounts}
          />
        </>
      )}
    </>
  );
}

CreatorsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

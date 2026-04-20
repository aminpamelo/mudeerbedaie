import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
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

function PrimaryBadge() {
  return (
    <span className="inline-flex items-center rounded-full bg-[#ECFDF5] px-2 py-[2px] text-[10.5px] font-medium text-[#065F46]">
      Primary
    </span>
  );
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
  const { creators, filters, hosts, platformAccounts, flash } = usePage().props;

  const [search, setSearch] = useState(filters?.search ?? '');
  const [createOpen, setCreateOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);
  const [flashMessage, setFlashMessage] = useState(null);

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
      router.get(
        '/livehost/creators',
        { search: search || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [search, filters]);

  const handleDelete = (creator) => {
    const label = creator.creator_handle || creator.creator_platform_user_id;
    if (!window.confirm(`Remove creator "${label}"?`)) {
      return;
    }

    router.delete(`/livehost/creators/${creator.id}`, {
      preserveScroll: true,
    });
  };

  const newAction = (
    <Button
      size="sm"
      onClick={() => setCreateOpen(true)}
      className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]"
    >
      <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
      New creator
    </Button>
  );

  const availableHostsForCreate = useMemo(() => hosts, [hosts]);

  const groupedCreators = useMemo(() => {
    const groups = new Map();
    for (const creator of creators.data) {
      const key = creator.platform_account?.id ?? 'unassigned';
      if (!groups.has(key)) {
        groups.set(key, {
          key,
          platformAccount: creator.platform_account,
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

      <div className="space-y-6 p-8">
        <div>
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
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

        <div className="flex flex-wrap items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative max-w-md flex-1">
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
                  <div>
                    <div className="text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                      {group.platformAccount?.name ?? 'Unassigned'}
                    </div>
                    {group.platformAccount?.platform && (
                      <div className="mt-0.5 text-[11.5px] text-[#737373]">
                        {group.platformAccount.platform}
                      </div>
                    )}
                  </div>
                  <div className="text-[11.5px] font-medium text-[#737373]">
                    {group.creators.length} creator{group.creators.length === 1 ? '' : 's'}
                  </div>
                </div>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                      <th className="px-5 py-3 text-left">Host</th>
                      <th className="px-5 py-3 text-left">Nickname</th>
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
                              <div className="flex items-center gap-2 truncate text-[13.5px] font-semibold tracking-[-0.01em]">
                                {creator.host.name}
                                {creator.is_primary && <PrimaryBadge />}
                              </div>
                              <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                                {creator.host.email}
                              </div>
                            </div>
                          ) : (
                            <span className="text-[#737373]">—</span>
                          )}
                        </td>
                        <td className="px-5 py-3.5 text-[13px] text-[#0A0A0A]">
                          {creator.creator_handle || <span className="text-[#737373]">—</span>}
                        </td>
                        <td className="px-5 py-3.5 font-mono text-[12px] tabular-nums text-[#0A0A0A]">
                          {creator.creator_platform_user_id || (
                            <span className="text-[#737373]">—</span>
                          )}
                        </td>
                        <td className="px-5 py-3.5 text-right">
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
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
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
  );
}

CreatorsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

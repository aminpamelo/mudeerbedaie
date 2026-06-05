import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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

function Chip({ children, accent = false }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-[2px] text-[10.5px] font-medium ${
        accent ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F5F5F5] text-[#525252]'
      }`}
    >
      {children}
    </span>
  );
}

function AccountFormModal({ open, onOpenChange, mode, account, shops, hosts }) {
  const isEdit = mode === 'edit';

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    creator_user_id: '',
    nickname: '',
    display_name: '',
    needs_review: false,
    shop_ids: [],
    primary_shop_id: '',
    host_ids: [],
  });

  useEffect(() => {
    if (!open) {
      return;
    }
    clearErrors();

    if (isEdit && account) {
      setData({
        creator_user_id: account.creator_user_id ?? '',
        nickname: account.nickname ?? '',
        display_name: account.display_name ?? '',
        needs_review: Boolean(account.needs_review),
        shop_ids: account.shops.map((s) => s.id),
        primary_shop_id: account.shops.find((s) => s.is_primary)?.id ?? '',
        host_ids: account.hosts.map((h) => h.id),
      });
    } else {
      reset();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, isEdit, account?.id]);

  const toggle = (field, id) => {
    const set = new Set(data[field]);
    set.has(id) ? set.delete(id) : set.add(id);
    const next = [...set];
    setData(field, next);
    if (field === 'shop_ids' && !next.includes(data.primary_shop_id)) {
      setData('primary_shop_id', next[0] ?? '');
    }
  };

  const submit = (e) => {
    e.preventDefault();
    const opts = {
      preserveScroll: true,
      onSuccess: () => onOpenChange(false),
    };
    if (isEdit && account) {
      put(`/livehost/live-accounts/${account.id}`, opts);
    } else {
      post('/livehost/live-accounts', opts);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto border border-[#EAEAEA] bg-white text-[#0A0A0A] sm:max-w-[520px]">
        <DialogHeader className="text-left">
          <DialogTitle className="text-[17px] font-semibold tracking-[-0.02em]">
            {isEdit ? 'Edit live account' : 'New live account'}
          </DialogTitle>
          <DialogDescription className="text-[13px] text-[#737373]">
            The creator account a host goes live on. Link the shops it promotes and the hosts who operate it.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="space-y-4">
          <Field label="Nickname (@handle)" error={errors.nickname}>
            <Input
              value={data.nickname}
              onChange={(e) => setData('nickname', e.target.value)}
              placeholder="amarmirzabedaie"
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Display name" error={errors.display_name}>
              <Input
                value={data.display_name}
                onChange={(e) => setData('display_name', e.target.value)}
                placeholder="BeDaie Ustaz Amar"
              />
            </Field>
            <Field label="Creator ID" error={errors.creator_user_id} hint="Stable TikTok id">
              <Input
                value={data.creator_user_id}
                onChange={(e) => setData('creator_user_id', e.target.value)}
                placeholder="6526684195492729856"
              />
            </Field>
          </div>

          <Field label="Shops promoted" error={errors.shop_ids}>
            <CheckList
              items={shops}
              selected={data.shop_ids}
              onToggle={(id) => toggle('shop_ids', id)}
              primaryId={data.primary_shop_id}
              onPrimary={(id) => setData('primary_shop_id', id)}
            />
          </Field>

          <Field label="Hosts who operate this account" error={errors.host_ids}>
            <CheckList
              items={hosts}
              selected={data.host_ids}
              onToggle={(id) => toggle('host_ids', id)}
            />
          </Field>

          <label className="flex cursor-pointer items-center gap-2 text-[13px] text-[#0A0A0A]">
            <input
              type="checkbox"
              checked={data.needs_review}
              onChange={(e) => setData('needs_review', e.target.checked)}
              className="h-4 w-4 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30"
            />
            Needs review
          </label>

          <DialogFooter className="gap-2 sm:gap-2">
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} className="text-[#737373]">
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create account'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function CheckList({ items, selected, onToggle, primaryId = null, onPrimary = null }) {
  if (items.length === 0) {
    return <p className="text-[12px] italic text-[#A3A3A3]">Nothing to select.</p>;
  }
  return (
    <div className="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#EAEAEA] p-2">
      {items.map((item) => {
        const checked = selected.includes(item.id);
        return (
          <div key={item.id} className="flex items-center justify-between gap-2 rounded px-1.5 py-1 hover:bg-[#F5F5F5]">
            <label className="flex flex-1 cursor-pointer items-center gap-2 text-[13px]">
              <input
                type="checkbox"
                checked={checked}
                onChange={() => onToggle(item.id)}
                className="h-4 w-4 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30"
              />
              {item.name}
            </label>
            {onPrimary && checked && (
              <button
                type="button"
                onClick={() => onPrimary(item.id)}
                className={`rounded px-1.5 py-0.5 text-[10px] font-medium ${
                  primaryId === item.id
                    ? 'bg-[#ECFDF5] text-[#065F46]'
                    : 'text-[#A3A3A3] hover:text-[#0A0A0A]'
                }`}
              >
                {primaryId === item.id ? 'Primary' : 'Set primary'}
              </button>
            )}
          </div>
        );
      })}
    </div>
  );
}

function Field({ label, error, hint, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {hint && !error && <p className="text-[11px] text-[#737373]">{hint}</p>}
      {error && <p className="text-xs text-[#F43F5E]">{error}</p>}
    </div>
  );
}

export default function LiveAccountsIndex() {
  const { accounts, filters, shops, hosts, flash } = usePage().props;
  const [search, setSearch] = useState(filters?.search ?? '');
  const [needsReview, setNeedsReview] = useState(Boolean(filters?.needs_review));
  const [createOpen, setCreateOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);

  useEffect(() => {
    const initial = filters ?? {};
    if ((initial.search ?? '') === search && Boolean(initial.needs_review) === needsReview) {
      return undefined;
    }
    const handle = setTimeout(() => {
      router.get(
        '/livehost/live-accounts',
        { search: search || undefined, needs_review: needsReview ? 1 : undefined },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }, 300);
    return () => clearTimeout(handle);
  }, [search, needsReview, filters]);

  const remove = (account) => {
    if (window.confirm(`Remove the "${account.label}" live account?`)) {
      router.delete(`/livehost/live-accounts/${account.id}`, { preserveScroll: true });
    }
  };

  const newAction = (
    <Button
      size="sm"
      onClick={() => setCreateOpen(true)}
      className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]"
    >
      <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
      New account
    </Button>
  );

  return (
    <>
      <Head title="Live Accounts" />
      <TopBar breadcrumb={['Live Host Desk', 'Live Accounts']} actions={newAction} />

      <div className="space-y-6 p-8">
        <div>
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
            Live Accounts
          </h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            The creator accounts hosts go live on — the governing reference for scheduling. Each links to the shops it promotes.
          </p>
        </div>

        {flash?.success && (
          <div className="rounded-[12px] border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#065F46]">
            {flash.success}
          </div>
        )}

        <div className="flex flex-wrap items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#A3A3A3]" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search nickname, display name or Creator ID…"
              className="h-9 w-80 rounded-lg border border-[#EAEAEA] bg-white pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          <label className="flex cursor-pointer items-center gap-2 text-[13px] text-[#525252]">
            <input
              type="checkbox"
              checked={needsReview}
              onChange={(e) => setNeedsReview(e.target.checked)}
              className="h-4 w-4 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30"
            />
            Needs review only
          </label>
        </div>

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {accounts.data.length === 0 ? (
            <div className="p-8 text-center text-[13px] text-[#737373]">
              No live accounts yet. Create one, or run the consolidation command to import them.
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Account</th>
                  <th className="px-5 py-3 text-left">Creator ID</th>
                  <th className="px-5 py-3 text-left">Shops</th>
                  <th className="px-5 py-3 text-left">Hosts</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {accounts.data.map((account) => (
                  <tr key={account.id} className="border-t border-[#F0F0F0] hover:bg-[#F5F5F5]">
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-[#0A0A0A]">{account.label}</span>
                        {account.needs_review && <Chip>Review</Chip>}
                        {!account.is_active && <Chip>Inactive</Chip>}
                      </div>
                      {account.display_name && account.display_name !== account.label && (
                        <div className="text-[11px] text-[#737373]">{account.display_name}</div>
                      )}
                    </td>
                    <td className="px-5 py-3.5 font-mono text-[12px] text-[#525252]">
                      {account.creator_user_id ?? '—'}
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="flex flex-wrap gap-1">
                        {account.shops.length === 0 ? (
                          <span className="text-[12px] italic text-[#A3A3A3]">None</span>
                        ) : (
                          account.shops.map((s) => (
                            <Chip key={s.id} accent={s.is_primary}>
                              {s.name}
                            </Chip>
                          ))
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="flex flex-wrap gap-1">
                        {account.hosts.length === 0 ? (
                          <span className="text-[12px] italic text-[#A3A3A3]">None</span>
                        ) : (
                          account.hosts.map((h) => <Chip key={h.id}>{h.name}</Chip>)
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex gap-1">
                        <button
                          type="button"
                          onClick={() => setEditTarget(account)}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit"
                        >
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </button>
                        <button
                          type="button"
                          onClick={() => remove(account)}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FEF2F2] hover:text-[#F43F5E]"
                          title="Remove"
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
      </div>

      <AccountFormModal
        open={createOpen}
        onOpenChange={setCreateOpen}
        mode="create"
        shops={shops}
        hosts={hosts}
      />
      <AccountFormModal
        open={editTarget !== null}
        onOpenChange={(next) => !next && setEditTarget(null)}
        mode="edit"
        account={editTarget}
        shops={shops}
        hosts={hosts}
      />
    </>
  );
}

LiveAccountsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

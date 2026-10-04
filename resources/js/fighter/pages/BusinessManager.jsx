import { useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, router } from '@inertiajs/react';
import {
  Briefcase, Plus, RefreshCw, Pencil, Trash2, X, Loader2, ExternalLink, CheckCircle2, AlertTriangle,
  ShieldCheck, Link2, BarChart3,
} from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn, fighterSend, formatMoney, formatNumber, statusMeta, timeAgo } from '@/fighter/lib/utils';

const CONNECTION_STATUS = {
  connected: { label: 'Connected', className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: CheckCircle2 },
  error: { label: 'Needs attention', className: 'bg-rose-50 text-rose-700 ring-rose-600/20', icon: AlertTriangle },
  pending: { label: 'Pending', className: 'bg-slate-100 text-slate-600 ring-slate-500/20', icon: Loader2 },
};

function StatusPill({ status }) {
  const meta = CONNECTION_STATUS[status] ?? CONNECTION_STATUS.pending;
  const Icon = meta.icon;
  return (
    <span className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold ring-1', meta.className)}>
      <Icon className="h-3.5 w-3.5" strokeWidth={2.2} />
      {meta.label}
    </span>
  );
}

function Flash({ flash, onClose }) {
  if (!flash) return null;
  return (
    <div
      className={cn(
        'mb-5 flex items-start justify-between gap-3 rounded-xl px-4 py-3 text-[13px] ring-1',
        flash.type === 'error' ? 'bg-rose-50 text-rose-700 ring-rose-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
      )}
    >
      <span>{flash.message}</span>
      <button type="button" onClick={onClose} aria-label="Dismiss" className="shrink-0 opacity-70 hover:opacity-100">
        <X className="h-4 w-4" strokeWidth={2.2} />
      </button>
    </div>
  );
}

const STEPS = [
  {
    title: 'Find your Business Manager ID',
    body: 'Open Business Settings → Business info. Copy the numeric “Business portfolio ID”.',
    href: 'https://business.facebook.com/settings/info',
    cta: 'Open Business info',
  },
  {
    title: 'Create a System User',
    body: 'Business Settings → Users → System Users → Add. Any name, role “Employee” is enough. Then “Add assets” → Ad accounts → tick your ad accounts with “View performance”.',
    href: 'https://business.facebook.com/settings/system-users',
    cta: 'Open System Users',
  },
  {
    title: 'Generate a token',
    body: 'On the System User click “Generate new token”, pick any app, tick ads_read, set expiry to “Never”. Copy the token and paste it below.',
  },
];

function Field({ label, hint, error, children }) {
  return (
    <label className="block">
      <span className="text-[12px] font-semibold text-ink-2">{label}</span>
      <div className="mt-1">{children}</div>
      {hint && !error && <span className="mt-1 block text-[11.5px] text-muted">{hint}</span>}
      {error && <span className="mt-1 block text-[11.5px] font-medium text-rose-600">{error}</span>}
    </label>
  );
}

const INPUT = 'w-full rounded-xl border border-line bg-white px-3 py-2.5 text-[13.5px] text-ink outline-none transition-colors placeholder:text-muted-2 focus:border-[var(--color-brand)]';

/** Connect a new BM, or edit an existing one (token optional on edit). */
function ConnectionModal({ connection, onClose, onDone }) {
  const editing = Boolean(connection?.id);
  const [form, setForm] = useState({
    name: connection?.name ?? '',
    business_manager_id: connection?.business_manager_id ?? '',
    access_token: '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

  const submit = async () => {
    setSaving(true);
    setError(null);
    try {
      const res = await fighterSend(editing ? `/fighter/business-manager/${connection.id}` : '/fighter/business-manager', {
        method: editing ? 'PUT' : 'POST',
        body: form,
      });
      onDone(res?.message || (editing ? 'Business Manager updated.' : 'Business Manager connected.'));
    } catch (e) {
      setError(e.message);
      setSaving(false);
    }
  };

  const canSubmit = form.name.trim() && form.business_manager_id.trim() && (editing || form.access_token.trim());

  return createPortal(
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={saving ? undefined : onClose} />
      <div className="relative z-10 flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line/70 px-5 py-4">
          <div>
            <h3 className="text-[15px] font-semibold text-ink">{editing ? 'Edit Business Manager' : 'Connect Business Manager'}</h3>
            <p className="text-[12px] text-muted">Read-only access — nothing on your ads is changed.</p>
          </div>
          <button type="button" onClick={onClose} disabled={saving} className="grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink" aria-label="Close">
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>

        <div className="flex-1 space-y-5 overflow-y-auto px-5 py-4 scroll-thin">
          {!editing && (
            <ol className="space-y-3">
              {STEPS.map((step, i) => (
                <li key={step.title} className="flex gap-3 rounded-xl bg-surface p-3 ring-1 ring-line/70">
                  <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--color-brand)] text-[12px] font-bold text-white">{i + 1}</span>
                  <div className="min-w-0">
                    <div className="text-[13px] font-semibold text-ink">{step.title}</div>
                    <p className="mt-0.5 text-[12.5px] leading-relaxed text-muted">{step.body}</p>
                    {step.href && (
                      <a href={step.href} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1 text-[12.5px] font-semibold text-[var(--color-brand-ink)] hover:underline">
                        {step.cta} <ExternalLink className="h-3.5 w-3.5" strokeWidth={2.2} />
                      </a>
                    )}
                  </div>
                </li>
              ))}
            </ol>
          )}

          <div className="space-y-4">
            <Field label="Name" hint="Just for you — e.g. “My BM — Haid”.">
              <input className={INPUT} value={form.name} onChange={set('name')} placeholder="My Business Manager" />
            </Field>
            <Field label="Business Manager ID">
              <input className={cn(INPUT, 'font-mono')} value={form.business_manager_id} onChange={set('business_manager_id')} placeholder="123456789012345" inputMode="numeric" />
            </Field>
            <Field label="System User access token" hint={editing ? 'Leave blank to keep the current token.' : 'Stored securely and only used to read ad spend.'}>
              <textarea
                className={cn(INPUT, 'min-h-[84px] font-mono text-[12px]')}
                value={form.access_token}
                onChange={set('access_token')}
                placeholder={editing ? 'Paste a new token to rotate it' : 'EAAG…'}
              />
            </Field>
          </div>

          {error && (
            <div className="flex items-start gap-2 rounded-xl bg-rose-50 px-3 py-2.5 text-[12.5px] text-rose-700 ring-1 ring-rose-600/20">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={2.2} />
              <div>
                <div className="font-semibold">Couldn't connect</div>
                <div>{error}</div>
                <div className="mt-1 text-rose-600/80">Check the token belongs to a System User in this same BM, has ads_read, and is assigned your ad accounts.</div>
              </div>
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 border-t border-line/70 px-5 py-3.5">
          <button type="button" onClick={onClose} disabled={saving} className="rounded-xl bg-slate-100 px-4 py-2.5 text-[13px] font-semibold text-ink-2 hover:bg-slate-200">
            Cancel
          </button>
          <button
            type="button"
            onClick={submit}
            disabled={!canSubmit || saving}
            className="flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-[var(--color-brand-ink)] disabled:opacity-60"
          >
            {saving && <Loader2 className="h-4 w-4 animate-spin" />}
            {saving ? (editing ? 'Saving…' : 'Verifying & syncing…') : editing ? 'Save changes' : 'Connect'}
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}

function IconButton({ icon: Icon, label, onClick, busy, danger }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={busy}
      title={label}
      aria-label={label}
      className={cn(
        'grid h-8 w-8 place-items-center rounded-lg ring-1 ring-line/70 transition-colors disabled:opacity-60',
        danger ? 'text-rose-600 hover:bg-rose-50' : 'text-ink-2 hover:bg-surface'
      )}
    >
      <Icon className={cn('h-4 w-4', busy && 'animate-spin')} strokeWidth={2} />
    </button>
  );
}

function ConnectionCard({ connection, onEdit, onFlash }) {
  const [busy, setBusy] = useState(null);

  const sync = async () => {
    setBusy('sync');
    try {
      const res = await fighterSend(`/fighter/business-manager/${connection.id}/sync`);
      onFlash({ type: 'success', message: res?.message || 'Synced.' });
    } catch (e) {
      onFlash({ type: 'error', message: e.message });
    } finally {
      setBusy(null);
      router.reload({ preserveScroll: true });
    }
  };

  const remove = async () => {
    if (!window.confirm(`Disconnect “${connection.name}”? Its ad spend history will be removed from your reports.`)) return;
    setBusy('delete');
    try {
      await fighterSend(`/fighter/business-manager/${connection.id}`, { method: 'DELETE' });
      onFlash({ type: 'success', message: 'Business Manager disconnected.' });
      router.reload({ preserveScroll: true });
    } catch (e) {
      onFlash({ type: 'error', message: e.message });
      setBusy(null);
    }
  };

  return (
    <div className="overflow-hidden rounded-2xl ring-1 ring-line/70">
      <div className="flex flex-wrap items-start justify-between gap-3 bg-white px-4 py-3.5">
        <div className="flex min-w-0 items-center gap-3">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600">
            <Briefcase className="h-5 w-5" strokeWidth={2} />
          </div>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <span className="truncate text-[14.5px] font-semibold text-ink">{connection.name}</span>
              <StatusPill status={connection.status} />
            </div>
            <div className="mt-0.5 text-[12px] text-muted">
              BM <span className="font-mono">{connection.business_manager_id}</span>
              {connection.last_synced_at && <> · synced {timeAgo(connection.last_synced_at)}</>}
            </div>
          </div>
        </div>
        <div className="flex items-center gap-1.5">
          <IconButton icon={RefreshCw} label="Sync now" onClick={sync} busy={busy === 'sync'} />
          <IconButton icon={Pencil} label="Edit" onClick={() => onEdit(connection)} />
          <IconButton icon={Trash2} label="Disconnect" onClick={remove} busy={busy === 'delete'} danger />
        </div>
      </div>

      {connection.status === 'error' && connection.status_message && (
        <div className="border-t border-rose-600/10 bg-rose-50 px-4 py-2.5 text-[12.5px] text-rose-700">{connection.status_message}</div>
      )}

      {connection.accounts.length === 0 ? (
        <p className="border-t border-line/70 bg-surface px-4 py-5 text-center text-[12.5px] text-muted">
          No ad accounts yet — assign your ad accounts to the System User, then press Sync.
        </p>
      ) : (
        <div className="overflow-x-auto border-t border-line/70">
          <table className="min-w-full">
            <thead className="bg-surface">
              <tr>
                {['Ad account', 'Spend (30d)', 'Impressions', 'Clicks', 'Funnels'].map((h, i) => (
                  <th key={h} className={cn('whitespace-nowrap px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2', i === 0 ? 'text-left' : 'text-right')}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-line/70 bg-white">
              {connection.accounts.map((account) => (
                <tr key={account.id}>
                  <td className="px-3 py-3 text-left">
                    <div className="text-[13.5px] font-semibold text-ink">{account.name}</div>
                    <div className="font-mono text-[11.5px] text-muted">act_{account.account_id}{account.currency ? ` · ${account.currency}` : ''}</div>
                  </td>
                  <td className="whitespace-nowrap px-3 py-3 text-right text-[13.5px] font-semibold tabular-nums text-ink">{formatMoney(account.spend_30d)}</td>
                  <td className="whitespace-nowrap px-3 py-3 text-right text-[13.5px] tabular-nums text-ink-2">{formatNumber(account.impressions_30d)}</td>
                  <td className="whitespace-nowrap px-3 py-3 text-right text-[13.5px] tabular-nums text-ink-2">{formatNumber(account.clicks_30d)}</td>
                  <td className="whitespace-nowrap px-3 py-3 text-right text-[13.5px] tabular-nums text-ink-2">{account.linked_funnels_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function FunnelLinkRow({ funnel, connections, onFlash }) {
  const [value, setValue] = useState(funnel.facebook_ad_account_id ?? '');
  const [saving, setSaving] = useState(false);
  const status = statusMeta(funnel.status);

  const change = async (e) => {
    const next = e.target.value;
    const previous = value;
    setValue(next);
    setSaving(true);
    try {
      await fighterSend(`/fighter/business-manager/funnels/${funnel.uuid}`, {
        method: 'PUT',
        body: { facebook_ad_account_id: next ? Number(next) : null },
      });
      router.reload({ only: ['connections'], preserveScroll: true });
    } catch (err) {
      setValue(previous);
      onFlash({ type: 'error', message: err.message });
    } finally {
      setSaving(false);
    }
  };

  return (
    <tr>
      <td className="px-3 py-3">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-[13.5px] font-semibold text-ink">{funnel.name}</span>
          <span className={cn('rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1', status.className)}>{status.label}</span>
        </div>
      </td>
      <td className="px-3 py-3">
        <div className="flex items-center justify-end gap-2">
          {saving && <Loader2 className="h-4 w-4 animate-spin text-muted-2" />}
          <select
            value={value}
            onChange={change}
            disabled={saving}
            className="w-full max-w-[280px] rounded-xl border border-line bg-white px-3 py-2 text-[13px] font-medium text-ink outline-none focus:border-[var(--color-brand)]"
          >
            <option value="">— Not linked —</option>
            {connections.map((connection) => (
              <optgroup key={connection.id} label={connection.name}>
                {connection.accounts.map((account) => (
                  <option key={account.id} value={account.id}>{account.name}</option>
                ))}
              </optgroup>
            ))}
          </select>
        </div>
      </td>
    </tr>
  );
}

function EmptyState({ onConnect }) {
  return (
    <div className="grid place-items-center rounded-2xl bg-surface px-6 py-14 text-center ring-1 ring-line/70">
      <div className="grid h-14 w-14 place-items-center rounded-2xl bg-white text-[var(--color-brand)] ring-1 ring-line/70">
        <Briefcase className="h-7 w-7" strokeWidth={1.8} />
      </div>
      <h2 className="mt-4 text-[16px] font-semibold text-ink">Link your Facebook Business Manager</h2>
      <p className="mt-1 max-w-md text-[13px] text-muted">
        Pull your daily ad spend automatically, link each funnel to its ad account, and see ROAS and net profit in Daily Reporting.
      </p>
      <div className="mt-4 flex items-center gap-1.5 text-[12px] text-muted">
        <ShieldCheck className="h-4 w-4 text-emerald-600" strokeWidth={2} /> Read-only — we can never edit or spend on your ads.
      </div>
      <button type="button" onClick={onConnect} className="mt-5 flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-[var(--color-brand-ink)]">
        <Plus className="h-4 w-4" strokeWidth={2.4} /> Connect Business Manager
      </button>
    </div>
  );
}

export default function BusinessManager({ connections = [], funnels = [] }) {
  const [modal, setModal] = useState(null);
  const [flash, setFlash] = useState(null);
  const hasAccounts = connections.some((c) => c.accounts.length > 0);

  const done = (message) => {
    setModal(null);
    setFlash({ type: 'success', message });
    router.reload({ preserveScroll: true });
  };

  return (
    <FighterLayout
      title="Business Manager"
      subtitle="Link your own Facebook Business Managers so ad spend flows into your reports."
      actions={
        connections.length > 0 && (
          <button type="button" onClick={() => setModal({})} className="flex items-center gap-2 rounded-xl bg-[var(--color-brand)] px-3.5 py-2.5 text-[13px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)]">
            <Plus className="h-4 w-4" strokeWidth={2.4} /> Connect BM
          </button>
        )
      }
    >
      <Flash flash={flash} onClose={() => setFlash(null)} />

      {connections.length === 0 ? (
        <EmptyState onConnect={() => setModal({})} />
      ) : (
        <>
          <div className="space-y-4">
            {connections.map((connection) => (
              <ConnectionCard key={connection.id} connection={connection} onEdit={setModal} onFlash={setFlash} />
            ))}
          </div>

          <section className="mt-7 overflow-hidden rounded-2xl ring-1 ring-line/70">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line/70 bg-white px-4 py-3.5">
              <div className="flex items-center gap-2.5">
                <Link2 className="h-[18px] w-[18px] text-[var(--color-brand)]" strokeWidth={2.2} />
                <div>
                  <h2 className="text-[15px] font-semibold text-ink">Link funnels to ad accounts</h2>
                  <p className="text-[12.5px] text-muted">Tell us which ad account drives each funnel — used for per-funnel ROAS.</p>
                </div>
              </div>
              <Link href="/fighter/daily-reporting" className="flex items-center gap-1.5 text-[12.5px] font-semibold text-[var(--color-brand-ink)] hover:underline">
                <BarChart3 className="h-4 w-4" strokeWidth={2.2} /> View daily reporting
              </Link>
            </div>
            {funnels.length === 0 ? (
              <p className="bg-white px-4 py-8 text-center text-[13px] text-muted">You don't have any funnels yet.</p>
            ) : !hasAccounts ? (
              <p className="bg-white px-4 py-8 text-center text-[13px] text-muted">Sync a Business Manager with at least one ad account to start linking.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full">
                  <thead className="bg-surface">
                    <tr>
                      <th className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Funnel</th>
                      <th className="px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2">Ad account</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-line/70 bg-white">
                    {funnels.map((funnel) => (
                      <FunnelLinkRow key={funnel.uuid} funnel={funnel} connections={connections} onFlash={setFlash} />
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}

      {modal && <ConnectionModal connection={modal} onClose={() => setModal(null)} onDone={done} />}
    </FighterLayout>
  );
}

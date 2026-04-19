import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
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

function mapSessionStatus(status) {
  if (status === 'live') {
    return 'live';
  }
  if (status === 'ended') {
    return 'done';
  }
  if (status === 'cancelled') {
    return 'suspended';
  }
  return 'scheduled';
}

function formatDate(iso) {
  if (!iso) {
    return 'Unscheduled';
  }
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function formatDateOnly(iso) {
  if (!iso) {
    return '—';
  }
  try {
    return new Date(iso).toLocaleDateString();
  } catch {
    return iso;
  }
}

function formatMoney(value) {
  const num = Number(value ?? 0);
  return num.toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatPercent(value) {
  const num = Number(value ?? 0);
  if (Number.isInteger(num)) {
    return `${num}%`;
  }
  return `${num.toFixed(2).replace(/\.?0+$/, '')}%`;
}

export default function HostShow() {
  const {
    host,
    platformAccounts,
    recentSessions,
    stats,
    commissionProfile,
    commissionProfiles,
    platformCommissionRates,
    platforms,
    uplineCandidates,
  } = usePage().props;
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');

  const handleDelete = () => {
    setDeleting(true);
    router.delete(`/livehost/hosts/${host.id}`, {
      onFinish: () => {
        setDeleting(false);
        setConfirmDelete(false);
      },
    });
  };

  return (
    <>
      <Head title={host.name} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Hosts', host.name]}
        actions={
          <div className="flex gap-2">
            <Link href="/livehost/hosts">
              <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
                <ArrowLeft className="w-3.5 h-3.5" />
                Back
              </Button>
            </Link>
            <Link href={`/livehost/hosts/${host.id}/edit`}>
              <Button variant="ghost" className="gap-1.5 text-[#0A0A0A]">
                <Pencil className="w-3.5 h-3.5" />
                Edit
              </Button>
            </Link>
            <Button
              onClick={() => setConfirmDelete(true)}
              className="gap-1.5 bg-transparent text-[#F43F5E] border border-[#F43F5E] hover:bg-[#FFF1F2]"
            >
              <Trash2 className="w-3.5 h-3.5" />
              Delete
            </Button>
          </div>
        }
      />

      <div className="p-8 space-y-6">
        {/* Hero block */}
        <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 flex items-center gap-6">
          <div className="w-20 h-20 rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-white grid place-items-center font-semibold text-2xl tracking-[-0.02em]">
            {host.initials}
          </div>
          <div className="flex-1 min-w-0">
            <div className="text-2xl font-semibold tracking-[-0.02em] mb-1 truncate">{host.name}</div>
            <div className="text-sm text-[#737373] truncate">
              {host.email} · {host.phone ?? 'No phone'} · ID {host.id}
            </div>
          </div>
          <StatusChip variant={statusVariant(host.status || 'inactive')} />
        </div>

        {/* Tabs */}
        <div className="flex items-center gap-6 border-b border-[#EAEAEA]">
          {[
            { id: 'overview', label: 'Overview' },
            { id: 'commission', label: 'Commission' },
          ].map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={[
                'px-1 pb-3 text-sm font-medium -mb-px border-b-2 transition-colors',
                activeTab === tab.id
                  ? 'border-[#0A0A0A] text-[#0A0A0A]'
                  : 'border-transparent text-[#737373] hover:text-[#0A0A0A]',
              ].join(' ')}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {activeTab === 'overview' && (
          <>
            {/* Stats row */}
            <div className="grid grid-cols-3 gap-4">
              <StatTile label="Total sessions" value={stats.totalSessions} />
              <StatTile label="Completed" value={stats.completedSessions} />
              <StatTile label="Platform accounts" value={stats.platformAccounts} />
            </div>

            {/* Grid: platforms + sessions */}
            <div className="grid grid-cols-12 gap-4">
              <div className="col-span-5 bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
                <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Platform accounts</div>
                {platformAccounts.length === 0 ? (
                  <div className="text-sm text-[#737373] py-6 text-center">No platform accounts assigned.</div>
                ) : (
                  <ul className="space-y-0">
                    {platformAccounts.map((pa) => (
                      <li
                        key={pa.id}
                        className="flex items-center justify-between py-2.5 border-b border-[#F0F0F0] last:border-0"
                      >
                        <span className="text-sm font-medium text-[#0A0A0A]">{pa.name}</span>
                        <span className="text-xs text-[#737373] uppercase tracking-wide">
                          {pa.platformName ?? pa.platform ?? '—'}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              <div className="col-span-7 bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
                <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Recent sessions</div>
                {recentSessions.length === 0 ? (
                  <div className="text-sm text-[#737373] py-6 text-center">No sessions yet.</div>
                ) : (
                  <ul className="space-y-0">
                    {recentSessions.map((s) => (
                      <li
                        key={s.id}
                        className="flex items-center justify-between py-2.5 border-b border-[#F0F0F0] last:border-0"
                      >
                        <div className="min-w-0">
                          <div className="text-sm font-medium text-[#0A0A0A] truncate">
                            #{s.sessionId}{' '}
                            <span className="text-[#737373] font-normal">on {s.platformAccount ?? '—'}</span>
                          </div>
                          <div className="text-xs text-[#737373] mt-0.5">{formatDate(s.scheduledStart)}</div>
                        </div>
                        <StatusChip variant={mapSessionStatus(s.status)} />
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </>
        )}

        {activeTab === 'commission' && (
          <CommissionPanel
            host={host}
            commissionProfile={commissionProfile}
            commissionProfiles={commissionProfiles}
            platformCommissionRates={platformCommissionRates}
            platforms={platforms}
            uplineCandidates={uplineCandidates}
            formatDateOnly={formatDateOnly}
            formatMoney={formatMoney}
            formatPercent={formatPercent}
          />
        )}
      </div>

      {confirmDelete && (
        <div className="fixed inset-0 bg-black/40 grid place-items-center z-50">
          <div className="bg-white rounded-[16px] p-6 max-w-md shadow-lg">
            <div className="font-semibold text-lg mb-2 tracking-[-0.02em]">Delete {host.name}?</div>
            <p className="text-sm text-[#737373] mb-4">
              This soft-deletes the host. Their record stays in the database but is hidden from this list. This cannot be
              reversed from the dashboard.
            </p>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setConfirmDelete(false)} disabled={deleting}>
                Cancel
              </Button>
              <Button
                onClick={handleDelete}
                disabled={deleting}
                className="bg-[#F43F5E] text-white hover:bg-[#E11D48]"
              >
                {deleting ? 'Deleting' : 'Confirm delete'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

HostShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function StatTile({ label, value }) {
  return (
    <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="text-3xl font-semibold tracking-[-0.03em] mt-2 tabular-nums">{value}</div>
    </div>
  );
}

function CommissionPanel({
  host,
  commissionProfile,
  commissionProfiles,
  platformCommissionRates,
  platforms,
  uplineCandidates,
  formatDateOnly,
  formatMoney,
  formatPercent,
}) {
  const hasActive = Boolean(commissionProfile);

  const [formValues, setFormValues] = useState(() => ({
    base_salary_myr: commissionProfile?.base_salary_myr ?? 0,
    per_live_rate_myr: commissionProfile?.per_live_rate_myr ?? 0,
    upline_user_id: commissionProfile?.upline_user_id ?? '',
    override_rate_l1_percent: commissionProfile?.override_rate_l1_percent ?? 10,
    override_rate_l2_percent: commissionProfile?.override_rate_l2_percent ?? 5,
    notes: commissionProfile?.notes ?? '',
  }));
  const [uplineSearch, setUplineSearch] = useState('');
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');

  const filteredUpline = useMemo(() => {
    const q = uplineSearch.trim().toLowerCase();
    if (!q) {
      return uplineCandidates;
    }
    return uplineCandidates.filter(
      (u) =>
        u.name?.toLowerCase().includes(q) ||
        u.email?.toLowerCase().includes(q),
    );
  }, [uplineCandidates, uplineSearch]);

  const history = (commissionProfiles || []).filter((p) => !p.is_active);

  const handleProfileSubmit = (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setSuccessMsg('');

    const method = hasActive ? 'put' : 'post';
    const payload = {
      base_salary_myr: Number(formValues.base_salary_myr || 0),
      per_live_rate_myr: Number(formValues.per_live_rate_myr || 0),
      upline_user_id: formValues.upline_user_id === '' ? null : Number(formValues.upline_user_id),
      override_rate_l1_percent: Number(formValues.override_rate_l1_percent || 0),
      override_rate_l2_percent: Number(formValues.override_rate_l2_percent || 0),
      notes: formValues.notes || null,
    };

    router[method](`/livehost/hosts/${host.id}/commission-profile`, payload, {
      preserveScroll: true,
      onError: (errs) => setErrors(errs),
      onSuccess: () => setSuccessMsg('Commission profile saved.'),
      onFinish: () => setSaving(false),
    });
  };

  return (
    <div className="space-y-6">
      {successMsg && (
        <div className="rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-4 py-2.5 text-sm text-[#166534]">
          {successMsg}
        </div>
      )}

      {/* Commission profile form */}
      <form
        onSubmit={handleProfileSubmit}
        className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5"
      >
        <div className="flex items-center justify-between mb-4">
          <div>
            <div className="font-semibold text-[15px] tracking-[-0.015em]">Commission profile</div>
            <div className="text-xs text-[#737373] mt-0.5">
              {hasActive
                ? `Active since ${formatDateOnly(commissionProfile.effective_from)}`
                : 'No active commission profile yet.'}
            </div>
          </div>
          {hasActive && commissionProfile.upline_name && (
            <div className="text-xs text-[#737373]">
              Upline: <span className="text-[#0A0A0A] font-medium">{commissionProfile.upline_name}</span>
            </div>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <Field label="Base salary (RM)" error={errors.base_salary_myr}>
            <Input
              type="number"
              step="0.01"
              min="0"
              value={formValues.base_salary_myr}
              onChange={(e) => setFormValues((v) => ({ ...v, base_salary_myr: e.target.value }))}
            />
          </Field>
          <Field label="Per-live rate (RM)" error={errors.per_live_rate_myr}>
            <Input
              type="number"
              step="0.01"
              min="0"
              value={formValues.per_live_rate_myr}
              onChange={(e) => setFormValues((v) => ({ ...v, per_live_rate_myr: e.target.value }))}
            />
          </Field>
          <Field label="L1 override %" error={errors.override_rate_l1_percent}>
            <Input
              type="number"
              step="0.01"
              min="0"
              max="100"
              value={formValues.override_rate_l1_percent}
              onChange={(e) =>
                setFormValues((v) => ({ ...v, override_rate_l1_percent: e.target.value }))
              }
            />
          </Field>
          <Field label="L2 override %" error={errors.override_rate_l2_percent}>
            <Input
              type="number"
              step="0.01"
              min="0"
              max="100"
              value={formValues.override_rate_l2_percent}
              onChange={(e) =>
                setFormValues((v) => ({ ...v, override_rate_l2_percent: e.target.value }))
              }
            />
          </Field>
          <div className="col-span-2">
            <Field label="Upline (optional)" error={errors.upline_user_id}>
              <div className="space-y-2">
                <Input
                  type="text"
                  placeholder="Search other live hosts…"
                  value={uplineSearch}
                  onChange={(e) => setUplineSearch(e.target.value)}
                />
                <select
                  value={formValues.upline_user_id ?? ''}
                  onChange={(e) => setFormValues((v) => ({ ...v, upline_user_id: e.target.value }))}
                  className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                >
                  <option value="">— No upline —</option>
                  {filteredUpline.map((u) => (
                    <option key={u.id} value={u.id}>
                      {u.name} ({u.email})
                    </option>
                  ))}
                </select>
              </div>
            </Field>
          </div>
          <div className="col-span-2">
            <Field label="Notes" error={errors.notes}>
              <textarea
                rows={3}
                value={formValues.notes ?? ''}
                onChange={(e) => setFormValues((v) => ({ ...v, notes: e.target.value }))}
                className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              />
            </Field>
          </div>
        </div>

        <div className="mt-4 flex justify-end">
          <Button
            type="submit"
            disabled={saving}
            className="bg-[#0A0A0A] text-white hover:bg-[#262626]"
          >
            {saving ? 'Saving…' : hasActive ? 'Update profile' : 'Create profile'}
          </Button>
        </div>
      </form>

      {/* Per-platform rate table */}
      <PlatformRatesPanel
        host={host}
        rates={platformCommissionRates}
        platforms={platforms}
        formatDateOnly={formatDateOnly}
        formatPercent={formatPercent}
      />

      {/* Commission history */}
      <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
        <div className="font-semibold text-[15px] tracking-[-0.015em] mb-3">Commission history</div>
        {history.length === 0 ? (
          <div className="text-sm text-[#737373] py-6 text-center">No prior profile revisions.</div>
        ) : (
          <ul className="space-y-0">
            {history.map((p) => (
              <li
                key={p.id}
                className="py-3 border-b border-[#F0F0F0] last:border-0 flex items-center justify-between"
              >
                <div className="min-w-0">
                  <div className="text-sm font-medium text-[#0A0A0A]">
                    RM {formatMoney(p.base_salary_myr)} + RM {formatMoney(p.per_live_rate_myr)}/live
                  </div>
                  <div className="text-xs text-[#737373] mt-0.5">
                    Effective {formatDateOnly(p.effective_from)} → {formatDateOnly(p.effective_to)}
                    {p.upline_name ? ` · Upline: ${p.upline_name}` : ''}
                  </div>
                </div>
                <div className="text-xs text-[#737373]">
                  L1 {formatPercent(p.override_rate_l1_percent)} · L2{' '}
                  {formatPercent(p.override_rate_l2_percent)}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function PlatformRatesPanel({ host, rates, platforms, formatDateOnly, formatPercent }) {
  const [adding, setAdding] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState({ platform_id: '', commission_rate_percent: '' });
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);

  const startAdd = () => {
    const firstPlatform = platforms?.[0]?.id ?? '';
    setAdding(true);
    setEditingId(null);
    setErrors({});
    setForm({ platform_id: firstPlatform, commission_rate_percent: '' });
  };

  const startEdit = (rate) => {
    setAdding(false);
    setEditingId(rate.id);
    setErrors({});
    setForm({
      platform_id: rate.platform_id,
      commission_rate_percent: rate.commission_rate_percent,
    });
  };

  const cancel = () => {
    setAdding(false);
    setEditingId(null);
    setErrors({});
  };

  const submit = (e) => {
    e.preventDefault();
    setBusy(true);
    setErrors({});
    const payload = {
      platform_id: Number(form.platform_id),
      commission_rate_percent: Number(form.commission_rate_percent),
    };

    const opts = {
      preserveScroll: true,
      onError: (errs) => setErrors(errs),
      onSuccess: () => {
        setAdding(false);
        setEditingId(null);
      },
      onFinish: () => setBusy(false),
    };

    if (editingId) {
      router.put(`/livehost/hosts/${host.id}/platform-rates/${editingId}`, payload, opts);
    } else {
      router.post(`/livehost/hosts/${host.id}/platform-rates`, payload, opts);
    }
  };

  return (
    <div className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-5">
      <div className="flex items-center justify-between mb-3">
        <div className="font-semibold text-[15px] tracking-[-0.015em]">Per-platform rates</div>
        {!adding && !editingId && (
          <Button
            type="button"
            size="sm"
            onClick={startAdd}
            className="h-8 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]"
          >
            <Plus className="h-3.5 w-3.5" />
            Add platform rate
          </Button>
        )}
      </div>

      {(adding || editingId) && (
        <form
          onSubmit={submit}
          className="mb-4 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] p-4 grid grid-cols-3 gap-3"
        >
          <Field label="Platform" error={errors.platform_id}>
            <select
              value={form.platform_id}
              onChange={(e) => setForm((v) => ({ ...v, platform_id: e.target.value }))}
              className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              disabled={Boolean(editingId)}
            >
              {platforms.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Commission rate %" error={errors.commission_rate_percent}>
            <Input
              type="number"
              step="0.01"
              min="0"
              max="100"
              value={form.commission_rate_percent}
              onChange={(e) =>
                setForm((v) => ({ ...v, commission_rate_percent: e.target.value }))
              }
            />
          </Field>
          <div className="flex items-end gap-2">
            <Button
              type="submit"
              disabled={busy}
              className="bg-[#0A0A0A] text-white hover:bg-[#262626]"
            >
              {busy ? 'Saving…' : 'Save'}
            </Button>
            <Button type="button" variant="ghost" onClick={cancel}>
              Cancel
            </Button>
          </div>
        </form>
      )}

      {rates.length === 0 ? (
        <div className="text-sm text-[#737373] py-6 text-center">No platform rates set.</div>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="text-[11.5px] font-medium text-[#737373]">
              <th className="py-2 text-left">Platform</th>
              <th className="py-2 text-right">Rate</th>
              <th className="py-2 text-left">Effective from</th>
              <th className="py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rates.map((r) => (
              <tr key={r.id} className="border-t border-[#F0F0F0]">
                <td className="py-2.5 text-[#0A0A0A] font-medium">
                  {r.platform_name ?? r.platform_slug ?? `Platform #${r.platform_id}`}
                </td>
                <td className="py-2.5 text-right tabular-nums">
                  {formatPercent(r.commission_rate_percent)}
                </td>
                <td className="py-2.5 text-[#737373]">{formatDateOnly(r.effective_from)}</td>
                <td className="py-2.5 text-right">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => startEdit(r)}
                    className="h-7 gap-1 text-[#737373] hover:text-[#0A0A0A]"
                  >
                    <Pencil className="h-3.5 w-3.5" />
                    Edit
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function Field({ label, error, children }) {
  return (
    <label className="block">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium mb-1.5">
        {label}
      </div>
      {children}
      {error && <div className="text-xs text-[#F43F5E] mt-1">{error}</div>}
    </label>
  );
}

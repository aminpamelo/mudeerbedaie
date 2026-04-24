import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowLeft,
  Check,
  ChevronDown,
  Loader2,
  Pencil,
  Plus,
  Sparkles,
  Trash2,
  X,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import CommissionTierTable from '@/livehost/components/CommissionTierTable';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { resolveTier, formatTierRange } from '@/livehost/utils/commissionTier';

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
    commissionTiers,
    auth,
  } = usePage().props;
  const canManageHosts = Boolean(auth?.permissions?.canManageHosts);
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
            {canManageHosts && (
              <Link href={`/livehost/hosts/${host.id}/edit`}>
                <Button variant="ghost" className="gap-1.5 text-[#0A0A0A]">
                  <Pencil className="w-3.5 h-3.5" />
                  Edit
                </Button>
              </Link>
            )}
            {canManageHosts && (
              <Button
                onClick={() => setConfirmDelete(true)}
                className="gap-1.5 bg-transparent text-[#F43F5E] border border-[#F43F5E] hover:bg-[#FFF1F2]"
              >
                <Trash2 className="w-3.5 h-3.5" />
                Delete
              </Button>
            )}
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
            ...(canManageHosts ? [{ id: 'commission', label: 'Commission' }] : []),
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

        {activeTab === 'commission' && canManageHosts && (
          <CommissionPanel
            host={host}
            commissionProfile={commissionProfile}
            commissionProfiles={commissionProfiles}
            platformCommissionRates={platformCommissionRates}
            platforms={platforms}
            uplineCandidates={uplineCandidates}
            commissionTiers={commissionTiers}
            formatDateOnly={formatDateOnly}
            formatMoney={formatMoney}
            formatPercent={formatPercent}
          />
        )}
      </div>

      {confirmDelete && canManageHosts && (
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

/**
 * LiveField — inline click-to-edit cell used across the pay ledger.
 * type: 'money' | 'percent' | 'number' — controls format + step.
 */
function LiveField({
  value,
  onSave,
  type = 'money',
  prefix = '',
  suffix = '',
  align = 'left',
  placeholder = '—',
  disabled = false,
  'aria-label': ariaLabel,
}) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(() => String(value ?? ''));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!editing) {
      setDraft(String(value ?? ''));
    }
  }, [value, editing]);

  const displayValue = useMemo(() => {
    if (value === null || value === undefined || value === '') {
      return placeholder;
    }
    const num = Number(value);
    if (type === 'money') {
      return `${prefix}${num.toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}${suffix}`;
    }
    if (type === 'percent') {
      const rounded = Math.round(num * 100) / 100;
      return `${rounded}${suffix || '%'}`;
    }
    return `${prefix}${num}${suffix}`;
  }, [value, type, prefix, suffix, placeholder]);

  const begin = () => {
    if (disabled) {
      return;
    }
    setDraft(value === null || value === undefined ? '' : String(value));
    setError(null);
    setEditing(true);
  };

  const commit = () => {
    const asNumber = Number(draft);
    if (Number.isNaN(asNumber)) {
      setError('Invalid number');
      return;
    }
    if (String(asNumber) === String(value ?? 0)) {
      setEditing(false);
      return;
    }

    setSaving(true);
    Promise.resolve(onSave(asNumber))
      .then(() => setEditing(false))
      .catch((err) => setError(typeof err === 'string' ? err : 'Save failed'))
      .finally(() => setSaving(false));
  };

  const handleKey = (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      commit();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      setEditing(false);
      setError(null);
    }
  };

  if (editing) {
    return (
      <div className={`flex items-center gap-1.5 ${align === 'right' ? 'justify-end' : ''}`}>
        {prefix && <span className="text-[#737373] text-[13px]">{prefix}</span>}
        <input
          autoFocus
          type="number"
          step="0.01"
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          onBlur={commit}
          onKeyDown={handleKey}
          aria-label={ariaLabel}
          className={`h-8 w-[96px] rounded-md border border-[#10B981] bg-white px-2 text-[14px] tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/30 ${align === 'right' ? 'text-right' : 'text-left'}`}
        />
        {suffix && <span className="text-[#737373] text-[13px]">{suffix}</span>}
        {saving && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#737373]" />}
        {error && <span className="text-[11px] text-[#DC2626]">{error}</span>}
      </div>
    );
  }

  return (
    <button
      type="button"
      onClick={begin}
      disabled={disabled || saving}
      aria-label={ariaLabel}
      className={`group inline-flex h-8 items-center gap-1.5 rounded-md px-1.5 text-[14px] tabular-nums text-[#0A0A0A] transition-colors hover:bg-[#F5F5F5] disabled:opacity-60 ${align === 'right' ? 'justify-end' : ''}`}
    >
      <span className="font-semibold tracking-[-0.01em]">{displayValue}</span>
      {saving && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#737373]" />}
    </button>
  );
}

/**
 * Reads on-page host state + derived rates and renders the Pay Ledger.
 * Editable fields save directly via the existing API (no batch submit).
 * A fallback "Save all" button still exists for setting upline + notes.
 */
function CommissionPanel({
  host,
  commissionProfile,
  commissionProfiles,
  platformCommissionRates,
  platforms,
  uplineCandidates,
  commissionTiers,
  formatDateOnly,
  formatMoney,
  formatPercent,
}) {
  const hasActive = Boolean(commissionProfile);

  const baseSalary = Number(commissionProfile?.base_salary_myr ?? 0);
  const perLiveRate = Number(commissionProfile?.per_live_rate_myr ?? 0);
  const l1Percent = Number(commissionProfile?.override_rate_l1_percent ?? 0);
  const l2Percent = Number(commissionProfile?.override_rate_l2_percent ?? 0);

  const primaryRate = useMemo(() => {
    const rates = platformCommissionRates ?? [];
    return (
      rates.find((r) => r.platform_slug === 'tiktok-shop') ??
      rates.find((r) => r.is_active !== false) ??
      rates[0] ??
      null
    );
  }, [platformCommissionRates]);

  const primaryPercent = primaryRate ? Number(primaryRate.commission_rate_percent) : 0;

  const [upline, setUpline] = useState(commissionProfile?.upline_user_id ?? '');
  const [notes, setNotes] = useState(commissionProfile?.notes ?? '');
  const [savingMeta, setSavingMeta] = useState(false);
  const [metaSuccess, setMetaSuccess] = useState(false);
  const [uplineSearch, setUplineSearch] = useState('');
  const [uplineOpen, setUplineOpen] = useState(false);
  const uplineRef = useRef(null);

  useEffect(() => {
    setUpline(commissionProfile?.upline_user_id ?? '');
    setNotes(commissionProfile?.notes ?? '');
  }, [commissionProfile?.upline_user_id, commissionProfile?.notes]);

  useEffect(() => {
    const onClick = (event) => {
      if (uplineRef.current && !uplineRef.current.contains(event.target)) {
        setUplineOpen(false);
      }
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const filteredUpline = useMemo(() => {
    const q = uplineSearch.trim().toLowerCase();
    if (!q) {
      return uplineCandidates;
    }
    return uplineCandidates.filter(
      (u) => u.name?.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q),
    );
  }, [uplineCandidates, uplineSearch]);

  const currentUpline = useMemo(
    () => uplineCandidates.find((u) => String(u.id) === String(upline)),
    [uplineCandidates, upline],
  );

  const saveProfileField = (patch) => {
    const method = hasActive ? 'put' : 'post';
    const payload = {
      base_salary_myr: Number(commissionProfile?.base_salary_myr ?? 0),
      per_live_rate_myr: Number(commissionProfile?.per_live_rate_myr ?? 0),
      upline_user_id: commissionProfile?.upline_user_id ?? null,
      override_rate_l1_percent: Number(commissionProfile?.override_rate_l1_percent ?? 0),
      override_rate_l2_percent: Number(commissionProfile?.override_rate_l2_percent ?? 0),
      notes: commissionProfile?.notes ?? null,
      ...patch,
    };

    return new Promise((resolve, reject) => {
      router[method](`/livehost/hosts/${host.id}/commission-profile`, payload, {
        preserveScroll: true,
        onSuccess: () => resolve(),
        onError: (errs) => {
          const firstErr = Object.values(errs || {})[0];
          reject(Array.isArray(firstErr) ? firstErr[0] : firstErr || 'Save failed');
        },
      });
    });
  };

  const handleSaveMeta = () => {
    setSavingMeta(true);
    setMetaSuccess(false);
    saveProfileField({
      upline_user_id: upline === '' ? null : Number(upline),
      notes: notes || null,
    })
      .then(() => setMetaSuccess(true))
      .finally(() => setSavingMeta(false));
  };

  const handleTierUpdate = (tierId, patch) => {
    router.patch(`/livehost/hosts/${host.id}/tiers/${tierId}`, patch, {
      preserveScroll: true,
    });
  };

  const handleTierRemove = (tierId) => {
    router.delete(`/livehost/hosts/${host.id}/tiers/${tierId}`, {
      preserveScroll: true,
    });
  };

  const handleAddTier = (group) => {
    const existing = [...(group.tiers || [])].sort(
      (a, b) => Number(a.tier_number) - Number(b.tier_number),
    );
    const topTier = existing[existing.length - 1];
    const nextTierNumber = (Number(topTier?.tier_number) || 0) + 1;
    const prevMax =
      topTier?.max_gmv_myr === null || topTier?.max_gmv_myr === undefined
        ? Number(topTier?.min_gmv_myr ?? 0)
        : Number(topTier?.max_gmv_myr);

    // Rebuild the schedule: existing tiers (with the previous open-ended tier
    // closed off at its current min) + a new top tier that inherits the null max.
    const tiers = existing.map((tier, index) => {
      const isLast = index === existing.length - 1;
      return {
        tier_number: Number(tier.tier_number),
        min_gmv_myr: Number(tier.min_gmv_myr ?? 0),
        max_gmv_myr: isLast
          ? prevMax
          : tier.max_gmv_myr === null || tier.max_gmv_myr === undefined
            ? null
            : Number(tier.max_gmv_myr),
        internal_percent: Number(tier.internal_percent ?? 0),
        l1_percent: Number(tier.l1_percent ?? 0),
        l2_percent: Number(tier.l2_percent ?? 0),
      };
    });

    tiers.push({
      tier_number: nextTierNumber,
      min_gmv_myr: prevMax,
      max_gmv_myr: null,
      internal_percent: 0,
      l1_percent: 0,
      l2_percent: 0,
    });

    router.post(
      `/livehost/hosts/${host.id}/platforms/${group.platform_id}/tiers`,
      { effective_from: group.effective_from, tiers },
      { preserveScroll: true },
    );
  };

  const handleAddPlatformSchedule = (platformId, effectiveFrom) => {
    return new Promise((resolve, reject) => {
      router.post(
        `/livehost/hosts/${host.id}/platforms/${platformId}/tiers`,
        {
          effective_from: effectiveFrom,
          tiers: [
            {
              tier_number: 1,
              min_gmv_myr: 0,
              max_gmv_myr: null,
              internal_percent: 0,
              l1_percent: 0,
              l2_percent: 0,
            },
          ],
        },
        {
          preserveScroll: true,
          onSuccess: () => resolve(),
          onError: (errs) => {
            const firstErr = Object.values(errs || {})[0];
            reject(Array.isArray(firstErr) ? firstErr[0] : firstErr || 'Save failed');
          },
        },
      );
    });
  };

  const history = (commissionProfiles || []).filter((p) => !p.is_active);

  return (
    <div className="space-y-6">
      <HeroFormula
        baseSalary={baseSalary}
        perLiveRate={perLiveRate}
        primaryPercent={primaryPercent}
        primaryPlatform={primaryRate?.platform_name ?? primaryRate?.platform_slug ?? 'TikTok Shop'}
        l1Percent={l1Percent}
        l2Percent={l2Percent}
        effectiveFrom={commissionProfile?.effective_from}
        formatDateOnly={formatDateOnly}
        hasActive={hasActive}
      />

      <LedgerSection
        index="01"
        title="Fixed pay"
        subtitle="Guaranteed every payroll regardless of performance."
        accent="#10B981"
      >
        <LedgerRow
          label="Base salary"
          description="Flat monthly amount. Unaffected by session count or GMV."
          field={
            <LiveField
              value={baseSalary}
              type="money"
              prefix="RM "
              align="right"
              aria-label="Base salary"
              onSave={(v) => saveProfileField({ base_salary_myr: v })}
            />
          }
        />
        <LedgerRow
          label="Per-live rate"
          description="Paid once for each completed live session within the period."
          field={
            <LiveField
              value={perLiveRate}
              type="money"
              prefix="RM "
              suffix=" / live"
              align="right"
              aria-label="Per live rate"
              onSave={(v) => saveProfileField({ per_live_rate_myr: v })}
            />
          }
        />
      </LedgerSection>

      <LedgerSection
        index="02"
        title="Commission tiers"
        subtitle="Per-platform tiered commission ladder scaled by monthly GMV. Internal %, L1 %, and L2 % per bracket."
        accent="#EC4899"
        action={
          <AddTierScheduleButton
            platforms={platforms}
            existingGroups={commissionTiers}
            onAdd={handleAddPlatformSchedule}
          />
        }
      >
        {(commissionTiers || []).length === 0 ? (
          <div className="px-5 py-6">
            <div className="rounded-lg border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-4 py-6 text-center text-[13px] text-[#737373]">
              No commission tier schedule yet. <strong>Add a platform</strong> above to set up tier-based commission.
            </div>
          </div>
        ) : (
          <div className="space-y-4 px-5 py-5">
            {commissionTiers.map((group) => (
              <CommissionTierTable
                key={`${group.platform_id}-${group.effective_from}`}
                platform={group.platform}
                effectiveFrom={group.effective_from}
                tiers={group.tiers}
                onEditRow={handleTierUpdate}
                onAddTier={() => handleAddTier(group)}
                onRemoveTier={handleTierRemove}
                readOnly={false}
              />
            ))}
          </div>
        )}
      </LedgerSection>

      <MonthlyProjection
        baseSalary={baseSalary}
        perLiveRate={perLiveRate}
        commissionTiers={commissionTiers}
        formatMoney={formatMoney}
      />

      {/* Upline + notes (less frequently edited — batched save) */}
      <LedgerSection
        index="03"
        title="Hierarchy & Notes"
        subtitle="Set this host's upline so L1/L2 overrides flow up the chain."
        accent="#8B5CF6"
        allowOverflow
        action={
          <div className="flex items-center gap-2">
            {metaSuccess && (
              <div className="flex items-center gap-1.5 rounded-full bg-[#ECFDF5] px-2.5 py-1 text-[11.5px] font-medium text-[#065F46]">
                <Check className="h-3 w-3" strokeWidth={3} />
                Saved
              </div>
            )}
            <Button
              type="button"
              onClick={handleSaveMeta}
              disabled={savingMeta}
              size="sm"
              className="bg-[#0A0A0A] text-white hover:bg-[#262626]"
            >
              {savingMeta ? 'Saving…' : hasActive ? 'Save' : 'Create profile'}
            </Button>
          </div>
        }
      >
        <div className="p-5">
        <div className="grid grid-cols-2 gap-4">
          <div className="relative" ref={uplineRef}>
            <div className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Upline (optional)
            </div>
            <button
              type="button"
              onClick={() => setUplineOpen((v) => !v)}
              className="flex h-10 w-full items-center justify-between rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] hover:bg-[#FAFAFA] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <span className="flex items-center gap-2 truncate">
                {currentUpline ? (
                  <>
                    <span className="inline-grid h-6 w-6 place-items-center rounded-full bg-gradient-to-br from-[#8B5CF6] to-[#EC4899] text-[10px] font-semibold text-white">
                      {(currentUpline.name || '?').slice(0, 1).toUpperCase()}
                    </span>
                    <span className="truncate font-medium">{currentUpline.name}</span>
                    <span className="truncate text-[#737373]">· {currentUpline.email}</span>
                  </>
                ) : (
                  <span className="text-[#737373]">— No upline —</span>
                )}
              </span>
              <ChevronDown className="h-4 w-4 text-[#737373]" />
            </button>
            {uplineOpen && (
              <div className="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-[#EAEAEA] bg-white shadow-[0_10px_30px_rgba(0,0,0,0.08)]">
                <div className="border-b border-[#F0F0F0] p-2">
                  <Input
                    autoFocus
                    value={uplineSearch}
                    onChange={(e) => setUplineSearch(e.target.value)}
                    placeholder="Search other live hosts…"
                    className="h-8 border-0 bg-[#FAFAFA] text-[13px] shadow-none focus-visible:ring-0"
                  />
                </div>
                <div className="max-h-[240px] overflow-y-auto p-1">
                  <button
                    type="button"
                    onClick={() => {
                      setUpline('');
                      setUplineOpen(false);
                    }}
                    className="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[13px] text-[#737373] hover:bg-[#F5F5F5]"
                  >
                    <span>— No upline —</span>
                    {!upline && <Check className="h-3.5 w-3.5 text-[#10B981]" />}
                  </button>
                  {filteredUpline.length === 0 && (
                    <div className="px-2.5 py-3 text-center text-[12px] text-[#737373]">
                      No matches.
                    </div>
                  )}
                  {filteredUpline.map((u) => (
                    <button
                      key={u.id}
                      type="button"
                      onClick={() => {
                        setUpline(String(u.id));
                        setUplineOpen(false);
                        setUplineSearch('');
                      }}
                      className="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[13px] hover:bg-[#F5F5F5]"
                    >
                      <span className="flex items-center gap-2">
                        <span className="inline-grid h-6 w-6 place-items-center rounded-full bg-gradient-to-br from-[#8B5CF6] to-[#EC4899] text-[10px] font-semibold text-white">
                          {(u.name || '?').slice(0, 1).toUpperCase()}
                        </span>
                        <span className="font-medium text-[#0A0A0A]">{u.name}</span>
                        <span className="text-[#737373]">· {u.email}</span>
                      </span>
                      {String(u.id) === String(upline) && (
                        <Check className="h-3.5 w-3.5 text-[#10B981]" />
                      )}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>

          <div>
            <div className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Notes
            </div>
            <textarea
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Context for this commission structure…"
              className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
        </div>
        </div>
      </LedgerSection>

      <HistoryTimeline
        history={history}
        activeFrom={commissionProfile?.effective_from}
        formatDateOnly={formatDateOnly}
        formatMoney={formatMoney}
        formatPercent={formatPercent}
      />
    </div>
  );
}

function HeroFormula({
  baseSalary,
  perLiveRate,
  primaryPercent,
  primaryPlatform,
  l1Percent,
  l2Percent,
  effectiveFrom,
  formatDateOnly,
  hasActive,
}) {
  return (
    <div className="relative overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-gradient-to-br from-white via-white to-[#FAFAFA] p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div
        className="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full opacity-[0.35] blur-3xl"
        style={{
          background:
            'radial-gradient(circle, rgba(16,185,129,0.35), rgba(236,72,153,0.18) 60%, transparent)',
        }}
      />
      <div className="relative flex items-start justify-between">
        <div>
          <div className="flex items-center gap-2 text-[11.5px] font-medium uppercase tracking-[0.12em] text-[#737373]">
            <Sparkles className="h-3 w-3" strokeWidth={2.5} />
            Pay structure
          </div>
          <div className="mt-2 text-[13px] text-[#525252]">
            {hasActive ? (
              <>
                Active since{' '}
                <span className="font-medium text-[#0A0A0A]">
                  {formatDateOnly(effectiveFrom)}
                </span>
              </>
            ) : (
              'No active profile yet — values below are defaults.'
            )}
          </div>
        </div>
      </div>

      <div className="relative mt-5 flex flex-wrap items-baseline gap-x-2 gap-y-3 font-mono text-[15px] tabular-nums text-[#0A0A0A]">
        <FormulaPill tone="emerald">
          <FormulaValue value={`RM ${baseSalary.toLocaleString('en-MY')}`} label="base" />
        </FormulaPill>
        <span className="text-[#A3A3A3]">+</span>
        <FormulaPill tone="emerald">
          <FormulaValue value={`RM ${perLiveRate}`} label="per live" />
        </FormulaPill>
        <span className="text-[#A3A3A3]">+</span>
        <FormulaPill tone="rose">
          <FormulaValue value={`${primaryPercent}%`} label={`× ${primaryPlatform} GMV`} />
        </FormulaPill>
        {(l1Percent > 0 || l2Percent > 0) && (
          <>
            <span className="text-[#A3A3A3]">+</span>
            <FormulaPill tone="amber">
              <FormulaValue
                value={`${l1Percent}% / ${l2Percent}%`}
                label="L1 / L2 override"
              />
            </FormulaPill>
          </>
        )}
      </div>
    </div>
  );
}

function FormulaPill({ tone = 'emerald', children }) {
  const tones = {
    emerald: 'bg-[#ECFDF5] text-[#065F46] ring-[#A7F3D0]',
    rose: 'bg-[#FDF2F8] text-[#9D174D] ring-[#FBCFE8]',
    amber: 'bg-[#FFFBEB] text-[#92400E] ring-[#FDE68A]',
  };
  return (
    <span
      className={`inline-flex items-center gap-2 rounded-lg px-3 py-1.5 ring-1 ring-inset ${tones[tone] ?? tones.emerald}`}
    >
      {children}
    </span>
  );
}

function FormulaValue({ value, label }) {
  return (
    <span className="flex items-baseline gap-1.5">
      <span className="font-semibold">{value}</span>
      <span className="font-sans text-[10.5px] uppercase tracking-[0.08em] opacity-70">
        {label}
      </span>
    </span>
  );
}

function LedgerSection({ index, title, subtitle, accent, action, children, allowOverflow = false }) {
  return (
    <section className={`${allowOverflow ? 'overflow-visible' : 'overflow-hidden'} rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]`}>
      <header className="flex items-start justify-between gap-4 border-b border-[#F0F0F0] px-5 py-4">
        <div className="flex items-start gap-3">
          <div
            className="mt-[2px] font-mono text-[10.5px] font-medium tracking-[0.12em] tabular-nums"
            style={{ color: accent }}
          >
            {index}
          </div>
          <div>
            <h3 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
              {title}
            </h3>
            {subtitle && <p className="mt-0.5 text-[12.5px] text-[#737373]">{subtitle}</p>}
          </div>
        </div>
        {action}
      </header>
      <div>{children}</div>
    </section>
  );
}

function LedgerRow({ label, description, field }) {
  return (
    <div className="flex items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-[#FAFAFA]">
      <div className="min-w-0">
        <div className="text-[14px] font-medium tracking-[-0.01em] text-[#0A0A0A]">{label}</div>
        {description && (
          <div className="mt-0.5 text-[12px] leading-relaxed text-[#737373]">{description}</div>
        )}
      </div>
      <div className="shrink-0">{field}</div>
    </div>
  );
}

function AddTierScheduleButton({ platforms, existingGroups, onAdd }) {
  const [open, setOpen] = useState(false);
  const [platformId, setPlatformId] = useState('');
  const [effectiveFrom, setEffectiveFrom] = useState(() =>
    new Date().toISOString().slice(0, 10),
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const usedPlatformIds = new Set((existingGroups || []).map((g) => g.platform_id));
  const availablePlatforms = (platforms || []).filter((p) => !usedPlatformIds.has(p.id));

  const handleSubmit = (event) => {
    event.preventDefault();
    if (!platformId || !effectiveFrom) {
      setError('Pick a platform and effective date.');
      return;
    }
    setBusy(true);
    setError(null);
    onAdd(Number(platformId), effectiveFrom)
      .then(() => {
        setOpen(false);
        setPlatformId('');
      })
      .catch((err) => setError(err ?? 'Save failed'))
      .finally(() => setBusy(false));
  };

  if (!open) {
    return (
      <Button
        type="button"
        size="sm"
        onClick={() => {
          setOpen(true);
          setPlatformId(String(availablePlatforms[0]?.id ?? ''));
        }}
        className="h-8 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]"
        disabled={availablePlatforms.length === 0}
      >
        <Plus className="h-3.5 w-3.5" strokeWidth={2.5} />
        Add platform
      </Button>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="flex items-center gap-2 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-2 py-1.5"
    >
      <select
        value={platformId}
        onChange={(event) => setPlatformId(event.target.value)}
        className="h-8 rounded-md border border-[#EAEAEA] bg-white px-2 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
      >
        {availablePlatforms.map((p) => (
          <option key={p.id} value={p.id}>
            {p.name}
          </option>
        ))}
      </select>
      <input
        type="date"
        value={effectiveFrom}
        onChange={(event) => setEffectiveFrom(event.target.value)}
        className="h-8 rounded-md border border-[#EAEAEA] bg-white px-2 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
        aria-label="Effective from"
      />
      <button
        type="submit"
        disabled={busy}
        className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[#10B981] text-white hover:bg-[#059669] disabled:opacity-50"
      >
        {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" strokeWidth={3} />}
      </button>
      <button
        type="button"
        onClick={() => {
          setOpen(false);
          setError(null);
        }}
        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-white hover:text-[#0A0A0A]"
      >
        <X className="h-3.5 w-3.5" />
      </button>
      {error && <span className="text-[11px] text-[#DC2626]">{error}</span>}
    </form>
  );
}

function MonthlyProjection({ baseSalary, perLiveRate, commissionTiers, formatMoney }) {
  const groups = Array.isArray(commissionTiers) ? commissionTiers : [];
  const [sessions, setSessions] = useState(20);
  const [gmv, setGmv] = useState(50000);
  const [selectedPlatformId, setSelectedPlatformId] = useState(() =>
    groups.length > 0 ? groups[0].platform_id : null,
  );

  const activeGroup = useMemo(() => {
    if (groups.length === 0) {
      return null;
    }
    return groups.find((g) => g.platform_id === selectedPlatformId) ?? groups[0];
  }, [groups, selectedPlatformId]);

  const activeTiers = activeGroup?.tiers ?? [];
  const platformLabel =
    activeGroup?.platform?.name ?? activeGroup?.platform?.slug ?? 'Platform';

  const matchedTier = useMemo(() => resolveTier(activeTiers, gmv), [activeTiers, gmv]);

  const perLivePay = perLiveRate * sessions;
  const internalPercent = matchedTier ? Number(matchedTier.internal_percent) : 0;
  const l1Percent = matchedTier ? Number(matchedTier.l1_percent) : 0;
  const l2Percent = matchedTier ? Number(matchedTier.l2_percent) : 0;
  const performancePay = (gmv * internalPercent) / 100;
  const l1Generated = (gmv * l1Percent) / 100;
  const l2Generated = (gmv * l2Percent) / 100;
  const total = baseSalary + perLivePay + performancePay;

  const hasSchedule = activeTiers.length > 0;
  const belowFloor = hasSchedule && !matchedTier;

  return (
    <section className="overflow-hidden rounded-[16px] border border-[#0A0A0A] bg-[#0A0A0A] text-white shadow-[0_8px_24px_rgba(0,0,0,0.15)]">
      <header className="flex items-center justify-between border-b border-white/10 px-5 py-4">
        <div className="flex items-center gap-3">
          <div className="font-mono text-[10.5px] font-medium tracking-[0.12em] text-[#10B981]">
            PROJ
          </div>
          <div>
            <h3 className="text-[15px] font-semibold tracking-[-0.015em]">Monthly projection</h3>
            <p className="mt-0.5 text-[12.5px] text-white/60">
              Tune the inputs to see estimated take-home.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          {groups.length > 1 && (
            <select
              value={activeGroup?.platform_id ?? ''}
              onChange={(e) => setSelectedPlatformId(Number(e.target.value))}
              className="h-8 rounded-md border border-white/20 bg-white/5 px-2 text-[12px] text-white focus:outline-none focus:ring-2 focus:ring-[#10B981]/40"
              aria-label="Projection platform"
            >
              {groups.map((g) => (
                <option key={g.platform_id} value={g.platform_id} className="text-black">
                  {g.platform?.name ?? g.platform?.slug ?? `Platform #${g.platform_id}`}
                </option>
              ))}
            </select>
          )}
          <div className="text-right">
            <div className="text-[10.5px] font-medium uppercase tracking-[0.1em] text-white/50">
              Total
            </div>
            <div className="font-mono text-[28px] font-semibold leading-none tracking-[-0.02em] tabular-nums text-white">
              RM {formatMoney(Math.round(total))}
            </div>
          </div>
        </div>
      </header>

      <div className="grid grid-cols-[1fr_1px_1fr] gap-5 px-5 py-5">
        <ProjectionInput
          label="Live sessions"
          hint="completed this period"
          value={sessions}
          min={0}
          max={60}
          onChange={setSessions}
          footer={`≈ RM ${formatMoney(Math.round(perLivePay))} per-live pay`}
        />
        <div className="bg-white/10" />
        <ProjectionInput
          label={`${platformLabel} GMV`}
          hint="attributed to this host"
          value={gmv}
          min={0}
          max={500000}
          step={1000}
          prefix="RM "
          onChange={setGmv}
          badge={
            <TierBadge
              tier={matchedTier}
              hasSchedule={hasSchedule}
              belowFloor={belowFloor}
            />
          }
          footer={
            !hasSchedule
              ? 'No tier schedule configured for this platform'
              : belowFloor
                ? 'Below Tier 1 — no performance commission'
                : `≈ RM ${formatMoney(Math.round(performancePay))} performance pay`
          }
        />
      </div>

      <div className="border-t border-white/10 px-5 py-4">
        <div className="mb-3 text-[10.5px] font-medium uppercase tracking-[0.1em] text-white/40">
          Breakdown
        </div>
        <div className="space-y-2 text-[12.5px]">
          <BreakdownRow
            label="Base salary"
            value={`RM ${formatMoney(Math.round(baseSalary))}`}
          />
          <BreakdownRow
            label={`Per-live · ${sessions} session${sessions === 1 ? '' : 's'}`}
            value={`RM ${formatMoney(Math.round(perLivePay))}`}
          />
          <BreakdownRow
            label={
              matchedTier
                ? `Your earnings · ${internalPercent}% × GMV`
                : 'Your earnings · performance commission'
            }
            value={`RM ${formatMoney(Math.round(performancePay))}`}
            emphasize
          />
        </div>

        <div className="mt-4 border-t border-white/10 pt-3">
          <div className="mb-2 flex items-center gap-2 text-[10.5px] font-medium uppercase tracking-[0.1em] text-white/30">
            Informational · upline overrides generated
          </div>
          <div className="space-y-1.5 text-[12px]">
            <BreakdownRow
              label={`L1 upline override${matchedTier ? ` · ${l1Percent}% × GMV` : ''}`}
              value={`RM ${formatMoney(Math.round(l1Generated))}`}
              muted
            />
            <BreakdownRow
              label={`L2 upline override${matchedTier ? ` · ${l2Percent}% × GMV` : ''}`}
              value={`RM ${formatMoney(Math.round(l2Generated))}`}
              muted
            />
          </div>
        </div>
      </div>

      <footer className="grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 text-[12px]">
        <ProjectionLeg label="Base" value={`RM ${formatMoney(baseSalary)}`} />
        <ProjectionLeg label="Per-live" value={`RM ${formatMoney(Math.round(perLivePay))}`} />
        <ProjectionLeg
          label={matchedTier ? `Tier ${matchedTier.tier_number}` : 'Performance'}
          value={`RM ${formatMoney(Math.round(performancePay))}`}
        />
      </footer>
    </section>
  );
}

function TierBadge({ tier, hasSchedule, belowFloor }) {
  if (!hasSchedule) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-white/15 bg-white/5 px-2 py-0.5 font-mono text-[10.5px] uppercase tracking-[0.06em] text-white/50">
        No schedule
      </span>
    );
  }
  if (belowFloor || !tier) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full border border-white/15 bg-white/5 px-2 py-0.5 font-mono text-[10.5px] uppercase tracking-[0.06em] text-white/50">
        Below Tier 1
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full border border-[#10B981]/40 bg-[#10B981]/15 px-2 py-0.5 font-mono text-[10.5px] font-medium tracking-[0.04em] text-[#34D399]">
      <span>T{tier.tier_number}</span>
      <span className="text-white/60">·</span>
      <span>{formatTierRange(tier)}</span>
      <span className="text-white/60">·</span>
      <span>{Number(tier.internal_percent)}%</span>
    </span>
  );
}

function BreakdownRow({ label, value, emphasize = false, muted = false }) {
  return (
    <div className="flex items-baseline justify-between gap-3">
      <div
        className={`truncate ${
          muted ? 'text-white/40' : emphasize ? 'text-white' : 'text-white/70'
        }`}
      >
        {label}
      </div>
      <div
        className={`shrink-0 font-mono tabular-nums ${
          muted
            ? 'text-white/50'
            : emphasize
              ? 'text-[#34D399]'
              : 'text-white'
        }`}
      >
        {value}
      </div>
    </div>
  );
}

function ProjectionInput({ label, hint, value, min = 0, max = 100, step = 1, prefix = '', onChange, footer, badge }) {
  const percent = max === min ? 0 : Math.min(100, Math.max(0, ((value - min) / (max - min)) * 100));
  return (
    <div>
      <div className="flex items-baseline justify-between">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <div className="text-[11.5px] font-medium uppercase tracking-[0.08em] text-white/60">
              {label}
            </div>
            {badge}
          </div>
          <div className="mt-0.5 text-[11px] text-white/40">{hint}</div>
        </div>
        <div className="flex items-baseline gap-1 font-mono text-[20px] font-semibold tabular-nums">
          {prefix && <span className="text-white/60 text-[14px]">{prefix}</span>}
          <input
            type="number"
            value={value}
            min={min}
            max={max}
            step={step}
            onChange={(e) => onChange(Number(e.target.value) || 0)}
            className="w-[112px] bg-transparent text-right outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
          />
        </div>
      </div>
      <div className="relative mt-3 h-1 rounded-full bg-white/10">
        <div
          className="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-[#10B981] to-[#34D399]"
          style={{ width: `${percent}%` }}
        />
        <input
          type="range"
          min={min}
          max={max}
          step={step}
          value={value}
          onChange={(e) => onChange(Number(e.target.value))}
          className="absolute inset-0 h-1 w-full cursor-pointer opacity-0"
          aria-label={label}
        />
        <div
          className="pointer-events-none absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full bg-white shadow"
          style={{ left: `calc(${percent}% - 6px)` }}
        />
      </div>
      <div className="mt-2 text-[11px] text-white/50">{footer}</div>
    </div>
  );
}

function ProjectionLeg({ label, value }) {
  return (
    <div className="px-5 py-3">
      <div className="text-[10.5px] font-medium uppercase tracking-[0.1em] text-white/40">
        {label}
      </div>
      <div className="mt-1 font-mono text-[14px] tabular-nums text-white">{value}</div>
    </div>
  );
}

function HistoryTimeline({ history, activeFrom, formatDateOnly, formatMoney, formatPercent }) {
  if (!history || history.length === 0) {
    return (
      <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="flex items-center gap-2 text-[13px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
          Revision history
        </div>
        <div className="mt-4 text-[13px] text-[#737373]">No prior revisions yet.</div>
      </div>
    );
  }

  // Oldest → newest, so diff from previous makes chronological sense.
  const chronological = [...history].sort((a, b) =>
    String(a.effective_from).localeCompare(String(b.effective_from)),
  );

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4 flex items-center gap-2 text-[13px] font-semibold uppercase tracking-[0.08em] text-[#737373]">
        Revision history
      </div>
      <ol className="relative space-y-5 pl-6 before:absolute before:left-[7px] before:top-1 before:bottom-1 before:w-px before:bg-[#EAEAEA]">
        {[...chronological].reverse().map((entry, index, arr) => {
          const prev = arr[index + 1];
          const diff = prev ? summarizeDiff(prev, entry) : [];
          const isLatest = index === 0;
          return (
            <li key={entry.id} className="relative">
              <span
                className={`absolute -left-6 top-1 h-3.5 w-3.5 rounded-full border-2 ${
                  isLatest ? 'border-[#10B981] bg-white' : 'border-[#EAEAEA] bg-white'
                }`}
              />
              <div className="flex items-baseline justify-between gap-4">
                <div className="min-w-0">
                  <div className="text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                    RM {formatMoney(entry.base_salary_myr)} + RM {formatMoney(entry.per_live_rate_myr)}
                    /live
                  </div>
                  <div className="mt-0.5 text-[11.5px] text-[#737373]">
                    {formatDateOnly(entry.effective_from)} → {formatDateOnly(entry.effective_to)}
                    {entry.upline_name ? ` · upline ${entry.upline_name}` : ''}
                  </div>
                </div>
                <div className="shrink-0 font-mono text-[11.5px] tabular-nums text-[#737373]">
                  L1 {formatPercent(entry.override_rate_l1_percent)} · L2{' '}
                  {formatPercent(entry.override_rate_l2_percent)}
                </div>
              </div>
              {diff.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {diff.map((d, i) => (
                    <span
                      key={i}
                      className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium ${
                        d.kind === 'up'
                          ? 'bg-[#ECFDF5] text-[#065F46]'
                          : d.kind === 'down'
                            ? 'bg-[#FEF2F2] text-[#991B1B]'
                            : 'bg-[#F5F5F5] text-[#525252]'
                      }`}
                    >
                      {d.label}
                    </span>
                  ))}
                </div>
              )}
            </li>
          );
        })}
        {activeFrom && (
          <li className="relative text-[11.5px] text-[#737373]">
            <span className="absolute -left-6 top-1 h-3.5 w-3.5 rounded-full border-2 border-[#D4D4D4] bg-white" />
            Started {formatDateOnly(activeFrom)}
          </li>
        )}
      </ol>
    </div>
  );
}

function summarizeDiff(prev, curr) {
  const out = [];
  const diffNum = (key, label, suffix = '') => {
    const p = Number(prev[key] ?? 0);
    const c = Number(curr[key] ?? 0);
    if (p === c) {
      return;
    }
    const delta = c - p;
    const kind = delta > 0 ? 'up' : 'down';
    const arrow = delta > 0 ? '↑' : '↓';
    out.push({ kind, label: `${label} ${arrow} ${Math.abs(delta)}${suffix}` });
  };
  diffNum('base_salary_myr', 'base', '');
  diffNum('per_live_rate_myr', 'per-live', '');
  diffNum('override_rate_l1_percent', 'L1', '%');
  diffNum('override_rate_l2_percent', 'L2', '%');
  if ((prev.upline_user_id ?? null) !== (curr.upline_user_id ?? null)) {
    out.push({ kind: 'neutral', label: 'upline changed' });
  }
  return out;
}


import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Download, Loader2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

function formatCurrency(value) {
  const num = Number(value || 0);
  return `RM ${num.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
}

function formatPercent(value) {
  const num = Number(value || 0);
  // Trim trailing zeros after decimal
  const rounded = Math.round(num * 100) / 100;
  return `${rounded}%`;
}

/**
 * Inline-editable cell.
 *
 * `type` controls the displayed formatter: 'money' | 'percent' | 'plain'.
 * `onSave(newValue)` must return a Promise-like (uses Inertia router callbacks).
 * While the cell is saving we render a subtle spinner instead of the value.
 */
/**
 * Inline-editable cell.
 *
 * `align` matches the parent `<td>`'s text alignment so the value visually
 * sits on the same axis as the column header (right-align for numeric
 * columns, left-align for text). Both idle and editing states occupy the
 * same box so entering edit mode doesn't shift the layout.
 */
function EditableCell({
  value,
  type = 'plain',
  suffix = '',
  onSave,
  disabled = false,
  placeholder = '—',
  align = 'right',
}) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(() => String(value ?? ''));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  const display = useMemo(() => {
    if (value === null || value === undefined || value === '') {
      return placeholder;
    }
    if (type === 'money') {
      return formatCurrency(value);
    }
    if (type === 'percent') {
      return formatPercent(value);
    }
    return `${value}${suffix}`;
  }, [value, type, placeholder, suffix]);

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
    if (type !== 'plain' && Number.isNaN(asNumber)) {
      setError('Invalid number');
      return;
    }

    const nextValue = type === 'plain' ? draft : asNumber;
    // Skip save if value unchanged.
    if (String(nextValue) === String(value ?? '')) {
      setEditing(false);
      return;
    }

    setSaving(true);
    Promise.resolve(onSave(nextValue))
      .then(() => {
        setEditing(false);
      })
      .catch((err) => {
        setError(typeof err === 'string' ? err : 'Save failed');
      })
      .finally(() => {
        setSaving(false);
      });
  };

  const cancel = () => {
    setEditing(false);
    setError(null);
  };

  const handleKeyDown = (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      commit();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      cancel();
    }
  };

  const alignClass = align === 'right' ? 'text-right justify-end' : 'text-left justify-start';

  if (editing) {
    return (
      <div className={`flex items-center gap-1.5 ${align === 'right' ? 'justify-end' : ''}`}>
        <input
          autoFocus
          type={type === 'plain' ? 'text' : 'number'}
          step={type === 'percent' ? '0.01' : '0.01'}
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          onBlur={commit}
          onKeyDown={handleKeyDown}
          className={`h-7 w-[88px] rounded-md border border-[#10B981] bg-white px-2 text-[13px] tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/30 ${align === 'right' ? 'text-right' : 'text-left'}`}
        />
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
      className={`group inline-flex h-7 items-center gap-1.5 rounded-md px-2 text-[13px] tabular-nums text-[#0A0A0A] transition-colors hover:bg-[#F5F5F5] disabled:opacity-60 ${alignClass}`}
      title={disabled ? 'Not editable' : 'Click to edit'}
    >
      <span>{display}</span>
      {saving && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#737373]" />}
    </button>
  );
}

export default function CommissionIndex() {
  const { hosts, platforms } = usePage().props;

  const [flash, setFlash] = useState(null);

  const defaultPlatformId = useMemo(() => {
    const tiktok = (platforms ?? []).find((p) => p.slug === 'tiktok-shop');
    return tiktok?.id ?? platforms?.[0]?.id ?? null;
  }, [platforms]);

  const saveProfileField = (host, field, nextValue) => {
    // Profile uses PUT if one exists, POST otherwise. Send the full required
    // payload (backend FormRequest validates every key), derived from the
    // current row + the one field the user just changed.
    const method = host.has_profile ? 'put' : 'post';
    const payload = {
      base_salary_myr: Number(host.base_salary_myr || 0),
      per_live_rate_myr: Number(host.per_live_rate_myr || 0),
      upline_user_id: host.upline_user_id ?? null,
      override_rate_l1_percent: Number(host.override_rate_l1_percent || 0),
      override_rate_l2_percent: Number(host.override_rate_l2_percent || 0),
    };
    payload[field] = Number(nextValue);

    return new Promise((resolve, reject) => {
      router[method](`/livehost/hosts/${host.id}/commission-profile`, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setFlash({ kind: 'success', message: `Updated ${host.name}.` });
          resolve();
        },
        onError: (errs) => {
          const firstErr = Object.values(errs || {})[0];
          reject(Array.isArray(firstErr) ? firstErr[0] : firstErr || 'Save failed');
        },
      });
    });
  };

  const savePlatformRate = (host, nextValue) => {
    const platformId = host.primary_platform_id ?? defaultPlatformId;
    if (!platformId) {
      return Promise.reject('No platform configured yet.');
    }

    const payload = {
      platform_id: platformId,
      commission_rate_percent: Number(nextValue),
    };

    const path = host.primary_platform_rate_id
      ? `/livehost/hosts/${host.id}/platform-rates/${host.primary_platform_rate_id}`
      : `/livehost/hosts/${host.id}/platform-rates`;
    const method = host.primary_platform_rate_id ? 'put' : 'post';

    return new Promise((resolve, reject) => {
      router[method](path, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setFlash({ kind: 'success', message: `Updated ${host.name} rate.` });
          resolve();
        },
        onError: (errs) => {
          const firstErr = Object.values(errs || {})[0];
          reject(Array.isArray(firstErr) ? firstErr[0] : firstErr || 'Save failed');
        },
      });
    });
  };

  return (
    <>
      <Head title="Commission Overview" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Commission']}
        actions={
          <a href="/livehost/commission/export">
            <Button
              size="sm"
              variant="outline"
              className="h-9 gap-1.5 rounded-lg border-[#EAEAEA] bg-white text-[#0A0A0A] hover:bg-[#F5F5F5]"
            >
              <Download className="h-[13px] w-[13px]" strokeWidth={2.5} />
              Export CSV
            </Button>
          </a>
        }
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Commission Overview
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              One row per live host. Click any cell to edit — Enter or blur saves.
            </p>
          </div>
        </div>

        {flash && (
          <div
            className={`rounded-lg border px-4 py-2.5 text-sm ${
              flash.kind === 'success'
                ? 'border-[#BBF7D0] bg-[#F0FDF4] text-[#166534]'
                : 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]'
            }`}
          >
            {flash.message}
          </div>
        )}

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {hosts.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">No live hosts yet.</div>
          ) : (
            <table className="w-full text-sm table-fixed">
              <colgroup>
                <col style={{ width: '32%' }} />
                <col style={{ width: '14%' }} />
                <col style={{ width: '10%' }} />
                <col style={{ width: '12%' }} />
                <col style={{ width: '18%' }} />
                <col style={{ width: '14%' }} />
              </colgroup>
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
                  <th className="px-5 py-3 text-left">Host</th>
                  <th className="px-5 py-3 text-right">Base Salary</th>
                  <th className="px-5 py-3 text-right">%</th>
                  <th className="px-5 py-3 text-right">Per-Live</th>
                  <th className="px-5 py-3 text-left">Upline</th>
                  <th className="px-5 py-3 text-right">L1 / L2</th>
                </tr>
              </thead>
              <tbody>
                {hosts.map((host) => (
                  <tr
                    key={host.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                  >
                    <td className="px-5 py-3.5 align-middle">
                      <div className="font-medium text-[#0A0A0A]">{host.name}</div>
                      <div className="truncate text-[11.5px] text-[#737373]">{host.email}</div>
                    </td>
                    <td className="px-3 py-3.5 align-middle text-right">
                      <EditableCell
                        value={host.base_salary_myr}
                        type="money"
                        align="right"
                        onSave={(v) => saveProfileField(host, 'base_salary_myr', v)}
                      />
                    </td>
                    <td className="px-3 py-3.5 align-middle text-right">
                      <EditableCell
                        value={host.primary_platform_rate_percent}
                        type="percent"
                        align="right"
                        onSave={(v) => savePlatformRate(host, v)}
                        disabled={!host.primary_platform_id && !defaultPlatformId}
                      />
                    </td>
                    <td className="px-3 py-3.5 align-middle text-right">
                      <EditableCell
                        value={host.per_live_rate_myr}
                        type="money"
                        align="right"
                        onSave={(v) => saveProfileField(host, 'per_live_rate_myr', v)}
                      />
                    </td>
                    <td className="px-5 py-3.5 align-middle">
                      <span className="text-[13px] text-[#0A0A0A]">
                        {host.upline_name ?? <span className="text-[#737373]">—</span>}
                      </span>
                    </td>
                    <td className="px-3 py-3.5 align-middle text-right">
                      <div className="flex items-center justify-end gap-0.5 tabular-nums">
                        <EditableCell
                          value={host.override_rate_l1_percent}
                          type="percent"
                          align="right"
                          onSave={(v) => saveProfileField(host, 'override_rate_l1_percent', v)}
                        />
                        <span className="text-[#D4D4D4]">/</span>
                        <EditableCell
                          value={host.override_rate_l2_percent}
                          type="percent"
                          align="right"
                          onSave={(v) => saveProfileField(host, 'override_rate_l2_percent', v)}
                        />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <p className="text-[12px] text-[#737373]">
          Tip: the upline column is read-only here — set uplines on the host detail page.
        </p>
      </div>
    </>
  );
}

CommissionIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

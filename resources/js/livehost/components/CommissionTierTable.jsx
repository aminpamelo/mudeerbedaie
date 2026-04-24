import { useEffect, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';

/**
 * CommissionTierTable — presentational tier ladder for one
 * (platform, effective_from) commission schedule.
 *
 * The parent owns persistence. Inline edits call `onEditRow(tierId, patch)`,
 * which the parent forwards to its Inertia `router.patch(...)` call.
 *
 * @typedef {Object} CommissionTier
 * @property {number} id
 * @property {number} tier_number
 * @property {number|string} min_gmv_myr
 * @property {number|string|null} max_gmv_myr
 * @property {number|string} internal_percent
 * @property {number|string} l1_percent
 * @property {number|string} l2_percent
 * @property {boolean} [is_active]
 *
 * @typedef {Object} CommissionTierTableProps
 * @property {{ id: number, name: string, slug?: string, icon_url?: string }} platform
 * @property {string} effectiveFrom                'YYYY-MM-DD'
 * @property {CommissionTier[]} tiers
 * @property {(tierId: number, patch: object) => void} onEditRow
 * @property {() => void} onAddTier
 * @property {(tierId: number) => void} onRemoveTier
 * @property {boolean} [readOnly]
 */

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
  if (value === null || value === undefined || value === '') {
    return '—';
  }
  const num = Number(value);
  if (!Number.isFinite(num)) {
    return '—';
  }
  return num.toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatPercent(value) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }
  const num = Number(value);
  if (!Number.isFinite(num)) {
    return '—';
  }
  const rounded = Math.round(num * 100) / 100;
  return `${rounded}%`;
}

/**
 * TierCellInput — controlled number input that commits via `onCommit(number|null)`
 * on blur or Enter. Esc discards. Designed for tabular, tight rows.
 *
 * Passing `allowNull=true` lets a blank value commit as `null` (used for the
 * open-ended top tier's `max_gmv_myr`).
 */
function TierCellInput({
  value,
  onCommit,
  allowNull = false,
  align = 'left',
  ariaLabel,
  placeholder,
  disabled = false,
  width = 'w-[96px]',
  step = '0.01',
  min = '0',
  max,
}) {
  const initialDraft = value === null || value === undefined ? '' : String(value);
  const [draft, setDraft] = useState(initialDraft);

  useEffect(() => {
    setDraft(value === null || value === undefined ? '' : String(value));
  }, [value]);

  const commit = () => {
    const trimmed = draft.trim();
    if (trimmed === '') {
      if (allowNull) {
        if (value !== null && value !== undefined) {
          onCommit(null);
        }
        return;
      }
      // Non-null field: revert to previous value on empty commit.
      setDraft(value === null || value === undefined ? '' : String(value));
      return;
    }
    const asNumber = Number(trimmed);
    if (Number.isNaN(asNumber)) {
      setDraft(value === null || value === undefined ? '' : String(value));
      return;
    }
    if (String(asNumber) === String(value ?? '')) {
      return;
    }
    onCommit(asNumber);
  };

  const handleKey = (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      event.currentTarget.blur();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      setDraft(value === null || value === undefined ? '' : String(value));
      event.currentTarget.blur();
    }
  };

  return (
    <input
      type="number"
      inputMode="decimal"
      step={step}
      min={min}
      max={max}
      value={draft}
      disabled={disabled}
      placeholder={placeholder}
      aria-label={ariaLabel}
      onChange={(event) => setDraft(event.target.value)}
      onBlur={commit}
      onKeyDown={handleKey}
      className={cn(
        'h-8 rounded-md border border-[#EAEAEA] bg-white px-2 text-[13px] tabular-nums text-[#0A0A0A] transition-colors',
        'hover:border-[#D4D4D4] focus:border-[#10B981] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20',
        'disabled:cursor-not-allowed disabled:bg-[#FAFAFA] disabled:text-[#737373]',
        align === 'right' ? 'text-right' : 'text-left',
        width,
      )}
    />
  );
}

export default function CommissionTierTable({
  platform,
  effectiveFrom,
  tiers,
  onEditRow,
  onAddTier,
  onRemoveTier,
  readOnly = false,
}) {
  const rows = Array.isArray(tiers) ? [...tiers].sort((a, b) => a.tier_number - b.tier_number) : [];
  const maxTierNumber = rows.reduce((acc, t) => Math.max(acc, Number(t.tier_number) || 0), 0);
  const platformName = platform?.name ?? platform?.slug ?? `Platform #${platform?.id ?? '?'}`;

  return (
    <section className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <header className="flex items-center justify-between gap-4 border-b border-[#F0F0F0] px-5 py-4">
        <div className="flex items-center gap-3">
          {platform?.icon_url ? (
            <img
              src={platform.icon_url}
              alt=""
              className="h-7 w-7 shrink-0 rounded-md border border-[#EAEAEA] bg-white object-contain p-1"
            />
          ) : null}
          <div>
            <h3 className="text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
              {platformName}
            </h3>
            <p className="mt-0.5 text-[12px] text-[#737373]">
              Commission ladder — tiered by monthly GMV.
            </p>
          </div>
        </div>
        <span className="inline-flex items-center gap-1.5 rounded-full border border-[#EAEAEA] bg-[#FAFAFA] px-2.5 py-1 text-[11.5px] font-medium text-[#525252]">
          <span className="text-[#737373]">effective from</span>
          <span className="tabular-nums text-[#0A0A0A]">{formatDateOnly(effectiveFrom)}</span>
        </span>
      </header>

      {rows.length === 0 ? (
        <div className="px-5 py-8 text-center text-[13px] text-[#737373]">
          No tiers configured yet.
          {!readOnly ? (
            <>
              {' '}
              Click <strong>Add tier</strong> below to create the first one.
            </>
          ) : null}
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-[13px]">
            <thead>
              <tr className="border-b border-[#F0F0F0] bg-[#FAFAFA] text-left text-[11px] font-medium uppercase tracking-[0.06em] text-[#737373]">
                <th className="w-[56px] px-5 py-2.5">Tier</th>
                <th className="px-3 py-2.5">Monthly GMV (min – max)</th>
                <th className="w-[128px] px-3 py-2.5 text-right">Internal %</th>
                <th className="w-[112px] px-3 py-2.5 text-right">L1 %</th>
                <th className="w-[112px] px-3 py-2.5 text-right">L2 %</th>
                <th className="w-[48px] px-3 py-2.5" aria-label="Actions" />
              </tr>
            </thead>
            <tbody className="divide-y divide-[#F0F0F0]">
              {rows.map((tier) => {
                const isTopTier = Number(tier.tier_number) === maxTierNumber;
                const canDelete = !readOnly && isTopTier && rows.length > 1;

                return (
                  <tr key={tier.id} className="transition-colors hover:bg-[#FAFAFA]">
                    <td className="px-5 py-3 align-middle">
                      <span className="inline-flex h-6 min-w-[32px] items-center justify-center rounded-md border border-[#EAEAEA] bg-white px-1.5 font-mono text-[11.5px] font-semibold tabular-nums text-[#0A0A0A]">
                        T{tier.tier_number}
                      </span>
                    </td>
                    <td className="px-3 py-3 align-middle">
                      {readOnly ? (
                        <span className="tabular-nums text-[#0A0A0A]">
                          {formatMoney(tier.min_gmv_myr)}
                          <span className="mx-1.5 text-[#737373]">–</span>
                          {tier.max_gmv_myr === null || tier.max_gmv_myr === undefined
                            ? '∞'
                            : formatMoney(tier.max_gmv_myr)}
                        </span>
                      ) : (
                        <div className="flex items-center gap-2">
                          <TierCellInput
                            value={tier.min_gmv_myr}
                            onCommit={(next) => onEditRow(tier.id, { min_gmv_myr: next })}
                            ariaLabel={`Tier ${tier.tier_number} minimum GMV`}
                            align="right"
                            step="1"
                            width="w-[120px]"
                          />
                          <span className="text-[#737373]">–</span>
                          <div className="relative">
                            <TierCellInput
                              value={tier.max_gmv_myr}
                              onCommit={(next) => onEditRow(tier.id, { max_gmv_myr: next })}
                              ariaLabel={`Tier ${tier.tier_number} maximum GMV`}
                              align="right"
                              allowNull
                              step="1"
                              width="w-[120px]"
                              placeholder="∞"
                            />
                          </div>
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-3 align-middle text-right">
                      {readOnly ? (
                        <span className="tabular-nums font-medium text-[#0A0A0A]">
                          {formatPercent(tier.internal_percent)}
                        </span>
                      ) : (
                        <TierCellInput
                          value={tier.internal_percent}
                          onCommit={(next) => onEditRow(tier.id, { internal_percent: next })}
                          ariaLabel={`Tier ${tier.tier_number} internal percent`}
                          align="right"
                          step="0.01"
                          min="0"
                          max="100"
                          width="w-[92px]"
                        />
                      )}
                    </td>
                    <td className="px-3 py-3 align-middle text-right">
                      {readOnly ? (
                        <span className="tabular-nums text-[#0A0A0A]">
                          {formatPercent(tier.l1_percent)}
                        </span>
                      ) : (
                        <TierCellInput
                          value={tier.l1_percent}
                          onCommit={(next) => onEditRow(tier.id, { l1_percent: next })}
                          ariaLabel={`Tier ${tier.tier_number} L1 percent`}
                          align="right"
                          step="0.01"
                          min="0"
                          max="100"
                          width="w-[84px]"
                        />
                      )}
                    </td>
                    <td className="px-3 py-3 align-middle text-right">
                      {readOnly ? (
                        <span className="tabular-nums text-[#0A0A0A]">
                          {formatPercent(tier.l2_percent)}
                        </span>
                      ) : (
                        <TierCellInput
                          value={tier.l2_percent}
                          onCommit={(next) => onEditRow(tier.id, { l2_percent: next })}
                          ariaLabel={`Tier ${tier.tier_number} L2 percent`}
                          align="right"
                          step="0.01"
                          min="0"
                          max="100"
                          width="w-[84px]"
                        />
                      )}
                    </td>
                    <td className="px-3 py-3 align-middle text-right">
                      {canDelete ? (
                        <button
                          type="button"
                          onClick={() => onRemoveTier(tier.id)}
                          aria-label={`Remove tier T${tier.tier_number}`}
                          className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent text-[#737373] transition-colors hover:border-[#FECACA] hover:bg-[#FEF2F2] hover:text-[#DC2626]"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      ) : (
                        <span aria-hidden="true" className="inline-block h-7 w-7" />
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {!readOnly ? (
        <div className="border-t border-[#F0F0F0] bg-[#FAFAFA] px-5 py-3">
          <button
            type="button"
            onClick={onAddTier}
            className="inline-flex items-center gap-1.5 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#0A0A0A] transition-colors hover:border-[#10B981] hover:text-[#059669]"
          >
            <Plus className="h-3.5 w-3.5" />
            Add tier
          </button>
        </div>
      ) : null}
    </section>
  );
}

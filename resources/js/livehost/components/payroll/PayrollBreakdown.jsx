import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

function formatMyr(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) {
    return '—';
  }
  return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function OverrideBlock({ title, rows }) {
  return (
    <div>
      <div className="mb-2 text-[11.5px] font-medium uppercase tracking-[0.02em] text-[#737373]">
        {title} ({rows.length})
      </div>
      {rows.length === 0 ? (
        <div className="rounded-[12px] border border-dashed border-[#E5E5E5] px-4 py-3 text-[12px] text-[#737373]">
          No downlines contributed override for this period.
        </div>
      ) : (
        <div className="overflow-hidden rounded-[12px] border border-[#EAEAEA] bg-white">
          <table className="w-full text-[12px]">
            <thead>
              <tr className="bg-[#F5F5F5] text-[10.5px] font-medium text-[#737373]">
                <th className="px-3 py-2 text-left">Downline</th>
                <th className="px-3 py-2 text-right">Their Monthly GMV</th>
                <th className="px-3 py-2 text-right">Tier</th>
                <th className="px-3 py-2 text-right">Rate %</th>
                <th className="px-3 py-2 text-right">Override</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr
                  key={`${row.downline_user_id}-${row.platform_id ?? 'na'}-${row.tier_id ?? index}`}
                  className="border-t border-[#F0F0F0]"
                >
                  <td className="px-3 py-1.5 text-[#0A0A0A]">{row.downline_name}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{formatMyr(row.monthly_gmv_myr)}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums text-[#737373]">{row.tier_number != null ? `T${row.tier_number}` : '—'}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{Number(row.override_rate_percent ?? 0).toFixed(2)}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums font-semibold">{formatMyr(row.override_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

/**
 * The per-host payroll breakdown: session detail (each Session ID links to its
 * matched orders) + Override L1 / L2. Rendered both inline (payroll run page)
 * and standalone (the per-host detail page), so it stays a single source.
 */
export default function PayrollBreakdownBody({ item }) {
  const breakdown = item.calculation_breakdown_json || {};
  const sessions = Array.isArray(breakdown.sessions) ? breakdown.sessions : [];
  const overridesL1 = Array.isArray(breakdown.overrides_l1) ? breakdown.overrides_l1 : [];
  const overridesL2 = Array.isArray(breakdown.overrides_l2) ? breakdown.overrides_l2 : [];

  return (
    <div className="grid gap-5 lg:grid-cols-2">
      {/* Session detail */}
      <div>
        <div className="mb-2 text-[11.5px] font-medium uppercase tracking-[0.02em] text-[#737373]">
          Session detail ({sessions.length})
        </div>
        {sessions.length === 0 ? (
          <div className="rounded-[12px] border border-dashed border-[#E5E5E5] px-4 py-3 text-[12px] text-[#737373]">
            No sessions in this period.
          </div>
        ) : (
          <div className="overflow-hidden rounded-[12px] border border-[#EAEAEA] bg-white">
            <table className="w-full text-[12px]">
              <thead>
                <tr className="bg-[#F5F5F5] text-[10.5px] font-medium text-[#737373]">
                  <th className="px-3 py-2 text-left">Session ID</th>
                  <th className="px-3 py-2 text-right">Gross GMV</th>
                  <th className="px-3 py-2 text-right">Adj.</th>
                  <th className="px-3 py-2 text-right">Net GMV</th>
                  <th className="px-3 py-2 text-right">Rate %</th>
                  <th className="px-3 py-2 text-right">GMV Comm.</th>
                  <th className="px-3 py-2 text-right">Per-Live</th>
                  <th className="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                {sessions.map((session) => (
                  <tr key={session.id} className="border-t border-[#F0F0F0]">
                    <td className="px-3 py-1.5 font-mono text-[11px]">
                      <Link
                        href={`/livehost/orders?session=${session.id}`}
                        className="inline-flex items-center gap-1 text-[#4338CA] hover:underline"
                        title="View this session's orders (incl. cancelled / refunded)"
                      >
                        #{session.id}
                        <ExternalLink className="h-3 w-3" strokeWidth={2} />
                      </Link>
                    </td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{formatMyr(session.gmv_amount)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{formatMyr(session.gmv_adjustment)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{formatMyr(session.net_gmv)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{Number(session.platform_rate_percent ?? 0).toFixed(2)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{formatMyr(session.gmv_commission)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{formatMyr(session.per_live)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums font-semibold">{formatMyr(session.session_total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Override breakdown */}
      <div className="flex flex-col gap-4">
        <OverrideBlock title="Override L1 (direct downlines)" rows={overridesL1} />
        <OverrideBlock title="Override L2 (2nd-level downlines)" rows={overridesL2} />
      </div>
    </div>
  );
}

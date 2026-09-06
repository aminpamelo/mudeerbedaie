/**
 * Studio Daily Reporting — the team's day-by-day ads performance: ad spend,
 * spend grossed up by SST, and the funnel sales each day drove (with ROAS and
 * net), plus a whole-month rollup, a per-funnel breakdown, and per-team-member
 * performance. 7/30/90 day window. Mirrors the Studio Reports page styling.
 */

import React, { useState, useEffect, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const fmtRM = (value) =>
    `RM ${Number(value || 0).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;

const fmtRMSigned = (value) =>
    `${Number(value || 0) < 0 ? '−' : ''}RM ${Math.abs(Number(value || 0)).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;

const fmtROAS = (roas) => (roas == null ? '—' : `${Number(roas).toFixed(2)}×`);

const roasClass = (roas) =>
    roas == null ? 'text-gray-400' : Number(roas) >= 1 ? 'text-green-600' : 'text-red-600';

const netClass = (net) => (Number(net) >= 0 ? 'text-green-600' : 'text-red-600');

const fmtDay = (day) => {
    try {
        return new Date(`${day}T00:00:00`).toLocaleDateString('en-MY', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    } catch (e) {
        return day;
    }
};

export default function StudioDailyReporting({ onSelectFunnel }) {
    const [report, setReport] = useState(null);
    const [days, setDays] = useState(30);
    const [loading, setLoading] = useState(true);

    const loadReport = useCallback(async (window) => {
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/studio/daily-reporting?days=${window}`, {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setReport(data.data || null);
        } catch (e) {
            setReport(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        loadReport(days);
    }, [days, loadReport]);

    const sstPct = Math.round((report?.sst_rate ?? 0.08) * 100);
    const chart = [...(report?.daily || [])].reverse(); // oldest → newest for the chart
    const maxBar = Math.max(1, ...(report?.daily || []).flatMap((d) => [d.spend, d.sales]));
    const maxFunnelSales = Math.max(1, ...(report?.by_funnel || []).map((f) => f.sales));
    const maxTeamRevenue = Math.max(1, ...(report?.by_team || []).map((t) => t.revenue));

    return (
        <div className="px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Daily <span className="fs-gradient-text">Reporting</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">
                        Ad spend, spend incl. {sstPct}% SST, and the sales each day drove — with ROAS &amp; net.
                    </p>
                </div>
                <div className="flex rounded-lg border border-gray-200 bg-white p-1">
                    {[7, 30, 90].map((window) => (
                        <button
                            key={window}
                            onClick={() => setDays(window)}
                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors cursor-pointer ${
                                days === window ? 'bg-orange-600 text-white' : 'text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            {window}d
                        </button>
                    ))}
                </div>
            </div>

            {loading || !report ? (
                <div className="py-24 text-center text-gray-500">
                    {loading ? 'Loading report...' : 'Could not load the report.'}
                </div>
            ) : (
                <div className="space-y-6">
                    {!report.can_see_spend && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800">
                            Ad spend is only visible for ad accounts you've connected. Sales figures below cover the
                            funnels you own.
                        </div>
                    )}

                    {/* Stat cards */}
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Ad Spend ({report.days}d)</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{fmtRM(report.totals.spend)}</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Spend + SST ({sstPct}%)</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{fmtRM(report.totals.spend_with_sst)}</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Sales</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{fmtRM(report.totals.sales)}</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">ROAS</p>
                            <p className={`fs-stat-value text-2xl font-bold ${roasClass(report.totals.roas)}`}>
                                {fmtROAS(report.totals.roas)}
                            </p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Net (Sales − Spend+SST)</p>
                            <p className={`fs-stat-value text-2xl font-bold ${netClass(report.totals.net)}`}>
                                {fmtRMSigned(report.totals.net)}
                            </p>
                        </div>
                    </div>

                    {/* Daily spend vs sales chart */}
                    <div className="rounded-lg border border-gray-200 bg-white p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Daily Spend vs Sales</h3>
                            <div className="flex items-center gap-4 text-xs text-gray-500">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-sm bg-sky-500/80" /> Ad Spend
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="fs-bar h-2.5 w-2.5 rounded-sm" /> Sales
                                </span>
                            </div>
                        </div>
                        <div className="flex h-44 items-end gap-[3px]">
                            {chart.map((d) => (
                                <div key={d.day} className="group relative flex h-full flex-1 items-end justify-center gap-[2px]">
                                    <div
                                        className="w-full max-w-[10px] rounded-t bg-sky-500/80"
                                        style={{ height: `${Math.max(d.spend > 0 ? 3 : 0, (d.spend / maxBar) * 160)}px` }}
                                    />
                                    <div
                                        className="fs-bar w-full max-w-[10px] rounded-t"
                                        style={{ height: `${Math.max(d.sales > 0 ? 3 : 0, (d.sales / maxBar) * 160)}px` }}
                                    />
                                    {/* Hover tooltip */}
                                    <div className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 scale-95 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1.5 text-center opacity-0 shadow-lg transition duration-150 group-hover:scale-100 group-hover:opacity-100">
                                        <div className="text-[11px] font-medium text-gray-300">{fmtDay(d.day)}</div>
                                        <div className="text-xs font-semibold text-sky-300">Spend {fmtRM(d.spend)}</div>
                                        <div className="text-xs font-semibold text-white">Sales {fmtRM(d.sales)}</div>
                                        <div className="text-[11px] text-gray-400">
                                            ROAS {fmtROAS(d.roas)} · {d.orders} orders
                                        </div>
                                        <div className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-900" />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-2 flex justify-between text-[11px] text-gray-400">
                            <span>{chart[0] && fmtDay(chart[0].day)}</span>
                            <span>{chart[chart.length - 1] && fmtDay(chart[chart.length - 1].day)}</span>
                        </div>
                    </div>

                    {/* Monthly performance */}
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Monthly Performance</h3>
                            <p className="text-xs text-gray-500">Every calendar month in this window, most recent first.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                        <th className="px-6 py-2.5 font-medium">Month</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Ad Spend</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Spend + SST</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Sales</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Orders</th>
                                        <th className="px-6 py-2.5 text-right font-medium">ROAS</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Net</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.monthly.map((m) => (
                                        <tr key={m.month} className="border-t border-gray-100">
                                            <td className="px-6 py-3 font-medium text-gray-900">{m.month_label}</td>
                                            <td className="px-6 py-3 text-right tabular-nums text-gray-700">{fmtRM(m.spend)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums text-gray-700">{fmtRM(m.spend_with_sst)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums font-semibold text-gray-900">{fmtRM(m.sales)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums text-gray-600">{m.orders.toLocaleString()}</td>
                                            <td className={`px-6 py-3 text-right tabular-nums font-medium ${roasClass(m.roas)}`}>{fmtROAS(m.roas)}</td>
                                            <td className={`px-6 py-3 text-right tabular-nums font-medium ${netClass(m.net)}`}>{fmtRMSigned(m.net)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Daily breakdown */}
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Daily Breakdown</h3>
                            <p className="text-xs text-gray-500">Day-by-day spend against sales, most recent first.</p>
                        </div>
                        <div className="max-h-[540px] overflow-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="sticky top-0 bg-white">
                                    <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                        <th className="px-6 py-2.5 font-medium">Date</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Ad Spend</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Spend + SST</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Sales</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Orders</th>
                                        <th className="px-6 py-2.5 text-right font-medium">ROAS</th>
                                        <th className="px-6 py-2.5 text-right font-medium">Net</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.daily.map((d) => (
                                        <tr key={d.day} className="border-t border-gray-100">
                                            <td className="px-6 py-3 font-medium text-gray-900">{fmtDay(d.day)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums text-gray-700">{fmtRM(d.spend)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums text-gray-700">{fmtRM(d.spend_with_sst)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums font-semibold text-gray-900">{fmtRM(d.sales)}</td>
                                            <td className="px-6 py-3 text-right tabular-nums text-gray-600">{d.orders.toLocaleString()}</td>
                                            <td className={`px-6 py-3 text-right tabular-nums font-medium ${roasClass(d.roas)}`}>{fmtROAS(d.roas)}</td>
                                            <td className={`px-6 py-3 text-right tabular-nums font-medium ${netClass(d.net)}`}>{fmtRMSigned(d.net)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Performance by funnel */}
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Performance by Funnel</h3>
                            <p className="text-xs text-gray-500">
                                Sales per funnel in this window. Spend &amp; ROAS show where a funnel is linked to an ad account
                                (its Ads Source).
                            </p>
                        </div>
                        {(report.by_funnel || []).length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">No funnel sales in this window yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead>
                                        <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                            <th className="px-6 py-2.5 font-medium">Funnel</th>
                                            <th className="px-6 py-2.5 font-medium">Share</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Sales</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Orders</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Linked Spend</th>
                                            <th className="px-6 py-2.5 text-right font-medium">ROAS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.by_funnel.map((f, i) => (
                                            <tr key={f.funnel_uuid || i} className="border-t border-gray-100">
                                                <td className="px-6 py-3">
                                                    <button
                                                        onClick={() => f.funnel_uuid && onSelectFunnel?.({ uuid: f.funnel_uuid })}
                                                        className="font-medium text-gray-900 hover:text-orange-600 cursor-pointer"
                                                    >
                                                        {f.funnel_name}
                                                    </button>
                                                </td>
                                                <td className="w-44 px-6 py-3">
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                                        <div
                                                            className="fs-bar h-full rounded-full"
                                                            style={{ width: `${Math.max(2, (f.sales / maxFunnelSales) * 100)}%` }}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3 text-right tabular-nums font-semibold text-gray-900">{fmtRM(f.sales)}</td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-600">{f.orders.toLocaleString()}</td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-700">
                                                    {f.linked_spend == null ? <span className="text-gray-400">—</span> : fmtRM(f.linked_spend)}
                                                </td>
                                                <td className={`px-6 py-3 text-right tabular-nums font-medium ${roasClass(f.roas)}`}>{fmtROAS(f.roas)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Team performance — who on the team is actually selling */}
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Team Performance</h3>
                            <p className="text-xs text-gray-500">Revenue and conversion per funnel owner in this window, top earner first.</p>
                        </div>
                        {(report.by_team || []).length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">No team sales in this window yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[820px] text-sm">
                                    <thead>
                                        <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                            <th className="px-6 py-2.5 font-medium">#</th>
                                            <th className="px-6 py-2.5 font-medium">Team Member</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Funnels</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Visitors</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Orders</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Conv. Rate</th>
                                            <th className="px-6 py-2.5 font-medium">Share</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.by_team.map((member, i) => (
                                            <tr key={member.owner_id ?? i} className="border-t border-gray-100">
                                                <td className="px-6 py-3 text-gray-400">{i + 1}</td>
                                                <td className="px-6 py-3">
                                                    <span className="flex items-center gap-2">
                                                        <span className="font-medium text-gray-900">{member.name}</span>
                                                        {member.role && member.role !== 'admin' && (
                                                            <span className="rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium capitalize text-purple-800">
                                                                {member.role}
                                                            </span>
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-600">{member.funnels}</td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-600">{member.sessions.toLocaleString()}</td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-700">{member.orders.toLocaleString()}</td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-700">
                                                    {member.conversion_rate != null ? `${member.conversion_rate}%` : '—'}
                                                </td>
                                                <td className="w-44 px-6 py-3">
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                                        <div
                                                            className="fs-bar h-full rounded-full"
                                                            style={{ width: `${Math.max(2, (member.revenue / maxTeamRevenue) * 100)}%` }}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3 text-right tabular-nums font-semibold text-gray-900">{fmtRM(member.revenue)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

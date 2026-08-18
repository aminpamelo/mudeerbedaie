/**
 * Studio Reports — cross-funnel performance overview: totals, daily revenue
 * chart, top funnels, and order-type breakdown, with a 7/30/90 day window.
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

const TYPE_LABELS = { main: 'Main Offers', upsell: 'Upsells', downsell: 'Downsells', bump: 'Order Bumps' };

export default function StudioReports({ onSelectFunnel }) {
    const [report, setReport] = useState(null);
    const [days, setDays] = useState(30);
    const [loading, setLoading] = useState(true);

    const loadReport = useCallback(async (window) => {
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/studio/reports?days=${window}`, {
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

    const maxDailyRevenue = Math.max(1, ...(report?.daily || []).map((d) => d.revenue));
    const maxFunnelRevenue = Math.max(1, ...(report?.top_funnels || []).map((f) => f.revenue));
    const typeTotal = Math.max(1, (report?.by_type || []).reduce((sum, t) => sum + t.revenue, 0));
    const sourceTotal = Math.max(1, (report?.by_source || []).reduce((sum, s) => sum + s.revenue, 0));
    const maxCampaignRevenue = Math.max(1, ...(report?.top_campaigns || []).map((c) => c.revenue));
    const maxTeamRevenue = Math.max(1, ...(report?.by_team || []).map((t) => t.revenue));

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Funnel <span className="fs-gradient-text">Reports</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">Performance across every funnel you can see.</p>
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
                <div className="py-24 text-center text-gray-500">{loading ? 'Loading report...' : 'Could not load the report.'}</div>
            ) : (
                <div className="space-y-6">
                    {/* Stat cards */}
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Revenue ({report.days}d)</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{fmtRM(report.totals.revenue)}</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Orders</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{report.totals.orders.toLocaleString()}</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Avg Order Value</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{fmtRM(report.totals.avg_order_value)}</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Funnels</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{report.totals.funnels}</p>
                        </div>
                    </div>

                    {/* Daily revenue chart */}
                    <div className="rounded-lg border border-gray-200 bg-white p-6">
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Daily Revenue</h3>
                        <div className="flex h-44 items-end gap-[3px]">
                            {report.daily.map((d) => (
                                <div key={d.day} className="group relative flex h-full flex-1 items-end">
                                    <div
                                        className="fs-bar w-full rounded-t"
                                        style={{ height: `${Math.max(2, (d.revenue / maxDailyRevenue) * 160)}px` }}
                                    />
                                    {/* Hover tooltip */}
                                    <div className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 scale-95 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1.5 text-center opacity-0 shadow-lg transition duration-150 group-hover:scale-100 group-hover:opacity-100">
                                        <div className="text-[11px] font-medium text-gray-300">{d.day}</div>
                                        <div className="text-xs font-semibold text-white">{fmtRM(d.revenue)}</div>
                                        <div className="text-[11px] text-gray-400">{d.orders} orders</div>
                                        <div className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-900" />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-2 flex justify-between text-[11px] text-gray-400">
                            <span>{report.daily[0]?.day}</span>
                            <span>{report.daily[report.daily.length - 1]?.day}</span>
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Top funnels */}
                        <div className="rounded-lg border border-gray-200 bg-white p-6">
                            <h3 className="mb-4 text-lg font-semibold text-gray-900">Top Funnels</h3>
                            {report.top_funnels.length === 0 ? (
                                <p className="text-sm text-gray-500">No orders in this window yet.</p>
                            ) : (
                                <div className="space-y-3">
                                    {report.top_funnels.map((funnel, i) => (
                                        <button
                                            key={funnel.funnel_uuid || i}
                                            onClick={() => funnel.funnel_uuid && onSelectFunnel?.({ uuid: funnel.funnel_uuid })}
                                            className="block w-full text-left cursor-pointer"
                                        >
                                            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <span className="w-4 shrink-0 text-gray-400">{i + 1}</span>
                                                    <span className="truncate font-medium text-gray-900 hover:text-orange-600">
                                                        {funnel.funnel_name}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 font-semibold text-gray-900">{fmtRM(funnel.revenue)}</span>
                                            </div>
                                            <div className="ml-6 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                                <div
                                                    className="fs-bar h-full rounded-full"
                                                    style={{ width: `${Math.max(2, (funnel.revenue / maxFunnelRevenue) * 100)}%` }}
                                                />
                                            </div>
                                            <p className="ml-6 mt-0.5 text-[11px] text-gray-500">{funnel.orders} orders</p>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Traffic source breakdown — which ads bring the money */}
                        <div className="rounded-lg border border-gray-200 bg-white p-6">
                            <h3 className="mb-1 text-lg font-semibold text-gray-900">Revenue by Traffic Source</h3>
                            <p className="mb-4 text-xs text-gray-500">Based on the UTM source of each buyer's session.</p>
                            {(report.by_source || []).length === 0 ? (
                                <p className="text-sm text-gray-500">No orders in this window yet.</p>
                            ) : (
                                <div className="space-y-4">
                                    {report.by_source.map((row) => (
                                        <div key={row.source}>
                                            <div className="mb-1 flex items-center justify-between text-sm">
                                                <span className="font-medium capitalize text-gray-900">{row.source}</span>
                                                <span className="text-gray-600">
                                                    {fmtRM(row.revenue)} · {row.orders} orders · {Math.round((row.revenue / sourceTotal) * 100)}%
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-gray-100">
                                                <div
                                                    className="fs-bar h-full rounded-full"
                                                    style={{ width: `${Math.max(2, (row.revenue / sourceTotal) * 100)}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Top campaigns */}
                        <div className="rounded-lg border border-gray-200 bg-white p-6">
                            <h3 className="mb-1 text-lg font-semibold text-gray-900">Top Campaigns</h3>
                            <p className="mb-4 text-xs text-gray-500">Orders that arrived with a utm_campaign tag.</p>
                            {(report.top_campaigns || []).length === 0 ? (
                                <p className="text-sm text-gray-500">
                                    No campaign-tagged orders yet — add utm_campaign to your ad links to see them here.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {report.top_campaigns.map((row, i) => (
                                        <div key={`${row.campaign}-${i}`}>
                                            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium text-gray-900">{row.campaign}</span>
                                                    <span className="block text-[11px] capitalize text-gray-500">{row.source || 'unknown source'}</span>
                                                </span>
                                                <span className="shrink-0 text-gray-600">
                                                    {fmtRM(row.revenue)} · {row.orders}
                                                </span>
                                            </div>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                                <div
                                                    className="fs-bar h-full rounded-full"
                                                    style={{ width: `${Math.max(2, (row.revenue / maxCampaignRevenue) * 100)}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Order type breakdown */}
                        <div className="rounded-lg border border-gray-200 bg-white p-6">
                            <h3 className="mb-4 text-lg font-semibold text-gray-900">Revenue by Order Type</h3>
                            {report.by_type.length === 0 ? (
                                <p className="text-sm text-gray-500">No orders in this window yet.</p>
                            ) : (
                                <div className="space-y-4">
                                    {report.by_type.map((row) => (
                                        <div key={row.type}>
                                            <div className="mb-1 flex items-center justify-between text-sm">
                                                <span className="font-medium text-gray-900">{TYPE_LABELS[row.type] || row.type}</span>
                                                <span className="text-gray-600">
                                                    {fmtRM(row.revenue)} · {row.orders} orders
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-gray-100">
                                                <div
                                                    className="fs-bar h-full rounded-full"
                                                    style={{ width: `${Math.max(2, (row.revenue / typeTotal) * 100)}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Team performance — who on the team is actually selling */}
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Team Performance</h3>
                            <p className="text-xs text-gray-500">
                                Revenue and conversion per funnel owner in this window, top earner first.
                            </p>
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

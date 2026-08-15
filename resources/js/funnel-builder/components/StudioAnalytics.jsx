/**
 * Studio Analytics — traffic & conversion across every visible funnel:
 * visitors vs conversions over time, and a per-funnel comparison table.
 * (Reports covers the money; this page covers the traffic that makes it.)
 */

import React, { useState, useEffect, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const fmtRM = (value) => `RM ${Number(value || 0).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;

export default function StudioAnalytics({ onSelectFunnel }) {
    const [report, setReport] = useState(null);
    const [days, setDays] = useState(30);
    const [loading, setLoading] = useState(true);

    const loadAnalytics = useCallback(async (window) => {
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/studio/analytics?days=${window}`, {
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
        loadAnalytics(days);
    }, [days, loadAnalytics]);

    const maxSessions = Math.max(1, ...(report?.daily || []).map((d) => d.sessions));
    const maxFunnelSessions = Math.max(1, ...(report?.funnels || []).map((f) => f.sessions));

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Funnel <span className="fs-gradient-text">Analytics</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">
                        Visitors, conversions, and which funnels actually convert.
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
                    {loading ? 'Loading analytics...' : 'Could not load analytics.'}
                </div>
            ) : (
                <div className="space-y-6">
                    {/* Stat cards */}
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Visitors ({report.days}d)</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">
                                {report.totals.sessions.toLocaleString()}
                            </p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Conversions</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">
                                {report.totals.conversions.toLocaleString()}
                            </p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Conversion Rate</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{report.totals.conversion_rate}%</p>
                        </div>
                        <div className="rounded-lg border border-gray-200 bg-white p-4">
                            <p className="text-sm text-gray-500">Revenue</p>
                            <p className="fs-stat-value text-2xl font-bold text-gray-900">{fmtRM(report.totals.revenue)}</p>
                        </div>
                    </div>

                    {/* Visitors vs conversions chart */}
                    <div className="rounded-lg border border-gray-200 bg-white p-6">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <h3 className="text-lg font-semibold text-gray-900">Visitors vs Conversions</h3>
                            <div className="flex items-center gap-4 text-xs text-gray-500">
                                <span className="flex items-center gap-1.5">
                                    <span className="fs-bar inline-block h-2.5 w-2.5 rounded-sm" /> Visitors
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-green-500" /> Conversions
                                </span>
                            </div>
                        </div>
                        <div className="flex h-44 items-end gap-[3px]">
                            {report.daily.map((d) => (
                                <div
                                    key={d.day}
                                    className="flex flex-1 items-end justify-center gap-[2px]"
                                    title={`${d.day}: ${d.sessions} visitors · ${d.conversions} conversions`}
                                >
                                    <div
                                        className="fs-bar w-full max-w-[14px] rounded-t"
                                        style={{ height: `${Math.max(2, (d.sessions / maxSessions) * 160)}px` }}
                                    />
                                    <div
                                        className="w-full max-w-[8px] rounded-t bg-green-500/80"
                                        style={{ height: `${Math.max(d.conversions > 0 ? 4 : 2, (d.conversions / maxSessions) * 160)}px` }}
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="mt-2 flex justify-between text-[11px] text-gray-400">
                            <span>{report.daily[0]?.day}</span>
                            <span>{report.daily[report.daily.length - 1]?.day}</span>
                        </div>
                    </div>

                    {/* Per-funnel comparison */}
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Funnel Comparison</h3>
                            <p className="text-xs text-gray-500">Funnels with traffic or sales in this window, busiest first.</p>
                        </div>
                        {report.funnels.length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">No traffic recorded in this window.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead>
                                        <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                            <th className="px-6 py-2.5 font-medium">Funnel</th>
                                            <th className="px-6 py-2.5 font-medium">Traffic</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Visitors</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Conversions</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Conv. Rate</th>
                                            <th className="px-6 py-2.5 text-right font-medium">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.funnels.map((funnel) => (
                                            <tr
                                                key={funnel.funnel_uuid}
                                                onClick={() => onSelectFunnel?.({ uuid: funnel.funnel_uuid }, 'analytics')}
                                                className="cursor-pointer border-t border-gray-100 transition-colors hover:bg-gray-50"
                                            >
                                                <td className="max-w-[240px] px-6 py-3">
                                                    <span className="block truncate font-medium text-gray-900">{funnel.funnel_name}</span>
                                                    <span className={`text-[11px] capitalize ${funnel.status === 'published' ? 'text-green-600' : 'text-gray-400'}`}>
                                                        {funnel.status}
                                                    </span>
                                                </td>
                                                <td className="w-48 px-6 py-3">
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                                        <div
                                                            className="fs-bar h-full rounded-full"
                                                            style={{ width: `${Math.max(2, (funnel.sessions / maxFunnelSessions) * 100)}%` }}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-700">{funnel.sessions.toLocaleString()}</td>
                                                <td className="px-6 py-3 text-right tabular-nums text-gray-700">{funnel.conversions.toLocaleString()}</td>
                                                <td className="px-6 py-3 text-right tabular-nums font-semibold text-gray-900">{funnel.conversion_rate}%</td>
                                                <td className="px-6 py-3 text-right tabular-nums font-semibold text-gray-900">{fmtRM(funnel.revenue)}</td>
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

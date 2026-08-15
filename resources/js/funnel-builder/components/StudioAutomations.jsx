/**
 * Studio Automations — every automation across all visible funnels in one
 * list: trigger, run stats, and an on/off toggle. Click through to the
 * funnel's Automations tab to edit the flow itself.
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const apiHeaders = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-XSRF-TOKEN': getCsrfToken(),
});

const TRIGGER_LABELS = {
    cart_abandonment: ['Cart Abandonment', 'bg-yellow-100 text-yellow-800'],
    purchase: ['Purchase', 'bg-green-100 text-green-800'],
    purchase_completed: ['Purchase Completed', 'bg-green-100 text-green-800'],
    purchase_failed: ['Purchase Failed', 'bg-red-100 text-red-700'],
    optin: ['Opt-in', 'bg-blue-100 text-blue-800'],
    optin_submitted: ['Opt-in Submitted', 'bg-blue-100 text-blue-800'],
    upsell_accepted: ['Upsell Accepted', 'bg-purple-100 text-purple-800'],
    upsell_declined: ['Upsell Declined', 'bg-red-100 text-red-700'],
    page_view: ['Page View', 'bg-gray-100 text-gray-600'],
    time_based: ['Time Based', 'bg-gray-100 text-gray-600'],
};

export default function StudioAutomations({ onSelectFunnel }) {
    const [automations, setAutomations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [activeFilter, setActiveFilter] = useState('');
    const [togglingId, setTogglingId] = useState(null);
    const debounceRef = useRef(null);

    const loadAutomations = useCallback(async (params) => {
        setLoading(true);
        try {
            const qs = new URLSearchParams(
                Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))
            ).toString();
            const response = await fetch(`/api/v1/studio/automations?${qs}`, {
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            setAutomations(data.data || []);
        } catch (e) {
            setAutomations([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            loadAutomations({ search, active: activeFilter });
        }, search ? 300 : 0);
        return () => clearTimeout(debounceRef.current);
    }, [search, activeFilter, loadAutomations]);

    const handleToggle = async (automation) => {
        setTogglingId(automation.id);
        try {
            const response = await fetch(`/api/v1/studio/automations/${automation.id}/toggle`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (response.ok) {
                setAutomations((prev) =>
                    prev.map((a) => (a.id === automation.id ? { ...a, is_active: data.is_active } : a))
                );
            }
        } catch (e) {
            // Toggle simply stays as-is.
        } finally {
            setTogglingId(null);
        }
    };

    const activeCount = automations.filter((a) => a.is_active).length;

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6">
                <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                    Funnel <span className="fs-gradient-text">Automations</span>
                </h1>
                <p className="mt-0.5 text-[13px] text-gray-500">
                    {loading ? 'Loading automations...' : `${automations.length} automation(s) · ${activeCount} active`}
                </p>
            </div>

            {/* Filters */}
            <div className="mb-5 flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search automations..."
                    className="min-w-[220px] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                />
                <div className="flex rounded-lg border border-gray-200 bg-white p-1">
                    {[
                        { key: '', label: 'All' },
                        { key: '1', label: 'Active' },
                        { key: '0', label: 'Paused' },
                    ].map(({ key, label }) => (
                        <button
                            key={label}
                            onClick={() => setActiveFilter(key)}
                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors cursor-pointer ${
                                activeFilter === key ? 'bg-orange-600 text-white' : 'text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            {/* List */}
            {loading ? (
                <div className="py-20 text-center text-gray-500">Loading automations...</div>
            ) : automations.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-12 text-center">
                    <svg className="mx-auto mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3 className="mb-2 text-lg font-medium text-gray-900">No automations yet</h3>
                    <p className="mx-auto mb-2 max-w-md text-sm text-gray-500">
                        Open any funnel's Automations tab to create email sequences, cart recovery flows, and more —
                        they'll all show up here for one-glance control.
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-left text-[11px] uppercase tracking-wider text-gray-500">
                                <th className="px-4 py-3 font-medium">Automation</th>
                                <th className="px-4 py-3 font-medium">Funnel</th>
                                <th className="px-4 py-3 font-medium">Trigger</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                                <th className="px-4 py-3 text-right font-medium">Runs</th>
                                <th className="px-4 py-3 font-medium">Last Run</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {automations.map((automation) => {
                                const [triggerLabel, triggerClass] =
                                    TRIGGER_LABELS[automation.trigger_type] || [automation.trigger_type, 'bg-gray-100 text-gray-600'];
                                return (
                                    <tr key={automation.id} className="border-b border-gray-100 last:border-b-0 hover:bg-gray-50">
                                        <td className="max-w-[240px] truncate px-4 py-3 font-medium text-gray-900">{automation.name}</td>
                                        <td className="max-w-[200px] truncate px-4 py-3 text-gray-700">{automation.funnel_name}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${triggerClass}`}>
                                                {triggerLabel}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-gray-600">{automation.actions_count}</td>
                                        <td className="px-4 py-3 text-right tabular-nums text-gray-600">{automation.runs_count.toLocaleString()}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-gray-500">{automation.last_run_at || 'Never'}</td>
                                        <td className="px-4 py-3">
                                            <button
                                                onClick={() => handleToggle(automation)}
                                                disabled={togglingId === automation.id}
                                                title={automation.is_active ? 'Pause automation' : 'Activate automation'}
                                                className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors cursor-pointer disabled:opacity-50 ${
                                                    automation.is_active ? 'bg-orange-600' : 'bg-gray-200'
                                                }`}
                                            >
                                                <span
                                                    className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform ${
                                                        automation.is_active ? 'translate-x-[18px]' : 'translate-x-1'
                                                    }`}
                                                />
                                            </button>
                                        </td>
                                        <td className="px-4 py-3">
                                            <button
                                                onClick={() => onSelectFunnel?.({ uuid: automation.funnel_uuid }, 'automations')}
                                                className="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 cursor-pointer"
                                            >
                                                Open
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

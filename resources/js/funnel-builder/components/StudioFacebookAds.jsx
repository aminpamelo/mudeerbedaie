/**
 * Studio Facebook Ads — connect multiple Business Managers (via System User
 * access tokens), sync their ad accounts + daily campaign insights, and see
 * which funnels use which ad account.
 */

import React, { useState, useEffect, useCallback } from 'react';

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

const fmtRM = (value) => `RM ${Number(value || 0).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;

function Toast({ message, type = 'success', onClose }) {
    useEffect(() => {
        const timer = setTimeout(onClose, 4000);
        return () => clearTimeout(timer);
    }, [onClose]);

    return (
        <div className={`toast toast-${type}`}>
            <span>{message}</span>
        </div>
    );
}

const STATUS_BADGES = {
    connected: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    error: 'bg-red-100 text-red-700',
};

export default function StudioFacebookAds() {
    const [connections, setConnections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [saving, setSaving] = useState(false);
    const [syncingId, setSyncingId] = useState(null);
    const [form, setForm] = useState({ name: '', business_manager_id: '', access_token: '' });

    const showToast = useCallback((message, type = 'success') => setToast({ message, type }), []);

    const loadConnections = useCallback(async () => {
        try {
            const response = await fetch('/api/v1/facebook-ads/connections', {
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            setConnections(data.data || []);
        } catch (err) {
            showToast('Failed to load connections', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        loadConnections();
    }, [loadConnections]);

    const handleAdd = async () => {
        if (!form.name.trim() || !form.business_manager_id.trim() || !form.access_token.trim()) {
            showToast('Please fill in all fields', 'error');
            return;
        }

        setSaving(true);
        try {
            const response = await fetch('/api/v1/facebook-ads/connections', {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: form.name.trim(),
                    business_manager_id: form.business_manager_id.trim(),
                    access_token: form.access_token.trim(),
                }),
            });
            const data = await response.json();
            showToast(data.message || (data.success ? 'Connected!' : 'Connection failed'), data.success ? 'success' : 'error');
            if (data.success) {
                setShowModal(false);
                setForm({ name: '', business_manager_id: '', access_token: '' });
            }
            loadConnections();
        } catch (err) {
            showToast('Failed to add connection', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleSync = async (connection) => {
        setSyncingId(connection.id);
        try {
            const response = await fetch(`/api/v1/facebook-ads/connections/${connection.id}/sync`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ days: 30 }),
            });
            const data = await response.json();
            showToast(data.message || (data.success ? 'Synced' : 'Sync failed'), data.success ? 'success' : 'error');
            loadConnections();
        } catch (err) {
            showToast('Sync failed', 'error');
        } finally {
            setSyncingId(null);
        }
    };

    const handleDelete = async (connection) => {
        if (!confirm(`Remove "${connection.name}"? Synced ad data for its accounts will be deleted too.`)) return;

        try {
            const response = await fetch(`/api/v1/facebook-ads/connections/${connection.id}`, {
                method: 'DELETE',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            if (response.ok) {
                showToast('Connection removed');
                loadConnections();
            }
        } catch (err) {
            showToast('Failed to remove connection', 'error');
        }
    };

    return (
        <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

            {/* Header */}
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Facebook <span className="fs-gradient-text">Ads</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">
                        Connect your Business Managers to pull ad accounts, spend, and campaign data into the Studio.
                    </p>
                </div>
                <button onClick={() => setShowModal(true)} className="fs-cta rounded-lg px-4 py-2 font-medium text-white">
                    + Connect Business Manager
                </button>
            </div>

            {loading ? (
                <div className="py-20 text-center text-gray-500">Loading connections...</div>
            ) : connections.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-12 text-center">
                    <svg className="mx-auto mb-4 h-12 w-12 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                    <h3 className="mb-2 text-lg font-medium text-gray-900">No Business Managers connected yet</h3>
                    <p className="mx-auto mb-6 max-w-lg text-sm text-gray-500">
                        Create a System User in Business Settings → Users → System Users, grant it your ad accounts
                        with <span className="font-mono">ads_read</span>, generate a token, and paste it here. System User
                        tokens don't expire, so the daily sync keeps running.
                    </p>
                    <button onClick={() => setShowModal(true)} className="fs-cta rounded-lg px-6 py-3 font-medium text-white">
                        Connect Your First Business Manager
                    </button>
                </div>
            ) : (
                <div className="space-y-6">
                    {connections.map((connection) => (
                        <div key={connection.id} className="rounded-lg border border-gray-200 bg-white">
                            <div className="flex flex-wrap items-center gap-3 border-b border-gray-200 px-5 py-4">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-semibold text-gray-900">{connection.name}</h3>
                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ${STATUS_BADGES[connection.status] || 'bg-gray-100 text-gray-600'}`}>
                                            {connection.status}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 truncate text-xs text-gray-500">
                                        BM {connection.business_manager_id}
                                        {connection.status_message ? ` · ${connection.status_message}` : ''}
                                        {connection.last_synced_at ? ` · Synced ${new Date(connection.last_synced_at).toLocaleString()}` : ' · Never synced'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => handleSync(connection)}
                                        disabled={syncingId === connection.id}
                                        className="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                                    >
                                        {syncingId === connection.id ? 'Syncing...' : 'Sync Now'}
                                    </button>
                                    <button
                                        onClick={() => handleDelete(connection)}
                                        className="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                            {connection.accounts.length === 0 ? (
                                <p className="px-5 py-4 text-sm text-gray-500">
                                    No ad accounts synced yet — hit Sync Now.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[720px] text-sm">
                                        <thead>
                                            <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                                <th className="px-5 py-2.5 font-medium">Ad Account</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Spend (30d)</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Impressions</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Clicks</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Funnels Using</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {connection.accounts.map((account) => (
                                                <tr key={account.id} className="border-t border-gray-100">
                                                    <td className="px-5 py-3">
                                                        <span className="block font-medium text-gray-900">{account.name}</span>
                                                        <span className="text-xs text-gray-500">
                                                            act_{account.account_id}
                                                            {account.currency ? ` · ${account.currency}` : ''}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">
                                                        {fmtRM(account.spend_30d)}
                                                    </td>
                                                    <td className="px-5 py-3 text-right tabular-nums text-gray-600">
                                                        {account.impressions_30d.toLocaleString()}
                                                    </td>
                                                    <td className="px-5 py-3 text-right tabular-nums text-gray-600">
                                                        {account.clicks_30d.toLocaleString()}
                                                    </td>
                                                    <td className="px-5 py-3 text-right tabular-nums text-gray-600">
                                                        {account.linked_funnels_count}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Add Connection Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowModal(false)}>
                    <div className="w-full max-w-lg rounded-xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">Connect Business Manager</h3>
                            <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600">
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div className="space-y-4 px-6 py-5">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    placeholder="e.g., BM Bedaie Utama"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-orange-500"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Business Manager ID <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.business_manager_id}
                                    onChange={(e) => setForm({ ...form, business_manager_id: e.target.value })}
                                    placeholder="e.g., 123456789012345"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-orange-500"
                                />
                                <p className="mt-1 text-xs text-gray-500">Business Settings → Business Info → Business Manager ID</p>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Access Token <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    value={form.access_token}
                                    onChange={(e) => setForm({ ...form, access_token: e.target.value })}
                                    rows={3}
                                    placeholder="System User access token with ads_read permission"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs outline-none focus:ring-2 focus:ring-orange-500"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Business Settings → Users → System Users → Generate Token (select ads_read; System User tokens never expire).
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                            <button onClick={() => setShowModal(false)} className="rounded-lg px-4 py-2 font-medium text-gray-700 hover:bg-gray-100">
                                Cancel
                            </button>
                            <button
                                onClick={handleAdd}
                                disabled={saving}
                                className="fs-cta rounded-lg px-4 py-2 font-medium text-white disabled:opacity-50"
                            >
                                {saving ? 'Verifying...' : 'Connect & Verify'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

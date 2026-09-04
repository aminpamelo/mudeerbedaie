/**
 * Studio Facebook Ads — connect multiple Business Managers (via System User
 * access tokens), sync their ad accounts + daily campaign insights, and see
 * which funnels use which ad account. Connecting runs through a guided
 * 4-step onboarding wizard.
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
    const [showWizard, setShowWizard] = useState(false);
    const [syncingId, setSyncingId] = useState(null);
    const [editing, setEditing] = useState(null);

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
                <button onClick={() => setShowWizard(true)} className="fs-cta rounded-lg px-4 py-2 font-medium text-white">
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
                        A short guided setup will walk you through getting your Business Manager ID and a permanent
                        System User token — no developer knowledge needed.
                    </p>
                    <button onClick={() => setShowWizard(true)} className="fs-cta rounded-lg px-6 py-3 font-medium text-white">
                        Start Guided Setup
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
                                        onClick={() => setEditing(connection)}
                                        className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        Edit
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

            {showWizard && (
                <ConnectWizard
                    onClose={() => setShowWizard(false)}
                    onFinished={() => {
                        setShowWizard(false);
                        loadConnections();
                    }}
                />
            )}

            {editing && (
                <EditConnectionModal
                    connection={editing}
                    showToast={showToast}
                    onClose={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        loadConnections();
                    }}
                />
            )}
        </div>
    );
}

/**
 * Edit a connection's name, Business Manager ID, and (optionally) access token.
 * Leaving the token blank keeps the current one. Changing the BM ID or pasting
 * a new token re-verifies with Facebook server-side before the change sticks.
 */
function EditConnectionModal({ connection, onClose, onSaved, showToast }) {
    const [form, setForm] = useState({
        name: connection.name || '',
        business_manager_id: connection.business_manager_id || '',
        access_token: '',
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const cleanBmId = form.business_manager_id.replace(/\s/g, '');
    const bmChanged = cleanBmId !== connection.business_manager_id;
    const tokenProvided = form.access_token.trim().length > 0;
    const willReverify = bmChanged || tokenProvided;

    const save = async () => {
        if (!form.name.trim()) {
            setError('Give this connection a name so your team recognises it.');
            return;
        }
        if (!/^\d{6,}$/.test(cleanBmId)) {
            setError('The Business Manager ID should be numbers only (usually 15-16 digits).');
            return;
        }
        if (tokenProvided && form.access_token.trim().length < 30) {
            setError('That token looks too short — make sure you copied the whole thing.');
            return;
        }

        setError(null);
        setSaving(true);
        try {
            const response = await fetch(`/api/v1/facebook-ads/connections/${connection.id}`, {
                method: 'PUT',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: form.name.trim(),
                    business_manager_id: cleanBmId,
                    access_token: form.access_token.trim(),
                }),
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message || 'Connection updated');
                onSaved();
            } else {
                setError(data.message || 'Could not update the connection.');
            }
        } catch (err) {
            setError('Network error — could not reach the server.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-lg rounded-xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 className="text-lg font-semibold text-gray-900">Edit connection</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="space-y-4 px-6 py-5">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-700">Connection name</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>

                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-700">Business Manager ID</label>
                        <input
                            type="text"
                            value={form.business_manager_id}
                            onChange={(e) => setForm({ ...form, business_manager_id: e.target.value })}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>

                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-700">
                            New access token <span className="font-normal text-gray-400">— leave blank to keep the current one</span>
                        </label>
                        <textarea
                            value={form.access_token}
                            onChange={(e) => setForm({ ...form, access_token: e.target.value })}
                            rows={3}
                            placeholder="Paste a new System User token to rotate it"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs outline-none focus:ring-2 focus:ring-orange-500"
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            The current token is never shown. Paste a new one only if you need to rotate it.
                        </p>
                    </div>

                    {willReverify && (
                        <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-800">
                            {bmChanged
                                ? 'Changing the Business Manager ID will re-verify with Facebook and re-sync its ad accounts.'
                                : 'The new token will be verified with Facebook before it is saved.'}
                        </div>
                    )}

                    {error && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
                </div>

                <div className="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg px-4 py-2 font-medium text-gray-600 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button
                        onClick={save}
                        disabled={saving}
                        className="fs-cta rounded-lg px-5 py-2 font-medium text-white disabled:opacity-50"
                    >
                        {saving
                            ? (willReverify ? 'Verifying…' : 'Saving…')
                            : (willReverify ? 'Save & re-verify' : 'Save changes')}
                    </button>
                </div>
            </div>
        </div>
    );
}

/**
 * Guided 4-step onboarding for connecting a Business Manager:
 * 1. What this does + what you need
 * 2. Find the Business Manager ID (with a direct link)
 * 3. Create a System User + generate the token (numbered walkthrough)
 * 4. Verify, sync, and land on a success (or fix-it) screen
 */
const WIZARD_STEPS = ['Overview', 'Business Manager', 'Access Token', 'Verify'];

const ERROR_HINTS = [
    'Copy the FULL token — it is long and easy to cut off.',
    'The token must belong to a System User inside this same Business Manager.',
    'When generating the token, tick the ads_read permission.',
    'The System User must be assigned your ad accounts (Add Assets).',
    'Double-check the Business Manager ID — numbers only.',
];

function ConnectWizard({ onClose, onFinished }) {
    const [step, setStep] = useState(0);
    const [form, setForm] = useState({ name: '', business_manager_id: '', access_token: '' });
    const [phase, setPhase] = useState('idle'); // idle | verifying | syncing | success | error
    const [result, setResult] = useState(null);
    const [fieldError, setFieldError] = useState(null);

    const bmIdValid = /^\d{6,}$/.test(form.business_manager_id.replace(/\s/g, ''));

    const nextFromBm = () => {
        if (!form.name.trim()) {
            setFieldError('Give this connection a name so your team recognises it.');
            return;
        }
        if (!bmIdValid) {
            setFieldError('The Business Manager ID should be numbers only (usually 15-16 digits).');
            return;
        }
        setFieldError(null);
        setStep(2);
    };

    const nextFromToken = () => {
        if (form.access_token.trim().length < 30) {
            setFieldError('That token looks too short — make sure you copied the whole thing.');
            return;
        }
        setFieldError(null);
        setStep(3);
        connect();
    };

    const connect = async () => {
        setPhase('verifying');
        setResult(null);
        try {
            const response = await fetch('/api/v1/facebook-ads/connections', {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: form.name.trim(),
                    business_manager_id: form.business_manager_id.replace(/\s/g, ''),
                    access_token: form.access_token.trim(),
                }),
            });
            const data = await response.json();
            if (data.success) {
                setResult(data);
                setPhase('success');
            } else {
                setResult(data);
                setPhase('error');
            }
        } catch (err) {
            setResult({ message: 'Network error — could not reach the server.' });
            setPhase('error');
        }
    };

    const syncInsights = async () => {
        if (!result?.connection_id) return;
        setPhase('syncing');
        try {
            await fetch(`/api/v1/facebook-ads/connections/${result.connection_id}/sync`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ days: 30 }),
            });
        } catch (err) {
            // The daily scheduler will retry; the connection itself is fine.
        }
        onFinished();
    };

    const stepIndex = Math.min(step, 3);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div
                className="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header + progress */}
                <div className="border-b border-gray-200 px-6 py-4">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">Connect Facebook Ads</h3>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                        {WIZARD_STEPS.map((label, i) => (
                            <React.Fragment key={label}>
                                {i > 0 && <div className={`h-px flex-1 ${i <= stepIndex ? 'bg-orange-500' : 'bg-gray-200'}`} />}
                                <div className="flex items-center gap-1.5">
                                    <span
                                        className={`flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold ${
                                            i < stepIndex
                                                ? 'bg-orange-600 text-white'
                                                : i === stepIndex
                                                    ? 'border-2 border-orange-500 text-orange-600'
                                                    : 'border border-gray-300 text-gray-400'
                                        }`}
                                    >
                                        {i < stepIndex ? '✓' : i + 1}
                                    </span>
                                    <span className={`hidden text-[11px] font-medium sm:block ${i === stepIndex ? 'text-gray-900' : 'text-gray-400'}`}>
                                        {label}
                                    </span>
                                </div>
                            </React.Fragment>
                        ))}
                    </div>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto px-6 py-5">
                    {step === 0 && (
                        <div className="space-y-5">
                            <p className="text-sm text-gray-600">
                                In about 3 minutes you'll link a Facebook Business Manager to the Studio. After that,
                                everything below stays up to date automatically:
                            </p>
                            <div className="space-y-3">
                                {[
                                    ['All ad accounts in the BM', 'Owned and client accounts are pulled in automatically.'],
                                    ['Daily spend & campaign performance', 'Spend, impressions, clicks, CPM, CPC, CTR — refreshed every morning.'],
                                    ['Funnel attribution', 'Link each funnel to the ad account that feeds it, so you can see spend vs sales.'],
                                ].map(([title, desc]) => (
                                    <div key={title} className="flex gap-3 rounded-lg bg-gray-50 p-3">
                                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{title}</p>
                                            <p className="text-xs text-gray-500">{desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-800">
                                <span className="font-semibold">You'll need:</span> Admin access to the Business Manager
                                (business.facebook.com). Nothing is posted or changed on your ads — the token is read-only.
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Connection name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    placeholder="e.g., BM Bedaie Utama"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-orange-500"
                                />
                            </div>

                            <div className="rounded-lg bg-gray-50 p-4">
                                <p className="mb-2 text-sm font-medium text-gray-900">Find your Business Manager ID:</p>
                                <ol className="list-decimal space-y-1.5 pl-5 text-sm text-gray-600">
                                    <li>
                                        Open{' '}
                                        <a
                                            href="https://business.facebook.com/settings/info"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-medium text-orange-600 underline hover:text-orange-700"
                                        >
                                            Business Settings → Business Info
                                        </a>{' '}
                                        (opens in a new tab)
                                    </li>
                                    <li>
                                        Copy the number under <span className="font-medium">Business Manager ID</span>
                                    </li>
                                    <li>Paste it below</li>
                                </ol>
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
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono outline-none focus:ring-2 focus:ring-orange-500"
                                />
                                {form.business_manager_id && bmIdValid && (
                                    <p className="mt-1 flex items-center gap-1 text-xs text-green-600">
                                        <span className="h-1.5 w-1.5 rounded-full bg-green-500" /> Looks good
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <div className="rounded-lg bg-gray-50 p-4">
                                <p className="mb-2 text-sm font-medium text-gray-900">
                                    Create a System User & token (one-time, ~2 minutes):
                                </p>
                                <ol className="list-decimal space-y-2 pl-5 text-sm text-gray-600">
                                    <li>
                                        Open{' '}
                                        <a
                                            href="https://business.facebook.com/settings/system-users"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-medium text-orange-600 underline hover:text-orange-700"
                                        >
                                            Business Settings → Users → System Users
                                        </a>
                                    </li>
                                    <li>
                                        Click <span className="font-medium">Add</span> → name it (e.g. "Funnel Studio") →
                                        role <span className="font-medium">Admin</span>
                                    </li>
                                    <li>
                                        Click <span className="font-medium">Add Assets</span> → Ad Accounts → select your
                                        accounts → enable <span className="font-medium">View performance</span>
                                    </li>
                                    <li>
                                        Click <span className="font-medium">Generate New Token</span> → choose any app →
                                        tick <span className="font-mono text-xs">ads_read</span> → set expiry to{' '}
                                        <span className="font-medium">Never</span>
                                    </li>
                                    <li>Copy the token and paste it below</li>
                                </ol>
                            </div>

                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700">
                                    Access Token <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    value={form.access_token}
                                    onChange={(e) => setForm({ ...form, access_token: e.target.value })}
                                    rows={4}
                                    placeholder="Paste the System User access token here"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs outline-none focus:ring-2 focus:ring-orange-500"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Stored securely on your server and only used to read ads data. It is never shown again.
                                </p>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="py-4">
                            {(phase === 'verifying' || phase === 'syncing') && (
                                <div className="flex flex-col items-center gap-4 py-8 text-center">
                                    <div className="h-10 w-10 animate-spin rounded-full border-4 border-orange-200 border-t-orange-600" />
                                    <div>
                                        <p className="font-medium text-gray-900">
                                            {phase === 'verifying' ? 'Verifying with Facebook...' : 'Syncing 30 days of ads data...'}
                                        </p>
                                        <p className="mt-1 text-sm text-gray-500">
                                            {phase === 'verifying'
                                                ? 'Checking your token and Business Manager access.'
                                                : 'Pulling spend and campaign performance. This can take up to a minute.'}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {phase === 'success' && (
                                <div className="space-y-5 text-center">
                                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                                        <svg className="h-7 w-7 text-green-600" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 className="text-lg font-semibold text-gray-900">Connected!</h4>
                                        <p className="mt-1 text-sm text-gray-600">{result?.message}</p>
                                        <p className="mt-2 text-sm font-medium text-gray-900">
                                            {result?.accounts_count ?? 0} ad account(s) found
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-gray-50 p-3 text-left text-xs text-gray-600">
                                        <span className="font-semibold text-gray-900">Next:</span> pull the last 30 days of
                                        spend now, then open any funnel's Tracking tab and set its{' '}
                                        <span className="font-medium">Ads Source</span> so spend is attributed per funnel.
                                    </div>
                                </div>
                            )}

                            {phase === 'error' && (
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
                                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <div>
                                            <p className="font-medium text-red-800">Facebook rejected the connection</p>
                                            <p className="mt-0.5 text-sm text-red-700">{result?.message}</p>
                                        </div>
                                    </div>
                                    <div className="rounded-lg bg-gray-50 p-4">
                                        <p className="mb-2 text-sm font-medium text-gray-900">Common fixes:</p>
                                        <ul className="list-disc space-y-1 pl-5 text-sm text-gray-600">
                                            {ERROR_HINTS.map((hint) => (
                                                <li key={hint}>{hint}</li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {fieldError && step < 3 && (
                        <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{fieldError}</p>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t border-gray-200 px-6 py-4">
                    <div>
                        {step > 0 && step < 3 && (
                            <button
                                onClick={() => {
                                    setFieldError(null);
                                    setStep(step - 1);
                                }}
                                className="rounded-lg px-4 py-2 font-medium text-gray-600 hover:bg-gray-100"
                            >
                                ← Back
                            </button>
                        )}
                        {step === 3 && phase === 'error' && (
                            <button
                                onClick={() => {
                                    setPhase('idle');
                                    setStep(2);
                                }}
                                className="rounded-lg px-4 py-2 font-medium text-gray-600 hover:bg-gray-100"
                            >
                                ← Fix the token
                            </button>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {step === 0 && (
                            <button onClick={() => setStep(1)} className="fs-cta rounded-lg px-5 py-2 font-medium text-white">
                                Start →
                            </button>
                        )}
                        {step === 1 && (
                            <button onClick={nextFromBm} className="fs-cta rounded-lg px-5 py-2 font-medium text-white">
                                Continue →
                            </button>
                        )}
                        {step === 2 && (
                            <button onClick={nextFromToken} className="fs-cta rounded-lg px-5 py-2 font-medium text-white">
                                Connect & Verify →
                            </button>
                        )}
                        {step === 3 && phase === 'success' && (
                            <>
                                <button onClick={onFinished} className="rounded-lg px-4 py-2 font-medium text-gray-600 hover:bg-gray-100">
                                    Skip for now
                                </button>
                                <button onClick={syncInsights} className="fs-cta rounded-lg px-5 py-2 font-medium text-white">
                                    Sync 30 days of data
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

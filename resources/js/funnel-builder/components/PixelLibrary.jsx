/**
 * Pixel Library
 * Reusable tracking pixels (Facebook / Google) that can be applied to any
 * funnel from the builder's Tracking tab. Supports grouping, CRUD, and a
 * credential test per pixel.
 */

import React, { useState, useEffect, useCallback } from 'react';

function Toast({ message, type = 'success', onClose }) {
    useEffect(() => {
        const timer = setTimeout(() => {
            onClose();
        }, 3000);
        return () => clearTimeout(timer);
    }, [onClose]);

    return (
        <div className={`toast toast-${type}`}>
            <div className="flex items-center gap-2">
                {type === 'success' && (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                )}
                {type === 'error' && (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                )}
                <span>{message}</span>
            </div>
        </div>
    );
}

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

/**
 * Turn a failed response into a human-readable message. Surfaces the first
 * validation error (422), a session-expiry hint (419), or falls back to the
 * status code when the body isn't JSON — so the user never sees a bare
 * "Failed to save pixel" that hides the real cause.
 */
async function extractError(response, fallback) {
    if (response.status === 419) {
        return 'Your session expired. Please refresh the page and try again.';
    }

    try {
        const body = await response.json();
        if (body?.errors) {
            const first = Object.values(body.errors)[0];
            if (Array.isArray(first) && first[0]) {
                return first[0];
            }
        }
        if (body?.message) {
            return body.message;
        }
    } catch (err) {
        // Body wasn't JSON (e.g. an HTML error page) — fall through.
    }

    return `${fallback} (HTTP ${response.status}).`;
}

const PLATFORM_META = {
    facebook: { label: 'Facebook', badge: 'bg-blue-100 text-blue-700' },
    google: { label: 'Google', badge: 'bg-red-100 text-red-700' },
};

const emptyForm = {
    name: '',
    platform: 'facebook',
    group_name: '',
    pixel_id: '',
    access_token: '',
    test_event_code: '',
    ga4_measurement_id: '',
    ads_conversion_id: '',
    ads_purchase_label: '',
};

function formFromPixel(pixel) {
    return {
        name: pixel.name || '',
        platform: pixel.platform || 'facebook',
        group_name: pixel.group_name || '',
        pixel_id: pixel.settings?.pixel_id || '',
        access_token: pixel.settings?.access_token || '',
        test_event_code: pixel.settings?.test_event_code || '',
        ga4_measurement_id: pixel.settings?.ga4_measurement_id || '',
        ads_conversion_id: pixel.settings?.ads_conversion_id || '',
        ads_purchase_label: pixel.settings?.ads_purchase_label || '',
    };
}

function settingsFromForm(form) {
    if (form.platform === 'facebook') {
        return {
            pixel_id: form.pixel_id.trim(),
            access_token: form.access_token.trim(),
            test_event_code: form.test_event_code.trim(),
        };
    }

    return {
        ga4_measurement_id: form.ga4_measurement_id.trim(),
        ads_conversion_id: form.ads_conversion_id.trim(),
        ads_purchase_label: form.ads_purchase_label.trim(),
    };
}

function pixelSummary(pixel) {
    if (pixel.platform === 'facebook') {
        return pixel.settings?.pixel_id || '—';
    }

    return [pixel.settings?.ga4_measurement_id, pixel.settings?.ads_conversion_id].filter(Boolean).join(' · ') || '—';
}

export default function PixelLibrary({ onBack, onSelectFunnel }) {
    const [pixels, setPixels] = useState([]);
    const [health, setHealth] = useState(null);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);
    const [platformFilter, setPlatformFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [saving, setSaving] = useState(false);
    const [testingId, setTestingId] = useState(null);

    const showToast = useCallback((message, type = 'success') => {
        setToast({ message, type });
    }, []);

    const loadPixels = useCallback(async () => {
        try {
            const response = await fetch('/api/v1/pixel-library', {
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            setPixels(data.data || []);
        } catch (err) {
            showToast('Failed to load pixel library', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        loadPixels();
    }, [loadPixels]);

    // Pixel health across published funnels (recorded by funnel:pixel-health)
    useEffect(() => {
        (async () => {
            try {
                const response = await fetch('/api/v1/studio/pixel-health', {
                    headers: apiHeaders(),
                    credentials: 'same-origin',
                });
                const data = await response.json();
                setHealth(data.data || null);
            } catch (err) {
                // Health banner is best-effort only.
            }
        })();
    }, []);

    const openCreateModal = () => {
        setEditingId(null);
        setForm(emptyForm);
        setShowModal(true);
    };

    const openEditModal = (pixel) => {
        setEditingId(pixel.id);
        setForm(formFromPixel(pixel));
        setShowModal(true);
    };

    const handleSave = async () => {
        if (!form.name.trim()) {
            showToast('Please give this pixel a name', 'error');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                name: form.name.trim(),
                platform: form.platform,
                group_name: form.group_name.trim() || null,
                settings: settingsFromForm(form),
            };

            const url = editingId ? `/api/v1/pixel-library/${editingId}` : '/api/v1/pixel-library';
            const response = await fetch(url, {
                method: editingId ? 'PUT' : 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                showToast(editingId ? 'Pixel updated' : 'Pixel added to library');
                setShowModal(false);
                loadPixels();
            } else {
                showToast(await extractError(response, 'Failed to save pixel'), 'error');
            }
        } catch (err) {
            showToast('Failed to save pixel', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (pixel) => {
        const usage = pixel.linked_funnels_count
            ? ` It is applied to ${pixel.linked_funnels_count} funnel(s) — they keep their settings, only the library entry is removed.`
            : '';
        if (!confirm(`Delete "${pixel.name}" from the library?${usage}`)) return;

        try {
            const response = await fetch(`/api/v1/pixel-library/${pixel.id}`, {
                method: 'DELETE',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });

            if (response.ok) {
                showToast('Pixel deleted');
                loadPixels();
            } else {
                const error = await response.json();
                showToast(error.message || 'Failed to delete pixel', 'error');
            }
        } catch (err) {
            showToast('Failed to delete pixel', 'error');
        }
    };

    const handleTest = async (pixel) => {
        setTestingId(pixel.id);
        try {
            const response = await fetch(`/api/v1/pixel-library/${pixel.id}/test`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            showToast(data.message || (data.success ? 'Test passed' : 'Test failed'), data.success ? 'success' : 'error');
            if (data.data) {
                setPixels((prev) => prev.map((p) => (p.id === data.data.id ? data.data : p)));
            }
        } catch (err) {
            showToast('Failed to test pixel', 'error');
        } finally {
            setTestingId(null);
        }
    };

    const filtered = pixels.filter((pixel) => {
        if (platformFilter !== 'all' && pixel.platform !== platformFilter) return false;
        if (search) {
            const haystack = `${pixel.name} ${pixel.group_name || ''} ${pixelSummary(pixel)}`.toLowerCase();
            if (!haystack.includes(search.toLowerCase())) return false;
        }
        return true;
    });

    const groups = filtered.reduce((acc, pixel) => {
        const key = pixel.group_name || 'Ungrouped';
        (acc[key] = acc[key] || []).push(pixel);
        return acc;
    }, {});

    return (
        <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-3">
                    <button
                        onClick={onBack}
                        className="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Pixel Library</h1>
                        <p className="text-sm text-gray-500">
                            Store your tracking pixels once, then apply them to any funnel from its Tracking tab.
                        </p>
                    </div>
                </div>
                <button
                    onClick={openCreateModal}
                    className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium"
                >
                    + New Pixel
                </button>
            </div>

            {/* Pixel health warnings from the daily installation check */}
            {health && health.problems.length > 0 && (
                <div className="mb-6 rounded-lg border border-red-100 bg-red-50 p-4">
                    <div className="mb-2 flex items-center gap-2">
                        <svg className="h-5 w-5 text-red-600" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <h3 className="font-semibold text-red-800">
                            {health.problems.length} funnel(s) with broken tracking
                        </h3>
                        {health.last_checked_at && (
                            <span className="ml-auto text-xs text-red-600">
                                Last checked: {new Date(health.last_checked_at).toLocaleString()}
                            </span>
                        )}
                    </div>
                    <div className="space-y-2">
                        {health.problems.map((problem) => (
                            <button
                                key={problem.funnel_uuid}
                                onClick={() => onSelectFunnel?.({ uuid: problem.funnel_uuid })}
                                className="block w-full rounded-md bg-white/60 px-3 py-2 text-left transition-colors hover:bg-white cursor-pointer"
                            >
                                <span className="block text-sm font-medium text-red-800">{problem.funnel_name}</span>
                                {problem.issues.map((issue, i) => (
                                    <span key={i} className="block text-xs text-red-700">
                                        <span className="font-medium capitalize">{issue.platform}:</span> {issue.message}
                                    </span>
                                ))}
                            </button>
                        ))}
                    </div>
                </div>
            )}
            {health && health.problems.length === 0 && health.checked_funnels > 0 && (
                <div className="mb-6 flex items-center gap-2 rounded-lg border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <span className="h-2 w-2 rounded-full bg-green-500" />
                    All {health.checked_funnels} published funnel(s) passed the last pixel installation check
                    {health.last_checked_at ? ` (${new Date(health.last_checked_at).toLocaleString()})` : ''}.
                </div>
            )}

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3 mb-6">
                <div className="flex rounded-lg border border-gray-200 bg-white p-1">
                    {['all', 'facebook', 'google'].map((key) => (
                        <button
                            key={key}
                            onClick={() => setPlatformFilter(key)}
                            className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                                platformFilter === key ? 'bg-orange-600 text-white' : 'text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            {key === 'all' ? 'All' : PLATFORM_META[key].label}
                        </button>
                    ))}
                </div>
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search pixels..."
                    className="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                />
            </div>

            {/* List */}
            {loading ? (
                <div className="text-center py-16 text-gray-500">Loading pixel library...</div>
            ) : filtered.length === 0 ? (
                <div className="bg-white rounded-lg border border-gray-200 p-12 text-center">
                    <svg className="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">No pixels yet</h3>
                    <p className="text-gray-500 mb-6 max-w-md mx-auto">
                        Add your Facebook Pixel or Google tracking IDs once, then reuse them across all your funnels.
                    </p>
                    <button
                        onClick={openCreateModal}
                        className="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium"
                    >
                        Add Your First Pixel
                    </button>
                </div>
            ) : (
                <div className="space-y-8">
                    {Object.entries(groups).map(([groupName, groupPixels]) => (
                        <div key={groupName}>
                            <h2 className="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{groupName}</h2>
                            <div className="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
                                {groupPixels.map((pixel) => (
                                    <div key={pixel.id} className="flex flex-wrap items-center gap-4 p-4">
                                        <div className="flex-1 min-w-[220px]">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-gray-900">{pixel.name}</span>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${PLATFORM_META[pixel.platform]?.badge || 'bg-gray-100 text-gray-600'}`}>
                                                    {PLATFORM_META[pixel.platform]?.label || pixel.platform}
                                                </span>
                                            </div>
                                            <div className="text-sm text-gray-500 font-mono mt-0.5">{pixelSummary(pixel)}</div>
                                            <div className="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                                <span>{pixel.linked_funnels_count} funnel(s) using this</span>
                                                {pixel.last_test_status && (
                                                    <span
                                                        className={`inline-flex items-center gap-1 ${pixel.last_test_status === 'passed' ? 'text-green-600' : 'text-red-600'}`}
                                                        title={pixel.last_test_message || ''}
                                                    >
                                                        <span className={`w-1.5 h-1.5 rounded-full ${pixel.last_test_status === 'passed' ? 'bg-green-500' : 'bg-red-500'}`} />
                                                        {pixel.last_test_status === 'passed' ? 'Test passed' : 'Test failed'}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => handleTest(pixel)}
                                                disabled={testingId === pixel.id}
                                                className="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-300 rounded-lg disabled:opacity-50"
                                            >
                                                {testingId === pixel.id ? 'Testing...' : 'Test'}
                                            </button>
                                            <button
                                                onClick={() => openEditModal(pixel)}
                                                className="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 border border-gray-300 rounded-lg"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => handleDelete(pixel)}
                                                className="px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                        {pixel.last_test_message && pixel.last_test_status === 'failed' && (
                                            <div className="w-full text-xs text-red-600 bg-red-50 border border-red-100 rounded-md px-3 py-2">
                                                {pixel.last_test_message}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Create / Edit Modal */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowModal(false)}>
                    <div
                        className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-900">{editingId ? 'Edit Pixel' : 'New Pixel'}</h3>
                            <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div className="px-6 py-5 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    placeholder="e.g., Bedaie Main Pixel"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Platform</label>
                                    <select
                                        value={form.platform}
                                        onChange={(e) => setForm({ ...form, platform: e.target.value })}
                                        disabled={!!editingId}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-500"
                                    >
                                        <option value="facebook">Facebook Pixel</option>
                                        <option value="google">Google (GA4 / Ads)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Group <span className="text-xs font-normal text-gray-500">(optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={form.group_name}
                                        onChange={(e) => setForm({ ...form, group_name: e.target.value })}
                                        placeholder="e.g., Brand A"
                                        list="pixel-group-suggestions"
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    />
                                    <datalist id="pixel-group-suggestions">
                                        {[...new Set(pixels.map((p) => p.group_name).filter(Boolean))].map((g) => (
                                            <option key={g} value={g} />
                                        ))}
                                    </datalist>
                                </div>
                            </div>

                            {form.platform === 'facebook' ? (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Pixel ID <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={form.pixel_id}
                                            onChange={(e) => setForm({ ...form, pixel_id: e.target.value })}
                                            placeholder="15-16 digit Pixel ID"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        />
                                        <p className="text-xs text-gray-500 mt-1">
                                            Facebook Events Manager → Data Sources → Your Pixel → Settings
                                        </p>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Conversions API Access Token
                                            <span className="ml-2 text-xs font-normal text-green-600">(Recommended)</span>
                                        </label>
                                        <input
                                            type="password"
                                            value={form.access_token}
                                            onChange={(e) => setForm({ ...form, access_token: e.target.value })}
                                            placeholder="For server-side tracking"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Test Event Code <span className="text-xs font-normal text-gray-500">(Optional)</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={form.test_event_code}
                                            onChange={(e) => setForm({ ...form, test_event_code: e.target.value })}
                                            placeholder="e.g., TEST12345"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">GA4 Measurement ID</label>
                                        <input
                                            type="text"
                                            value={form.ga4_measurement_id}
                                            onChange={(e) => setForm({ ...form, ga4_measurement_id: e.target.value })}
                                            placeholder="G-XXXXXXXXXX"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Google Ads Conversion ID</label>
                                        <input
                                            type="text"
                                            value={form.ads_conversion_id}
                                            onChange={(e) => setForm({ ...form, ads_conversion_id: e.target.value })}
                                            placeholder="AW-XXXXXXXXX"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Purchase Conversion Label <span className="text-xs font-normal text-gray-500">(Optional)</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={form.ads_purchase_label}
                                            onChange={(e) => setForm({ ...form, ads_purchase_label: e.target.value })}
                                            placeholder="e.g., AbC-D_efGh12ijkLmn"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                        />
                                    </div>
                                </>
                            )}
                        </div>

                        <div className="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                            <button
                                onClick={() => setShowModal(false)}
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSave}
                                disabled={saving}
                                className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                {saving ? 'Saving...' : editingId ? 'Save Changes' : 'Add Pixel'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Landing Library modals used from a funnel step:
 *  - UseTemplateModal: pick a template (with live preview) and copy it into
 *    the step as a draft.
 *  - SaveTemplateModal: snapshot the step's page into the library.
 */

import React, { useState, useEffect } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

export const landingApi = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(firstError || data.message || 'Request failed');
    }
    return data;
};

export const TEMPLATE_CATEGORIES = {
    sales: 'Sales page',
    lead: 'Lead / opt-in',
    webinar: 'Webinar / event',
    product: 'Product',
    course: 'Course / class',
    donation: 'Donation / CF',
    thankyou: 'Thank you',
    other: 'Other',
};

function Shell({ title, onClose, children, footer, wide }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div
                className={`flex max-h-[92vh] w-full ${wide ? 'max-w-5xl' : 'max-w-md'} flex-col rounded-xl bg-white shadow-xl`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Close">
                        ✕
                    </button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-gray-200 px-5 py-3">{footer}</div>}
            </div>
        </div>
    );
}

export function UseTemplateModal({ funnelUuid, step, onClose, onApplied }) {
    const [templates, setTemplates] = useState(null);
    const [selected, setSelected] = useState(null);
    const [search, setSearch] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        landingApi('/api/v1/landing-templates/options')
            .then((data) => {
                setTemplates(data.data || []);
                setSelected(data.data?.[0] || null);
            })
            .catch((e) => setError(e.message));
    }, []);

    const filtered = (templates || []).filter((t) => t.name.toLowerCase().includes(search.toLowerCase()));

    const apply = async () => {
        if (!selected) return;
        setBusy(true);
        setError(null);
        try {
            const data = await landingApi(`/api/v1/funnels/${funnelUuid}/steps/${step.id}/landing-template`, {
                method: 'POST',
                body: JSON.stringify({ template_id: selected.id }),
            });
            onApplied(data.message);
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Shell
            wide
            title={`Use a template for "${step.name}"`}
            onClose={onClose}
            footer={
                <>
                    <span className="mr-auto self-center text-xs text-gray-500">
                        Copies the design into this step as a <span className="font-semibold">draft</span> — the live page only changes when you publish.
                    </span>
                    <button onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Cancel
                    </button>
                    <button onClick={apply} disabled={!selected || busy} className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {busy ? 'Applying...' : 'Use this template'}
                    </button>
                </>
            }
        >
            {error && <div className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
            {templates === null ? (
                <div className="py-16 text-center text-sm text-gray-500">Loading library...</div>
            ) : templates.length === 0 ? (
                <div className="py-16 text-center text-sm text-gray-500">
                    The Landing Library is empty.{' '}
                    <a href="/funnel-builder/landing-library" className="text-orange-600 underline">
                        Open the library
                    </a>{' '}
                    to add templates.
                </div>
            ) : (
                <div className="flex h-[65vh] gap-4">
                    <div className="flex w-64 shrink-0 flex-col">
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search templates..."
                            className="mb-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                        <div className="min-h-0 flex-1 space-y-1 overflow-y-auto">
                            {filtered.map((t) => (
                                <button
                                    key={t.id}
                                    onClick={() => setSelected(t)}
                                    className={`block w-full rounded-lg border px-3 py-2 text-left text-sm ${
                                        selected?.id === t.id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    <span className="block truncate font-medium text-gray-900">{t.name}</span>
                                    <span className="text-xs text-gray-500">{TEMPLATE_CATEGORIES[t.category] || 'Uncategorised'}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="min-w-0 flex-1 overflow-hidden rounded-lg border border-gray-200 bg-white">
                        {selected && <iframe key={selected.id} src={selected.preview_url} title="Template preview" className="h-full w-full" />}
                    </div>
                </div>
            )}
        </Shell>
    );
}

export function SaveTemplateModal({ funnelUuid, step, defaultName, onClose, onSaved }) {
    const [form, setForm] = useState({ name: defaultName || step.name, description: '', category: step.type === 'thankyou' ? 'thankyou' : 'sales' });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const save = async () => {
        setBusy(true);
        setError(null);
        try {
            const data = await landingApi(`/api/v1/funnels/${funnelUuid}/steps/${step.id}/save-as-landing-template`, {
                method: 'POST',
                body: JSON.stringify(form),
            });
            onSaved(data.message);
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Shell
            title="Save page to Landing Library"
            onClose={onClose}
            footer={
                <>
                    <button onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Cancel
                    </button>
                    <button onClick={save} disabled={busy || !form.name.trim()} className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {busy ? 'Saving...' : 'Save template'}
                    </button>
                </>
            }
        >
            {error && <div className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
            <div className="space-y-3">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">Template name</span>
                    <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">Category</span>
                    <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        {Object.entries(TEMPLATE_CATEGORIES).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">Notes (optional)</span>
                    <textarea
                        rows={2}
                        value={form.description}
                        onChange={(e) => setForm({ ...form, description: e.target.value })}
                        placeholder="e.g. Long-form sales page, testimonials + FAQ, works well for cold traffic"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    />
                </label>
                <p className="text-xs text-gray-500">This step will be marked as using the new template.</p>
            </div>
        </Shell>
    );
}

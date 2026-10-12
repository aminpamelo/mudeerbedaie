/**
 * Studio Landing Library — reusable landing page templates and which funnels
 * use each one, with 30-day traffic / orders / conversion so the team can
 * see which page designs actually sell. Templates come from "Save to
 * library" on a funnel step, a blank/HTML template made here, or a one-click
 * import of existing landing pages. Applying a template copies it into a
 * step (copy-on-apply); the step remembers its template.
 */

import React, { useState, useEffect, useCallback } from 'react';
import { landingApi, TEMPLATE_CATEGORIES } from './LandingTemplateModals';

const money = (v) => `RM ${Number(v || 0).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;

function Toast({ toast, onClose }) {
    useEffect(() => {
        const timer = setTimeout(onClose, 5000);
        return () => clearTimeout(timer);
    }, [onClose]);
    return (
        <div className={`toast toast-${toast.type}`}>
            <span>{toast.message}</span>
        </div>
    );
}

function Modal({ title, onClose, children, footer, size = 'md' }) {
    const width = { md: 'max-w-lg', xl: 'max-w-6xl' }[size];
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className={`flex max-h-[94vh] w-full ${width} flex-col rounded-xl bg-white shadow-xl`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h3 className="truncate text-lg font-semibold text-gray-900">{title}</h3>
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

export default function StudioLandingLibrary({ onSelectFunnel }) {
    const [templates, setTemplates] = useState([]);
    const [canImport, setCanImport] = useState(false);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [sort, setSort] = useState('newest');
    const [preview, setPreview] = useState(null);
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);
    const [importing, setImporting] = useState(false);
    const [expanded, setExpanded] = useState(null);

    const showToast = useCallback((message, type = 'success') => setToast({ message, type }), []);

    const load = useCallback(() => {
        landingApi('/api/v1/landing-templates')
            .then((data) => {
                setTemplates(data.data || []);
                setCanImport(!!data.can_import);
            })
            .catch((e) => showToast(e.message, 'error'))
            .finally(() => setLoading(false));
    }, [showToast]);

    useEffect(() => {
        load();
    }, [load]);

    const runImport = async () => {
        setImporting(true);
        try {
            const data = await landingApi('/api/v1/landing-templates/import', { method: 'POST' });
            showToast(data.message);
            load();
        } catch (e) {
            showToast(e.message, 'error');
        } finally {
            setImporting(false);
        }
    };

    const remove = async (template) => {
        if (!window.confirm(`Delete template "${template.name}"? Funnels using it keep their pages.`)) return;
        try {
            const data = await landingApi(`/api/v1/landing-templates/${template.id}`, { method: 'DELETE' });
            showToast(data.message);
            load();
        } catch (e) {
            showToast(e.message, 'error');
        }
    };

    const visible = templates
        .filter((t) => !category || (category === 'unused' ? t.funnels_count === 0 : t.category === category))
        .filter((t) => !search || `${t.name} ${t.description || ''} ${t.used_by.map((u) => u.funnel_name).join(' ')}`.toLowerCase().includes(search.toLowerCase()))
        .sort((a, b) => {
            if (sort === 'used') return b.funnels_count - a.funnels_count;
            if (sort === 'revenue') return b.revenue_30d - a.revenue_30d;
            if (sort === 'conversion') return (b.conversion_rate ?? -1) - (a.conversion_rate ?? -1);
            return b.id - a.id;
        });

    const totals = {
        templates: templates.length,
        inUse: templates.filter((t) => t.funnels_count > 0).length,
        funnels: new Set(templates.flatMap((t) => t.used_by.map((u) => u.funnel_uuid))).size,
    };

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {toast && <Toast toast={toast} onClose={() => setToast(null)} />}

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Landing <span className="fs-gradient-text">Library</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">
                        Reusable landing pages — see which funnel uses which page, and which designs convert best.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canImport && (
                        <button
                            onClick={runImport}
                            disabled={importing}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            title="Turn existing landing/sales pages into templates and link their funnels"
                        >
                            {importing ? 'Importing...' : 'Import existing pages'}
                        </button>
                    )}
                    <button onClick={() => setCreating(true)} className="fs-cta rounded-lg px-4 py-2 font-medium text-white">
                        + New template
                    </button>
                </div>
            </div>

            <div className="mb-4 grid grid-cols-3 gap-3">
                <SummaryCard label="Templates" value={totals.templates} />
                <SummaryCard label="In use" value={totals.inUse} />
                <SummaryCard label="Funnels linked" value={totals.funnels} />
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search templates or funnels..."
                    className="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm"
                />
                <select value={category} onChange={(e) => setCategory(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All categories</option>
                    {Object.entries(TEMPLATE_CATEGORIES).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                    <option value="unused">Not used by any funnel</option>
                </select>
                <select value={sort} onChange={(e) => setSort(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="newest">Newest</option>
                    <option value="used">Most used</option>
                    <option value="revenue">Revenue (30d)</option>
                    <option value="conversion">Conversion (30d)</option>
                </select>
            </div>

            {loading ? (
                <div className="py-20 text-center text-gray-500">Loading library...</div>
            ) : templates.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-12 text-center">
                    <h3 className="mb-2 text-lg font-medium text-gray-900">Your Landing Library is empty</h3>
                    <p className="mx-auto mb-6 max-w-lg text-sm text-gray-500">
                        {canImport
                            ? 'Import your existing landing pages in one click, or save any funnel step with the bookmark icon on its Steps tab.'
                            : 'Save any funnel step with the bookmark icon on its Steps tab, or create a template here.'}
                    </p>
                    {canImport && (
                        <button onClick={runImport} disabled={importing} className="fs-cta rounded-lg px-6 py-3 font-medium text-white disabled:opacity-50">
                            {importing ? 'Importing...' : 'Import existing pages'}
                        </button>
                    )}
                </div>
            ) : visible.length === 0 ? (
                <div className="py-16 text-center text-sm text-gray-500">No templates match.</div>
            ) : (
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {visible.map((t) => (
                        <div key={t.id} className="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <button onClick={() => setPreview(t)} className="group relative block h-52 overflow-hidden border-b border-gray-200 bg-white" title="Preview">
                                <iframe
                                    src={t.preview_url}
                                    title={t.name}
                                    loading="lazy"
                                    tabIndex={-1}
                                    className="pointer-events-none origin-top-left"
                                    style={{ width: '300%', height: '300%', transform: 'scale(0.3334)', border: 0 }}
                                />
                                <span className="absolute inset-0 flex items-center justify-center bg-black/0 text-sm font-medium text-white opacity-0 transition group-hover:bg-black/40 group-hover:opacity-100">
                                    Preview
                                </span>
                            </button>
                            <div className="flex flex-1 flex-col p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <h3 className="font-semibold text-gray-900" title={t.name}>
                                        {t.name}
                                    </h3>
                                    {!t.is_active && <span className="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">Hidden</span>}
                                </div>
                                <div className="mt-1 flex flex-wrap gap-1.5">
                                    <span className="rounded-full bg-orange-100 px-2 py-0.5 text-[11px] font-medium text-orange-800">
                                        {TEMPLATE_CATEGORIES[t.category] || 'Uncategorised'}
                                    </span>
                                    {t.is_full_html && <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-800">HTML page</span>}
                                </div>
                                {t.description && <p className="mt-2 line-clamp-2 text-xs text-gray-500">{t.description}</p>}

                                <div className="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-gray-50 px-3 py-2 text-center">
                                    <Metric label="Funnels" value={t.funnels_count} />
                                    <Metric label="Conv. 30d" value={t.conversion_rate !== null ? `${t.conversion_rate}%` : '—'} />
                                    <Metric label="Rev. 30d" value={money(t.revenue_30d)} />
                                </div>

                                <div className="mt-3 flex-1">
                                    {t.used_by.length === 0 ? (
                                        <p className="text-xs text-gray-400">Not used by any funnel yet.</p>
                                    ) : (
                                        <>
                                            <button onClick={() => setExpanded(expanded === t.id ? null : t.id)} className="text-xs font-medium text-orange-600 hover:underline">
                                                {expanded === t.id ? 'Hide' : 'Show'} funnels using this ({t.used_by.length})
                                            </button>
                                            {expanded === t.id && (
                                                <ul className="mt-2 space-y-1">
                                                    {t.used_by.map((u) => (
                                                        <li key={u.step_id} className="flex items-center justify-between gap-2 text-xs">
                                                            <button
                                                                onClick={() => onSelectFunnel?.({ uuid: u.funnel_uuid, name: u.funnel_name }, 'steps')}
                                                                className="truncate text-left text-gray-700 hover:text-orange-600"
                                                                title={`${u.funnel_name} → ${u.step_name}`}
                                                            >
                                                                {u.funnel_name} <span className="text-gray-400">· {u.step_name}</span>
                                                            </button>
                                                            <span className="shrink-0 tabular-nums text-gray-500">
                                                                {u.orders_30d} ord · {u.sessions_30d} visits
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </>
                                    )}
                                </div>

                                <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-xs text-gray-500">
                                    <span className="truncate">{t.created_by ? `By ${t.created_by}` : ''}</span>
                                    <div className="flex gap-1">
                                        <button onClick={() => setPreview(t)} className="rounded px-2 py-1 text-gray-700 hover:bg-gray-100">
                                            Preview
                                        </button>
                                        {t.can_edit && (
                                            <>
                                                <button onClick={() => setEditing(t)} className="rounded px-2 py-1 text-gray-700 hover:bg-gray-100">
                                                    Edit
                                                </button>
                                                <button onClick={() => remove(t)} className="rounded px-2 py-1 text-red-600 hover:bg-red-50">
                                                    Delete
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {preview && <PreviewModal template={preview} onClose={() => setPreview(null)} />}
            {editing && (
                <DetailsModal
                    template={editing}
                    onClose={() => setEditing(null)}
                    onSaved={(message) => {
                        setEditing(null);
                        showToast(message);
                        load();
                    }}
                />
            )}
            {creating && (
                <DetailsModal
                    onClose={() => setCreating(false)}
                    onSaved={(message) => {
                        setCreating(false);
                        showToast(message);
                        load();
                    }}
                />
            )}
        </div>
    );
}

function SummaryCard({ label, value }) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <span className="block text-[11px] uppercase tracking-wider text-gray-500">{label}</span>
            <span className="mt-1 block text-xl font-bold tabular-nums text-gray-900">{value}</span>
        </div>
    );
}

function Metric({ label, value }) {
    return (
        <div>
            <span className="block text-sm font-semibold tabular-nums text-gray-900">{value}</span>
            <span className="block text-[10px] uppercase tracking-wider text-gray-500">{label}</span>
        </div>
    );
}

function PreviewModal({ template, onClose }) {
    const [device, setDevice] = useState('desktop');
    return (
        <Modal
            size="xl"
            title={template.name}
            onClose={onClose}
            footer={
                <>
                    <div className="mr-auto flex gap-1">
                        {['desktop', 'mobile'].map((d) => (
                            <button
                                key={d}
                                onClick={() => setDevice(d)}
                                className={`rounded-lg px-3 py-1.5 text-sm capitalize ${device === d ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-700'}`}
                            >
                                {d}
                            </button>
                        ))}
                    </div>
                    <a href={template.preview_url} target="_blank" rel="noreferrer" className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Open in new tab
                    </a>
                </>
            }
        >
            <div className="flex h-[72vh] justify-center bg-gray-100">
                <iframe
                    src={template.preview_url}
                    title={template.name}
                    className="h-full bg-white"
                    style={{ width: device === 'mobile' ? 390 : '100%', border: 0 }}
                />
            </div>
        </Modal>
    );
}

function DetailsModal({ template, onClose, onSaved }) {
    const isNew = !template;
    const [form, setForm] = useState({
        name: template?.name || '',
        description: template?.description || '',
        category: template?.category || 'sales',
        is_active: template?.is_active ?? true,
        html: '',
    });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const save = async () => {
        setBusy(true);
        setError(null);
        try {
            if (isNew) {
                await landingApi('/api/v1/landing-templates', {
                    method: 'POST',
                    body: JSON.stringify({ name: form.name, description: form.description || null, category: form.category, html: form.html || null }),
                });
                onSaved('Template created. Apply it to a funnel step to start using it.');
            } else {
                await landingApi(`/api/v1/landing-templates/${template.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ name: form.name, description: form.description || null, category: form.category, is_active: form.is_active }),
                });
                onSaved('Template updated.');
            }
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            title={isNew ? 'New landing template' : 'Edit template details'}
            onClose={onClose}
            footer={
                <>
                    <button onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Cancel
                    </button>
                    <button onClick={save} disabled={busy || !form.name.trim()} className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {busy ? 'Saving...' : 'Save'}
                    </button>
                </>
            }
        >
            {error && <div className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
            <div className="space-y-3">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-gray-700">Name</span>
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
                    <span className="mb-1 block text-sm font-medium text-gray-700">Notes</span>
                    <textarea
                        rows={2}
                        value={form.description}
                        onChange={(e) => setForm({ ...form, description: e.target.value })}
                        placeholder="When to use it, what makes it work..."
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    />
                </label>
                {isNew ? (
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-gray-700">HTML (optional)</span>
                        <textarea
                            rows={8}
                            value={form.html}
                            onChange={(e) => setForm({ ...form, html: e.target.value })}
                            placeholder="Paste a full HTML page or section. Leave empty for a blank page you design in the editor."
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs"
                        />
                        <span className="mt-1 block text-xs text-gray-500">
                            Tip: to template a page you built in the editor, use the bookmark icon on the funnel's Steps tab instead.
                        </span>
                    </label>
                ) : (
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
                        Show in the template picker
                    </label>
                )}
            </div>
        </Modal>
    );
}

/**
 * Funnel List Component
 * Displays all funnels grouped by category (default) or as a flat grid.
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { categoryApi, funnelApi, templateApi } from '../services/api';
import { FUNNEL_STATUSES } from '../types';
import ManageCategoriesModal from './ManageCategoriesModal';
import { getCategoryColorClasses } from '../config/categoryColors';

const VIEW_KEY = 'funnelBuilder.listView';

export default function FunnelList({ onSelectFunnel, onCreateFunnel }) {
    const [funnels, setFunnels] = useState([]);
    const [categories, setCategories] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [view, setView] = useState(() => {
        if (typeof window === 'undefined') return 'grouped';
        return window.localStorage?.getItem(VIEW_KEY) || 'grouped';
    });
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showCategoriesModal, setShowCategoriesModal] = useState(false);
    const [creating, setCreating] = useState(false);
    const [collapsed, setCollapsed] = useState({});

    const [newFunnel, setNewFunnel] = useState({
        name: '',
        description: '',
        template_id: null,
        funnel_category_id: null,
    });

    useEffect(() => {
        if (typeof window !== 'undefined') {
            window.localStorage?.setItem(VIEW_KEY, view);
        }
    }, [view]);

    const loadFunnels = useCallback(async () => {
        setLoading(true);
        try {
            const params = {};
            if (search) params.search = search;
            if (statusFilter !== 'all') params.status = statusFilter;

            const response = await funnelApi.list(params);
            setFunnels(response.data || []);
        } catch (err) {
            setError(err.message || 'Failed to load funnels');
        } finally {
            setLoading(false);
        }
    }, [search, statusFilter]);

    const loadCategories = useCallback(async () => {
        try {
            const response = await categoryApi.list();
            setCategories(response.data || []);
        } catch (err) {
            console.error('Failed to load categories:', err);
        }
    }, []);

    const loadTemplates = useCallback(async () => {
        try {
            const response = await templateApi.list();
            setTemplates(response.data || []);
        } catch (err) {
            console.error('Failed to load templates:', err);
        }
    }, []);

    useEffect(() => {
        loadFunnels();
        loadCategories();
        loadTemplates();
    }, [loadFunnels, loadCategories, loadTemplates]);

    const handleCreate = async (e) => {
        e.preventDefault();
        setCreating(true);
        try {
            const response = await funnelApi.create(newFunnel);
            setShowCreateModal(false);
            setNewFunnel({ name: '', description: '', template_id: null, funnel_category_id: null });
            loadFunnels();
            if (onCreateFunnel) onCreateFunnel(response.data);
        } catch (err) {
            setError(err.message || 'Failed to create funnel');
        } finally {
            setCreating(false);
        }
    };

    const handleDuplicate = async (uuid) => {
        try {
            await funnelApi.duplicate(uuid);
            loadFunnels();
        } catch (err) {
            setError(err.message || 'Failed to duplicate funnel');
        }
    };

    const handleDelete = async (uuid) => {
        if (!confirm('Are you sure you want to delete this funnel?')) return;
        try {
            await funnelApi.delete(uuid);
            loadFunnels();
        } catch (err) {
            setError(err.message || 'Failed to delete funnel');
        }
    };

    const handleAssignCategory = async (funnel, categoryId) => {
        try {
            await funnelApi.update(funnel.uuid, {
                funnel_category_id: categoryId,
            });
            loadFunnels();
        } catch (err) {
            setError(err.message || 'Failed to update category');
        }
    };

    const getStatusBadge = (status) => {
        const styles = {
            draft: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
            published: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            archived: 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        };
        return styles[status] || styles.draft;
    };

    // Build grouped sections: each category (with funnels in it) + uncategorized + empty categories at the bottom.
    const groups = useMemo(() => {
        const byCategory = new Map();
        categories.forEach((c) => byCategory.set(c.id, { category: c, funnels: [] }));
        const uncategorized = [];

        funnels.forEach((f) => {
            const cid = f.funnel_category_id;
            if (cid && byCategory.has(cid)) {
                byCategory.get(cid).funnels.push(f);
            } else {
                uncategorized.push(f);
            }
        });

        const sections = [];
        // Categories with funnels first, in their stored order
        categories.forEach((c) => {
            const entry = byCategory.get(c.id);
            if (entry.funnels.length > 0) sections.push(entry);
        });
        // Uncategorized
        if (uncategorized.length > 0) {
            sections.push({
                category: { id: null, name: 'Uncategorized', color: 'zinc' },
                funnels: uncategorized,
            });
        }
        // Then any empty categories so users can see them
        categories.forEach((c) => {
            const entry = byCategory.get(c.id);
            if (entry.funnels.length === 0) sections.push(entry);
        });

        return sections;
    }, [categories, funnels]);

    const toggleCollapse = (key) => {
        setCollapsed((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    if (loading && funnels.length === 0) {
        return (
            <div className="flex items-center justify-center py-32">
                <div className="h-5 w-5 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-600 dark:border-zinc-600 dark:border-t-zinc-300" />
            </div>
        );
    }

    return (
        <div className="funnel-list">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Sales Funnels</h1>
                    <p className="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">
                        {funnels.length > 0 ? `${funnels.length} funnel${funnels.length !== 1 ? 's' : ''} · ${categories.length} categor${categories.length === 1 ? 'y' : 'ies'}` : 'No funnels created yet'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setShowCategoriesModal(true)}
                        className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        Categories
                    </button>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="inline-flex items-center gap-1.5 rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create Funnel
                    </button>
                </div>
            </div>

            {/* Filters */}
            <div className="mb-6 flex items-center gap-2">
                <div className="relative flex-1">
                    <svg className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        type="text"
                        placeholder="Search funnels..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-md border border-zinc-200 bg-white py-1.5 pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-colors focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                    />
                </div>
                <div className="w-36 shrink-0">
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="w-full rounded-md border border-zinc-200 bg-white py-1.5 pl-3 pr-8 text-sm text-zinc-700 outline-none transition-colors focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:focus:border-zinc-500"
                    >
                        <option value="all">All Status</option>
                        {Object.entries(FUNNEL_STATUSES).map(([key, { label }]) => (
                            <option key={key} value={key}>{label}</option>
                        ))}
                    </select>
                </div>
                <div className="inline-flex shrink-0 overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
                    <button
                        type="button"
                        onClick={() => setView('grouped')}
                        className={`px-2.5 py-1.5 text-xs font-medium transition-colors ${view === 'grouped' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white text-zinc-600 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800'}`}
                        title="Grouped by category"
                    >
                        <svg className="inline h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        onClick={() => setView('flat')}
                        className={`border-l border-zinc-200 px-2.5 py-1.5 text-xs font-medium transition-colors dark:border-zinc-700 ${view === 'flat' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white text-zinc-600 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800'}`}
                        title="Flat grid"
                    >
                        <svg className="inline h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h6v6H4zM14 6h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" />
                        </svg>
                    </button>
                </div>
            </div>

            {/* Error Alert */}
            {error && (
                <div className="mb-6 flex items-center justify-between rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                    <span>{error}</span>
                    <button onClick={() => setError(null)} className="ml-3 text-red-400 transition-colors hover:text-red-600 dark:hover:text-red-300">&times;</button>
                </div>
            )}

            {/* Body */}
            {funnels.length === 0 ? (
                <EmptyState onCreate={() => setShowCreateModal(true)} />
            ) : view === 'flat' ? (
                <FunnelGrid
                    funnels={funnels}
                    categories={categories}
                    getStatusBadge={getStatusBadge}
                    onSelect={onSelectFunnel}
                    onDuplicate={handleDuplicate}
                    onDelete={handleDelete}
                    onAssignCategory={handleAssignCategory}
                />
            ) : (
                <div className="space-y-6">
                    {groups.map((group) => {
                        const key = group.category.id ?? 'uncategorized';
                        const isCollapsed = !!collapsed[key];
                        const colorClasses = getCategoryColorClasses(group.category.color);
                        return (
                            <section key={key}>
                                <button
                                    type="button"
                                    onClick={() => toggleCollapse(key)}
                                    className="mb-3 flex w-full items-center gap-2 text-left"
                                >
                                    <svg className={`h-3 w-3 text-zinc-400 transition-transform ${isCollapsed ? '' : 'rotate-90'}`} fill="none" stroke="currentColor" strokeWidth={2.5} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <span className={`h-2 w-2 rounded-full ${colorClasses.dot}`} />
                                    <h2 className="text-[13px] font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                        {group.category.name}
                                    </h2>
                                    <span className="text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500">
                                        {group.funnels.length}
                                    </span>
                                    <div className="ml-2 h-px flex-1 bg-zinc-100 dark:bg-zinc-800" />
                                </button>
                                {!isCollapsed && (
                                    group.funnels.length === 0 ? (
                                        <p className="rounded-md border border-dashed border-zinc-200 px-3 py-4 text-center text-[12px] text-zinc-400 dark:border-zinc-700 dark:text-zinc-500">
                                            No funnels in this category yet.
                                        </p>
                                    ) : (
                                        <FunnelGrid
                                            funnels={group.funnels}
                                            categories={categories}
                                            getStatusBadge={getStatusBadge}
                                            onSelect={onSelectFunnel}
                                            onDuplicate={handleDuplicate}
                                            onDelete={handleDelete}
                                            onAssignCategory={handleAssignCategory}
                                        />
                                    )
                                )}
                            </section>
                        );
                    })}
                </div>
            )}

            {/* Create Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm dark:bg-black/60">
                    <div className="w-full max-w-lg mx-4 rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <div className="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                            <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-100">Create New Funnel</h2>
                            <p className="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Set up a new sales funnel for your products.</p>
                        </div>

                        <form onSubmit={handleCreate}>
                            <div className="space-y-5 px-6 py-5">
                                <div>
                                    <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Funnel Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={newFunnel.name}
                                        onChange={(e) => setNewFunnel({ ...newFunnel, name: e.target.value })}
                                        required
                                        className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-colors focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                                        placeholder="e.g. Product Launch Funnel"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Description
                                    </label>
                                    <textarea
                                        value={newFunnel.description}
                                        onChange={(e) => setNewFunnel({ ...newFunnel, description: e.target.value })}
                                        rows={3}
                                        className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 outline-none transition-colors focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                                        placeholder="Briefly describe the purpose of this funnel..."
                                    />
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        Category
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <select
                                            value={newFunnel.funnel_category_id || ''}
                                            onChange={(e) => setNewFunnel({ ...newFunnel, funnel_category_id: e.target.value ? Number(e.target.value) : null })}
                                            className="flex-1 rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 outline-none transition-colors focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:focus:border-zinc-500"
                                        >
                                            <option value="">Uncategorized</option>
                                            {categories.map((c) => (
                                                <option key={c.id} value={c.id}>{c.name}</option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            onClick={() => setShowCategoriesModal(true)}
                                            className="rounded-md border border-zinc-200 px-2.5 py-2 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                        >
                                            Manage
                                        </button>
                                    </div>
                                </div>

                                {templates.length > 0 && (
                                    <div>
                                        <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                            Template
                                        </label>
                                        <select
                                            value={newFunnel.template_id || ''}
                                            onChange={(e) => setNewFunnel({ ...newFunnel, template_id: e.target.value || null })}
                                            className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 outline-none transition-colors focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:focus:border-zinc-500"
                                        >
                                            <option value="">Blank Funnel</option>
                                            {templates.map((template) => (
                                                <option key={template.id} value={template.id}>
                                                    {template.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="rounded-md border border-zinc-200 px-3 py-1.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={creating || !newFunnel.name}
                                    className="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800 disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                >
                                    {creating ? 'Creating...' : 'Create Funnel'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {showCategoriesModal && (
                <ManageCategoriesModal
                    categories={categories}
                    onClose={() => {
                        setShowCategoriesModal(false);
                        loadCategories();
                        loadFunnels();
                    }}
                    onChange={(next) => setCategories(next)}
                />
            )}
        </div>
    );
}

function EmptyState({ onCreate }) {
    return (
        <div className="rounded-lg border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
            <svg className="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
            </svg>
            <p className="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-400">No funnels yet</p>
            <p className="mt-1 text-[13px] text-zinc-400 dark:text-zinc-500">Create your first sales funnel to get started.</p>
            <button
                onClick={onCreate}
                className="mt-4 inline-flex items-center gap-1.5 rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                Create Funnel
            </button>
        </div>
    );
}

function FunnelGrid({ funnels, categories, getStatusBadge, onSelect, onDuplicate, onDelete, onAssignCategory }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {funnels.map((funnel) => (
                <FunnelCard
                    key={funnel.uuid}
                    funnel={funnel}
                    categories={categories}
                    getStatusBadge={getStatusBadge}
                    onSelect={onSelect}
                    onDuplicate={onDuplicate}
                    onDelete={onDelete}
                    onAssignCategory={onAssignCategory}
                />
            ))}
        </div>
    );
}

function FunnelCard({ funnel, categories, getStatusBadge, onSelect, onDuplicate, onDelete, onAssignCategory }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const category = funnel.category;
    const colorClasses = getCategoryColorClasses(category?.color);

    return (
        <div className="group rounded-lg border border-zinc-200 bg-white transition-all hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
            <div className="relative h-28 overflow-hidden rounded-t-lg bg-gradient-to-br from-zinc-200 via-zinc-100 to-zinc-200 dark:from-zinc-800 dark:via-zinc-750 dark:to-zinc-800">
                {funnel.thumbnail ? (
                    <img src={funnel.thumbnail} alt={funnel.name} className="h-full w-full object-cover" />
                ) : (
                    <div className="flex h-full items-center justify-center">
                        <svg className="h-8 w-8 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" strokeWidth={1} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </div>
                )}
                <span className={`absolute right-2 top-2 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider ${getStatusBadge(funnel.status)}`}>
                    {FUNNEL_STATUSES[funnel.status]?.label || funnel.status}
                </span>
                {category && (
                    <span className={`absolute left-2 top-2 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium ${colorClasses.badge}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${colorClasses.dot}`} />
                        {category.name}
                    </span>
                )}
            </div>

            <div className="px-4 pb-4 pt-3">
                <h3 className="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{funnel.name}</h3>
                <p className="mt-0.5 line-clamp-1 text-[13px] text-zinc-500 dark:text-zinc-400">
                    {funnel.description || 'No description'}
                </p>

                <div className="mt-3 flex items-center gap-3 text-[12px] tabular-nums text-zinc-400 dark:text-zinc-500">
                    <span className="flex items-center gap-1">
                        <svg className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        {funnel.steps_count || 0} steps
                    </span>
                    <span className="flex items-center gap-1">
                        <svg className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        {funnel.visitors_count || 0} visitors
                    </span>
                </div>

                <div className="mt-3 flex items-center gap-1.5 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <button
                        onClick={() => onSelect && onSelect(funnel)}
                        className="flex-1 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-[13px] font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-750"
                    >
                        Edit
                    </button>
                    <div className="relative">
                        <button
                            onClick={() => setMenuOpen((v) => !v)}
                            className="rounded-md border border-zinc-200 p-1.5 text-zinc-400 transition-colors hover:bg-zinc-50 hover:text-zinc-600 dark:border-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                            title="Set category"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </button>
                        {menuOpen && (
                            <>
                                <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
                                <div className="absolute right-0 top-full z-20 mt-1 max-h-64 w-48 overflow-y-auto rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    <button
                                        onClick={() => { onAssignCategory(funnel, null); setMenuOpen(false); }}
                                        className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800 ${!funnel.funnel_category_id ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400'}`}
                                    >
                                        <span className="h-2 w-2 rounded-full bg-zinc-300 dark:bg-zinc-600" />
                                        Uncategorized
                                    </button>
                                    {categories.length === 0 && (
                                        <div className="px-3 py-2 text-[12px] text-zinc-400">No categories. Use "Categories" to add some.</div>
                                    )}
                                    {categories.map((c) => {
                                        const cls = getCategoryColorClasses(c.color);
                                        return (
                                            <button
                                                key={c.id}
                                                onClick={() => { onAssignCategory(funnel, c.id); setMenuOpen(false); }}
                                                className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800 ${funnel.funnel_category_id === c.id ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400'}`}
                                            >
                                                <span className={`h-2 w-2 rounded-full ${cls.dot}`} />
                                                {c.name}
                                            </button>
                                        );
                                    })}
                                </div>
                            </>
                        )}
                    </div>
                    <button
                        onClick={() => onDuplicate(funnel.uuid)}
                        className="rounded-md border border-zinc-200 p-1.5 text-zinc-400 transition-colors hover:bg-zinc-50 hover:text-zinc-600 dark:border-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        title="Duplicate"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                    <button
                        onClick={() => onDelete(funnel.uuid)}
                        className="rounded-md border border-zinc-200 p-1.5 text-zinc-400 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-500 dark:border-zinc-700 dark:hover:border-red-800 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                        title="Delete"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
}

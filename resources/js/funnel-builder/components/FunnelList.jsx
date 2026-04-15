/**
 * Funnel List Component
 * Displays all funnels with search, filter, and management actions
 */

import React, { useState, useEffect, useCallback } from 'react';
import { funnelApi, templateApi } from '../services/api';
import { FUNNEL_STATUSES } from '../types';

export default function FunnelList({ onSelectFunnel, onCreateFunnel }) {
    const [funnels, setFunnels] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [creating, setCreating] = useState(false);

    // Form state for new funnel
    const [newFunnel, setNewFunnel] = useState({
        name: '',
        description: '',
        template_id: null,
    });

    // Load funnels
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

    // Load templates
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
        loadTemplates();
    }, [loadFunnels, loadTemplates]);

    // Create funnel
    const handleCreate = async (e) => {
        e.preventDefault();
        setCreating(true);

        try {
            const response = await funnelApi.create(newFunnel);
            setShowCreateModal(false);
            setNewFunnel({ name: '', description: '', template_id: null });
            loadFunnels();

            if (onCreateFunnel) {
                onCreateFunnel(response.data);
            }
        } catch (err) {
            setError(err.message || 'Failed to create funnel');
        } finally {
            setCreating(false);
        }
    };

    // Duplicate funnel
    const handleDuplicate = async (uuid) => {
        try {
            await funnelApi.duplicate(uuid);
            loadFunnels();
        } catch (err) {
            setError(err.message || 'Failed to duplicate funnel');
        }
    };

    // Delete funnel
    const handleDelete = async (uuid) => {
        if (!confirm('Are you sure you want to delete this funnel?')) return;

        try {
            await funnelApi.delete(uuid);
            loadFunnels();
        } catch (err) {
            setError(err.message || 'Failed to delete funnel');
        }
    };

    // Update status
    const handleStatusChange = async (uuid, status) => {
        try {
            await funnelApi.update(uuid, { status });
            loadFunnels();
        } catch (err) {
            setError(err.message || 'Failed to update status');
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
                        {funnels.length > 0 ? `${funnels.length} funnel${funnels.length !== 1 ? 's' : ''}` : 'No funnels created yet'}
                    </p>
                </div>
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
            </div>

            {/* Error Alert */}
            {error && (
                <div className="mb-6 flex items-center justify-between rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                    <span>{error}</span>
                    <button onClick={() => setError(null)} className="ml-3 text-red-400 transition-colors hover:text-red-600 dark:hover:text-red-300">&times;</button>
                </div>
            )}

            {/* Funnel Grid */}
            {funnels.length === 0 ? (
                <div className="rounded-lg border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
                    <svg className="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" strokeWidth={1.5} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                    <p className="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-400">No funnels yet</p>
                    <p className="mt-1 text-[13px] text-zinc-400 dark:text-zinc-500">Create your first sales funnel to get started.</p>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="mt-4 inline-flex items-center gap-1.5 rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        Create Funnel
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {funnels.map((funnel) => (
                        <div
                            key={funnel.uuid}
                            className="group rounded-lg border border-zinc-200 bg-white transition-all hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                        >
                            {/* Thumbnail */}
                            <div className="relative h-28 overflow-hidden rounded-t-lg bg-gradient-to-br from-zinc-200 via-zinc-100 to-zinc-200 dark:from-zinc-800 dark:via-zinc-750 dark:to-zinc-800">
                                {funnel.thumbnail ? (
                                    <img
                                        src={funnel.thumbnail}
                                        alt={funnel.name}
                                        className="h-full w-full object-cover"
                                    />
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
                            </div>

                            {/* Content */}
                            <div className="px-4 pb-4 pt-3">
                                <h3 className="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{funnel.name}</h3>
                                <p className="mt-0.5 line-clamp-1 text-[13px] text-zinc-500 dark:text-zinc-400">
                                    {funnel.description || 'No description'}
                                </p>

                                {/* Stats */}
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

                                {/* Actions */}
                                <div className="mt-3 flex items-center gap-1.5 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                    <button
                                        onClick={() => onSelectFunnel && onSelectFunnel(funnel)}
                                        className="flex-1 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-[13px] font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-750"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDuplicate(funnel.uuid)}
                                        className="rounded-md border border-zinc-200 p-1.5 text-zinc-400 transition-colors hover:bg-zinc-50 hover:text-zinc-600 dark:border-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                        title="Duplicate"
                                    >
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => handleDelete(funnel.uuid)}
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
                    ))}
                </div>
            )}

            {/* Create Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm dark:bg-black/60">
                    <div className="w-full max-w-lg mx-4 rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        {/* Modal Header */}
                        <div className="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                            <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-100">Create New Funnel</h2>
                            <p className="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Set up a new sales funnel for your products.</p>
                        </div>

                        {/* Modal Body */}
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

                            {/* Modal Footer */}
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
        </div>
    );
}

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

    const getStatusBadgeClass = (status) => {
        const statusConfig = FUNNEL_STATUSES[status] || {};
        return `px-2 py-1 rounded-full text-xs font-medium bg-${statusConfig.color}-100 text-${statusConfig.color}-800`;
    };

    if (loading && funnels.length === 0) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    return (
        <div className="funnel-list">
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Sales Funnels</h1>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Create Funnel
                </button>
            </div>

            {/* Filters */}
            <div className="flex items-center gap-4 mb-6">
                <div className="flex-1">
                    <input
                        type="text"
                        placeholder="Search funnels..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="all">All Status</option>
                    {Object.entries(FUNNEL_STATUSES).map(([key, { label }]) => (
                        <option key={key} value={key}>{label}</option>
                    ))}
                </select>
            </div>

            {/* Error Alert */}
            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    {error}
                    <button onClick={() => setError(null)} className="float-right font-bold">&times;</button>
                </div>
            )}

            {/* Funnel Grid */}
            {funnels.length === 0 ? (
                <div className="text-center py-12 bg-gray-50 rounded-lg">
                    <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    <h3 className="mt-2 text-sm font-medium text-gray-900">No funnels yet</h3>
                    <p className="mt-1 text-sm text-gray-500">Get started by creating your first sales funnel.</p>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium"
                    >
                        Create Funnel
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {funnels.map((funnel) => (
                        <div
                            key={funnel.uuid}
                            className="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow"
                        >
                            {/* Thumbnail */}
                            <div className="h-32 bg-gradient-to-br from-blue-500 to-purple-600 rounded-t-lg relative">
                                {funnel.thumbnail && (
                                    <img
                                        src={funnel.thumbnail}
                                        alt={funnel.name}
                                        className="w-full h-full object-cover rounded-t-lg"
                                    />
                                )}
                                <span className={`absolute top-2 right-2 ${getStatusBadgeClass(funnel.status)}`}>
                                    {FUNNEL_STATUSES[funnel.status]?.label || funnel.status}
                                </span>
                            </div>

                            {/* Content */}
                            <div className="p-4">
                                <h3 className="font-semibold text-gray-900 truncate">{funnel.name}</h3>
                                <p className="text-sm text-gray-500 mt-1 line-clamp-2">
                                    {funnel.description || 'No description'}
                                </p>

                                {/* Stats */}
                                <div className="flex items-center gap-4 mt-4 text-sm text-gray-500">
                                    <span>{funnel.steps_count || 0} steps</span>
                                    <span>{funnel.visitors_count || 0} visitors</span>
                                </div>

                                {/* Actions */}
                                <div className="flex items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                                    <button
                                        onClick={() => onSelectFunnel && onSelectFunnel(funnel)}
                                        className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm font-medium"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => handleDuplicate(funnel.uuid)}
                                        className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
                                        title="Duplicate"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => handleDelete(funnel.uuid)}
                                        className="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
                                        title="Delete"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                        <div className="p-6">
                            <h2 className="text-xl font-bold text-gray-900 mb-4">Create New Funnel</h2>
                            <form onSubmit={handleCreate}>
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Funnel Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={newFunnel.name}
                                        onChange={(e) => setNewFunnel({ ...newFunnel, name: e.target.value })}
                                        required
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="My Sales Funnel"
                                    />
                                </div>

                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Description
                                    </label>
                                    <textarea
                                        value={newFunnel.description}
                                        onChange={(e) => setNewFunnel({ ...newFunnel, description: e.target.value })}
                                        rows={3}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Describe your funnel..."
                                    />
                                </div>

                                {templates.length > 0 && (
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Start from Template
                                        </label>
                                        <select
                                            value={newFunnel.template_id || ''}
                                            onChange={(e) => setNewFunnel({ ...newFunnel, template_id: e.target.value || null })}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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

                                <div className="flex justify-end gap-3 mt-6">
                                    <button
                                        type="button"
                                        onClick={() => setShowCreateModal(false)}
                                        className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={creating || !newFunnel.name}
                                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                                    >
                                        {creating ? 'Creating...' : 'Create Funnel'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

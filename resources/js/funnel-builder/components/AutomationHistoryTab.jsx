/**
 * Automation History Tab Component
 * Shows execution history and flow visualization for funnel automations
 */

import React, { useState, useEffect, useCallback } from 'react';
import { automationApi } from '../services/api';
import { FUNNEL_TRIGGER_CONFIGS, FUNNEL_ACTION_CONFIGS } from '../types/funnel-automation-types';

export default function AutomationHistoryTab({ funnelUuid, automations = [], showToast }) {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [filters, setFilters] = useState({
        status: '',
        automation_id: '',
        per_page: 25,
    });
    const [selectedLog, setSelectedLog] = useState(null);

    // Load automation logs
    const loadLogs = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const params = {
                page,
                per_page: filters.per_page,
            };
            if (filters.status) params.status = filters.status;
            if (filters.automation_id) params.automation_id = filters.automation_id;

            const response = await automationApi.allLogs(funnelUuid, params);
            setLogs(response.data || []);
            setMeta(response.meta || { current_page: 1, last_page: 1, total: 0 });
        } catch (err) {
            console.error('Failed to load automation logs:', err);
            showToast?.('Failed to load automation history', 'error');
        } finally {
            setLoading(false);
        }
    }, [funnelUuid, filters, showToast]);

    useEffect(() => {
        loadLogs();
    }, [loadLogs]);

    // Filter handlers
    const handleFilterChange = (key, value) => {
        setFilters(prev => ({ ...prev, [key]: value }));
    };

    // Get status badge styles
    const getStatusBadge = (status) => {
        const styles = {
            executed: 'bg-green-100 text-green-800',
            pending: 'bg-yellow-100 text-yellow-800',
            failed: 'bg-red-100 text-red-800',
            skipped: 'bg-gray-100 text-gray-800',
        };
        return styles[status] || 'bg-gray-100 text-gray-800';
    };

    // Format date
    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('en-MY', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    };

    // Get trigger config
    const getTriggerConfig = (triggerType) => {
        return FUNNEL_TRIGGER_CONFIGS[triggerType] || {
            label: triggerType,
            icon: '⚡',
            color: '#6B7280',
        };
    };

    // Get action config
    const getActionConfig = (actionType) => {
        return FUNNEL_ACTION_CONFIGS[actionType] || {
            label: actionType,
            icon: '⚙️',
            color: '#6B7280',
        };
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900">Automation History</h3>
                    <p className="text-sm text-gray-500 mt-1">
                        View execution history and track where automations succeeded or got stuck.
                    </p>
                </div>
                <button
                    onClick={() => loadLogs(1)}
                    className="px-3 py-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg font-medium flex items-center gap-2"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
            </div>

            {/* Filters */}
            <div className="flex flex-wrap gap-4 p-4 bg-gray-50 rounded-lg">
                <div className="flex-1 min-w-[200px]">
                    <label className="block text-xs font-medium text-gray-500 mb-1">Automation</label>
                    <select
                        value={filters.automation_id}
                        onChange={(e) => handleFilterChange('automation_id', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Automations</option>
                        {automations.map((automation) => (
                            <option key={automation.id} value={automation.id}>
                                {automation.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="w-40">
                    <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select
                        value={filters.status}
                        onChange={(e) => handleFilterChange('status', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Statuses</option>
                        <option value="executed">Executed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="skipped">Skipped</option>
                    </select>
                </div>

                <div className="w-32">
                    <label className="block text-xs font-medium text-gray-500 mb-1">Per Page</label>
                    <select
                        value={filters.per_page}
                        onChange={(e) => handleFilterChange('per_page', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            {/* Stats Summary */}
            <div className="grid grid-cols-4 gap-4">
                <div className="bg-white p-4 rounded-lg border border-gray-200">
                    <p className="text-sm text-gray-500">Total Executions</p>
                    <p className="text-2xl font-bold text-gray-900">{meta.total}</p>
                </div>
                <div className="bg-white p-4 rounded-lg border border-gray-200">
                    <p className="text-sm text-gray-500">Successful</p>
                    <p className="text-2xl font-bold text-green-600">
                        {logs.filter(l => l.status === 'executed').length}
                    </p>
                </div>
                <div className="bg-white p-4 rounded-lg border border-gray-200">
                    <p className="text-sm text-gray-500">Failed</p>
                    <p className="text-2xl font-bold text-red-600">
                        {logs.filter(l => l.status === 'failed').length}
                    </p>
                </div>
                <div className="bg-white p-4 rounded-lg border border-gray-200">
                    <p className="text-sm text-gray-500">Pending</p>
                    <p className="text-2xl font-bold text-yellow-600">
                        {logs.filter(l => l.status === 'pending').length}
                    </p>
                </div>
            </div>

            {/* Logs List */}
            {loading ? (
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            ) : logs.length === 0 ? (
                <div className="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                    <svg className="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">No execution history</h3>
                    <p className="text-gray-500 max-w-md mx-auto">
                        When your automations are triggered, the execution history will appear here.
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    {logs.map((log) => (
                        <LogCard
                            key={log.id}
                            log={log}
                            getTriggerConfig={getTriggerConfig}
                            getActionConfig={getActionConfig}
                            getStatusBadge={getStatusBadge}
                            formatDate={formatDate}
                            onClick={() => setSelectedLog(log)}
                            isSelected={selectedLog?.id === log.id}
                        />
                    ))}
                </div>
            )}

            {/* Pagination */}
            {meta.last_page > 1 && (
                <div className="flex items-center justify-between pt-4 border-t border-gray-200">
                    <p className="text-sm text-gray-500">
                        Showing {logs.length} of {meta.total} results
                    </p>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => loadLogs(meta.current_page - 1)}
                            disabled={meta.current_page === 1}
                            className="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        <span className="text-sm text-gray-600">
                            Page {meta.current_page} of {meta.last_page}
                        </span>
                        <button
                            onClick={() => loadLogs(meta.current_page + 1)}
                            disabled={meta.current_page === meta.last_page}
                            className="px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}

            {/* Log Detail Modal */}
            {selectedLog && (
                <LogDetailModal
                    log={selectedLog}
                    getTriggerConfig={getTriggerConfig}
                    getActionConfig={getActionConfig}
                    getStatusBadge={getStatusBadge}
                    formatDate={formatDate}
                    onClose={() => setSelectedLog(null)}
                />
            )}
        </div>
    );
}

// Log Card Component
function LogCard({ log, getTriggerConfig, getActionConfig, getStatusBadge, formatDate, onClick, isSelected }) {
    const automation = log.automation;
    const action = log.action;
    const triggerConfig = automation ? getTriggerConfig(automation.trigger_type) : null;
    const actionConfig = action ? getActionConfig(action.action_type) : null;

    return (
        <div
            onClick={onClick}
            className={`bg-white rounded-lg border p-4 cursor-pointer transition-all hover:shadow-md ${
                isSelected ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200'
            }`}
        >
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-4">
                    {/* Status Icon */}
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                        log.status === 'executed' ? 'bg-green-100' :
                        log.status === 'failed' ? 'bg-red-100' :
                        log.status === 'pending' ? 'bg-yellow-100' :
                        'bg-gray-100'
                    }`}>
                        {log.status === 'executed' && (
                            <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        )}
                        {log.status === 'failed' && (
                            <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        )}
                        {log.status === 'pending' && (
                            <svg className="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        )}
                        {log.status === 'skipped' && (
                            <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        )}
                    </div>

                    {/* Info */}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                            <h4 className="font-medium text-gray-900">
                                {automation?.name || 'Unknown Automation'}
                            </h4>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${getStatusBadge(log.status)}`}>
                                {log.status}
                            </span>
                        </div>

                        {/* Flow Visualization */}
                        <div className="flex items-center gap-2 mt-2 text-sm">
                            {triggerConfig && (
                                <span className="flex items-center gap-1 px-2 py-1 bg-purple-50 text-purple-700 rounded">
                                    <span>{triggerConfig.icon}</span>
                                    <span className="font-medium">{triggerConfig.label}</span>
                                </span>
                            )}
                            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            {actionConfig && (
                                <span className={`flex items-center gap-1 px-2 py-1 rounded ${
                                    log.status === 'executed' ? 'bg-green-50 text-green-700' :
                                    log.status === 'failed' ? 'bg-red-50 text-red-700' :
                                    'bg-gray-50 text-gray-700'
                                }`}>
                                    <span>{actionConfig.icon}</span>
                                    <span className="font-medium">{actionConfig.label}</span>
                                </span>
                            )}
                        </div>

                        {/* Contact/Session Info */}
                        <div className="flex items-center gap-4 mt-2 text-xs text-gray-500">
                            {log.contact_email && (
                                <span className="flex items-center gap-1">
                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {log.contact_email}
                                </span>
                            )}
                            {log.session?.uuid && (
                                <span className="flex items-center gap-1">
                                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Session: {log.session.uuid.substring(0, 8)}...
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Timestamp */}
                <div className="text-right text-xs text-gray-500">
                    <div>{formatDate(log.executed_at || log.created_at)}</div>
                    {log.scheduled_at && log.status === 'pending' && (
                        <div className="mt-1 text-yellow-600">
                            Scheduled: {formatDate(log.scheduled_at)}
                        </div>
                    )}
                </div>
            </div>

            {/* Error Message Preview */}
            {log.status === 'failed' && log.result?.error && (
                <div className="mt-3 p-2 bg-red-50 text-red-700 text-xs rounded border border-red-100">
                    <span className="font-medium">Error:</span> {log.result.error}
                </div>
            )}
        </div>
    );
}

// Log Detail Modal
function LogDetailModal({ log, getTriggerConfig, getActionConfig, getStatusBadge, formatDate, onClose }) {
    const automation = log.automation;
    const action = log.action;
    const triggerConfig = automation ? getTriggerConfig(automation.trigger_type) : null;
    const actionConfig = action ? getActionConfig(action.action_type) : null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-xl font-bold text-gray-900">Execution Details</h2>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Status Banner */}
                    <div className={`p-4 rounded-lg mb-6 ${
                        log.status === 'executed' ? 'bg-green-50 border border-green-200' :
                        log.status === 'failed' ? 'bg-red-50 border border-red-200' :
                        log.status === 'pending' ? 'bg-yellow-50 border border-yellow-200' :
                        'bg-gray-50 border border-gray-200'
                    }`}>
                        <div className="flex items-center gap-3">
                            {log.status === 'executed' && (
                                <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            )}
                            {log.status === 'failed' && (
                                <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            )}
                            {log.status === 'pending' && (
                                <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            )}
                            <div>
                                <p className="font-medium text-gray-900">
                                    {log.status === 'executed' ? 'Automation Executed Successfully' :
                                     log.status === 'failed' ? 'Automation Failed' :
                                     log.status === 'pending' ? 'Automation Pending' :
                                     'Automation Skipped'}
                                </p>
                                <p className="text-sm text-gray-600">
                                    {formatDate(log.executed_at || log.created_at)}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Flow Visualization */}
                    <div className="mb-6">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3">Execution Flow</h3>
                        <div className="flex items-center justify-center">
                            <div className="flex items-center gap-4">
                                {/* Trigger */}
                                <div className="text-center">
                                    <div className="w-16 h-16 rounded-lg flex items-center justify-center text-2xl bg-purple-100 border-2 border-purple-300 mx-auto">
                                        {triggerConfig?.icon || '⚡'}
                                    </div>
                                    <p className="text-xs font-medium text-gray-700 mt-2">{triggerConfig?.label || 'Trigger'}</p>
                                    <p className="text-xs text-green-600 flex items-center justify-center gap-1 mt-1">
                                        <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                        </svg>
                                        Triggered
                                    </p>
                                </div>

                                {/* Arrow */}
                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>

                                {/* Action */}
                                <div className="text-center">
                                    <div className={`w-16 h-16 rounded-lg flex items-center justify-center text-2xl mx-auto border-2 ${
                                        log.status === 'executed' ? 'bg-green-100 border-green-300' :
                                        log.status === 'failed' ? 'bg-red-100 border-red-300' :
                                        log.status === 'pending' ? 'bg-yellow-100 border-yellow-300' :
                                        'bg-gray-100 border-gray-300'
                                    }`}>
                                        {actionConfig?.icon || '⚙️'}
                                    </div>
                                    <p className="text-xs font-medium text-gray-700 mt-2">{actionConfig?.label || 'Action'}</p>
                                    <p className={`text-xs flex items-center justify-center gap-1 mt-1 ${
                                        log.status === 'executed' ? 'text-green-600' :
                                        log.status === 'failed' ? 'text-red-600' :
                                        log.status === 'pending' ? 'text-yellow-600' :
                                        'text-gray-600'
                                    }`}>
                                        {log.status === 'executed' && (
                                            <>
                                                <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                </svg>
                                                Completed
                                            </>
                                        )}
                                        {log.status === 'failed' && (
                                            <>
                                                <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                                </svg>
                                                Failed
                                            </>
                                        )}
                                        {log.status === 'pending' && (
                                            <>
                                                <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.2 3.2.8-1.3-4.5-2.7V7z"/>
                                                </svg>
                                                Scheduled
                                            </>
                                        )}
                                        {log.status === 'skipped' && 'Skipped'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Details Grid */}
                    <div className="grid grid-cols-2 gap-4 mb-6">
                        <div className="p-3 bg-gray-50 rounded-lg">
                            <p className="text-xs font-medium text-gray-500 mb-1">Automation</p>
                            <p className="text-sm text-gray-900">{automation?.name || '-'}</p>
                        </div>
                        <div className="p-3 bg-gray-50 rounded-lg">
                            <p className="text-xs font-medium text-gray-500 mb-1">Action Type</p>
                            <p className="text-sm text-gray-900">{action?.action_type || '-'}</p>
                        </div>
                        <div className="p-3 bg-gray-50 rounded-lg">
                            <p className="text-xs font-medium text-gray-500 mb-1">Contact Email</p>
                            <p className="text-sm text-gray-900">{log.contact_email || '-'}</p>
                        </div>
                        <div className="p-3 bg-gray-50 rounded-lg">
                            <p className="text-xs font-medium text-gray-500 mb-1">Session ID</p>
                            <p className="text-sm text-gray-900 font-mono">
                                {log.session?.uuid || log.session_id || '-'}
                            </p>
                        </div>
                    </div>

                    {/* Result/Error Details */}
                    {log.result && (
                        <div className="mb-6">
                            <h3 className="text-sm font-semibold text-gray-700 mb-2">
                                {log.status === 'failed' ? 'Error Details' : 'Result'}
                            </h3>
                            <div className={`p-4 rounded-lg text-sm font-mono whitespace-pre-wrap ${
                                log.status === 'failed' ? 'bg-red-50 text-red-800' : 'bg-gray-50 text-gray-800'
                            }`}>
                                {JSON.stringify(log.result, null, 2)}
                            </div>
                        </div>
                    )}

                    {/* Action Config */}
                    {action?.action_config && (
                        <div className="mb-6">
                            <h3 className="text-sm font-semibold text-gray-700 mb-2">Action Configuration</h3>
                            <div className="p-4 bg-gray-50 rounded-lg text-sm font-mono whitespace-pre-wrap text-gray-800">
                                {JSON.stringify(action.action_config, null, 2)}
                            </div>
                        </div>
                    )}

                    {/* Close Button */}
                    <div className="flex justify-end pt-4 border-t border-gray-200">
                        <button
                            onClick={onClose}
                            className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

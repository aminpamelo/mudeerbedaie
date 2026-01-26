/**
 * Automations Tab Component
 * Manages funnel automations with React Flow builder
 */

import React, { useState, useEffect, useCallback } from 'react';
import { automationApi } from '../services/api';
import {
    FUNNEL_TRIGGER_TYPES,
    FUNNEL_TRIGGER_CONFIGS,
    AUTOMATION_STATUS,
} from '../types/funnel-automation-types';
import FunnelAutomationBuilder from './FunnelAutomationBuilder';

export default function AutomationsTab({ funnelUuid, steps = [], showToast }) {
    const [automations, setAutomations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showBuilder, setShowBuilder] = useState(false);
    const [editingAutomation, setEditingAutomation] = useState(null);
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Load automations
    const loadAutomations = useCallback(async () => {
        setLoading(true);
        try {
            const response = await automationApi.list(funnelUuid);
            setAutomations(response.data || []);
        } catch (err) {
            console.error('Failed to load automations:', err);
            showToast?.('Failed to load automations', 'error');
        } finally {
            setLoading(false);
        }
    }, [funnelUuid, showToast]);

    useEffect(() => {
        loadAutomations();
    }, [loadAutomations]);

    // Toggle automation active state
    const handleToggle = async (automation) => {
        try {
            await automationApi.toggle(funnelUuid, automation.id);
            loadAutomations();
            showToast?.(`Automation ${automation.is_active ? 'paused' : 'activated'}`, 'success');
        } catch (err) {
            showToast?.('Failed to toggle automation', 'error');
        }
    };

    // Duplicate automation
    const handleDuplicate = async (automation) => {
        try {
            await automationApi.duplicate(funnelUuid, automation.id);
            loadAutomations();
            showToast?.('Automation duplicated', 'success');
        } catch (err) {
            showToast?.('Failed to duplicate automation', 'error');
        }
    };

    // Delete automation
    const handleDelete = async (automation) => {
        if (!confirm(`Delete "${automation.name}"? This cannot be undone.`)) return;

        try {
            await automationApi.delete(funnelUuid, automation.id);
            loadAutomations();
            showToast?.('Automation deleted', 'success');
        } catch (err) {
            showToast?.('Failed to delete automation', 'error');
        }
    };

    // Open builder for editing
    const openBuilder = (automation = null) => {
        setEditingAutomation(automation);
        setShowBuilder(true);
    };

    // Close builder
    const closeBuilder = () => {
        setShowBuilder(false);
        setEditingAutomation(null);
        loadAutomations();
    };

    // Create new automation
    const handleCreate = async (data) => {
        try {
            const response = await automationApi.create(funnelUuid, data);
            setShowCreateModal(false);
            // Open builder with the new automation
            openBuilder(response.data);
            showToast?.('Automation created', 'success');
        } catch (err) {
            showToast?.('Failed to create automation', 'error');
        }
    };

    // Get trigger config for display
    const getTriggerConfig = (triggerType) => {
        return FUNNEL_TRIGGER_CONFIGS[triggerType] || {
            label: triggerType,
            icon: '⚡',
            color: '#6B7280',
        };
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    // Show builder if open
    if (showBuilder) {
        return (
            <FunnelAutomationBuilder
                funnelUuid={funnelUuid}
                automation={editingAutomation}
                steps={steps}
                onClose={closeBuilder}
                showToast={showToast}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900">Funnel Automations</h3>
                    <p className="text-sm text-gray-500 mt-1">
                        Automate email sequences, cart recovery, and more based on visitor actions.
                    </p>
                </div>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium flex items-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Create Automation
                </button>
            </div>

            {/* Automations List */}
            {automations.length === 0 ? (
                <EmptyState onCreateClick={() => setShowCreateModal(true)} />
            ) : (
                <div className="space-y-4">
                    {automations.map((automation) => (
                        <AutomationCard
                            key={automation.id}
                            automation={automation}
                            triggerConfig={getTriggerConfig(automation.trigger_type)}
                            onEdit={() => openBuilder(automation)}
                            onToggle={() => handleToggle(automation)}
                            onDuplicate={() => handleDuplicate(automation)}
                            onDelete={() => handleDelete(automation)}
                        />
                    ))}
                </div>
            )}

            {/* Create Automation Modal */}
            {showCreateModal && (
                <CreateAutomationModal
                    onClose={() => setShowCreateModal(false)}
                    onCreate={handleCreate}
                />
            )}
        </div>
    );
}

// Empty State Component
function EmptyState({ onCreateClick }) {
    return (
        <div className="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
            <svg className="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <h3 className="text-lg font-medium text-gray-900 mb-2">No automations yet</h3>
            <p className="text-gray-500 mb-4 max-w-md mx-auto">
                Create automations to send emails, tag contacts, and more based on what visitors do in your funnel.
            </p>
            <button
                onClick={onCreateClick}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium"
            >
                Create Your First Automation
            </button>
        </div>
    );
}

// Automation Card Component
function AutomationCard({ automation, triggerConfig, onEdit, onToggle, onDuplicate, onDelete }) {
    const [showMenu, setShowMenu] = useState(false);

    return (
        <div className="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-4">
                    {/* Trigger Icon */}
                    <div
                        className="w-12 h-12 rounded-lg flex items-center justify-center text-2xl"
                        style={{ backgroundColor: `${triggerConfig.color}20` }}
                    >
                        {triggerConfig.icon}
                    </div>

                    {/* Info */}
                    <div>
                        <div className="flex items-center gap-2">
                            <h4 className="font-medium text-gray-900">{automation.name}</h4>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                automation.is_active
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-gray-100 text-gray-800'
                            }`}>
                                {automation.is_active ? 'Active' : 'Paused'}
                            </span>
                        </div>
                        <p className="text-sm text-gray-500 mt-1">
                            Triggers on: <span className="font-medium">{triggerConfig.label}</span>
                        </p>
                        <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
                            <span>{automation.actions_count || 0} actions</span>
                            <span>{automation.executions_count || 0} executions</span>
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-2">
                    {/* Toggle Switch */}
                    <button
                        onClick={onToggle}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                            automation.is_active ? 'bg-green-500' : 'bg-gray-300'
                        }`}
                    >
                        <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                automation.is_active ? 'translate-x-6' : 'translate-x-1'
                            }`}
                        />
                    </button>

                    {/* Edit Button */}
                    <button
                        onClick={onEdit}
                        className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
                        title="Edit automation"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>

                    {/* More Menu */}
                    <div className="relative">
                        <button
                            onClick={() => setShowMenu(!showMenu)}
                            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                            </svg>
                        </button>

                        {showMenu && (
                            <>
                                <div className="fixed inset-0 z-10" onClick={() => setShowMenu(false)} />
                                <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-20">
                                    <button
                                        onClick={() => { onDuplicate(); setShowMenu(false); }}
                                        className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        Duplicate
                                    </button>
                                    <button
                                        onClick={() => { onDelete(); setShowMenu(false); }}
                                        className="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

// Create Automation Modal
function CreateAutomationModal({ onClose, onCreate }) {
    const [name, setName] = useState('');
    const [triggerType, setTriggerType] = useState('purchase_completed');
    const [creating, setCreating] = useState(false);

    // Group triggers by category
    const triggersByCategory = {
        purchase: [
            FUNNEL_TRIGGER_TYPES.PURCHASE_COMPLETED,
            FUNNEL_TRIGGER_TYPES.PURCHASE_FAILED,
        ],
        cart: [
            FUNNEL_TRIGGER_TYPES.CART_ABANDONMENT,
            FUNNEL_TRIGGER_TYPES.CART_CREATED,
        ],
        optin: [
            FUNNEL_TRIGGER_TYPES.OPTIN_SUBMITTED,
        ],
        upsell: [
            FUNNEL_TRIGGER_TYPES.UPSELL_ACCEPTED,
            FUNNEL_TRIGGER_TYPES.UPSELL_DECLINED,
            FUNNEL_TRIGGER_TYPES.DOWNSELL_ACCEPTED,
            FUNNEL_TRIGGER_TYPES.DOWNSELL_DECLINED,
        ],
        session: [
            FUNNEL_TRIGGER_TYPES.SESSION_STARTED,
            FUNNEL_TRIGGER_TYPES.PAGE_VIEW,
        ],
        order_bump: [
            FUNNEL_TRIGGER_TYPES.ORDER_BUMP_ACCEPTED,
            FUNNEL_TRIGGER_TYPES.ORDER_BUMP_DECLINED,
        ],
    };

    const categoryLabels = {
        purchase: 'Purchase Events',
        cart: 'Cart Events',
        optin: 'Opt-in Events',
        upsell: 'Upsell/Downsell Events',
        session: 'Session Events',
        order_bump: 'Order Bump Events',
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!name.trim()) return;

        setCreating(true);
        await onCreate({
            name,
            trigger_type: triggerType,
        });
        setCreating(false);
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-xl font-bold text-gray-900">Create Automation</h2>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        {/* Name */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Automation Name
                            </label>
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="e.g., Cart Recovery Email Sequence"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                autoFocus
                            />
                        </div>

                        {/* Trigger Type */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Trigger Event
                            </label>
                            <div className="space-y-4 max-h-64 overflow-y-auto">
                                {Object.entries(triggersByCategory).map(([category, triggers]) => (
                                    <div key={category}>
                                        <p className="text-xs font-semibold text-gray-500 uppercase mb-2">
                                            {categoryLabels[category]}
                                        </p>
                                        <div className="space-y-1">
                                            {triggers.map((trigger) => {
                                                const config = FUNNEL_TRIGGER_CONFIGS[trigger];
                                                if (!config) return null;

                                                return (
                                                    <label
                                                        key={trigger}
                                                        className={`flex items-center gap-3 p-2 rounded-lg cursor-pointer border transition-colors ${
                                                            triggerType === trigger
                                                                ? 'border-blue-500 bg-blue-50'
                                                                : 'border-transparent hover:bg-gray-50'
                                                        }`}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="triggerType"
                                                            value={trigger}
                                                            checked={triggerType === trigger}
                                                            onChange={(e) => setTriggerType(e.target.value)}
                                                            className="sr-only"
                                                        />
                                                        <span className="text-xl">{config.icon}</span>
                                                        <div>
                                                            <p className="text-sm font-medium text-gray-900">{config.label}</p>
                                                            <p className="text-xs text-gray-500">{config.description}</p>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={!name.trim() || creating}
                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                {creating ? 'Creating...' : 'Create & Configure'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

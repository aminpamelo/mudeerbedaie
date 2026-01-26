/**
 * Step List Component
 * Displays and manages funnel steps with drag-and-drop reordering
 */

import React, { useState } from 'react';
import { stepApi } from '../services/api';
import { STEP_TYPES } from '../types';

export default function StepList({ funnelUuid, steps, onRefresh, onEditStep, showToast }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState(null);
    const [draggedStep, setDraggedStep] = useState(null);

    // Form state for new step
    const [newStep, setNewStep] = useState({
        name: '',
        type: 'landing',
        slug: '',
    });

    // Create step
    const handleCreate = async (e) => {
        e.preventDefault();
        setCreating(true);
        setError(null);

        try {
            await stepApi.create(funnelUuid, newStep);
            setShowCreateModal(false);
            setNewStep({ name: '', type: 'landing', slug: '' });
            showToast('Step created successfully');
            onRefresh();
        } catch (err) {
            setError(err.message || 'Failed to create step');
            showToast('Failed to create step', 'error');
        } finally {
            setCreating(false);
        }
    };

    // Delete step
    const handleDelete = async (stepId) => {
        if (!confirm('Are you sure you want to delete this step?')) return;

        try {
            await stepApi.delete(funnelUuid, stepId);
            showToast('Step deleted successfully');
            onRefresh();
        } catch (err) {
            setError(err.message || 'Failed to delete step');
            showToast('Failed to delete step', 'error');
        }
    };

    // Duplicate step
    const handleDuplicate = async (stepId) => {
        try {
            await stepApi.duplicate(funnelUuid, stepId);
            showToast('Step duplicated successfully');
            onRefresh();
        } catch (err) {
            setError(err.message || 'Failed to duplicate step');
            showToast('Failed to duplicate step', 'error');
        }
    };

    // Handle drag start
    const handleDragStart = (e, step) => {
        setDraggedStep(step);
        e.dataTransfer.effectAllowed = 'move';
    };

    // Handle drag over
    const handleDragOver = (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    // Handle drop
    const handleDrop = async (e, targetStep) => {
        e.preventDefault();

        if (!draggedStep || draggedStep.id === targetStep.id) {
            setDraggedStep(null);
            return;
        }

        // Calculate new order
        const newOrder = steps
            .filter((s) => s.id !== draggedStep.id)
            .reduce((acc, s, i) => {
                if (s.id === targetStep.id) {
                    acc.push({ id: draggedStep.id, sort_order: i });
                }
                acc.push({ id: s.id, sort_order: acc.length });
                return acc;
            }, []);

        try {
            await stepApi.reorder(funnelUuid, newOrder);
            showToast('Steps reordered successfully');
            onRefresh();
        } catch (err) {
            setError(err.message || 'Failed to reorder steps');
            showToast('Failed to reorder steps', 'error');
        }

        setDraggedStep(null);
    };

    const getStepIcon = (type) => {
        const icons = {
            landing: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            ),
            sales: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            ),
            checkout: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            ),
            upsell: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            ),
            downsell: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                </svg>
            ),
            thankyou: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
            optin: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            ),
        };
        return icons[type] || icons.landing;
    };

    const getStepColor = (type) => {
        const config = STEP_TYPES[type] || {};
        return config.color || 'gray';
    };

    return (
        <div className="step-list">
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Funnel Steps</h2>
                    <p className="text-sm text-gray-500">Drag steps to reorder. Click to edit.</p>
                </div>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 text-sm"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Add Step
                </button>
            </div>

            {/* Error Alert */}
            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                    {error}
                    <button onClick={() => setError(null)} className="float-right font-bold">&times;</button>
                </div>
            )}

            {/* Steps */}
            {steps.length === 0 ? (
                <div className="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                    <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    <h3 className="mt-2 text-sm font-medium text-gray-900">No steps yet</h3>
                    <p className="mt-1 text-sm text-gray-500">Get started by adding your first funnel step.</p>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium text-sm"
                    >
                        Add First Step
                    </button>
                </div>
            ) : (
                <div className="space-y-3">
                    {steps.map((step, index) => (
                        <div
                            key={step.id}
                            draggable
                            onDragStart={(e) => handleDragStart(e, step)}
                            onDragOver={handleDragOver}
                            onDrop={(e) => handleDrop(e, step)}
                            className={`bg-white rounded-lg border border-gray-200 hover:shadow-md transition-all cursor-move ${
                                draggedStep?.id === step.id ? 'opacity-50' : ''
                            }`}
                        >
                            <div className="flex items-center gap-4 p-4">
                                {/* Drag Handle */}
                                <div className="text-gray-400 cursor-grab">
                                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                                    </svg>
                                </div>

                                {/* Step Number */}
                                <div className="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-medium text-gray-600">
                                    {index + 1}
                                </div>

                                {/* Step Icon */}
                                <div className={`flex-shrink-0 w-10 h-10 bg-${getStepColor(step.type)}-100 rounded-lg flex items-center justify-center text-${getStepColor(step.type)}-600`}>
                                    {getStepIcon(step.type)}
                                </div>

                                {/* Step Info */}
                                <div className="flex-1 min-w-0">
                                    <h3 className="font-medium text-gray-900 truncate">{step.name}</h3>
                                    <p className="text-sm text-gray-500">
                                        {STEP_TYPES[step.type]?.label || step.type} • /{step.slug}
                                    </p>
                                </div>

                                {/* Status */}
                                <div className="flex-shrink-0">
                                    {step.has_published_content ? (
                                        <span className="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                                            Published
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">
                                            Draft
                                        </span>
                                    )}
                                </div>

                                {/* Actions */}
                                <div className="flex items-center gap-1">
                                    <button
                                        onClick={() => onEditStep && onEditStep(step)}
                                        className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg"
                                        title="Edit Content"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => handleDuplicate(step.id)}
                                        className="p-2 text-gray-500 hover:bg-gray-100 rounded-lg"
                                        title="Duplicate"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => handleDelete(step.id)}
                                        className="p-2 text-red-500 hover:bg-red-50 rounded-lg"
                                        title="Delete"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {/* Connector Line */}
                            {index < steps.length - 1 && (
                                <div className="flex justify-center -mb-3 relative z-10">
                                    <div className="w-0.5 h-6 bg-gray-300"></div>
                                    <svg className="absolute bottom-0 w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Create Step Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                        <div className="p-6">
                            <h2 className="text-xl font-bold text-gray-900 mb-4">Add New Step</h2>
                            <form onSubmit={handleCreate}>
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Step Type *
                                        </label>
                                        <div className="grid grid-cols-2 gap-2">
                                            {Object.entries(STEP_TYPES).map(([key, { label, color }]) => (
                                                <button
                                                    key={key}
                                                    type="button"
                                                    onClick={() => setNewStep({ ...newStep, type: key })}
                                                    className={`p-3 rounded-lg border-2 text-left transition-colors ${
                                                        newStep.type === key
                                                            ? `border-${color}-500 bg-${color}-50`
                                                            : 'border-gray-200 hover:border-gray-300'
                                                    }`}
                                                >
                                                    <div className={`w-8 h-8 bg-${color}-100 rounded flex items-center justify-center mb-2 text-${color}-600`}>
                                                        {getStepIcon(key)}
                                                    </div>
                                                    <p className="font-medium text-sm text-gray-900">{label}</p>
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Step Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={newStep.name}
                                            onChange={(e) => setNewStep({ ...newStep, name: e.target.value })}
                                            required
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            placeholder="e.g., Main Landing Page"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            URL Slug
                                        </label>
                                        <input
                                            type="text"
                                            value={newStep.slug}
                                            onChange={(e) => setNewStep({ ...newStep, slug: e.target.value })}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            placeholder="Auto-generated if empty"
                                        />
                                        <p className="text-xs text-gray-500 mt-1">
                                            Leave empty to auto-generate from name
                                        </p>
                                    </div>
                                </div>

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
                                        disabled={creating || !newStep.name}
                                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                                    >
                                        {creating ? 'Creating...' : 'Add Step'}
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

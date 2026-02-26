/**
 * Funnel Editor Component
 * Main Puck editor wrapper for editing funnel step content
 */

import React, { useState, useCallback, useEffect, useRef } from 'react';
import { Puck } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { puckConfig } from '../config/puck-config';
import { stepApi } from '../services/api';

// Viewport presets for responsive preview
const VIEWPORTS = {
    desktop: { width: '100%', label: 'Desktop', icon: '🖥️' },
    tablet: { width: '768px', label: 'Tablet', icon: '📱' },
    mobile: { width: '375px', label: 'Mobile', icon: '📲' },
};

export default function FunnelEditor({ funnelUuid, stepId, stepName, initialContent, onSave }) {
    const [saving, setSaving] = useState(false);
    const [lastSaved, setLastSaved] = useState(null);
    const [error, setError] = useState(null);
    const [viewport, setViewport] = useState('desktop');
    const [pendingData, setPendingData] = useState(null);
    const [isContentLoaded, setIsContentLoaded] = useState(false);
    const saveTimeoutRef = useRef(null);
    const isFirstChangeRef = useRef(true);

    // Mark content as loaded when initialContent is received
    useEffect(() => {
        if (initialContent !== null) {
            setIsContentLoaded(true);
            isFirstChangeRef.current = true; // Reset on new content load
        }
    }, [initialContent]);

    // Debounced auto-save
    const debouncedSave = useCallback((data) => {
        // Don't auto-save if content hasn't loaded yet
        if (!isContentLoaded) {
            return;
        }

        // Skip the first change event (Puck fires onChange on initial render)
        if (isFirstChangeRef.current) {
            isFirstChangeRef.current = false;
            return;
        }

        if (saveTimeoutRef.current) {
            clearTimeout(saveTimeoutRef.current);
        }
        setPendingData(data);
        saveTimeoutRef.current = setTimeout(async () => {
            setSaving(true);
            setError(null);
            try {
                await stepApi.saveContent(funnelUuid, stepId, data);
                setLastSaved(new Date());
                setPendingData(null);
            } catch (err) {
                setError(err.message || 'Auto-save failed');
                console.error('Auto-save error:', err);
            } finally {
                setSaving(false);
            }
        }, 2000); // 2 second debounce
    }, [funnelUuid, stepId, isContentLoaded]);

    // Handle manual save
    const handleSave = useCallback(async (data) => {
        if (saveTimeoutRef.current) {
            clearTimeout(saveTimeoutRef.current);
        }
        setSaving(true);
        setError(null);

        try {
            await stepApi.saveContent(funnelUuid, stepId, data);
            setLastSaved(new Date());
            setPendingData(null);
            if (onSave) {
                onSave(data);
            }
        } catch (err) {
            setError(err.message || 'Failed to save');
            console.error('Save error:', err);
        } finally {
            setSaving(false);
        }
    }, [funnelUuid, stepId, onSave]);

    // Handle publish
    const handlePublish = useCallback(async () => {
        try {
            await stepApi.publishContent(funnelUuid, stepId);
            alert('Content published successfully!');
        } catch (err) {
            setError(err.message || 'Failed to publish');
        }
    }, [funnelUuid, stepId]);

    // Cleanup timeout on unmount
    useEffect(() => {
        return () => {
            if (saveTimeoutRef.current) {
                clearTimeout(saveTimeoutRef.current);
            }
        };
    }, []);

    // Custom iframe wrapper for viewport preview
    const renderPreview = useCallback(({ children }) => {
        const viewportConfig = VIEWPORTS[viewport];
        return (
            <div
                className="h-full flex justify-center bg-gray-100 overflow-y-auto"
                style={{ padding: viewport !== 'desktop' ? '20px' : '0' }}
            >
                <div
                    className="bg-white transition-all duration-300 shadow-lg"
                    style={{
                        width: viewportConfig.width,
                        maxWidth: '100%',
                        minHeight: 'fit-content',
                    }}
                >
                    {children}
                </div>
            </div>
        );
    }, [viewport]);

    return (
        <div className="funnel-editor h-screen flex flex-col">
            {/* Editor Header */}
            <div className="bg-gray-800 text-white px-4 py-2 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <a href={`/funnel-builder/${funnelUuid}`} className="text-gray-400 hover:text-white flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </a>
                    <span className="text-gray-600">|</span>
                    <div className="flex items-center gap-2">
                        <span className="text-gray-400 text-sm">Editing:</span>
                        <span className="font-medium">{stepName || 'Step'}</span>
                    </div>
                </div>

                {/* Viewport Selector */}
                <div className="flex items-center gap-1 bg-gray-700 rounded-lg p-1">
                    {Object.entries(VIEWPORTS).map(([key, config]) => (
                        <button
                            key={key}
                            onClick={() => setViewport(key)}
                            className={`px-3 py-1 rounded text-sm flex items-center gap-1 transition-colors ${
                                viewport === key
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-400 hover:text-white hover:bg-gray-600'
                            }`}
                            title={config.label}
                        >
                            <span>{config.icon}</span>
                            <span className="hidden sm:inline">{config.label}</span>
                        </button>
                    ))}
                </div>

                <div className="flex items-center gap-4">
                    {saving && (
                        <span className="text-yellow-400 text-sm flex items-center gap-1">
                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Saving...
                        </span>
                    )}
                    {pendingData && !saving && (
                        <span className="text-gray-400 text-sm">Unsaved changes</span>
                    )}
                    {lastSaved && !saving && !pendingData && (
                        <span className="text-green-400 text-sm flex items-center gap-1">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Saved {lastSaved.toLocaleTimeString()}
                        </span>
                    )}
                    {error && (
                        <span className="text-red-400 text-sm">{error}</span>
                    )}
                    <button
                        onClick={handlePublish}
                        className="bg-green-600 hover:bg-green-700 px-4 py-1.5 rounded text-sm font-medium flex items-center gap-1"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Publish
                    </button>
                </div>
            </div>

            {/* Puck Editor */}
            <div className="flex-1 overflow-hidden">
                {initialContent === null ? (
                    <div className="h-full flex items-center justify-center bg-gray-100">
                        <div className="text-center">
                            <svg className="w-8 h-8 animate-spin text-blue-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <p className="text-gray-600">Loading editor...</p>
                        </div>
                    </div>
                ) : (
                    <Puck
                        config={puckConfig}
                        data={initialContent}
                        onPublish={handleSave}
                        onChange={debouncedSave}
                        headerPath={`/funnel-builder/${funnelUuid}/steps/${stepId}`}
                        overrides={{
                            preview: renderPreview,
                        }}
                    />
                )}
            </div>
        </div>
    );
}

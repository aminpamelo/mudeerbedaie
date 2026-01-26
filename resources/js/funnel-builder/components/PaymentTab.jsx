/**
 * Payment Tab Component
 * Manages payment method settings for the funnel
 */

import React, { useState, useEffect, useCallback } from 'react';
import { funnelApi } from '../services/api';

export default function PaymentTab({ funnelUuid, funnel, onRefresh, showToast }) {
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [globalConfig, setGlobalConfig] = useState(null);
    const [settings, setSettings] = useState({
        enabled_methods: ['stripe', 'bayarcash_fpx'],
        default_method: 'stripe',
        show_method_selector: true,
        stripe_enabled: true,
        bayarcash_fpx_enabled: true,
        custom_labels: {
            stripe: 'Credit/Debit Card',
            bayarcash_fpx: 'FPX Online Banking',
        },
    });

    // Load global payment configuration
    const loadGlobalConfig = useCallback(async () => {
        try {
            const response = await fetch('/api/v1/funnel-checkout/config');
            const json = await response.json();
            // Extract data from API response
            setGlobalConfig(json.data || json);
        } catch (err) {
            console.error('Failed to load global payment config:', err);
        }
    }, []);

    // Initialize settings from funnel data
    useEffect(() => {
        loadGlobalConfig();

        if (funnel?.payment_settings) {
            setSettings(prev => ({
                ...prev,
                ...funnel.payment_settings,
            }));
        }
    }, [funnel, loadGlobalConfig]);

    // Toggle payment method
    const toggleMethod = (method) => {
        setSettings(prev => {
            const enabledMethods = [...prev.enabled_methods];
            const index = enabledMethods.indexOf(method);

            if (index > -1) {
                // Don't allow disabling all methods
                if (enabledMethods.length <= 1) {
                    showToast?.('At least one payment method must be enabled', 'error');
                    return prev;
                }
                enabledMethods.splice(index, 1);

                // Update default if disabled method was default
                if (prev.default_method === method) {
                    return {
                        ...prev,
                        enabled_methods: enabledMethods,
                        default_method: enabledMethods[0],
                        [`${method.replace(/-/g, '_')}_enabled`]: false,
                    };
                }
            } else {
                enabledMethods.push(method);
            }

            return {
                ...prev,
                enabled_methods: enabledMethods,
                [`${method.replace(/-/g, '_')}_enabled`]: index === -1,
            };
        });
    };

    // Update custom label
    const updateLabel = (method, label) => {
        setSettings(prev => ({
            ...prev,
            custom_labels: {
                ...prev.custom_labels,
                [method]: label,
            },
        }));
    };

    // Save settings
    const handleSave = async () => {
        setSaving(true);
        try {
            await funnelApi.update(funnelUuid, {
                payment_settings: settings,
            });
            showToast?.('Payment settings saved successfully');
            onRefresh?.();
        } catch (err) {
            showToast?.(err.message || 'Failed to save payment settings', 'error');
        } finally {
            setSaving(false);
        }
    };

    // Check if a method is globally configured
    const isMethodGloballyConfigured = (method) => {
        if (!globalConfig) return false;
        if (method === 'stripe') {
            return !!globalConfig.stripe_publishable_key;
        }
        if (method === 'bayarcash_fpx') {
            return globalConfig.bayarcash_enabled === true;
        }
        return false;
    };

    const paymentMethods = [
        {
            id: 'stripe',
            name: 'Stripe (Credit/Debit Card)',
            description: 'Accept credit and debit card payments via Stripe',
            icon: (
                <svg className="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                </svg>
            ),
            color: 'indigo',
        },
        {
            id: 'bayarcash_fpx',
            name: 'Bayarcash FPX',
            description: 'Accept online banking payments via FPX (Malaysia)',
            icon: (
                <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            ),
            color: 'emerald',
        },
    ];

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Payment Methods</h3>
                        <p className="text-sm text-gray-500 mt-1">
                            Configure which payment methods are available for this funnel's checkout
                        </p>
                    </div>
                    <button
                        onClick={handleSave}
                        disabled={saving}
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50 flex items-center gap-2"
                    >
                        {saving ? (
                            <>
                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                                Saving...
                            </>
                        ) : (
                            <>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                                Save Changes
                            </>
                        )}
                    </button>
                </div>
            </div>

            {/* Global Configuration Warning */}
            {globalConfig && (!globalConfig.stripe_publishable_key && !globalConfig.bayarcash_enabled) && (
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div className="flex items-start gap-3">
                        <svg className="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h4 className="font-medium text-yellow-800">Payment methods not configured globally</h4>
                            <p className="text-sm text-yellow-700 mt-1">
                                Please configure payment methods in{' '}
                                <a href="/admin/settings/payment" className="underline font-medium">
                                    Settings → Payment
                                </a>
                                {' '}first to enable payment processing for this funnel.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Payment Methods Grid */}
            <div className="grid gap-4">
                {paymentMethods.map((method) => {
                    const isEnabled = settings.enabled_methods.includes(method.id);
                    const isConfigured = isMethodGloballyConfigured(method.id);
                    const isDefault = settings.default_method === method.id;

                    return (
                        <div
                            key={method.id}
                            className={`bg-white rounded-lg border-2 transition-all ${
                                isEnabled
                                    ? `border-${method.color}-500 shadow-sm`
                                    : 'border-gray-200'
                            } ${!isConfigured ? 'opacity-60' : ''}`}
                        >
                            <div className="p-5">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start gap-4">
                                        {/* Icon */}
                                        <div className={`p-2 rounded-lg ${
                                            isEnabled ? `bg-${method.color}-100 text-${method.color}-600` : 'bg-gray-100 text-gray-400'
                                        }`}>
                                            {method.icon}
                                        </div>

                                        {/* Info */}
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="font-medium text-gray-900">{method.name}</h4>
                                                {isDefault && isEnabled && (
                                                    <span className="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded">
                                                        Default
                                                    </span>
                                                )}
                                                {!isConfigured && (
                                                    <span className="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 rounded">
                                                        Not Configured
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-sm text-gray-500 mt-1">{method.description}</p>

                                            {/* Custom Label */}
                                            {isEnabled && (
                                                <div className="mt-3">
                                                    <label className="block text-xs font-medium text-gray-500 mb-1">
                                                        Display Label (shown to customers)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={settings.custom_labels[method.id] || ''}
                                                        onChange={(e) => updateLabel(method.id, e.target.value)}
                                                        placeholder={method.name}
                                                        className="w-full max-w-xs px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Toggle */}
                                    <div className="flex items-center gap-3">
                                        {isEnabled && settings.enabled_methods.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => setSettings(prev => ({ ...prev, default_method: method.id }))}
                                                disabled={!isConfigured}
                                                className={`text-xs font-medium ${
                                                    isDefault
                                                        ? 'text-blue-600'
                                                        : 'text-gray-500 hover:text-gray-700'
                                                }`}
                                            >
                                                {isDefault ? 'Default' : 'Set as Default'}
                                            </button>
                                        )}

                                        <button
                                            type="button"
                                            onClick={() => toggleMethod(method.id)}
                                            disabled={!isConfigured}
                                            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                                                isEnabled && isConfigured ? 'bg-blue-600' : 'bg-gray-200'
                                            } ${!isConfigured ? 'cursor-not-allowed' : ''}`}
                                            role="switch"
                                            aria-checked={isEnabled}
                                        >
                                            <span
                                                className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                                    isEnabled ? 'translate-x-5' : 'translate-x-0'
                                                }`}
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Display Options */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Display Options</h3>

                <div className="space-y-4">
                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={settings.show_method_selector}
                            onChange={(e) => setSettings(prev => ({
                                ...prev,
                                show_method_selector: e.target.checked
                            }))}
                            className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        />
                        <div>
                            <span className="text-sm font-medium text-gray-900">
                                Show payment method selector
                            </span>
                            <p className="text-xs text-gray-500">
                                When disabled, only the default payment method will be shown
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            {/* Preview */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Preview</h3>
                <p className="text-sm text-gray-500 mb-4">
                    This is how payment options will appear on your checkout page
                </p>

                <div className="bg-gray-50 rounded-lg p-4 max-w-md">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Payment Method
                    </label>

                    {settings.show_method_selector && settings.enabled_methods.length > 1 ? (
                        <div className="space-y-2">
                            {settings.enabled_methods.map((methodId) => {
                                const method = paymentMethods.find(m => m.id === methodId);
                                if (!method) return null;

                                const isSelected = methodId === settings.default_method;
                                const label = settings.custom_labels[methodId] || method.name;

                                return (
                                    <label
                                        key={methodId}
                                        className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                            isSelected
                                                ? 'border-blue-500 bg-blue-50'
                                                : 'border-gray-200 hover:bg-gray-100'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="preview_method"
                                            checked={isSelected}
                                            readOnly
                                            className="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        />
                                        <span className="text-sm font-medium text-gray-900">{label}</span>
                                    </label>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="p-3 rounded-lg border border-gray-200 bg-white">
                            <span className="text-sm font-medium text-gray-900">
                                {settings.custom_labels[settings.default_method] ||
                                 paymentMethods.find(m => m.id === settings.default_method)?.name ||
                                 'Credit/Debit Card'}
                            </span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

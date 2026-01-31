/**
 * Settings Tab Component
 * General funnel settings including name, slug, SEO, and order visibility
 */

import React, { useState, useEffect } from 'react';
import { funnelApi } from '../services/api';

export default function SettingsTab({ funnelUuid, funnel, onRefresh, showToast }) {
    const [form, setForm] = useState({
        name: '',
        slug: '',
        description: '',
        meta_title: '',
        meta_description: '',
        show_orders_in_admin: true,
        disable_shipping: false,
    });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (funnel) {
            setForm({
                name: funnel.name || '',
                slug: funnel.slug || '',
                description: funnel.description || '',
                meta_title: funnel.settings?.meta_title || '',
                meta_description: funnel.settings?.meta_description || '',
                show_orders_in_admin: funnel.show_orders_in_admin ?? true,
                disable_shipping: funnel.disable_shipping ?? false,
            });
        }
    }, [funnel]);

    const handleSave = async () => {
        setSaving(true);
        try {
            await funnelApi.update(funnelUuid, {
                name: form.name,
                slug: form.slug,
                description: form.description,
                show_orders_in_admin: form.show_orders_in_admin,
                disable_shipping: form.disable_shipping,
                settings: {
                    ...funnel.settings,
                    meta_title: form.meta_title,
                    meta_description: form.meta_description,
                },
            });
            await onRefresh();
            showToast('Settings saved successfully', 'success');
        } catch (err) {
            console.error('Failed to save settings:', err);
            showToast('Failed to save settings', 'error');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* General Settings */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-1">General Settings</h3>
                <p className="text-sm text-gray-500 mb-4">Basic funnel information and URL configuration.</p>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Funnel Name
                        </label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            URL Slug
                        </label>
                        <div className="flex items-center">
                            <span className="text-gray-500 text-sm mr-2">/f/</span>
                            <input
                                type="text"
                                value={form.slug}
                                onChange={(e) => setForm({ ...form, slug: e.target.value })}
                                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea
                            value={form.description}
                            onChange={(e) => setForm({ ...form, description: e.target.value })}
                            rows={3}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                </div>
            </div>

            {/* SEO Settings */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-1">SEO Settings</h3>
                <p className="text-sm text-gray-500 mb-4">Search engine optimization for your funnel pages.</p>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Meta Title
                        </label>
                        <input
                            type="text"
                            value={form.meta_title}
                            onChange={(e) => setForm({ ...form, meta_title: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Meta Description
                        </label>
                        <textarea
                            value={form.meta_description}
                            onChange={(e) => setForm({ ...form, meta_description: e.target.value })}
                            rows={2}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                </div>
            </div>

            {/* Order Settings */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-1">Order Settings</h3>
                <p className="text-sm text-gray-500 mb-4">Control how funnel orders are handled in your admin panel.</p>

                <div className="flex items-start justify-between">
                    <div className="flex-1 mr-4">
                        <p className="text-sm font-medium text-gray-900">Show orders in Orders & Package Sales</p>
                        <p className="text-sm text-gray-500 mt-1">
                            When enabled, orders from this funnel will appear in the main Orders & Package Sales page.
                            Turn off for free funnels or funnels that don't require shipping/postage processing.
                        </p>
                        <p className="text-sm text-gray-400 mt-1">
                            Note: Orders will always be tracked in this funnel's Orders tab for conversion analytics regardless of this setting.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setForm({ ...form, show_orders_in_admin: !form.show_orders_in_admin })}
                        className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                            form.show_orders_in_admin ? 'bg-blue-600' : 'bg-gray-200'
                        }`}
                        role="switch"
                        aria-checked={form.show_orders_in_admin}
                    >
                        <span
                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                form.show_orders_in_admin ? 'translate-x-5' : 'translate-x-0'
                            }`}
                        />
                    </button>
                </div>
            </div>

            {/* Checkout Settings */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-1">Checkout Settings</h3>
                <p className="text-sm text-gray-500 mb-4">Configure checkout form behavior for this funnel.</p>

                <div className="flex items-start justify-between">
                    <div className="flex-1 mr-4">
                        <p className="text-sm font-medium text-gray-900">Disable shipping/billing address fields</p>
                        <p className="text-sm text-gray-500 mt-1">
                            When enabled, the shipping and billing address fields will be hidden from the checkout form.
                            Use this for digital products or services that don't require a physical delivery address.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setForm({ ...form, disable_shipping: !form.disable_shipping })}
                        className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                            form.disable_shipping ? 'bg-blue-600' : 'bg-gray-200'
                        }`}
                        role="switch"
                        aria-checked={form.disable_shipping}
                    >
                        <span
                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                form.disable_shipping ? 'translate-x-5' : 'translate-x-0'
                            }`}
                        />
                    </button>
                </div>
            </div>

            {/* Save Button */}
            <div className="flex justify-end">
                <button
                    onClick={handleSave}
                    disabled={saving}
                    className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save Settings'}
                </button>
            </div>
        </div>
    );
}

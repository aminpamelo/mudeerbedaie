/**
 * Settings Tab Component
 * General funnel settings including name, slug, SEO, and order visibility
 */

import React, { useState, useEffect } from 'react';
import { funnelApi, customDomainApi } from '../services/api';

export default function SettingsTab({ funnelUuid, funnel, onRefresh, showToast }) {
    const [form, setForm] = useState({
        name: '',
        slug: '',
        description: '',
        meta_title: '',
        meta_description: '',
        show_orders_in_admin: true,
        disable_shipping: false,
        product_selection_mode: 'multi',
        shipping_settings: {
            enabled: false,
            semenanjung_cost: '',
            sabah_sarawak_cost: '',
        },
    });
    const [saving, setSaving] = useState(false);

    // Custom domain state
    const [customDomain, setCustomDomain] = useState(null);
    const [domainInput, setDomainInput] = useState('');
    const [domainType, setDomainType] = useState('custom');
    const [domainLoading, setDomainLoading] = useState(false);
    const [domainError, setDomainError] = useState('');

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
                product_selection_mode: funnel.settings?.product_selection_mode || 'multi',
                shipping_settings: {
                    enabled: funnel.shipping_settings?.enabled ?? false,
                    semenanjung_cost: funnel.shipping_settings?.semenanjung_cost ?? '',
                    sabah_sarawak_cost: funnel.shipping_settings?.sabah_sarawak_cost ?? '',
                },
            });
        }
    }, [funnel]);

    // Fetch custom domain on mount
    useEffect(() => {
        if (funnelUuid) {
            fetchCustomDomain();
        }
    }, [funnelUuid]);

    const fetchCustomDomain = async () => {
        try {
            const response = await customDomainApi.get(funnelUuid);
            setCustomDomain(response.data || null);
        } catch (err) {
            console.error('Failed to fetch custom domain:', err);
        }
    };

    const handleAddDomain = async () => {
        if (!domainInput.trim()) {
            setDomainError('Please enter a domain name.');
            return;
        }

        setDomainLoading(true);
        setDomainError('');
        try {
            const domain = domainInput.trim();

            const response = await customDomainApi.add(funnelUuid, {
                domain,
                type: domainType,
            });
            setCustomDomain(response.data);
            setDomainInput('');
            showToast('Domain added successfully. Please configure your DNS records.', 'success');
        } catch (err) {
            console.error('Failed to add domain:', err);
            setDomainError(err.message || 'Failed to add domain.');
        } finally {
            setDomainLoading(false);
        }
    };

    const handleCheckDomainStatus = async () => {
        setDomainLoading(true);
        try {
            const response = await customDomainApi.checkStatus(funnelUuid);
            setCustomDomain(response.data);
            if (response.data?.verification_status === 'active') {
                showToast('Domain verified and active!', 'success');
            } else {
                showToast('Domain verification still pending.', 'info');
            }
        } catch (err) {
            console.error('Failed to check domain status:', err);
            showToast('Failed to check domain status.', 'error');
        } finally {
            setDomainLoading(false);
        }
    };

    const handleRemoveDomain = async () => {
        if (!confirm('Are you sure you want to remove this custom domain?')) {
            return;
        }

        setDomainLoading(true);
        try {
            await customDomainApi.remove(funnelUuid);
            setCustomDomain(null);
            setDomainInput('');
            setDomainType('custom');
            showToast('Domain removed successfully.', 'success');
        } catch (err) {
            console.error('Failed to remove domain:', err);
            showToast('Failed to remove domain.', 'error');
        } finally {
            setDomainLoading(false);
        }
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            await funnelApi.update(funnelUuid, {
                name: form.name,
                slug: form.slug,
                description: form.description,
                show_orders_in_admin: form.show_orders_in_admin,
                disable_shipping: form.disable_shipping,
                shipping_settings: {
                    enabled: form.shipping_settings.enabled,
                    semenanjung_cost: parseFloat(form.shipping_settings.semenanjung_cost) || 0,
                    sabah_sarawak_cost: parseFloat(form.shipping_settings.sabah_sarawak_cost) || 0,
                },
                settings: {
                    ...funnel.settings,
                    meta_title: form.meta_title,
                    meta_description: form.meta_description,
                    product_selection_mode: form.product_selection_mode,
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

                <div className="border-t border-gray-200 mt-6 pt-6">
                    <div className="flex items-start justify-between">
                        <div className="flex-1 mr-4">
                            <p className="text-sm font-medium text-gray-900">Product Selection Mode</p>
                            <p className="text-sm text-gray-500 mt-1">
                                Choose whether customers can select multiple products or only one product at checkout.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setForm({ ...form, product_selection_mode: form.product_selection_mode === 'multi' ? 'single' : 'multi' })}
                            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                                form.product_selection_mode === 'single' ? 'bg-blue-600' : 'bg-gray-200'
                            }`}
                            role="switch"
                            aria-checked={form.product_selection_mode === 'single'}
                        >
                            <span
                                className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                    form.product_selection_mode === 'single' ? 'translate-x-5' : 'translate-x-0'
                                }`}
                            />
                        </button>
                    </div>
                    <p className="text-xs text-gray-400 mt-2">
                        {form.product_selection_mode === 'single'
                            ? 'Single product only — customers can only select one product at a time.'
                            : 'Multiple products — customers can select more than one product.'}
                    </p>
                </div>
            </div>

            {/* Shipping Cost Settings */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-1">Shipping Cost Settings</h3>
                <p className="text-sm text-gray-500 mb-4">
                    Set flat-rate shipping fees by delivery zone. Customers will select their zone at checkout.
                </p>

                <div className="flex items-start justify-between">
                    <div className="flex-1 mr-4">
                        <p className="text-sm font-medium text-gray-900">Enable shipping cost</p>
                        <p className="text-sm text-gray-500 mt-1">
                            When enabled, customers must select a delivery zone and the corresponding fee will be added to their order total.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setForm({
                            ...form,
                            shipping_settings: {
                                ...form.shipping_settings,
                                enabled: !form.shipping_settings.enabled,
                            },
                        })}
                        className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                            form.shipping_settings.enabled ? 'bg-blue-600' : 'bg-gray-200'
                        }`}
                        role="switch"
                        aria-checked={form.shipping_settings.enabled}
                    >
                        <span
                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                form.shipping_settings.enabled ? 'translate-x-5' : 'translate-x-0'
                            }`}
                        />
                    </button>
                </div>

                {form.shipping_settings.enabled && (
                    <div className="border-t border-gray-200 mt-6 pt-6 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Semenanjung Malaysia (RM)
                                </label>
                                <div className="flex items-center">
                                    <span className="px-3 py-2 bg-gray-50 border border-r-0 border-gray-300 rounded-l-lg text-gray-500 text-sm">RM</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.shipping_settings.semenanjung_cost}
                                        onChange={(e) => setForm({
                                            ...form,
                                            shipping_settings: {
                                                ...form.shipping_settings,
                                                semenanjung_cost: e.target.value,
                                            },
                                        })}
                                        placeholder="0.00"
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                                <p className="text-xs text-gray-400 mt-1">West Malaysia delivery fee</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Sabah &amp; Sarawak (RM)
                                </label>
                                <div className="flex items-center">
                                    <span className="px-3 py-2 bg-gray-50 border border-r-0 border-gray-300 rounded-l-lg text-gray-500 text-sm">RM</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.shipping_settings.sabah_sarawak_cost}
                                        onChange={(e) => setForm({
                                            ...form,
                                            shipping_settings: {
                                                ...form.shipping_settings,
                                                sabah_sarawak_cost: e.target.value,
                                            },
                                        })}
                                        placeholder="0.00"
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                </div>
                                <p className="text-xs text-gray-400 mt-1">East Malaysia delivery fee</p>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Custom Domain */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-1">Custom Domain</h3>
                <p className="text-sm text-gray-500 mb-4">
                    Connect a custom domain or subdomain to your funnel for a branded checkout experience.
                </p>

                {!customDomain ? (
                    <div className="space-y-4">
                        {/* Domain type selection */}
                        <div className="flex gap-4">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="domainType"
                                    value="custom"
                                    checked={domainType === 'custom'}
                                    onChange={() => {
                                        setDomainType('custom');
                                        setDomainInput('');
                                        setDomainError('');
                                    }}
                                    className="text-blue-600 focus:ring-blue-500"
                                />
                                <span className="text-sm font-medium text-gray-700">Custom Domain</span>
                            </label>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="domainType"
                                    value="subdomain"
                                    checked={domainType === 'subdomain'}
                                    onChange={() => {
                                        setDomainType('subdomain');
                                        setDomainInput('');
                                        setDomainError('');
                                    }}
                                    className="text-blue-600 focus:ring-blue-500"
                                />
                                <span className="text-sm font-medium text-gray-700">Platform Subdomain</span>
                            </label>
                        </div>

                        {/* Domain input */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                {domainType === 'custom' ? 'Domain Name' : 'Subdomain'}
                            </label>
                            {domainType === 'custom' ? (
                                <input
                                    type="text"
                                    value={domainInput}
                                    onChange={(e) => {
                                        setDomainInput(e.target.value);
                                        setDomainError('');
                                    }}
                                    placeholder="checkout.yourdomain.com"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            ) : (
                                <div className="flex items-center">
                                    <input
                                        type="text"
                                        value={domainInput}
                                        onChange={(e) => {
                                            setDomainInput(e.target.value);
                                            setDomainError('');
                                        }}
                                        placeholder="yourname"
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    <span className="px-3 py-2 bg-gray-50 border border-l-0 border-gray-300 rounded-r-lg text-gray-500 text-sm">
                                        .kelasify.com
                                    </span>
                                </div>
                            )}
                            <p className="text-xs text-gray-400 mt-1">
                                {domainType === 'custom'
                                    ? 'Enter the full domain you want to use (e.g., checkout.yourdomain.com)'
                                    : 'Choose a subdomain name for yourname.kelasify.com'}
                            </p>
                        </div>

                        {domainError && (
                            <div className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                                {domainError}
                            </div>
                        )}

                        <button
                            onClick={handleAddDomain}
                            disabled={domainLoading || !domainInput.trim()}
                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                        >
                            {domainLoading ? 'Adding...' : 'Add Domain'}
                        </button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {/* Domain display with status badge */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <p className="text-sm font-medium text-gray-900">
                                    {customDomain.full_domain || customDomain.domain}
                                </p>
                                {customDomain.verification_status === 'pending' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Pending Verification
                                    </span>
                                )}
                                {customDomain.verification_status === 'active' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                )}
                                {customDomain.verification_status === 'failed' && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Failed
                                    </span>
                                )}
                            </div>
                            <span className="text-xs text-gray-400 capitalize">{customDomain.type} domain</span>
                        </div>

                        {/* Pending verification: CNAME instructions */}
                        {customDomain.verification_status === 'pending' && (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p className="text-sm font-medium text-blue-800 mb-2">
                                    DNS Configuration Required
                                </p>
                                <p className="text-sm text-blue-700 mb-3">
                                    Add the following CNAME record to your domain's DNS settings:
                                </p>
                                <div className="bg-white rounded-lg border border-blue-200 p-3 space-y-2">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-medium text-gray-500 w-14">Type:</span>
                                        <code className="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-0.5 rounded">CNAME</code>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-medium text-gray-500 w-14">Name:</span>
                                        <code className="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-0.5 rounded">
                                            {customDomain.full_domain || customDomain.domain}
                                        </code>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-medium text-gray-500 w-14">Target:</span>
                                        <code className="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-0.5 rounded">
                                            {customDomain.cname_target || 'cname.kelasify.com'}
                                        </code>
                                    </div>
                                </div>
                                <p className="text-xs text-blue-600 mt-2">
                                    DNS changes may take up to 48 hours to propagate. Click "Check Status" to verify.
                                </p>
                            </div>
                        )}

                        {/* Failed verification: error details */}
                        {customDomain.verification_status === 'failed' && customDomain.verification_errors && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                <p className="text-sm font-medium text-red-800 mb-1">Verification Failed</p>
                                <p className="text-sm text-red-700">
                                    {Array.isArray(customDomain.verification_errors)
                                        ? customDomain.verification_errors.join(', ')
                                        : customDomain.verification_errors}
                                </p>
                            </div>
                        )}

                        {/* Action buttons */}
                        <div className="flex gap-3">
                            {(customDomain.verification_status === 'pending' || customDomain.verification_status === 'failed') && (
                                <button
                                    onClick={handleCheckDomainStatus}
                                    disabled={domainLoading}
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                                >
                                    {domainLoading ? 'Checking...' : customDomain.verification_status === 'failed' ? 'Retry Verification' : 'Check Status'}
                                </button>
                            )}
                            <button
                                onClick={handleRemoveDomain}
                                disabled={domainLoading}
                                className="px-4 py-2 bg-white hover:bg-red-50 text-red-600 border border-red-300 rounded-lg text-sm font-medium disabled:opacity-50"
                            >
                                Remove Domain
                            </button>
                        </div>
                    </div>
                )}
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

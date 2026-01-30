/**
 * Affiliates Tab Component
 * Manages affiliate settings, commission rules, affiliate list, and commission approvals
 */

import React, { useState, useEffect, useCallback } from 'react';
import { affiliateApi } from '../services/api';

export default function AffiliatesTab({ funnelUuid, showToast }) {
    const [loading, setLoading] = useState(true);
    const [settings, setSettings] = useState(null);
    const [affiliates, setAffiliates] = useState([]);
    const [commissions, setCommissions] = useState([]);
    const [commissionsMeta, setCommissionsMeta] = useState(null);
    const [activeSection, setActiveSection] = useState('affiliates');
    const [savingSettings, setSavingSettings] = useState(false);
    const [commissionFilter, setCommissionFilter] = useState('all');
    const [commissionsPage, setCommissionsPage] = useState(1);

    // Load all data
    const loadData = useCallback(async () => {
        setLoading(true);
        try {
            const [settingsRes, affiliatesRes] = await Promise.all([
                affiliateApi.settings(funnelUuid),
                affiliateApi.affiliates(funnelUuid),
            ]);
            setSettings(settingsRes);
            setAffiliates(affiliatesRes.affiliates || []);
        } catch (err) {
            console.error('Failed to load affiliate data:', err);
            showToast('Failed to load affiliate data', 'error');
        } finally {
            setLoading(false);
        }
    }, [funnelUuid, showToast]);

    // Load commissions
    const loadCommissions = useCallback(async () => {
        try {
            const params = { page: commissionsPage };
            if (commissionFilter !== 'all') {
                params.status = commissionFilter;
            }
            const res = await affiliateApi.commissions(funnelUuid, params);
            setCommissions(res.data || []);
            setCommissionsMeta(res.meta || null);
        } catch (err) {
            console.error('Failed to load commissions:', err);
        }
    }, [funnelUuid, commissionFilter, commissionsPage]);

    useEffect(() => {
        loadData();
    }, [loadData]);

    useEffect(() => {
        if (activeSection === 'commissions') {
            loadCommissions();
        }
    }, [activeSection, loadCommissions]);

    // Toggle affiliate enabled
    const handleToggleAffiliate = async () => {
        setSavingSettings(true);
        try {
            const newEnabled = !settings.affiliate_enabled;
            await affiliateApi.updateSettings(funnelUuid, {
                affiliate_enabled: newEnabled,
                commission_rules: settings.commission_rules || [],
            });
            setSettings({ ...settings, affiliate_enabled: newEnabled });
            showToast(newEnabled ? 'Affiliate program enabled' : 'Affiliate program disabled', 'success');
        } catch (err) {
            showToast('Failed to update settings', 'error');
        } finally {
            setSavingSettings(false);
        }
    };

    // Update commission rule
    const handleUpdateRule = (productId, field, value) => {
        const rules = [...(settings.commission_rules || [])];
        const existingIndex = rules.findIndex(r => r.funnel_product_id === productId);

        if (existingIndex >= 0) {
            rules[existingIndex] = { ...rules[existingIndex], [field]: value };
        } else {
            rules.push({
                funnel_product_id: productId,
                commission_type: field === 'commission_type' ? value : 'percentage',
                commission_value: field === 'commission_value' ? value : 0,
            });
        }

        setSettings({ ...settings, commission_rules: rules });
    };

    // Save commission rules
    const handleSaveRules = async () => {
        setSavingSettings(true);
        try {
            await affiliateApi.updateSettings(funnelUuid, {
                affiliate_enabled: settings.affiliate_enabled,
                commission_rules: settings.commission_rules || [],
            });
            showToast('Commission rules saved', 'success');
        } catch (err) {
            showToast('Failed to save commission rules', 'error');
        } finally {
            setSavingSettings(false);
        }
    };

    // Approve commission
    const handleApprove = async (commissionId) => {
        try {
            await affiliateApi.approveCommission(funnelUuid, commissionId);
            showToast('Commission approved', 'success');
            loadCommissions();
        } catch (err) {
            showToast('Failed to approve commission', 'error');
        }
    };

    // Reject commission
    const handleReject = async (commissionId) => {
        const notes = prompt('Reason for rejection (optional):');
        try {
            await affiliateApi.rejectCommission(funnelUuid, commissionId, notes || '');
            showToast('Commission rejected', 'success');
            loadCommissions();
        } catch (err) {
            showToast('Failed to reject commission', 'error');
        }
    };

    // Bulk approve
    const handleBulkApprove = async () => {
        if (!confirm('Approve all pending commissions?')) return;
        try {
            await affiliateApi.bulkApprove(funnelUuid);
            showToast('All pending commissions approved', 'success');
            loadCommissions();
        } catch (err) {
            showToast('Failed to bulk approve', 'error');
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-48">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Enable/Disable Toggle */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Affiliate Program</h3>
                        <p className="text-sm text-gray-500 mt-1">
                            Allow affiliates to promote this funnel and earn commissions on sales.
                        </p>
                    </div>
                    <button
                        onClick={handleToggleAffiliate}
                        disabled={savingSettings}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                            settings?.affiliate_enabled ? 'bg-blue-600' : 'bg-gray-200'
                        } ${savingSettings ? 'opacity-50' : ''}`}
                    >
                        <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                settings?.affiliate_enabled ? 'translate-x-6' : 'translate-x-1'
                            }`}
                        />
                    </button>
                </div>
            </div>

            {settings?.affiliate_enabled && (
                <>
                    {/* Custom Affiliate URL */}
                    <div className="bg-white rounded-lg border border-gray-200 p-6">
                        <h4 className="text-sm font-semibold text-gray-900 mb-1">Custom Affiliate URL</h4>
                        <p className="text-sm text-gray-500 mb-3">
                            Set a custom URL that affiliates can share. Their ref code will be appended automatically.
                        </p>
                        <div className="flex items-center gap-3">
                            <input
                                type="url"
                                value={settings.affiliate_custom_url || ''}
                                onChange={(e) => setSettings({ ...settings, affiliate_custom_url: e.target.value })}
                                placeholder="https://example.com/landing-page"
                                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <button
                                onClick={async () => {
                                    setSavingSettings(true);
                                    try {
                                        await affiliateApi.updateSettings(funnelUuid, {
                                            affiliate_custom_url: settings.affiliate_custom_url || null,
                                        });
                                        showToast('Custom URL saved', 'success');
                                    } catch (err) {
                                        showToast('Failed to save custom URL', 'error');
                                    } finally {
                                        setSavingSettings(false);
                                    }
                                }}
                                disabled={savingSettings}
                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                            >
                                {savingSettings ? 'Saving...' : 'Save'}
                            </button>
                        </div>
                        {settings.affiliate_custom_url && (
                            <p className="text-xs text-gray-400 mt-2">
                                Affiliates will see: {settings.affiliate_custom_url}?ref=AFXXXXXX
                            </p>
                        )}
                    </div>

                    {/* Section Tabs */}
                    <div className="flex gap-2">
                        {[
                            { key: 'affiliates', label: 'Affiliates' },
                            { key: 'commissions', label: 'Commissions' },
                            { key: 'settings', label: 'Commission Rules' },
                        ].map((section) => (
                            <button
                                key={section.key}
                                onClick={() => setActiveSection(section.key)}
                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    activeSection === section.key
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}
                            >
                                {section.label}
                            </button>
                        ))}
                    </div>

                    {/* Affiliates Table */}
                    {activeSection === 'affiliates' && (
                        <AffiliatesSection
                            affiliates={affiliates}
                            funnelUuid={funnelUuid}
                        />
                    )}

                    {/* Commissions Section */}
                    {activeSection === 'commissions' && (
                        <CommissionsSection
                            commissions={commissions}
                            meta={commissionsMeta}
                            filter={commissionFilter}
                            onFilterChange={(f) => { setCommissionFilter(f); setCommissionsPage(1); }}
                            page={commissionsPage}
                            onPageChange={setCommissionsPage}
                            onApprove={handleApprove}
                            onReject={handleReject}
                            onBulkApprove={handleBulkApprove}
                        />
                    )}

                    {/* Commission Rules */}
                    {activeSection === 'settings' && (
                        <CommissionRulesSection
                            settings={settings}
                            onUpdateRule={handleUpdateRule}
                            onSave={handleSaveRules}
                            saving={savingSettings}
                        />
                    )}
                </>
            )}

            {!settings?.affiliate_enabled && (
                <div className="bg-gray-50 rounded-lg border border-gray-200 p-12 text-center">
                    <svg className="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Enable Affiliate Program</h3>
                    <p className="text-gray-500 mb-6 max-w-md mx-auto">
                        Let affiliates promote your funnel and earn commissions. Affiliates get unique tracking URLs and you control commission rates per product.
                    </p>
                    <button
                        onClick={handleToggleAffiliate}
                        disabled={savingSettings}
                        className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                    >
                        {savingSettings ? 'Enabling...' : 'Enable Affiliate Program'}
                    </button>
                </div>
            )}
        </div>
    );
}

// Affiliates List Section
function AffiliatesSection({ affiliates, funnelUuid }) {
    if (affiliates.length === 0) {
        return (
            <div className="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <p className="text-gray-500">No affiliates have joined this funnel yet.</p>
                <p className="text-sm text-gray-400 mt-2">
                    Affiliates can discover and join this funnel from the affiliate dashboard.
                </p>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200">
                <h4 className="font-semibold text-gray-900">
                    Affiliates ({affiliates.length})
                </h4>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref Code</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Views</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Checkout Fills</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">TY Clicks</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {affiliates.map((affiliate) => (
                            <tr key={affiliate.id} className="hover:bg-gray-50">
                                <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                    {affiliate.name}
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-500">
                                    {affiliate.phone}
                                </td>
                                <td className="px-6 py-4 text-sm">
                                    <span className="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-mono text-xs">
                                        {affiliate.ref_code}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-900 text-right">
                                    {(affiliate.stats?.views || 0).toLocaleString()}
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-900 text-right">
                                    {(affiliate.stats?.checkout_fills || 0).toLocaleString()}
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-900 text-right">
                                    {(affiliate.stats?.thankyou_clicks || 0).toLocaleString()}
                                </td>
                                <td className="px-6 py-4 text-sm text-right">
                                    <div>
                                        <span className="font-medium text-gray-900">
                                            RM {(affiliate.stats?.total_commission || 0).toLocaleString()}
                                        </span>
                                        {(affiliate.stats?.pending_commission || 0) > 0 && (
                                            <span className="block text-xs text-yellow-600">
                                                RM {affiliate.stats.pending_commission.toLocaleString()} pending
                                            </span>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// Commissions Section
function CommissionsSection({ commissions, meta, filter, onFilterChange, page, onPageChange, onApprove, onReject, onBulkApprove }) {
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        paid: 'bg-blue-100 text-blue-800',
    };

    const hasPending = commissions.some(c => c.status === 'pending');

    return (
        <div className="space-y-4">
            {/* Filter & Actions */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    {['all', 'pending', 'approved', 'rejected', 'paid'].map((status) => (
                        <button
                            key={status}
                            onClick={() => onFilterChange(status)}
                            className={`px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                                filter === status
                                    ? 'bg-gray-900 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {status.charAt(0).toUpperCase() + status.slice(1)}
                        </button>
                    ))}
                </div>
                {hasPending && filter === 'pending' && (
                    <button
                        onClick={onBulkApprove}
                        className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium"
                    >
                        Approve All Pending
                    </button>
                )}
            </div>

            {/* Commissions Table */}
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {commissions.length === 0 ? (
                    <div className="p-8 text-center">
                        <p className="text-gray-500">No commissions found.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Affiliate</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Order Amount</th>
                                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rate</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission</th>
                                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {commissions.map((commission) => (
                                    <tr key={commission.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                            {commission.affiliate?.name || '-'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500">
                                            #{commission.funnel_order_id}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-900 text-right">
                                            RM {parseFloat(commission.order_amount).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 text-center">
                                            {commission.commission_type === 'percentage'
                                                ? `${commission.commission_rate}%`
                                                : `RM ${parseFloat(commission.commission_rate).toLocaleString()}`
                                            }
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-gray-900 text-right">
                                            RM {parseFloat(commission.commission_amount).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[commission.status] || 'bg-gray-100 text-gray-800'}`}>
                                                {commission.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500">
                                            {new Date(commission.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            {commission.status === 'pending' && (
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        onClick={() => onApprove(commission.id)}
                                                        className="px-3 py-1 bg-green-100 text-green-700 hover:bg-green-200 rounded text-xs font-medium"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        onClick={() => onReject(commission.id)}
                                                        className="px-3 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded text-xs font-medium"
                                                    >
                                                        Reject
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500">
                        Showing {meta.from}-{meta.to} of {meta.total}
                    </p>
                    <div className="flex gap-2">
                        <button
                            onClick={() => onPageChange(page - 1)}
                            disabled={page <= 1}
                            className="px-3 py-1.5 border border-gray-300 rounded text-sm disabled:opacity-50 hover:bg-gray-50"
                        >
                            Previous
                        </button>
                        <button
                            onClick={() => onPageChange(page + 1)}
                            disabled={page >= meta.last_page}
                            className="px-3 py-1.5 border border-gray-300 rounded text-sm disabled:opacity-50 hover:bg-gray-50"
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

// Commission Rules Section
function CommissionRulesSection({ settings, onUpdateRule, onSave, saving }) {
    const products = settings?.products || [];
    const rules = settings?.commission_rules || [];

    const getRuleForProduct = (productId) => {
        return rules.find(r => r.funnel_product_id === productId) || {
            commission_type: 'percentage',
            commission_value: 0,
        };
    };

    if (products.length === 0) {
        return (
            <div className="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <p className="text-gray-500">No products found in this funnel.</p>
                <p className="text-sm text-gray-400 mt-2">
                    Add products to your funnel steps to configure commission rules.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                    <h4 className="font-semibold text-gray-900">Commission Rules Per Product</h4>
                    <p className="text-sm text-gray-500 mt-1">
                        Set commission type and rate for each product. Affiliates earn this commission when a sale is made through their link.
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commission Type</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commission Value</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Estimated Commission</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {products.map((product) => {
                                const rule = getRuleForProduct(product.id);
                                const estimated = rule.commission_type === 'percentage'
                                    ? (parseFloat(product.price || 0) * parseFloat(rule.commission_value || 0) / 100)
                                    : parseFloat(rule.commission_value || 0);

                                return (
                                    <tr key={product.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                            {product.name}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500">
                                            RM {parseFloat(product.price || 0).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-4">
                                            <select
                                                value={rule.commission_type}
                                                onChange={(e) => onUpdateRule(product.id, 'commission_type', e.target.value)}
                                                className="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                            >
                                                <option value="percentage">Percentage (%)</option>
                                                <option value="fixed">Fixed Amount (RM)</option>
                                            </select>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step={rule.commission_type === 'percentage' ? '1' : '0.01'}
                                                    max={rule.commission_type === 'percentage' ? '100' : undefined}
                                                    value={rule.commission_value || ''}
                                                    onChange={(e) => onUpdateRule(product.id, 'commission_value', e.target.value)}
                                                    placeholder="0"
                                                    className="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                                />
                                                <span className="text-sm text-gray-500">
                                                    {rule.commission_type === 'percentage' ? '%' : 'RM'}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm font-medium text-right">
                                            {estimated > 0 ? (
                                                <span className="text-green-600">
                                                    RM {estimated.toFixed(2)}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400">-</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="flex justify-end">
                <button
                    onClick={onSave}
                    disabled={saving}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save Commission Rules'}
                </button>
            </div>
        </div>
    );
}

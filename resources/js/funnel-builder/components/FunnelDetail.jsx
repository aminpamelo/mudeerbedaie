/**
 * Funnel Detail Component
 * Displays funnel overview with step management and analytics
 */

import React, { useState, useEffect, useCallback } from 'react';
import { funnelApi, stepApi, analyticsApi, automationApi } from '../services/api';
import { STEP_TYPES, FUNNEL_STATUSES } from '../types';
import StepList from './StepList';
import ProductsTab from './ProductsTab';
import AutomationsTab from './AutomationsTab';
import OrdersTab from './OrdersTab';
import PaymentTab from './PaymentTab';

// Toast notification component
function Toast({ message, type = 'success', onClose }) {
    useEffect(() => {
        const timer = setTimeout(() => {
            onClose();
        }, 3000);
        return () => clearTimeout(timer);
    }, [onClose]);

    return (
        <div className={`toast toast-${type}`}>
            <div className="flex items-center gap-2">
                {type === 'success' && (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                )}
                {type === 'error' && (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                )}
                <span>{message}</span>
            </div>
        </div>
    );
}

// Get initial tab from URL query parameter
const getTabFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    const validTabs = ['steps', 'products', 'automations', 'analytics', 'orders', 'payment', 'tracking', 'embed'];
    return validTabs.includes(tab) ? tab : 'steps';
};

// Update URL with tab parameter
const updateUrlWithTab = (tab) => {
    const url = new URL(window.location.href);
    if (tab === 'steps') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', tab);
    }
    window.history.replaceState({}, '', url.toString());
};

export default function FunnelDetail({ funnelUuid, onBack, onEditStep }) {
    const [funnel, setFunnel] = useState(null);
    const [analytics, setAnalytics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeTab, setActiveTab] = useState(getTabFromUrl);
    const [showSettings, setShowSettings] = useState(false);
    const [toast, setToast] = useState(null);

    // Handle tab change with URL update
    const handleTabChange = useCallback((tab) => {
        setActiveTab(tab);
        updateUrlWithTab(tab);
    }, []);

    // Show toast helper - passed to children
    const showToast = useCallback((message, type = 'success') => {
        setToast({ message, type });
    }, []);

    // Load funnel data
    const loadFunnel = useCallback(async () => {
        setLoading(true);
        try {
            const response = await funnelApi.get(funnelUuid);
            setFunnel(response.data);
        } catch (err) {
            setError(err.message || 'Failed to load funnel');
        } finally {
            setLoading(false);
        }
    }, [funnelUuid]);

    // Load analytics
    const loadAnalytics = useCallback(async () => {
        try {
            const response = await analyticsApi.getFunnelStats(funnelUuid, '30d');
            setAnalytics(response.data?.summary || response.data);
        } catch (err) {
            console.error('Failed to load analytics:', err);
        }
    }, [funnelUuid]);

    useEffect(() => {
        loadFunnel();
        loadAnalytics();
    }, [loadFunnel, loadAnalytics]);

    // Update funnel
    const handleUpdate = async (data) => {
        try {
            await funnelApi.update(funnelUuid, data);
            loadFunnel();
        } catch (err) {
            setError(err.message || 'Failed to update funnel');
        }
    };

    // Publish funnel
    const handlePublish = async () => {
        try {
            await funnelApi.publish(funnelUuid);
            loadFunnel();
        } catch (err) {
            setError(err.message || 'Failed to publish funnel');
        }
    };

    // Unpublish funnel
    const handleUnpublish = async () => {
        try {
            await funnelApi.unpublish(funnelUuid);
            loadFunnel();
        } catch (err) {
            setError(err.message || 'Failed to unpublish funnel');
        }
    };

    // Copy funnel URL
    const copyFunnelUrl = () => {
        const url = `${window.location.origin}/f/${funnel.slug}`;
        navigator.clipboard.writeText(url);
        alert('Funnel URL copied to clipboard!');
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (!funnel) {
        return (
            <div className="text-center py-12">
                <p className="text-gray-500">Funnel not found</p>
                <button onClick={onBack} className="mt-4 text-blue-600 hover:text-blue-700">
                    Go back
                </button>
            </div>
        );
    }

    return (
        <div className="funnel-detail">
            {/* Header */}
            <div className="bg-white border-b border-gray-200 px-6 py-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={onBack}
                            className="text-gray-500 hover:text-gray-700"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{funnel.name}</h1>
                            <div className="flex items-center gap-2 mt-1">
                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                    funnel.status === 'published'
                                        ? 'bg-green-100 text-green-800'
                                        : funnel.status === 'draft'
                                        ? 'bg-gray-100 text-gray-800'
                                        : 'bg-yellow-100 text-yellow-800'
                                }`}>
                                    {FUNNEL_STATUSES[funnel.status]?.label || funnel.status}
                                </span>
                                <span className="text-sm text-gray-500">/{funnel.slug}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {funnel.status === 'published' && (
                            <button
                                onClick={copyFunnelUrl}
                                className="px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm font-medium flex items-center gap-2"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Copy URL
                            </button>
                        )}
                        <a
                            href={`/f/${funnel.slug}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm font-medium flex items-center gap-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Preview
                        </a>
                        <button
                            onClick={() => setShowSettings(true)}
                            className="px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm font-medium flex items-center gap-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </button>
                        {funnel.status === 'draft' ? (
                            <button
                                onClick={handlePublish}
                                className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium"
                            >
                                Publish Funnel
                            </button>
                        ) : (
                            <button
                                onClick={handleUnpublish}
                                className="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium"
                            >
                                Unpublish
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Error Alert */}
            {error && (
                <div className="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    {error}
                    <button onClick={() => setError(null)} className="float-right font-bold">&times;</button>
                </div>
            )}

            {/* Analytics Cards */}
            <div className="px-6 py-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <p className="text-sm text-gray-500">Total Visitors</p>
                        <p className="text-2xl font-bold text-gray-900">
                            {analytics?.total_visitors?.toLocaleString() || 0}
                        </p>
                    </div>
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <p className="text-sm text-gray-500">Conversions</p>
                        <p className="text-2xl font-bold text-gray-900">
                            {analytics?.total_conversions?.toLocaleString() || 0}
                        </p>
                    </div>
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <p className="text-sm text-gray-500">Conversion Rate</p>
                        <p className="text-2xl font-bold text-gray-900">
                            {analytics?.conversion_rate || 0}%
                        </p>
                    </div>
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <p className="text-sm text-gray-500">Revenue</p>
                        <p className="text-2xl font-bold text-gray-900">
                            RM {(analytics?.total_revenue || 0).toLocaleString()}
                        </p>
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="px-6">
                <div className="border-b border-gray-200">
                    <nav className="flex gap-8">
                        {['steps', 'products', 'automations', 'analytics', 'orders', 'payment', 'tracking', 'embed'].map((tab) => (
                            <button
                                key={tab}
                                onClick={() => handleTabChange(tab)}
                                className={`py-4 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === tab
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                {tab.charAt(0).toUpperCase() + tab.slice(1)}
                            </button>
                        ))}
                    </nav>
                </div>
            </div>

            {/* Tab Content */}
            <div className="p-6">
                {activeTab === 'steps' && (
                    <StepList
                        funnelUuid={funnelUuid}
                        steps={funnel.steps || []}
                        onRefresh={loadFunnel}
                        onEditStep={onEditStep}
                        showToast={showToast}
                    />
                )}

                {activeTab === 'products' && (
                    <ProductsTab funnelUuid={funnelUuid} showToast={showToast} />
                )}

                {activeTab === 'automations' && (
                    <AutomationsTab funnelUuid={funnelUuid} steps={funnel.steps || []} showToast={showToast} />
                )}

                {activeTab === 'analytics' && (
                    <AnalyticsTab funnelUuid={funnelUuid} analytics={analytics} />
                )}

                {activeTab === 'orders' && (
                    <OrdersTab funnelUuid={funnelUuid} showToast={showToast} />
                )}

                {activeTab === 'payment' && (
                    <PaymentTab funnelUuid={funnelUuid} funnel={funnel} onRefresh={loadFunnel} showToast={showToast} />
                )}

                {activeTab === 'tracking' && (
                    <TrackingTab funnelUuid={funnelUuid} funnel={funnel} onRefresh={loadFunnel} showToast={showToast} />
                )}

                {activeTab === 'embed' && (
                    <EmbedTab funnel={funnel} onRefresh={loadFunnel} showToast={showToast} />
                )}
            </div>

            {/* Settings Modal */}
            {showSettings && (
                <FunnelSettingsModal
                    funnel={funnel}
                    onClose={() => setShowSettings(false)}
                    onSave={handleUpdate}
                />
            )}

            {/* Toast Notification */}
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </div>
    );
}

// Analytics Tab Component
function AnalyticsTab({ funnelUuid, analytics: initialAnalytics }) {
    const [period, setPeriod] = useState('7d');
    const [loading, setLoading] = useState(false);
    const [analytics, setAnalytics] = useState(initialAnalytics);
    const [stepStats, setStepStats] = useState([]);
    const [timeSeriesData, setTimeSeriesData] = useState([]);

    const PERIODS = [
        { value: '24h', label: '24 Hours' },
        { value: '7d', label: '7 Days' },
        { value: '30d', label: '30 Days' },
        { value: '90d', label: '90 Days' },
    ];

    // Load analytics when period changes
    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            try {
                const response = await analyticsApi.getFunnelStats(funnelUuid, period);
                const data = response.data || response;
                setAnalytics(data.summary || data);
                setStepStats(data.steps || []);
                setTimeSeriesData(data.timeseries || []);
            } catch (err) {
                console.error('Failed to load analytics:', err);
            } finally {
                setLoading(false);
            }
        };
        loadData();
    }, [funnelUuid, period]);

    // Calculate max values for chart scaling
    const maxVisitors = Math.max(...(timeSeriesData.map(d => d.visitors) || [1]), 1);
    const maxRevenue = Math.max(...(timeSeriesData.map(d => d.revenue) || [1]), 1);

    // Calculate funnel drop-off
    const maxStepVisitors = Math.max(...(stepStats.map(s => s.visitors) || [1]), 1);

    return (
        <div className="space-y-6">
            {/* Period Selector */}
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">Funnel Analytics</h3>
                <div className="flex items-center gap-2 bg-gray-100 rounded-lg p-1">
                    {PERIODS.map((p) => (
                        <button
                            key={p.value}
                            onClick={() => setPeriod(p.value)}
                            className={`px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                                period === p.value
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>
            </div>

            {loading && (
                <div className="flex items-center justify-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            )}

            {!loading && (
                <>
                    {/* Summary Stats */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <StatCard
                            label="Visitors"
                            value={analytics?.total_visitors?.toLocaleString() || 0}
                            trend={analytics?.visitors_trend}
                            icon={
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            }
                        />
                        <StatCard
                            label="Conversions"
                            value={analytics?.total_conversions?.toLocaleString() || 0}
                            trend={analytics?.conversions_trend}
                            icon={
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            label="Conversion Rate"
                            value={`${analytics?.conversion_rate || 0}%`}
                            trend={analytics?.rate_trend}
                            icon={
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            }
                        />
                        <StatCard
                            label="Revenue"
                            value={`RM ${(analytics?.total_revenue || 0).toLocaleString()}`}
                            trend={analytics?.revenue_trend}
                            icon={
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                    </div>

                    {/* Funnel Visualization */}
                    {stepStats.length > 0 && (
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h4 className="font-semibold text-gray-900 mb-4">Funnel Steps Performance</h4>
                            <div className="space-y-3">
                                {stepStats.map((step, index) => {
                                    const widthPercent = (step.visitors / maxStepVisitors) * 100;
                                    const dropOff = index > 0
                                        ? Math.round((1 - step.visitors / stepStats[index - 1].visitors) * 100)
                                        : 0;

                                    return (
                                        <div key={step.id || index} className="relative">
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="text-sm font-medium text-gray-700">
                                                    {index + 1}. {step.name}
                                                </span>
                                                <div className="flex items-center gap-4 text-sm">
                                                    <span className="text-gray-500">
                                                        {step.visitors?.toLocaleString() || 0} visitors
                                                    </span>
                                                    {dropOff > 0 && (
                                                        <span className="text-red-500 text-xs">
                                                            -{dropOff}% drop-off
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="h-8 bg-gray-100 rounded-lg overflow-hidden">
                                                <div
                                                    className="h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-lg transition-all duration-500 flex items-center justify-end pr-2"
                                                    style={{ width: `${Math.max(widthPercent, 5)}%` }}
                                                >
                                                    {step.conversions > 0 && (
                                                        <span className="text-xs text-white font-medium">
                                                            {step.conversions} conv.
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Time Series Charts */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Visitors Chart */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h4 className="font-semibold text-gray-900 mb-4">Visitors Over Time</h4>
                            {timeSeriesData.length > 0 ? (
                                <SimpleBarChart
                                    data={timeSeriesData}
                                    dataKey="visitors"
                                    color="#3b82f6"
                                    maxValue={maxVisitors}
                                />
                            ) : (
                                <div className="h-48 flex items-center justify-center text-gray-400">
                                    No data available
                                </div>
                            )}
                        </div>

                        {/* Revenue Chart */}
                        <div className="bg-white rounded-lg border border-gray-200 p-6">
                            <h4 className="font-semibold text-gray-900 mb-4">Revenue Over Time</h4>
                            {timeSeriesData.length > 0 ? (
                                <SimpleBarChart
                                    data={timeSeriesData}
                                    dataKey="revenue"
                                    color="#10b981"
                                    maxValue={maxRevenue}
                                    formatValue={(v) => `RM ${v.toLocaleString()}`}
                                />
                            ) : (
                                <div className="h-48 flex items-center justify-center text-gray-400">
                                    No data available
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Step Breakdown Table */}
                    {stepStats.length > 0 && (
                        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h4 className="font-semibold text-gray-900">Step-by-Step Breakdown</h4>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Step</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Visitors</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pageviews</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Conversions</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Conv. Rate</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {stepStats.map((step, index) => (
                                            <tr key={step.id || index} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                                    {step.name}
                                                    <span className="ml-2 text-xs text-gray-400 capitalize">{step.type}</span>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 text-right">
                                                    {step.visitors?.toLocaleString() || 0}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 text-right">
                                                    {step.pageviews?.toLocaleString() || 0}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 text-right">
                                                    {step.conversions?.toLocaleString() || 0}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-right">
                                                    <span className={`font-medium ${
                                                        (step.conversion_rate || 0) >= 50 ? 'text-green-600' :
                                                        (step.conversion_rate || 0) >= 20 ? 'text-yellow-600' : 'text-red-600'
                                                    }`}>
                                                        {step.conversion_rate || 0}%
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-900 text-right font-medium">
                                                    RM {(step.revenue || 0).toLocaleString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-gray-50">
                                        <tr>
                                            <td className="px-6 py-4 text-sm font-semibold text-gray-900">Total</td>
                                            <td className="px-6 py-4 text-sm font-semibold text-gray-900 text-right">
                                                {stepStats.reduce((sum, s) => sum + (s.visitors || 0), 0).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm font-semibold text-gray-900 text-right">
                                                {stepStats.reduce((sum, s) => sum + (s.pageviews || 0), 0).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm font-semibold text-gray-900 text-right">
                                                {stepStats.reduce((sum, s) => sum + (s.conversions || 0), 0).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 text-sm font-semibold text-gray-900 text-right">
                                                {analytics?.conversion_rate || 0}%
                                            </td>
                                            <td className="px-6 py-4 text-sm font-semibold text-gray-900 text-right">
                                                RM {stepStats.reduce((sum, s) => sum + (s.revenue || 0), 0).toLocaleString()}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

// Stat Card Component
function StatCard({ label, value, trend, icon }) {
    return (
        <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
                <div className="text-gray-400">{icon}</div>
                {trend !== undefined && trend !== null && (
                    <span className={`text-xs font-medium ${trend >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {trend >= 0 ? '+' : ''}{trend}%
                    </span>
                )}
            </div>
            <p className="text-2xl font-bold text-gray-900 mt-2">{value}</p>
            <p className="text-sm text-gray-500">{label}</p>
        </div>
    );
}

// Simple Bar Chart Component (SVG-based)
function SimpleBarChart({ data, dataKey, color, maxValue, formatValue = (v) => v.toLocaleString() }) {
    const chartHeight = 160;
    const barWidth = Math.max(20, Math.min(40, 600 / data.length - 4));

    return (
        <div className="relative">
            <svg className="w-full" height={chartHeight + 30} viewBox={`0 0 ${data.length * (barWidth + 4)} ${chartHeight + 30}`}>
                {data.map((item, index) => {
                    const value = item[dataKey] || 0;
                    const barHeight = maxValue > 0 ? (value / maxValue) * chartHeight : 0;
                    const x = index * (barWidth + 4);
                    const y = chartHeight - barHeight;

                    return (
                        <g key={index}>
                            {/* Bar */}
                            <rect
                                x={x}
                                y={y}
                                width={barWidth}
                                height={barHeight}
                                fill={color}
                                rx={4}
                                className="opacity-80 hover:opacity-100 transition-opacity cursor-pointer"
                            >
                                <title>{`${item.date || item.label}: ${formatValue(value)}`}</title>
                            </rect>
                            {/* Label */}
                            <text
                                x={x + barWidth / 2}
                                y={chartHeight + 15}
                                textAnchor="middle"
                                className="text-xs fill-gray-400"
                                fontSize="10"
                            >
                                {item.label || (item.date ? item.date.slice(-2) : index + 1)}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

// Embed Tab Component
function EmbedTab({ funnel, onRefresh, showToast }) {
    const [embedEnabled, setEmbedEnabled] = useState(funnel?.embed_enabled || false);
    const [embedKey, setEmbedKey] = useState(funnel?.embed_key || '');
    const [embedSettings, setEmbedSettings] = useState(funnel?.embed_settings || {
        theme: 'light',
        primary_color: '#3b82f6',
        border_radius: 'xl',
        show_powered_by: true,
        allowed_domains: [],
    });
    const [loading, setLoading] = useState(false);
    const [copiedField, setCopiedField] = useState(null);

    // Sync state with funnel prop when it changes
    useEffect(() => {
        setEmbedEnabled(funnel?.embed_enabled || false);
        setEmbedKey(funnel?.embed_key || '');
        setEmbedSettings(funnel?.embed_settings || {
            theme: 'light',
            primary_color: '#3b82f6',
            border_radius: 'xl',
            show_powered_by: true,
            allowed_domains: [],
        });
    }, [funnel?.embed_enabled, funnel?.embed_key, funnel?.embed_settings]);

    const baseUrl = window.location.origin;
    const embedUrl = `${baseUrl}/embed/${embedKey}`;
    const scriptUrl = `${baseUrl}/embed/funnel.js`;

    // Generate iframe code
    const iframeCode = `<!-- Funnel Checkout Embed -->
<iframe
    src="${embedUrl}"
    width="100%"
    height="800"
    frameborder="0"
    allow="payment"
    style="border: none; max-width: 500px; margin: 0 auto; display: block;"
></iframe>`;

    // Generate script code
    const scriptCode = `<!-- Funnel Checkout Widget -->
<div id="funnel-checkout-${embedKey}"></div>
<script src="${scriptUrl}" data-funnel-key="${embedKey}"></script>`;

    // Toggle embed
    const handleToggleEmbed = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/funnels/${funnel.id}/embed/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ enabled: !embedEnabled }),
            });
            const data = await response.json();
            if (data.success) {
                setEmbedEnabled(data.embed_enabled);
                setEmbedKey(data.embed_key);
                showToast(data.embed_enabled ? 'Embed enabled' : 'Embed disabled', 'success');
                onRefresh();
            }
        } catch (err) {
            showToast('Failed to toggle embed', 'error');
        } finally {
            setLoading(false);
        }
    };

    // Regenerate key
    const handleRegenerateKey = async () => {
        if (!confirm('Are you sure? This will invalidate all existing embed codes.')) return;
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/funnels/${funnel.id}/embed/regenerate-key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success) {
                setEmbedKey(data.embed_key);
                showToast('Embed key regenerated', 'success');
                onRefresh();
            }
        } catch (err) {
            showToast('Failed to regenerate key', 'error');
        } finally {
            setLoading(false);
        }
    };

    // Update settings
    const handleSaveSettings = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/funnels/${funnel.id}/embed/settings`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(embedSettings),
            });
            const data = await response.json();
            if (data.success) {
                showToast('Settings saved', 'success');
                onRefresh();
            }
        } catch (err) {
            showToast('Failed to save settings', 'error');
        } finally {
            setLoading(false);
        }
    };

    // Copy to clipboard
    const copyToClipboard = (text, field) => {
        navigator.clipboard.writeText(text);
        setCopiedField(field);
        showToast('Copied to clipboard', 'success');
        setTimeout(() => setCopiedField(null), 2000);
    };

    // Get CSRF token
    const getCsrfToken = () => {
        return document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1]
            ?.replace(/%3D/g, '=') || '';
    };

    return (
        <div className="space-y-6">
            {/* Enable/Disable Toggle */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Embeddable Checkout</h3>
                        <p className="text-sm text-gray-500 mt-1">
                            Embed your checkout form on any website using an iframe or JavaScript widget.
                        </p>
                    </div>
                    <button
                        onClick={handleToggleEmbed}
                        disabled={loading}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                            embedEnabled ? 'bg-blue-600' : 'bg-gray-200'
                        } ${loading ? 'opacity-50' : ''}`}
                    >
                        <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                embedEnabled ? 'translate-x-6' : 'translate-x-1'
                            }`}
                        />
                    </button>
                </div>
            </div>

            {embedEnabled && embedKey && (
                <>
                    {/* Embed Codes */}
                    <div className="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
                        <h3 className="text-lg font-semibold text-gray-900">Embed Codes</h3>

                        {/* Embed URL */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Direct Embed URL
                            </label>
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    value={embedUrl}
                                    readOnly
                                    className="flex-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-600"
                                />
                                <button
                                    onClick={() => copyToClipboard(embedUrl, 'url')}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                >
                                    {copiedField === 'url' ? 'Copied!' : 'Copy'}
                                </button>
                            </div>
                        </div>

                        {/* iframe Code */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                iframe Embed Code
                                <span className="ml-2 text-xs font-normal text-gray-500">(Recommended)</span>
                            </label>
                            <div className="relative">
                                <pre className="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto">
                                    <code>{iframeCode}</code>
                                </pre>
                                <button
                                    onClick={() => copyToClipboard(iframeCode, 'iframe')}
                                    className="absolute top-2 right-2 px-3 py-1 text-xs font-medium text-gray-300 bg-gray-800 rounded hover:bg-gray-700"
                                >
                                    {copiedField === 'iframe' ? 'Copied!' : 'Copy'}
                                </button>
                            </div>
                        </div>

                        {/* JavaScript Widget Code */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                JavaScript Widget Code
                                <span className="ml-2 text-xs font-normal text-gray-500">(Auto-resize)</span>
                            </label>
                            <div className="relative">
                                <pre className="bg-gray-900 text-gray-100 rounded-lg p-4 text-sm overflow-x-auto">
                                    <code>{scriptCode}</code>
                                </pre>
                                <button
                                    onClick={() => copyToClipboard(scriptCode, 'script')}
                                    className="absolute top-2 right-2 px-3 py-1 text-xs font-medium text-gray-300 bg-gray-800 rounded hover:bg-gray-700"
                                >
                                    {copiedField === 'script' ? 'Copied!' : 'Copy'}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Customization */}
                    <div className="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
                        <h3 className="text-lg font-semibold text-gray-900">Customization</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Theme */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Theme
                                </label>
                                <select
                                    value={embedSettings.theme || 'light'}
                                    onChange={(e) => setEmbedSettings({ ...embedSettings, theme: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="auto">Auto (System Preference)</option>
                                </select>
                            </div>

                            {/* Primary Color */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Primary Color
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="color"
                                        value={embedSettings.primary_color || '#3b82f6'}
                                        onChange={(e) => setEmbedSettings({ ...embedSettings, primary_color: e.target.value })}
                                        className="h-10 w-14 rounded border border-gray-300 cursor-pointer"
                                    />
                                    <input
                                        type="text"
                                        value={embedSettings.primary_color || '#3b82f6'}
                                        onChange={(e) => setEmbedSettings({ ...embedSettings, primary_color: e.target.value })}
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>

                            {/* Border Radius */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Border Radius
                                </label>
                                <select
                                    value={embedSettings.border_radius || 'xl'}
                                    onChange={(e) => setEmbedSettings({ ...embedSettings, border_radius: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="none">None</option>
                                    <option value="sm">Small</option>
                                    <option value="md">Medium</option>
                                    <option value="lg">Large</option>
                                    <option value="xl">Extra Large</option>
                                    <option value="2xl">2XL</option>
                                </select>
                            </div>

                            {/* Show Powered By */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Show "Powered by" Link
                                </label>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={embedSettings.show_powered_by !== false}
                                        onChange={(e) => setEmbedSettings({ ...embedSettings, show_powered_by: e.target.checked })}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-600">Display powered by attribution</span>
                                </label>
                            </div>
                        </div>

                        <button
                            onClick={handleSaveSettings}
                            disabled={loading}
                            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                        >
                            {loading ? 'Saving...' : 'Save Customization'}
                        </button>
                    </div>

                    {/* Security */}
                    <div className="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900">Security</h3>

                        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p className="font-medium text-gray-900">Embed Key</p>
                                <p className="text-sm text-gray-500 font-mono mt-1">{embedKey}</p>
                            </div>
                            <button
                                onClick={handleRegenerateKey}
                                disabled={loading}
                                className="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                            >
                                Regenerate Key
                            </button>
                        </div>

                        <p className="text-sm text-gray-500">
                            Regenerating the key will invalidate all existing embed codes. You'll need to update the embed code on all websites where it's used.
                        </p>
                    </div>

                    {/* Preview */}
                    <div className="bg-white rounded-lg border border-gray-200 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-semibold text-gray-900">Preview</h3>
                            <a
                                href={embedUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-blue-600 hover:text-blue-700"
                            >
                                Open in new tab
                            </a>
                        </div>
                        <div className="border border-gray-200 rounded-lg overflow-hidden bg-gray-100" style={{ height: '600px' }}>
                            <iframe
                                src={embedUrl}
                                className="w-full h-full"
                                style={{ maxWidth: '500px', margin: '0 auto', display: 'block' }}
                            />
                        </div>
                    </div>
                </>
            )}

            {!embedEnabled && (
                <div className="bg-gray-50 rounded-lg border border-gray-200 p-12 text-center">
                    <svg className="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Enable Embeddable Checkout</h3>
                    <p className="text-gray-500 mb-6 max-w-md mx-auto">
                        Allow your checkout form to be embedded on any website. Perfect for landing pages, blogs, or partner sites.
                    </p>
                    <button
                        onClick={handleToggleEmbed}
                        disabled={loading}
                        className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                    >
                        {loading ? 'Enabling...' : 'Enable Embed'}
                    </button>
                </div>
            )}
        </div>
    );
}

// Tracking Tab Component - Facebook Pixel & Conversions API Settings
function TrackingTab({ funnelUuid, funnel, onRefresh, showToast }) {
    const [loading, setLoading] = useState(false);
    const [testing, setTesting] = useState(false);
    const [settings, setSettings] = useState({
        enabled: funnel?.settings?.pixel_settings?.facebook?.enabled || false,
        pixel_id: funnel?.settings?.pixel_settings?.facebook?.pixel_id || '',
        access_token: funnel?.settings?.pixel_settings?.facebook?.access_token || '',
        test_event_code: funnel?.settings?.pixel_settings?.facebook?.test_event_code || '',
        events: funnel?.settings?.pixel_settings?.facebook?.events || {
            page_view: true,
            view_content: true,
            add_to_cart: true,
            initiate_checkout: true,
            purchase: true,
            lead: true,
        },
    });

    // Sync state with funnel prop when it changes
    useEffect(() => {
        if (funnel?.settings?.pixel_settings?.facebook) {
            setSettings({
                enabled: funnel.settings.pixel_settings.facebook.enabled || false,
                pixel_id: funnel.settings.pixel_settings.facebook.pixel_id || '',
                access_token: funnel.settings.pixel_settings.facebook.access_token || '',
                test_event_code: funnel.settings.pixel_settings.facebook.test_event_code || '',
                events: funnel.settings.pixel_settings.facebook.events || {
                    page_view: true,
                    view_content: true,
                    add_to_cart: true,
                    initiate_checkout: true,
                    purchase: true,
                    lead: true,
                },
            });
        }
    }, [funnel]);

    // Get CSRF token
    const getCsrfToken = () => {
        return document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1]
            ?.replace(/%3D/g, '=') || '';
    };

    // Save settings
    const handleSave = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/v1/funnels/${funnelUuid}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    settings: {
                        ...funnel.settings,
                        pixel_settings: {
                            ...funnel.settings?.pixel_settings,
                            facebook: settings,
                        },
                    },
                }),
            });

            if (response.ok) {
                showToast('Tracking settings saved', 'success');
                onRefresh();
            } else {
                const error = await response.json();
                showToast(error.message || 'Failed to save settings', 'error');
            }
        } catch (err) {
            showToast('Failed to save settings', 'error');
        } finally {
            setLoading(false);
        }
    };

    // Test connection
    const handleTestConnection = async () => {
        if (!settings.pixel_id || !settings.access_token) {
            showToast('Please enter Pixel ID and Access Token first', 'error');
            return;
        }

        setTesting(true);
        try {
            // First save the current settings
            await handleSave();

            // Then test the connection
            const response = await fetch(`/api/v1/funnels/${funnelUuid}/pixel/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            });

            const data = await response.json();
            if (data.success) {
                showToast(data.message || 'Connection successful!', 'success');
            } else {
                showToast(data.message || 'Connection failed', 'error');
            }
        } catch (err) {
            showToast('Failed to test connection', 'error');
        } finally {
            setTesting(false);
        }
    };

    // Toggle event
    const toggleEvent = (eventKey) => {
        setSettings({
            ...settings,
            events: {
                ...settings.events,
                [eventKey]: !settings.events[eventKey],
            },
        });
    };

    const eventDescriptions = {
        page_view: { label: 'PageView', desc: 'Track when visitors view any funnel page' },
        view_content: { label: 'ViewContent', desc: 'Track when visitors view product/landing pages' },
        add_to_cart: { label: 'AddToCart', desc: 'Track when visitors add products to cart' },
        initiate_checkout: { label: 'InitiateCheckout', desc: 'Track when visitors start the checkout process' },
        purchase: { label: 'Purchase', desc: 'Track completed purchases (required for ROAS tracking)' },
        lead: { label: 'Lead', desc: 'Track opt-in form submissions' },
    };

    return (
        <div className="space-y-6">
            {/* Facebook Pixel Section */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg className="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Facebook Pixel</h3>
                            <p className="text-sm text-gray-500">Track conversions and build audiences</p>
                        </div>
                    </div>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <span className="text-sm text-gray-600">
                            {settings.enabled ? 'Enabled' : 'Disabled'}
                        </span>
                        <button
                            onClick={() => setSettings({ ...settings, enabled: !settings.enabled })}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                settings.enabled ? 'bg-blue-600' : 'bg-gray-200'
                            }`}
                        >
                            <span
                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    settings.enabled ? 'translate-x-6' : 'translate-x-1'
                                }`}
                            />
                        </button>
                    </label>
                </div>

                {settings.enabled && (
                    <div className="space-y-6">
                        {/* Pixel ID */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Pixel ID <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={settings.pixel_id}
                                onChange={(e) => setSettings({ ...settings, pixel_id: e.target.value })}
                                placeholder="Enter your 15-16 digit Pixel ID"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                Find this in Facebook Events Manager → Data Sources → Your Pixel → Settings
                            </p>
                        </div>

                        {/* Conversions API Access Token */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Conversions API Access Token
                                <span className="ml-2 text-xs font-normal text-green-600">(Recommended)</span>
                            </label>
                            <input
                                type="password"
                                value={settings.access_token}
                                onChange={(e) => setSettings({ ...settings, access_token: e.target.value })}
                                placeholder="Enter your access token for server-side tracking"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                Enables server-side tracking for 95%+ accuracy. Get this from Events Manager → Settings → Generate access token
                            </p>
                        </div>

                        {/* Test Event Code */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Test Event Code
                                <span className="ml-2 text-xs font-normal text-gray-500">(Optional)</span>
                            </label>
                            <input
                                type="text"
                                value={settings.test_event_code}
                                onChange={(e) => setSettings({ ...settings, test_event_code: e.target.value })}
                                placeholder="e.g., TEST12345"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                Use this to test events in Facebook Events Manager without affecting your real data
                            </p>
                        </div>

                        {/* Events to Track */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-3">
                                Events to Track
                            </label>
                            <div className="space-y-3">
                                {Object.entries(eventDescriptions).map(([key, { label, desc }]) => (
                                    <label
                                        key={key}
                                        className="flex items-start gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={settings.events[key] !== false}
                                            onChange={() => toggleEvent(key)}
                                            className="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <div>
                                            <div className="font-medium text-gray-900">{label}</div>
                                            <div className="text-sm text-gray-500">{desc}</div>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-3 pt-4 border-t border-gray-200">
                            <button
                                onClick={handleSave}
                                disabled={loading}
                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                {loading ? 'Saving...' : 'Save Settings'}
                            </button>
                            {settings.access_token && (
                                <button
                                    onClick={handleTestConnection}
                                    disabled={testing || loading}
                                    className="px-4 py-2 text-gray-700 hover:bg-gray-100 border border-gray-300 rounded-lg font-medium disabled:opacity-50"
                                >
                                    {testing ? 'Testing...' : 'Test Connection'}
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {!settings.enabled && (
                    <div className="bg-blue-50 border border-blue-100 rounded-lg p-4">
                        <h4 className="font-medium text-blue-900 mb-2">Why use Facebook Pixel?</h4>
                        <ul className="text-sm text-blue-800 space-y-1">
                            <li>• Track conversions from your Facebook & Instagram ads</li>
                            <li>• Build retargeting audiences for abandoned carts</li>
                            <li>• Create lookalike audiences to find new customers</li>
                            <li>• Optimize ad delivery for better ROAS</li>
                        </ul>
                    </div>
                )}
            </div>

            {/* Info Box */}
            <div className="bg-gray-50 rounded-lg border border-gray-200 p-4">
                <div className="flex gap-3">
                    <svg className="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h4 className="font-medium text-gray-900 mb-1">How it works</h4>
                        <p className="text-sm text-gray-600">
                            When enabled, pixel events are sent both from the visitor's browser and from our server (if you provide the Access Token).
                            This dual approach ensures maximum data accuracy by bypassing ad blockers and iOS privacy restrictions.
                            Events are deduplicated automatically so they're only counted once.
                        </p>
                    </div>
                </div>
            </div>

            {/* Coming Soon: TikTok Pixel */}
            <div className="bg-white rounded-lg border border-gray-200 p-6 opacity-60">
                <div className="flex items-center gap-3 mb-2">
                    <div className="w-10 h-10 bg-black rounded-lg flex items-center justify-center">
                        <svg className="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">TikTok Pixel</h3>
                        <p className="text-sm text-gray-500">Coming soon</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Funnel Settings Modal
function FunnelSettingsModal({ funnel, onClose, onSave }) {
    const [form, setForm] = useState({
        name: funnel.name || '',
        slug: funnel.slug || '',
        description: funnel.description || '',
        meta_title: funnel.settings?.meta_title || '',
        meta_description: funnel.settings?.meta_description || '',
    });
    const [saving, setSaving] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await onSave({
                name: form.name,
                slug: form.slug,
                description: form.description,
                settings: {
                    ...funnel.settings,
                    meta_title: form.meta_title,
                    meta_description: form.meta_description,
                },
            });
            onClose();
        } catch (err) {
            console.error('Failed to save settings:', err);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    <h2 className="text-xl font-bold text-gray-900 mb-4">Funnel Settings</h2>
                    <form onSubmit={handleSubmit}>
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

                            <hr className="my-4" />

                            <h3 className="font-medium text-gray-900">SEO Settings</h3>

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

                        <div className="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={saving}
                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                {saving ? 'Saving...' : 'Save Settings'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

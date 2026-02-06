/**
 * Orders Tab Component
 * Displays funnel orders, abandoned carts, and order statistics
 */

import React, { useState, useEffect, useCallback } from 'react';
import { ordersApi } from '../services/api';

// Badge colors for order types
const ORDER_TYPE_COLORS = {
    main: 'bg-blue-100 text-blue-800',
    upsell: 'bg-green-100 text-green-800',
    downsell: 'bg-yellow-100 text-yellow-800',
    bump: 'bg-purple-100 text-purple-800',
};

// Badge colors for order status
const ORDER_STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    created: 'bg-gray-100 text-gray-800',
    paid: 'bg-green-100 text-green-800',
    confirmed: 'bg-blue-100 text-blue-800',
    processing: 'bg-indigo-100 text-indigo-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
    rts: 'bg-orange-100 text-orange-800',
    unknown: 'bg-gray-100 text-gray-600',
};

// Badge colors for cart recovery status
const CART_STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    sent: 'bg-blue-100 text-blue-800',
    recovered: 'bg-green-100 text-green-800',
    expired: 'bg-gray-100 text-gray-600',
};

// Stat Card Component
function StatCard({ label, value, color = 'gray', icon }) {
    const colorClasses = {
        gray: 'text-gray-900',
        green: 'text-green-600',
        orange: 'text-orange-600',
        blue: 'text-blue-600',
    };

    return (
        <div className="bg-white rounded-lg border border-gray-200 p-4">
            {icon && <div className="text-gray-400 mb-2">{icon}</div>}
            <p className={`text-2xl font-bold ${colorClasses[color] || colorClasses.gray}`}>
                {value}
            </p>
            <p className="text-sm text-gray-500">{label}</p>
        </div>
    );
}

// Pagination Component
function Pagination({ meta, onPageChange }) {
    if (!meta || meta.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between px-6 py-4 border-t border-gray-200">
            <p className="text-sm text-gray-500">
                Showing {((meta.current_page - 1) * meta.per_page) + 1} to{' '}
                {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} results
            </p>
            <div className="flex gap-2">
                <button
                    onClick={() => onPageChange(meta.current_page - 1)}
                    disabled={meta.current_page === 1}
                    className="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Previous
                </button>
                <button
                    onClick={() => onPageChange(meta.current_page + 1)}
                    disabled={meta.current_page === meta.last_page}
                    className="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Next
                </button>
            </div>
        </div>
    );
}

export default function OrdersTab({ funnelUuid, showToast }) {
    // State
    const [stats, setStats] = useState(null);
    const [orders, setOrders] = useState({ data: [], meta: {} });
    const [carts, setCarts] = useState({ data: [], meta: {} });
    const [loading, setLoading] = useState(true);
    const [ordersLoading, setOrdersLoading] = useState(false);
    const [cartsLoading, setCartsLoading] = useState(false);

    // Filters
    const [orderTypeFilter, setOrderTypeFilter] = useState('');
    const [orderDateFilter, setOrderDateFilter] = useState('');
    const [cartStatusFilter, setCartStatusFilter] = useState('');
    const [ordersPage, setOrdersPage] = useState(1);
    const [cartsPage, setCartsPage] = useState(1);

    // Load stats
    const loadStats = useCallback(async () => {
        try {
            const response = await ordersApi.stats(funnelUuid);
            setStats(response.data);
        } catch (err) {
            console.error('Failed to load order stats:', err);
            showToast?.('Failed to load order statistics', 'error');
        }
    }, [funnelUuid, showToast]);

    // Load orders
    const loadOrders = useCallback(async () => {
        setOrdersLoading(true);
        try {
            const params = { page: ordersPage };
            if (orderTypeFilter) params.type = orderTypeFilter;
            if (orderDateFilter) params.date = orderDateFilter;

            const response = await ordersApi.list(funnelUuid, params);
            setOrders({ data: response.data, meta: response.meta });
        } catch (err) {
            console.error('Failed to load orders:', err);
            showToast?.('Failed to load orders', 'error');
        } finally {
            setOrdersLoading(false);
        }
    }, [funnelUuid, ordersPage, orderTypeFilter, orderDateFilter, showToast]);

    // Load abandoned carts
    const loadCarts = useCallback(async () => {
        setCartsLoading(true);
        try {
            const params = { page: cartsPage };
            if (cartStatusFilter) params.status = cartStatusFilter;

            const response = await ordersApi.abandonedCarts(funnelUuid, params);
            setCarts({ data: response.data, meta: response.meta });
        } catch (err) {
            console.error('Failed to load abandoned carts:', err);
            showToast?.('Failed to load abandoned carts', 'error');
        } finally {
            setCartsLoading(false);
        }
    }, [funnelUuid, cartsPage, cartStatusFilter, showToast]);

    // Initial load
    useEffect(() => {
        const loadAll = async () => {
            setLoading(true);
            await Promise.all([loadStats(), loadOrders(), loadCarts()]);
            setLoading(false);
        };
        loadAll();
    }, [loadStats, loadOrders, loadCarts]);

    // Reload orders when filters change
    useEffect(() => {
        if (!loading) {
            loadOrders();
        }
    }, [orderTypeFilter, orderDateFilter, ordersPage]);

    // Reload carts when filter changes
    useEffect(() => {
        if (!loading) {
            loadCarts();
        }
    }, [cartStatusFilter, cartsPage]);

    // Reset page when filter changes
    const handleOrderTypeChange = (value) => {
        setOrderTypeFilter(value);
        setOrdersPage(1);
    };

    const handleOrderDateChange = (value) => {
        setOrderDateFilter(value);
        setOrdersPage(1);
    };

    const handleCartStatusChange = (value) => {
        setCartStatusFilter(value);
        setCartsPage(1);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Quick Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard
                    label="Total Orders"
                    value={stats?.total_orders?.toLocaleString() || 0}
                    icon={
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Total Revenue"
                    value={`RM ${(stats?.total_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
                    color="green"
                    icon={
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Avg Order Value"
                    value={`RM ${(stats?.avg_order_value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
                    icon={
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Abandoned Carts"
                    value={stats?.abandoned_count?.toLocaleString() || 0}
                    color="orange"
                    icon={
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    }
                />
            </div>

            {/* Order Type Breakdown and Cart Recovery Stats */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Order Type Breakdown */}
                <div className="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-1">Order Type Breakdown</h3>
                    <p className="text-sm text-gray-500 mb-4">Revenue by order type</p>

                    <div className="space-y-3">
                        {[
                            { key: 'main', label: 'Main Orders' },
                            { key: 'upsell', label: 'Upsells' },
                            { key: 'downsell', label: 'Downsells' },
                            { key: 'bump', label: 'Order Bumps' },
                        ].map(({ key, label }) => {
                            const data = stats?.type_breakdown?.[key] || { count: 0, revenue: 0 };
                            return (
                                <div key={key} className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className={`px-2 py-0.5 rounded text-xs font-medium ${ORDER_TYPE_COLORS[key]}`}>
                                            {label}
                                        </span>
                                        <span className="text-sm text-gray-500">({data.count})</span>
                                    </div>
                                    <span className="font-semibold text-gray-900">
                                        RM {data.revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Cart Recovery Stats */}
                <div className="bg-white rounded-lg border border-gray-200 p-6 lg:col-span-2">
                    <h3 className="text-lg font-semibold text-gray-900 mb-1">Cart Recovery Status</h3>
                    <p className="text-sm text-gray-500 mb-4">Abandoned cart breakdown by recovery status</p>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="text-center p-3 bg-yellow-50 rounded-lg">
                            <p className="text-2xl font-bold text-yellow-600">
                                {stats?.cart_stats?.pending || 0}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">Pending</p>
                        </div>
                        <div className="text-center p-3 bg-blue-50 rounded-lg">
                            <p className="text-2xl font-bold text-blue-600">
                                {stats?.cart_stats?.sent || 0}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">Recovery Sent</p>
                        </div>
                        <div className="text-center p-3 bg-green-50 rounded-lg">
                            <p className="text-2xl font-bold text-green-600">
                                {stats?.cart_stats?.recovered || 0}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">Recovered</p>
                        </div>
                        <div className="text-center p-3 bg-gray-50 rounded-lg">
                            <p className="text-2xl font-bold text-gray-600">
                                {stats?.cart_stats?.expired || 0}
                            </p>
                            <p className="text-sm text-gray-500 mt-1">Expired</p>
                        </div>
                    </div>

                    {(stats?.cart_stats?.recoverable_value || 0) > 0 && (
                        <div className="mt-4 p-3 bg-orange-50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-orange-800">Recoverable Value</span>
                                <span className="text-lg font-bold text-orange-600">
                                    RM {stats.cart_stats.recoverable_value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Orders Table */}
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div className="p-6 border-b border-gray-200">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Funnel Orders</h3>
                            <p className="text-sm text-gray-500">All orders from this funnel</p>
                        </div>

                        <div className="flex items-center gap-3">
                            <select
                                value={orderTypeFilter}
                                onChange={(e) => handleOrderTypeChange(e.target.value)}
                                className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">All Types</option>
                                <option value="main">Main</option>
                                <option value="upsell">Upsell</option>
                                <option value="downsell">Downsell</option>
                                <option value="bump">Bump</option>
                            </select>

                            <select
                                value={orderDateFilter}
                                onChange={(e) => handleOrderDateChange(e.target.value)}
                                className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="7d">Last 7 Days</option>
                                <option value="30d">Last 30 Days</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    {ordersLoading && (
                        <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        </div>
                    )}
                    <table className="min-w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Step</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-12 text-center">
                                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                        <h3 className="mt-2 text-lg font-medium text-gray-900">No orders yet</h3>
                                        <p className="mt-1 text-sm text-gray-500">Orders from this funnel will appear here.</p>
                                    </td>
                                </tr>
                            ) : (
                                orders.data.map((order) => (
                                    <tr key={order.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="font-medium text-gray-900">{order.order_number}</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-sm text-gray-900">{order.customer_email}</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="font-semibold text-gray-900">{order.formatted_revenue}</span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-0.5 rounded text-xs font-medium ${ORDER_TYPE_COLORS[order.order_type] || 'bg-gray-100 text-gray-800'}`}>
                                                {order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-0.5 rounded text-xs font-medium ${ORDER_STATUS_COLORS[order.order_status] || 'bg-gray-100 text-gray-800'}`}>
                                                {order.order_status.charAt(0).toUpperCase() + order.order_status.slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {order.step_name}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">
                                                {new Date(order.created_at).toLocaleDateString('en-MY', { month: 'short', day: 'numeric', year: 'numeric' })}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {new Date(order.created_at).toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', hour12: true })}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={orders.meta} onPageChange={setOrdersPage} />
            </div>

            {/* Abandoned Carts Table */}
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div className="p-6 border-b border-gray-200">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Abandoned Carts</h3>
                            <p className="text-sm text-gray-500">Carts that were abandoned before checkout</p>
                        </div>

                        <select
                            value={cartStatusFilter}
                            onChange={(e) => handleCartStatusChange(e.target.value)}
                            className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="sent">Recovery Sent</option>
                            <option value="recovered">Recovered</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>

                <div className="overflow-x-auto relative">
                    {cartsLoading && (
                        <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        </div>
                    )}
                    <table className="min-w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cart Value</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emails Sent</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abandoned</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {carts.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center">
                                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <h3 className="mt-2 text-lg font-medium text-gray-900">No abandoned carts</h3>
                                        <p className="mt-1 text-sm text-gray-500">Abandoned carts from this funnel will appear here.</p>
                                    </td>
                                </tr>
                            ) : (
                                carts.data.map((cart) => (
                                    <tr key={cart.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{cart.email}</div>
                                            {cart.phone && (
                                                <div className="text-xs text-gray-500">{cart.phone}</div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="font-semibold text-gray-900">{cart.formatted_total}</div>
                                            <div className="text-xs text-gray-500">{cart.item_count} item(s)</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-0.5 rounded text-xs font-medium ${CART_STATUS_COLORS[cart.recovery_status] || 'bg-gray-100 text-gray-800'}`}>
                                                {cart.recovery_status.charAt(0).toUpperCase() + cart.recovery_status.slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {cart.recovery_emails_sent} / 3
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {cart.abandoned_at ? (
                                                <>
                                                    <div className="text-sm text-gray-900">
                                                        {new Date(cart.abandoned_at).toLocaleDateString('en-MY', { month: 'short', day: 'numeric', year: 'numeric' })}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {new Date(cart.abandoned_at).toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', hour12: true })}
                                                    </div>
                                                </>
                                            ) : (
                                                <span className="text-sm text-gray-500">-</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {cart.abandoned_at_human || '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination meta={carts.meta} onPageChange={setCartsPage} />
            </div>
        </div>
    );
}

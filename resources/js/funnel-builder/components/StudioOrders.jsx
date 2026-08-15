/**
 * Studio Orders — every funnel order across all visible funnels in one list,
 * with filters and a click-through detail modal showing everything inside
 * the order (customer, items, payment, traffic source, funnel flags).
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const apiHeaders = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-XSRF-TOKEN': getCsrfToken(),
});

const fmtRM = (value) => `RM ${Number(value || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;

const STATUS_BADGES = {
    paid: 'bg-green-100 text-green-800',
    confirmed: 'bg-green-100 text-green-800',
    delivered: 'bg-green-100 text-green-800',
    processing: 'bg-blue-100 text-blue-800',
    shipped: 'bg-blue-100 text-blue-800',
    pending: 'bg-yellow-100 text-yellow-800',
    unpaid: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-700',
    refunded: 'bg-red-100 text-red-700',
    returned: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
};

const TYPE_LABELS = { main: 'Main', upsell: 'Upsell', downsell: 'Downsell', bump: 'Bump' };

function Badge({ value }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_BADGES[value] || 'bg-gray-100 text-gray-600'}`}>
            {value}
        </span>
    );
}

export default function StudioOrders({ onSelectFunnel }) {
    const [orders, setOrders] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const [date, setDate] = useState('30d');
    const [detail, setDetail] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [editing, setEditing] = useState(false);
    const [editForm, setEditForm] = useState(null);
    const [savingEdit, setSavingEdit] = useState(false);
    const [editError, setEditError] = useState(null);
    const debounceRef = useRef(null);

    const loadOrders = useCallback(async (params) => {
        setLoading(true);
        try {
            const qs = new URLSearchParams(
                Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))
            ).toString();
            const response = await fetch(`/api/v1/studio/orders?${qs}`, {
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            setOrders(data.data || []);
            setMeta(data.meta || null);
        } catch (e) {
            setOrders([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            loadOrders({ search, type, date, page, per_page: 20 });
        }, search ? 300 : 0);
        return () => clearTimeout(debounceRef.current);
    }, [search, type, date, page, loadOrders]);

    const openDetail = async (order) => {
        setDetailLoading(true);
        setEditing(false);
        setEditError(null);
        setDetail({ order, full: null });
        try {
            const response = await fetch(`/api/v1/studio/orders/${order.id}`, {
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json();
            setDetail({ order, full: data.data });
        } catch (e) {
            // Keep the row summary visible even if the detail fetch fails.
        } finally {
            setDetailLoading(false);
        }
    };

    const startEditing = () => {
        const editable = detail?.full?.editable || {};
        setEditForm({
            customer_name: editable.customer_name || '',
            guest_email: editable.guest_email || '',
            tracking_id: editable.tracking_id || '',
            internal_notes: editable.internal_notes || '',
            status: editable.status || 'pending',
            payment_status: editable.payment_status || 'pending',
        });
        setEditError(null);
        setEditing(true);
    };

    const saveEdit = async () => {
        if (!detail?.order) return;
        setSavingEdit(true);
        setEditError(null);
        try {
            const response = await fetch(`/api/v1/studio/orders/${detail.order.id}`, {
                method: 'PUT',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(editForm),
            });
            const data = await response.json();
            if (response.ok) {
                setDetail({ order: detail.order, full: data.data });
                setEditing(false);
                loadOrders({ search, type, date, page, per_page: 20 });
            } else {
                setEditError(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Failed to save changes');
            }
        } catch (e) {
            setEditError('Failed to save changes');
        } finally {
            setSavingEdit(false);
        }
    };

    const full = detail?.full;

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6">
                <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                    Funnel <span className="fs-gradient-text">Orders</span>
                </h1>
                <p className="mt-0.5 text-[13px] text-gray-500">
                    {meta ? `${meta.total.toLocaleString()} orders across all funnels` : 'Loading orders...'}
                </p>
            </div>

            {/* Filters */}
            <div className="mb-5 flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    value={search}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        setPage(1);
                    }}
                    placeholder="Search order #, customer, email..."
                    className="min-w-[220px] flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                />
                <select
                    value={type}
                    onChange={(e) => {
                        setType(e.target.value);
                        setPage(1);
                    }}
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none"
                >
                    <option value="">All Types</option>
                    <option value="main">Main</option>
                    <option value="upsell">Upsell</option>
                    <option value="downsell">Downsell</option>
                    <option value="bump">Order Bump</option>
                </select>
                <select
                    value={date}
                    onChange={(e) => {
                        setDate(e.target.value);
                        setPage(1);
                    }}
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none"
                >
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                </select>
            </div>

            {/* Table */}
            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table className="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr className="border-b border-gray-200 text-left text-xs uppercase tracking-wider text-gray-500">
                            <th className="px-4 py-3 font-medium">Order</th>
                            <th className="px-4 py-3 font-medium">Customer</th>
                            <th className="px-4 py-3 font-medium">Funnel</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 text-right font-medium">Revenue</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody className="divide-gray-200">
                        {loading && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-gray-500">Loading orders...</td>
                            </tr>
                        )}
                        {!loading && orders.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-gray-500">No orders found for these filters.</td>
                            </tr>
                        )}
                        {!loading &&
                            orders.map((order) => (
                                <tr
                                    key={order.id}
                                    onClick={() => openDetail(order)}
                                    className="cursor-pointer border-b border-gray-100 transition-colors hover:bg-gray-50"
                                >
                                    <td className="px-4 py-3 font-medium text-gray-900">{order.order_number}</td>
                                    <td className="px-4 py-3">
                                        <div className="text-gray-900">{order.customer_name}</div>
                                        <div className="text-xs text-gray-500">{order.customer_email}</div>
                                    </td>
                                    <td className="max-w-[180px] truncate px-4 py-3 text-gray-700">{order.funnel_name}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800">
                                            {TYPE_LABELS[order.order_type] || order.order_type}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{fmtRM(order.funnel_revenue)}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col gap-1">
                                            <Badge value={order.payment_status} />
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-500">{order.created_at_human}</td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm text-gray-500">
                    <span>
                        Page {meta.current_page} of {meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={meta.current_page <= 1}
                            className="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                        >
                            Previous
                        </button>
                        <button
                            onClick={() => setPage((p) => p + 1)}
                            disabled={meta.current_page >= meta.last_page}
                            className="rounded-lg border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-40"
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}

            {/* Order Detail Modal */}
            {detail && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setDetail(null)}>
                    <div
                        className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">{detail.order.order_number}</h3>
                                <p className="text-xs text-gray-500">{detail.order.created_at_human}</p>
                            </div>
                            <div className="flex items-center gap-2">
                                {full && !editing && (
                                    <button
                                        onClick={startEditing}
                                        className="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 cursor-pointer"
                                    >
                                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit Order
                                    </button>
                                )}
                                <button onClick={() => setDetail(null)} className="text-gray-400 hover:text-gray-600">
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {detailLoading && <div className="px-6 py-10 text-center text-gray-500">Loading order details...</div>}

                        {!detailLoading && full && editing && editForm && (
                            <div className="space-y-4 px-6 py-5">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Customer Name</label>
                                        <input
                                            type="text"
                                            value={editForm.customer_name}
                                            onChange={(e) => setEditForm({ ...editForm, customer_name: e.target.value })}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Customer Email</label>
                                        <input
                                            type="email"
                                            value={editForm.guest_email}
                                            onChange={(e) => setEditForm({ ...editForm, guest_email: e.target.value })}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Order Status</label>
                                        <select
                                            value={editForm.status}
                                            onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none"
                                        >
                                            {['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'returned', 'cancelled'].map((s) => (
                                                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                            ))}
                                        </select>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Cancelling or returning a paid order automatically flips payment to refunded.
                                        </p>
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Payment Status</label>
                                        <select
                                            value={editForm.payment_status}
                                            onChange={(e) => setEditForm({ ...editForm, payment_status: e.target.value })}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none"
                                        >
                                            {['pending', 'paid', 'refunded', 'failed'].map((s) => (
                                                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-gray-700">Tracking ID</label>
                                    <input
                                        type="text"
                                        value={editForm.tracking_id}
                                        onChange={(e) => setEditForm({ ...editForm, tracking_id: e.target.value })}
                                        placeholder="Courier tracking number"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-gray-700">Internal Notes</label>
                                    <textarea
                                        value={editForm.internal_notes}
                                        onChange={(e) => setEditForm({ ...editForm, internal_notes: e.target.value })}
                                        rows={3}
                                        placeholder="Only your team sees this"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-orange-500"
                                    />
                                </div>

                                {editError && (
                                    <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{editError}</p>
                                )}

                                <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                                    <button
                                        onClick={() => setEditing(false)}
                                        disabled={savingEdit}
                                        className="rounded-lg px-4 py-2 font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={saveEdit}
                                        disabled={savingEdit}
                                        className="rounded-lg bg-orange-600 px-4 py-2 font-medium text-white hover:bg-orange-700 disabled:opacity-50"
                                    >
                                        {savingEdit ? 'Saving...' : 'Save Changes'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {!detailLoading && full && !editing && (
                            <div className="space-y-5 px-6 py-5">
                                {/* Customer + Funnel */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="rounded-lg bg-gray-50 p-4">
                                        <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Customer</p>
                                        <p className="font-medium text-gray-900">{full.customer.name}</p>
                                        <p className="text-sm text-gray-600">{full.customer.email}</p>
                                    </div>
                                    <div className="rounded-lg bg-gray-50 p-4">
                                        <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Funnel</p>
                                        <button
                                            onClick={() => {
                                                setDetail(null);
                                                onSelectFunnel?.({ uuid: full.funnel_uuid });
                                            }}
                                            className="font-medium text-orange-600 hover:text-orange-700 cursor-pointer text-left"
                                        >
                                            {full.funnel_name}
                                        </button>
                                        <p className="text-sm text-gray-600">
                                            {full.step_name} · {TYPE_LABELS[full.order_type] || full.order_type}
                                        </p>
                                    </div>
                                </div>

                                {/* Items */}
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Items</p>
                                    <div className="divide-gray-200 rounded-lg border border-gray-200">
                                        {(full.items || []).map((item, i) => (
                                            <div key={i} className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-gray-900">{item.name}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {[item.variant, item.sku].filter(Boolean).join(' · ') || `Qty ${item.quantity}`}
                                                    </p>
                                                </div>
                                                <div className="shrink-0 text-right text-sm">
                                                    <p className="font-semibold text-gray-900">{fmtRM(item.unit_price)}</p>
                                                    <p className="text-xs text-gray-500">× {item.quantity}</p>
                                                </div>
                                            </div>
                                        ))}
                                        {(full.items || []).length === 0 && (
                                            <p className="px-4 py-3 text-sm text-gray-500">No line items recorded.</p>
                                        )}
                                    </div>
                                </div>

                                {/* Payment summary */}
                                <div className="rounded-lg bg-gray-50 p-4 text-sm">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Payment</p>
                                    <div className="space-y-1">
                                        <div className="flex justify-between text-gray-600">
                                            <span>Subtotal</span>
                                            <span>{fmtRM(full.payment.subtotal)}</span>
                                        </div>
                                        <div className="flex justify-between text-gray-600">
                                            <span>Shipping</span>
                                            <span>{fmtRM(full.payment.shipping_cost)}</span>
                                        </div>
                                        {full.payment.discount_amount > 0 && (
                                            <div className="flex justify-between text-gray-600">
                                                <span>Discount{full.payment.coupon_code ? ` (${full.payment.coupon_code})` : ''}</span>
                                                <span>- {fmtRM(full.payment.discount_amount)}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900">
                                            <span>Total</span>
                                            <span>{fmtRM(full.payment.total_amount)}</span>
                                        </div>
                                        <div className="flex items-center justify-between pt-2">
                                            <span className="text-gray-600">Status</span>
                                            <span className="flex gap-2">
                                                <Badge value={full.payment.status} />
                                                <Badge value={full.payment.payment_status} />
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Source + funnel flags */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="rounded-lg bg-gray-50 p-4 text-sm">
                                        <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Traffic Source</p>
                                        <p className="text-gray-900">{full.source.utm_source}</p>
                                        {full.source.utm_campaign && <p className="text-xs text-gray-500">Campaign: {full.source.utm_campaign}</p>}
                                        {full.source.utm_medium && <p className="text-xs text-gray-500">Medium: {full.source.utm_medium}</p>}
                                    </div>
                                    <div className="rounded-lg bg-gray-50 p-4 text-sm">
                                        <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Funnel Extras</p>
                                        <p className="text-gray-600">Upsells accepted: <span className="font-medium text-gray-900">{full.funnel_flags.upsells_accepted}</span></p>
                                        <p className="text-gray-600">Bumps accepted: <span className="font-medium text-gray-900">{full.funnel_flags.bumps_accepted}</span></p>
                                        <p className="text-gray-600">Downsells accepted: <span className="font-medium text-gray-900">{full.funnel_flags.downsells_accepted}</span></p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

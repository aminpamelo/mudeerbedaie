import React, { useState, useEffect } from 'react';
import { saleApi } from '../services/api';

const STATUS_OPTIONS = [
    { value: 'paid', label: 'Paid', color: 'bg-green-100 text-green-700' },
    { value: 'pending', label: 'Pending', color: 'bg-yellow-100 text-yellow-700' },
    { value: 'cancelled', label: 'Cancelled', color: 'bg-red-100 text-red-700' },
];

function getDisplayStatus(sale) {
    if (sale.paid_time) return 'paid';
    if (sale.status === 'cancelled') return 'cancelled';
    return 'pending';
}

const PERIOD_OPTIONS = [
    { value: '', label: 'All Time' },
    { value: 'today', label: 'Today' },
    { value: 'this_week', label: 'This Week' },
    { value: 'this_month', label: 'This Month' },
];

const PAYMENT_OPTIONS = [
    { value: '', label: 'All Payments' },
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'ewallet', label: 'E-Wallet' },
];

export default function SalesHistory({ isMobile = false }) {
    const [sales, setSales] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [paymentFilter, setPaymentFilter] = useState('');
    const [periodFilter, setPeriodFilter] = useState('');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [selectedSale, setSelectedSale] = useState(null);
    const [updating, setUpdating] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    // Inline editing states
    const [editingTracking, setEditingTracking] = useState(false);
    const [trackingValue, setTrackingValue] = useState('');
    const [editingNotes, setEditingNotes] = useState(false);
    const [notesValue, setNotesValue] = useState('');
    const [savingDetails, setSavingDetails] = useState(false);

    const fetchSales = async (searchTerm, pageNum, filters = {}) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: 15 };
            if (searchTerm) params.search = searchTerm;
            if (filters.status) params.status = filters.status;
            if (filters.payment_method) params.payment_method = filters.payment_method;
            if (filters.period) params.period = filters.period;

            const response = await saleApi.list(params);
            const data = response.data || [];

            if (pageNum === 1) {
                setSales(data);
            } else {
                setSales(prev => [...prev, ...data]);
            }
            setHasMore(response.next_page_url !== null);
        } catch (err) {
            console.error('Failed to fetch sales:', err);
        } finally {
            setLoading(false);
        }
    };

    const currentFilters = { status: statusFilter, payment_method: paymentFilter, period: periodFilter };
    const hasActiveFilters = statusFilter || paymentFilter || periodFilter;

    useEffect(() => {
        setPage(1);
        const timer = setTimeout(() => fetchSales(search, 1, currentFilters), 300);
        return () => clearTimeout(timer);
    }, [search, statusFilter, paymentFilter, periodFilter]);

    const handleStatusChange = async (newStatus) => {
        if (!selectedSale || updating) return;
        setUpdating(true);
        try {
            const response = await saleApi.updateStatus(selectedSale.id, newStatus);
            const updatedSale = response.data;

            setSales(prev => prev.map(s => s.id === updatedSale.id ? updatedSale : s));
            setSelectedSale(updatedSale);
        } catch (err) {
            console.error('Failed to update status:', err);
            alert('Failed to update status: ' + err.message);
        } finally {
            setUpdating(false);
        }
    };

    const handleSaveTracking = async () => {
        if (!selectedSale || savingDetails) return;
        setSavingDetails(true);
        try {
            const response = await saleApi.updateDetails(selectedSale.id, {
                tracking_id: trackingValue || null,
            });
            const updatedSale = response.data;
            setSales(prev => prev.map(s => s.id === updatedSale.id ? updatedSale : s));
            setSelectedSale(updatedSale);
            setEditingTracking(false);
        } catch (err) {
            console.error('Failed to update tracking:', err);
            alert('Failed to update tracking: ' + err.message);
        } finally {
            setSavingDetails(false);
        }
    };

    const handleSaveNotes = async () => {
        if (!selectedSale || savingDetails) return;
        setSavingDetails(true);
        try {
            const response = await saleApi.updateDetails(selectedSale.id, {
                internal_notes: notesValue || null,
            });
            const updatedSale = response.data;
            setSales(prev => prev.map(s => s.id === updatedSale.id ? updatedSale : s));
            setSelectedSale(updatedSale);
            setEditingNotes(false);
        } catch (err) {
            console.error('Failed to update notes:', err);
            alert('Failed to update notes: ' + err.message);
        } finally {
            setSavingDetails(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedSale || deleting) return;
        setDeleting(true);
        try {
            await saleApi.delete(selectedSale.id);
            setSales(prev => prev.filter(s => s.id !== selectedSale.id));
            setSelectedSale(null);
            setShowDeleteConfirm(false);
        } catch (err) {
            console.error('Failed to delete sale:', err);
            alert('Failed to delete sale: ' + err.message);
        } finally {
            setDeleting(false);
        }
    };

    // Reset editing states when selected sale changes
    useEffect(() => {
        setEditingTracking(false);
        setEditingNotes(false);
        setShowDeleteConfirm(false);
    }, [selectedSale?.id]);

    const renderSaleDetail = () => {
        if (!selectedSale) return null;

        return (
            <div className="space-y-3">
                <div>
                    <p className="text-xs text-gray-500">Customer</p>
                    <p className="text-sm font-medium text-gray-900">
                        {selectedSale.customer?.name || selectedSale.customer_name || 'Walk-in'}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500">Salesperson</p>
                    <p className="text-sm font-medium text-gray-900">{selectedSale.metadata?.salesperson_name || '-'}</p>
                </div>

                {/* Contact & Address */}
                {(selectedSale.customer_phone || selectedSale.customer?.phone || selectedSale.guest_email || selectedSale.customer?.email || selectedSale.shipping_address) && (
                    <div className="border-t border-gray-100 pt-3 space-y-2">
                        {(selectedSale.customer_phone || selectedSale.customer?.phone) && (
                            <div>
                                <p className="text-xs text-gray-500">Phone</p>
                                <p className="text-sm text-gray-700">{selectedSale.customer_phone || selectedSale.customer?.phone}</p>
                            </div>
                        )}
                        {(selectedSale.guest_email || selectedSale.customer?.email) && (
                            <div>
                                <p className="text-xs text-gray-500">Email</p>
                                <p className="text-sm text-gray-700">{selectedSale.guest_email || selectedSale.customer?.email}</p>
                            </div>
                        )}
                        {selectedSale.shipping_address && (
                            <div>
                                <p className="text-xs text-gray-500">Address</p>
                                <p className="text-sm text-gray-700">
                                    {typeof selectedSale.shipping_address === 'string'
                                        ? selectedSale.shipping_address
                                        : selectedSale.shipping_address.full_address || '-'}
                                </p>
                            </div>
                        )}
                    </div>
                )}

                <div className="border-t border-gray-100 pt-3">
                    <p className="text-xs text-gray-500 mb-2">Items</p>
                    {selectedSale.items?.map((item, i) => (
                        <div key={i} className="flex justify-between py-1">
                            <span className="text-sm text-gray-700">
                                {item.product_name} {item.variant_name ? `(${item.variant_name})` : ''} x{item.quantity_ordered || item.quantity}
                            </span>
                            <span className="text-sm font-medium text-gray-900">
                                RM {parseFloat(item.total_price).toFixed(2)}
                            </span>
                        </div>
                    ))}
                </div>
                {parseFloat(selectedSale.discount_amount) > 0 && (
                    <div className="flex justify-between text-sm text-red-500">
                        <span>Discount</span>
                        <span>- RM {parseFloat(selectedSale.discount_amount).toFixed(2)}</span>
                    </div>
                )}
                <div className="border-t border-gray-200 pt-3 flex justify-between">
                    <span className="font-semibold text-gray-900">Total</span>
                    <span className="font-bold text-blue-600">
                        RM {parseFloat(selectedSale.total_amount).toFixed(2)}
                    </span>
                </div>

                {/* Tracking Number */}
                <div className="border-t border-gray-100 pt-3">
                    <div className="flex items-center justify-between mb-1">
                        <p className="text-xs text-gray-500">Tracking Number</p>
                        {!editingTracking && (
                            <button
                                onClick={() => { setTrackingValue(selectedSale.tracking_id || ''); setEditingTracking(true); }}
                                className="text-xs text-blue-600 hover:text-blue-700"
                            >
                                {selectedSale.tracking_id ? 'Edit' : 'Add'}
                            </button>
                        )}
                    </div>
                    {editingTracking ? (
                        <div className="flex items-center gap-1">
                            <input
                                type="text"
                                value={trackingValue}
                                onChange={(e) => setTrackingValue(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') handleSaveTracking();
                                    if (e.key === 'Escape') setEditingTracking(false);
                                }}
                                placeholder="Enter tracking number"
                                className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                                autoFocus
                            />
                            <button
                                onClick={handleSaveTracking}
                                disabled={savingDetails}
                                className="p-1 text-green-600 hover:text-green-700 hover:bg-green-50 rounded disabled:opacity-50"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button
                                onClick={() => setEditingTracking(false)}
                                className="p-1 text-red-600 hover:text-red-700 hover:bg-red-50 rounded"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    ) : (
                        <p className="text-sm text-gray-700">
                            {selectedSale.tracking_id || <span className="text-gray-400">No tracking number</span>}
                        </p>
                    )}
                </div>

                {/* Notes */}
                <div className="border-t border-gray-100 pt-3">
                    <div className="flex items-center justify-between mb-1">
                        <p className="text-xs text-gray-500">Notes</p>
                        {!editingNotes && (
                            <button
                                onClick={() => { setNotesValue(selectedSale.internal_notes || ''); setEditingNotes(true); }}
                                className="text-xs text-blue-600 hover:text-blue-700"
                            >
                                {selectedSale.internal_notes ? 'Edit' : 'Add'}
                            </button>
                        )}
                    </div>
                    {editingNotes ? (
                        <div>
                            <textarea
                                value={notesValue}
                                onChange={(e) => setNotesValue(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') setEditingNotes(false);
                                }}
                                placeholder="Add notes..."
                                rows={3}
                                className="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"
                                autoFocus
                            />
                            <div className="flex justify-end gap-1 mt-1">
                                <button
                                    onClick={() => setEditingNotes(false)}
                                    className="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleSaveNotes}
                                    disabled={savingDetails}
                                    className="px-2 py-1 text-xs text-white bg-blue-600 hover:bg-blue-700 rounded disabled:opacity-50"
                                >
                                    {savingDetails ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-gray-700">
                            {selectedSale.internal_notes || <span className="text-gray-400">No notes</span>}
                        </p>
                    )}
                </div>

                {/* Receipt Attachment */}
                {selectedSale.receipt_attachment_url && (
                    <div className="border-t border-gray-100 pt-3">
                        <p className="text-xs text-gray-500 mb-2">Receipt</p>
                        {selectedSale.receipt_attachment?.endsWith('.pdf') ? (
                            <a
                                href={selectedSale.receipt_attachment_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-3 py-2 text-sm text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                View Receipt PDF
                            </a>
                        ) : (
                            <a
                                href={selectedSale.receipt_attachment_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block"
                            >
                                <img
                                    src={selectedSale.receipt_attachment_url}
                                    alt="Receipt"
                                    className="w-full rounded-lg border border-gray-200 hover:opacity-90 transition-opacity cursor-pointer"
                                />
                            </a>
                        )}
                    </div>
                )}

                {/* Status Update */}
                <div className="border-t border-gray-200 pt-3">
                    <p className="text-xs text-gray-500 mb-2">Update Status</p>
                    <div className="flex gap-2 flex-wrap">
                        {STATUS_OPTIONS.map(option => {
                            const currentStatus = getDisplayStatus(selectedSale);
                            const isActive = currentStatus === option.value;
                            return (
                                <button
                                    key={option.value}
                                    onClick={() => !isActive && handleStatusChange(option.value)}
                                    disabled={isActive || updating}
                                    className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
                                        isActive
                                            ? 'border-blue-300 bg-blue-50 text-blue-700 cursor-default'
                                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 cursor-pointer'
                                    } ${updating ? 'opacity-50' : ''}`}
                                >
                                    {updating ? '...' : option.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Delete */}
                <div className="border-t border-gray-200 pt-3">
                    {!showDeleteConfirm ? (
                        <button
                            onClick={() => setShowDeleteConfirm(true)}
                            className="w-full px-3 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg border border-red-200 transition-colors"
                        >
                            Delete Sale
                        </button>
                    ) : (
                        <div className="bg-red-50 rounded-lg p-3 border border-red-200">
                            <p className="text-sm text-red-700 font-medium mb-2">Are you sure you want to delete this sale?</p>
                            <p className="text-xs text-red-500 mb-3">This action cannot be undone.</p>
                            <div className="flex gap-2">
                                <button
                                    onClick={handleDelete}
                                    disabled={deleting}
                                    className="flex-1 px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {deleting ? 'Deleting...' : 'Yes, Delete'}
                                </button>
                                <button
                                    onClick={() => setShowDeleteConfirm(false)}
                                    className="flex-1 px-3 py-1.5 text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    return (
        <div className="h-full flex flex-col bg-gray-50">
            {/* Search Bar & Filters */}
            <div className="bg-white border-b border-gray-200 px-4 lg:px-6 py-3 shrink-0">
                <div className="flex items-center gap-2 lg:gap-3 overflow-x-auto pb-1 lg:pb-0">
                    <div className="relative w-full lg:w-64 shrink-0">
                        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search sales..."
                            className="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className={`px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 shrink-0 ${
                            statusFilter ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600'
                        }`}
                    >
                        <option value="">All Status</option>
                        {STATUS_OPTIONS.map(opt => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    <select
                        value={paymentFilter}
                        onChange={(e) => setPaymentFilter(e.target.value)}
                        className={`px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 shrink-0 ${
                            paymentFilter ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600'
                        }`}
                    >
                        {PAYMENT_OPTIONS.map(opt => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    <select
                        value={periodFilter}
                        onChange={(e) => setPeriodFilter(e.target.value)}
                        className={`px-3 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 shrink-0 ${
                            periodFilter ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600'
                        }`}
                    >
                        {PERIOD_OPTIONS.map(opt => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    {hasActiveFilters && (
                        <button
                            onClick={() => { setStatusFilter(''); setPaymentFilter(''); setPeriodFilter(''); }}
                            className="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors shrink-0"
                        >
                            Clear filters
                        </button>
                    )}
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-4 lg:p-6">
                {loading && sales.length === 0 ? (
                    <div className="flex items-center justify-center h-40">
                        <div className="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
                    </div>
                ) : sales.length === 0 ? (
                    <div className="text-center py-16 text-gray-400">
                        <p className="text-lg font-medium">No sales found</p>
                        <p className="text-sm mt-1">Sales will appear here once transactions are made.</p>
                    </div>
                ) : (
                    <div className="flex gap-6">
                        {/* Sales List */}
                        <div className="flex-1 min-w-0">
                            {isMobile ? (
                                /* Mobile: Card-based list */
                                <div className="space-y-2">
                                    {sales.map(sale => {
                                        const displayStatus = getDisplayStatus(sale);
                                        const statusOption = STATUS_OPTIONS.find(o => o.value === displayStatus);
                                        return (
                                            <button
                                                key={sale.id}
                                                onClick={() => { setSelectedSale(sale); setShowDeleteConfirm(false); }}
                                                className={`w-full text-left bg-white rounded-xl border p-4 transition-colors active:bg-gray-50 ${
                                                    selectedSale?.id === sale.id ? 'border-blue-300 bg-blue-50' : 'border-gray-200'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between mb-1.5">
                                                    <span className="text-sm font-medium text-blue-600">{sale.order_number}</span>
                                                    <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${statusOption?.color}`}>
                                                        {statusOption?.label}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-gray-900 font-medium">
                                                    {sale.customer?.name || sale.customer_name || 'Walk-in'}
                                                </p>
                                                <div className="flex items-center justify-between mt-2">
                                                    <div className="flex items-center gap-2 text-xs text-gray-500">
                                                        <span className="capitalize">{sale.payment_method?.replace('_', ' ')}</span>
                                                        <span>&middot;</span>
                                                        <span>{sale.items?.length || 0} item{(sale.items?.length || 0) !== 1 ? 's' : ''}</span>
                                                    </div>
                                                    <span className="text-sm font-bold text-gray-900">
                                                        RM {parseFloat(sale.total_amount).toFixed(2)}
                                                    </span>
                                                </div>
                                                {sale.order_date && (
                                                    <p className="text-xs text-gray-400 mt-1.5">
                                                        {new Date(sale.order_date).toLocaleDateString('en-MY', {
                                                            day: '2-digit', month: 'short', year: 'numeric',
                                                            hour: '2-digit', minute: '2-digit',
                                                        })}
                                                    </p>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                /* Desktop: Table view */
                                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="bg-gray-50 border-b border-gray-200">
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Sale #</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Customer</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Items</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Total</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Payment</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Notes</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tracking</th>
                                                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {sales.map(sale => {
                                                const displayStatus = getDisplayStatus(sale);
                                                const statusOption = STATUS_OPTIONS.find(o => o.value === displayStatus);
                                                const notes = sale.internal_notes || sale.customer_notes;
                                                return (
                                                    <tr
                                                        key={sale.id}
                                                        onClick={() => { setSelectedSale(sale); setShowDeleteConfirm(false); }}
                                                        className={`cursor-pointer hover:bg-gray-50 transition-colors ${
                                                            selectedSale?.id === sale.id ? 'bg-blue-50' : ''
                                                        }`}
                                                    >
                                                        <td className="px-4 py-3 text-sm font-medium text-blue-600">{sale.order_number}</td>
                                                        <td className="px-4 py-3 text-sm text-gray-900">
                                                            {sale.customer?.name || sale.customer_name || 'Walk-in'}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-600">{sale.items?.length || 0}</td>
                                                        <td className="px-4 py-3 text-sm font-semibold text-gray-900">
                                                            RM {parseFloat(sale.total_amount).toFixed(2)}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-600 capitalize">
                                                            {sale.payment_method?.replace('_', ' ')}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded-full ${statusOption?.color}`}>
                                                                {statusOption?.label}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 max-w-[150px]">
                                                            {notes ? (
                                                                <span className="truncate block" title={notes}>
                                                                    {notes.length > 25 ? notes.substring(0, 25) + '...' : notes}
                                                                </span>
                                                            ) : (
                                                                <span className="text-gray-300">&mdash;</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500">
                                                            {sale.tracking_id ? (
                                                                <span className="text-gray-700 font-medium">{sale.tracking_id}</span>
                                                            ) : (
                                                                <span className="text-gray-300">&mdash;</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500">
                                                            {sale.order_date ? new Date(sale.order_date).toLocaleDateString('en-MY', {
                                                                day: '2-digit', month: 'short', year: 'numeric',
                                                                hour: '2-digit', minute: '2-digit',
                                                            }) : '-'}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {hasMore && (
                                <div className="mt-4 text-center">
                                    <button
                                        onClick={() => { const next = page + 1; setPage(next); fetchSales(search, next, currentFilters); }}
                                        disabled={loading}
                                        className="px-4 py-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        {loading ? 'Loading...' : 'Load More'}
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Sale Detail Panel - Desktop: side panel, Mobile: full-screen overlay */}
                        {selectedSale && !isMobile && (
                            <div className="w-80 shrink-0">
                                <div className="bg-white rounded-xl border border-gray-200 p-5 sticky top-0">
                                    <h3 className="font-semibold text-gray-900 mb-4">{selectedSale.order_number}</h3>
                                    {renderSaleDetail()}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Mobile: Full-screen sale detail overlay */}
            {selectedSale && isMobile && (
                <div className="fixed inset-0 z-50 bg-white flex flex-col">
                    <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
                        <h3 className="font-semibold text-gray-900">{selectedSale.order_number}</h3>
                        <button
                            onClick={() => setSelectedSale(null)}
                            className="p-2 -mr-2 text-gray-400 hover:text-gray-600 active:text-gray-800"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div className="flex-1 overflow-y-auto p-4">
                        {renderSaleDetail()}
                    </div>
                </div>
            )}
        </div>
    );
}

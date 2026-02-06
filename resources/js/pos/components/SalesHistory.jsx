import React, { useState, useEffect } from 'react';
import { saleApi } from '../services/api';

export default function SalesHistory({ onBack }) {
    const [sales, setSales] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [selectedSale, setSelectedSale] = useState(null);

    const fetchSales = async (searchTerm, pageNum) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: 15 };
            if (searchTerm) params.search = searchTerm;

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

    useEffect(() => {
        setPage(1);
        const timer = setTimeout(() => fetchSales(search, 1), 300);
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <div className="h-full flex flex-col bg-gray-50">
            {/* Header */}
            <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-3">
                    <button onClick={onBack} className="p-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <h1 className="text-lg font-semibold text-gray-900">Sales History</h1>
                </div>
                <div className="relative w-72">
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
            </header>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-6">
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
                        <div className="flex-1">
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
                                            <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {sales.map(sale => (
                                            <tr
                                                key={sale.id}
                                                onClick={() => setSelectedSale(sale)}
                                                className={`cursor-pointer hover:bg-gray-50 transition-colors ${
                                                    selectedSale?.id === sale.id ? 'bg-blue-50' : ''
                                                }`}
                                            >
                                                <td className="px-4 py-3 text-sm font-medium text-blue-600">{sale.sale_number}</td>
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
                                                    <span className={`inline-flex px-2 py-0.5 text-xs font-medium rounded-full ${
                                                        sale.payment_status === 'paid'
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-yellow-100 text-yellow-700'
                                                    }`}>
                                                        {sale.payment_status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-gray-500">
                                                    {new Date(sale.sale_date).toLocaleDateString('en-MY', {
                                                        day: '2-digit', month: 'short', year: 'numeric',
                                                        hour: '2-digit', minute: '2-digit',
                                                    })}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {hasMore && (
                                <div className="mt-4 text-center">
                                    <button
                                        onClick={() => { const next = page + 1; setPage(next); fetchSales(search, next); }}
                                        disabled={loading}
                                        className="px-4 py-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        {loading ? 'Loading...' : 'Load More'}
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Sale Detail Panel */}
                        {selectedSale && (
                            <div className="w-80 shrink-0">
                                <div className="bg-white rounded-xl border border-gray-200 p-5 sticky top-0">
                                    <h3 className="font-semibold text-gray-900 mb-4">{selectedSale.sale_number}</h3>
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-xs text-gray-500">Customer</p>
                                            <p className="text-sm font-medium text-gray-900">
                                                {selectedSale.customer?.name || selectedSale.customer_name || 'Walk-in'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-gray-500">Salesperson</p>
                                            <p className="text-sm font-medium text-gray-900">{selectedSale.salesperson?.name}</p>
                                        </div>
                                        <div className="border-t border-gray-100 pt-3">
                                            <p className="text-xs text-gray-500 mb-2">Items</p>
                                            {selectedSale.items?.map((item, i) => (
                                                <div key={i} className="flex justify-between py-1">
                                                    <span className="text-sm text-gray-700">
                                                        {item.item_name} {item.variant_name ? `(${item.variant_name})` : ''} x{item.quantity}
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
                                        {selectedSale.notes && (
                                            <div className="pt-2">
                                                <p className="text-xs text-gray-500">Notes</p>
                                                <p className="text-sm text-gray-700 mt-1">{selectedSale.notes}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

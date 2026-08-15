/**
 * Studio Products — every product offered across all visible funnels,
 * with search/type filters and a jump into the owning funnel.
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const fmtRM = (value) => `RM ${Number(value || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;

const TYPE_BADGES = {
    main: 'bg-orange-100 text-orange-800',
    bump: 'bg-yellow-100 text-yellow-800',
    upsell: 'bg-green-100 text-green-800',
    downsell: 'bg-blue-100 text-blue-800',
};

export default function StudioProducts({ onSelectFunnel }) {
    const [products, setProducts] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const debounceRef = useRef(null);

    const loadProducts = useCallback(async (params) => {
        setLoading(true);
        try {
            const qs = new URLSearchParams(
                Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))
            ).toString();
            const response = await fetch(`/api/v1/studio/products?${qs}`, {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setProducts(data.data || []);
            setMeta(data.meta || null);
        } catch (e) {
            setProducts([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            loadProducts({ search, type, page, per_page: 24 });
        }, search ? 300 : 0);
        return () => clearTimeout(debounceRef.current);
    }, [search, type, page, loadProducts]);

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="mb-6">
                <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                    Funnel <span className="fs-gradient-text">Products</span>
                </h1>
                <p className="mt-0.5 text-[13px] text-gray-500">
                    {meta ? `${meta.total.toLocaleString()} products across all funnels` : 'Loading products...'}
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
                    placeholder="Search products..."
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
                    <option value="main">Main Offer</option>
                    <option value="bump">Order Bump</option>
                    <option value="upsell">Upsell</option>
                    <option value="downsell">Downsell</option>
                </select>
            </div>

            {/* Grid */}
            {loading ? (
                <div className="py-20 text-center text-gray-500">Loading products...</div>
            ) : products.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-12 text-center">
                    <h3 className="mb-1 text-lg font-medium text-gray-900">No products found</h3>
                    <p className="text-gray-500">Products you attach to funnel steps will appear here.</p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {products.map((product) => (
                        <div key={product.id} className="flex flex-col rounded-lg border border-gray-200 bg-white p-4">
                            <div className="flex items-start gap-3">
                                {product.image_url ? (
                                    <img
                                        src={product.image_url}
                                        alt={product.name}
                                        className="h-14 w-14 shrink-0 rounded-lg border border-gray-200 object-cover"
                                    />
                                ) : (
                                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                                        <svg className="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" strokeWidth={1.6} viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="line-clamp-2 font-medium text-gray-900">{product.name}</p>
                                    <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ${TYPE_BADGES[product.type] || 'bg-gray-100 text-gray-600'}`}>
                                            {product.type}
                                        </span>
                                        {product.is_popular && (
                                            <span className="rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-800">Popular</span>
                                        )}
                                        {product.is_recurring && (
                                            <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-800">Recurring</span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-3 flex items-baseline gap-2">
                                <span className="text-lg font-bold text-gray-900">{fmtRM(product.price)}</span>
                                {product.compare_at_price != null && product.compare_at_price > product.price && (
                                    <span className="text-sm text-gray-400 line-through">{fmtRM(product.compare_at_price)}</span>
                                )}
                            </div>

                            <button
                                onClick={() => onSelectFunnel?.({ uuid: product.funnel_uuid })}
                                className="mt-3 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-left transition-colors hover:bg-gray-100 cursor-pointer"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium text-gray-700">{product.funnel_name}</span>
                                    <span className="block text-xs text-gray-500">{product.step_name}</span>
                                </span>
                                <svg className="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
                <div className="mt-5 flex items-center justify-between text-sm text-gray-500">
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
        </div>
    );
}

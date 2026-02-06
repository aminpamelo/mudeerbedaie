import React, { useState, useEffect, useCallback } from 'react';
import { productApi, packageApi, courseApi } from '../services/api';

const TABS = [
    { key: 'products', label: 'Products' },
    { key: 'packages', label: 'Packages' },
    { key: 'courses', label: 'Courses' },
];

export default function ProductSearch({ onProductClick, onPackageClick, onCourseClick }) {
    const [activeTab, setActiveTab] = useState('products');
    const [search, setSearch] = useState('');
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);

    const fetchItems = useCallback(async (searchTerm, pageNum, tab) => {
        setLoading(true);
        try {
            const params = { page: pageNum };
            if (searchTerm) params.search = searchTerm;

            let response;
            if (tab === 'products') {
                response = await productApi.list(params);
            } else if (tab === 'packages') {
                response = await packageApi.list(params);
            } else {
                response = await courseApi.list(params);
            }

            const data = response.data || [];
            if (pageNum === 1) {
                setItems(data);
            } else {
                setItems(prev => [...prev, ...data]);
            }
            setHasMore(response.next_page_url !== null);
        } catch (err) {
            console.error('Failed to fetch items:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        setPage(1);
        setItems([]);
        const timer = setTimeout(() => {
            fetchItems(search, 1, activeTab);
        }, 300);
        return () => clearTimeout(timer);
    }, [search, activeTab, fetchItems]);

    const loadMore = () => {
        const nextPage = page + 1;
        setPage(nextPage);
        fetchItems(search, nextPage, activeTab);
    };

    const handleItemClick = (item) => {
        if (activeTab === 'products') onProductClick(item);
        else if (activeTab === 'packages') onPackageClick(item);
        else onCourseClick(item);
    };

    return (
        <div className="h-full flex flex-col p-4">
            {/* Search */}
            <div className="mb-4 shrink-0">
                <div className="relative">
                    <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={`Search ${activeTab}...`}
                        className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                    />
                </div>
            </div>

            {/* Tabs */}
            <div className="flex gap-1 mb-4 shrink-0 bg-gray-100 rounded-lg p-1">
                {TABS.map(tab => (
                    <button
                        key={tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={`flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                            activeTab === tab.key
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Items Grid */}
            <div className="flex-1 overflow-y-auto pos-scroll">
                {loading && items.length === 0 ? (
                    <div className="flex items-center justify-center h-40">
                        <div className="animate-spin w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full" />
                    </div>
                ) : items.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-40 text-gray-400">
                        <svg className="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p className="text-sm">No {activeTab} found</p>
                    </div>
                ) : (
                    <>
                        <div className="pos-grid">
                            {items.map(item => (
                                <ItemCard
                                    key={item.id}
                                    item={item}
                                    type={activeTab}
                                    onClick={() => handleItemClick(item)}
                                />
                            ))}
                        </div>
                        {hasMore && (
                            <div className="mt-4 text-center">
                                <button
                                    onClick={loadMore}
                                    disabled={loading}
                                    className="px-4 py-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    {loading ? 'Loading...' : 'Load More'}
                                </button>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

function ItemCard({ item, type, onClick }) {
    const getPrice = () => {
        if (type === 'products') return item.base_price;
        if (type === 'packages') return item.price;
        if (type === 'courses') return item.price || '0.00';
        return '0.00';
    };

    const getSubtext = () => {
        if (type === 'products' && item.variants?.length > 0) {
            return `${item.variants.length} variant${item.variants.length > 1 ? 's' : ''}`;
        }
        if (type === 'packages' && item.items?.length > 0) {
            return `${item.items.length} item${item.items.length > 1 ? 's' : ''}`;
        }
        if (type === 'courses') {
            return item.code || '';
        }
        return item.category?.name || '';
    };

    const getTypeIcon = () => {
        if (type === 'packages') return '📦';
        if (type === 'courses') return '📚';
        return null;
    };

    return (
        <button
            onClick={onClick}
            className="bg-white border border-gray-200 rounded-xl p-3 text-left hover:border-blue-400 hover:shadow-md transition-all group"
        >
            {/* Image or Placeholder */}
            <div className="w-full aspect-square bg-gray-100 rounded-lg mb-2 flex items-center justify-center overflow-hidden">
                {item.primary_image?.url ? (
                    <img
                        src={item.primary_image.url}
                        alt={item.name}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    <span className="text-2xl">{getTypeIcon() || '🏷️'}</span>
                )}
            </div>

            {/* Info */}
            <p className="text-sm font-medium text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                {item.name}
            </p>
            <p className="text-xs text-gray-500 truncate mt-0.5">{getSubtext()}</p>
            <p className="text-sm font-semibold text-blue-600 mt-1">
                RM {parseFloat(getPrice()).toFixed(2)}
            </p>
        </button>
    );
}

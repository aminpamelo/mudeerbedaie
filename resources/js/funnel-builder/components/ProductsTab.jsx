/**
 * Products Tab Component
 * Manages products attached to funnel steps
 */

import React, { useState, useEffect, useCallback } from 'react';
import { productApi, orderBumpApi, stepApi } from '../services/api';

export default function ProductsTab({ funnelUuid, showToast }) {
    const [steps, setSteps] = useState([]);
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showBumpModal, setShowBumpModal] = useState(false);
    const [selectedStep, setSelectedStep] = useState(null);
    const [editingProduct, setEditingProduct] = useState(null);
    const [editingBump, setEditingBump] = useState(null);

    // Load steps and products
    const loadData = useCallback(async () => {
        setLoading(true);
        try {
            const [stepsResponse, productsResponse] = await Promise.all([
                stepApi.list(funnelUuid),
                productApi.listAll(funnelUuid),
            ]);
            setSteps(stepsResponse.data || []);
            setProducts(productsResponse.data || []);
        } catch (err) {
            setError(err.message || 'Failed to load data');
        } finally {
            setLoading(false);
        }
    }, [funnelUuid]);

    useEffect(() => {
        loadData();
    }, [loadData]);

    // Group products by step
    const getProductsForStep = (stepId) => {
        return products.filter(p => p.funnel_step_id === stepId);
    };

    // Handle adding product
    const handleAddProduct = (step) => {
        setSelectedStep(step);
        setEditingProduct(null);
        setShowAddModal(true);
    };

    // Handle editing product
    const handleEditProduct = (step, product) => {
        setSelectedStep(step);
        setEditingProduct(product);
        setShowAddModal(true);
    };

    // Handle deleting product
    const handleDeleteProduct = async (step, product) => {
        if (!window.confirm('Are you sure you want to remove this product?')) {
            return;
        }

        try {
            await productApi.delete(funnelUuid, step.id, product.id);
            showToast?.('Product removed successfully');
            loadData();
        } catch (err) {
            showToast?.(err.message || 'Failed to remove product', 'error');
        }
    };

    // Handle adding order bump
    const handleAddBump = (step) => {
        setSelectedStep(step);
        setEditingBump(null);
        setShowBumpModal(true);
    };

    // Handle editing order bump
    const handleEditBump = (step, bump) => {
        setSelectedStep(step);
        setEditingBump(bump);
        setShowBumpModal(true);
    };

    // Handle deleting order bump
    const handleDeleteBump = async (step, bump) => {
        if (!window.confirm('Are you sure you want to remove this order bump?')) {
            return;
        }

        try {
            await orderBumpApi.delete(funnelUuid, step.id, bump.id);
            showToast?.('Order bump removed successfully');
            loadData();
        } catch (err) {
            showToast?.(err.message || 'Failed to remove order bump', 'error');
        }
    };

    // Product saved callback
    const handleProductSaved = () => {
        setShowAddModal(false);
        setSelectedStep(null);
        setEditingProduct(null);
        loadData();
    };

    // Order bump saved callback
    const handleBumpSaved = () => {
        setShowBumpModal(false);
        setSelectedStep(null);
        setEditingBump(null);
        loadData();
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {error}
                <button onClick={() => setError(null)} className="float-right font-bold">&times;</button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Summary Card */}
            <div className="bg-white rounded-lg border border-gray-200 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h3 className="font-medium text-gray-900">Products Overview</h3>
                        <p className="text-sm text-gray-500 mt-1">
                            {products.length} product{products.length !== 1 ? 's' : ''} attached to {steps.length} step{steps.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-4 text-sm">
                        <div className="flex items-center gap-2">
                            <span className="w-3 h-3 rounded-full bg-blue-500"></span>
                            <span className="text-gray-600">Main</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="w-3 h-3 rounded-full bg-green-500"></span>
                            <span className="text-gray-600">Upsell</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="w-3 h-3 rounded-full bg-orange-500"></span>
                            <span className="text-gray-600">Downsell</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Steps with Products */}
            {steps.length === 0 ? (
                <div className="text-center py-12 bg-gray-50 rounded-lg">
                    <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h3 className="mt-2 text-sm font-medium text-gray-900">No steps yet</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Create steps in your funnel first, then add products.
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    {steps.map((step) => (
                        <StepProductCard
                            key={step.id}
                            step={step}
                            products={getProductsForStep(step.id)}
                            onAddProduct={() => handleAddProduct(step)}
                            onEditProduct={(product) => handleEditProduct(step, product)}
                            onDeleteProduct={(product) => handleDeleteProduct(step, product)}
                            onAddBump={() => handleAddBump(step)}
                            onEditBump={(bump) => handleEditBump(step, bump)}
                            onDeleteBump={(bump) => handleDeleteBump(step, bump)}
                        />
                    ))}
                </div>
            )}

            {/* Add/Edit Product Modal */}
            {showAddModal && (
                <AddProductModal
                    funnelUuid={funnelUuid}
                    step={selectedStep}
                    product={editingProduct}
                    onClose={() => {
                        setShowAddModal(false);
                        setSelectedStep(null);
                        setEditingProduct(null);
                    }}
                    onSaved={handleProductSaved}
                    showToast={showToast}
                />
            )}

            {/* Add/Edit Order Bump Modal */}
            {showBumpModal && (
                <AddOrderBumpModal
                    funnelUuid={funnelUuid}
                    step={selectedStep}
                    bump={editingBump}
                    onClose={() => {
                        setShowBumpModal(false);
                        setSelectedStep(null);
                        setEditingBump(null);
                    }}
                    onSaved={handleBumpSaved}
                    showToast={showToast}
                />
            )}
        </div>
    );
}

// Step Product Card Component
function StepProductCard({ step, products, onAddProduct, onEditProduct, onDeleteProduct, onAddBump, onEditBump, onDeleteBump }) {
    const isCheckoutStep = step.type === 'checkout' || step.type === 'sales';
    const orderBumps = step.order_bumps || [];

    return (
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
            {/* Step Header */}
            <div className="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <span className={`px-2 py-1 rounded text-xs font-medium ${getStepTypeColor(step.type)}`}>
                        {step.type}
                    </span>
                    <h3 className="font-medium text-gray-900">{step.name}</h3>
                </div>
                <button
                    onClick={onAddProduct}
                    className="px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg flex items-center gap-1"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Add Product
                </button>
            </div>

            {/* Products List */}
            <div className="divide-y divide-gray-100">
                {products.length === 0 ? (
                    <div className="px-4 py-8 text-center text-gray-500">
                        <p className="text-sm">No products attached to this step</p>
                    </div>
                ) : (
                    products.map((product) => (
                        <ProductCard
                            key={product.id}
                            product={product}
                            onEdit={() => onEditProduct(product)}
                            onDelete={() => onDeleteProduct(product)}
                        />
                    ))
                )}
            </div>

            {/* Order Bumps Section (for checkout steps) */}
            {isCheckoutStep && (
                <div className="border-t border-gray-200">
                    <div className="px-4 py-3 bg-yellow-50 border-b border-yellow-100 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <svg className="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span className="font-medium text-yellow-800">Order Bumps</span>
                            <span className="text-xs text-yellow-600">({orderBumps.length})</span>
                        </div>
                        <button
                            onClick={onAddBump}
                            className="px-3 py-1.5 text-sm font-medium text-yellow-700 hover:bg-yellow-100 rounded-lg flex items-center gap-1"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Add Bump
                        </button>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {orderBumps.length === 0 ? (
                            <div className="px-4 py-4 text-center text-gray-500">
                                <p className="text-sm">No order bumps. Add one-click upsells at checkout.</p>
                            </div>
                        ) : (
                            orderBumps.map((bump) => (
                                <OrderBumpCard
                                    key={bump.id}
                                    bump={bump}
                                    onEdit={() => onEditBump(bump)}
                                    onDelete={() => onDeleteBump(bump)}
                                />
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// Product Card Component
function ProductCard({ product, onEdit, onDelete }) {
    return (
        <div className="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
            <div className="flex items-center gap-4">
                {/* Product Image */}
                <div className="w-12 h-12 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                    {product.image_url ? (
                        <img
                            src={product.image_url}
                            alt={product.name}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center text-gray-400">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    )}
                </div>

                {/* Product Info */}
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-900">{product.name}</span>
                        <span className={`px-2 py-0.5 rounded text-xs font-medium ${getProductTypeColor(product.type)}`}>
                            {product.type}
                        </span>
                        {product.is_course && (
                            <span className="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                Course
                            </span>
                        )}
                        {product.is_package && (
                            <span className="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                Package
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-3 mt-1">
                        <span className="font-bold text-gray-900">{product.formatted_price}</span>
                        {product.has_discount && (
                            <span className="text-sm text-gray-400 line-through">{product.formatted_compare_at_price}</span>
                        )}
                        {product.has_discount && (
                            <span className="text-xs text-green-600 font-medium">-{product.discount_percentage}%</span>
                        )}
                        {product.is_recurring && (
                            <span className="text-xs text-gray-500">/{product.billing_interval}</span>
                        )}
                    </div>
                </div>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-2">
                <button
                    onClick={onEdit}
                    className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                    title="Edit"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    onClick={onDelete}
                    className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                    title="Remove"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>
    );
}

// Order Bump Card Component
function OrderBumpCard({ bump, onEdit, onDelete }) {
    return (
        <div className="px-4 py-3 flex items-center justify-between hover:bg-yellow-50/50">
            <div className="flex items-center gap-4">
                {/* Bump Image */}
                <div className="w-10 h-10 bg-yellow-100 rounded-lg overflow-hidden flex-shrink-0">
                    {bump.image_url ? (
                        <img
                            src={bump.image_url}
                            alt={bump.name}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center text-yellow-500">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    )}
                </div>

                {/* Bump Info */}
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-900">{bump.headline}</span>
                        {!bump.is_active && (
                            <span className="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                Inactive
                            </span>
                        )}
                        {bump.is_checked_by_default && (
                            <span className="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                Pre-checked
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-3 mt-0.5">
                        <span className="font-bold text-gray-900">{bump.formatted_price}</span>
                        {bump.has_discount && (
                            <span className="text-sm text-gray-400 line-through">{bump.formatted_compare_at_price}</span>
                        )}
                    </div>
                </div>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-2">
                <button
                    onClick={onEdit}
                    className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                    title="Edit"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    onClick={onDelete}
                    className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                    title="Remove"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>
    );
}

// Add/Edit Product Modal
function AddProductModal({ funnelUuid, step, product, onClose, onSaved, showToast }) {
    const [searchTab, setSearchTab] = useState('products');
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);
    const [saving, setSaving] = useState(false);

    const [form, setForm] = useState({
        type: product?.type || 'main',
        name: product?.name || '',
        description: product?.description || '',
        funnel_price: product?.funnel_price || '',
        compare_at_price: product?.compare_at_price || '',
        is_recurring: product?.is_recurring || false,
        billing_interval: product?.billing_interval || 'monthly',
    });

    // Search products/courses/packages
    const handleSearch = async () => {
        if (!searchQuery.trim()) {
            setSearchResults([]);
            return;
        }

        setSearching(true);
        try {
            let response;
            if (searchTab === 'products') {
                response = await productApi.search(searchQuery);
            } else if (searchTab === 'courses') {
                response = await productApi.searchCourses(searchQuery);
            } else {
                response = await productApi.searchPackages(searchQuery);
            }
            setSearchResults(response.data || []);
        } catch (err) {
            console.error('Search failed:', err);
            setSearchResults([]);
        } finally {
            setSearching(false);
        }
    };

    // Debounced search
    useEffect(() => {
        const timer = setTimeout(handleSearch, 300);
        return () => clearTimeout(timer);
    }, [searchQuery, searchTab]);

    // Select item from search results
    const handleSelectItem = (item) => {
        setSelectedItem({
            ...item,
            source: searchTab,
        });
        setForm(prev => ({
            ...prev,
            name: item.name,
            funnel_price: item.price || '',
            compare_at_price: searchTab === 'packages' && item.original_price > item.price ? item.original_price : '',
            is_recurring: searchTab === 'packages' ? false : prev.is_recurring,
        }));
    };

    // Save product
    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);

        try {
            const data = {
                type: form.type,
                name: form.name,
                description: form.description || null,
                funnel_price: parseFloat(form.funnel_price) || 0,
                compare_at_price: form.compare_at_price ? parseFloat(form.compare_at_price) : null,
                is_recurring: form.is_recurring,
                billing_interval: form.is_recurring ? form.billing_interval : null,
            };

            if (selectedItem) {
                if (selectedItem.source === 'products') {
                    data.product_id = selectedItem.id;
                } else if (selectedItem.source === 'courses') {
                    data.course_id = selectedItem.id;
                } else if (selectedItem.source === 'packages') {
                    data.package_id = selectedItem.id;
                }
            } else if (product) {
                // Keep existing product/course/package reference when editing
                data.product_id = product.product_id;
                data.course_id = product.course_id;
                data.package_id = product.source_package?.id || null;
            }

            if (product) {
                // Update existing
                await productApi.update(funnelUuid, step.id, product.id, data);
                showToast?.('Product updated successfully');
            } else {
                // Create new
                if (!selectedItem && !product) {
                    showToast?.('Please select a product or course', 'error');
                    setSaving(false);
                    return;
                }
                await productApi.create(funnelUuid, step.id, data);
                showToast?.('Product added successfully');
            }

            onSaved();
        } catch (err) {
            showToast?.(err.message || 'Failed to save product', 'error');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-xl font-bold text-gray-900">
                            {product ? 'Edit Product' : 'Add Product'}
                        </h2>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <p className="text-sm text-gray-500 mb-6">
                        Adding to: <span className="font-medium">{step.name}</span>
                    </p>

                    <form onSubmit={handleSubmit}>
                        {/* Search Section (only for new products) */}
                        {!product && (
                            <div className="mb-6">
                                <div className="flex gap-2 mb-3">
                                    <button
                                        type="button"
                                        onClick={() => { setSearchTab('products'); setSearchResults([]); setSelectedItem(null); }}
                                        className={`px-4 py-2 text-sm font-medium rounded-lg ${
                                            searchTab === 'products'
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        Products
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setSearchTab('courses'); setSearchResults([]); setSelectedItem(null); }}
                                        className={`px-4 py-2 text-sm font-medium rounded-lg ${
                                            searchTab === 'courses'
                                                ? 'bg-purple-100 text-purple-700'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        Courses
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setSearchTab('packages'); setSearchResults([]); setSelectedItem(null); }}
                                        className={`px-4 py-2 text-sm font-medium rounded-lg ${
                                            searchTab === 'packages'
                                                ? 'bg-green-100 text-green-700'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        Packages
                                    </button>
                                </div>

                                <div className="relative">
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder={`Search ${searchTab}...`}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    {searching && (
                                        <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                        </div>
                                    )}
                                </div>

                                {/* Search Results */}
                                {searchResults.length > 0 && (
                                    <div className="mt-2 border border-gray-200 rounded-lg max-h-48 overflow-y-auto">
                                        {searchResults.map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() => handleSelectItem(item)}
                                                className={`w-full px-4 py-3 text-left hover:bg-gray-50 flex items-center justify-between ${
                                                    selectedItem?.id === item.id && selectedItem?.source === searchTab ? 'bg-blue-50' : ''
                                                }`}
                                            >
                                                <div>
                                                    <p className="font-medium text-gray-900">{item.name}</p>
                                                    <div className="flex items-center gap-2">
                                                        <p className="text-sm text-gray-500">{item.formatted_price}</p>
                                                        {searchTab === 'packages' && item.item_count > 0 && (
                                                            <span className="text-xs text-gray-400">
                                                                ({item.item_count} item{item.item_count !== 1 ? 's' : ''})
                                                            </span>
                                                        )}
                                                        {searchTab === 'packages' && item.savings > 0 && (
                                                            <span className="text-xs text-green-600 font-medium">
                                                                Save RM {parseFloat(item.savings).toFixed(2)}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                {selectedItem?.id === item.id && selectedItem?.source === searchTab && (
                                                    <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                                    </svg>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {/* Selected Item Display */}
                                {selectedItem && (
                                    <div className="mt-3 p-3 bg-gray-50 rounded-lg">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                    selectedItem.source === 'courses' ? 'bg-purple-100 text-purple-700'
                                                    : selectedItem.source === 'packages' ? 'bg-green-100 text-green-700'
                                                    : 'bg-blue-100 text-blue-700'
                                                }`}>
                                                    {selectedItem.source === 'courses' ? 'Course' : selectedItem.source === 'packages' ? 'Package' : 'Product'}
                                                </span>
                                                <span className="font-medium">{selectedItem.name}</span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => setSelectedItem(null)}
                                                className="text-gray-400 hover:text-gray-600"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        {/* Package items preview */}
                                        {selectedItem.source === 'packages' && selectedItem.items && selectedItem.items.length > 0 && (
                                            <div className="mt-2 pl-3 border-l-2 border-green-200">
                                                <p className="text-xs text-gray-500 mb-1">Package contains:</p>
                                                {selectedItem.items.map((pkgItem, idx) => (
                                                    <div key={idx} className="text-xs text-gray-600 flex items-center gap-1">
                                                        <span className={`w-1.5 h-1.5 rounded-full ${pkgItem.type === 'course' ? 'bg-purple-400' : 'bg-blue-400'}`}></span>
                                                        {pkgItem.quantity > 1 ? `${pkgItem.quantity}x ` : ''}{pkgItem.name}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Product Type (hidden for packages - always main) */}
                        {!(selectedItem?.source === 'packages') && (
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Product Type
                                </label>
                                <select
                                    value={form.type}
                                    onChange={(e) => setForm({ ...form, type: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="main">Main Product</option>
                                    <option value="upsell">Upsell</option>
                                    <option value="downsell">Downsell</option>
                                </select>
                            </div>
                        )}

                        {/* Custom Name */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Display Name <span className="text-gray-400">(optional override)</span>
                            </label>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        {/* Pricing */}
                        <div className="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Funnel Price (RM)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.funnel_price}
                                    onChange={(e) => setForm({ ...form, funnel_price: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Compare at Price <span className="text-gray-400">(optional)</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.compare_at_price}
                                    onChange={(e) => setForm({ ...form, compare_at_price: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                        </div>

                        {/* Recurring Option (hidden for packages) */}
                        {!(selectedItem?.source === 'packages') && (
                            <div className="mb-6">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.is_recurring}
                                        onChange={(e) => setForm({ ...form, is_recurring: e.target.checked })}
                                        className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-700">Recurring payment</span>
                                </label>

                                {form.is_recurring && (
                                    <div className="mt-3 ml-6">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Billing Interval
                                        </label>
                                        <select
                                            value={form.billing_interval}
                                            onChange={(e) => setForm({ ...form, billing_interval: e.target.value })}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={saving || (!product && !selectedItem)}
                                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                {saving ? 'Saving...' : (product ? 'Update Product' : 'Add Product')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// Add/Edit Order Bump Modal
function AddOrderBumpModal({ funnelUuid, step, bump, onClose, onSaved, showToast }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);
    const [saving, setSaving] = useState(false);

    const [form, setForm] = useState({
        headline: bump?.headline || '',
        description: bump?.description || '',
        price: bump?.price || '',
        compare_at_price: bump?.compare_at_price || '',
        is_active: bump?.is_active ?? true,
        is_checked_by_default: bump?.is_checked_by_default ?? false,
    });

    // Search products
    const handleSearch = async () => {
        if (!searchQuery.trim()) {
            setSearchResults([]);
            return;
        }

        setSearching(true);
        try {
            const response = await productApi.search(searchQuery);
            setSearchResults(response.data || []);
        } catch (err) {
            console.error('Search failed:', err);
            setSearchResults([]);
        } finally {
            setSearching(false);
        }
    };

    // Debounced search
    useEffect(() => {
        const timer = setTimeout(handleSearch, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    // Select item from search results
    const handleSelectItem = (item) => {
        setSelectedItem(item);
        setForm(prev => ({
            ...prev,
            headline: `Add ${item.name}!`,
            price: item.price || '',
        }));
    };

    // Save order bump
    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);

        try {
            const data = {
                headline: form.headline,
                description: form.description,
                price: parseFloat(form.price) || 0,
                compare_at_price: form.compare_at_price ? parseFloat(form.compare_at_price) : null,
                is_active: form.is_active,
                is_checked_by_default: form.is_checked_by_default,
            };

            if (selectedItem) {
                data.product_id = selectedItem.id;
            } else if (bump) {
                data.product_id = bump.product_id;
                data.course_id = bump.course_id;
            }

            if (bump) {
                await orderBumpApi.update(funnelUuid, step.id, bump.id, data);
                showToast?.('Order bump updated successfully');
            } else {
                await orderBumpApi.create(funnelUuid, step.id, data);
                showToast?.('Order bump added successfully');
            }

            onSaved();
        } catch (err) {
            showToast?.(err.message || 'Failed to save order bump', 'error');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-xl font-bold text-gray-900">
                            {bump ? 'Edit Order Bump' : 'Add Order Bump'}
                        </h2>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={handleSubmit}>
                        {/* Search Section (only for new bumps) */}
                        {!bump && (
                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Link to Product (optional)
                                </label>
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Search products..."
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />

                                {/* Search Results */}
                                {searchResults.length > 0 && (
                                    <div className="mt-2 border border-gray-200 rounded-lg max-h-32 overflow-y-auto">
                                        {searchResults.map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() => handleSelectItem(item)}
                                                className={`w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center justify-between ${
                                                    selectedItem?.id === item.id ? 'bg-blue-50' : ''
                                                }`}
                                            >
                                                <span className="font-medium">{item.name}</span>
                                                <span className="text-sm text-gray-500">{item.formatted_price}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {selectedItem && (
                                    <div className="mt-2 p-2 bg-blue-50 rounded-lg text-sm flex items-center justify-between">
                                        <span>Selected: <strong>{selectedItem.name}</strong></span>
                                        <button type="button" onClick={() => setSelectedItem(null)} className="text-blue-600 hover:text-blue-800">
                                            Clear
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Headline */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Headline
                            </label>
                            <input
                                type="text"
                                value={form.headline}
                                onChange={(e) => setForm({ ...form, headline: e.target.value })}
                                placeholder="e.g., Add this special bonus!"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                            />
                        </div>

                        {/* Description */}
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Description
                            </label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                                rows={3}
                                placeholder="Describe what they'll get..."
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                            />
                        </div>

                        {/* Pricing */}
                        <div className="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Price (RM)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.price}
                                    onChange={(e) => setForm({ ...form, price: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Compare at
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.compare_at_price}
                                    onChange={(e) => setForm({ ...form, compare_at_price: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                        </div>

                        {/* Options */}
                        <div className="mb-6 space-y-3">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.is_active}
                                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                                    className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <span className="text-sm text-gray-700">Active</span>
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.is_checked_by_default}
                                    onChange={(e) => setForm({ ...form, is_checked_by_default: e.target.checked })}
                                    className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <span className="text-sm text-gray-700">Pre-checked by default</span>
                            </label>
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end gap-3">
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
                                className="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                {saving ? 'Saving...' : (bump ? 'Update Bump' : 'Add Bump')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

// Helper functions
function getStepTypeColor(type) {
    const colors = {
        landing: 'bg-blue-100 text-blue-800',
        optin: 'bg-green-100 text-green-800',
        sales: 'bg-purple-100 text-purple-800',
        checkout: 'bg-orange-100 text-orange-800',
        upsell: 'bg-pink-100 text-pink-800',
        downsell: 'bg-yellow-100 text-yellow-800',
        thankyou: 'bg-teal-100 text-teal-800',
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
}

function getProductTypeColor(type) {
    const colors = {
        main: 'bg-blue-100 text-blue-800',
        upsell: 'bg-green-100 text-green-800',
        downsell: 'bg-orange-100 text-orange-800',
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
}

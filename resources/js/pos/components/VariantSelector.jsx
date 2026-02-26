import React from 'react';

export default function VariantSelector({ product, onSelect, onClose }) {
    const variants = product.variants || [];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4" onClick={e => e.stopPropagation()}>
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">Select Variant</h3>
                        <p className="text-sm text-gray-500">{product.name}</p>
                    </div>
                    <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-6 py-4 max-h-64 overflow-y-auto">
                    {variants.length === 0 ? (
                        <p className="text-sm text-gray-500 text-center py-8">No variants available</p>
                    ) : (
                        <div className="space-y-2">
                            {variants.map(variant => (
                                <button
                                    key={variant.id}
                                    onClick={() => onSelect(variant)}
                                    className="w-full px-4 py-3 text-left bg-gray-50 rounded-xl hover:bg-blue-50 hover:border-blue-200 border border-gray-100 transition-colors flex items-center justify-between"
                                >
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{variant.name}</p>
                                        {variant.sku && <p className="text-xs text-gray-500 mt-0.5">SKU: {variant.sku}</p>}
                                    </div>
                                    <p className="text-sm font-semibold text-blue-600">
                                        RM {parseFloat(variant.price || product.base_price).toFixed(2)}
                                    </p>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

import React from 'react';

export default function CartItem({ item, onUpdateQuantity, onRemove }) {
    const typeLabel = item.type === 'package' ? 'PKG' : item.type === 'course' ? 'CRS' : null;

    return (
        <div className="cart-item-enter bg-gray-50 rounded-lg p-3">
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5">
                        {typeLabel && (
                            <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${
                                item.type === 'package' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'
                            }`}>
                                {typeLabel}
                            </span>
                        )}
                        <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                    </div>
                    {item.variantName && (
                        <p className="text-xs text-gray-500 mt-0.5">{item.variantName}</p>
                    )}
                    <p className="text-xs text-gray-500 mt-0.5">
                        RM {parseFloat(item.unitPrice).toFixed(2)} each
                    </p>
                </div>
                <button
                    onClick={onRemove}
                    className="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div className="flex items-center justify-between mt-2">
                <div className="flex items-center gap-1">
                    <button
                        onClick={() => onUpdateQuantity(item.quantity - 1)}
                        className="w-7 h-7 flex items-center justify-center rounded-md bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 text-sm font-medium"
                    >
                        -
                    </button>
                    <span className="w-8 text-center text-sm font-medium">{item.quantity}</span>
                    <button
                        onClick={() => onUpdateQuantity(item.quantity + 1)}
                        className="w-7 h-7 flex items-center justify-center rounded-md bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 text-sm font-medium"
                    >
                        +
                    </button>
                </div>
                <p className="text-sm font-semibold text-gray-900">
                    RM {item.totalPrice.toFixed(2)}
                </p>
            </div>
        </div>
    );
}

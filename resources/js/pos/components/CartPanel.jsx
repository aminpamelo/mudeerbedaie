import React, { useState } from 'react';
import CartItem from './CartItem';
import CustomerSelect from './CustomerSelect';

export default function CartPanel({ cart, customer, onCustomerChange, onUpdateQuantity, onRemoveItem, onClearCart, onCharge, subtotal, discount, onDiscountChange, postage, onPostageChange }) {

    const [showCustomer, setShowCustomer] = useState(false);

    const discountValue = discount.type === 'percentage'
        ? (subtotal * discount.amount / 100)
        : discount.amount;

    const total = Math.max(0, subtotal - discountValue + (postage || 0));

    const canCharge = cart.length > 0 && customer !== null;

    const handleCharge = () => {
        if (!canCharge) return;
        onCharge({ discount, total });
    };

    return (
        <div className="h-full flex flex-col">
            {/* Cart Header */}
            <div className="px-3 py-2.5 border-b border-gray-100 flex items-center justify-between shrink-0">
                <h2 className="font-semibold text-gray-900">Cart</h2>
                {cart.length > 0 && (
                    <button
                        onClick={onClearCart}
                        className="text-xs text-red-500 hover:text-red-600 font-medium"
                    >
                        Clear All
                    </button>
                )}
            </div>

            {/* Collapsible Customer Section Header */}
            <div className="shrink-0 border-b border-gray-100">
                <button
                    type="button"
                    onClick={() => setShowCustomer(!showCustomer)}
                    className="w-full px-3 py-2 flex items-center justify-between text-left hover:bg-gray-50 transition-colors"
                >
                    <div className="flex items-center gap-2 min-w-0">
                        <svg className="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {customer ? (
                            <span className="text-sm font-medium text-gray-900 truncate">{customer.name}</span>
                        ) : (
                            <span className="text-sm text-gray-500">Customer Info</span>
                        )}
                    </div>
                    <svg className={`w-4 h-4 text-gray-400 shrink-0 transition-transform ${showCustomer ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            {/* Scrollable area: Customer Selection (collapsible) + Cart Items */}
            <div className="flex-1 overflow-y-auto pos-scroll">
                {/* Customer Selection */}
                {showCustomer && (
                    <div className="px-3 py-2.5 border-b border-gray-100">
                        <CustomerSelect customer={customer} onCustomerChange={onCustomerChange} postage={postage} />
                    </div>
                )}

                {/* Cart Items */}
                <div className="px-3 py-2">
                    {cart.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-10 text-gray-400">
                            <svg className="w-16 h-16 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                            </svg>
                            <p className="text-sm font-medium">Cart is empty</p>
                            <p className="text-xs mt-1">Add items to get started</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {cart.map(item => (
                                <CartItem
                                    key={item.key}
                                    item={item}
                                    onUpdateQuantity={(qty) => onUpdateQuantity(item.key, qty)}
                                    onRemove={() => onRemoveItem(item.key)}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Discount */}
            {cart.length > 0 && (
                <div className="px-3 py-2 border-t border-gray-100 shrink-0">
                    <div className="flex items-center gap-2">
                        <label className="text-xs font-medium text-gray-500 shrink-0">Discount</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={discount.amount || ''}
                            onChange={(e) => onDiscountChange({ ...discount, amount: parseFloat(e.target.value) || 0 })}
                            placeholder="0"
                            className="flex-1 px-2 py-1.5 border border-gray-200 rounded text-sm text-right outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <select
                            value={discount.type}
                            onChange={(e) => onDiscountChange({ ...discount, type: e.target.value })}
                            className="px-2 py-1.5 border border-gray-200 rounded text-sm outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="fixed">RM</option>
                            <option value="percentage">%</option>
                        </select>
                    </div>
                </div>
            )}

            {/* Postage / Delivery Cost */}
            {cart.length > 0 && (
                <div className="px-3 py-2 border-t border-gray-100 shrink-0">
                    <div className="flex items-center gap-2">
                        <label className="text-xs font-medium text-gray-500 shrink-0">Postage</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={postage || ''}
                            onChange={(e) => onPostageChange(parseFloat(e.target.value) || 0)}
                            placeholder="0"
                            className="flex-1 px-2 py-1.5 border border-gray-200 rounded text-sm text-right outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <span className="px-2 py-1.5 text-sm text-gray-500">RM</span>
                    </div>
                </div>
            )}

            {/* Totals & Charge */}
            <div className="px-3 py-2.5 border-t border-gray-200 bg-gray-50 shrink-0">
                <div className="space-y-1.5 mb-3">
                    <div className="flex justify-between text-sm text-gray-600">
                        <span>Subtotal</span>
                        <span>RM {subtotal.toFixed(2)}</span>
                    </div>
                    {discountValue > 0 && (
                        <div className="flex justify-between text-sm text-red-500">
                            <span>Discount</span>
                            <span>- RM {discountValue.toFixed(2)}</span>
                        </div>
                    )}
                    {postage > 0 && (
                        <div className="flex justify-between text-sm text-gray-600">
                            <span>Postage</span>
                            <span>+ RM {postage.toFixed(2)}</span>
                        </div>
                    )}
                    <div className="flex justify-between text-lg font-bold text-gray-900 pt-1 border-t border-gray-200">
                        <span>Total</span>
                        <span>RM {total.toFixed(2)}</span>
                    </div>
                </div>
                {cart.length > 0 && !customer && (
                    <p className="text-xs text-amber-600 text-center mb-2">
                        Please fill in customer info (name & phone required)
                    </p>
                )}
                <button
                    onClick={handleCharge}
                    disabled={!canCharge}
                    className="w-full py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors text-sm"
                >
                    {cart.length === 0 ? 'Add Items to Cart' : !customer ? 'Customer Info Required' : `Charge RM ${total.toFixed(2)}`}
                </button>
            </div>
        </div>
    );
}

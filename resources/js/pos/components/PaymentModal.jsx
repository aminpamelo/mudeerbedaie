import React, { useState } from 'react';
import { saleApi } from '../services/api';

export default function PaymentModal({ cart, customer, subtotal, onClose, onComplete }) {
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [paymentReference, setPaymentReference] = useState('');
    const [paymentStatus, setPaymentStatus] = useState('paid');
    const [discount, setDiscount] = useState({ amount: 0, type: 'fixed' });
    const [notes, setNotes] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const discountValue = discount.type === 'percentage'
        ? (subtotal * discount.amount / 100)
        : discount.amount;
    const total = Math.max(0, subtotal - discountValue);

    const handleSubmit = async () => {
        setLoading(true);
        setError(null);

        try {
            const payload = {
                payment_method: paymentMethod,
                payment_reference: paymentMethod === 'bank_transfer' ? paymentReference : null,
                payment_status: paymentStatus,
                notes: notes || null,
                items: cart.map(item => ({
                    itemable_type: item.type,
                    itemable_id: item.id,
                    product_variant_id: item.variantId || null,
                    class_id: item.classId || null,
                    quantity: item.quantity,
                    unit_price: item.unitPrice,
                })),
            };

            if (discount.amount > 0) {
                payload.discount_amount = discount.amount;
                payload.discount_type = discount.type;
            }

            if (customer) {
                if (customer.type === 'existing') {
                    payload.customer_id = customer.id;
                } else {
                    payload.customer_name = customer.name;
                    payload.customer_phone = customer.phone || null;
                    payload.customer_email = customer.email || null;
                    payload.customer_address = customer.address || null;
                }
            }

            const response = await saleApi.create(payload);
            onComplete(response.data);
        } catch (err) {
            setError(err.message || 'Failed to create sale');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" onClick={e => e.stopPropagation()}>
                {/* Header */}
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900">Complete Payment</h3>
                    <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="px-6 py-4 space-y-4">
                    {/* Total */}
                    <div className="text-center py-3 bg-blue-50 rounded-xl">
                        <p className="text-sm text-blue-600 font-medium">Total Amount</p>
                        <p className="text-3xl font-bold text-blue-700">RM {total.toFixed(2)}</p>
                    </div>

                    {/* Payment Method */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                onClick={() => setPaymentMethod('cash')}
                                className={`py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all ${
                                    paymentMethod === 'cash'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                💵 Cash
                            </button>
                            <button
                                onClick={() => setPaymentMethod('bank_transfer')}
                                className={`py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all ${
                                    paymentMethod === 'bank_transfer'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                🏦 Bank Transfer
                            </button>
                        </div>
                    </div>

                    {/* Bank Transfer Reference */}
                    {paymentMethod === 'bank_transfer' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Transfer Reference</label>
                            <input
                                type="text"
                                value={paymentReference}
                                onChange={(e) => setPaymentReference(e.target.value)}
                                placeholder="Enter reference number"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    )}

                    {/* Payment Status */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                onClick={() => setPaymentStatus('paid')}
                                className={`py-2 px-3 rounded-lg border text-sm font-medium transition-all ${
                                    paymentStatus === 'paid'
                                        ? 'border-green-500 bg-green-50 text-green-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                Paid
                            </button>
                            <button
                                onClick={() => setPaymentStatus('pending')}
                                className={`py-2 px-3 rounded-lg border text-sm font-medium transition-all ${
                                    paymentStatus === 'pending'
                                        ? 'border-yellow-500 bg-yellow-50 text-yellow-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                Pending
                            </button>
                        </div>
                    </div>

                    {/* Notes */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Add any notes..."
                            rows={2}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                        />
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p className="text-sm text-red-600">{error}</p>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="px-6 py-4 border-t border-gray-100 flex gap-3">
                    <button
                        onClick={onClose}
                        className="flex-1 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSubmit}
                        disabled={loading || (paymentMethod === 'bank_transfer' && !paymentReference)}
                        className="flex-1 py-2.5 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-sm transition-colors"
                    >
                        {loading ? 'Processing...' : `Confirm RM ${total.toFixed(2)}`}
                    </button>
                </div>
            </div>
        </div>
    );
}

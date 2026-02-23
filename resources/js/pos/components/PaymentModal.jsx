import React, { useState } from 'react';
import { saleApi } from '../services/api';

const MALAYSIAN_STATES = [
    'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
    'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
    'Sarawak', 'Selangor', 'Terengganu',
    'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
];

function formatAddress({ addressLine, city, postcode, state }) {
    const parts = [addressLine, city, postcode && state ? `${postcode} ${state}` : state || postcode].filter(Boolean);
    return parts.join(', ');
}

export default function PaymentModal({ cart, customer, subtotal, discount, postage, onClose, onComplete }) {
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [paymentReference, setPaymentReference] = useState('');
    const [paymentStatus, setPaymentStatus] = useState('paid');
    const [notes, setNotes] = useState('');
    const [receiptFile, setReceiptFile] = useState(null);
    const [receiptPreview, setReceiptPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [editingCustomer, setEditingCustomer] = useState(false);
    const [customerEdit, setCustomerEdit] = useState({
        name: customer?.name || '',
        phone: customer?.phone || '',
        email: customer?.email || '',
        addressLine: '',
        city: '',
        postcode: '',
        state: '',
    });

    const handleReceiptChange = (e) => {
        const file = e.target.files[0];
        if (!file) {
            setReceiptFile(null);
            setReceiptPreview(null);
            return;
        }
        setReceiptFile(file);
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (ev) => setReceiptPreview(ev.target.result);
            reader.readAsDataURL(file);
        } else {
            setReceiptPreview(null);
        }
    };

    const removeReceipt = () => {
        setReceiptFile(null);
        setReceiptPreview(null);
    };

    const discountValue = discount.type === 'percentage'
        ? (subtotal * discount.amount / 100)
        : discount.amount;
    const postageValue = postage || 0;
    const total = Math.max(0, subtotal - discountValue + postageValue);

    const editedAddress = formatAddress(customerEdit);
    const displayName = editingCustomer ? customerEdit.name : (customer?.name || 'Walk-in Customer');
    const displayPhone = editingCustomer ? customerEdit.phone : (customer?.phone || null);
    const displayEmail = editingCustomer ? customerEdit.email : (customer?.email || null);
    const displayAddress = editingCustomer ? (editedAddress || null) : (customer?.address || null);

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

            if (postageValue > 0) {
                payload.shipping_cost = postageValue;
            }

            if (customer) {
                if (customer.type === 'existing' && !editingCustomer) {
                    payload.customer_id = customer.id;
                    payload.customer_name = customer.name || null;
                    payload.customer_phone = customer.phone || null;
                    payload.customer_email = customer.email || null;
                    payload.customer_address = customer.address || null;
                } else if (customer.type === 'existing' && editingCustomer) {
                    payload.customer_id = customer.id;
                    payload.customer_name = customerEdit.name || null;
                    payload.customer_phone = customerEdit.phone || null;
                    payload.customer_email = customerEdit.email || null;
                    payload.customer_address = editedAddress || null;
                } else {
                    payload.customer_name = editingCustomer ? customerEdit.name : customer.name;
                    payload.customer_phone = (editingCustomer ? customerEdit.phone : customer.phone) || null;
                    payload.customer_email = (editingCustomer ? customerEdit.email : customer.email) || null;
                    payload.customer_address = (editingCustomer ? editedAddress : customer.address) || null;
                }
            }

            const response = await saleApi.create(payload, receiptFile);
            onComplete(response.data);
        } catch (err) {
            setError(err.message || 'Failed to create sale');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
                {/* Header */}
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <h3 className="text-lg font-semibold text-gray-900">Order Confirmation</h3>
                    <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Scrollable Body */}
                <div className="flex-1 overflow-y-auto px-6 py-4 space-y-5">
                    {/* Customer Info */}
                    <div className="bg-gray-50 rounded-xl p-4">
                        <div className="flex items-center justify-between mb-2">
                            <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Customer</h4>
                            <button
                                type="button"
                                onClick={() => setEditingCustomer(!editingCustomer)}
                                className="text-xs font-medium text-blue-600 hover:text-blue-700"
                            >
                                {editingCustomer ? 'Done' : 'Edit'}
                            </button>
                        </div>
                        {editingCustomer ? (
                            <div className="space-y-2">
                                <input
                                    type="text"
                                    value={customerEdit.name}
                                    onChange={(e) => setCustomerEdit(prev => ({ ...prev, name: e.target.value }))}
                                    placeholder="Customer name *"
                                    className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                />
                                <input
                                    type="tel"
                                    value={customerEdit.phone}
                                    onChange={(e) => setCustomerEdit(prev => ({ ...prev, phone: e.target.value }))}
                                    placeholder="Phone number"
                                    className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                />
                                <input
                                    type="email"
                                    value={customerEdit.email}
                                    onChange={(e) => setCustomerEdit(prev => ({ ...prev, email: e.target.value }))}
                                    placeholder="Email"
                                    className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                />
                                <input
                                    type="text"
                                    value={customerEdit.addressLine}
                                    onChange={(e) => setCustomerEdit(prev => ({ ...prev, addressLine: e.target.value }))}
                                    placeholder="Address"
                                    className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                />
                                <div className="grid grid-cols-2 gap-2">
                                    <input
                                        type="text"
                                        value={customerEdit.city}
                                        onChange={(e) => setCustomerEdit(prev => ({ ...prev, city: e.target.value }))}
                                        placeholder="City"
                                        className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                    />
                                    <input
                                        type="text"
                                        value={customerEdit.postcode}
                                        onChange={(e) => setCustomerEdit(prev => ({ ...prev, postcode: e.target.value }))}
                                        placeholder="Postcode"
                                        maxLength={5}
                                        className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                    />
                                </div>
                                <select
                                    value={customerEdit.state}
                                    onChange={(e) => setCustomerEdit(prev => ({ ...prev, state: e.target.value }))}
                                    className="w-full px-3 py-1.5 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                                >
                                    <option value="">Select state</option>
                                    {MALAYSIAN_STATES.map(s => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                        ) : (
                            <div>
                                <p className="text-sm font-semibold text-gray-900">{displayName}</p>
                                {displayPhone && (
                                    <p className="text-xs text-gray-500 mt-0.5">{displayPhone}</p>
                                )}
                                {displayEmail && (
                                    <p className="text-xs text-gray-500 mt-0.5">{displayEmail}</p>
                                )}
                                {displayAddress && (
                                    <p className="text-xs text-gray-500 mt-0.5">{displayAddress}</p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Items */}
                    <div>
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Items ({cart.length})</h4>
                        <div className="border border-gray-200 rounded-xl divide-y divide-gray-100">
                            {cart.map(item => (
                                <div key={item.key} className="px-4 py-3 flex items-center justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                                        {item.variantName && (
                                            <p className="text-xs text-gray-500">{item.variantName}</p>
                                        )}
                                        <p className="text-xs text-gray-400">
                                            RM {item.unitPrice.toFixed(2)} x {item.quantity}
                                        </p>
                                    </div>
                                    <span className="text-sm font-semibold text-gray-900 shrink-0">
                                        RM {item.totalPrice.toFixed(2)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Price Breakdown */}
                    <div className="bg-gray-50 rounded-xl p-4 space-y-2">
                        <div className="flex justify-between text-sm text-gray-600">
                            <span>Subtotal</span>
                            <span>RM {subtotal.toFixed(2)}</span>
                        </div>
                        {discountValue > 0 && (
                            <div className="flex justify-between text-sm text-red-500">
                                <span>Discount {discount.type === 'percentage' ? `(${discount.amount}%)` : ''}</span>
                                <span>- RM {discountValue.toFixed(2)}</span>
                            </div>
                        )}
                        {postageValue > 0 && (
                            <div className="flex justify-between text-sm text-gray-600">
                                <span>Postage</span>
                                <span>+ RM {postageValue.toFixed(2)}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-200">
                            <span>Total</span>
                            <span className="text-blue-600">RM {total.toFixed(2)}</span>
                        </div>
                    </div>

                    {/* Payment Method */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Payment Method</label>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                onClick={() => setPaymentMethod('cash')}
                                className={`py-2.5 px-4 rounded-xl border-2 text-sm font-medium transition-all ${
                                    paymentMethod === 'cash'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                Cash
                            </button>
                            <button
                                onClick={() => setPaymentMethod('bank_transfer')}
                                className={`py-2.5 px-4 rounded-xl border-2 text-sm font-medium transition-all ${
                                    paymentMethod === 'bank_transfer'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                Bank Transfer
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
                        <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Payment Status</label>
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

                    {/* Receipt Attachment */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Receipt Attachment (optional)</label>
                        {!receiptFile ? (
                            <label className="flex flex-col items-center justify-center w-full py-4 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-colors">
                                <svg className="w-6 h-6 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span className="text-xs text-gray-500">Upload receipt image or PDF</span>
                                <span className="text-xs text-gray-400 mt-0.5">JPG, PNG, PDF, WebP (max 5MB)</span>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    onChange={handleReceiptChange}
                                    className="hidden"
                                />
                            </label>
                        ) : (
                            <div className="border border-gray-200 rounded-lg p-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2 min-w-0">
                                        {receiptPreview ? (
                                            <img src={receiptPreview} alt="Receipt" className="w-10 h-10 rounded object-cover shrink-0" />
                                        ) : (
                                            <div className="w-10 h-10 rounded bg-red-50 flex items-center justify-center shrink-0">
                                                <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-gray-700 truncate">{receiptFile.name}</p>
                                            <p className="text-xs text-gray-400">{(receiptFile.size / 1024).toFixed(0)} KB</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={removeReceipt}
                                        className="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p className="text-sm text-red-600">{error}</p>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="px-6 py-4 border-t border-gray-100 flex gap-3 shrink-0">
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

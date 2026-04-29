import React, { useEffect, useMemo, useState } from 'react';
import { saleApi, salesSourceApi } from '../services/api';

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cod', label: 'COD' },
];

const INPUT_CLASS =
    'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';

function normalizeItemableType(rawType) {
    if (!rawType) return 'product';
    const lower = String(rawType).toLowerCase();
    if (lower.includes('package')) return 'package';
    if (lower.includes('course')) return 'course';
    return 'product';
}

export default function EditSaleModal({ sale, onClose, onSaved }) {
    const [customer, setCustomer] = useState({
        customer_id: sale.customer?.id || sale.customer_id || null,
        name: sale.customer?.name || sale.customer_name || '',
        phone: sale.customer?.phone || sale.customer_phone || '',
        email: sale.customer?.email || sale.guest_email || '',
        address:
            typeof sale.shipping_address === 'string'
                ? sale.shipping_address
                : sale.shipping_address?.full_address || '',
    });
    const [items, setItems] = useState(() =>
        (sale.items || []).map((item) => ({
            id: item.id,
            itemable_type: normalizeItemableType(item.itemable_type),
            itemable_id: item.product_id || item.package_id || item.itemable_id,
            product_variant_id: item.product_variant_id || null,
            product_name: item.product_name,
            variant_name: item.variant_name,
            quantity: Number(item.quantity_ordered || item.quantity || 1),
            unit_price: Number(item.unit_price),
        })),
    );
    const [paymentMethod, setPaymentMethod] = useState(sale.payment_method || 'cash');
    const [paymentReference, setPaymentReference] = useState(sale.metadata?.payment_reference || '');
    const [salesSourceId, setSalesSourceId] = useState(sale.sales_source_id || null);
    const [salesSources, setSalesSources] = useState([]);
    const [discountType, setDiscountType] = useState(sale.metadata?.discount_type || 'fixed');
    const [discountAmount, setDiscountAmount] = useState(
        Number(sale.metadata?.discount_input ?? sale.discount_amount ?? 0),
    );
    const [shippingCost, setShippingCost] = useState(Number(sale.shipping_cost || 0));
    const [notes, setNotes] = useState(sale.internal_notes || '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        salesSourceApi
            .list()
            .then((res) => setSalesSources(res.data || []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        const handleKey = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const subtotal = useMemo(
        () => items.reduce((sum, it) => sum + Number(it.quantity) * Number(it.unit_price), 0),
        [items],
    );
    const discountValue =
        discountType === 'percentage'
            ? Math.round(subtotal * (Number(discountAmount) / 100) * 100) / 100
            : Number(discountAmount) || 0;
    const total = Math.max(0, subtotal - discountValue + Number(shippingCost || 0));

    const updateItem = (index, patch) =>
        setItems((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    const removeItem = (index) =>
        setItems((prev) => prev.filter((_, i) => i !== index));

    const salespersonName = sale.metadata?.salesperson_name || sale.salesperson_name || '—';

    const handleSave = async () => {
        if (saving) {
            return;
        }
        setSaving(true);
        setError(null);
        try {
            const payload = {
                sales_source_id: salesSourceId,
                customer_id: customer.customer_id,
                customer_name: customer.name,
                customer_phone: customer.phone,
                customer_email: customer.email || null,
                customer_address: customer.address || null,
                payment_method: paymentMethod,
                discount_amount: Number(discountAmount) || 0,
                discount_type: discountType,
                shipping_cost: Number(shippingCost) || 0,
                notes,
                items: items.map((it) => {
                    const row = {
                        itemable_type: it.itemable_type,
                        itemable_id: it.itemable_id,
                        quantity: Number(it.quantity),
                        unit_price: Number(it.unit_price),
                    };
                    if (it.id) {
                        row.id = it.id;
                    }
                    if (it.product_variant_id) {
                        row.product_variant_id = it.product_variant_id;
                    }
                    return row;
                }),
            };

            if (paymentMethod === 'bank_transfer') {
                payload.payment_reference = paymentReference;
            }

            const res = await saleApi.update(sale.id, payload);
            onSaved(res.data);
        } catch (err) {
            setError(err.message || 'Failed to save changes.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 sm:p-4"
            onClick={onClose}
        >
            <div
                className="bg-white rounded-t-2xl sm:rounded-2xl shadow-xl w-full sm:max-w-3xl max-h-full sm:max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-label="Edit sale"
            >
                {/* Header */}
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900">
                            Edit Sale {sale.order_number}
                        </h3>
                        <p className="text-xs text-gray-400 mt-0.5">
                            Salesperson: {salespersonName}{' '}
                            <span className="text-gray-300">(locked)</span>
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-1 text-gray-400 hover:text-gray-600"
                        aria-label="Close edit modal"
                    >
                        <svg
                            className="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">
                    {/* Customer */}
                    <section>
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                            Customer
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input
                                type="text"
                                className={INPUT_CLASS}
                                placeholder="Name"
                                value={customer.name}
                                onChange={(e) =>
                                    setCustomer({ ...customer, name: e.target.value })
                                }
                            />
                            <input
                                type="tel"
                                className={INPUT_CLASS}
                                placeholder="Phone"
                                value={customer.phone}
                                onChange={(e) =>
                                    setCustomer({ ...customer, phone: e.target.value })
                                }
                            />
                            <input
                                type="email"
                                className={INPUT_CLASS}
                                placeholder="Email"
                                value={customer.email}
                                onChange={(e) =>
                                    setCustomer({ ...customer, email: e.target.value })
                                }
                            />
                            <input
                                type="text"
                                className={INPUT_CLASS}
                                placeholder="Address"
                                value={customer.address}
                                onChange={(e) =>
                                    setCustomer({ ...customer, address: e.target.value })
                                }
                            />
                        </div>
                    </section>

                    {/* Items */}
                    <section>
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                            Items ({items.length})
                        </h4>
                        <div className="border border-gray-200 rounded-xl divide-y divide-gray-100">
                            {items.length === 0 ? (
                                <p className="px-4 py-6 text-sm text-gray-400 text-center">
                                    No items. Add one below.
                                </p>
                            ) : (
                                items.map((it, i) => (
                                    <div
                                        key={it.id || `new-${i}`}
                                        className="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2.5"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">
                                                {it.product_name || 'Item'}
                                            </p>
                                            {it.variant_name && (
                                                <p className="text-xs text-gray-500">
                                                    {it.variant_name}
                                                </p>
                                            )}
                                        </div>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={it.unit_price}
                                            onChange={(e) =>
                                                updateItem(i, { unit_price: e.target.value })
                                            }
                                            className="w-20 sm:w-24 px-2 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            aria-label="Unit price"
                                        />
                                        <input
                                            type="number"
                                            min="1"
                                            step="1"
                                            value={it.quantity}
                                            onChange={(e) =>
                                                updateItem(i, { quantity: e.target.value })
                                            }
                                            className="w-16 sm:w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            aria-label="Quantity"
                                        />
                                        <span className="w-20 sm:w-24 text-right text-sm font-semibold text-gray-900 shrink-0">
                                            RM{' '}
                                            {(
                                                Number(it.quantity) * Number(it.unit_price)
                                            ).toFixed(2)}
                                        </span>
                                        <button
                                            onClick={() => removeItem(i)}
                                            className="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                                            aria-label={`Remove ${it.product_name || 'item'}`}
                                        >
                                            <svg
                                                className="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    {/* Payment */}
                    <section>
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                            Payment
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <select
                                className={INPUT_CLASS}
                                value={paymentMethod}
                                onChange={(e) => setPaymentMethod(e.target.value)}
                            >
                                {PAYMENT_METHODS.map((m) => (
                                    <option key={m.value} value={m.value}>
                                        {m.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={INPUT_CLASS}
                                value={salesSourceId || ''}
                                onChange={(e) =>
                                    setSalesSourceId(
                                        e.target.value ? Number(e.target.value) : null,
                                    )
                                }
                            >
                                <option value="">Select sales source</option>
                                {salesSources.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                            {paymentMethod === 'bank_transfer' && (
                                <input
                                    type="text"
                                    className={`${INPUT_CLASS} sm:col-span-2`}
                                    placeholder="Payment reference"
                                    value={paymentReference}
                                    onChange={(e) => setPaymentReference(e.target.value)}
                                />
                            )}
                        </div>
                    </section>

                    {/* Totals */}
                    <section>
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                            Totals
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                            <select
                                className={INPUT_CLASS}
                                value={discountType}
                                onChange={(e) => setDiscountType(e.target.value)}
                            >
                                <option value="fixed">Fixed (RM)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className={INPUT_CLASS}
                                placeholder="Discount"
                                value={discountAmount}
                                onChange={(e) => setDiscountAmount(e.target.value)}
                                aria-label="Discount amount"
                            />
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className={INPUT_CLASS}
                                placeholder="Shipping"
                                value={shippingCost}
                                onChange={(e) => setShippingCost(e.target.value)}
                                aria-label="Shipping cost"
                            />
                        </div>
                        <div className="bg-gray-50 rounded-xl p-4 space-y-2">
                            <div className="flex justify-between text-sm text-gray-600">
                                <span>Subtotal</span>
                                <span>RM {subtotal.toFixed(2)}</span>
                            </div>
                            {discountValue > 0 && (
                                <div className="flex justify-between text-sm text-red-500">
                                    <span>
                                        Discount{' '}
                                        {discountType === 'percentage'
                                            ? `(${Number(discountAmount) || 0}%)`
                                            : ''}
                                    </span>
                                    <span>- RM {discountValue.toFixed(2)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm text-gray-600">
                                <span>Shipping</span>
                                <span>+ RM {Number(shippingCost || 0).toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-200">
                                <span>Total</span>
                                <span className="text-blue-600">RM {total.toFixed(2)}</span>
                            </div>
                        </div>
                    </section>

                    {/* Notes */}
                    <section>
                        <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                            Notes
                        </h4>
                        <textarea
                            rows={3}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                            placeholder="Internal notes..."
                        />
                    </section>

                    {error && (
                        <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p className="text-sm text-red-600">{error}</p>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="px-6 py-4 border-t border-gray-100 flex gap-3 shrink-0 justify-end">
                    <button
                        onClick={onClose}
                        className="px-5 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSave}
                        disabled={saving}
                        className="px-5 py-2.5 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-sm transition-colors"
                    >
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </div>
        </div>
    );
}

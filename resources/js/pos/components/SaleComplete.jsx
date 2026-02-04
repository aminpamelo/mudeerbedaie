import React from 'react';

export default function SaleComplete({ sale, onNewSale }) {
    return (
        <div className="h-full flex items-center justify-center bg-gray-50">
            <div className="max-w-md w-full mx-4 text-center">
                {/* Success Icon */}
                <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg className="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h2 className="text-2xl font-bold text-gray-900 mb-2">Sale Complete!</h2>
                <p className="text-gray-500 mb-6">Transaction has been recorded successfully.</p>

                {/* Sale Details */}
                <div className="bg-white rounded-2xl border border-gray-200 p-6 mb-6 text-left">
                    <div className="space-y-3">
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Order Number</span>
                            <span className="text-sm font-semibold text-gray-900">{sale.order_number || sale.sale_number}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Customer</span>
                            <span className="text-sm font-medium text-gray-900">
                                {sale.customer?.name || sale.customer_name || 'Walk-in'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Payment</span>
                            <span className="text-sm font-medium text-gray-900 capitalize">
                                {sale.payment_method?.replace('_', ' ')}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Status</span>
                            <span className={`text-sm font-medium capitalize ${
                                sale.payment_status === 'paid' ? 'text-green-600' : 'text-yellow-600'
                            }`}>
                                {sale.payment_status}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Items</span>
                            <span className="text-sm font-medium text-gray-900">{sale.items?.length || 0}</span>
                        </div>
                        <div className="border-t border-gray-100 pt-3">
                            <div className="flex justify-between">
                                <span className="text-base font-semibold text-gray-900">Total</span>
                                <span className="text-base font-bold text-blue-600">
                                    RM {parseFloat(sale.total_amount).toFixed(2)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <button
                    onClick={onNewSale}
                    className="w-full py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-colors"
                >
                    New Sale
                </button>
            </div>
        </div>
    );
}

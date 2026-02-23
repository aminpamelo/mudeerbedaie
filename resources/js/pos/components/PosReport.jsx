import React, { useState, useEffect, useRef } from 'react';
import { Chart, registerables } from 'chart.js';
import { reportApi } from '../services/api';

Chart.register(...registerables);

const MONTH_NAMES_FULL = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

function formatRM(value) {
    return 'RM ' + Number(value).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function PosReport({ isMobile = false }) {
    const [reportView, setReportView] = useState('monthly');

    // Monthly state
    const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
    const [monthlyData, setMonthlyData] = useState(null);
    const [monthlyLoading, setMonthlyLoading] = useState(false);

    // Daily state
    const [dailyYear, setDailyYear] = useState(new Date().getFullYear());
    const [dailyMonth, setDailyMonth] = useState(new Date().getMonth() + 1);
    const [dailyData, setDailyData] = useState(null);
    const [dailyLoading, setDailyLoading] = useState(false);

    // Day detail modal
    const [selectedDay, setSelectedDay] = useState(null);
    const [dayDetail, setDayDetail] = useState(null);
    const [dayDetailLoading, setDayDetailLoading] = useState(false);

    // Chart
    const chartRef = useRef(null);
    const chartInstance = useRef(null);

    // Year options
    const currentYear = new Date().getFullYear();
    const yearOptions = [];
    for (let y = currentYear; y >= currentYear - 5; y--) {
        yearOptions.push(y);
    }

    // Fetch monthly data
    useEffect(() => {
        if (reportView !== 'monthly') return;
        setMonthlyLoading(true);
        reportApi.monthly({ year: selectedYear })
            .then(res => setMonthlyData(res.data))
            .catch(err => console.error('Failed to fetch monthly report:', err))
            .finally(() => setMonthlyLoading(false));
    }, [reportView, selectedYear]);

    // Fetch daily data
    useEffect(() => {
        if (reportView !== 'daily') return;
        setDailyLoading(true);
        reportApi.daily({ year: dailyYear, month: dailyMonth })
            .then(res => setDailyData(res.data))
            .catch(err => console.error('Failed to fetch daily report:', err))
            .finally(() => setDailyLoading(false));
    }, [reportView, dailyYear, dailyMonth]);

    // Chart.js lifecycle
    useEffect(() => {
        if (!monthlyData || reportView !== 'monthly' || !chartRef.current) return;

        if (chartInstance.current) {
            chartInstance.current.destroy();
        }

        chartInstance.current = new Chart(chartRef.current, {
            type: 'line',
            data: {
                labels: monthlyData.months.map(m => m.month_name),
                datasets: [{
                    label: 'Revenue (RM)',
                    data: monthlyData.months.map(m => m.revenue),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => formatRM(ctx.parsed.y),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (val) => 'RM ' + val.toLocaleString('en-MY'),
                        },
                    },
                },
            },
        });

        return () => {
            if (chartInstance.current) {
                chartInstance.current.destroy();
                chartInstance.current = null;
            }
        };
    }, [monthlyData, reportView]);

    // Fetch day detail
    const fetchDayDetail = async (day) => {
        setSelectedDay(day);
        setDayDetail(null);
        setDayDetailLoading(true);
        try {
            const res = await reportApi.daily({ year: dailyYear, month: dailyMonth, day });
            setDayDetail(res.data);
        } catch (err) {
            console.error('Failed to fetch day detail:', err);
        } finally {
            setDayDetailLoading(false);
        }
    };

    // Daily month navigation
    const prevMonth = () => {
        if (dailyMonth === 1) {
            setDailyMonth(12);
            setDailyYear(dailyYear - 1);
        } else {
            setDailyMonth(dailyMonth - 1);
        }
    };

    const nextMonth = () => {
        if (dailyMonth === 12) {
            setDailyMonth(1);
            setDailyYear(dailyYear + 1);
        } else {
            setDailyMonth(dailyMonth + 1);
        }
    };

    const Spinner = () => (
        <div className="flex items-center justify-center h-40">
            <div className="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
        </div>
    );

    return (
        <div className="h-full flex flex-col bg-gray-50">
            {/* Toolbar */}
            <div className="bg-white border-b border-gray-200 px-4 lg:px-6 py-3 shrink-0 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-0">
                <div className="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                    <button
                        onClick={() => setReportView('monthly')}
                        className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
                            reportView === 'monthly'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Monthly
                    </button>
                    <button
                        onClick={() => setReportView('daily')}
                        className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
                            reportView === 'daily'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Daily
                    </button>
                </div>

                {reportView === 'monthly' && (
                    <select
                        value={selectedYear}
                        onChange={(e) => setSelectedYear(parseInt(e.target.value))}
                        className="px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        {yearOptions.map(y => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                )}

                {reportView === 'daily' && (
                    <div className="flex items-center gap-3">
                        <button onClick={prevMonth} className="p-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <span className="text-sm font-semibold text-gray-900 min-w-[140px] text-center">
                            {MONTH_NAMES_FULL[dailyMonth - 1]} {dailyYear}
                        </span>
                        <button onClick={nextMonth} className="p-1.5 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                )}
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-4 lg:p-6">
                {reportView === 'monthly' && (
                    monthlyLoading ? <Spinner /> : monthlyData && (
                        <>
                            {/* Revenue Chart */}
                            <div className="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                                <h3 className="text-sm font-semibold text-gray-700 mb-3">Revenue Trend — {selectedYear}</h3>
                                <div style={{ height: '250px' }}>
                                    <canvas ref={chartRef} />
                                </div>
                            </div>

                            {/* Summary Cards */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                                <div className="bg-white rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-medium text-gray-500">Total Revenue</p>
                                    <p className="text-xl font-bold text-blue-600 mt-1">{formatRM(monthlyData.totals.revenue)}</p>
                                </div>
                                <div className="bg-white rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-medium text-gray-500">Total Sales</p>
                                    <p className="text-xl font-bold text-green-600 mt-1">{monthlyData.totals.sales_count}</p>
                                </div>
                                <div className="bg-white rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-medium text-gray-500">Items Sold</p>
                                    <p className="text-xl font-bold text-purple-600 mt-1">{monthlyData.totals.items_sold}</p>
                                </div>
                            </div>

                            {/* Monthly Table */}
                            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-gray-50 border-b border-gray-200">
                                            <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Month</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Revenue</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Sales</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Items Sold</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {monthlyData.months.map(m => (
                                            <tr key={m.month} className={m.sales_count === 0 ? 'text-gray-300' : ''}>
                                                <td className="px-4 py-3 text-sm font-medium text-gray-900">{MONTH_NAMES_FULL[m.month - 1]}</td>
                                                <td className="px-4 py-3 text-sm text-right font-semibold">{formatRM(m.revenue)}</td>
                                                <td className="px-4 py-3 text-sm text-right">{m.sales_count}</td>
                                                <td className="px-4 py-3 text-sm text-right">{m.items_sold}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-gray-50 border-t border-gray-200 font-bold">
                                            <td className="px-4 py-3 text-sm text-gray-900">Total</td>
                                            <td className="px-4 py-3 text-sm text-right text-blue-600">{formatRM(monthlyData.totals.revenue)}</td>
                                            <td className="px-4 py-3 text-sm text-right">{monthlyData.totals.sales_count}</td>
                                            <td className="px-4 py-3 text-sm text-right">{monthlyData.totals.items_sold}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )
                )}

                {reportView === 'daily' && (
                    dailyLoading ? <Spinner /> : dailyData && (
                        <>
                            {/* Summary Cards */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                <div className="bg-white rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-medium text-gray-500">Revenue — {dailyData.month_name}</p>
                                    <p className="text-xl font-bold text-blue-600 mt-1">{formatRM(dailyData.totals.revenue)}</p>
                                </div>
                                <div className="bg-white rounded-xl border border-gray-200 p-4">
                                    <p className="text-xs font-medium text-gray-500">Sales — {dailyData.month_name}</p>
                                    <p className="text-xl font-bold text-green-600 mt-1">{dailyData.totals.sales_count}</p>
                                </div>
                            </div>

                            {/* Daily Table */}
                            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-gray-50 border-b border-gray-200">
                                            <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                                            <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Day</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Revenue</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {dailyData.days.map(d => {
                                            const isWeekend = d.day_name === 'Sat' || d.day_name === 'Sun';
                                            const hasSales = d.sales_count > 0;
                                            return (
                                                <tr
                                                    key={d.day}
                                                    onClick={() => hasSales && fetchDayDetail(d.day)}
                                                    className={`transition-colors ${
                                                        isWeekend ? 'bg-gray-50/50' : ''
                                                    } ${
                                                        hasSales
                                                            ? 'cursor-pointer hover:bg-blue-50'
                                                            : 'text-gray-300'
                                                    }`}
                                                >
                                                    <td className="px-4 py-2.5 text-sm font-medium">{d.date}</td>
                                                    <td className="px-4 py-2.5 text-sm">{d.day_name}</td>
                                                    <td className="px-4 py-2.5 text-sm text-right font-semibold">
                                                        {hasSales ? formatRM(d.revenue) : '-'}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-sm text-right">
                                                        {hasSales ? d.sales_count : '-'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-gray-50 border-t border-gray-200 font-bold">
                                            <td className="px-4 py-3 text-sm text-gray-900" colSpan={2}>Total</td>
                                            <td className="px-4 py-3 text-sm text-right text-blue-600">{formatRM(dailyData.totals.revenue)}</td>
                                            <td className="px-4 py-3 text-sm text-right">{dailyData.totals.sales_count}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )
                )}
            </div>

            {/* Day Detail Modal */}
            {selectedDay && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setSelectedDay(null)}>
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
                        {/* Header */}
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">Daily Report</h3>
                                <p className="text-sm text-gray-500">{dayDetail?.date || `${dailyYear}-${String(dailyMonth).padStart(2, '0')}-${String(selectedDay).padStart(2, '0')}`}</p>
                            </div>
                            <button onClick={() => setSelectedDay(null)} className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Body */}
                        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-5">
                            {dayDetailLoading ? (
                                <Spinner />
                            ) : dayDetail && (
                                <>
                                    {/* Summary */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="bg-blue-50 rounded-xl p-3 text-center">
                                            <p className="text-xs text-blue-500 font-medium">Revenue</p>
                                            <p className="text-lg font-bold text-blue-700">{formatRM(dayDetail.revenue)}</p>
                                        </div>
                                        <div className="bg-green-50 rounded-xl p-3 text-center">
                                            <p className="text-xs text-green-500 font-medium">Sales</p>
                                            <p className="text-lg font-bold text-green-700">{dayDetail.sales_count}</p>
                                        </div>
                                    </div>

                                    {/* Items breakdown */}
                                    {dayDetail.items.length > 0 && (
                                        <div>
                                            <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Products Sold</h4>
                                            <div className="border border-gray-200 rounded-xl divide-y divide-gray-100">
                                                {dayDetail.items.map((item, i) => (
                                                    <div key={i} className="px-4 py-3 flex justify-between">
                                                        <div>
                                                            <p className="text-sm font-medium text-gray-900">{item.product_name}</p>
                                                            {item.variant_name && <p className="text-xs text-gray-500">{item.variant_name}</p>}
                                                        </div>
                                                        <div className="text-right">
                                                            <p className="text-sm font-semibold text-gray-900">{formatRM(item.total_amount)}</p>
                                                            <p className="text-xs text-gray-500">x{item.quantity}</p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Individual orders */}
                                    {dayDetail.orders.length > 0 && (
                                        <div>
                                            <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                                                Orders ({dayDetail.orders.length})
                                            </h4>
                                            <div className="space-y-2">
                                                {dayDetail.orders.map(order => (
                                                    <div key={order.id} className="bg-gray-50 rounded-lg px-4 py-2.5">
                                                        <div className="flex justify-between">
                                                            <span className="text-sm font-medium text-blue-600">{order.order_number}</span>
                                                            <span className="text-sm font-semibold text-gray-900">{formatRM(order.total_amount)}</span>
                                                        </div>
                                                        <div className="flex justify-between mt-0.5">
                                                            <span className="text-xs text-gray-500">{order.customer_name}</span>
                                                            <span className="text-xs text-gray-400 capitalize">{order.payment_method?.replace('_', ' ')}</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Footer */}
                        <div className="px-6 py-4 border-t border-gray-100 shrink-0">
                            <button
                                onClick={() => setSelectedDay(null)}
                                className="w-full py-2.5 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 text-sm transition-colors"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

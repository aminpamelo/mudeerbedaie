import React, { useState, useEffect } from 'react';
import api from '../services/api';
import StatCard from '../components/StatCard';

const formatRM = (amount) => {
    const num = parseFloat(amount) || 0;
    return `RM ${num.toFixed(2)}`;
};

const statusBadge = (status) => {
    const styles = {
        approved: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        rejected: 'bg-red-100 text-red-700',
        paid: 'bg-blue-100 text-blue-700',
    };
    return styles[status] || 'bg-gray-100 text-gray-700';
};

const statusLabel = (status) => {
    const labels = {
        approved: 'Diluluskan',
        pending: 'Belum Selesai',
        rejected: 'Ditolak',
        paid: 'Dibayar',
    };
    return labels[status] || status;
};

export default function Earnings() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('all');

    useEffect(() => {
        const params = filter !== 'all' ? { status: filter } : {};
        setLoading(true);
        api.getEarnings(params)
            .then((res) => setData(res))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [filter]);

    if (loading && !data) {
        return (
            <div className="space-y-4">
                <div className="h-8 bg-gray-200 rounded animate-pulse w-48"></div>
                <div className="grid grid-cols-2 gap-3">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="h-24 bg-gray-200 rounded-xl animate-pulse"></div>
                    ))}
                </div>
                <div className="h-60 bg-gray-200 rounded-xl animate-pulse"></div>
            </div>
        );
    }

    const summary = data?.summary || {};
    const commissions = Array.isArray(data?.commissions) ? data.commissions : (data?.commissions?.data || []);

    const filters = [
        { key: 'all', label: 'Semua' },
        { key: 'pending', label: 'Belum Selesai' },
        { key: 'approved', label: 'Diluluskan' },
        { key: 'paid', label: 'Dibayar' },
        { key: 'rejected', label: 'Ditolak' },
    ];

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Pendapatan</h2>
                <p className="text-sm text-gray-500">Jejak komisen dan pembayaran anda</p>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <StatCard label="Jumlah Pendapatan" value={formatRM(summary.total_approved)} />
                <StatCard label="Belum Selesai" value={formatRM(summary.total_pending)} />
                <StatCard label="Diluluskan" value={formatRM(summary.total_approved)} />
                <StatCard label="Telah Dibayar" value={formatRM(summary.total_paid)} />
            </div>

            <div>
                <div className="flex items-center gap-2 overflow-x-auto pb-2 mb-3 -mx-1 px-1">
                    {filters.map((f) => (
                        <button
                            key={f.key}
                            onClick={() => setFilter(f.key)}
                            className={`shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
                                filter === f.key
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {f.label}
                        </button>
                    ))}
                </div>

                {loading ? (
                    <div className="h-40 bg-gray-200 rounded-xl animate-pulse"></div>
                ) : commissions.length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 text-center">
                        <p className="text-sm text-gray-500">Tiada komisen dijumpai.</p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {commissions.map((c, idx) => (
                            <div
                                key={c.id || idx}
                                className="bg-white rounded-xl border border-gray-200 p-4"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900">
                                            {formatRM(c.commission_amount)}
                                        </p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {c.funnel?.name || 'Program'}
                                        </p>
                                        <p className="text-xs text-gray-400 mt-0.5">
                                            {c.date || new Date(c.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge(c.status)}`}>
                                        {statusLabel(c.status)}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

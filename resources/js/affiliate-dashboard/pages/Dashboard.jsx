import React, { useState, useEffect } from 'react';
import api from '../services/api';
import StatCard from '../components/StatCard';
import CopyUrlButton from '../components/CopyUrlButton';

export default function Dashboard({ user, navigate }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.getDashboard()
            .then((res) => setData(res))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="space-y-4">
                <div className="h-8 bg-gray-200 rounded animate-pulse w-48"></div>
                <div className="grid grid-cols-2 gap-3">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="h-24 bg-gray-200 rounded-xl animate-pulse"></div>
                    ))}
                </div>
                <div className="h-40 bg-gray-200 rounded-xl animate-pulse"></div>
            </div>
        );
    }

    const stats = data?.stats || {};
    const funnels = data?.joined_funnels || [];

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">
                    Selamat kembali, {user?.name || 'Affiliate'}
                </h2>
                <p className="text-sm text-gray-500">Berikut adalah ringkasan prestasi anda</p>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <StatCard
                    label="Klik"
                    value={stats.total_clicks ?? 0}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59" />
                        </svg>
                    }
                />
                <StatCard
                    label="Penukaran"
                    value={stats.total_conversions ?? 0}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    }
                />
            </div>

            <div>
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-semibold text-gray-900">Program Anda</h3>
                    <button
                        onClick={() => navigate('/affiliate/discover')}
                        className="text-sm text-indigo-600 font-medium hover:text-indigo-500"
                    >
                        Terokai lagi
                    </button>
                </div>

                {funnels.length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 text-center">
                        <p className="text-sm text-gray-500">Anda belum menyertai sebarang program.</p>
                        <button
                            onClick={() => navigate('/affiliate/discover')}
                            className="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-500"
                        >
                            Layari program yang tersedia
                        </button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {funnels.map((funnel) => (
                            <div
                                key={funnel.id}
                                className="bg-white rounded-xl border border-gray-200 p-4"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex-1 min-w-0">
                                        <button
                                            onClick={() => navigate(`/affiliate/funnels/${funnel.id}`)}
                                            className="text-sm font-medium text-gray-900 hover:text-indigo-600 transition-colors text-left"
                                        >
                                            {funnel.name}
                                        </button>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            Klik: {funnel.stats?.total_clicks ?? 0}
                                        </p>
                                    </div>
                                    <button
                                        onClick={() => navigate(`/affiliate/funnels/${funnel.id}`)}
                                        className="text-gray-400 hover:text-indigo-600 ml-2"
                                    >
                                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                </div>
                                {(funnel.affiliate_custom_url || funnel.affiliate_url) && (
                                    <div className="mt-3">
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="text"
                                                readOnly
                                                value={funnel.affiliate_custom_url || funnel.affiliate_url}
                                                className={`flex-1 text-xs border rounded-lg px-3 py-1.5 truncate ${funnel.affiliate_custom_url ? 'bg-indigo-50 border-indigo-200 text-indigo-600' : 'bg-gray-50 border-gray-200 text-gray-600'}`}
                                            />
                                            <CopyUrlButton text={funnel.affiliate_custom_url || funnel.affiliate_url} label="Salin" />
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

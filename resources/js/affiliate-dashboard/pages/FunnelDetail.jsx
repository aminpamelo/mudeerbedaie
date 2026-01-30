import React, { useState, useEffect } from 'react';
import api from '../services/api';
import StatCard from '../components/StatCard';
import CopyUrlButton from '../components/CopyUrlButton';

export default function FunnelDetail({ funnelId, navigate }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.getFunnelStats(funnelId)
            .then((res) => setData(res))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [funnelId]);

    if (loading) {
        return (
            <div className="space-y-4">
                <div className="h-8 bg-gray-200 rounded animate-pulse w-48"></div>
                <div className="grid grid-cols-3 gap-3">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="h-24 bg-gray-200 rounded-xl animate-pulse"></div>
                    ))}
                </div>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="text-center py-12">
                <p className="text-sm text-gray-500">Program tidak dijumpai.</p>
                <button
                    onClick={() => navigate('/affiliate/dashboard')}
                    className="mt-3 text-sm font-medium text-indigo-600"
                >
                    Kembali ke Dashboard
                </button>
            </div>
        );
    }

    const funnel = data.funnel || {};
    const stats = data.stats || {};

    return (
        <div className="space-y-6">
            <div>
                <button
                    onClick={() => navigate('/affiliate/dashboard')}
                    className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-2"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Kembali
                </button>
                <h2 className="text-lg font-semibold text-gray-900">{funnel.name}</h2>
                {funnel.description && (
                    <p className="text-sm text-gray-500 mt-1">{funnel.description}</p>
                )}
            </div>

            {(funnel.affiliate_custom_url || funnel.affiliate_url) && (
                <div className="bg-white rounded-xl border border-gray-200 p-4">
                    <div>
                        <p className={`text-xs font-medium uppercase tracking-wide mb-2 ${funnel.affiliate_custom_url ? 'text-indigo-500' : 'text-gray-500'}`}>
                            URL Affiliate Anda
                        </p>
                        <div className="flex items-center gap-2">
                            <input
                                type="text"
                                readOnly
                                value={funnel.affiliate_custom_url || funnel.affiliate_url}
                                className={`flex-1 text-xs border rounded-lg px-3 py-2 truncate ${funnel.affiliate_custom_url ? 'bg-indigo-50 border-indigo-200 text-indigo-600' : 'bg-gray-50 border-gray-200 text-gray-600'}`}
                            />
                            <CopyUrlButton text={funnel.affiliate_custom_url || funnel.affiliate_url} />
                        </div>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-3 gap-3">
                <StatCard label="Views" value={stats.clicks ?? 0} />
                <StatCard label="Checkout" value={stats.conversions ?? 0} />
                <StatCard label="TQ Click" value={stats.thankyou_clicks ?? 0} />
            </div>
        </div>
    );
}

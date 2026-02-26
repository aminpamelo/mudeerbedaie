import React, { useState, useEffect } from 'react';
import api from '../services/api';

export default function Discover({ navigate }) {
    const [funnels, setFunnels] = useState([]);
    const [loading, setLoading] = useState(true);
    const [joining, setJoining] = useState(null);

    useEffect(() => {
        api.discoverFunnels()
            .then((res) => setFunnels(res.funnels || res.data || res))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const handleJoin = async (funnelId) => {
        setJoining(funnelId);
        try {
            await api.joinFunnel(funnelId);
            setFunnels((prev) =>
                prev.map((f) =>
                    f.id === funnelId ? { ...f, joined: true } : f
                )
            );
        } catch {
            // silently fail
        } finally {
            setJoining(null);
        }
    };

    if (loading) {
        return (
            <div className="space-y-4">
                <div className="h-8 bg-gray-200 rounded animate-pulse w-48"></div>
                {[1, 2, 3].map((i) => (
                    <div key={i} className="h-32 bg-gray-200 rounded-xl animate-pulse"></div>
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Terokai Program</h2>
                <p className="text-sm text-gray-500">Cari dan sertai program untuk mula menjana pendapatan</p>
            </div>

            {funnels.length === 0 ? (
                <div className="bg-white rounded-xl border border-gray-200 p-6 text-center">
                    <svg className="w-12 h-12 text-gray-300 mx-auto" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <p className="mt-3 text-sm text-gray-500">Tiada program tersedia buat masa ini.</p>
                    <p className="text-xs text-gray-400 mt-1">Sila semak semula nanti untuk peluang baharu.</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {funnels.map((funnel) => (
                        <div
                            key={funnel.id}
                            className={`bg-white rounded-xl border p-4 ${funnel.joined ? 'border-green-200' : 'border-gray-200'}`}
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-sm font-medium text-gray-900">{funnel.name}</h3>
                                    {funnel.description && (
                                        <p className="text-xs text-gray-500 mt-1 line-clamp-2">{funnel.description}</p>
                                    )}
                                </div>
                                {funnel.joined && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 shrink-0 ml-2">
                                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        Telah Disertai
                                    </span>
                                )}
                            </div>

                            <div className="mt-3">
                                {funnel.joined ? (
                                    <button
                                        onClick={() => navigate(`/affiliate/funnels/${funnel.id}`)}
                                        className="w-full rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition-colors"
                                    >
                                        Lihat Butiran
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => handleJoin(funnel.id)}
                                        disabled={joining === funnel.id}
                                        className="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {joining === funnel.id ? 'Menyertai...' : 'Sertai Program'}
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

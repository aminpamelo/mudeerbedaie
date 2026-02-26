import React, { useState, useEffect } from 'react';
import api from '../services/api';
import StatCard from '../components/StatCard';

export default function Leaderboard() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.getLeaderboard()
            .then((res) => setData(res))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div className="space-y-4">
                <div className="h-8 bg-gray-200 rounded animate-pulse w-48"></div>
                <div className="grid grid-cols-3 gap-3">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="h-24 bg-gray-200 rounded-xl animate-pulse"></div>
                    ))}
                </div>
                <div className="h-60 bg-gray-200 rounded-xl animate-pulse"></div>
            </div>
        );
    }

    const myStats = data?.my_stats || {};
    const leaderboard = data?.leaderboard || [];

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Statistik & Kedudukan</h2>
                <p className="text-sm text-gray-500">Prestasi anda berbanding affiliate lain</p>
            </div>

            <div className="grid grid-cols-3 gap-3">
                <StatCard
                    label="Views"
                    value={myStats.views ?? 0}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.577 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.577-3.007-9.963-7.178Z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Checkout"
                    value={myStats.checkout_fills ?? 0}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                    }
                />
                <StatCard
                    label="TQ Click"
                    value={myStats.thankyou_clicks ?? 0}
                    icon={
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    }
                />
            </div>

            {myStats.rank && (
                <div className="bg-indigo-50 rounded-xl border border-indigo-100 p-4 flex items-center gap-3">
                    <div className="shrink-0 w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">
                        #{myStats.rank}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-indigo-900">Kedudukan anda</p>
                        <p className="text-xs text-indigo-600">Daripada {leaderboard.length} affiliate</p>
                    </div>
                </div>
            )}

            <div>
                <h3 className="text-sm font-semibold text-gray-900 mb-3">Papan Kedudukan</h3>
                {leaderboard.length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 text-center">
                        <p className="text-sm text-gray-500">Belum ada data kedudukan.</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50">
                                    <th className="text-left px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th className="text-left px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th className="text-center px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">Views</th>
                                    <th className="text-center px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">Checkout</th>
                                    <th className="text-center px-3 py-2.5 text-xs font-medium text-gray-500 uppercase">TQ</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {leaderboard.map((entry, idx) => {
                                    const isMe = entry.id === data?.my_stats?.affiliate_id;
                                    return (
                                        <tr
                                            key={entry.id}
                                            className={isMe ? 'bg-indigo-50' : ''}
                                        >
                                            <td className="px-4 py-2.5 text-gray-500 text-xs font-medium">
                                                {idx < 3 ? (
                                                    <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold ${
                                                        idx === 0 ? 'bg-yellow-100 text-yellow-700' :
                                                        idx === 1 ? 'bg-gray-200 text-gray-600' :
                                                        'bg-orange-100 text-orange-700'
                                                    }`}>
                                                        {idx + 1}
                                                    </span>
                                                ) : (
                                                    <span className="pl-1.5">{idx + 1}</span>
                                                )}
                                            </td>
                                            <td className={`px-4 py-2.5 text-xs font-medium ${isMe ? 'text-indigo-700' : 'text-gray-900'}`}>
                                                {entry.name}
                                                {isMe && <span className="ml-1 text-indigo-500">(Anda)</span>}
                                            </td>
                                            <td className="px-3 py-2.5 text-center text-xs text-gray-600">{entry.views}</td>
                                            <td className="px-3 py-2.5 text-center text-xs text-gray-600">{entry.checkout_fills}</td>
                                            <td className="px-3 py-2.5 text-center text-xs text-gray-600">{entry.thankyou_clicks}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}

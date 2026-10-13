/**
 * Laporan Harian Iklan — port of Dana Ummah's "Laporan Harian": for one year, every ad
 * account's daily cost (Meta spend) and income (funnel sales from its ads, or every sale of
 * its funnels), laid out quarter → month → day with ROAS. Months expand to their days.
 */

import React, { useState, useEffect, useMemo, Fragment } from 'react';

const rm = (v) => (v ? `RM${Number(v).toLocaleString('en-MY', { maximumFractionDigits: 0 })}` : '—');
const roas = (cost, income) => (cost > 0 ? `${(income / cost).toFixed(2)}x` : income > 0 ? '∞' : '—');
const roasClass = (cost, income) => {
    if (!cost) return 'text-gray-400';
    const r = income / cost;
    return r >= 2 ? 'text-green-700' : r >= 1 ? 'text-yellow-700' : 'text-red-600';
};

const MONTHS = ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];

export default function StudioAdDailyReport() {
    const [year, setYear] = useState(new Date().getFullYear());
    const [source, setSource] = useState('ads');
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [openMonth, setOpenMonth] = useState(new Date().getMonth());
    const [only, setOnly] = useState('');

    useEffect(() => {
        setData(null);
        setError(null);
        fetch(`/api/v1/ad-monitor/daily?year=${year}&source=${source}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(async (r) => {
                const json = await r.json();
                if (!r.ok) throw new Error(json.message || 'Gagal memuatkan laporan');
                setData(json);
            })
            .catch((e) => setError(e.message));
    }, [year, source]);

    const clients = useMemo(() => (data?.clients || []).filter((c) => !only || String(c.id) === only), [data, only]);

    // months[m] = { days: [{date, cells: {id: [cost, income, count]}}], totals: {id: [...]}}
    const months = useMemo(() => {
        if (!data) return [];
        const out = Array.from({ length: 12 }, () => ({ days: [], totals: {} }));
        Object.entries(data.days).forEach(([date, cells]) => {
            const m = Number(date.slice(5, 7)) - 1;
            out[m].days.push({ date, cells });
            Object.entries(cells).forEach(([id, [cost, income, count]]) => {
                const t = out[m].totals[id] || [0, 0, 0];
                out[m].totals[id] = [t[0] + cost, t[1] + income, t[2] + count];
            });
        });
        return out;
    }, [data]);

    const sumRow = (cells) =>
        clients.reduce(
            (acc, c) => {
                const v = cells[c.id] || [0, 0, 0];
                return [acc[0] + v[0], acc[1] + v[1], acc[2] + v[2]];
            },
            [0, 0, 0],
        );

    const addCells = (list) =>
        list.reduce((acc, cells) => {
            Object.entries(cells).forEach(([id, v]) => {
                const t = acc[id] || [0, 0, 0];
                acc[id] = [t[0] + v[0], t[1] + v[1], t[2] + v[2]];
            });
            return acc;
        }, {});

    const yearTotals = addCells(months.map((m) => m.totals));
    const grand = sumRow(yearTotals);

    const Cells = ({ cells, bold }) => (
        <>
            {clients.map((c) => {
                const [cost, income] = cells[c.id] || [0, 0, 0];
                return (
                    <Fragment key={c.id}>
                        <td className={`border-l border-gray-100 px-2 py-1.5 text-right ${bold ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>{rm(cost)}</td>
                        <td className={`px-2 py-1.5 text-right ${bold ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>{rm(income)}</td>
                        <td className={`px-2 py-1.5 text-right ${roasClass(cost, income)}`}>{roas(cost, income)}</td>
                    </Fragment>
                );
            })}
            {(() => {
                const [cost, income] = sumRow(cells);
                return (
                    <>
                        <td className="border-l border-gray-200 px-2 py-1.5 text-right font-semibold text-gray-900">{rm(cost)}</td>
                        <td className="px-2 py-1.5 text-right font-semibold text-gray-900">{rm(income)}</td>
                        <td className={`px-2 py-1.5 text-right font-semibold ${roasClass(cost, income)}`}>{roas(cost, income)}</td>
                    </>
                );
            })()}
        </>
    );

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Laporan Harian <span className="fs-gradient-text">Iklan</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">Kos iklan Meta vs jualan funnel setiap akaun, setiap hari — dikumpul ikut suku tahun dan bulan.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <select value={year} onChange={(e) => setYear(Number(e.target.value))} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        {(data?.years || [year]).map((y) => (
                            <option key={y} value={y}>
                                {y}
                            </option>
                        ))}
                    </select>
                    <select value={source} onChange={(e) => setSource(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="ads">Jualan dari iklan sahaja</option>
                        <option value="all">Semua jualan funnel dipautkan</option>
                    </select>
                    {data?.clients?.length > 1 && (
                        <select value={only} onChange={(e) => setOnly(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Semua akaun</option>
                            {data.clients.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
            </div>

            {error ? (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
            ) : !data ? (
                <div className="py-20 text-center text-gray-500">Memuatkan laporan…</div>
            ) : clients.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-12 text-center text-sm text-gray-500">Tiada kos atau jualan iklan untuk {year}.</div>
            ) : (
                <>
                    <div className="mb-4 grid grid-cols-3 gap-3">
                        {[
                            ['Kos iklan', rm(grand[0])],
                            ['Jualan', `${rm(grand[1])} · ${grand[2].toLocaleString()} order`],
                            ['ROAS', roas(grand[0], grand[1])],
                        ].map(([label, value]) => (
                            <div key={label} className="rounded-lg border border-gray-200 bg-white px-4 py-3">
                                <span className="block text-[11px] uppercase tracking-wider text-gray-500">{label}</span>
                                <span className={`mt-1 block text-xl font-bold tabular-nums ${label === 'ROAS' ? roasClass(grand[0], grand[1]) : 'text-gray-900'}`}>{value}</span>
                            </div>
                        ))}
                    </div>

                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="w-full text-xs tabular-nums">
                            <thead className="text-gray-500">
                                <tr className="border-b border-gray-200">
                                    <th rowSpan={2} className="sticky left-0 z-10 bg-white px-3 py-2 text-left font-medium">
                                        Tempoh
                                    </th>
                                    {clients.map((c) => (
                                        <th key={c.id} colSpan={3} className="border-l border-gray-100 px-2 py-2 text-center font-semibold text-gray-900" title={`act_${c.account_id}`}>
                                            {c.name}
                                        </th>
                                    ))}
                                    <th colSpan={3} className="border-l border-gray-200 px-2 py-2 text-center font-semibold text-gray-900">
                                        Jumlah
                                    </th>
                                </tr>
                                <tr className="border-b border-gray-200 text-[10px] uppercase tracking-wider">
                                    {[...clients, { id: 'total' }].map((c) => (
                                        <Fragment key={c.id}>
                                            <th className="border-l border-gray-100 px-2 py-1.5 text-right font-medium">Kos</th>
                                            <th className="px-2 py-1.5 text-right font-medium">Jualan</th>
                                            <th className="px-2 py-1.5 text-right font-medium">ROAS</th>
                                        </Fragment>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[0, 1, 2, 3].map((q) => {
                                    const qMonths = [q * 3, q * 3 + 1, q * 3 + 2];
                                    const qTotals = addCells(qMonths.map((m) => months[m].totals));
                                    if (qMonths.every((m) => months[m].days.length === 0)) return null;
                                    return (
                                        <Fragment key={q}>
                                            <tr className="border-t border-gray-200 bg-gray-100">
                                                <td className="sticky left-0 z-10 bg-gray-100 px-3 py-1.5 font-semibold text-gray-900">Suku {q + 1}</td>
                                                <Cells cells={qTotals} bold />
                                            </tr>
                                            {qMonths.map((m) =>
                                                months[m].days.length === 0 ? null : (
                                                    <Fragment key={m}>
                                                        <tr className="cursor-pointer border-t border-gray-100 bg-gray-50 hover:bg-gray-100" onClick={() => setOpenMonth(openMonth === m ? null : m)}>
                                                            <td className="sticky left-0 z-10 bg-gray-50 px-3 py-1.5 font-medium text-gray-900">
                                                                {openMonth === m ? '▾' : '▸'} {MONTHS[m]}
                                                            </td>
                                                            <Cells cells={months[m].totals} bold />
                                                        </tr>
                                                        {openMonth === m &&
                                                            months[m].days.map((d) => (
                                                                <tr key={d.date} className={`border-t border-gray-100 ${d.date === data.today ? 'bg-orange-50' : ''}`}>
                                                                    <td className="sticky left-0 z-10 bg-white px-3 py-1.5 pl-7 text-gray-600">{d.date.slice(8)}</td>
                                                                    <Cells cells={d.cells} />
                                                                </tr>
                                                            ))}
                                                    </Fragment>
                                                ),
                                            )}
                                        </Fragment>
                                    );
                                })}
                                <tr className="border-t-2 border-gray-300">
                                    <td className="sticky left-0 z-10 bg-white px-3 py-2 font-semibold text-gray-900">Jumlah {year}</td>
                                    <Cells cells={yearTotals} bold />
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p className="mt-2 text-xs text-gray-500">
                        Kos = belanja Meta (sync ad-level). Jualan = order funnel dibayar / COD belum dibatalkan{' '}
                        {source === 'ads' ? 'dari trafik iklan akaun itu (UTM)' : 'termasuk semua trafik funnel yang dipautkan ke akaun'}.
                    </p>
                </>
            )}
        </div>
    );
}

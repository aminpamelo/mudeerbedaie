/**
 * Monitor Iklan — port of Dana Ummah's "Monitor Klien" for the Funnel Studio.
 * One row per Facebook ad account: spend vs real funnel sales, ROAS (Meta vs sebenar),
 * CPM / CTR (Sales), LPV, conversion, total sales, alerts and an automatic diagnosis of
 * the main leak. Expanding a row shows the buyer journey, campaign breakdown (Sales vs
 * support layers), creative scoreboard (thumbnails, hook/hold, fatigue, verdict) and funnels.
 * Meta numbers sync every 5 minutes; "Sync Meta sekarang" pulls them on demand.
 */

import React, { useState, useEffect, useCallback, useRef, Fragment } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const api = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'Permintaan gagal');
    return data;
};

const rm = (v, digits = 0) =>
    v === null || v === undefined ? '—' : `RM${Number(v).toLocaleString('en-MY', { minimumFractionDigits: digits, maximumFractionDigits: digits })}`;
const num = (v) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('en-MY'));
const pct = (v) => (v === null || v === undefined ? '—' : `${Number(v).toLocaleString('en-MY', { maximumFractionDigits: 1 })}%`);
const roas = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(2)}x`);
const fmt = (v, format) => (format === 'rm' ? rm(v, 2) : format === 'pct' ? pct(v) : format === 'x' ? roas(v) : num(v));

const ago = (iso) => {
    if (!iso) return 'belum pernah';
    const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (mins < 1) return 'baru sahaja';
    if (mins < 60) return `${mins} min lalu`;
    const hours = Math.round(mins / 60);
    return hours < 24 ? `${hours} jam lalu` : `${Math.round(hours / 24)} hari lalu`;
};

const delta = (now, before) => {
    if (before === null || before === undefined || Number(before) === 0 || now === null || now === undefined) return null;
    return Math.round(((Number(now) - Number(before)) / Math.abs(Number(before))) * 100);
};

const RANGES = [
    ['today', 'Hari ini'],
    ['yesterday', 'Semalam'],
    ['week', 'Minggu ini'],
    ['7', '7 hari'],
    ['30', '30 hari'],
    ['month', 'Bulan ini'],
    ['last_month', 'Bulan lepas'],
    ['year', 'Tahun ini'],
];

const LEVEL = {
    ok: { chip: 'bg-green-100 text-green-800', text: 'text-green-700', label: 'OK' },
    warning: { chip: 'bg-yellow-100 text-yellow-800', text: 'text-yellow-700', label: 'Amaran' },
    critical: { chip: 'bg-red-100 text-red-700', text: 'text-red-600', label: 'Kritikal' },
    insufficient: { chip: 'bg-gray-100 text-gray-600', text: 'text-gray-500', label: 'Data belum cukup' },
    info: { chip: 'bg-blue-100 text-blue-800', text: 'text-blue-700', label: 'Info' },
};

const VERDICT = {
    winner: 'bg-green-100 text-green-800',
    profitable: 'bg-green-100 text-green-800',
    watch: 'bg-yellow-100 text-yellow-800',
    weak: 'bg-red-100 text-red-700',
    kill: 'bg-red-100 text-red-700',
    learning: 'bg-gray-100 text-gray-600',
};

const LAYER = {
    sales: 'bg-orange-100 text-orange-800',
    engagement: 'bg-purple-100 text-purple-800',
    traffic: 'bg-blue-100 text-blue-800',
    awareness: 'bg-purple-100 text-purple-800',
    leads: 'bg-green-100 text-green-800',
    lain: 'bg-gray-100 text-gray-600',
};

const roasClass = (v) => (v === null || v === undefined ? 'text-gray-400' : v >= 2 ? 'text-green-700' : v >= 1 ? 'text-yellow-700' : 'text-red-600');
const judged = (c) => (c.has_sales ? c.sales : c.blended);

function Toast({ toast, onClose }) {
    useEffect(() => {
        const timer = setTimeout(onClose, 5000);
        return () => clearTimeout(timer);
    }, [onClose]);
    return (
        <div className={`toast toast-${toast.type}`}>
            <span>{toast.message}</span>
        </div>
    );
}

function Delta({ now, before, invert = false }) {
    const d = delta(now, before);
    if (d === null) return null;
    const good = invert ? d < 0 : d > 0;
    return (
        <span className={`text-xs font-medium ${d === 0 ? 'text-gray-500' : good ? 'text-green-700' : 'text-red-600'}`}>
            {d > 0 ? '▲' : d < 0 ? '▼' : '•'} {Math.abs(d)}%
        </span>
    );
}

function Kpi({ label, value, sub, now, before, invert, tone }) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <span className="block text-[11px] uppercase tracking-wider text-gray-500">{label}</span>
            <span className={`mt-1 block text-xl font-bold tabular-nums ${tone || 'text-gray-900'}`}>{value}</span>
            <span className="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                {sub}
                <Delta now={now} before={before} invert={invert} />
            </span>
        </div>
    );
}

export default function StudioAdMonitor({ onSelectFunnel }) {
    const [range, setRange] = useState(() => {
        try {
            return window.localStorage?.getItem('fs-monitor-range') || 'today';
        } catch (e) {
            return 'today';
        }
    });
    const [custom, setCustom] = useState({ from: '', to: '' });
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [syncing, setSyncing] = useState(false);
    const [toast, setToast] = useState(null);
    const [open, setOpen] = useState(null);
    const [showIdle, setShowIdle] = useState(false);
    const [showGuide, setShowGuide] = useState(false);
    // Visible width of the scrolling table, so an expanded account panel fits the screen
    // instead of stretching to the (wider) table.
    const scrollRef = useRef(null);
    const [panelWidth, setPanelWidth] = useState(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (!el || typeof ResizeObserver === 'undefined') return undefined;
        const observer = new ResizeObserver(() => setPanelWidth(el.clientWidth));
        observer.observe(el);
        return () => observer.disconnect();
    }, [data]);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams(range === 'custom' && custom.from && custom.to ? { from: custom.from, to: custom.to } : { range });
            setData(await api(`/api/v1/ad-monitor?${params}`));
        } catch (e) {
            setError(e.message);
        } finally {
            setLoading(false);
        }
    }, [range, custom]);

    useEffect(() => {
        load();
        try {
            if (range !== 'custom') window.localStorage?.setItem('fs-monitor-range', range);
        } catch (e) {
            /* storage unavailable */
        }
    }, [load, range]);

    const syncNow = async () => {
        setSyncing(true);
        try {
            const res = await api('/api/v1/ad-monitor/sync', { method: 'POST' });
            setToast({ message: res.message, type: res.success ? 'success' : 'error' });
            load();
        } catch (e) {
            setToast({ message: e.message, type: 'error' });
        } finally {
            setSyncing(false);
        }
    };

    const t = data?.totals;
    const clients = (data?.clients || []).filter((c) => showIdle || c.has_activity || c.alerts.some((a) => a.level === 'critical'));
    const hidden = (data?.clients?.length || 0) - clients.length;

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {toast && <Toast toast={toast} onClose={() => setToast(null)} />}

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Monitor <span className="fs-gradient-text">Iklan</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">
                        Belanja iklan, jualan sebenar funnel, ROAS dan penukaran setiap akaun iklan — siapa perlu perhatian, sekali pandang.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <select value={range} onChange={(e) => setRange(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        {RANGES.map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                        <option value="custom">Tarikh pilihan…</option>
                    </select>
                    {range === 'custom' && (
                        <>
                            <input type="date" value={custom.from} onChange={(e) => setCustom({ ...custom, from: e.target.value })} className="rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                            <input type="date" value={custom.to} onChange={(e) => setCustom({ ...custom, to: e.target.value })} className="rounded-lg border border-gray-300 px-2 py-2 text-sm" />
                        </>
                    )}
                    <button onClick={syncNow} disabled={syncing} className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {syncing ? 'Sync…' : 'Sync Meta sekarang'}
                    </button>
                </div>
            </div>

            {data?.sync && (
                <p className="mb-4 text-xs text-gray-500">
                    Sync terakhir {ago(data.sync.last_synced_at)} · automatik setiap {data.sync.every_minutes} min ({data.sync.days} hari terkini) · backfill{' '}
                    {data.sync.backfill} · {data.sync.connected} akaun
                    {data.sync.failing > 0 && <span className="font-medium text-red-600"> · {data.sync.failing} gagal</span>}
                    {t?.period && (
                        <span>
                            {' '}
                            · {t.period.from === t.period.to ? t.period.from : `${t.period.from} → ${t.period.to}`} (banding {t.period.previous_from} → {t.period.previous_to})
                        </span>
                    )}
                </p>
            )}

            {loading && !data ? (
                <div className="py-20 text-center text-gray-500">Memuatkan monitor…</div>
            ) : error ? (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
            ) : (
                <>
                    <div className="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
                        <Kpi label="Belanja" value={rm(t.spend)} now={t.spend} before={t.previous.spend} invert sub={t.daily_budget ? `bajet aktif ${rm(t.daily_budget)}/hari` : null} />
                        <Kpi label="Jualan iklan" value={rm(t.actual_value)} sub={`${num(t.actual_count)} jualan`} now={t.actual_value} before={t.previous.actual_value} />
                        <Kpi label="ROAS sebenar" value={roas(t.actual_roas)} tone={roasClass(t.actual_roas)} sub={`Meta ${roas(t.meta_roas)}`} now={t.actual_roas} before={t.previous.actual_roas} />
                        <Kpi label="Kos / jualan" value={rm(t.cost_per_sale, 2)} />
                        <Kpi label="Penukaran" value={pct(t.lpv_conversion)} sub="jualan ÷ LPV" now={t.lpv_conversion} before={t.previous.lpv_conversion} />
                        <Kpi label="Jumlah jualan funnel" value={rm(t.raised_total)} sub="semua sumber" now={t.raised_total} before={t.previous.raised_total} />
                        <Kpi
                            label="Perlu perhatian"
                            value={num(t.clients_needing_attention)}
                            tone={t.clients_needing_attention > 0 ? 'text-red-600' : 'text-green-700'}
                            sub="akaun kritikal"
                        />
                    </div>

                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
                            <h2 className="font-semibold text-gray-900">Akaun iklan</h2>
                            <label className="flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" checked={showIdle} onChange={(e) => setShowIdle(e.target.checked)} />
                                Tunjuk akaun tanpa aktiviti{hidden > 0 && !showIdle ? ` (${hidden} disembunyi)` : ''}
                            </label>
                        </div>
                        {clients.length === 0 ? (
                            <div className="py-14 text-center text-sm text-gray-500">
                                Tiada aktiviti iklan dalam tempoh ini. {data.clients.length === 0 && 'Sambungkan Business Manager di halaman Facebook Ads dahulu.'}
                            </div>
                        ) : (
                            <div className="overflow-x-auto" ref={scrollRef}>
                                <table className="w-full min-w-[1100px] text-sm tabular-nums">
                                    <thead className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                        <tr className="border-b border-gray-200">
                                            <th className="px-4 py-2.5 font-medium">Akaun</th>
                                            <th className="px-3 py-2.5 text-right font-medium">Belanja</th>
                                            <th className="px-3 py-2.5 text-right font-medium">Jualan iklan</th>
                                            <th className="px-3 py-2.5 text-right font-medium">ROAS sebenar</th>
                                            <th className="px-3 py-2.5 text-right font-medium">Kos / jualan</th>
                                            <th className="px-3 py-2.5 text-right font-medium" title="CPM kempen Sales sahaja; tanpa kempen Sales: gabungan">
                                                CPM (Sales)
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-medium" title="CTR link kempen Sales sahaja">
                                                CTR link (Sales)
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-medium">LPV</th>
                                            <th className="px-3 py-2.5 text-right font-medium">Pelawat</th>
                                            <th className="px-3 py-2.5 text-right font-medium" title="Jualan dari iklan ÷ landing page view">
                                                Penukaran
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-medium">Jumlah jualan</th>
                                            <th className="w-8 px-3 py-2.5" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {clients.map((c) => {
                                            const critical = c.alerts.filter((a) => a.level === 'critical').length;
                                            const j = judged(c);
                                            return (
                                                <Fragment key={c.id}>
                                                    <tr className="cursor-pointer border-t border-gray-100 hover:bg-gray-50" onClick={() => setOpen(open === c.id ? null : c.id)}>
                                                        <td className="max-w-[260px] px-4 py-3">
                                                            <span className="block truncate font-medium text-gray-900" title={c.name}>
                                                                {c.name}
                                                            </span>
                                                            <span className="flex flex-wrap items-center gap-1 text-xs text-gray-500">
                                                                {critical > 0 ? (
                                                                    <span className="font-medium text-red-600">● {critical} kritikal</span>
                                                                ) : c.alerts.some((a) => a.level === 'warning') ? (
                                                                    <span className="font-medium text-yellow-700">● perhatian</span>
                                                                ) : (
                                                                    <span className="text-green-700">● tiada isu</span>
                                                                )}
                                                                <span>· {c.sync_failed ? <span className="text-red-600">sync gagal</span> : `sync ${ago(c.synced_at)}`}</span>
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-3 text-right">
                                                            <span className="font-semibold text-gray-900">{rm(c.spend)}</span>
                                                            {c.daily_budget ? <span className="block text-[11px] text-gray-500">bajet {rm(c.daily_budget)}/hari</span> : null}
                                                            <Delta now={c.spend} before={c.previous.spend} invert />
                                                        </td>
                                                        <td className="px-3 py-3 text-right">
                                                            {rm(c.actual_value)}
                                                            <span className="block text-[11px] text-gray-500">{num(c.actual_count)} jualan</span>
                                                        </td>
                                                        <td className={`px-3 py-3 text-right font-semibold ${roasClass(c.actual_roas)}`}>
                                                            {roas(c.actual_roas)}
                                                            <span className="block text-[11px] font-normal text-gray-500">Meta {roas(c.meta_roas)}</span>
                                                        </td>
                                                        <td className="px-3 py-3 text-right text-gray-700">{rm(c.cost_per_sale, 2)}</td>
                                                        <td className="px-3 py-3 text-right text-gray-700">{rm(j.cpm, 2)}</td>
                                                        <td className="px-3 py-3 text-right text-gray-700">{pct(j.ctr)}</td>
                                                        <td className="px-3 py-3 text-right text-gray-700">
                                                            {num(c.lpv)}
                                                            <span className="block text-[11px] text-gray-500">{pct(c.lpv_rate)} klik</span>
                                                        </td>
                                                        <td className="px-3 py-3 text-right text-gray-700">{num(c.unique_visitors)}</td>
                                                        <td className="px-3 py-3 text-right text-gray-700">{pct(c.lpv_conversion)}</td>
                                                        <td className="px-3 py-3 text-right text-gray-900">{rm(c.raised_total)}</td>
                                                        <td className="px-3 py-3 text-gray-400">{open === c.id ? '▾' : '▸'}</td>
                                                    </tr>
                                                    {open === c.id && (
                                                        <tr>
                                                            <td colSpan={12} className="bg-gray-50 p-0">
                                                                <div className="sticky left-0" style={{ width: panelWidth || '100%' }}>
                                                                    <AccountDetail c={c} onSelectFunnel={onSelectFunnel} />
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div className="mt-5 rounded-lg border border-gray-200 bg-white">
                        <button onClick={() => setShowGuide(!showGuide)} className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-gray-900">
                            Panduan metrik &amp; sasaran
                            <span className="text-gray-400">{showGuide ? '▾' : '▸'}</span>
                        </button>
                        {showGuide && (
                            <div className="overflow-x-auto border-t border-gray-200">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">Semakan</th>
                                            <th className="px-4 py-2 font-medium text-green-700">OK</th>
                                            <th className="px-4 py-2 font-medium text-yellow-700">Amaran</th>
                                            <th className="px-4 py-2 font-medium text-red-600">Kritikal</th>
                                            <th className="px-4 py-2 font-medium">Data minimum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.diagnosis_rules.map((r) => (
                                            <tr key={r.key} className="border-t border-gray-100">
                                                <td className="px-4 py-2 font-medium text-gray-900">{r.label}</td>
                                                <td className="px-4 py-2 text-gray-700">{r.ok}</td>
                                                <td className="px-4 py-2 text-gray-700">{r.warning}</td>
                                                <td className="px-4 py-2 text-gray-700">{r.critical}</td>
                                                <td className="px-4 py-2 text-gray-500">{r.minimum}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <p className="px-4 py-3 text-xs text-gray-500">
                                    Jualan sebenar = order funnel yang dibayar (atau COD belum dibatalkan) dari sesi dengan utm_source fb/ig, dipadankan ke akaun melalui
                                    utm_campaign (ID kempen). Pastikan URL iklan guna{' '}
                                    <span className="font-mono">utm_source=fb&amp;utm_campaign={'{{campaign.id}}'}&amp;utm_term={'{{adset.id}}'}&amp;utm_content={'{{ad.id}}'}</span>.
                                </p>
                            </div>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}

function AccountDetail({ c, onSelectFunnel }) {
    const [tab, setTab] = useState('diagnosis');
    const tabs = [
        ['diagnosis', 'Diagnosis'],
        ['campaigns', `Kempen (${c.campaigns.length})`],
        ['creatives', `Creative (${c.creatives.items.length})`],
        ['funnels', `Funnel (${c.funnels.length})`],
    ];

    return (
        <div className="space-y-4 p-4">
            {c.alerts.length > 0 && (
                <ul className="space-y-1.5">
                    {c.alerts.map((a) => (
                        <li key={a.message} className="flex items-start gap-2 text-sm text-gray-700">
                            <span className={`mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold ${LEVEL[a.level].chip}`}>{LEVEL[a.level].label}</span>
                            {a.message}
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap gap-1 border-b border-gray-200">
                {tabs.map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        className={`-mb-px border-b-2 px-3 py-1.5 text-sm font-medium ${tab === key ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'diagnosis' && (
                <div className="space-y-4">
                    <Diagnosis d={c.diagnosis} />
                    <Journey c={c} />
                    <div className="grid gap-3 text-sm sm:grid-cols-4">
                        <Mini label={c.has_sales ? 'CPM Sales' : 'CPM'} value={rm(judged(c).cpm, 2)} sub={c.has_sales ? `gabungan ${rm(c.blended.cpm, 2)}` : null} />
                        <Mini label={c.has_sales ? 'Kos per klik Sales' : 'Kos per klik'} value={rm(judged(c).cost_per_click, 2)} />
                        <Mini label={c.has_sales ? 'Kos per LPV Sales' : 'Kos per LPV'} value={rm(judged(c).cost_per_lpv, 2)} />
                        <Mini label="ROAS Meta (pixel)" value={roas(c.meta_roas)} sub={`${num(c.meta_purchases)} purchase`} />
                    </div>
                </div>
            )}
            {tab === 'campaigns' && <Campaigns rows={c.campaigns} />}
            {tab === 'creatives' && <Creatives report={c.creatives} />}
            {tab === 'funnels' && <Funnels rows={c.funnels} onSelectFunnel={onSelectFunnel} />}
        </div>
    );
}

function Mini({ label, value, sub }) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white px-3 py-2">
            <span className="block text-xs text-gray-500">{label}</span>
            <span className="font-semibold text-gray-900">{value}</span>
            {sub && <span className="block text-xs text-gray-500">{sub}</span>}
        </div>
    );
}

function Diagnosis({ d }) {
    const b = d.bottleneck;
    return (
        <div className="space-y-3">
            <div className={`rounded-lg border px-4 py-3 text-sm ${b ? (b.level === 'critical' ? 'border-red-200 bg-red-50' : 'border-yellow-200 bg-yellow-50') : 'border-green-200 bg-green-50'}`}>
                {b ? (
                    <>
                        <span className="font-semibold text-gray-900">Kebocoran utama: {b.label}</span> <span className={LEVEL[b.level].text}>({fmt(b.value, b.format)})</span>
                        <span className="block text-gray-700">{b.action}</span>
                        <span className="block text-xs text-gray-500">{b.target}</span>
                    </>
                ) : (
                    <span className="font-medium text-green-800">Tiada kebocoran utama dikesan dalam funnel iklan ({d.basis === 'sales' ? 'kempen Sales' : 'gabungan'}).</span>
                )}
            </div>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                {d.findings.map((f) => (
                    <div key={f.key} className="rounded-lg border border-gray-200 bg-white px-3 py-2">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-xs text-gray-500">{f.label}</span>
                            <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ${LEVEL[f.level].chip}`}>{LEVEL[f.level].label}</span>
                        </div>
                        <span className="block text-lg font-semibold text-gray-900">{fmt(f.value, f.format)}</span>
                        <span className="block text-[11px] text-gray-500">{f.target}</span>
                        {f.action && f.level !== 'ok' && <span className="mt-1 block text-xs text-gray-700">{f.action}</span>}
                    </div>
                ))}
            </div>
        </div>
    );
}

function Journey({ c }) {
    const steps = [
        ['Impressions', c.impressions, null],
        ['Klik pautan', c.link_clicks, `CTR link ${pct(c.ctr)}`],
        ['Landing page view', c.lpv, `${pct(c.lpv_rate)} daripada klik`],
        ['Pelawat unik funnel', c.unique_visitors, null],
        ['Checkout', c.checkout_people, null],
        ['Jualan', c.completed_people, `${pct(c.conversion_rate)} pelawat · online ${pct(c.payment_success_rate)} bayar`],
    ];
    return (
        <div>
            <p className="mb-2 text-sm font-medium text-gray-900">
                Perjalanan pembeli <span className="text-xs font-normal text-gray-500">· semua kempen</span>
            </p>
            <ol className="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                {steps.map(([label, value, rate]) => (
                    <li key={label} className="rounded-lg border border-gray-200 bg-white p-3">
                        <p className="text-xs text-gray-500">{label}</p>
                        <p className="text-lg font-semibold tabular-nums text-gray-900">{num(value)}</p>
                        {rate && <p className="text-xs text-gray-500">{rate}</p>}
                    </li>
                ))}
            </ol>
        </div>
    );
}

function Campaigns({ rows }) {
    if (rows.length === 0) return <p className="text-sm text-gray-500">Tiada kempen dengan belanja dalam tempoh ini.</p>;
    return (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table className="w-full min-w-[900px] text-sm tabular-nums">
                <thead className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th className="px-3 py-2 font-medium">Kempen</th>
                        <th className="px-3 py-2 font-medium">Lapisan</th>
                        <th className="px-3 py-2 text-right font-medium">Belanja</th>
                        <th className="px-3 py-2 text-right font-medium">% bajet</th>
                        <th className="px-3 py-2 text-right font-medium">Bajet harian</th>
                        <th className="px-3 py-2 text-right font-medium">CPM</th>
                        <th className="px-3 py-2 text-right font-medium">CTR link</th>
                        <th className="px-3 py-2 text-right font-medium">LPV</th>
                        <th className="px-3 py-2 text-right font-medium">Purchase (Meta)</th>
                        <th className="px-3 py-2 text-right font-medium">ROAS Meta</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.campaign_id} className="border-t border-gray-100">
                            <td className="max-w-xs px-3 py-2">
                                <span className="block truncate text-gray-900" title={r.name}>
                                    {r.name}
                                </span>
                                {r.role && <span className="text-[11px] text-gray-500">{r.role}</span>}
                            </td>
                            <td className="px-3 py-2">
                                <span className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${LAYER[r.layer] || LAYER.lain}`}>{r.layer_label}</span>
                            </td>
                            <td className="px-3 py-2 text-right font-medium text-gray-900">{rm(r.spend)}</td>
                            <td className="px-3 py-2 text-right text-gray-600">{pct(r.share_of_spend)}</td>
                            <td className="px-3 py-2 text-right text-gray-600">{r.daily_budget ? rm(r.daily_budget) : '—'}</td>
                            <td className="px-3 py-2 text-right text-gray-600">{rm(r.cpm, 2)}</td>
                            <td className="px-3 py-2 text-right text-gray-600">{pct(r.ctr)}</td>
                            <td className="px-3 py-2 text-right text-gray-600">{num(r.lpv)}</td>
                            <td className="px-3 py-2 text-right text-gray-600">{num(r.meta_purchases)}</td>
                            <td className={`px-3 py-2 text-right font-medium ${roasClass(r.meta_roas)}`}>{roas(r.meta_roas)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Creatives({ report }) {
    const [openKey, setOpenKey] = useState(null);
    if (report.items.length === 0) return <p className="text-sm text-gray-500">Tiada data creative dalam tempoh ini.</p>;

    return (
        <div className="space-y-3">
            <p className="text-xs text-gray-500">
                Iklan yang guna video/imej sama digabungkan. ROAS dinilai pada{' '}
                <span className="font-semibold">{report.roas_basis === 'actual' ? 'jualan sebenar funnel' : 'purchase pixel Meta'}</span>
                {report.unmatched_count > 0 && ` · ${report.unmatched_count} jualan (${rm(report.unmatched_value)}) tidak dapat dipadankan ke iklan`}
                {!report.has_creative_data && ' · gambar creative sedang disync'}
            </p>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {report.items.map((cr) => (
                    <div key={cr.key} className="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div className="flex gap-3 p-3">
                            {cr.thumbnail_url ? (
                                <img src={cr.thumbnail_url} alt="" className="h-20 w-20 shrink-0 rounded object-cover" loading="lazy" />
                            ) : (
                                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded bg-gray-100 text-xs text-gray-400">{cr.format || 'iklan'}</div>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-1">
                                    <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ${VERDICT[cr.verdict.key]}`}>{cr.verdict.label}</span>
                                    {cr.fatigue && <span className="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">Letih −{cr.fatigue.drop}% CTR</span>}
                                    {cr.status === 'ACTIVE' ? (
                                        <span className="text-[10px] text-green-700">● {cr.active_ads} aktif</span>
                                    ) : (
                                        cr.status && <span className="text-[10px] text-gray-500">{String(cr.status).toLowerCase()}</span>
                                    )}
                                </div>
                                <p className="mt-1 truncate text-sm font-medium text-gray-900" title={cr.name}>
                                    {cr.name}
                                </p>
                                <p className="text-[11px] text-gray-500">
                                    {cr.ads} iklan · {pct(cr.share_of_spend)} belanja
                                    {cr.permalink && (
                                        <>
                                            {' · '}
                                            <a href={cr.permalink} target="_blank" rel="noreferrer" className="text-orange-600 hover:underline">
                                                lihat
                                            </a>
                                        </>
                                    )}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-4 gap-1 border-t border-gray-100 px-3 py-2 text-center text-xs">
                            <Stat label="Belanja" value={rm(cr.spend)} />
                            <Stat label="ROAS" value={roas(cr.roas)} tone={roasClass(cr.roas)} />
                            <Stat label="Jualan" value={num(cr.results)} />
                            <Stat label="CTR" value={pct(cr.ctr)} level={cr.levels.ctr} />
                            <Stat label="CPM" value={rm(cr.cpm)} level={cr.levels.cpm} />
                            <Stat label="Hook" value={pct(cr.hook_rate)} level={cr.levels.hook} />
                            <Stat label="Hold" value={pct(cr.hold_rate)} level={cr.levels.hold} />
                            <Stat label="Penukaran" value={pct(cr.conversion)} level={cr.levels.conversion} />
                        </div>
                        {cr.issue && <p className={`border-t border-gray-100 px-3 py-2 text-xs ${LEVEL[cr.issue.level].text}`}>{cr.issue.action}</p>}
                        <button onClick={() => setOpenKey(openKey === cr.key ? null : cr.key)} className="border-t border-gray-100 px-3 py-1.5 text-left text-[11px] font-medium text-orange-600">
                            {openKey === cr.key ? 'Sembunyi' : 'Di mana ia berjalan'} ({cr.placements.length})
                        </button>
                        {openKey === cr.key && (
                            <ul className="space-y-1 border-t border-gray-100 px-3 py-2 text-[11px]">
                                {cr.placements.map((p) => (
                                    <li key={p.ad_id} className="flex justify-between gap-2">
                                        <span className="min-w-0 truncate text-gray-700" title={`${p.campaign} › ${p.adset} › ${p.ad_name}`}>
                                            {p.role} · {p.adset}
                                        </span>
                                        <span className="shrink-0 tabular-nums text-gray-500">
                                            {rm(p.spend)} · {roas(p.roas)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

function Stat({ label, value, level, tone }) {
    const color = tone || (level ? LEVEL[level]?.text : 'text-gray-900');
    return (
        <div>
            <span className={`block font-semibold tabular-nums ${color}`}>{value}</span>
            <span className="block text-[10px] text-gray-500">{label}</span>
        </div>
    );
}

function Funnels({ rows, onSelectFunnel }) {
    if (rows.length === 0) return <p className="text-sm text-gray-500">Tiada funnel menerima trafik dari akaun ini dalam tempoh ini.</p>;
    return (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table className="w-full min-w-[640px] text-sm tabular-nums">
                <thead className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th className="px-3 py-2 font-medium">Funnel</th>
                        <th className="px-3 py-2 text-right font-medium">Pelawat (iklan)</th>
                        <th className="px-3 py-2 text-right font-medium">Checkout</th>
                        <th className="px-3 py-2 text-right font-medium">Jualan</th>
                        <th className="px-3 py-2 text-right font-medium">Penukaran</th>
                        <th className="px-3 py-2 text-right font-medium">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((f) => (
                        <tr key={f.uuid} className="border-t border-gray-100">
                            <td className="px-3 py-2">
                                <button onClick={() => onSelectFunnel?.({ uuid: f.uuid, name: f.name }, 'analytics')} className="text-left font-medium text-gray-900 hover:text-orange-600">
                                    {f.name}
                                </button>
                                <span className="ml-2 text-[11px] text-gray-500">
                                    {f.linked ? 'dipautkan' : 'dari UTM'}
                                    {f.status !== 'published' && ` · ${f.status}`}
                                </span>
                            </td>
                            <td className="px-3 py-2 text-right text-gray-700">{num(f.unique_visitors)}</td>
                            <td className="px-3 py-2 text-right text-gray-700">{num(f.checkout_people)}</td>
                            <td className="px-3 py-2 text-right text-gray-700">{num(f.completed)}</td>
                            <td className="px-3 py-2 text-right text-gray-700">{pct(f.conversion_rate)}</td>
                            <td className="px-3 py-2 text-right font-medium text-gray-900">{rm(f.raised)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

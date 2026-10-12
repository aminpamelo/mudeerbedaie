/**
 * Studio Ads Manager — run Facebook ads from the platform.
 *  - Campaigns: drill campaign → ad set → ad with FB ROAS vs funnel ROAS,
 *    pause/resume toggles and inline daily-budget edits (always confirmed).
 *  - Launch: guided campaign → ad set → creative → ad builder (created PAUSED,
 *    UTM-tagged so funnel sales attribute back to each ad).
 *  - Rules: automated pause / activate / budget rules, hourly, dry-run first.
 *  - Activity: audit log of every write (manual, launch, rule).
 * Requires a connection token with ads_management; admin + employee only.
 */

import React, { useState, useEffect, useCallback } from 'react';

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

const apiHeaders = (json = true) => ({
    ...(json ? { 'Content-Type': 'application/json' } : {}),
    Accept: 'application/json',
    'X-XSRF-TOKEN': getCsrfToken(),
});

const api = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: apiHeaders(), ...options });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(firstError || data.message || 'Request failed');
    }
    return data;
};

const money = (value, currency = 'MYR') =>
    value === null || value === undefined
        ? '—'
        : `${currency === 'MYR' ? 'RM' : currency} ${Number(value).toLocaleString('en-MY', { maximumFractionDigits: 2 })}`;

const num = (value) => (value === null || value === undefined ? '—' : Number(value).toLocaleString('en-MY'));

const roasClass = (value) => {
    if (value === null || value === undefined) return 'text-gray-400';
    if (value >= 2) return 'text-green-700 font-semibold';
    if (value >= 1) return 'text-yellow-700 font-semibold';
    return 'text-red-600 font-semibold';
};

const DATE_PRESETS = [
    ['today', 'Today'],
    ['yesterday', 'Yesterday'],
    ['last_3d', 'Last 3 days'],
    ['last_7d', 'Last 7 days'],
    ['last_14d', 'Last 14 days'],
    ['last_30d', 'Last 30 days'],
];

const LEVEL_LABEL = { campaign: 'Campaign', adset: 'Ad set', ad: 'Ad' };

const STATUS_BADGE = {
    ACTIVE: 'bg-green-100 text-green-800',
    PAUSED: 'bg-gray-100 text-gray-600',
    CAMPAIGN_PAUSED: 'bg-gray-100 text-gray-600',
    ADSET_PAUSED: 'bg-gray-100 text-gray-600',
    WITH_ISSUES: 'bg-red-100 text-red-700',
    DISAPPROVED: 'bg-red-100 text-red-700',
    PENDING_REVIEW: 'bg-yellow-100 text-yellow-800',
    IN_PROCESS: 'bg-yellow-100 text-yellow-800',
};

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

function Modal({ title, onClose, children, footer, wide = false }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div
                className={`flex max-h-[90vh] w-full ${wide ? 'max-w-2xl' : 'max-w-md'} flex-col rounded-xl bg-white shadow-xl`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Close">
                        ✕
                    </button>
                </div>
                <div className="overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-gray-200 px-5 py-3">{footer}</div>}
            </div>
        </div>
    );
}

function Field({ label, hint, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-gray-500">{hint}</span>}
        </label>
    );
}

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm';

export default function StudioAdsManager() {
    const [accounts, setAccounts] = useState([]);
    const [accountId, setAccountId] = useState(() => window.localStorage?.getItem('fs-ads-account') || '');
    const [tab, setTab] = useState('campaigns');
    const [toast, setToast] = useState(null);
    const [loadingAccounts, setLoadingAccounts] = useState(true);
    const [showLaunch, setShowLaunch] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    const showToast = useCallback((message, type = 'success') => setToast({ message, type }), []);

    useEffect(() => {
        api('/api/v1/facebook-ads/manage/accounts')
            .then((data) => {
                const list = data.data || [];
                setAccounts(list);
                if (!list.some((a) => String(a.id) === String(accountId))) {
                    const preferred = list.find((a) => a.can_manage) || list[0];
                    setAccountId(preferred ? String(preferred.id) : '');
                }
            })
            .catch((e) => showToast(e.message, 'error'))
            .finally(() => setLoadingAccounts(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        try {
            if (accountId) window.localStorage?.setItem('fs-ads-account', accountId);
        } catch (e) {
            /* storage unavailable */
        }
    }, [accountId]);

    const account = accounts.find((a) => String(a.id) === String(accountId));

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {toast && <Toast toast={toast} onClose={() => setToast(null)} />}

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="fs-display text-2xl font-bold tracking-tight text-gray-900">
                        Ads <span className="fs-gradient-text">Manager</span>
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-500">
                        Run your Facebook ads from the Studio — pause, scale, launch and automate, judged on real funnel sales.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <select value={accountId} onChange={(e) => setAccountId(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        {accounts.length === 0 && <option value="">No ad accounts</option>}
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name} · act_{a.account_id}
                                {a.can_manage ? '' : ' (read-only)'}
                            </option>
                        ))}
                    </select>
                    <button
                        onClick={() => setShowLaunch(true)}
                        disabled={!account?.can_manage}
                        className="fs-cta rounded-lg px-4 py-2 font-medium text-white disabled:opacity-50"
                    >
                        + Launch Campaign
                    </button>
                </div>
            </div>

            {loadingAccounts ? (
                <div className="py-20 text-center text-gray-500">Loading ad accounts...</div>
            ) : accounts.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-12 text-center">
                    <h3 className="mb-2 text-lg font-medium text-gray-900">No ad accounts yet</h3>
                    <p className="mb-6 text-sm text-gray-500">Connect a Business Manager on the Facebook Ads page first.</p>
                    <a href="/funnel-builder/facebook-ads" className="fs-cta rounded-lg px-5 py-2.5 font-medium text-white">
                        Go to Facebook Ads
                    </a>
                </div>
            ) : (
                <>
                    {account && !account.can_manage && (
                        <div className="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                            <span className="font-semibold">Read-only token.</span> You can view performance, but to pause, edit budgets,
                            launch or automate, re-generate the System User token in Business Manager with{' '}
                            <span className="font-mono text-xs">ads_management</span> ticked and paste it via{' '}
                            <a href="/funnel-builder/facebook-ads" className="underline">
                                Facebook Ads → Edit
                            </a>
                            .
                        </div>
                    )}

                    <div className="mb-4 flex gap-1 border-b border-gray-200">
                        {[
                            ['campaigns', 'Campaigns'],
                            ['rules', 'Automation Rules'],
                            ['activity', 'Activity Log'],
                        ].map(([key, label]) => (
                            <button
                                key={key}
                                onClick={() => setTab(key)}
                                className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${
                                    tab === key ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {tab === 'campaigns' && account && (
                        <CampaignsTab key={`${account.id}-${refreshKey}`} account={account} showToast={showToast} />
                    )}
                    {tab === 'rules' && <RulesTab accounts={accounts} defaultAccountId={account?.id} showToast={showToast} />}
                    {tab === 'activity' && <ActivityTab accountId={account?.id} />}
                </>
            )}

            {showLaunch && account && (
                <LaunchWizard
                    account={account}
                    onClose={() => setShowLaunch(false)}
                    onLaunched={(message) => {
                        setShowLaunch(false);
                        showToast(message);
                        setTab('campaigns');
                        setRefreshKey((k) => k + 1);
                    }}
                />
            )}
        </div>
    );
}

/* ───────────────────────────── Campaigns ───────────────────────────── */

function CampaignsTab({ account, showToast }) {
    const [datePreset, setDatePreset] = useState('last_7d');
    const [path, setPath] = useState([]); // [{level, id, name}]
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [confirm, setConfirm] = useState(null);
    const [busy, setBusy] = useState(false);

    const level = path.length === 0 ? 'campaign' : path.length === 1 ? 'adset' : 'ad';
    const parentId = path.length ? path[path.length - 1].id : null;

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams({ level, date_preset: datePreset });
            if (parentId) params.set('parent_id', parentId);
            const data = await api(`/api/v1/facebook-ads/manage/accounts/${account.id}/entities?${params}`);
            setRows(data.data || []);
        } catch (e) {
            setError(e.message);
            setRows([]);
        } finally {
            setLoading(false);
        }
    }, [account.id, level, parentId, datePreset]);

    useEffect(() => {
        load();
    }, [load]);

    const totals = rows.reduce(
        (acc, r) => ({
            spend: acc.spend + (r.spend || 0),
            purchase_value: acc.purchase_value + (r.purchase_value || 0),
            funnel_revenue: acc.funnel_revenue + (r.funnel_revenue || 0),
            purchases: acc.purchases + (r.purchases || 0),
            funnel_orders: acc.funnel_orders + (r.funnel_orders || 0),
        }),
        { spend: 0, purchase_value: 0, funnel_revenue: 0, purchases: 0, funnel_orders: 0 },
    );

    const runConfirmed = async () => {
        setBusy(true);
        try {
            const { kind, row, value } = confirm;
            const endpoint = kind === 'status' ? 'status' : 'budget';
            const body =
                kind === 'status'
                    ? { object_type: row.level, object_id: row.id, object_name: row.name, status: value }
                    : { object_type: row.level, object_id: row.id, object_name: row.name, daily_budget: Number(value), current_budget: row.daily_budget };
            const data = await api(`/api/v1/facebook-ads/manage/accounts/${account.id}/${endpoint}`, {
                method: 'POST',
                body: JSON.stringify(body),
            });
            showToast(data.message);
            setConfirm(null);
            load();
        } catch (e) {
            showToast(e.message, 'error');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <nav className="flex flex-wrap items-center gap-1 text-sm">
                    <button onClick={() => setPath([])} className={path.length ? 'text-orange-600 hover:underline' : 'font-semibold text-gray-900'}>
                        All campaigns
                    </button>
                    {path.map((p, i) => (
                        <React.Fragment key={p.id}>
                            <span className="text-gray-400">/</span>
                            <button
                                onClick={() => setPath(path.slice(0, i + 1))}
                                className={i === path.length - 1 ? 'font-semibold text-gray-900' : 'text-orange-600 hover:underline'}
                            >
                                {p.name}
                            </button>
                        </React.Fragment>
                    ))}
                </nav>
                <div className="flex items-center gap-2">
                    <select value={datePreset} onChange={(e) => setDatePreset(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                        {DATE_PRESETS.map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <button onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Refresh
                    </button>
                </div>
            </div>

            <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <Stat label="Spend" value={money(totals.spend, account.currency)} />
                <Stat
                    label="FB ROAS"
                    value={totals.spend > 0 ? (totals.purchase_value / totals.spend).toFixed(2) : '—'}
                    sub={`${num(totals.purchases)} FB purchases`}
                />
                <Stat label="Funnel revenue" value={money(totals.funnel_revenue, account.currency)} sub={`${num(totals.funnel_orders)} funnel orders`} />
                <Stat
                    label="Funnel ROAS"
                    value={totals.spend > 0 ? (totals.funnel_revenue / totals.spend).toFixed(2) : '—'}
                    sub="Real sales ÷ spend"
                />
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                {loading ? (
                    <div className="py-16 text-center text-sm text-gray-500">Loading from Facebook...</div>
                ) : error ? (
                    <div className="px-5 py-10 text-center text-sm text-red-600">{error}</div>
                ) : rows.length === 0 ? (
                    <div className="py-16 text-center text-sm text-gray-500">No {LEVEL_LABEL[level].toLowerCase()}s found.</div>
                ) : (
                    <table className="w-full min-w-[1000px] text-sm">
                        <thead>
                            <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                <th className="w-14 px-4 py-2.5 font-medium">On</th>
                                <th className="px-4 py-2.5 font-medium">{LEVEL_LABEL[level]}</th>
                                <th className="px-4 py-2.5 text-right font-medium">Daily budget</th>
                                <th className="px-4 py-2.5 text-right font-medium">Spend</th>
                                <th className="px-4 py-2.5 text-right font-medium">CTR</th>
                                <th className="px-4 py-2.5 text-right font-medium">CPC</th>
                                <th className="px-4 py-2.5 text-right font-medium">FB purch.</th>
                                <th className="px-4 py-2.5 text-right font-medium">FB ROAS</th>
                                <th className="px-4 py-2.5 text-right font-medium">Funnel rev.</th>
                                <th className="px-4 py-2.5 text-right font-medium">Funnel ROAS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t border-gray-100">
                                    <td className="px-4 py-3">
                                        <Toggle
                                            on={row.status === 'ACTIVE'}
                                            disabled={!account.can_manage}
                                            onChange={() =>
                                                setConfirm({ kind: 'status', row, value: row.status === 'ACTIVE' ? 'PAUSED' : 'ACTIVE' })
                                            }
                                        />
                                    </td>
                                    <td className="max-w-xs px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            {row.thumbnail && <img src={row.thumbnail} alt="" className="h-9 w-9 shrink-0 rounded object-cover" />}
                                            <div className="min-w-0">
                                                {level !== 'ad' ? (
                                                    <button
                                                        onClick={() => setPath([...path, { level, id: row.id, name: row.name }])}
                                                        className="block truncate text-left font-medium text-gray-900 hover:text-orange-600"
                                                        title={row.name}
                                                    >
                                                        {row.name}
                                                    </button>
                                                ) : (
                                                    <span className="block truncate font-medium text-gray-900" title={row.name}>
                                                        {row.name}
                                                    </span>
                                                )}
                                                <span className={`mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${STATUS_BADGE[row.effective_status] || 'bg-gray-100 text-gray-600'}`}>
                                                    {String(row.effective_status || '').replace(/_/g, ' ')}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {row.daily_budget !== null ? (
                                            <button
                                                disabled={!account.can_manage}
                                                onClick={() => setConfirm({ kind: 'budget', row, value: row.daily_budget })}
                                                className="rounded px-1.5 py-0.5 text-gray-900 hover:bg-gray-100 disabled:hover:bg-transparent"
                                                title="Edit daily budget"
                                            >
                                                {money(row.daily_budget, account.currency)} ✎
                                            </button>
                                        ) : row.lifetime_budget !== null ? (
                                            <span className="text-gray-500">{money(row.lifetime_budget, account.currency)} lifetime</span>
                                        ) : (
                                            <span className="text-gray-400">{level === 'adset' ? 'Campaign budget' : '—'}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">{money(row.spend, account.currency)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums text-gray-600">{row.ctr !== null ? `${row.ctr}%` : '—'}</td>
                                    <td className="px-4 py-3 text-right tabular-nums text-gray-600">{money(row.cpc, account.currency)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums text-gray-600">{num(row.purchases)}</td>
                                    <td className={`px-4 py-3 text-right tabular-nums ${roasClass(row.fb_roas)}`}>{row.fb_roas ?? '—'}</td>
                                    <td className="px-4 py-3 text-right tabular-nums text-gray-600">
                                        {money(row.funnel_revenue, account.currency)}
                                        <span className="block text-[11px] text-gray-400">{row.funnel_orders} orders</span>
                                    </td>
                                    <td className={`px-4 py-3 text-right tabular-nums ${roasClass(row.funnel_roas)}`}>{row.funnel_roas ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
            <p className="mt-2 text-xs text-gray-500">
                Funnel ROAS counts real funnel orders tagged with this {LEVEL_LABEL[level].toLowerCase()}'s id. Campaigns launched from the Studio are
                tagged automatically; for older ads add URL parameters{' '}
                <span className="font-mono">utm_campaign={'{{campaign.id}}'}&utm_term={'{{adset.id}}'}&utm_content={'{{ad.id}}'}</span>.
            </p>

            {confirm && (
                <ConfirmChange
                    confirm={confirm}
                    currency={account.currency}
                    busy={busy}
                    onChange={(value) => setConfirm({ ...confirm, value })}
                    onCancel={() => setConfirm(null)}
                    onConfirm={runConfirmed}
                />
            )}
        </div>
    );
}

function Stat({ label, value, sub }) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <span className="block text-[11px] uppercase tracking-wider text-gray-500">{label}</span>
            <span className="mt-1 block text-xl font-bold tabular-nums text-gray-900">{value}</span>
            {sub && <span className="block text-xs text-gray-500">{sub}</span>}
        </div>
    );
}

function Toggle({ on, disabled, onChange }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            disabled={disabled}
            onClick={onChange}
            className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition disabled:opacity-40 ${on ? 'bg-orange-500' : 'bg-gray-300'}`}
        >
            <span className={`inline-block h-4 w-4 rounded-full bg-white shadow transition ${on ? 'translate-x-4' : 'translate-x-0.5'}`} />
        </button>
    );
}

function ConfirmChange({ confirm, currency, busy, onChange, onCancel, onConfirm }) {
    const { kind, row, value } = confirm;
    const isStatus = kind === 'status';
    const change = !isStatus && row.daily_budget ? ((Number(value) - row.daily_budget) / row.daily_budget) * 100 : 0;

    return (
        <Modal
            title={isStatus ? (value === 'ACTIVE' ? 'Turn on?' : 'Pause?') : 'Change daily budget'}
            onClose={onCancel}
            footer={
                <>
                    <button onClick={onCancel} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Cancel
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={busy || (!isStatus && (!value || Number(value) < 1))}
                        className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {busy ? 'Saving...' : 'Confirm'}
                    </button>
                </>
            }
        >
            <p className="mb-3 text-sm text-gray-600">
                {LEVEL_LABEL[row.level]} <span className="font-semibold text-gray-900">{row.name}</span>
            </p>
            {isStatus ? (
                <p className="text-sm text-gray-600">
                    {value === 'ACTIVE'
                        ? 'This will start delivering and spending money on Facebook right away.'
                        : 'Delivery stops immediately. You can turn it back on any time.'}
                </p>
            ) : (
                <div className="space-y-2">
                    <Field label={`New daily budget (${currency})`} hint={`Current: ${money(row.daily_budget, currency)}`}>
                        <input type="number" min="1" step="0.01" value={value} onChange={(e) => onChange(e.target.value)} className={inputClass} />
                    </Field>
                    {Math.abs(change) >= 0.5 && (
                        <p className={`text-xs ${change > 0 ? 'text-orange-700' : 'text-gray-600'}`}>
                            {change > 0 ? '+' : ''}
                            {change.toFixed(0)}% vs current
                            {change > 30 && ' — big jumps can reset Facebook\'s learning phase; +20–30% steps are safer.'}
                        </p>
                    )}
                </div>
            )}
        </Modal>
    );
}

/* ───────────────────────────── Launch wizard ───────────────────────────── */

const CTAS = [
    ['SHOP_NOW', 'Shop Now'],
    ['ORDER_NOW', 'Order Now'],
    ['BUY_NOW', 'Buy Now'],
    ['LEARN_MORE', 'Learn More'],
    ['SIGN_UP', 'Sign Up'],
    ['GET_OFFER', 'Get Offer'],
    ['SUBSCRIBE', 'Subscribe'],
    ['CONTACT_US', 'Contact Us'],
];

function LaunchWizard({ account, onClose, onLaunched }) {
    const [step, setStep] = useState(0);
    const [options, setOptions] = useState(null);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [imagePreview, setImagePreview] = useState(null);
    const [form, setForm] = useState({
        name: '',
        objective: 'OUTCOME_SALES',
        funnel_id: '',
        link: '',
        daily_budget: 30,
        page_id: '',
        pixel_id: '',
        countries: 'MY',
        age_min: 18,
        age_max: 65,
        advantage_audience: true,
        primary_text: '',
        headline: '',
        description: '',
        call_to_action: 'SHOP_NOW',
        image_hash: '',
    });

    const set = (patch) => setForm((f) => ({ ...f, ...patch }));

    useEffect(() => {
        api(`/api/v1/facebook-ads/manage/accounts/${account.id}/launch-options`)
            .then((data) => {
                setOptions(data.data);
                set({ page_id: data.data.pages[0]?.id || '', pixel_id: data.data.pixels[0]?.id || '' });
            })
            .catch((e) => setError(e.message));
    }, [account.id]);

    const pickFunnel = (id) => {
        const funnel = options?.funnels.find((f) => String(f.id) === String(id));
        set({
            funnel_id: id,
            link: funnel?.url || form.link,
            name: form.name || (funnel ? `${funnel.name} — ${new Date().toLocaleDateString('en-MY')}` : ''),
            pixel_id: funnel?.pixel_id && options?.pixels.some((p) => p.id === funnel.pixel_id) ? funnel.pixel_id : form.pixel_id,
        });
    };

    const upload = async (file) => {
        if (!file) return;
        setUploading(true);
        setError(null);
        setImagePreview(URL.createObjectURL(file));
        try {
            const body = new FormData();
            body.append('image', file);
            const response = await fetch(`/api/v1/facebook-ads/manage/accounts/${account.id}/images`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: apiHeaders(false),
                body,
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Upload failed');
            set({ image_hash: data.hash });
        } catch (e) {
            setError(e.message);
            setImagePreview(null);
        } finally {
            setUploading(false);
        }
    };

    const stepValid = [
        form.name && form.link && Number(form.daily_budget) >= 1,
        form.countries.trim() && Number(form.age_min) >= 18 && Number(form.age_max) >= Number(form.age_min),
        form.page_id && (form.objective !== 'OUTCOME_SALES' || form.pixel_id) && form.image_hash && form.primary_text && form.headline,
        true,
    ];

    const submit = async () => {
        setBusy(true);
        setError(null);
        try {
            const payload = {
                ...form,
                funnel_id: form.funnel_id || null,
                pixel_id: form.objective === 'OUTCOME_SALES' ? form.pixel_id : null,
                description: form.description || null,
                daily_budget: Number(form.daily_budget),
                age_min: Number(form.age_min),
                age_max: Number(form.age_max),
                countries: form.countries
                    .split(',')
                    .map((c) => c.trim().toUpperCase())
                    .filter(Boolean),
            };
            const data = await api(`/api/v1/facebook-ads/manage/accounts/${account.id}/launch`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            onLaunched(data.message);
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    };

    const STEPS = ['Campaign', 'Audience', 'Creative', 'Review'];

    return (
        <Modal
            wide
            title="Launch campaign"
            onClose={onClose}
            footer={
                <>
                    {step > 0 && (
                        <button onClick={() => setStep(step - 1)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                            Back
                        </button>
                    )}
                    {step < 3 ? (
                        <button
                            onClick={() => setStep(step + 1)}
                            disabled={!stepValid[step] || uploading}
                            className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            Next
                        </button>
                    ) : (
                        <button onClick={submit} disabled={busy} className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {busy ? 'Creating on Facebook...' : 'Create campaign (paused)'}
                        </button>
                    )}
                </>
            }
        >
            <ol className="mb-5 flex gap-2 text-xs">
                {STEPS.map((label, i) => (
                    <li
                        key={label}
                        className={`flex-1 rounded-full px-2 py-1 text-center font-medium ${
                            i === step ? 'bg-orange-500 text-white' : i < step ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-500'
                        }`}
                    >
                        {i + 1}. {label}
                    </li>
                ))}
            </ol>

            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

            {!options ? (
                <div className="py-10 text-center text-sm text-gray-500">Loading pages and pixels...</div>
            ) : (
                <div className="space-y-4">
                    {step === 0 && (
                        <>
                            <Field label="Funnel" hint="Picks the landing URL (and the funnel's pixel) automatically.">
                                <select value={form.funnel_id} onChange={(e) => pickFunnel(e.target.value)} className={inputClass}>
                                    <option value="">— Custom URL —</option>
                                    {options.funnels.map((f) => (
                                        <option key={f.id} value={f.id}>
                                            {f.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Landing page URL">
                                <input value={form.link} onChange={(e) => set({ link: e.target.value })} placeholder="https://" className={inputClass} />
                            </Field>
                            <Field label="Campaign name">
                                <input value={form.name} onChange={(e) => set({ name: e.target.value })} className={inputClass} />
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Objective">
                                    <select value={form.objective} onChange={(e) => set({ objective: e.target.value })} className={inputClass}>
                                        <option value="OUTCOME_SALES">Sales (optimise for purchases)</option>
                                        <option value="OUTCOME_TRAFFIC">Traffic (landing page views)</option>
                                    </select>
                                </Field>
                                <Field label={`Daily budget (${account.currency})`} hint="Campaign budget (CBO).">
                                    <input type="number" min="1" value={form.daily_budget} onChange={(e) => set({ daily_budget: e.target.value })} className={inputClass} />
                                </Field>
                            </div>
                        </>
                    )}

                    {step === 1 && (
                        <>
                            <Field label="Countries" hint="2-letter codes, comma separated (e.g. MY, SG, BN).">
                                <input value={form.countries} onChange={(e) => set({ countries: e.target.value })} className={inputClass} />
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Min age">
                                    <input type="number" min="18" max="65" value={form.age_min} onChange={(e) => set({ age_min: e.target.value })} className={inputClass} />
                                </Field>
                                <Field label="Max age">
                                    <input type="number" min="18" max="65" value={form.age_max} onChange={(e) => set({ age_max: e.target.value })} className={inputClass} />
                                </Field>
                            </div>
                            <label className="flex items-start gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={form.advantage_audience}
                                    onChange={(e) => set({ advantage_audience: e.target.checked })}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="font-medium">Advantage+ audience</span>
                                    <span className="block text-xs text-gray-500">Let Facebook expand beyond your settings when it finds buyers. Recommended.</span>
                                </span>
                            </label>
                        </>
                    )}

                    {step === 2 && (
                        <>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Facebook Page">
                                    <select value={form.page_id} onChange={(e) => set({ page_id: e.target.value })} className={inputClass}>
                                        {options.pages.length === 0 && <option value="">No pages available</option>}
                                        {options.pages.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                {form.objective === 'OUTCOME_SALES' && (
                                    <Field label="Pixel">
                                        <select value={form.pixel_id} onChange={(e) => set({ pixel_id: e.target.value })} className={inputClass}>
                                            {options.pixels.length === 0 && <option value="">No pixels available</option>}
                                            {options.pixels.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name} · {p.id}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                )}
                            </div>
                            <Field label="Image" hint="JPG/PNG, square 1080×1080 or 4:5 works best.">
                                <div className="flex items-center gap-3">
                                    {imagePreview && <img src={imagePreview} alt="" className="h-16 w-16 rounded object-cover" />}
                                    <input type="file" accept="image/jpeg,image/png" onChange={(e) => upload(e.target.files?.[0])} className="text-sm" />
                                    {uploading && <span className="text-xs text-gray-500">Uploading...</span>}
                                    {form.image_hash && !uploading && <span className="text-xs text-green-700">✓ Uploaded</span>}
                                </div>
                            </Field>
                            <Field label="Primary text">
                                <textarea rows={4} value={form.primary_text} onChange={(e) => set({ primary_text: e.target.value })} className={inputClass} />
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Headline">
                                    <input value={form.headline} onChange={(e) => set({ headline: e.target.value })} className={inputClass} />
                                </Field>
                                <Field label="Button">
                                    <select value={form.call_to_action} onChange={(e) => set({ call_to_action: e.target.value })} className={inputClass}>
                                        {CTAS.map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <Field label="Description (optional)">
                                <input value={form.description} onChange={(e) => set({ description: e.target.value })} className={inputClass} />
                            </Field>
                        </>
                    )}

                    {step === 3 && (
                        <div className="space-y-3 text-sm">
                            <ReviewRow label="Campaign" value={`${form.name} · ${form.objective === 'OUTCOME_SALES' ? 'Sales' : 'Traffic'}`} />
                            <ReviewRow label="Budget" value={`${money(form.daily_budget, account.currency)} / day`} />
                            <ReviewRow label="Audience" value={`${form.countries.toUpperCase()} · ${form.age_min}–${form.age_max}${form.advantage_audience ? ' · Advantage+' : ''}`} />
                            <ReviewRow label="Page" value={options.pages.find((p) => p.id === form.page_id)?.name} />
                            {form.objective === 'OUTCOME_SALES' && <ReviewRow label="Pixel" value={form.pixel_id} />}
                            <ReviewRow label="Link" value={form.link} />
                            <div className="flex gap-3 rounded-lg border border-gray-200 p-3">
                                {imagePreview && <img src={imagePreview} alt="" className="h-20 w-20 rounded object-cover" />}
                                <div className="min-w-0">
                                    <p className="whitespace-pre-line text-gray-700">{form.primary_text}</p>
                                    <p className="mt-1 font-semibold text-gray-900">{form.headline}</p>
                                </div>
                            </div>
                            <div className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                Everything is created <span className="font-semibold">PAUSED</span> — nothing spends until you switch it on in the Campaigns
                                tab. The ad is UTM-tagged so funnel sales show up as Funnel ROAS.
                            </div>
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

function ReviewRow({ label, value }) {
    return (
        <div className="flex gap-3">
            <span className="w-24 shrink-0 text-gray-500">{label}</span>
            <span className="min-w-0 break-words font-medium text-gray-900">{value || '—'}</span>
        </div>
    );
}

/* ───────────────────────────── Rules ───────────────────────────── */

const METRICS = [
    ['funnel_roas', 'Funnel ROAS (real sales ÷ spend)'],
    ['fb_roas', 'FB ROAS (pixel purchases ÷ spend)'],
    ['spend', 'Spend'],
    ['cpa', 'Cost per FB purchase'],
    ['purchases', 'FB purchases'],
    ['cpc', 'CPC'],
    ['ctr', 'CTR (%)'],
];

const ACTIONS = [
    ['pause', 'Pause it'],
    ['activate', 'Turn it back on'],
    ['increase_budget', 'Increase daily budget by %'],
    ['decrease_budget', 'Decrease daily budget by %'],
];

const RULE_PRESETS = [
    {
        label: 'Stop losers',
        rule: { name: 'Pause ad sets losing money', level: 'adset', metric: 'funnel_roas', operator: 'lt', threshold: 1, min_spend: 50, lookback_days: 3, action: 'pause' },
    },
    {
        label: 'Scale winners',
        rule: { name: 'Scale winning campaigns +20%', level: 'campaign', metric: 'funnel_roas', operator: 'gt', threshold: 3, min_spend: 100, lookback_days: 3, action: 'increase_budget', action_value: 20, max_daily_budget: 300, cooldown_hours: 48 },
    },
    {
        label: 'Kill no-sale ads',
        rule: { name: 'Pause ads with spend but no purchase', level: 'ad', metric: 'purchases', operator: 'lt', threshold: 1, min_spend: 40, lookback_days: 3, action: 'pause' },
    },
];

const emptyRule = (accountId) => ({
    facebook_ad_account_id: accountId || '',
    name: '',
    level: 'adset',
    metric: 'funnel_roas',
    operator: 'lt',
    threshold: 1,
    min_spend: 50,
    lookback_days: 3,
    action: 'pause',
    action_value: 20,
    max_daily_budget: '',
    min_daily_budget: '',
    cooldown_hours: 24,
    is_active: true,
    dry_run: true,
});

function RulesTab({ accounts, defaultAccountId, showToast }) {
    const [rules, setRules] = useState([]);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(null);
    const [runningId, setRunningId] = useState(null);

    const load = useCallback(() => {
        api('/api/v1/facebook-ads/manage/rules')
            .then((data) => setRules(data.data || []))
            .catch((e) => showToast(e.message, 'error'))
            .finally(() => setLoading(false));
    }, [showToast]);

    useEffect(() => {
        load();
    }, [load]);

    const save = async (rule, patch = {}) => {
        const payload = { ...rule, ...patch };
        ['max_daily_budget', 'min_daily_budget', 'action_value'].forEach((k) => {
            if (payload[k] === '' || payload[k] === undefined) payload[k] = null;
        });
        if (!['increase_budget', 'decrease_budget'].includes(payload.action)) payload.action_value = null;
        const data = await api(rule.id ? `/api/v1/facebook-ads/manage/rules/${rule.id}` : '/api/v1/facebook-ads/manage/rules', {
            method: rule.id ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });
        load();
        return data;
    };

    const quickUpdate = async (rule, patch, message) => {
        try {
            await save(rule, patch);
            showToast(message);
        } catch (e) {
            showToast(e.message, 'error');
        }
    };

    const runNow = async (rule) => {
        setRunningId(rule.id);
        try {
            const data = await api(`/api/v1/facebook-ads/manage/rules/${rule.id}/run`, { method: 'POST' });
            showToast(data.message, data.success ? 'success' : 'error');
            load();
        } catch (e) {
            showToast(e.message, 'error');
        } finally {
            setRunningId(null);
        }
    };

    const remove = async (rule) => {
        if (!window.confirm(`Delete rule "${rule.name}"?`)) return;
        try {
            await api(`/api/v1/facebook-ads/manage/rules/${rule.id}`, { method: 'DELETE' });
            showToast('Rule deleted');
            load();
        } catch (e) {
            showToast(e.message, 'error');
        }
    };

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-2xl text-sm text-gray-500">
                    Rules run every hour. New rules start in <span className="font-semibold">dry run</span> — they only log what they would do in the Activity
                    Log. Switch to live once you trust the results.
                </p>
                <div className="flex flex-wrap gap-2">
                    {RULE_PRESETS.map((preset) => (
                        <button
                            key={preset.label}
                            onClick={() => setEditing({ ...emptyRule(defaultAccountId), ...preset.rule })}
                            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            {preset.label}
                        </button>
                    ))}
                    <button onClick={() => setEditing(emptyRule(defaultAccountId))} className="fs-cta rounded-lg px-4 py-1.5 text-sm font-medium text-white">
                        + New rule
                    </button>
                </div>
            </div>

            {loading ? (
                <div className="py-16 text-center text-sm text-gray-500">Loading rules...</div>
            ) : rules.length === 0 ? (
                <div className="rounded-lg border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
                    No rules yet — start from a preset above.
                </div>
            ) : (
                <div className="space-y-3">
                    {rules.map((rule) => (
                        <div key={rule.id} className="flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-white px-5 py-4">
                            <Toggle on={rule.is_active} onChange={() => quickUpdate(rule, { is_active: !rule.is_active }, rule.is_active ? 'Rule disabled' : 'Rule enabled')} />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-semibold text-gray-900">{rule.name}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${rule.dry_run ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}`}>
                                        {rule.dry_run ? 'Dry run' : 'Live'}
                                    </span>
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{LEVEL_LABEL[rule.level]}s</span>
                                </div>
                                <p className="mt-0.5 text-sm text-gray-600">{rule.description}</p>
                                <p className="mt-0.5 text-xs text-gray-500">
                                    {rule.account_name} · cooldown {rule.cooldown_hours}h
                                    {rule.last_run_at ? ` · Last run ${new Date(rule.last_run_at).toLocaleString()}: ${rule.last_run_summary}` : ' · Not run yet'}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    onClick={() => quickUpdate(rule, { dry_run: !rule.dry_run }, rule.dry_run ? 'Rule is now LIVE' : 'Rule switched to dry run')}
                                    className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    {rule.dry_run ? 'Go live' : 'Dry run'}
                                </button>
                                <button
                                    onClick={() => runNow(rule)}
                                    disabled={runningId === rule.id}
                                    className="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                                >
                                    {runningId === rule.id ? 'Running...' : 'Run now'}
                                </button>
                                <button onClick={() => setEditing(rule)} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                    Edit
                                </button>
                                <button onClick={() => remove(rule)} className="rounded-lg px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">
                                    Delete
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {editing && (
                <RuleModal
                    rule={editing}
                    accounts={accounts}
                    onClose={() => setEditing(null)}
                    onSave={async (rule) => {
                        await save(rule);
                        showToast(rule.id ? 'Rule updated' : 'Rule created (dry run)');
                        setEditing(null);
                    }}
                />
            )}
        </div>
    );
}

function RuleModal({ rule, accounts, onClose, onSave }) {
    const [form, setForm] = useState({ ...emptyRule(), ...rule });
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const set = (patch) => setForm((f) => ({ ...f, ...patch }));
    const isBudget = ['increase_budget', 'decrease_budget'].includes(form.action);
    const currency = accounts.find((a) => String(a.id) === String(form.facebook_ad_account_id))?.currency || 'MYR';

    const submit = async () => {
        setBusy(true);
        setError(null);
        try {
            await onSave(form);
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal
            wide
            title={rule.id ? 'Edit rule' : 'New automation rule'}
            onClose={onClose}
            footer={
                <>
                    <button onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Cancel
                    </button>
                    <button onClick={submit} disabled={busy} className="fs-cta rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                        {busy ? 'Saving...' : 'Save rule'}
                    </button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
            <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Rule name">
                        <input value={form.name} onChange={(e) => set({ name: e.target.value })} className={inputClass} />
                    </Field>
                    <Field label="Ad account">
                        <select value={form.facebook_ad_account_id} onChange={(e) => set({ facebook_ad_account_id: e.target.value })} className={inputClass}>
                            {accounts.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                    {a.can_manage ? '' : ' (read-only)'}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <div className="rounded-lg border border-gray-200 p-3">
                    <span className="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-500">If</span>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Field label="Any active">
                            <select value={form.level} onChange={(e) => set({ level: e.target.value })} className={inputClass}>
                                <option value="campaign">Campaign</option>
                                <option value="adset">Ad set</option>
                                <option value="ad">Ad</option>
                            </select>
                        </Field>
                        <Field label="Metric">
                            <select value={form.metric} onChange={(e) => set({ metric: e.target.value })} className={inputClass}>
                                {METRICS.map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Is">
                            <select value={form.operator} onChange={(e) => set({ operator: e.target.value })} className={inputClass}>
                                <option value="lt">Less than</option>
                                <option value="gt">Greater than</option>
                            </select>
                        </Field>
                        <Field label="Value">
                            <input type="number" step="0.01" min="0" value={form.threshold} onChange={(e) => set({ threshold: e.target.value })} className={inputClass} />
                        </Field>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-3">
                        <Field label="Over the last (days)">
                            <input type="number" min="1" max="30" value={form.lookback_days} onChange={(e) => set({ lookback_days: e.target.value })} className={inputClass} />
                        </Field>
                        <Field label={`Only after spending at least (${currency})`} hint="Avoids judging too early.">
                            <input type="number" min="0" value={form.min_spend} onChange={(e) => set({ min_spend: e.target.value })} className={inputClass} />
                        </Field>
                    </div>
                </div>

                <div className="rounded-lg border border-gray-200 p-3">
                    <span className="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-500">Then</span>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Action">
                            <select value={form.action} onChange={(e) => set({ action: e.target.value })} className={inputClass}>
                                {ACTIONS.map(([value, label]) => (
                                    <option key={value} value={value} disabled={form.level === 'ad' && value.includes('budget')}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Cooldown (hours)" hint="Don't touch the same object again for this long.">
                            <input type="number" min="1" value={form.cooldown_hours} onChange={(e) => set({ cooldown_hours: e.target.value })} className={inputClass} />
                        </Field>
                    </div>
                    {isBudget && (
                        <div className="mt-3 grid grid-cols-3 gap-3">
                            <Field label="By %">
                                <input type="number" min="1" max="100" value={form.action_value ?? ''} onChange={(e) => set({ action_value: e.target.value })} className={inputClass} />
                            </Field>
                            <Field label={`Max daily budget (${currency})`}>
                                <input type="number" min="1" value={form.max_daily_budget ?? ''} onChange={(e) => set({ max_daily_budget: e.target.value })} className={inputClass} />
                            </Field>
                            <Field label={`Min daily budget (${currency})`}>
                                <input type="number" min="1" value={form.min_daily_budget ?? ''} onChange={(e) => set({ min_daily_budget: e.target.value })} className={inputClass} />
                            </Field>
                        </div>
                    )}
                </div>

                <label className="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={form.dry_run} onChange={(e) => set({ dry_run: e.target.checked })} className="mt-0.5" />
                    <span>
                        <span className="font-medium">Dry run</span>
                        <span className="block text-xs text-gray-500">Only log what would happen — nothing changes on Facebook.</span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

/* ───────────────────────────── Activity ───────────────────────────── */

function ActivityTab({ accountId }) {
    const [actions, setActions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [onlyAccount, setOnlyAccount] = useState(true);

    useEffect(() => {
        setLoading(true);
        const params = onlyAccount && accountId ? `?account_id=${accountId}` : '';
        api(`/api/v1/facebook-ads/manage/actions${params}`)
            .then((data) => setActions(data.data || []))
            .finally(() => setLoading(false));
    }, [accountId, onlyAccount]);

    const describe = (a) => {
        if (a.action === 'status') return a.after?.status === 'ACTIVE' ? 'Turned on' : 'Paused';
        if (a.action === 'budget') return `Budget ${a.before?.daily_budget ?? '?'} → ${a.after?.daily_budget}`;
        if (a.action === 'launch') return 'Launched';
        return a.action;
    };

    return (
        <div>
            <label className="mb-3 flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" checked={onlyAccount} onChange={(e) => setOnlyAccount(e.target.checked)} />
                Only this ad account
            </label>
            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                {loading ? (
                    <div className="py-16 text-center text-sm text-gray-500">Loading...</div>
                ) : actions.length === 0 ? (
                    <div className="py-16 text-center text-sm text-gray-500">No activity yet.</div>
                ) : (
                    <table className="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr className="text-left text-[11px] uppercase tracking-wider text-gray-500">
                                <th className="px-4 py-2.5 font-medium">When</th>
                                <th className="px-4 py-2.5 font-medium">By</th>
                                <th className="px-4 py-2.5 font-medium">Object</th>
                                <th className="px-4 py-2.5 font-medium">Change</th>
                                <th className="px-4 py-2.5 font-medium">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            {actions.map((a) => (
                                <tr key={a.id} className="border-t border-gray-100 align-top">
                                    <td className="whitespace-nowrap px-4 py-3 text-gray-600">{new Date(a.created_at).toLocaleString()}</td>
                                    <td className="px-4 py-3 text-gray-700">
                                        {a.source === 'rule' ? (
                                            <span>
                                                Rule: <span className="font-medium">{a.rule || 'deleted'}</span>
                                            </span>
                                        ) : (
                                            a.user || '—'
                                        )}
                                    </td>
                                    <td className="max-w-xs px-4 py-3">
                                        <span className="block truncate font-medium text-gray-900">{a.object_name || a.object_id}</span>
                                        <span className="text-xs text-gray-500">
                                            {LEVEL_LABEL[a.object_type] || a.object_type} · {a.account}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700">
                                        {describe(a)}
                                        {a.dry_run && <span className="ml-2 rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-medium text-yellow-800">DRY RUN</span>}
                                    </td>
                                    <td className="max-w-sm px-4 py-3">
                                        <span className={a.success ? 'text-green-700' : 'text-red-600'}>{a.success ? '✓' : '✕'}</span>{' '}
                                        <span className="text-xs text-gray-600">{a.message}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

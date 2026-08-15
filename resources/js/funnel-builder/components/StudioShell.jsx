/**
 * Studio Shell — persistent admin chrome for the Funnel Studio.
 *
 * Wraps every view EXCEPT the Puck editor with:
 *  - a left sidebar: fixed nav, pinned funnels (⭐), and user-customizable
 *    shortcuts (both persisted in localStorage)
 *  - a top bar: live funnel search, breadcrumb, pin toggle for the open
 *    funnel, and quick links
 *
 * Opening a funnel swaps only the content area — the chrome never goes away,
 * so you can always jump back or across funnels.
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';

const PINNED_KEY = 'fs-pinned-funnels';
const SHORTCUTS_KEY = 'fs-custom-shortcuts';
const NOTIF_SEEN_KEY = 'fs-notif-seen-at';

const fmtRM = (value) => `RM ${Number(value || 0).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;

const readStore = (key) => {
    try {
        return JSON.parse(localStorage.getItem(key) || '[]');
    } catch (e) {
        return [];
    }
};

const writeStore = (key, value) => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
        // localStorage unavailable — shortcuts just won't persist.
    }
};

const getCsrfToken = () =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1]
        ?.replace(/%3D/g, '=') || '';

function NavItem({ active, onClick, href, icon, label, badge }) {
    const className = `fs-nav-item flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors cursor-pointer ${
        active ? 'fs-nav-active' : 'text-zinc-400 hover:text-zinc-100'
    }`;

    const content = (
        <>
            {icon}
            <span className="flex-1 truncate text-left">{label}</span>
            {badge != null && <span className="text-[10px] text-zinc-500">{badge}</span>}
        </>
    );

    if (href) {
        return (
            <a href={href} className={className}>
                {content}
            </a>
        );
    }

    return (
        <button onClick={onClick} className={className}>
            {content}
        </button>
    );
}

export default function StudioShell({
    currentView,
    funnelName,
    funnelUuid,
    fighterContext,
    onNavigate,
    onSelectFunnel,
    children,
}) {
    const [pinned, setPinned] = useState(() => readStore(PINNED_KEY));
    const [shortcuts, setShortcuts] = useState(() => readStore(SHORTCUTS_KEY));
    const [editingShortcuts, setEditingShortcuts] = useState(false);
    const [newLabel, setNewLabel] = useState('');
    const [newUrl, setNewUrl] = useState('');
    const [search, setSearch] = useState('');
    const [results, setResults] = useState([]);
    const [searchOpen, setSearchOpen] = useState(false);
    const [searching, setSearching] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [notif, setNotif] = useState(null);
    const [notifOpen, setNotifOpen] = useState(false);
    const [notifSeenAt, setNotifSeenAt] = useState(() => localStorage.getItem(NOTIF_SEEN_KEY) || '');
    const searchRef = useRef(null);
    const notifRef = useRef(null);
    const debounceRef = useRef(null);

    // ── Pinned funnels ────────────────────────────────────────────────
    const isPinned = funnelUuid && pinned.some((p) => p.uuid === funnelUuid);

    const togglePin = () => {
        if (!funnelUuid) return;
        const next = isPinned
            ? pinned.filter((p) => p.uuid !== funnelUuid)
            : [...pinned, { uuid: funnelUuid, name: funnelName || 'Funnel' }];
        setPinned(next);
        writeStore(PINNED_KEY, next);
    };

    const unpin = (uuid) => {
        const next = pinned.filter((p) => p.uuid !== uuid);
        setPinned(next);
        writeStore(PINNED_KEY, next);
    };

    // Keep the pinned entry's name fresh when the funnel loads/renames.
    useEffect(() => {
        if (!funnelUuid || !funnelName) return;
        setPinned((prev) => {
            const entry = prev.find((p) => p.uuid === funnelUuid);
            if (!entry || entry.name === funnelName) return prev;
            const next = prev.map((p) => (p.uuid === funnelUuid ? { ...p, name: funnelName } : p));
            writeStore(PINNED_KEY, next);
            return next;
        });
    }, [funnelUuid, funnelName]);

    // ── Custom shortcuts ──────────────────────────────────────────────
    const addShortcut = () => {
        if (!newLabel.trim() || !newUrl.trim()) return;
        let url = newUrl.trim();
        if (!/^(https?:\/\/|\/)/i.test(url)) {
            url = '/' + url;
        }
        const next = [...shortcuts, { id: Date.now(), label: newLabel.trim(), url }];
        setShortcuts(next);
        writeStore(SHORTCUTS_KEY, next);
        setNewLabel('');
        setNewUrl('');
    };

    const removeShortcut = (id) => {
        const next = shortcuts.filter((s) => s.id !== id);
        setShortcuts(next);
        writeStore(SHORTCUTS_KEY, next);
    };

    // ── Funnel search ─────────────────────────────────────────────────
    const runSearch = useCallback(async (term) => {
        if (!term.trim()) {
            setResults([]);
            return;
        }
        setSearching(true);
        try {
            const response = await fetch(`/api/v1/funnels?search=${encodeURIComponent(term)}&per_page=8`, {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setResults((data.data || []).slice(0, 8));
        } catch (e) {
            setResults([]);
        } finally {
            setSearching(false);
        }
    }, []);

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => runSearch(search), 250);
        return () => clearTimeout(debounceRef.current);
    }, [search, runSearch]);

    // Close search dropdown on outside click
    useEffect(() => {
        const onClick = (e) => {
            if (searchRef.current && !searchRef.current.contains(e.target)) {
                setSearchOpen(false);
            }
            if (notifRef.current && !notifRef.current.contains(e.target)) {
                setNotifOpen(false);
            }
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    // ── Notifications: today's pulse + attention items ────────────────
    const loadNotifications = useCallback(async () => {
        try {
            const response = await fetch('/api/v1/studio/notifications', {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setNotif(data.data || null);
        } catch (e) {
            // Bell simply stays empty.
        }
    }, []);

    useEffect(() => {
        loadNotifications();
        const interval = setInterval(loadNotifications, 120000);
        return () => clearInterval(interval);
    }, [loadNotifications]);

    const unreadCount = notif
        ? (notif.recent_orders || []).filter((o) => !notifSeenAt || o.created_at > notifSeenAt).length
            + (notif.pixel_problems || []).length
            + (notif.ads_errors || []).length
        : 0;

    const openNotifications = () => {
        const next = !notifOpen;
        setNotifOpen(next);
        if (next) {
            const now = new Date().toISOString();
            localStorage.setItem(NOTIF_SEEN_KEY, now);
            // Delay so the badge clears after the panel closes, not while opening.
            setTimeout(() => setNotifSeenAt(now), 400);
        }
    };

    const openFunnel = (funnel) => {
        setSearchOpen(false);
        setSearch('');
        setSidebarOpen(false);
        onSelectFunnel(funnel);
    };

    // ── Breadcrumb ────────────────────────────────────────────────────
    const VIEW_LABELS = {
        list: 'Funnels',
        pixels: 'Pixel Library',
        products: 'Products',
        orders: 'Orders',
        reports: 'Reports',
        facebook_ads: 'Facebook Ads',
    };

    const crumbs =
        currentView === 'detail'
            ? ['Funnels', funnelName || '...']
            : [VIEW_LABELS[currentView] || 'Funnels'];

    const backHref = fighterContext ? '/fighter' : '/admin/funnels';

    const sidebar = (
        <aside className="fs-sidebar flex h-full w-60 shrink-0 flex-col">
            {/* Brand */}
            <div className="flex items-center gap-2.5 px-4 pb-5 pt-5">
                <div className="fs-brand-mark">
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2.2} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
                    </svg>
                </div>
                <div className="fs-display text-sm font-semibold tracking-tight text-zinc-100">
                    Funnel <span className="fs-gradient-text">Studio</span>
                </div>
            </div>

            {/* Fixed nav */}
            <nav className="space-y-1 px-3">
                {fighterContext ? (
                    <NavItem
                        href="/fighter"
                        label="Funnels"
                        icon={
                            <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
                            </svg>
                        }
                    />
                ) : (
                    <NavItem
                        active={currentView === 'list'}
                        onClick={() => {
                            setSidebarOpen(false);
                            onNavigate('list');
                        }}
                        label="Funnels"
                        icon={
                            <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
                            </svg>
                        }
                    />
                )}
                <NavItem
                    active={currentView === 'products'}
                    onClick={() => {
                        setSidebarOpen(false);
                        onNavigate('products');
                    }}
                    label="Products"
                    icon={
                        <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    }
                />
                <NavItem
                    active={currentView === 'orders'}
                    onClick={() => {
                        setSidebarOpen(false);
                        onNavigate('orders');
                    }}
                    label="Orders"
                    icon={
                        <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    }
                />
                <NavItem
                    active={currentView === 'reports'}
                    onClick={() => {
                        setSidebarOpen(false);
                        onNavigate('reports');
                    }}
                    label="Reports"
                    icon={
                        <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    }
                />
                <NavItem
                    active={currentView === 'facebook_ads'}
                    onClick={() => {
                        setSidebarOpen(false);
                        onNavigate('facebook_ads');
                    }}
                    label="Facebook Ads"
                    icon={
                        <svg className="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    }
                />
                <NavItem
                    active={currentView === 'pixels'}
                    onClick={() => {
                        setSidebarOpen(false);
                        onNavigate('pixels');
                    }}
                    label="Pixel Library"
                    icon={
                        <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    }
                />
            </nav>

            {/* Pinned funnels */}
            <div className="mt-6 px-3">
                <div className="mb-2 flex items-center justify-between px-3">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-zinc-500">Pinned</span>
                </div>
                {pinned.length === 0 ? (
                    <p className="px-3 text-[11px] leading-relaxed text-zinc-600">
                        Open a funnel and tap the ⭐ in the top bar to pin it here.
                    </p>
                ) : (
                    <div className="space-y-0.5">
                        {pinned.map((p) => (
                            <div key={p.uuid} className="group flex items-center">
                                <button
                                    onClick={() => openFunnel({ uuid: p.uuid })}
                                    className={`fs-nav-item flex-1 truncate rounded-lg px-3 py-1.5 text-left text-[13px] transition-colors cursor-pointer ${
                                        funnelUuid === p.uuid ? 'fs-nav-active' : 'text-zinc-400 hover:text-zinc-100'
                                    }`}
                                >
                                    {p.name}
                                </button>
                                <button
                                    onClick={() => unpin(p.uuid)}
                                    className="mr-1 hidden rounded p-1 text-zinc-600 hover:text-red-400 group-hover:block"
                                    title="Unpin"
                                >
                                    <svg className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Custom shortcuts */}
            <div className="mt-6 px-3">
                <div className="mb-2 flex items-center justify-between px-3">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-zinc-500">Shortcuts</span>
                    <button
                        onClick={() => setEditingShortcuts(!editingShortcuts)}
                        className="text-[10px] font-medium text-zinc-500 transition-colors hover:text-zinc-200 cursor-pointer"
                    >
                        {editingShortcuts ? 'Done' : 'Edit'}
                    </button>
                </div>
                <div className="space-y-0.5">
                    {shortcuts.map((s) => (
                        <div key={s.id} className="group flex items-center">
                            <a
                                href={s.url}
                                className="fs-nav-item flex flex-1 items-center gap-2 truncate rounded-lg px-3 py-1.5 text-[13px] text-zinc-400 transition-colors hover:text-zinc-100"
                            >
                                <svg className="h-3 w-3 shrink-0 text-zinc-600" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                <span className="truncate">{s.label}</span>
                            </a>
                            {editingShortcuts && (
                                <button
                                    onClick={() => removeShortcut(s.id)}
                                    className="mr-1 rounded p-1 text-zinc-600 hover:text-red-400"
                                    title="Remove"
                                >
                                    <svg className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            )}
                        </div>
                    ))}
                    {shortcuts.length === 0 && !editingShortcuts && (
                        <p className="px-3 text-[11px] leading-relaxed text-zinc-600">
                            Tap Edit to add your own quick links.
                        </p>
                    )}
                </div>
                {editingShortcuts && (
                    <div className="mt-2 space-y-1.5 rounded-lg border border-zinc-700 p-2">
                        <input
                            type="text"
                            value={newLabel}
                            onChange={(e) => setNewLabel(e.target.value)}
                            placeholder="Label (e.g. Orders)"
                            className="w-full rounded-md border border-zinc-700 px-2 py-1 text-xs outline-none focus:border-zinc-500"
                        />
                        <input
                            type="text"
                            value={newUrl}
                            onChange={(e) => setNewUrl(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && addShortcut()}
                            placeholder="URL (e.g. /admin/orders)"
                            className="w-full rounded-md border border-zinc-700 px-2 py-1 text-xs outline-none focus:border-zinc-500"
                        />
                        <button
                            onClick={addShortcut}
                            disabled={!newLabel.trim() || !newUrl.trim()}
                            className="fs-cta w-full rounded-md px-2 py-1 text-xs font-medium text-white disabled:opacity-40"
                        >
                            Add Shortcut
                        </button>
                    </div>
                )}
            </div>

            {/* Exit */}
            <div className="mt-auto px-3 pb-4">
                <NavItem
                    href={backHref}
                    label={fighterContext ? 'Back to Fighter' : 'Back to Admin'}
                    icon={
                        <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    }
                />
            </div>
        </aside>
    );

    return (
        <div className="flex h-screen overflow-hidden">
            {/* Desktop sidebar */}
            <div className="hidden lg:block">{sidebar}</div>

            {/* Mobile sidebar drawer */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden" onClick={() => setSidebarOpen(false)}>
                    <div className="absolute inset-0 bg-black/50" />
                    <div className="fs-page-bg relative h-full w-60" onClick={(e) => e.stopPropagation()}>
                        {sidebar}
                    </div>
                </div>
            )}

            {/* Main column */}
            <div className="flex min-w-0 flex-1 flex-col">
                {/* Top bar */}
                <header className="fs-header z-40 shrink-0">
                    <div className="flex h-14 items-center gap-3 px-4 lg:px-6">
                        {/* Mobile menu */}
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="rounded-md p-1.5 text-zinc-400 hover:text-zinc-100 lg:hidden"
                        >
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        {/* Breadcrumb */}
                        <div className="flex min-w-0 items-center gap-1.5 text-sm">
                            {crumbs.map((crumb, i) => (
                                <React.Fragment key={i}>
                                    {i > 0 && <span className="text-zinc-600">/</span>}
                                    {i === 0 && crumbs.length > 1 ? (
                                        <button
                                            onClick={() => onNavigate('list')}
                                            className="shrink-0 text-zinc-500 transition-colors hover:text-zinc-200 cursor-pointer"
                                        >
                                            {crumb}
                                        </button>
                                    ) : (
                                        <span className={`truncate ${i === crumbs.length - 1 ? 'font-medium text-zinc-100' : 'text-zinc-500'}`}>
                                            {crumb}
                                        </span>
                                    )}
                                </React.Fragment>
                            ))}
                            {currentView === 'detail' && funnelUuid && (
                                <button
                                    onClick={togglePin}
                                    className="ml-1 shrink-0 rounded p-1 transition-colors cursor-pointer"
                                    title={isPinned ? 'Unpin from sidebar' : 'Pin to sidebar'}
                                >
                                    <svg
                                        className={`h-4 w-4 ${isPinned ? 'fs-star-active' : 'text-zinc-600 hover:text-zinc-300'}`}
                                        fill={isPinned ? 'currentColor' : 'none'}
                                        stroke="currentColor"
                                        strokeWidth={2}
                                        viewBox="0 0 24 24"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                    </svg>
                                </button>
                            )}
                        </div>

                        {/* Search */}
                        <div ref={searchRef} className="relative ml-auto w-full max-w-xs lg:max-w-sm">
                            <svg className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-500" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setSearchOpen(true);
                                }}
                                onFocus={() => setSearchOpen(true)}
                                placeholder="Search funnels..."
                                className="w-full rounded-lg border border-zinc-700 py-1.5 pl-9 pr-3 text-sm outline-none transition-colors focus:border-zinc-500"
                            />
                            {searchOpen && search.trim() && (
                                <div className="fs-search-panel absolute right-0 top-full z-50 mt-2 w-full overflow-hidden rounded-xl">
                                    {searching && <div className="px-4 py-3 text-xs text-zinc-500">Searching...</div>}
                                    {!searching && results.length === 0 && (
                                        <div className="px-4 py-3 text-xs text-zinc-500">No funnels found.</div>
                                    )}
                                    {!searching &&
                                        results.map((f) => (
                                            <button
                                                key={f.uuid}
                                                onClick={() => openFunnel(f)}
                                                className="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-zinc-800 cursor-pointer"
                                            >
                                                <span
                                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${f.status === 'published' ? 'bg-green-500' : 'bg-zinc-600'}`}
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm text-zinc-100">{f.name}</span>
                                                    <span className="block truncate text-[11px] text-zinc-500">/{f.slug}</span>
                                                </span>
                                                <span className="shrink-0 text-[10px] uppercase tracking-wide text-zinc-600">
                                                    {f.status}
                                                </span>
                                            </button>
                                        ))}
                                </div>
                            )}
                        </div>

                        {/* Today's pulse */}
                        {notif && (
                            <button
                                onClick={() => onNavigate('reports')}
                                title="Today's revenue and orders — open Reports"
                                className="hidden shrink-0 items-center gap-2 rounded-lg border border-zinc-700 px-3 py-1.5 text-xs transition-colors hover:border-zinc-500 md:flex cursor-pointer"
                            >
                                <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                <span className="text-zinc-400">Today</span>
                                <span className="fs-gradient-text font-semibold">{fmtRM(notif.today?.revenue)}</span>
                                <span className="text-zinc-500">· {notif.today?.orders ?? 0} orders</span>
                            </button>
                        )}

                        {/* Notification bell */}
                        <div ref={notifRef} className="relative shrink-0">
                            <button
                                onClick={openNotifications}
                                className="relative rounded-lg p-2 text-zinc-400 transition-colors hover:text-zinc-100 cursor-pointer"
                                title="Notifications"
                            >
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                {unreadCount > 0 && (
                                    <span className="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                                        {unreadCount > 9 ? '9+' : unreadCount}
                                    </span>
                                )}
                            </button>

                            {notifOpen && (
                                <div className="fs-search-panel absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-xl sm:w-96">
                                    <div className="border-b border-zinc-700/50 px-4 py-3">
                                        <p className="text-sm font-semibold text-zinc-100">Notifications</p>
                                    </div>
                                    <div className="max-h-[420px] overflow-y-auto">
                                        {/* Attention: broken pixels */}
                                        {(notif?.pixel_problems || []).map((problem) => (
                                            <button
                                                key={`px-${problem.funnel_uuid}`}
                                                onClick={() => {
                                                    setNotifOpen(false);
                                                    onSelectFunnel({ uuid: problem.funnel_uuid });
                                                }}
                                                className="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-zinc-800 cursor-pointer"
                                            >
                                                <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-500/15">
                                                    <svg className="h-3.5 w-3.5 text-red-400" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block text-sm text-zinc-100">Broken pixel tracking</span>
                                                    <span className="block truncate text-xs text-zinc-500">
                                                        {problem.funnel_name} — ads may be running blind. Tap to fix.
                                                    </span>
                                                </span>
                                            </button>
                                        ))}

                                        {/* Attention: failed ads connections */}
                                        {(notif?.ads_errors || []).map((error, i) => (
                                            <button
                                                key={`ads-${i}`}
                                                onClick={() => {
                                                    setNotifOpen(false);
                                                    onNavigate('facebook_ads');
                                                }}
                                                className="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-zinc-800 cursor-pointer"
                                            >
                                                <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-yellow-500/15">
                                                    <svg className="h-3.5 w-3.5 text-yellow-400" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block text-sm text-zinc-100">Facebook Ads connection failed</span>
                                                    <span className="block truncate text-xs text-zinc-500">{error.name}: {error.message}</span>
                                                </span>
                                            </button>
                                        ))}

                                        {/* Recent orders */}
                                        {(notif?.recent_orders || []).map((order) => {
                                            const isNew = !notifSeenAt || order.created_at > notifSeenAt;
                                            return (
                                                <button
                                                    key={`o-${order.id}`}
                                                    onClick={() => {
                                                        setNotifOpen(false);
                                                        onNavigate('orders');
                                                    }}
                                                    className="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-zinc-800 cursor-pointer"
                                                >
                                                    <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500/15">
                                                        <svg className="h-3.5 w-3.5 text-green-400" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                        </svg>
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex items-center gap-2 text-sm text-zinc-100">
                                                            New order · {fmtRM(order.revenue)}
                                                            {isNew && <span className="h-1.5 w-1.5 rounded-full bg-orange-500" />}
                                                        </span>
                                                        <span className="block truncate text-xs text-zinc-500">
                                                            {order.customer_name} — {order.funnel_name} · {order.created_at_human}
                                                        </span>
                                                    </span>
                                                </button>
                                            );
                                        })}

                                        {notif
                                            && (notif.pixel_problems || []).length === 0
                                            && (notif.ads_errors || []).length === 0
                                            && (notif.recent_orders || []).length === 0 && (
                                            <p className="px-4 py-8 text-center text-sm text-zinc-500">
                                                All quiet — no new orders in the last 48 hours and nothing needs attention.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Content */}
                <main className="min-h-0 flex-1 overflow-y-auto">{children}</main>
            </div>
        </div>
    );
}

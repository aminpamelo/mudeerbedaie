import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, CheckSquare, Columns3, Calendar, BarChart3,
    FileText, Trophy, Settings, FolderKanban, Plus, Search, Menu, X, GanttChart,
    Sparkles
} from 'lucide-react';
import { useState } from 'react';
import NotificationBell from '@/workspace/components/NotificationBell';
import AiAssistant from '@/workspace/components/AiAssistant';

const NAV_ITEMS = [
    { label: 'Dashboard', href: '/workspace', icon: LayoutDashboard },
    { label: 'My Tasks', href: '/workspace/my-tasks', icon: CheckSquare },
    { label: 'Board', href: '/workspace/board', icon: Columns3 },
    { label: 'Calendar', href: '/workspace/calendar', icon: Calendar },
    { label: 'Gantt', href: '/workspace/gantt', icon: GanttChart },
    { label: 'Projects', href: '/workspace/projects', icon: FolderKanban },
    { label: 'KPI', href: '/workspace/kpi', icon: BarChart3 },
    { label: 'Reports', href: '/workspace/reports', icon: FileText },
    { label: 'Leaderboard', href: '/workspace/leaderboard', icon: Trophy },
    { label: 'Settings', href: '/workspace/settings', icon: Settings },
];

function NavItem({ item, current }) {
    const active = current === item.href || (item.href !== '/workspace' && current.startsWith(item.href));
    const Icon = item.icon;
    return (
        <Link
            href={item.href}
            className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-medium transition-all duration-200 ${
                active
                    ? 'bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500 text-white shadow-lg shadow-indigo-500/30'
                    : 'text-slate-400 hover:bg-white/[0.06] hover:text-white'
            }`}
        >
            <Icon className={`h-[18px] w-[18px] transition-transform duration-200 ${!active ? 'group-hover:scale-110' : ''}`} strokeWidth={active ? 2.2 : 1.8} />
            {item.label}
        </Link>
    );
}

export default function WorkspaceLayout({ title, subtitle, actions, children }) {
    const { url, props } = usePage();
    const user = props.auth?.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="flex h-screen overflow-hidden bg-slate-50">
            {/* Mobile overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            {/* Sidebar — dark theme */}
            <aside className={`fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col bg-[#0f172a] transition-transform duration-300 lg:static lg:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                {/* Brand */}
                <div className="flex h-[68px] items-center justify-between px-5">
                    <Link href="/workspace" className="flex items-center gap-3">
                        <div className="relative grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-500 text-white shadow-lg shadow-indigo-500/40">
                            <Sparkles className="h-[18px] w-[18px]" strokeWidth={2.2} />
                            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/20 to-transparent" />
                        </div>
                        <div>
                            <span className="text-[15px] font-bold tracking-tight text-white">Workspace</span>
                            <p className="text-[10px] font-medium tracking-wider text-indigo-400">BeDaie TMS</p>
                        </div>
                    </Link>
                    <button onClick={() => setSidebarOpen(false)} className="lg:hidden rounded-lg p-1.5 text-slate-500 hover:text-white">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Nav */}
                <nav className="ws-scroll flex-1 space-y-0.5 overflow-y-auto px-3 py-3">
                    <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-600">Navigation</p>
                    {NAV_ITEMS.slice(0, 6).map((item) => (
                        <NavItem key={item.href} item={item} current={url.split('?')[0]} />
                    ))}

                    <div className="my-4 border-t border-white/[0.06]" />
                    <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.1em] text-slate-600">Analytics</p>
                    {NAV_ITEMS.slice(6).map((item) => (
                        <NavItem key={item.href} item={item} current={url.split('?')[0]} />
                    ))}
                </nav>

                {/* User card */}
                {user && (
                    <div className="border-t border-white/[0.06] px-4 py-4">
                        <div className="flex items-center gap-3">
                            <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-[11px] font-bold text-white ring-2 ring-indigo-400/30">
                                {user.name?.charAt(0)?.toUpperCase() ?? '?'}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-[13px] font-semibold text-white">{user.name}</p>
                                <p className="truncate text-[11px] text-slate-500">{user.email}</p>
                            </div>
                        </div>
                    </div>
                )}
            </aside>

            {/* Main content */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Header */}
                <header className="flex h-[68px] shrink-0 items-center justify-between border-b border-slate-200/80 bg-white/80 backdrop-blur-lg px-4 lg:px-6">
                    <div className="flex items-center gap-3">
                        <button onClick={() => setSidebarOpen(true)} className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                            <Menu className="h-5 w-5" />
                        </button>
                        <div>
                            {title && <h1 className="text-[17px] font-bold text-slate-900">{title}</h1>}
                            {subtitle && <p className="text-[12px] text-slate-400 mt-0.5">{subtitle}</p>}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button className="grid h-10 w-10 place-items-center rounded-xl text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                            <Search className="h-[18px] w-[18px]" strokeWidth={2} />
                        </button>
                        <NotificationBell />
                        {actions}
                    </div>
                </header>

                {/* Page content */}
                <main className="ws-scroll ws-page-enter flex-1 overflow-y-auto p-4 lg:p-6">
                    {children}
                </main>
            </div>

            <AiAssistant />
        </div>
    );
}

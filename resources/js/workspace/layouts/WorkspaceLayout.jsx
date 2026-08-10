import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, CheckSquare, Columns3, Calendar, BarChart3,
    FileText, Trophy, Settings, FolderKanban, ChevronDown, Plus, Search, Menu, X
} from 'lucide-react';
import { useState } from 'react';
import NotificationBell from '@/workspace/components/NotificationBell';

const NAV_ITEMS = [
    { label: 'Dashboard', href: '/workspace', icon: LayoutDashboard },
    { label: 'My Tasks', href: '/workspace/my-tasks', icon: CheckSquare },
    { label: 'Board', href: '/workspace/board', icon: Columns3 },
    { label: 'Calendar', href: '/workspace/calendar', icon: Calendar },
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
            className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-medium transition-all ${
                active
                    ? 'bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-500/25'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            }`}
        >
            <Icon className="h-[18px] w-[18px]" strokeWidth={active ? 2.2 : 1.8} />
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
                <div className="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            {/* Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:static lg:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex h-16 items-center justify-between border-b border-slate-100 px-5">
                    <Link href="/workspace" className="flex items-center gap-2.5">
                        <div className="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-sm">
                            <Columns3 className="h-4 w-4" strokeWidth={2.4} />
                        </div>
                        <span className="text-[15px] font-bold tracking-tight text-slate-900">Workspace</span>
                    </Link>
                    <button onClick={() => setSidebarOpen(false)} className="lg:hidden rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    {NAV_ITEMS.map((item) => (
                        <NavItem key={item.href} item={item} current={url.split('?')[0]} />
                    ))}
                </nav>

                {user && (
                    <div className="border-t border-slate-100 px-4 py-3">
                        <div className="flex items-center gap-3">
                            <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-400 to-violet-400 text-xs font-bold text-white">
                                {user.name?.charAt(0)?.toUpperCase() ?? '?'}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-[13px] font-semibold text-slate-900">{user.name}</p>
                                <p className="truncate text-[11px] text-slate-400">{user.email}</p>
                            </div>
                        </div>
                    </div>
                )}
            </aside>

            {/* Main content */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Header */}
                <header className="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-6">
                    <div className="flex items-center gap-3">
                        <button onClick={() => setSidebarOpen(true)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                            <Menu className="h-5 w-5" />
                        </button>
                        <div>
                            {title && <h1 className="text-[16px] font-bold text-slate-900">{title}</h1>}
                            {subtitle && <p className="text-[12px] text-slate-400">{subtitle}</p>}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button className="grid h-9 w-9 place-items-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <Search className="h-[18px] w-[18px]" strokeWidth={2} />
                        </button>
                        <NotificationBell />
                        {actions}
                    </div>
                </header>

                {/* Page content */}
                <main className="flex-1 overflow-y-auto p-4 lg:p-6">
                    {children}
                </main>
            </div>
        </div>
    );
}

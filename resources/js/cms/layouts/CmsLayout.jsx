import { useState, useEffect } from 'react';
import { Outlet, NavLink, Link, useLocation } from 'react-router-dom';
import {
    LayoutDashboard,
    FileText,
    Megaphone,
    Menu,
    X,
    ArrowLeft,
    ChevronRight,
    ChevronDown,
    Columns3,
    Calendar,
    CheckCheck,
    Flag,
    BarChart3,
    ListChecks,
    Share2,
    TrendingUp,
    Users,
} from 'lucide-react';
import useCmsStore from '../stores/useCmsStore';
import { cn } from '../lib/utils';

const navigation = [
    { name: 'Dashboard', to: '/', icon: LayoutDashboard, end: true },
    {
        name: 'Contents',
        icon: FileText,
        prefix: '/contents',
        children: [
            { name: 'All Contents', to: '/contents', icon: FileText },
            { name: 'Kanban Board', to: '/kanban', icon: Columns3 },
            { name: 'Calendar', to: '/calendar', icon: Calendar },
            { name: 'Content Report', to: '/reports/content', icon: BarChart3 },
        ],
    },
    {
        name: 'Ads',
        icon: Megaphone,
        prefix: '/ads',
        children: [
            { name: 'Marked Posts', to: '/ads/marked', icon: Flag },
            { name: 'Campaigns', to: '/ads', icon: BarChart3 },
        ],
    },
    {
        name: 'Platform',
        icon: Share2,
        prefix: '/platform',
        children: [
            { name: 'Cross-Post Queue', to: '/platform/queue', icon: ListChecks },
            { name: 'Posted History', to: '/platform/history', icon: CheckCheck },
        ],
    },
    { name: 'Performance', to: '/reports/performance', icon: TrendingUp },
    { name: 'Creators', to: '/creators', icon: Users },
];

function getExpandedSections(pathname) {
    const expanded = {};
    navigation.forEach((item) => {
        if (item.children && item.prefix && pathname.startsWith(item.prefix)) {
            expanded[item.name] = true;
        }
    });
    // Also expand Contents when on /kanban, /calendar, or /reports/content
    if (
        pathname.startsWith('/kanban') ||
        pathname.startsWith('/calendar') ||
        pathname.startsWith('/reports/content')
    ) {
        expanded['Contents'] = true;
    }
    return expanded;
}

function NavItem({ item, mobile, toggleSidebar }) {
    const Icon = item.icon;
    return (
        <NavLink
            to={item.to}
            end={item.end}
            onClick={mobile ? toggleSidebar : undefined}
            className={({ isActive: active }) =>
                cn(
                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    active
                        ? 'bg-zinc-900 text-white'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
                )
            }
        >
            {({ isActive: active }) => (
                <>
                    <Icon
                        className={cn(
                            'h-5 w-5 shrink-0',
                            active
                                ? 'text-white'
                                : 'text-zinc-400 group-hover:text-zinc-600'
                        )}
                    />
                    {item.name}
                </>
            )}
        </NavLink>
    );
}

function NavGroup({ item, expanded, onToggle, mobile, toggleSidebar, pathname }) {
    const Icon = item.icon;
    const isActive = pathname.startsWith(item.prefix) ||
        (item.name === 'Contents' && (
            pathname.startsWith('/kanban') ||
            pathname.startsWith('/calendar') ||
            pathname.startsWith('/reports/content')
        ));

    return (
        <div>
            <button
                onClick={onToggle}
                className={cn(
                    'group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    isActive
                        ? 'bg-zinc-100 text-zinc-900'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
                )}
            >
                <Icon
                    className={cn(
                        'h-5 w-5 shrink-0',
                        isActive
                            ? 'text-zinc-700'
                            : 'text-zinc-400 group-hover:text-zinc-600'
                    )}
                />
                {item.name}
                {expanded ? (
                    <ChevronDown className="ml-auto h-4 w-4 shrink-0 text-zinc-400" />
                ) : (
                    <ChevronRight className="ml-auto h-4 w-4 shrink-0 text-zinc-400" />
                )}
            </button>
            {expanded && (
                <div className="mt-0.5 space-y-0.5 pl-4">
                    {item.children.map((child) => {
                        const ChildIcon = child.icon;
                        return (
                            <NavLink
                                key={child.to}
                                to={child.to}
                                onClick={mobile ? toggleSidebar : undefined}
                                className={({ isActive: active }) =>
                                    cn(
                                        'group flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                        active
                                            ? 'bg-zinc-900 text-white'
                                            : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900'
                                    )
                                }
                            >
                                {({ isActive: active }) => (
                                    <>
                                        <ChildIcon
                                            className={cn(
                                                'h-4 w-4 shrink-0',
                                                active
                                                    ? 'text-white'
                                                    : 'text-zinc-400 group-hover:text-zinc-500'
                                            )}
                                        />
                                        {child.name}
                                    </>
                                )}
                            </NavLink>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function Sidebar({ mobile = false }) {
    const { sidebarOpen, toggleSidebar } = useCmsStore();
    const location = useLocation();
    const pathname = location.pathname.replace(/^\/cms/, '');
    const config = window.cmsConfig || {};
    const user = config.user || { name: 'User', role: 'Admin' };

    const [expandedSections, setExpandedSections] = useState(() =>
        getExpandedSections(pathname)
    );

    // Auto-expand section when navigating
    useEffect(() => {
        const autoExpanded = getExpandedSections(pathname);
        setExpandedSections((prev) => ({ ...prev, ...autoExpanded }));
    }, [pathname]);

    function toggleSection(name) {
        setExpandedSections((prev) => ({ ...prev, [name]: !prev[name] }));
    }

    return (
        <aside
            className={cn(
                'flex h-full flex-col border-r border-zinc-200 bg-white',
                mobile ? 'w-full' : 'w-[260px]'
            )}
        >
            {/* Brand */}
            <div className="flex h-16 items-center justify-between border-b border-zinc-200 px-5">
                <Link to="/" className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white text-sm font-bold">
                        CMS
                    </div>
                    <span className="text-base font-semibold text-zinc-900">
                        CMS Module
                    </span>
                </Link>
                {mobile && (
                    <button
                        onClick={toggleSidebar}
                        className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700"
                    >
                        <X className="h-5 w-5" />
                    </button>
                )}
            </div>

            {/* Navigation */}
            <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
                {navigation.map((item) => {
                    if (item.children) {
                        return (
                            <NavGroup
                                key={item.name}
                                item={item}
                                expanded={!!expandedSections[item.name]}
                                onToggle={() => toggleSection(item.name)}
                                mobile={mobile}
                                toggleSidebar={toggleSidebar}
                                pathname={pathname}
                            />
                        );
                    }

                    return (
                        <NavItem
                            key={item.to}
                            item={item}
                            mobile={mobile}
                            toggleSidebar={toggleSidebar}
                        />
                    );
                })}
            </nav>

            {/* Bottom section */}
            <div className="border-t border-zinc-200 p-3">
                <a
                    href={config.dashboardUrl || '/dashboard'}
                    onClick={(e) => { e.preventDefault(); window.location.href = config.dashboardUrl || '/dashboard'; }}
                    className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Main App
                </a>

                <div className="mt-2 flex items-center gap-3 rounded-lg px-3 py-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-600">
                        {user.name?.charAt(0)?.toUpperCase() || 'U'}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-zinc-900">
                            {user.name}
                        </p>
                        <p className="truncate text-xs text-zinc-500">
                            {user.role}
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    );
}

export default function CmsLayout() {
    const { sidebarOpen, toggleSidebar } = useCmsStore();

    return (
        <div className="flex h-screen overflow-hidden bg-zinc-50">
            {/* Desktop sidebar */}
            <div className="hidden lg:flex lg:shrink-0">
                <Sidebar />
            </div>

            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        className="fixed inset-0 bg-black/30 backdrop-blur-sm"
                        onClick={toggleSidebar}
                    />
                    <div className="fixed inset-y-0 left-0 z-50 w-[280px] shadow-xl">
                        <Sidebar mobile />
                    </div>
                </div>
            )}

            {/* Main content */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Mobile header */}
                <header className="flex h-14 items-center justify-between border-b border-zinc-200 bg-white px-4 lg:hidden">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={toggleSidebar}
                            className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700"
                        >
                            <Menu className="h-5 w-5" />
                        </button>
                        <div className="flex items-center gap-2">
                            <div className="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600 text-white text-xs font-bold">
                                CMS
                            </div>
                            <span className="text-sm font-semibold text-zinc-900">
                                CMS Module
                            </span>
                        </div>
                    </div>
                </header>

                {/* Page content */}
                <main className="flex-1 overflow-y-auto">
                    <div className="px-4 py-6 sm:px-6 lg:px-8">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}

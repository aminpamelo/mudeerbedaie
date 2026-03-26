import { Outlet, NavLink } from 'react-router-dom';
import {
    User,
    CalendarCheck,
    Clock,
    Receipt,
    ArrowLeft,
    MoreHorizontal,
} from 'lucide-react';
import { cn } from '../lib/utils';

const tabs = [
    { name: 'My Profile', to: '/', icon: User, end: true },
    { name: 'Attendance', to: '/attendance', icon: CalendarCheck, coming: true },
    { name: 'Leave', to: '/leave', icon: Clock, coming: true },
    { name: 'Payslip', to: '/payslip', icon: Receipt, coming: true },
    { name: 'More', to: '/more', icon: MoreHorizontal, coming: true },
];

function TopHeader({ user }) {
    return (
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-zinc-200 bg-white px-4 lg:hidden">
            <a
                href={window.hrConfig?.dashboardUrl || '/dashboard'}
                onClick={(e) => {
                    e.preventDefault();
                    window.location.href = window.hrConfig?.dashboardUrl || '/dashboard';
                }}
                className="flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900"
            >
                <ArrowLeft className="h-5 w-5" />
            </a>

            <span className="text-sm font-semibold text-zinc-900">HR Portal</span>

            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                {user.name?.charAt(0)?.toUpperCase() || 'U'}
            </div>
        </header>
    );
}

function BottomTabs() {
    return (
        <nav className="fixed bottom-0 left-0 right-0 z-30 border-t border-zinc-200 bg-white lg:hidden">
            <div className="flex items-stretch">
                {tabs.map((tab) => (
                    <NavLink
                        key={tab.name}
                        to={tab.coming ? '#' : tab.to}
                        end={tab.end}
                        onClick={tab.coming ? (e) => e.preventDefault() : undefined}
                        className={({ isActive }) =>
                            cn(
                                'flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium transition-colors relative',
                                tab.coming
                                    ? 'text-zinc-300 cursor-default'
                                    : isActive
                                      ? 'text-blue-600'
                                      : 'text-zinc-400 active:text-zinc-600'
                            )
                        }
                    >
                        {({ isActive }) => (
                            <>
                                <tab.icon
                                    className={cn(
                                        'h-5 w-5 transition-colors',
                                        tab.coming
                                            ? 'text-zinc-300'
                                            : isActive
                                              ? 'text-blue-600'
                                              : 'text-zinc-400'
                                    )}
                                    strokeWidth={!tab.coming && isActive ? 2.25 : 1.75}
                                />
                                <span>{tab.name}</span>
                                {tab.coming && (
                                    <span className="absolute -top-0.5 right-1/2 translate-x-1/2 text-[8px] text-zinc-400 font-normal">
                                        soon
                                    </span>
                                )}
                            </>
                        )}
                    </NavLink>
                ))}
            </div>
            <div className="h-[env(safe-area-inset-bottom)]" />
        </nav>
    );
}

function DesktopSidebar({ user }) {
    return (
        <aside className="hidden lg:flex lg:w-[260px] lg:shrink-0 lg:flex-col lg:border-r lg:border-zinc-200 lg:bg-white">
            <div className="flex h-16 items-center gap-3 border-b border-zinc-200 px-5">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                    {user.name?.charAt(0)?.toUpperCase() || 'U'}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-zinc-900">HR Portal</p>
                    <p className="truncate text-xs text-zinc-500">{user.name}</p>
                </div>
            </div>

            <nav className="flex-1 space-y-1 px-3 py-4">
                {tabs.map((tab) => (
                    <NavLink
                        key={tab.name}
                        to={tab.coming ? '#' : tab.to}
                        end={tab.end}
                        onClick={tab.coming ? (e) => e.preventDefault() : undefined}
                        className={({ isActive }) =>
                            cn(
                                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                tab.coming
                                    ? 'text-zinc-300 cursor-default'
                                    : isActive
                                      ? 'bg-blue-600 text-white'
                                      : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
                            )
                        }
                    >
                        {({ isActive }) => (
                            <>
                                <tab.icon
                                    className={cn(
                                        'h-5 w-5 shrink-0',
                                        tab.coming
                                            ? 'text-zinc-300'
                                            : isActive
                                              ? 'text-white'
                                              : 'text-zinc-400 group-hover:text-zinc-600'
                                    )}
                                />
                                <span className="flex-1">{tab.name}</span>
                                {tab.coming && (
                                    <span className="text-[10px] text-zinc-400 bg-zinc-100 rounded px-1.5 py-0.5">Soon</span>
                                )}
                            </>
                        )}
                    </NavLink>
                ))}
            </nav>

            <div className="border-t border-zinc-200 p-3">
                <a
                    href={window.hrConfig?.dashboardUrl || '/dashboard'}
                    onClick={(e) => {
                        e.preventDefault();
                        window.location.href = window.hrConfig?.dashboardUrl || '/dashboard';
                    }}
                    className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Main App
                </a>
            </div>
        </aside>
    );
}

export default function EmployeeAppLayout() {
    const config = window.hrConfig || {};
    const user = config.user || { name: 'User' };

    return (
        <div className="flex h-screen overflow-hidden bg-zinc-50">
            <DesktopSidebar user={user} />

            <div className="flex flex-1 flex-col overflow-hidden">
                <TopHeader user={user} />

                <main className="flex-1 overflow-y-auto pb-20 lg:pb-0">
                    <div className="mx-auto max-w-3xl px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
                        <Outlet />
                    </div>
                </main>

                <BottomTabs />
            </div>
        </div>
    );
}

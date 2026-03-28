import { useState } from 'react';
import { Outlet, NavLink, Link, useNavigate } from 'react-router-dom';
import {
    User,
    CalendarCheck,
    Clock,
    Timer,
    CalendarOff,
    FilePlus,
    Receipt,
    ArrowLeft,
    MoreHorizontal,
    Wallet,
    Bell,
    X,
    CalendarRange,
    ListTodo,
} from 'lucide-react';
import { cn } from '../lib/utils';
import NotificationBell from '../components/NotificationBell';

const tabs = [
    { name: 'Clock', to: '/clock', icon: Clock },
    { name: 'Attendance', to: '/my/attendance', icon: CalendarCheck },
    { name: 'Leave', to: '/my/leave', icon: CalendarOff },
    { name: 'More', to: '#', icon: MoreHorizontal, isMore: true },
];

const moreMenuItems = [
    { name: 'My Claims', to: '/my/claims', icon: Receipt, description: 'Submit and track expense claims' },
    { name: 'My Payslips', to: '/my/payslips', icon: Wallet, description: 'View and download payslips' },
    { name: 'My Overtime', to: '/my/overtime', icon: Timer, description: 'View overtime requests' },
    { name: 'My Meetings', to: '/my/meetings', icon: CalendarRange, description: 'View your meeting invitations' },
    { name: 'My Tasks', to: '/my/tasks', icon: ListTodo, description: 'View action items from meetings' },
    { name: 'Notifications', to: '/notifications', icon: Bell, description: 'View notifications & push settings' },
];

const sidebarNav = [
    { name: 'My Profile', to: '/', icon: User, end: true },
    { name: 'Clock In/Out', to: '/clock', icon: Clock },
    { name: 'My Attendance', to: '/my/attendance', icon: CalendarCheck },
    { name: 'My Overtime', to: '/my/overtime', icon: Timer },
    { name: 'My Leave', to: '/my/leave', icon: CalendarOff },
    { name: 'Apply Leave', to: '/my/leave/apply', icon: FilePlus },
    { name: 'My Claims', to: '/my/claims', icon: Receipt },
    { name: 'My Payslips', to: '/my/payslips', icon: Wallet },
    { name: 'My Meetings', to: '/my/meetings', icon: CalendarRange },
    { name: 'My Tasks', to: '/my/tasks', icon: ListTodo },
    { name: 'Notifications', to: '/notifications', icon: Bell },
];

function TopHeader({ user }) {
    return (
        <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200/80 bg-white/80 backdrop-blur-lg px-4 lg:hidden">
            <a
                href={window.hrConfig?.dashboardUrl || '/dashboard'}
                onClick={(e) => {
                    e.preventDefault();
                    window.location.href = window.hrConfig?.dashboardUrl || '/dashboard';
                }}
                className="flex items-center gap-1.5 text-sm text-slate-400 transition-colors hover:text-slate-700"
            >
                <ArrowLeft className="h-5 w-5" />
            </a>

            <span className="text-sm font-semibold text-slate-800">HR Portal</span>

            <div className="flex items-center gap-1">
                <NotificationBell />
                <Link
                    to="/"
                    className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-semibold text-white shadow-sm transition-shadow hover:shadow-md active:scale-95"
                >
                    {user.name?.charAt(0)?.toUpperCase() || 'U'}
                </Link>
            </div>
        </header>
    );
}

function BottomTabs() {
    const [showMore, setShowMore] = useState(false);
    const navigate = useNavigate();

    return (
        <>
            {/* More Menu Overlay */}
            {showMore && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-black/30 backdrop-blur-sm" onClick={() => setShowMore(false)} />
                    <div className="absolute bottom-[calc(3.5rem+env(safe-area-inset-bottom))] left-0 right-0 rounded-t-2xl bg-white p-4 shadow-2xl">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-slate-800">More</h3>
                            <button onClick={() => setShowMore(false)} className="rounded-full p-1 text-slate-400 hover:bg-slate-100">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <div className="space-y-1">
                            {moreMenuItems.map((item) => (
                                <button
                                    key={item.name}
                                    onClick={() => {
                                        setShowMore(false);
                                        navigate(item.to);
                                    }}
                                    className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition-colors hover:bg-slate-50 active:bg-slate-100"
                                >
                                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50">
                                        <item.icon className="h-4.5 w-4.5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-slate-800">{item.name}</p>
                                        <p className="text-xs text-slate-500">{item.description}</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            <nav className="fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200/80 bg-white/90 backdrop-blur-lg lg:hidden">
                <div className="flex items-stretch">
                    {tabs.map((tab) => {
                        if (tab.isMore) {
                            return (
                                <button
                                    key={tab.name}
                                    onClick={() => setShowMore(!showMore)}
                                    className={cn(
                                        'flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium transition-colors',
                                        showMore ? 'text-indigo-600' : 'text-slate-400 active:text-slate-600'
                                    )}
                                >
                                    <tab.icon className={cn('h-5 w-5', showMore ? 'text-indigo-600' : 'text-slate-400')} strokeWidth={1.75} />
                                    <span>{tab.name}</span>
                                </button>
                            );
                        }
                        return (
                            <NavLink
                                key={tab.name}
                                to={tab.to}
                                end={tab.end}
                                onClick={() => setShowMore(false)}
                                className={({ isActive }) =>
                                    cn(
                                        'flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium transition-colors',
                                        isActive ? 'text-indigo-600' : 'text-slate-400 active:text-slate-600'
                                    )
                                }
                            >
                                {({ isActive }) => (
                                    <>
                                        <tab.icon
                                            className={cn('h-5 w-5 transition-colors', isActive ? 'text-indigo-600' : 'text-slate-400')}
                                            strokeWidth={isActive ? 2.25 : 1.75}
                                        />
                                        <span>{tab.name}</span>
                                    </>
                                )}
                            </NavLink>
                        );
                    })}
                </div>
                <div className="h-[env(safe-area-inset-bottom)]" />
            </nav>
        </>
    );
}

function DesktopSidebar({ user }) {
    return (
        <aside className="hidden lg:flex lg:w-[260px] lg:shrink-0 lg:flex-col lg:border-r lg:border-slate-200/80 lg:bg-white">
            <div className="flex h-16 items-center gap-3 border-b border-slate-200/80 px-5">
                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-semibold text-white shadow-sm">
                    {user.name?.charAt(0)?.toUpperCase() || 'U'}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-slate-800">HR Portal</p>
                    <p className="truncate text-xs text-slate-500">{user.name}</p>
                </div>
            </div>

            <nav className="flex-1 space-y-0.5 px-3 py-4">
                {sidebarNav.map((item) => (
                    <NavLink
                        key={item.name}
                        to={item.to}
                        end={item.end}
                        className={({ isActive }) =>
                            cn(
                                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all',
                                isActive
                                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/20'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            )
                        }
                    >
                        {({ isActive }) => (
                            <>
                                <item.icon
                                    className={cn(
                                        'h-5 w-5 shrink-0',
                                        isActive
                                            ? 'text-white'
                                            : 'text-slate-400 group-hover:text-slate-600'
                                    )}
                                />
                                <span className="flex-1">{item.name}</span>
                            </>
                        )}
                    </NavLink>
                ))}
            </nav>

            <div className="border-t border-slate-200/80 p-3">
                <a
                    href={window.hrConfig?.dashboardUrl || '/dashboard'}
                    onClick={(e) => {
                        e.preventDefault();
                        window.location.href = window.hrConfig?.dashboardUrl || '/dashboard';
                    }}
                    className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
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
        <div className="flex h-screen overflow-hidden bg-slate-50">
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

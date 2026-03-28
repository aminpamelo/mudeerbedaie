import { useEffect } from 'react';
import { Outlet, NavLink, Link } from 'react-router-dom';
import {
    LayoutDashboard,
    Users,
    Building2,
    Briefcase,
    Menu,
    X,
    ArrowLeft,
    ChevronRight,
    Clock,
    ClipboardList,
    CalendarClock,
    Timer,
    CalendarDays,
    BarChart3,
    CalendarOff,
    FileText,
    Calendar,
    Scale,
    Tags,
    UserCheck,
    Clock4,
    DollarSign,
    Settings2,
    ShieldCheck,
    Receipt,
    Shield,
    Package,
    Package2,
    Tag,
    RotateCcw,
    BarChart2,
    Banknote,
} from 'lucide-react';
import useHrStore from '../stores/useHrStore';
import usePushSubscription from '../hooks/usePushSubscription';
import NotificationBell from '../components/NotificationBell';
import { cn } from '../lib/utils';

const navigation = [
    { name: 'Dashboard', to: '/', icon: LayoutDashboard, end: true },
    { name: 'Employees', to: '/employees', icon: Users },
    { name: 'Departments', to: '/departments', icon: Building2 },
    { name: 'Positions', to: '/positions', icon: Briefcase },
    { type: 'separator' },
    { name: 'Attendance', to: '/attendance', icon: Clock },
    { name: 'Records', to: '/attendance/records', icon: ClipboardList, indent: true },
    { name: 'Schedules', to: '/attendance/schedules', icon: CalendarClock, indent: true },
    { name: 'Assignments', to: '/attendance/assignments', icon: UserCheck, indent: true },
    { name: 'Overtime', to: '/attendance/overtime', icon: Timer, indent: true },
    { name: 'Holidays', to: '/attendance/holidays', icon: CalendarDays, indent: true },
    { name: 'Analytics', to: '/attendance/analytics', icon: BarChart3, indent: true },
    { type: 'separator' },
    { name: 'Leave', to: '/leave', icon: CalendarOff },
    { name: 'Requests', to: '/leave/requests', icon: FileText, indent: true },
    { name: 'Calendar', to: '/leave/calendar', icon: Calendar, indent: true },
    { name: 'Balances', to: '/leave/balances', icon: Scale, indent: true },
    { name: 'Types', to: '/leave/types', icon: Tags, indent: true },
    { type: 'separator' },
    { name: 'Approvers', to: '/attendance/approvers', icon: UserCheck },
    { name: 'Clock In/Out', to: '/clock', icon: Clock4 },
    { type: 'separator' },
    { name: 'Payroll', to: '/payroll', icon: DollarSign },
    { name: 'Payroll History', to: '/payroll/history', icon: ClipboardList, indent: true },
    { name: 'Salary Components', to: '/payroll/components', icon: Settings2, indent: true },
    { name: 'Employee Salaries', to: '/payroll/salaries', icon: Users, indent: true },
    { name: 'Tax Profiles', to: '/payroll/tax-profiles', icon: FileText, indent: true },
    { name: 'Statutory Rates', to: '/payroll/statutory-rates', icon: ShieldCheck, indent: true },
    { name: 'EA Forms', to: '/payroll/ea-forms', icon: Receipt, indent: true },
    { name: 'Reports', to: '/payroll/reports', icon: BarChart3, indent: true },
    { name: 'Settings', to: '/payroll/settings', icon: Settings2, indent: true },
    { type: 'separator' },
    { name: 'Claims', to: '/claims', icon: Banknote },
    { name: 'Requests', to: '/claims/requests', icon: FileText, indent: true },
    { name: 'Types', to: '/claims/types', icon: Tags, indent: true },
    { name: 'Approvers', to: '/claims/approvers', icon: UserCheck, indent: true },
    { name: 'Reports', to: '/claims/reports', icon: BarChart2, indent: true },
    { type: 'separator' },
    { name: 'Benefits', to: '/benefits', icon: Shield },
    { name: 'Benefit Types', to: '/benefits/types', icon: Tag, indent: true },
    { type: 'separator' },
    { name: 'Assets', to: '/assets', icon: Package },
    { name: 'Inventory', to: '/assets/inventory', icon: Package2, indent: true },
    { name: 'Categories', to: '/assets/categories', icon: Tag, indent: true },
    { name: 'Assignments', to: '/assets/assignments', icon: RotateCcw, indent: true },
];

function Sidebar({ mobile = false }) {
    const { sidebarOpen, toggleSidebar } = useHrStore();
    const config = window.hrConfig || {};
    const user = config.user || { name: 'User', role: 'Admin' };

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
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-900 text-white text-sm font-bold">
                        HR
                    </div>
                    <span className="text-base font-semibold text-zinc-900">
                        HR Module
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
                {navigation.map((item, idx) => {
                    if (item.type === 'separator') {
                        return (
                            <div key={`sep-${idx}`} className="my-2 border-t border-zinc-100" />
                        );
                    }

                    return (
                        <NavLink
                            key={item.name}
                            to={item.to}
                            end={item.end}
                            onClick={mobile ? toggleSidebar : undefined}
                            className={({ isActive }) =>
                                cn(
                                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    item.indent && 'ml-4 text-xs',
                                    isActive
                                        ? 'bg-zinc-900 text-white'
                                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
                                )
                            }
                        >
                            {({ isActive }) => (
                                <>
                                    <item.icon
                                        className={cn(
                                            'shrink-0',
                                            item.indent ? 'h-4 w-4' : 'h-5 w-5',
                                            isActive
                                                ? 'text-white'
                                                : 'text-zinc-400 group-hover:text-zinc-600'
                                        )}
                                    />
                                    {item.name}
                                    <ChevronRight
                                        className={cn(
                                            'ml-auto h-4 w-4 shrink-0 transition-colors',
                                            isActive
                                                ? 'text-zinc-400'
                                                : 'text-transparent group-hover:text-zinc-300'
                                        )}
                                    />
                                </>
                            )}
                        </NavLink>
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

export default function HrLayout() {
    const { sidebarOpen, toggleSidebar } = useHrStore();
    const { isSubscribed, isSupported, subscribe } = usePushSubscription();

    useEffect(() => {
        if (isSupported && !isSubscribed) {
            subscribe();
        }
    }, [isSupported, isSubscribed]);

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
                {/* Desktop top bar */}
                <div className="hidden h-14 items-center justify-end border-b border-zinc-200 bg-white px-6 lg:flex">
                    <NotificationBell />
                </div>

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
                            <div className="flex h-7 w-7 items-center justify-center rounded-md bg-zinc-900 text-white text-xs font-bold">
                                HR
                            </div>
                            <span className="text-sm font-semibold text-zinc-900">
                                HR Module
                            </span>
                        </div>
                    </div>
                    <NotificationBell />
                </header>

                {/* Page content */}
                <main className="flex-1 overflow-y-auto">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}

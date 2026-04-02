import { useState, useEffect } from 'react';
import { Outlet, NavLink, Link, useLocation } from 'react-router-dom';
import {
    LayoutDashboard,
    Users,
    Building2,
    Briefcase,
    Menu,
    X,
    ArrowLeft,
    ChevronRight,
    ChevronDown,
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
    CalendarRange,
    ListTodo,
    ListOrdered,
    UserPlus,
    Search,
    MessageSquare,
    ClipboardCheck,
    Star,
    Target,
    TrendingUp,
    AlertTriangle,
    Gavel,
    UserMinus,
    CheckSquare,
    MessageCircle,
    Calculator,
    FileSignature,
    GraduationCap,
    BookOpen,
    Award,
    Wallet,
    Network,
    DoorOpen,
} from 'lucide-react';
import useHrStore from '../stores/useHrStore';
import NotificationBell from '../components/NotificationBell';
import { cn } from '../lib/utils';

const navigation = [
    { name: 'Dashboard', to: '/', icon: LayoutDashboard, end: true },
    { name: 'Employees', to: '/employees', icon: Users },
    { name: 'Departments', to: '/departments', icon: Building2 },
    { name: 'Positions', to: '/positions', icon: Briefcase },
    { name: 'Org Chart', to: '/org-chart', icon: Network },
    { name: 'Approvers', to: '/attendance/approvers', icon: UserCheck },
    {
        name: 'Attendance',
        icon: Clock,
        prefix: '/attendance',
        children: [
            { name: 'Records', to: '/attendance/records', icon: ClipboardList },
            { name: 'Monthly View', to: '/attendance/monthly', icon: CalendarRange },
            { name: 'Schedules', to: '/attendance/schedules', icon: CalendarClock },
            { name: 'Assignments', to: '/attendance/assignments', icon: UserCheck },
            { name: 'Overtime', to: '/attendance/overtime', icon: Timer },
            { name: 'Holidays', to: '/attendance/holidays', icon: CalendarDays },
            { name: 'Analytics', to: '/attendance/analytics', icon: BarChart3 },
            { name: 'Settings', to: '/attendance/settings', icon: Settings2 },
        ],
    },
    {
        name: 'Leave',
        icon: CalendarOff,
        prefix: '/leave',
        children: [
            { name: 'Requests', to: '/leave/requests', icon: FileText },
            { name: 'Calendar', to: '/leave/calendar', icon: Calendar },
            { name: 'Balances', to: '/leave/balances', icon: Scale },
            { name: 'Entitlements', to: '/leave/entitlements', icon: BookOpen },
            { name: 'Types', to: '/leave/types', icon: Tags },
        ],
    },
    { name: 'Clock In/Out', to: '/clock', icon: Clock4 },
    {
        name: 'Payroll',
        icon: DollarSign,
        prefix: '/payroll',
        children: [
            { name: 'Payroll History', to: '/payroll/history', icon: ClipboardList },
            { name: 'Salary Components', to: '/payroll/components', icon: Settings2 },
            { name: 'Employee Salaries', to: '/payroll/salaries', icon: Users },
            { name: 'Tax Profiles', to: '/payroll/tax-profiles', icon: FileText },
            { name: 'Statutory Rates', to: '/payroll/statutory-rates', icon: ShieldCheck },
            { name: 'EA Forms', to: '/payroll/ea-forms', icon: Receipt },
            { name: 'Reports', to: '/payroll/reports', icon: BarChart3 },
            { name: 'Settings', to: '/payroll/settings', icon: Settings2 },
        ],
    },
    {
        name: 'Claims',
        icon: Banknote,
        prefix: '/claims',
        children: [
            { name: 'Requests', to: '/claims/requests', icon: FileText },
            { name: 'Types', to: '/claims/types', icon: Tags },
            { name: 'Approvers', to: '/claims/approvers', icon: UserCheck },
            { name: 'Reports', to: '/claims/reports', icon: BarChart2 },
        ],
    },
    {
        name: 'Exit Permissions',
        icon: DoorOpen,
        prefix: '/exit-permissions',
        children: [
            { name: 'Requests', to: '/exit-permissions', icon: FileText },
            { name: 'Notifiers', to: '/exit-permissions/notifiers', icon: UserCheck },
        ],
    },
    {
        name: 'Benefits',
        icon: Shield,
        prefix: '/benefits',
        children: [
            { name: 'Benefit Types', to: '/benefits/types', icon: Tag },
        ],
    },
    {
        name: 'Assets',
        icon: Package,
        prefix: '/assets',
        children: [
            { name: 'Inventory', to: '/assets/inventory', icon: Package2 },
            { name: 'Categories', to: '/assets/categories', icon: Tag },
            { name: 'Assignments', to: '/assets/assignments', icon: RotateCcw },
        ],
    },
    {
        name: 'Recruitment',
        icon: UserPlus,
        prefix: '/recruitment',
        children: [
            { name: 'Dashboard', to: '/recruitment', icon: LayoutDashboard },
            { name: 'Job Postings', to: '/recruitment/postings', icon: FileText },
            { name: 'Applicants', to: '/recruitment/applicants', icon: Search },
            { name: 'Interviews', to: '/recruitment/interviews', icon: MessageSquare },
            { name: 'Onboarding', to: '/onboarding', icon: ClipboardCheck },
            { name: 'Templates', to: '/onboarding/templates', icon: ListTodo },
        ],
    },
    {
        name: 'Performance',
        icon: Star,
        prefix: '/performance',
        children: [
            { name: 'Dashboard', to: '/performance', icon: LayoutDashboard },
            { name: 'Review Cycles', to: '/performance/cycles', icon: Target },
            { name: 'KPI Templates', to: '/performance/kpis', icon: TrendingUp },
            { name: 'PIPs', to: '/performance/pips', icon: AlertTriangle },
            { name: 'Rating Scales', to: '/performance/rating-scales', icon: BarChart3 },
        ],
    },
    {
        name: 'Disciplinary',
        icon: Gavel,
        prefix: '/disciplinary',
        children: [
            { name: 'Dashboard', to: '/disciplinary', icon: LayoutDashboard },
            { name: 'Records', to: '/disciplinary/records', icon: ClipboardList },
            { name: 'Letter Templates', to: '/disciplinary/letter-templates', icon: FileSignature },
        ],
    },
    {
        name: 'Offboarding',
        icon: UserMinus,
        prefix: '/offboarding',
        children: [
            { name: 'Resignations', to: '/offboarding/resignations', icon: FileText },
            { name: 'Exit Checklists', to: '/offboarding/checklists', icon: CheckSquare },
            { name: 'Exit Interviews', to: '/offboarding/exit-interviews', icon: MessageCircle },
            { name: 'Settlements', to: '/offboarding/settlements', icon: Calculator },
        ],
    },
    {
        name: 'Training',
        icon: GraduationCap,
        prefix: '/training',
        children: [
            { name: 'Dashboard', to: '/training', icon: LayoutDashboard },
            { name: 'Programs', to: '/training/programs', icon: BookOpen },
            { name: 'Certifications', to: '/training/certifications', icon: Award },
            { name: 'Employee Certs', to: '/training/employee-certifications', icon: ShieldCheck },
            { name: 'Budgets', to: '/training/budgets', icon: Wallet },
            { name: 'Reports', to: '/training/reports', icon: BarChart3 },
        ],
    },
    {
        name: 'Meetings',
        icon: CalendarRange,
        prefix: '/meetings',
        children: [
            { name: 'All Meetings', to: '/meetings', icon: CalendarDays },
            { name: 'Meeting Series', to: '/meetings/series', icon: ListOrdered },
            { name: 'Task Dashboard', to: '/meetings/tasks', icon: ListTodo },
        ],
    },
];

function getExpandedSections(pathname) {
    const expanded = {};
    navigation.forEach((item) => {
        if (item.children && item.prefix && pathname.startsWith(item.prefix)) {
            expanded[item.name] = true;
        }
    });
    return expanded;
}

function NavItem({ item, isActive, mobile, toggleSidebar }) {
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
    const isActive = pathname.startsWith(item.prefix);

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
    const { sidebarOpen, toggleSidebar } = useHrStore();
    const location = useLocation();
    const pathname = location.pathname.replace(/^\/hr/, '');
    const config = window.hrConfig || {};
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

export default function HrLayout() {
    const { sidebarOpen, toggleSidebar } = useHrStore();

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
                    <div className="px-4 py-6 sm:px-6 lg:px-8">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}

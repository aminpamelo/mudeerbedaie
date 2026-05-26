import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
    Search,
    LayoutDashboard,
    Users,
    Building2,
    Briefcase,
    Network,
    Clock,
    CalendarOff,
    DollarSign,
    Receipt,
    Package,
    UserPlus,
    Star,
    Gavel,
    GraduationCap,
    UserMinus,
    CalendarRange,
    ShieldCheck,
    Wallet,
    DoorOpen,
    Bell,
    Settings2,
    CornerDownLeft,
    Command,
    User,
    FileText,
    Timer,
    ListTodo,
} from 'lucide-react';
import { fetchEmployees } from '../lib/api';
import { cn } from '../lib/utils';

/**
 * Global ⌘K command palette. Mount once at app root.
 * Open with Ctrl/⌘+K. Search routes + employees.
 */
export function CommandPalette({ isAdmin }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);
    const navigate = useNavigate();

    // ⌘K / Ctrl+K to toggle
    useEffect(() => {
        function handleKey(e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        }
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, []);

    // Focus input when opened, reset state when closed
    useEffect(() => {
        if (open) {
            setQuery('');
            setActiveIndex(0);
            requestAnimationFrame(() => inputRef.current?.focus());
        }
    }, [open]);

    // Esc to close
    useEffect(() => {
        if (!open) return;
        function handleEsc(e) {
            if (e.key === 'Escape') setOpen(false);
        }
        window.addEventListener('keydown', handleEsc);
        return () => window.removeEventListener('keydown', handleEsc);
    }, [open]);

    // Lazy load employees only when palette is open and query is non-empty
    const { data: employeesData } = useQuery({
        queryKey: ['cmdk-employees', query],
        queryFn: () => fetchEmployees({ search: query, per_page: 8 }),
        enabled: open && isAdmin && query.length > 1,
        staleTime: 30 * 1000,
    });
    const employees = employeesData?.data || [];

    const navItems = useMemo(() => isAdmin ? ADMIN_NAV : EMPLOYEE_NAV, [isAdmin]);

    const filteredNav = useMemo(() => {
        if (!query) return navItems.slice(0, 8);
        const q = query.toLowerCase();
        return navItems
            .filter((item) =>
                item.name.toLowerCase().includes(q) ||
                item.section?.toLowerCase().includes(q) ||
                item.keywords?.some((k) => k.toLowerCase().includes(q))
            )
            .slice(0, 12);
    }, [query, navItems]);

    // Combined flat list for keyboard navigation
    const allItems = useMemo(() => {
        const items = [];
        filteredNav.forEach((nav, i) =>
            items.push({ kind: 'nav', data: nav, key: `nav-${i}` })
        );
        employees.forEach((emp) =>
            items.push({ kind: 'employee', data: emp, key: `emp-${emp.id}` })
        );
        return items;
    }, [filteredNav, employees]);

    // Reset active index when items change
    useEffect(() => { setActiveIndex(0); }, [allItems.length]);

    function handleSelect(item) {
        if (item.kind === 'nav') {
            navigate(item.data.to);
        } else {
            navigate(`/employees/${item.data.id}`);
        }
        setOpen(false);
    }

    function handleListKey(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex((i) => Math.min(allItems.length - 1, i + 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex((i) => Math.max(0, i - 1));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (allItems[activeIndex]) handleSelect(allItems[activeIndex]);
        }
    }

    // Scroll active item into view
    useEffect(() => {
        if (!listRef.current) return;
        const active = listRef.current.querySelector('[data-active="true"]');
        active?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-[200] flex items-start justify-center pt-[15vh] px-4">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                onClick={() => setOpen(false)}
                aria-hidden
            />
            {/* Palette */}
            <div
                className="relative w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl shadow-slate-900/20 ring-1 ring-black/5"
                onKeyDown={handleListKey}
            >
                {/* Search input */}
                <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
                    <Search className="h-4 w-4 shrink-0 text-slate-400" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={isAdmin ? 'Search pages, employees…' : 'Search pages…'}
                        className="flex-1 bg-transparent text-sm placeholder:text-slate-400 focus:outline-none"
                    />
                    <kbd className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                        ESC
                    </kbd>
                </div>

                {/* Results */}
                <div ref={listRef} className="max-h-[60vh] overflow-y-auto py-1">
                    {filteredNav.length === 0 && employees.length === 0 && (
                        <div className="px-4 py-12 text-center">
                            <p className="text-sm text-slate-500">No results for &ldquo;{query}&rdquo;</p>
                        </div>
                    )}

                    {filteredNav.length > 0 && (
                        <div className="py-1">
                            <div className="px-3 pb-1 pt-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Pages
                            </div>
                            {filteredNav.map((nav, navIdx) => {
                                const globalIdx = navIdx;
                                const isActive = globalIdx === activeIndex;
                                const Icon = nav.icon;
                                return (
                                    <button
                                        key={nav.to}
                                        data-active={isActive}
                                        onClick={() => handleSelect({ kind: 'nav', data: nav })}
                                        onMouseEnter={() => setActiveIndex(globalIdx)}
                                        className={cn(
                                            'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors',
                                            isActive
                                                ? 'bg-gradient-to-r from-indigo-50 via-pink-50 to-orange-50'
                                                : 'hover:bg-slate-50'
                                        )}
                                    >
                                        <div className={cn(
                                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                                            isActive
                                                ? 'bg-gradient-to-br from-indigo-500 via-pink-500 to-orange-400 text-white shadow-sm shadow-pink-500/30'
                                                : 'bg-slate-100 text-slate-500'
                                        )}>
                                            {Icon && <Icon className="h-3.5 w-3.5" strokeWidth={2.25} />}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-slate-900">{nav.name}</p>
                                            {nav.section && (
                                                <p className="truncate text-[11px] text-slate-400">{nav.section}</p>
                                            )}
                                        </div>
                                        {isActive && (
                                            <CornerDownLeft className="h-3.5 w-3.5 shrink-0 text-pink-500" strokeWidth={2.5} />
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {employees.length > 0 && (
                        <div className="border-t border-slate-100 py-1">
                            <div className="px-3 pb-1 pt-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                Employees
                            </div>
                            {employees.map((emp, empIdx) => {
                                const globalIdx = filteredNav.length + empIdx;
                                const isActive = globalIdx === activeIndex;
                                return (
                                    <button
                                        key={emp.id}
                                        data-active={isActive}
                                        onClick={() => handleSelect({ kind: 'employee', data: emp })}
                                        onMouseEnter={() => setActiveIndex(globalIdx)}
                                        className={cn(
                                            'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors',
                                            isActive
                                                ? 'bg-gradient-to-r from-indigo-50 via-pink-50 to-orange-50'
                                                : 'hover:bg-slate-50'
                                        )}
                                    >
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-indigo-100 to-pink-100 text-[10px] font-bold text-indigo-700 ring-2 ring-white">
                                            {emp.profile_photo_url ? (
                                                <img src={emp.profile_photo_url} alt="" className="h-full w-full object-cover" />
                                            ) : (
                                                emp.full_name?.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase()
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-slate-900">{emp.full_name}</p>
                                            <p className="truncate text-[11px] text-slate-400 tabular-nums">
                                                {emp.employee_id}
                                                {emp.position?.title && ` · ${emp.position.title}`}
                                            </p>
                                        </div>
                                        {isActive && (
                                            <CornerDownLeft className="h-3.5 w-3.5 shrink-0 text-pink-500" strokeWidth={2.5} />
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/60 px-3 py-2 text-[10px] text-slate-500">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded border border-slate-300 bg-white px-1 font-mono text-[9px]">↑</kbd>
                            <kbd className="rounded border border-slate-300 bg-white px-1 font-mono text-[9px]">↓</kbd>
                            navigate
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded border border-slate-300 bg-white px-1 font-mono text-[9px]">↵</kbd>
                            open
                        </span>
                    </div>
                    <span className="inline-flex items-center gap-1">
                        <Command className="h-3 w-3" />
                        <span className="font-mono">K</span>
                    </span>
                </div>
            </div>
        </div>
    );
}

// ─── Navigation data ─────────────────────────────────────────────────────────

const ADMIN_NAV = [
    { name: 'Dashboard', to: '/', icon: LayoutDashboard, section: 'Home' },
    { name: 'Employees', to: '/employees', icon: Users, section: 'People' },
    { name: 'Departments', to: '/departments', icon: Building2, section: 'People' },
    { name: 'Positions', to: '/positions', icon: Briefcase, section: 'People' },
    { name: 'Org Chart', to: '/org-chart', icon: Network, section: 'People' },
    { name: 'Add Employee', to: '/employees/create', icon: UserPlus, section: 'People · Quick action', keywords: ['new', 'create'] },

    { name: 'Attendance Dashboard', to: '/attendance', icon: Clock, section: 'Attendance' },
    { name: 'Attendance Records', to: '/attendance/records', icon: Clock, section: 'Attendance' },
    { name: 'Monthly View', to: '/attendance/monthly', icon: CalendarRange, section: 'Attendance' },
    { name: 'Work Schedules', to: '/attendance/schedules', icon: Clock, section: 'Attendance' },
    { name: 'Overtime Management', to: '/attendance/overtime', icon: Timer, section: 'Attendance' },
    { name: 'Holiday Calendar', to: '/attendance/holidays', icon: CalendarRange, section: 'Attendance' },
    { name: 'Attendance Approvers', to: '/attendance/approvers', icon: ShieldCheck, section: 'Attendance' },

    { name: 'Leave Dashboard', to: '/leave', icon: CalendarOff, section: 'Leave' },
    { name: 'Leave Requests', to: '/leave/requests', icon: CalendarOff, section: 'Leave' },
    { name: 'Leave Calendar', to: '/leave/calendar', icon: CalendarRange, section: 'Leave' },
    { name: 'Leave Balances', to: '/leave/balances', icon: CalendarOff, section: 'Leave' },
    { name: 'Leave Types', to: '/leave/types', icon: CalendarOff, section: 'Leave' },

    { name: 'Payroll Dashboard', to: '/payroll', icon: DollarSign, section: 'Payroll' },
    { name: 'Payroll History', to: '/payroll/history', icon: DollarSign, section: 'Payroll' },
    { name: 'Employee Salaries', to: '/payroll/salaries', icon: Wallet, section: 'Payroll' },
    { name: 'EA Forms', to: '/payroll/ea-forms', icon: FileText, section: 'Payroll' },
    { name: 'Tax Profiles', to: '/payroll/tax-profiles', icon: FileText, section: 'Payroll' },

    { name: 'Claims Dashboard', to: '/claims', icon: Receipt, section: 'Claims' },
    { name: 'Claim Requests', to: '/claims/requests', icon: Receipt, section: 'Claims' },
    { name: 'Claim Types', to: '/claims/types', icon: Receipt, section: 'Claims' },

    { name: 'Exit Permissions', to: '/exit-permissions', icon: DoorOpen, section: 'Exit Permissions' },

    { name: 'Assets', to: '/assets', icon: Package, section: 'Assets' },
    { name: 'Asset Inventory', to: '/assets/inventory', icon: Package, section: 'Assets' },

    { name: 'Recruitment Dashboard', to: '/recruitment', icon: UserPlus, section: 'Recruitment' },
    { name: 'Job Postings', to: '/recruitment/postings', icon: FileText, section: 'Recruitment' },
    { name: 'Applicants', to: '/recruitment/applicants', icon: Users, section: 'Recruitment' },
    { name: 'Onboarding', to: '/onboarding', icon: ListTodo, section: 'Recruitment' },

    { name: 'Performance', to: '/performance', icon: Star, section: 'Performance' },
    { name: 'Review Cycles', to: '/performance/cycles', icon: Star, section: 'Performance' },
    { name: 'KPI Templates', to: '/performance/kpis', icon: Star, section: 'Performance' },
    { name: 'PIPs', to: '/performance/pips', icon: Star, section: 'Performance' },

    { name: 'Meetings', to: '/meetings', icon: CalendarRange, section: 'Meetings' },
    { name: 'Meeting Tasks', to: '/meetings/tasks', icon: ListTodo, section: 'Meetings' },

    { name: 'Disciplinary', to: '/disciplinary', icon: Gavel, section: 'Disciplinary' },
    { name: 'Disciplinary Records', to: '/disciplinary/records', icon: Gavel, section: 'Disciplinary' },

    { name: 'Resignations', to: '/offboarding/resignations', icon: UserMinus, section: 'Offboarding' },
    { name: 'Exit Checklists', to: '/offboarding/checklists', icon: UserMinus, section: 'Offboarding' },

    { name: 'Training Dashboard', to: '/training', icon: GraduationCap, section: 'Training' },
    { name: 'Training Programs', to: '/training/programs', icon: GraduationCap, section: 'Training' },
    { name: 'Certifications', to: '/training/certifications', icon: GraduationCap, section: 'Training' },

    { name: 'PWA Settings', to: '/settings/pwa', icon: Settings2, section: 'Settings' },

    { name: 'Notifications', to: '/notifications', icon: Bell, section: 'Account' },
];

const EMPLOYEE_NAV = [
    { name: 'My Profile', to: '/', icon: User, section: 'Home' },
    { name: 'Clock In / Out', to: '/clock', icon: Clock, section: 'Daily', keywords: ['start', 'work'] },
    { name: 'My Attendance', to: '/my/attendance', icon: Clock, section: 'Daily' },
    { name: 'My Leave', to: '/my/leave', icon: CalendarOff, section: 'Time off' },
    { name: 'Apply for Leave', to: '/my/leave/apply', icon: CalendarOff, section: 'Time off · Quick action' },
    { name: 'My Overtime', to: '/my/overtime', icon: Timer, section: 'Time off' },
    { name: 'My Payslips', to: '/my/payslips', icon: Wallet, section: 'Pay' },
    { name: 'My Claims', to: '/my/claims', icon: Receipt, section: 'Pay' },
    { name: 'My Assets', to: '/my/assets', icon: Package, section: 'Assets' },
    { name: 'My Meetings', to: '/my/meetings', icon: CalendarRange, section: 'Meetings' },
    { name: 'My Tasks', to: '/my/tasks', icon: ListTodo, section: 'Meetings' },
    { name: 'My Reviews', to: '/my/reviews', icon: Star, section: 'Performance' },
    { name: 'My Improvement Plan', to: '/my/pip', icon: Star, section: 'Performance' },
    { name: 'My Onboarding', to: '/my/onboarding', icon: ListTodo, section: 'Onboarding' },
    { name: 'My Training', to: '/my/training', icon: GraduationCap, section: 'Training' },
    { name: 'My Exit Permissions', to: '/my/exit-permissions', icon: DoorOpen, section: 'Other' },
    { name: 'My Disciplinary', to: '/my/disciplinary', icon: Gavel, section: 'Other' },
    { name: 'My Resignation', to: '/my/resignation', icon: UserMinus, section: 'Other' },
    { name: 'My Approvals', to: '/my/approvals', icon: ShieldCheck, section: 'Approvals' },
    { name: 'Notifications', to: '/notifications', icon: Bell, section: 'Account' },
];

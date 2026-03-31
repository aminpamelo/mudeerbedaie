import { useState, useRef, useCallback, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ZoomIn,
    ZoomOut,
    Maximize2,
    Users,
    Building2,
    ChevronDown,
    ChevronRight,
    Loader2,
    Search,
    X,
    Download,
} from 'lucide-react';
import { fetchOrgChart } from '../lib/api';
import PageHeader from '../components/PageHeader';
import { Avatar, AvatarImage, AvatarFallback } from '../components/ui/avatar';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { cn } from '../lib/utils';

// Department color palette
const DEPT_COLORS = [
    { bg: 'bg-blue-50', border: 'border-blue-200', accent: 'bg-blue-600', text: 'text-blue-700', light: 'bg-blue-100' },
    { bg: 'bg-violet-50', border: 'border-violet-200', accent: 'bg-violet-600', text: 'text-violet-700', light: 'bg-violet-100' },
    { bg: 'bg-emerald-50', border: 'border-emerald-200', accent: 'bg-emerald-600', text: 'text-emerald-700', light: 'bg-emerald-100' },
    { bg: 'bg-amber-50', border: 'border-amber-200', accent: 'bg-amber-600', text: 'text-amber-700', light: 'bg-amber-100' },
    { bg: 'bg-rose-50', border: 'border-rose-200', accent: 'bg-rose-600', text: 'text-rose-700', light: 'bg-rose-100' },
    { bg: 'bg-cyan-50', border: 'border-cyan-200', accent: 'bg-cyan-600', text: 'text-cyan-700', light: 'bg-cyan-100' },
    { bg: 'bg-orange-50', border: 'border-orange-200', accent: 'bg-orange-600', text: 'text-orange-700', light: 'bg-orange-100' },
    { bg: 'bg-indigo-50', border: 'border-indigo-200', accent: 'bg-indigo-600', text: 'text-indigo-700', light: 'bg-indigo-100' },
];

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function EmployeeCard({ employee, isHead, colorScheme, onClick }) {
    return (
        <div
            onClick={() => onClick?.(employee.id)}
            className={cn(
                'group flex items-center gap-3 rounded-lg border p-3 transition-all cursor-pointer',
                isHead
                    ? `${colorScheme.bg} ${colorScheme.border} shadow-sm hover:shadow-md`
                    : 'bg-white border-zinc-200 hover:border-zinc-300 hover:shadow-sm'
            )}
        >
            <Avatar className={cn(isHead ? 'h-12 w-12' : 'h-10 w-10', 'ring-2 ring-white shadow-sm')}>
                {employee.profile_photo_url ? (
                    <AvatarImage src={employee.profile_photo_url} alt={employee.full_name} />
                ) : null}
                <AvatarFallback className={cn(isHead ? `${colorScheme.accent} text-white font-bold` : 'bg-zinc-200 text-zinc-600')}>
                    {getInitials(employee.full_name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className={cn(
                    'truncate font-semibold leading-tight',
                    isHead ? 'text-sm text-zinc-900' : 'text-sm text-zinc-800'
                )}>
                    {employee.full_name}
                </p>
                <p className={cn(
                    'truncate text-xs leading-tight mt-0.5',
                    isHead ? colorScheme.text : 'text-zinc-500'
                )}>
                    {employee.position?.title || 'No Position'}
                    {employee.position?.level && (
                        <span className="ml-1 text-[10px] opacity-60">L{employee.position.level}</span>
                    )}
                </p>
                {isHead && (
                    <Badge variant="secondary" className={cn('mt-1 text-[10px] px-1.5 py-0', colorScheme.light, colorScheme.text)}>
                        Department Head
                    </Badge>
                )}
            </div>
        </div>
    );
}

function DepartmentNode({ department, colorScheme, level = 0, searchTerm, onEmployeeClick }) {
    const [isExpanded, setIsExpanded] = useState(level < 2);
    const [showAllEmployees, setShowAllEmployees] = useState(false);

    const headEmployee = department.head_employee;
    const otherEmployees = (department.employees || []).filter(
        (e) => e.id !== department.head_employee_id
    );
    const children = department.children || [];
    const hasContent = otherEmployees.length > 0 || children.length > 0;
    const employeeCount = department.employees_count ?? department.employees?.length ?? 0;

    // Filter employees based on search
    const filteredOther = searchTerm
        ? otherEmployees.filter((e) =>
            e.full_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            e.position?.title?.toLowerCase().includes(searchTerm.toLowerCase())
        )
        : otherEmployees;

    const headMatches = searchTerm && headEmployee
        ? headEmployee.full_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
          headEmployee.position?.title?.toLowerCase().includes(searchTerm.toLowerCase())
        : true;

    const deptMatches = searchTerm
        ? department.name.toLowerCase().includes(searchTerm.toLowerCase())
        : true;

    const hasMatchingChildren = searchTerm
        ? children.some((c) => departmentHasMatch(c, searchTerm))
        : true;

    // Auto-expand if search matches
    useEffect(() => {
        if (searchTerm && (deptMatches || headMatches || filteredOther.length > 0 || hasMatchingChildren)) {
            setIsExpanded(true);
        }
    }, [searchTerm, deptMatches, headMatches, filteredOther.length, hasMatchingChildren]);

    // Hide departments that don't match search
    if (searchTerm && !deptMatches && !headMatches && filteredOther.length === 0 && !hasMatchingChildren) {
        return null;
    }

    const visibleEmployees = showAllEmployees ? filteredOther : filteredOther.slice(0, 6);

    return (
        <div className={cn('relative', level > 0 && 'ml-6 sm:ml-10')}>
            {/* Connector line */}
            {level > 0 && (
                <div className="absolute -left-5 sm:-left-6 top-0 bottom-0 w-px bg-zinc-200" />
            )}
            {level > 0 && (
                <div className="absolute -left-5 sm:-left-6 top-6 w-4 sm:w-5 h-px bg-zinc-200" />
            )}

            {/* Department header */}
            <div className={cn(
                'rounded-xl border-2 overflow-hidden transition-all',
                colorScheme.border,
                'shadow-sm hover:shadow-md'
            )}>
                {/* Department title bar */}
                <div
                    className={cn(
                        'flex items-center gap-3 px-4 py-3 cursor-pointer select-none',
                        colorScheme.accent,
                        'text-white'
                    )}
                    onClick={() => setIsExpanded(!isExpanded)}
                >
                    <Building2 className="h-5 w-5 shrink-0 opacity-80" />
                    <div className="flex-1 min-w-0">
                        <h3 className="font-bold text-sm truncate">{department.name}</h3>
                        {department.code && (
                            <p className="text-xs opacity-75">{department.code}</p>
                        )}
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <span className="flex items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium">
                            <Users className="h-3 w-3" />
                            {employeeCount}
                        </span>
                        {hasContent ? (
                            isExpanded ? (
                                <ChevronDown className="h-4 w-4" />
                            ) : (
                                <ChevronRight className="h-4 w-4" />
                            )
                        ) : null}
                    </div>
                </div>

                {/* Expanded content */}
                {isExpanded && (
                    <div className={cn('p-4', colorScheme.bg)}>
                        {/* Department Head */}
                        {headEmployee && (
                            <div className="mb-3">
                                <EmployeeCard
                                    employee={headEmployee}
                                    isHead
                                    colorScheme={colorScheme}
                                    onClick={onEmployeeClick}
                                />
                            </div>
                        )}

                        {/* Team Members */}
                        {filteredOther.length > 0 && (
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Team Members ({filteredOther.length})
                                </p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {visibleEmployees.map((emp) => (
                                        <EmployeeCard
                                            key={emp.id}
                                            employee={emp}
                                            isHead={false}
                                            colorScheme={colorScheme}
                                            onClick={onEmployeeClick}
                                        />
                                    ))}
                                </div>
                                {filteredOther.length > 6 && !showAllEmployees && (
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setShowAllEmployees(true);
                                        }}
                                        className={cn(
                                            'mt-2 text-xs font-medium transition-colors',
                                            colorScheme.text,
                                            'hover:underline'
                                        )}
                                    >
                                        Show all {filteredOther.length} members
                                    </button>
                                )}
                                {showAllEmployees && filteredOther.length > 6 && (
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setShowAllEmployees(false);
                                        }}
                                        className={cn(
                                            'mt-2 text-xs font-medium transition-colors',
                                            colorScheme.text,
                                            'hover:underline'
                                        )}
                                    >
                                        Show less
                                    </button>
                                )}
                            </div>
                        )}

                        {!headEmployee && filteredOther.length === 0 && (
                            <p className="text-xs text-zinc-400 italic">No employees assigned</p>
                        )}
                    </div>
                )}
            </div>

            {/* Child departments */}
            {isExpanded && children.length > 0 && (
                <div className="mt-3 space-y-3">
                    {children.map((child, idx) => (
                        <DepartmentNode
                            key={child.id}
                            department={child}
                            colorScheme={DEPT_COLORS[(level + idx + 1) % DEPT_COLORS.length]}
                            level={level + 1}
                            searchTerm={searchTerm}
                            onEmployeeClick={onEmployeeClick}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function departmentHasMatch(dept, searchTerm) {
    const term = searchTerm.toLowerCase();
    if (dept.name.toLowerCase().includes(term)) return true;
    if (dept.head_employee?.full_name?.toLowerCase().includes(term)) return true;
    if (dept.employees?.some((e) =>
        e.full_name.toLowerCase().includes(term) ||
        e.position?.title?.toLowerCase().includes(term)
    )) return true;
    if (dept.children?.some((c) => departmentHasMatch(c, term))) return true;
    return false;
}

export default function OrgChart() {
    const navigate = useNavigate();
    const containerRef = useRef(null);
    const [search, setSearch] = useState('');
    const [scale, setScale] = useState(1);

    const { data, isLoading, error } = useQuery({
        queryKey: ['hr', 'org-chart'],
        queryFn: fetchOrgChart,
    });

    const departments = data?.data || [];
    const meta = data?.meta || {};

    const handleZoomIn = useCallback(() => {
        setScale((s) => Math.min(s + 0.1, 1.5));
    }, []);

    const handleZoomOut = useCallback(() => {
        setScale((s) => Math.max(s - 0.1, 0.5));
    }, []);

    const handleReset = useCallback(() => {
        setScale(1);
    }, []);

    const handleEmployeeClick = useCallback((id) => {
        navigate(`/employees/${id}`);
    }, [navigate]);

    if (isLoading) {
        return (
            <div className="flex h-96 items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex h-96 flex-col items-center justify-center gap-2">
                <p className="text-sm text-zinc-500">Failed to load organization chart</p>
                <Button variant="outline" size="sm" onClick={() => window.location.reload()}>
                    Try Again
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Organization Chart"
                description="Visual overview of your company structure, departments, and team members"
            />

            {/* Stats bar */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                            <Users className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">{meta.total_employees ?? 0}</p>
                            <p className="text-xs text-zinc-500">Total Employees</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100">
                            <Building2 className="h-5 w-5 text-violet-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">{meta.total_departments ?? 0}</p>
                            <p className="text-xs text-zinc-500">Departments</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100">
                            <Users className="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">
                                {departments.reduce((sum, d) => {
                                    const headCount = d.head_employee ? 1 : 0;
                                    return sum + headCount;
                                }, 0)}
                            </p>
                            <p className="text-xs text-zinc-500">Dept. Heads</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-lg border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100">
                            <Building2 className="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-zinc-900">
                                {departments.reduce((sum, d) => sum + (d.children?.length || 0), 0)}
                            </p>
                            <p className="text-xs text-zinc-500">Sub-departments</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Toolbar */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <Input
                        placeholder="Search employees, positions, departments..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-9 pr-8"
                    />
                    {search && (
                        <button
                            onClick={() => setSearch('')}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>
                <div className="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1">
                    <Button variant="ghost" size="sm" onClick={handleZoomOut} title="Zoom Out">
                        <ZoomOut className="h-4 w-4" />
                    </Button>
                    <span className="px-2 text-xs font-medium text-zinc-500 tabular-nums">
                        {Math.round(scale * 100)}%
                    </span>
                    <Button variant="ghost" size="sm" onClick={handleZoomIn} title="Zoom In">
                        <ZoomIn className="h-4 w-4" />
                    </Button>
                    <div className="mx-1 h-4 w-px bg-zinc-200" />
                    <Button variant="ghost" size="sm" onClick={handleReset} title="Reset Zoom">
                        <Maximize2 className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Org Chart */}
            <div
                ref={containerRef}
                className="overflow-auto rounded-xl border border-zinc-200 bg-zinc-50/50 p-6"
                style={{ minHeight: '400px' }}
            >
                <div
                    style={{
                        transform: `scale(${scale})`,
                        transformOrigin: 'top left',
                        transition: 'transform 0.2s ease',
                    }}
                >
                    {departments.length === 0 ? (
                        <div className="flex h-64 flex-col items-center justify-center gap-2 text-zinc-400">
                            <Building2 className="h-12 w-12" />
                            <p className="text-sm">No departments found</p>
                            <p className="text-xs">Create departments and assign employees to see the organization chart</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {departments.map((dept, idx) => (
                                <DepartmentNode
                                    key={dept.id}
                                    department={dept}
                                    colorScheme={DEPT_COLORS[idx % DEPT_COLORS.length]}
                                    level={0}
                                    searchTerm={search}
                                    onEmployeeClick={handleEmployeeClick}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

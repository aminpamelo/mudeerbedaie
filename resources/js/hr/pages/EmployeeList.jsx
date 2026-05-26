import { useState, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    Plus,
    Download,
    LayoutList,
    Grid3X3,
    Users,
    UserCheck,
    UserMinus,
    Clock,
    Eye,
    Pencil,
    X,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { fetchEmployees, fetchDepartments, exportEmployees } from '../lib/api';
import { cn } from '../lib/utils';
import { PageHeader } from '../components/ui/page-header';
import { StatCard } from '../components/ui/stat-card';
import { StatusBadge } from '../components/ui/status-badge';
import { EmptyState } from '../components/ui/empty-state';
import SearchInput from '../components/SearchInput';
import { Button } from '../components/ui/button';
import { Card } from '../components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../components/ui/table';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'probation', label: 'Probation' },
    { value: 'resigned', label: 'Resigned' },
    { value: 'terminated', label: 'Terminated' },
];

const TYPE_OPTIONS = [
    { value: 'all', label: 'All Types' },
    { value: 'full-time', label: 'Full-time' },
    { value: 'part-time', label: 'Part-time' },
    { value: 'contract', label: 'Contract' },
    { value: 'intern', label: 'Intern' },
];

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return '–';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function EmployeeAvatar({ employee, size = 'sm' }) {
    const sizeClasses = size === 'lg' ? 'h-14 w-14 text-base' : 'h-9 w-9 text-xs';

    if (employee.profile_photo_url) {
        return (
            <img
                src={employee.profile_photo_url}
                alt={employee.full_name}
                className={cn('rounded-full object-cover ring-2 ring-white', sizeClasses)}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex items-center justify-center rounded-full bg-gradient-to-br from-indigo-100 to-indigo-50 font-semibold text-indigo-700 ring-2 ring-white',
                sizeClasses
            )}
        >
            {getInitials(employee.full_name)}
        </div>
    );
}

function SkeletonRow() {
    return (
        <div className="flex items-center gap-4 px-4 py-3.5">
            <div className="h-9 w-9 animate-pulse rounded-full bg-slate-200" />
            <div className="flex-1 space-y-2">
                <div className="h-3.5 w-48 animate-pulse rounded bg-slate-200" />
                <div className="h-2.5 w-32 animate-pulse rounded bg-slate-100" />
            </div>
            <div className="h-3.5 w-20 animate-pulse rounded bg-slate-200" />
            <div className="h-3.5 w-24 animate-pulse rounded bg-slate-200" />
            <div className="h-5 w-16 animate-pulse rounded-full bg-slate-200" />
        </div>
    );
}

function SkeletonStats() {
    return (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {[1, 2, 3, 4].map((i) => (
                <div key={i} className="rounded-2xl border border-slate-200/70 bg-white p-5">
                    <div className="mb-3 h-3 w-24 animate-pulse rounded bg-slate-200" />
                    <div className="mb-2 h-9 w-16 animate-pulse rounded bg-slate-200" />
                    <div className="h-3 w-20 animate-pulse rounded bg-slate-100" />
                </div>
            ))}
        </div>
    );
}

export default function EmployeeList() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [typeFilter, setTypeFilter] = useState('all');
    const [viewMode, setViewMode] = useState('table');
    const [page, setPage] = useState(1);

    const hasFilters =
        search !== '' ||
        departmentFilter !== 'all' ||
        statusFilter !== 'all' ||
        typeFilter !== 'all';

    const { data, isLoading, isError, error } = useQuery({
        queryKey: [
            'hr',
            'employees',
            { search, department: departmentFilter, status: statusFilter, type: typeFilter, page },
        ],
        queryFn: () =>
            fetchEmployees({
                search,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                employment_type: typeFilter !== 'all' ? typeFilter : undefined,
                page,
            }),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const employees = data?.data || [];
    const pagination = data?.meta || data || {};
    const stats = data?.stats || {};
    const totalEmployees = stats.total || pagination.total || 0;
    const activeCount = stats.active || 0;
    const probationCount = stats.probation || 0;
    const inactiveCount = (totalEmployees || 0) - activeCount - probationCount;
    const lastPage = pagination.last_page || 1;
    const departments = departmentsData?.data || [];

    const resetPage = useCallback(() => setPage(1), []);

    function handleSearchChange(value) {
        setSearch(value);
        resetPage();
    }

    function handleDepartmentChange(value) {
        setDepartmentFilter(value);
        resetPage();
    }

    function handleStatusChange(value) {
        setStatusFilter(value);
        resetPage();
    }

    function handleTypeChange(value) {
        setTypeFilter(value);
        resetPage();
    }

    function clearFilters() {
        setSearch('');
        setDepartmentFilter('all');
        setStatusFilter('all');
        setTypeFilter('all');
        resetPage();
    }

    async function handleExport() {
        try {
            const blob = await exportEmployees({
                search,
                department_id: departmentFilter !== 'all' ? departmentFilter : undefined,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                employment_type: typeFilter !== 'all' ? typeFilter : undefined,
            });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `employees-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Export failed:', err);
        }
    }

    return (
        <div className="space-y-6 pb-10">
            <PageHeader
                title="Employees"
                description="Manage your workforce and view employee details"
                actions={
                    <>
                        <Button variant="outline" onClick={handleExport}>
                            <Download className="h-4 w-4" />
                            Export
                        </Button>
                        <Button asChild>
                            <Link to="/employees/create">
                                <Plus className="h-4 w-4" />
                                Add Employee
                            </Link>
                        </Button>
                    </>
                }
            />

            {/* Stats */}
            {isLoading ? (
                <SkeletonStats />
            ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Total" value={totalEmployees} sub="all employees" icon={Users} accent="indigo" />
                    <StatCard label="Active" value={activeCount} sub="currently working" icon={UserCheck} accent="emerald" />
                    <StatCard label="Probation" value={probationCount} sub="pending confirmation" icon={Clock} accent="amber" />
                    <StatCard label="Inactive" value={Math.max(0, inactiveCount)} sub="resigned or terminated" icon={UserMinus} accent="violet" />
                </div>
            )}

            {/* Filters */}
            <Card className="p-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <SearchInput
                        value={search}
                        onChange={handleSearchChange}
                        placeholder="Search name, employee ID, IC..."
                        className="w-full lg:w-64"
                    />

                    <Select value={departmentFilter} onValueChange={handleDepartmentChange}>
                        <SelectTrigger className="w-full lg:w-44">
                            <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Departments</SelectItem>
                            {departments.map((dept) => (
                                <SelectItem key={dept.id} value={String(dept.id)}>
                                    {dept.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={statusFilter} onValueChange={handleStatusChange}>
                        <SelectTrigger className="w-full lg:w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUS_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={typeFilter} onValueChange={handleTypeChange}>
                        <SelectTrigger className="w-full lg:w-36">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            {TYPE_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="flex items-center gap-2 lg:ml-auto">
                        <div className="flex rounded-lg border border-slate-300 bg-white p-0.5">
                            <button
                                type="button"
                                onClick={() => setViewMode('table')}
                                aria-label="Table view"
                                aria-pressed={viewMode === 'table'}
                                className={cn(
                                    'flex items-center justify-center rounded-md px-2.5 py-1.5 text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                    viewMode === 'table'
                                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20'
                                        : 'text-slate-500 hover:text-slate-700'
                                )}
                            >
                                <LayoutList className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setViewMode('grid')}
                                aria-label="Grid view"
                                aria-pressed={viewMode === 'grid'}
                                className={cn(
                                    'flex items-center justify-center rounded-md px-2.5 py-1.5 text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                    viewMode === 'grid'
                                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/20'
                                        : 'text-slate-500 hover:text-slate-700'
                                )}
                            >
                                <Grid3X3 className="h-4 w-4" />
                            </button>
                        </div>

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {hasFilters && (
                    <div className="mt-3 flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                        <span className="text-xs font-medium text-slate-500">Active filters:</span>
                        {search && (
                            <FilterChip onRemove={() => handleSearchChange('')}>
                                Search: "{search}"
                            </FilterChip>
                        )}
                        {departmentFilter !== 'all' && (
                            <FilterChip onRemove={() => handleDepartmentChange('all')}>
                                Dept: {departments.find((d) => String(d.id) === departmentFilter)?.name}
                            </FilterChip>
                        )}
                        {statusFilter !== 'all' && (
                            <FilterChip onRemove={() => handleStatusChange('all')}>
                                Status: {STATUS_OPTIONS.find((s) => s.value === statusFilter)?.label}
                            </FilterChip>
                        )}
                        {typeFilter !== 'all' && (
                            <FilterChip onRemove={() => handleTypeChange('all')}>
                                Type: {TYPE_OPTIONS.find((t) => t.value === typeFilter)?.label}
                            </FilterChip>
                        )}
                    </div>
                )}
            </Card>

            {/* Content */}
            {isError ? (
                <Card className="p-0">
                    <EmptyState
                        icon={X}
                        accent="rose"
                        title="Failed to load employees"
                        description={
                            error?.response?.status === 401
                                ? 'Your session has expired. Please refresh the page.'
                                : error?.response?.data?.error
                                ? error.response.data.error
                                : `Something went wrong. (${error?.response?.status || 'Network error'})`
                        }
                        action={
                            <Button variant="outline" onClick={() => window.location.reload()}>
                                Refresh Page
                            </Button>
                        }
                        className="py-12"
                    />
                </Card>
            ) : isLoading ? (
                <Card className="overflow-hidden p-0">
                    <div className="divide-y divide-slate-100">
                        {Array.from({ length: 8 }).map((_, i) => <SkeletonRow key={i} />)}
                    </div>
                </Card>
            ) : employees.length === 0 ? (
                <Card className="p-0">
                    <EmptyState
                        icon={Users}
                        accent="indigo"
                        title={hasFilters ? 'No employees found' : 'No employees yet'}
                        description={
                            hasFilters
                                ? 'Try adjusting your search or filters.'
                                : 'Get started by adding your first employee to the directory.'
                        }
                        action={
                            <div className="flex gap-2">
                                {hasFilters && (
                                    <Button variant="outline" onClick={clearFilters}>
                                        Clear Filters
                                    </Button>
                                )}
                                <Button asChild>
                                    <Link to="/employees/create">
                                        <Plus className="h-4 w-4" />
                                        Add Employee
                                    </Link>
                                </Button>
                            </div>
                        }
                        className="py-16"
                    />
                </Card>
            ) : viewMode === 'table' ? (
                <Card className="overflow-hidden p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-14"></TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Employee ID</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Join Date</TableHead>
                                <TableHead className="w-20"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.map((employee) => (
                                <TableRow key={employee.id}>
                                    <TableCell>
                                        <EmployeeAvatar employee={employee} />
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            to={`/employees/${employee.id}`}
                                            className="font-medium text-slate-900 hover:text-indigo-600 focus:outline-none focus-visible:underline"
                                        >
                                            {employee.full_name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="font-mono text-xs text-slate-500">
                                        {employee.employee_id}
                                    </TableCell>
                                    <TableCell className="text-slate-600">
                                        {employee.department?.name || '–'}
                                    </TableCell>
                                    <TableCell className="text-slate-600">
                                        {employee.position?.title || '–'}
                                    </TableCell>
                                    <TableCell className="capitalize text-slate-600">
                                        {employee.employment_type_label ||
                                            (Array.isArray(employee.employment_type)
                                                ? employee.employment_type.map((t) => t.replace(/[-_]/g, ' ')).join(', ')
                                                : '–')}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={employee.status} />
                                    </TableCell>
                                    <TableCell className="tabular-nums text-slate-600">
                                        {formatDate(employee.join_date)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() => navigate(`/employees/${employee.id}`)}
                                                aria-label="View employee"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() => navigate(`/employees/${employee.id}/edit`)}
                                                aria-label="Edit employee"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {employees.map((employee) => (
                        <Link
                            key={employee.id}
                            to={`/employees/${employee.id}`}
                            className="group rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                        >
                            <Card className="h-full p-5 transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md hover:shadow-slate-200/60">
                                <div className="flex items-start gap-4">
                                    <EmployeeAvatar employee={employee} size="lg" />
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-sm font-semibold text-slate-900 group-hover:text-indigo-600">
                                            {employee.full_name}
                                        </h3>
                                        <p className="mt-0.5 font-mono text-[11px] text-slate-500 tabular-nums">
                                            {employee.employee_id}
                                        </p>
                                        <p className="mt-2 truncate text-xs text-slate-700">
                                            {employee.department?.name || '–'}
                                        </p>
                                        <p className="truncate text-[11px] text-slate-400">
                                            {employee.position?.title || '–'}
                                        </p>
                                        <div className="mt-3">
                                            <StatusBadge status={employee.status} />
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </Link>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {!isLoading && employees.length > 0 && lastPage > 1 && (
                <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <p className="text-sm text-slate-500">
                        Page <span className="font-semibold tabular-nums text-slate-700">{page}</span> of{' '}
                        <span className="font-semibold tabular-nums text-slate-700">{lastPage}</span>
                        {' '}<span className="text-slate-400">·</span>{' '}
                        <span className="tabular-nums">{totalEmployees}</span> employees
                    </p>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Previous
                        </Button>
                        {Array.from({ length: Math.min(lastPage, 5) }, (_, i) => {
                            let pageNum;
                            if (lastPage <= 5) pageNum = i + 1;
                            else if (page <= 3) pageNum = i + 1;
                            else if (page >= lastPage - 2) pageNum = lastPage - 4 + i;
                            else pageNum = page - 2 + i;

                            return (
                                <Button
                                    key={pageNum}
                                    variant={page === pageNum ? 'default' : 'outline'}
                                    size="sm"
                                    className="h-9 w-9 p-0 tabular-nums"
                                    onClick={() => setPage(pageNum)}
                                >
                                    {pageNum}
                                </Button>
                            );
                        })}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page >= lastPage}
                            onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                        >
                            Next
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function FilterChip({ children, onRemove }) {
    return (
        <button
            type="button"
            onClick={onRemove}
            className="group inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-medium text-indigo-700 transition-colors hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            {children}
            <X className="h-3 w-3 text-indigo-400 group-hover:text-indigo-700" />
        </button>
    );
}

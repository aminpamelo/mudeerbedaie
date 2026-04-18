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
    MoreHorizontal,
    Eye,
    Pencil,
    X,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { fetchEmployees, fetchDepartments, exportEmployees } from '../lib/api';
import { cn } from '../lib/utils';
import PageHeader from '../components/PageHeader';
import SearchInput from '../components/SearchInput';
import StatusBadge from '../components/StatusBadge';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
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
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function EmployeeAvatar({ employee, size = 'sm' }) {
    const sizeClasses = size === 'lg' ? 'h-14 w-14 text-lg' : 'h-9 w-9 text-xs';

    if (employee.profile_photo_url) {
        return (
            <img
                src={employee.profile_photo_url}
                alt={employee.full_name}
                className={cn('rounded-full object-cover', sizeClasses)}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex items-center justify-center rounded-full bg-zinc-200 font-semibold text-zinc-600',
                sizeClasses
            )}
        >
            {getInitials(employee.full_name)}
        </div>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-9 w-9 animate-pulse rounded-full bg-zinc-200" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState({ hasFilters, onClearFilters }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <Users className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                {hasFilters ? 'No employees found' : 'No employees yet'}
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                {hasFilters
                    ? 'Try adjusting your search or filters to find what you are looking for.'
                    : 'Get started by adding your first employee to the directory.'}
            </p>
            <div className="mt-4 flex gap-2">
                {hasFilters && (
                    <Button variant="outline" onClick={onClearFilters}>
                        Clear Filters
                    </Button>
                )}
                <Button asChild>
                    <Link to="/employees/create">
                        <Plus className="mr-1.5 h-4 w-4" />
                        Add Employee
                    </Link>
                </Button>
            </div>
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

    const { data, isLoading } = useQuery({
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
        } catch (error) {
            console.error('Export failed:', error);
        }
    }

    return (
        <div>
            <PageHeader
                title="Employee Directory"
                description="Manage and view all employees in the organization."
                action={
                    <Button asChild>
                        <Link to="/employees/create">
                            <Plus className="mr-1.5 h-4 w-4" />
                            Add Employee
                        </Link>
                    </Button>
                }
            />

            {/* Stats Bar */}
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100">
                                <Users className="h-5 w-5 text-zinc-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">
                                    {totalEmployees}
                                </p>
                                <p className="text-xs text-zinc-500">Total Employees</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50">
                                <UserCheck className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">
                                    {activeCount}
                                </p>
                                <p className="text-xs text-zinc-500">Active</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filters Toolbar */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={handleSearchChange}
                            placeholder="Search name, employee ID, IC..."
                            className="w-full lg:w-64"
                        />

                        <Select
                            value={departmentFilter}
                            onValueChange={handleDepartmentChange}
                        >
                            <SelectTrigger className="w-full lg:w-44">
                                <SelectValue placeholder="Department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem
                                        key={dept.id}
                                        value={String(dept.id)}
                                    >
                                        {dept.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={statusFilter}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger className="w-full lg:w-36">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={typeFilter} onValueChange={handleTypeChange}>
                            <SelectTrigger className="w-full lg:w-36">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                {TYPE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2 lg:ml-auto">
                            <div className="flex rounded-lg border border-zinc-300">
                                <button
                                    type="button"
                                    onClick={() => setViewMode('table')}
                                    className={cn(
                                        'flex items-center justify-center rounded-l-lg px-3 py-2 text-sm transition-colors',
                                        viewMode === 'table'
                                            ? 'bg-zinc-900 text-white'
                                            : 'text-zinc-600 hover:bg-zinc-100'
                                    )}
                                >
                                    <LayoutList className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setViewMode('grid')}
                                    className={cn(
                                        'flex items-center justify-center rounded-r-lg px-3 py-2 text-sm transition-colors',
                                        viewMode === 'grid'
                                            ? 'bg-zinc-900 text-white'
                                            : 'text-zinc-600 hover:bg-zinc-100'
                                    )}
                                >
                                    <Grid3X3 className="h-4 w-4" />
                                </button>
                            </div>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleExport}
                            >
                                <Download className="mr-1.5 h-4 w-4" />
                                Export
                            </Button>

                            {hasFilters && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearFilters}
                                >
                                    <X className="mr-1 h-4 w-4" />
                                    Clear
                                </Button>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Content */}
            {isLoading ? (
                <Card>
                    <SkeletonTable />
                </Card>
            ) : employees.length === 0 ? (
                <Card>
                    <EmptyState
                        hasFilters={hasFilters}
                        onClearFilters={clearFilters}
                    />
                </Card>
            ) : viewMode === 'table' ? (
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12"></TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Employee ID</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Join Date</TableHead>
                                <TableHead className="w-12"></TableHead>
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
                                            className="font-medium text-zinc-900 hover:text-zinc-600 hover:underline"
                                        >
                                            {employee.full_name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {employee.employee_id}
                                    </TableCell>
                                    <TableCell>
                                        {employee.department?.name || '-'}
                                    </TableCell>
                                    <TableCell>
                                        {employee.position?.title || '-'}
                                    </TableCell>
                                    <TableCell className="capitalize">
                                        {employee.employment_type_label || (Array.isArray(employee.employment_type) ? employee.employment_type.map(t => t.replace(/[-_]/g, ' ')).join(', ') : '-')}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={employee.status} />
                                    </TableCell>
                                    <TableCell>
                                        {formatDate(employee.join_date)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() =>
                                                    navigate(
                                                        `/employees/${employee.id}`
                                                    )
                                                }
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8"
                                                onClick={() =>
                                                    navigate(
                                                        `/employees/${employee.id}/edit`
                                                    )
                                                }
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
                            className="group"
                        >
                            <Card className="transition-shadow hover:shadow-md">
                                <CardContent className="p-5">
                                    <div className="flex items-start gap-4">
                                        <EmployeeAvatar
                                            employee={employee}
                                            size="lg"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <h3 className="truncate text-sm font-semibold text-zinc-900 group-hover:text-zinc-600">
                                                {employee.full_name}
                                            </h3>
                                            <p className="mt-0.5 font-mono text-xs text-zinc-500">
                                                {employee.employee_id}
                                            </p>
                                            <p className="mt-1 truncate text-sm text-zinc-600">
                                                {employee.department?.name || '-'}
                                            </p>
                                            <p className="truncate text-xs text-zinc-500">
                                                {employee.position?.title || '-'}
                                            </p>
                                            <div className="mt-2">
                                                <StatusBadge
                                                    status={employee.status}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {!isLoading && employees.length > 0 && lastPage > 1 && (
                <div className="mt-6 flex items-center justify-between">
                    <p className="text-sm text-zinc-500">
                        Page {page} of {lastPage} ({totalEmployees} employees)
                    </p>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                            <ChevronLeft className="mr-1 h-4 w-4" />
                            Previous
                        </Button>
                        {Array.from({ length: Math.min(lastPage, 5) }, (_, i) => {
                            let pageNum;
                            if (lastPage <= 5) {
                                pageNum = i + 1;
                            } else if (page <= 3) {
                                pageNum = i + 1;
                            } else if (page >= lastPage - 2) {
                                pageNum = lastPage - 4 + i;
                            } else {
                                pageNum = page - 2 + i;
                            }
                            return (
                                <Button
                                    key={pageNum}
                                    variant={
                                        page === pageNum ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    className="h-9 w-9 p-0"
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
                            onClick={() =>
                                setPage((p) => Math.min(lastPage, p + 1))
                            }
                        >
                            Next
                            <ChevronRight className="ml-1 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

import { useState, useCallback, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Download,
    Search,
    Filter,
    Eye,
    Pencil,
    Clock,
    MapPin,
    Camera,
    MessageSquare,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import {
    Card,
    CardHeader,
    CardContent,
    CardTitle,
    CardDescription,
} from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import { Textarea } from '../../components/ui/textarea';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { cn } from '../../lib/utils';
import {
    fetchAttendance,
    updateAttendanceLog,
    exportAttendance,
    fetchDepartments,
} from '../../lib/api';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'present', label: 'Present' },
    { value: 'late', label: 'Late' },
    { value: 'absent', label: 'Absent' },
    { value: 'wfh', label: 'WFH' },
    { value: 'on_leave', label: 'On Leave' },
    { value: 'half_day', label: 'Half Day' },
];

const STATUS_COLORS = {
    present: { label: 'Present', bg: 'bg-emerald-100', text: 'text-emerald-800' },
    late: { label: 'Late', bg: 'bg-amber-100', text: 'text-amber-800' },
    absent: { label: 'Absent', bg: 'bg-red-100', text: 'text-red-800' },
    wfh: { label: 'WFH', bg: 'bg-blue-100', text: 'text-blue-800' },
    on_leave: { label: 'On Leave', bg: 'bg-purple-100', text: 'text-purple-800' },
    half_day: { label: 'Half Day', bg: 'bg-orange-100', text: 'text-orange-800' },
};

function AttendanceStatusBadge({ status }) {
    const config = STATUS_COLORS[status] || { label: status, bg: 'bg-zinc-100', text: 'text-zinc-800' };
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', config.bg, config.text)}>
            {config.label}
        </span>
    );
}

function formatDate(dateString) {
    if (!dateString) {
        return '-';
    }
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(timeString) {
    if (!timeString) {
        return '-';
    }
    const date = new Date(timeString);
    if (isNaN(date.getTime())) {
        return timeString.slice(0, 5);
    }
    return date.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatHours(totalMinutes) {
    if (!totalMinutes && totalMinutes !== 0) {
        return '-';
    }
    const hours = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;
    return `${hours}h ${mins}m`;
}

function LiveTimer({ clockIn }) {
    const [elapsed, setElapsed] = useState(() => {
        const diff = Math.floor((Date.now() - new Date(clockIn).getTime()) / 1000);
        return diff > 0 ? diff : 0;
    });

    useEffect(() => {
        const interval = setInterval(() => {
            const diff = Math.floor((Date.now() - new Date(clockIn).getTime()) / 1000);
            setElapsed(diff > 0 ? diff : 0);
        }, 1000);
        return () => clearInterval(interval);
    }, [clockIn]);

    const hours = Math.floor(elapsed / 3600);
    const mins = Math.floor((elapsed % 3600) / 60);
    const secs = elapsed % 60;
    const display = `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-mono font-semibold text-blue-700 ring-1 ring-blue-200">
            <span className="relative flex h-1.5 w-1.5">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75" />
                <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-blue-500" />
            </span>
            {display}
        </span>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function AttendanceRecords() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [department, setDepartment] = useState('all');
    const [status, setStatus] = useState('all');
    const [detailRecord, setDetailRecord] = useState(null);
    const [editRecord, setEditRecord] = useState(null);
    const [editForm, setEditForm] = useState({
        clock_in: '',
        clock_out: '',
        status: '',
        remarks: '',
    });

    const filters = {
        page,
        per_page: 20,
        search: search || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        department_id: department !== 'all' ? department : undefined,
        status: status !== 'all' ? status : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'records', filters],
        queryFn: () => fetchAttendance(filters),
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['hr', 'departments'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateAttendanceLog(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance'] });
            setEditRecord(null);
        },
    });

    const departments = departmentsData?.data || [];
    const records = data?.data || [];
    const pagination = data?.meta || {};

    function handleExport() {
        exportAttendance({
            search: search || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            department_id: department !== 'all' ? department : undefined,
            status: status !== 'all' ? status : undefined,
        }).then((blob) => {
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `attendance-records-${new Date().toISOString().slice(0, 10)}.csv`;
            link.click();
            window.URL.revokeObjectURL(url);
        });
    }

    function openEdit(record) {
        setEditForm({
            clock_in: record.clock_in || '',
            clock_out: record.clock_out || '',
            status: record.status || '',
            remarks: record.admin_remarks || '',
        });
        setEditRecord(record);
    }

    function handleSaveEdit() {
        updateMutation.mutate({
            id: editRecord.id,
            data: editForm,
        });
    }

    function handleClearFilters() {
        setSearch('');
        setDateFrom('');
        setDateTo('');
        setDepartment('all');
        setStatus('all');
        setPage(1);
    }

    const hasFilters = search || dateFrom || dateTo || department !== 'all' || status !== 'all';

    return (
        <div className="space-y-6">
            <PageHeader
                title="Attendance Records"
                description="View and manage all attendance logs"
                action={
                    <Button variant="outline" size="sm" onClick={handleExport}>
                        <Download className="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                }
            />

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-[200px] flex-1">
                            <SearchInput
                                value={search}
                                onChange={(val) => { setSearch(val); setPage(1); }}
                                placeholder="Search employee..."
                            />
                        </div>
                        <div>
                            <Label className="mb-1 block text-xs text-zinc-500">From</Label>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => { setDateFrom(e.target.value); setPage(1); }}
                                className="w-40"
                            />
                        </div>
                        <div>
                            <Label className="mb-1 block text-xs text-zinc-500">To</Label>
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => { setDateTo(e.target.value); setPage(1); }}
                                className="w-40"
                            />
                        </div>
                        <div className="w-44">
                            <Select value={department} onValueChange={(val) => { setDepartment(val); setPage(1); }}>
                                <SelectTrigger>
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
                        </div>
                        <div className="w-36">
                            <Select value={status} onValueChange={(val) => { setStatus(val); setPage(1); }}>
                                <SelectTrigger>
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
                        </div>
                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={handleClearFilters}>
                                Clear
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : records.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Clock className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No attendance records found</p>
                            <p className="text-xs text-zinc-400">Try adjusting your filters</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Clock In</TableHead>
                                        <TableHead>Clock Out</TableHead>
                                        <TableHead>Total Hours</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Late</TableHead>
                                        <TableHead>Early Leave</TableHead>
                                        <TableHead className="w-20">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records.map((record) => (
                                        <TableRow key={record.id} className="cursor-pointer hover:bg-zinc-50" onClick={() => setDetailRecord(record)}>
                                            <TableCell className="text-sm text-zinc-900">
                                                {formatDate(record.date)}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">
                                                        {record.employee?.full_name || 'Unknown'}
                                                    </p>
                                                    <p className="text-xs text-zinc-500">
                                                        {record.employee?.employee_id || ''}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {record.employee?.department?.name || '-'}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-900">
                                                {formatTime(record.clock_in)}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-900">
                                                {formatTime(record.clock_out)}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {record.clock_in && !record.clock_out
                                                    ? <LiveTimer clockIn={record.clock_in} />
                                                    : formatHours(record.total_work_minutes)
                                                }
                                            </TableCell>
                                            <TableCell>
                                                <AttendanceStatusBadge status={record.status} />
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {record.late_minutes > 0 ? (
                                                    <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">+{formatHours(record.late_minutes)}</span>
                                                ) : record.clock_in ? (
                                                    <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">On Time</span>
                                                ) : (
                                                    <span className="text-zinc-400">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {record.early_leave_minutes > 0 ? (
                                                    <span className="font-medium text-orange-600">{formatHours(record.early_leave_minutes)}</span>
                                                ) : (
                                                    <span className="text-zinc-400">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1" onClick={(e) => e.stopPropagation()}>
                                                    <Button variant="ghost" size="sm" onClick={() => setDetailRecord(record)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(record)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {pagination.last_page > 1 && (
                                <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                                    <p className="text-sm text-zinc-500">
                                        Showing {pagination.from}-{pagination.to} of {pagination.total}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page <= 1}
                                            onClick={() => setPage(page - 1)}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <span className="text-sm text-zinc-600">
                                            Page {page} of {pagination.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page >= pagination.last_page}
                                            onClick={() => setPage(page + 1)}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            {/* Detail Dialog */}
            <Dialog open={!!detailRecord} onOpenChange={() => setDetailRecord(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Attendance Detail</DialogTitle>
                        <DialogDescription>
                            {detailRecord?.employee?.full_name} - {formatDate(detailRecord?.date)}
                        </DialogDescription>
                    </DialogHeader>
                    {detailRecord && (
                        <div className="space-y-4">
                            {/* Clock In / Clock Out with photos */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <p className="text-xs font-medium text-zinc-500">Clock In</p>
                                    <p className="text-sm text-zinc-900">{formatTime(detailRecord.clock_in)}</p>
                                    {detailRecord.clock_in_photo_url ? (
                                        <img
                                            src={detailRecord.clock_in_photo_url}
                                            alt="Clock in selfie"
                                            className="h-28 w-full rounded-lg border border-zinc-200 object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-28 w-full items-center justify-center rounded-lg border border-dashed border-zinc-200 bg-zinc-50">
                                            <Camera className="h-5 w-5 text-zinc-300" />
                                        </div>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <p className="text-xs font-medium text-zinc-500">Clock Out</p>
                                    <p className="text-sm text-zinc-900">{formatTime(detailRecord.clock_out)}</p>
                                    {detailRecord.clock_out_photo_url ? (
                                        <img
                                            src={detailRecord.clock_out_photo_url}
                                            alt="Clock out selfie"
                                            className="h-28 w-full rounded-lg border border-zinc-200 object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-28 w-full items-center justify-center rounded-lg border border-dashed border-zinc-200 bg-zinc-50">
                                            <Camera className="h-5 w-5 text-zinc-300" />
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-zinc-500">Status</p>
                                    <AttendanceStatusBadge status={detailRecord.status} />
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-zinc-500">Total Hours</p>
                                    {detailRecord.clock_in && !detailRecord.clock_out
                                        ? <LiveTimer clockIn={detailRecord.clock_in} />
                                        : <p className="text-sm text-zinc-900">{formatHours(detailRecord.total_work_minutes)}</p>
                                    }
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-zinc-500">Late</p>
                                    {detailRecord.late_minutes > 0 ? (
                                        <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">+{formatHours(detailRecord.late_minutes)}</span>
                                    ) : detailRecord.clock_in ? (
                                        <span className="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">On Time</span>
                                    ) : (
                                        <p className="text-sm text-zinc-900">-</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-zinc-500">Early Leave</p>
                                    {detailRecord.early_leave_minutes > 0 ? (
                                        <span className="text-sm font-medium text-orange-600">{formatHours(detailRecord.early_leave_minutes)}</span>
                                    ) : (
                                        <p className="text-sm text-zinc-900">-</p>
                                    )}
                                </div>
                            </div>

                            {detailRecord.clock_in_ip && (
                                <div className="flex items-center gap-2 text-sm text-zinc-600">
                                    <MapPin className="h-4 w-4 text-zinc-400" />
                                    <span>IP: {detailRecord.clock_in_ip}</span>
                                </div>
                            )}

                            {detailRecord.remarks && (
                                <div className="flex items-start gap-2">
                                    <MessageSquare className="mt-0.5 h-4 w-4 text-zinc-400" />
                                    <div>
                                        <p className="text-xs font-medium text-zinc-500">Remarks</p>
                                        <p className="text-sm text-zinc-700">{detailRecord.remarks}</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editRecord} onOpenChange={() => setEditRecord(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Attendance Record</DialogTitle>
                        <DialogDescription>
                            {editRecord?.employee?.full_name} - {formatDate(editRecord?.date)}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Clock In</Label>
                                <Input
                                    type="time"
                                    value={editForm.clock_in}
                                    onChange={(e) => setEditForm({ ...editForm, clock_in: e.target.value })}
                                />
                            </div>
                            <div>
                                <Label>Clock Out</Label>
                                <Input
                                    type="time"
                                    value={editForm.clock_out}
                                    onChange={(e) => setEditForm({ ...editForm, clock_out: e.target.value })}
                                />
                            </div>
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select value={editForm.status} onValueChange={(val) => setEditForm({ ...editForm, status: val })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.filter((s) => s.value !== 'all').map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Admin Remarks</Label>
                            <Textarea
                                value={editForm.remarks}
                                onChange={(e) => setEditForm({ ...editForm, remarks: e.target.value })}
                                placeholder="Add admin remarks..."
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditRecord(null)}>
                            Cancel
                        </Button>
                        <Button onClick={handleSaveEdit} disabled={updateMutation.isPending}>
                            {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

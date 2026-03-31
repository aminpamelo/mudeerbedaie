import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Clock,
    Star,
    Users,
} from 'lucide-react';
import {
    Card,
    CardContent,
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
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Badge } from '../../components/ui/badge';
import { Checkbox } from '../../components/ui/checkbox';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import PageHeader from '../../components/PageHeader';
import ConfirmDialog from '../../components/ConfirmDialog';
import { cn } from '../../lib/utils';
import {
    fetchSchedules,
    createSchedule,
    updateSchedule,
    deleteSchedule,
} from '../../lib/api';

const DAYS_OF_WEEK = [
    { key: 'mon', label: 'Mon' },
    { key: 'tue', label: 'Tue' },
    { key: 'wed', label: 'Wed' },
    { key: 'thu', label: 'Thu' },
    { key: 'fri', label: 'Fri' },
    { key: 'sat', label: 'Sat' },
    { key: 'sun', label: 'Sun' },
];

const SCHEDULE_TYPES = [
    { value: 'fixed', label: 'Fixed' },
    { value: 'flexible', label: 'Flexible' },
    { value: 'shift', label: 'Shift' },
];

const EMPTY_FORM = {
    name: '',
    type: 'fixed',
    start_time: '09:00',
    end_time: '18:00',
    break_duration: 60,
    grace_period: 15,
    working_days: ['mon', 'tue', 'wed', 'thu', 'fri'],
    is_default: false,
};

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-28 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-16 animate-pulse rounded bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function WorkSchedules() {
    const queryClient = useQueryClient();
    const [showDialog, setShowDialog] = useState(false);
    const [editingSchedule, setEditingSchedule] = useState(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [deleteTarget, setDeleteTarget] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'schedules'],
        queryFn: fetchSchedules,
    });

    const createMutation = useMutation({
        mutationFn: createSchedule,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'schedules'] });
            closeDialog();
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateSchedule(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'schedules'] });
            closeDialog();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteSchedule,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'schedules'] });
            setDeleteTarget(null);
        },
    });

    const schedules = data?.data || [];

    function openCreate() {
        setEditingSchedule(null);
        setForm({ ...EMPTY_FORM });
        setShowDialog(true);
    }

    function openEdit(schedule) {
        const numToDayMap = { 1: 'mon', 2: 'tue', 3: 'wed', 4: 'thu', 5: 'fri', 6: 'sat', 7: 'sun' };
        const days = (schedule.working_days || []).map((d) =>
            typeof d === 'number' ? numToDayMap[d] || d : d
        );
        setEditingSchedule(schedule);
        setForm({
            name: schedule.name || '',
            type: schedule.type || 'fixed',
            start_time: (schedule.start_time || '09:00').slice(0, 5),
            end_time: (schedule.end_time || '18:00').slice(0, 5),
            break_duration: schedule.break_duration_minutes ?? schedule.break_duration ?? 60,
            grace_period: schedule.grace_period_minutes ?? schedule.grace_period ?? 15,
            working_days: days,
            is_default: schedule.is_default || false,
        });
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setEditingSchedule(null);
        setForm({ ...EMPTY_FORM });
    }

    function buildPayload(formData) {
        const dayMap = { mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6, sun: 7 };
        return {
            name: formData.name,
            type: formData.type,
            start_time: formData.start_time,
            end_time: formData.end_time,
            break_duration_minutes: formData.break_duration,
            grace_period_minutes: formData.grace_period,
            working_days: formData.working_days.map((d) => dayMap[d] || d),
            is_default: formData.is_default,
        };
    }

    function handleSave() {
        const payload = buildPayload(form);
        if (editingSchedule) {
            updateMutation.mutate({ id: editingSchedule.id, data: payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    function handleSetDefault(schedule) {
        updateMutation.mutate({
            id: schedule.id,
            data: { ...schedule, is_default: true },
        });
    }

    function toggleDay(day) {
        setForm((prev) => ({
            ...prev,
            working_days: prev.working_days.includes(day)
                ? prev.working_days.filter((d) => d !== day)
                : [...prev.working_days, day],
        }));
    }

    function formatWorkingHours(start, end) {
        if (!start || !end) {
            return '-';
        }
        return `${start.slice(0, 5)} - ${end.slice(0, 5)}`;
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;

    return (
        <div className="space-y-6">
            <PageHeader
                title="Work Schedules"
                description="Manage work schedules and office hours"
                action={
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Schedule
                    </Button>
                }
            />

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : schedules.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <Clock className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No schedules configured</p>
                            <p className="mb-4 text-xs text-zinc-400">Create your first work schedule to get started</p>
                            <Button onClick={openCreate} size="sm">
                                <Plus className="mr-2 h-4 w-4" />
                                Create Schedule
                            </Button>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Hours</TableHead>
                                    <TableHead>Break</TableHead>
                                    <TableHead>Grace Period</TableHead>
                                    <TableHead>Working Days</TableHead>
                                    <TableHead>Employees</TableHead>
                                    <TableHead>Default</TableHead>
                                    <TableHead className="w-28">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {schedules.map((schedule) => (
                                    <TableRow key={schedule.id}>
                                        <TableCell>
                                            <p className="text-sm font-medium text-zinc-900">{schedule.name}</p>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {SCHEDULE_TYPES.find((t) => t.value === schedule.type)?.label || schedule.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {formatWorkingHours(schedule.start_time, schedule.end_time)}
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {schedule.break_duration ?? 0} min
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            {schedule.grace_period ?? 0} min
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                {DAYS_OF_WEEK.map((day) => (
                                                    <span
                                                        key={day.key}
                                                        className={cn(
                                                            'inline-flex h-6 w-6 items-center justify-center rounded text-[10px] font-medium',
                                                            schedule.working_days?.includes(day.key)
                                                                ? 'bg-blue-100 text-blue-700'
                                                                : 'bg-zinc-100 text-zinc-400'
                                                        )}
                                                    >
                                                        {day.label.charAt(0)}
                                                    </span>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-zinc-600">
                                            <div className="flex items-center gap-1">
                                                <Users className="h-3.5 w-3.5 text-zinc-400" />
                                                {schedule.employee_schedules_count ?? 0}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {schedule.is_default ? (
                                                <Badge variant="success">
                                                    <Star className="mr-1 h-3 w-3" />
                                                    Default
                                                </Badge>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleSetDefault(schedule)}
                                                    className="text-xs text-zinc-500 hover:text-zinc-700"
                                                >
                                                    Set Default
                                                </Button>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(schedule)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDeleteTarget(schedule)}
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Create/Edit Dialog */}
            <Dialog open={showDialog} onOpenChange={closeDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingSchedule ? 'Edit Schedule' : 'Create Schedule'}</DialogTitle>
                        <DialogDescription>
                            {editingSchedule ? 'Update the work schedule details' : 'Configure a new work schedule'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Schedule Name</Label>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder="e.g. Standard Office Hours"
                            />
                        </div>
                        <div>
                            <Label>Type</Label>
                            <Select value={form.type} onValueChange={(val) => setForm({ ...form, type: val })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {SCHEDULE_TYPES.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Start Time</Label>
                                <Input
                                    type="time"
                                    value={form.start_time}
                                    onChange={(e) => setForm({ ...form, start_time: e.target.value })}
                                />
                            </div>
                            <div>
                                <Label>End Time</Label>
                                <Input
                                    type="time"
                                    value={form.end_time}
                                    onChange={(e) => setForm({ ...form, end_time: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Break Duration (min)</Label>
                                <Input
                                    type="number"
                                    value={form.break_duration}
                                    onChange={(e) => setForm({ ...form, break_duration: parseInt(e.target.value) || 0 })}
                                    min={0}
                                />
                            </div>
                            <div>
                                <Label>Grace Period (min)</Label>
                                <Input
                                    type="number"
                                    value={form.grace_period}
                                    onChange={(e) => setForm({ ...form, grace_period: parseInt(e.target.value) || 0 })}
                                    min={0}
                                />
                            </div>
                        </div>
                        <div>
                            <Label className="mb-2 block">Working Days</Label>
                            <div className="flex gap-2">
                                {DAYS_OF_WEEK.map((day) => (
                                    <button
                                        key={day.key}
                                        type="button"
                                        onClick={() => toggleDay(day.key)}
                                        className={cn(
                                            'flex h-10 w-10 items-center justify-center rounded-lg border text-sm font-medium transition-colors',
                                            form.working_days.includes(day.key)
                                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                : 'border-zinc-200 bg-white text-zinc-500 hover:bg-zinc-50'
                                        )}
                                    >
                                        {day.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_default"
                                checked={form.is_default}
                                onCheckedChange={(checked) => setForm({ ...form, is_default: !!checked })}
                            />
                            <Label htmlFor="is_default">Set as default schedule</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving || !form.name}>
                            {isSaving ? 'Saving...' : editingSchedule ? 'Update Schedule' : 'Create Schedule'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Delete Schedule"
                description={`Are you sure you want to delete "${deleteTarget?.name}"? This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
            />
        </div>
    );
}

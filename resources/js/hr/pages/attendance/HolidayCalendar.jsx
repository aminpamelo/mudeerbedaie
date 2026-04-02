import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Plus,
    Pencil,
    Trash2,
    Calendar,
    List,
    Grid3X3,
    Upload,
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
import { Checkbox } from '../../components/ui/checkbox';
import PageHeader from '../../components/PageHeader';
import ConfirmDialog from '../../components/ConfirmDialog';
import { cn } from '../../lib/utils';
import {
    fetchHolidays,
    createHoliday,
    updateHoliday,
    deleteHoliday,
    bulkImportHolidays,
} from '../../lib/api';

const HOLIDAY_TYPES = [
    { value: 'public', label: 'Public Holiday' },
    { value: 'company', label: 'Company Holiday' },
    { value: 'state', label: 'State Holiday' },
    { value: 'replacement', label: 'Replacement Holiday' },
];

const MALAYSIAN_STATES = [
    'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
    'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
    'Sarawak', 'Selangor', 'Terengganu', 'W.P. Kuala Lumpur',
    'W.P. Labuan', 'W.P. Putrajaya',
];

const EMPTY_FORM = {
    name: '',
    date: '',
    type: 'public',
    states: [],
    is_recurring: false,
};

const DAYS_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function formatDate(dateString) {
    if (!dateString) {
        return '-';
    }
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        weekday: 'short',
    });
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function CalendarGrid({ year, month, holidays }) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDayOfWeek = firstDay.getDay();
    const daysInMonth = lastDay.getDate();

    const holidayMap = useMemo(() => {
        const map = {};
        holidays.forEach((h) => {
            const d = new Date(h.date);
            if (d.getFullYear() === year && d.getMonth() === month) {
                const key = d.getDate();
                if (!map[key]) {
                    map[key] = [];
                }
                map[key].push(h);
            }
        });
        return map;
    }, [holidays, year, month]);

    const cells = [];
    for (let i = 0; i < startDayOfWeek; i++) {
        cells.push(<div key={`empty-${i}`} className="h-24 border border-zinc-100 bg-zinc-50/50" />);
    }
    for (let day = 1; day <= daysInMonth; day++) {
        const dayHolidays = holidayMap[day] || [];
        const isToday =
            new Date().getFullYear() === year &&
            new Date().getMonth() === month &&
            new Date().getDate() === day;

        cells.push(
            <div
                key={day}
                className={cn(
                    'h-24 border border-zinc-100 p-1',
                    dayHolidays.length > 0 && 'bg-red-50',
                    isToday && 'ring-2 ring-inset ring-blue-400'
                )}
            >
                <div className={cn(
                    'mb-0.5 text-xs font-medium',
                    isToday ? 'text-blue-600' : 'text-zinc-600'
                )}>
                    {day}
                </div>
                {dayHolidays.map((h) => (
                    <div
                        key={h.id}
                        className="mb-0.5 truncate rounded bg-red-100 px-1 py-0.5 text-[10px] font-medium text-red-700"
                        title={h.name}
                    >
                        {h.name}
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div>
            <div className="grid grid-cols-7 gap-0">
                {DAYS_LABELS.map((label) => (
                    <div key={label} className="border border-zinc-100 bg-zinc-50 py-2 text-center text-xs font-medium text-zinc-500">
                        {label}
                    </div>
                ))}
                {cells}
            </div>
        </div>
    );
}

export default function HolidayCalendar() {
    const queryClient = useQueryClient();
    const currentYear = new Date().getFullYear();
    const [viewMode, setViewMode] = useState('calendar');
    const [selectedYear, setSelectedYear] = useState(currentYear);
    const [calendarMonth, setCalendarMonth] = useState(new Date().getMonth());
    const [showDialog, setShowDialog] = useState(false);
    const [editTarget, setEditTarget] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [showImportDialog, setShowImportDialog] = useState(false);
    const [importYear, setImportYear] = useState(String(currentYear));
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [formErrors, setFormErrors] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'attendance', 'holidays', selectedYear],
        queryFn: () => fetchHolidays({ year: selectedYear }),
    });

    const createMutation = useMutation({
        mutationFn: createHoliday,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'holidays'] });
            closeDialog();
        },
        onError: (err) => setFormErrors(err?.response?.data?.errors || {}),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => updateHoliday(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'holidays'] });
            closeDialog();
        },
        onError: (err) => setFormErrors(err?.response?.data?.errors || {}),
    });

    const deleteMutation = useMutation({
        mutationFn: deleteHoliday,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'holidays'] });
            setDeleteTarget(null);
        },
        onError: (error) => alert(error?.response?.data?.message || 'Failed to delete holiday'),
    });

    const importMutation = useMutation({
        mutationFn: bulkImportHolidays,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'attendance', 'holidays'] });
            setShowImportDialog(false);
        },
        onError: (error) => alert(error?.response?.data?.message || 'Failed to import holidays'),
    });

    const holidays = data?.data || [];

    function openCreate() {
        setEditTarget(null);
        setForm({ ...EMPTY_FORM });
        setShowDialog(true);
    }

    function openEdit(holiday) {
        setEditTarget(holiday);
        setForm({
            name: holiday.name || '',
            date: holiday.date || '',
            type: holiday.type || 'public',
            states: holiday.states || [],
            is_recurring: holiday.is_recurring || false,
        });
        setShowDialog(true);
    }

    function closeDialog() {
        setShowDialog(false);
        setEditTarget(null);
        setForm({ ...EMPTY_FORM });
        setFormErrors({});
    }

    function handleSave() {
        if (editTarget) {
            updateMutation.mutate({ id: editTarget.id, data: form });
        } else {
            createMutation.mutate(form);
        }
    }

    function handleImport() {
        importMutation.mutate({ year: parseInt(importYear) });
    }

    function navigateMonth(direction) {
        let newMonth = calendarMonth + direction;
        let newYear = selectedYear;
        if (newMonth < 0) {
            newMonth = 11;
            newYear = selectedYear - 1;
        } else if (newMonth > 11) {
            newMonth = 0;
            newYear = selectedYear + 1;
        }
        setCalendarMonth(newMonth);
        setSelectedYear(newYear);
    }

    function toggleState(state) {
        setForm((prev) => ({
            ...prev,
            states: prev.states.includes(state)
                ? prev.states.filter((s) => s !== state)
                : [...prev.states, state],
        }));
    }

    const isSaving = createMutation.isPending || updateMutation.isPending;
    const monthName = new Date(selectedYear, calendarMonth).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' });
    const yearOptions = Array.from({ length: 5 }, (_, i) => currentYear - 1 + i);

    return (
        <div className="space-y-6">
            <PageHeader
                title="Holiday Calendar"
                description="Manage public holidays and company holidays"
                action={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={() => setShowImportDialog(true)}>
                            <Upload className="mr-2 h-4 w-4" />
                            Import Holidays
                        </Button>
                        <Button onClick={openCreate} size="sm">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Holiday
                        </Button>
                    </div>
                }
            />

            {/* View Toggle & Year Selector */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Button
                        variant={viewMode === 'calendar' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setViewMode('calendar')}
                    >
                        <Grid3X3 className="mr-1 h-4 w-4" />
                        Calendar
                    </Button>
                    <Button
                        variant={viewMode === 'list' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setViewMode('list')}
                    >
                        <List className="mr-1 h-4 w-4" />
                        List
                    </Button>
                </div>
                <div className="w-32">
                    <Select value={String(selectedYear)} onValueChange={(val) => setSelectedYear(parseInt(val))}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {yearOptions.map((y) => (
                                <SelectItem key={y} value={String(y)}>
                                    {y}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {/* Calendar View */}
            {viewMode === 'calendar' && (
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <Button variant="ghost" size="sm" onClick={() => navigateMonth(-1)}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <CardTitle>{monthName}</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => navigateMonth(1)}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="h-[500px] animate-pulse rounded bg-zinc-100" />
                        ) : (
                            <CalendarGrid
                                year={selectedYear}
                                month={calendarMonth}
                                holidays={holidays}
                            />
                        )}
                    </CardContent>
                </Card>
            )}

            {/* List View */}
            {viewMode === 'list' && (
                <Card>
                    <CardContent className="p-0">
                        {isLoading ? (
                            <SkeletonTable />
                        ) : holidays.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <Calendar className="mb-3 h-10 w-10 text-zinc-300" />
                                <p className="text-sm font-medium text-zinc-500">No holidays for {selectedYear}</p>
                                <p className="mb-4 text-xs text-zinc-400">Add holidays or import Malaysian public holidays</p>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setShowImportDialog(true)}>
                                        <Upload className="mr-2 h-4 w-4" />
                                        Import
                                    </Button>
                                    <Button size="sm" onClick={openCreate}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Holiday
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>States</TableHead>
                                        <TableHead>Recurring</TableHead>
                                        <TableHead className="w-24">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {holidays.map((holiday) => (
                                        <TableRow key={holiday.id}>
                                            <TableCell className="text-sm text-zinc-900">
                                                {formatDate(holiday.date)}
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm font-medium text-zinc-900">{holiday.name}</p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {HOLIDAY_TYPES.find((t) => t.value === holiday.type)?.label || holiday.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {holiday.states?.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {holiday.states.slice(0, 3).map((state) => (
                                                            <Badge key={state} variant="outline" className="text-[10px]">
                                                                {state}
                                                            </Badge>
                                                        ))}
                                                        {holiday.states.length > 3 && (
                                                            <Badge variant="outline" className="text-[10px]">
                                                                +{holiday.states.length - 3}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-zinc-400">All</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-600">
                                                {holiday.is_recurring ? 'Yes' : 'No'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Button variant="ghost" size="sm" onClick={() => openEdit(holiday)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setDeleteTarget(holiday)}
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
            )}

            {/* Create/Edit Dialog */}
            <Dialog open={showDialog} onOpenChange={closeDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editTarget ? 'Edit Holiday' : 'Add Holiday'}</DialogTitle>
                        <DialogDescription>
                            {editTarget ? 'Update holiday details' : 'Add a new holiday to the calendar'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Holiday Name</Label>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder="e.g. Hari Raya Aidilfitri"
                            />
                        </div>
                        <div>
                            <Label>Date</Label>
                            <Input
                                type="date"
                                value={form.date}
                                onChange={(e) => setForm({ ...form, date: e.target.value })}
                            />
                        </div>
                        <div>
                            <Label>Type</Label>
                            <Select value={form.type} onValueChange={(val) => setForm({ ...form, type: val })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {HOLIDAY_TYPES.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label className="mb-2 block">Applicable States (leave empty for all)</Label>
                            <div className="grid max-h-40 grid-cols-2 gap-1 overflow-y-auto rounded-lg border border-zinc-200 p-2">
                                {MALAYSIAN_STATES.map((state) => (
                                    <label key={state} className="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 hover:bg-zinc-50">
                                        <Checkbox
                                            checked={form.states.includes(state)}
                                            onCheckedChange={() => toggleState(state)}
                                        />
                                        <span className="text-xs text-zinc-700">{state}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_recurring"
                                checked={form.is_recurring}
                                onCheckedChange={(checked) => setForm({ ...form, is_recurring: !!checked })}
                            />
                            <Label htmlFor="is_recurring">Recurring every year</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving || !form.name || !form.date}>
                            {isSaving ? 'Saving...' : editTarget ? 'Update Holiday' : 'Add Holiday'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Import Dialog */}
            <Dialog open={showImportDialog} onOpenChange={() => setShowImportDialog(false)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Import Malaysian Holidays</DialogTitle>
                        <DialogDescription>
                            Import public holidays for a selected year
                        </DialogDescription>
                    </DialogHeader>
                    <div>
                        <Label>Year</Label>
                        <Select value={importYear} onValueChange={setImportYear}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {yearOptions.map((y) => (
                                    <SelectItem key={y} value={String(y)}>
                                        {y}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowImportDialog(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleImport} disabled={importMutation.isPending}>
                            {importMutation.isPending ? 'Importing...' : 'Import Holidays'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Delete Holiday"
                description={`Are you sure you want to delete "${deleteTarget?.name}"?`}
                confirmLabel="Delete"
                variant="destructive"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
            />
        </div>
    );
}

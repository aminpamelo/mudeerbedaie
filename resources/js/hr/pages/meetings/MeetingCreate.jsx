import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import {
    ArrowLeft,
    Plus,
    Trash2,
    GripVertical,
    Loader2,
    Save,
    CalendarCheck,
} from 'lucide-react';
import {
    createMeeting,
    fetchMeetingSeries,
    createMeetingSeries,
    fetchEmployees,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';

export default function MeetingCreate() {
    const navigate = useNavigate();
    const [errors, setErrors] = useState({});
    const [form, setForm] = useState({
        title: '',
        description: '',
        location: '',
        date: '',
        start_time: '',
        end_time: '',
        series_id: '',
        note_taker_id: '',
        attendee_ids: [],
        agenda_items: [],
    });
    const [showSeriesDialog, setShowSeriesDialog] = useState(false);
    const [newSeries, setNewSeries] = useState({ name: '', description: '' });

    const { data: seriesData } = useQuery({
        queryKey: ['hr', 'meeting-series'],
        queryFn: fetchMeetingSeries,
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const seriesList = seriesData?.data || seriesData || [];
    const employees = employeesData?.data || [];

    const createSeriesMut = useMutation({
        mutationFn: (data) => createMeetingSeries(data),
        onSuccess: (res) => {
            const created = res.data || res;
            setForm((f) => ({ ...f, series_id: String(created.id) }));
            setShowSeriesDialog(false);
            setNewSeries({ name: '', description: '' });
        },
    });

    const createMut = useMutation({
        mutationFn: (data) => createMeeting(data),
        onSuccess: (res) => {
            const meeting = res.data || res;
            navigate(`/meetings/${meeting.id}`);
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    function updateField(field, value) {
        setForm((f) => ({ ...f, [field]: value }));
        setErrors((e) => ({ ...e, [field]: undefined }));
    }

    function addAgendaItem() {
        setForm((f) => ({
            ...f,
            agenda_items: [...f.agenda_items, { title: '', description: '' }],
        }));
    }

    function updateAgendaItem(index, field, value) {
        setForm((f) => ({
            ...f,
            agenda_items: f.agenda_items.map((item, i) =>
                i === index ? { ...item, [field]: value } : item
            ),
        }));
    }

    function removeAgendaItem(index) {
        setForm((f) => ({
            ...f,
            agenda_items: f.agenda_items.filter((_, i) => i !== index),
        }));
    }

    function toggleAttendee(employeeId) {
        const id = String(employeeId);
        setForm((f) => ({
            ...f,
            attendee_ids: f.attendee_ids.includes(id)
                ? f.attendee_ids.filter((a) => a !== id)
                : [...f.attendee_ids, id],
        }));
    }

    function handleSubmit(status) {
        const payload = {
            ...form,
            status,
            series_id: form.series_id || null,
            note_taker_id: form.note_taker_id || null,
            agenda_items: form.agenda_items.filter((a) => a.title.trim()),
        };
        createMut.mutate(payload);
    }

    return (
        <div>
            <PageHeader
                title="Create Meeting"
                description="Schedule a new meeting and invite attendees."
                action={
                    <Button variant="outline" onClick={() => navigate('/meetings')}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back to Meetings
                    </Button>
                }
            />

            <div className="space-y-6">
                {/* Basic Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Meeting Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="title">Title *</Label>
                                <Input
                                    id="title"
                                    value={form.title}
                                    onChange={(e) => updateField('title', e.target.value)}
                                    placeholder="Meeting title"
                                />
                                {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title[0]}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={form.description}
                                    onChange={(e) => updateField('description', e.target.value)}
                                    placeholder="Meeting description or purpose"
                                    rows={3}
                                />
                            </div>

                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    value={form.location}
                                    onChange={(e) => updateField('location', e.target.value)}
                                    placeholder="Meeting room, virtual link, etc."
                                />
                            </div>

                            <div>
                                <Label htmlFor="date">Date *</Label>
                                <Input
                                    id="date"
                                    type="date"
                                    value={form.date}
                                    onChange={(e) => updateField('date', e.target.value)}
                                />
                                {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date[0]}</p>}
                            </div>

                            <div>
                                <Label htmlFor="start_time">Start Time *</Label>
                                <Input
                                    id="start_time"
                                    type="time"
                                    value={form.start_time}
                                    onChange={(e) => updateField('start_time', e.target.value)}
                                />
                                {errors.start_time && <p className="mt-1 text-xs text-red-500">{errors.start_time[0]}</p>}
                            </div>

                            <div>
                                <Label htmlFor="end_time">End Time</Label>
                                <Input
                                    id="end_time"
                                    type="time"
                                    value={form.end_time}
                                    onChange={(e) => updateField('end_time', e.target.value)}
                                />
                            </div>

                            <div>
                                <Label>Series</Label>
                                <div className="flex gap-2">
                                    <Select
                                        value={form.series_id}
                                        onValueChange={(v) => updateField('series_id', v === 'none' ? '' : v)}
                                    >
                                        <SelectTrigger className="flex-1">
                                            <SelectValue placeholder="Select series (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No series</SelectItem>
                                            {seriesList.map((s) => (
                                                <SelectItem key={s.id} value={String(s.id)}>
                                                    {s.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={() => setShowSeriesDialog(true)}
                                    >
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <Label>Note Taker</Label>
                                <Select
                                    value={form.note_taker_id}
                                    onValueChange={(v) => updateField('note_taker_id', v === 'none' ? '' : v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select note taker (optional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        {employees.map((emp) => (
                                            <SelectItem key={emp.id} value={String(emp.id)}>
                                                {emp.full_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Attendees */}
                <Card>
                    <CardHeader>
                        <CardTitle>Attendees</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="max-h-60 space-y-1 overflow-y-auto">
                            {employees.length === 0 && (
                                <p className="text-sm text-zinc-500">No employees found.</p>
                            )}
                            {employees.map((emp) => (
                                <label
                                    key={emp.id}
                                    className="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-zinc-50"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.attendee_ids.includes(String(emp.id))}
                                        onChange={() => toggleAttendee(emp.id)}
                                        className="h-4 w-4 rounded border-zinc-300"
                                    />
                                    <div>
                                        <p className="text-sm font-medium text-zinc-900">{emp.full_name}</p>
                                        <p className="text-xs text-zinc-500">
                                            {emp.department?.name || ''} {emp.position?.title ? `- ${emp.position.title}` : ''}
                                        </p>
                                    </div>
                                </label>
                            ))}
                        </div>
                        {form.attendee_ids.length > 0 && (
                            <p className="mt-2 text-xs text-zinc-500">
                                {form.attendee_ids.length} attendee{form.attendee_ids.length !== 1 ? 's' : ''} selected
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Agenda Items */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Agenda Items</CardTitle>
                        <Button variant="outline" size="sm" onClick={addAgendaItem}>
                            <Plus className="mr-1 h-3.5 w-3.5" />
                            Add Item
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {form.agenda_items.length === 0 ? (
                            <p className="text-sm text-zinc-500">No agenda items added yet.</p>
                        ) : (
                            <div className="space-y-3">
                                {form.agenda_items.map((item, index) => (
                                    <div
                                        key={index}
                                        className="flex items-start gap-3 rounded-lg border border-zinc-200 p-3"
                                    >
                                        <div className="flex items-center pt-2.5">
                                            <GripVertical className="h-4 w-4 text-zinc-400" />
                                        </div>
                                        <div className="flex-1 space-y-2">
                                            <Input
                                                value={item.title}
                                                onChange={(e) => updateAgendaItem(index, 'title', e.target.value)}
                                                placeholder={`Agenda item ${index + 1}`}
                                            />
                                            <Textarea
                                                value={item.description}
                                                onChange={(e) => updateAgendaItem(index, 'description', e.target.value)}
                                                placeholder="Description (optional)"
                                                rows={2}
                                            />
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeAgendaItem(index)}
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex justify-end gap-3">
                    <Button variant="outline" onClick={() => navigate('/meetings')}>
                        Cancel
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={() => handleSubmit('draft')}
                        disabled={createMut.isPending}
                    >
                        {createMut.isPending ? (
                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="mr-1.5 h-4 w-4" />
                        )}
                        Save as Draft
                    </Button>
                    <Button
                        onClick={() => handleSubmit('scheduled')}
                        disabled={createMut.isPending}
                    >
                        {createMut.isPending ? (
                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        ) : (
                            <CalendarCheck className="mr-1.5 h-4 w-4" />
                        )}
                        Schedule
                    </Button>
                </div>
            </div>

            {/* Create Series Dialog */}
            <Dialog open={showSeriesDialog} onOpenChange={setShowSeriesDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Meeting Series</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="series_name">Name *</Label>
                            <Input
                                id="series_name"
                                value={newSeries.name}
                                onChange={(e) => setNewSeries((s) => ({ ...s, name: e.target.value }))}
                                placeholder="e.g. Weekly Team Standup"
                            />
                        </div>
                        <div>
                            <Label htmlFor="series_desc">Description</Label>
                            <Textarea
                                id="series_desc"
                                value={newSeries.description}
                                onChange={(e) => setNewSeries((s) => ({ ...s, description: e.target.value }))}
                                placeholder="Series description"
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowSeriesDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => createSeriesMut.mutate(newSeries)}
                            disabled={createSeriesMut.isPending || !newSeries.name.trim()}
                        >
                            {createSeriesMut.isPending ? 'Creating...' : 'Create Series'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

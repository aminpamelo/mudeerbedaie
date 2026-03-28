import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useParams } from 'react-router-dom';
import {
    ArrowLeft,
    Plus,
    Trash2,
    GripVertical,
    Loader2,
} from 'lucide-react';
import {
    fetchMeeting,
    updateMeeting,
    fetchMeetingSeries,
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

export default function MeetingEdit() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [errors, setErrors] = useState({});
    const [form, setForm] = useState(null);

    const { data: meetingData, isLoading } = useQuery({
        queryKey: ['hr', 'meeting', id],
        queryFn: () => fetchMeeting(id),
    });

    const { data: seriesData } = useQuery({
        queryKey: ['hr', 'meeting-series'],
        queryFn: fetchMeetingSeries,
    });

    const { data: employeesData } = useQuery({
        queryKey: ['hr', 'employees', 'all'],
        queryFn: () => fetchEmployees({ per_page: 200 }),
    });

    const meeting = meetingData?.data || meetingData;
    const seriesList = seriesData?.data || seriesData || [];
    const employees = employeesData?.data || [];

    useEffect(() => {
        if (meeting && !form) {
            setForm({
                title: meeting.title || '',
                description: meeting.description || '',
                location: meeting.location || '',
                date: meeting.date || '',
                start_time: meeting.start_time || '',
                end_time: meeting.end_time || '',
                series_id: meeting.series_id ? String(meeting.series_id) : '',
                note_taker_id: meeting.note_taker_id ? String(meeting.note_taker_id) : '',
                attendee_ids: meeting.attendees?.map((a) => String(a.employee_id || a.id)) || [],
                agenda_items: meeting.agenda_items?.map((a) => ({
                    id: a.id,
                    title: a.title || '',
                    description: a.description || '',
                })) || [],
            });
        }
    }, [meeting, form]);

    const updateMut = useMutation({
        mutationFn: (data) => updateMeeting(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'meetings'] });
            navigate(`/meetings/${id}`);
        },
        onError: (err) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            }
        },
    });

    if (isLoading || !form) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

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
        const eid = String(employeeId);
        setForm((f) => ({
            ...f,
            attendee_ids: f.attendee_ids.includes(eid)
                ? f.attendee_ids.filter((a) => a !== eid)
                : [...f.attendee_ids, eid],
        }));
    }

    function handleSubmit() {
        const payload = {
            ...form,
            series_id: form.series_id || null,
            note_taker_id: form.note_taker_id || null,
            agenda_items: form.agenda_items.filter((a) => a.title.trim()),
        };
        updateMut.mutate(payload);
    }

    return (
        <div>
            <PageHeader
                title="Edit Meeting"
                description={`Editing: ${meeting?.title || ''}`}
                action={
                    <Button variant="outline" onClick={() => navigate(`/meetings/${id}`)}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back to Meeting
                    </Button>
                }
            />

            <div className="space-y-6">
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
                                />
                                {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title[0]}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={form.description}
                                    onChange={(e) => updateField('description', e.target.value)}
                                    rows={3}
                                />
                            </div>

                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    value={form.location}
                                    onChange={(e) => updateField('location', e.target.value)}
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
                                <Select
                                    value={form.series_id}
                                    onValueChange={(v) => updateField('series_id', v === 'none' ? '' : v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="No series" />
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
                            </div>

                            <div>
                                <Label>Note Taker</Label>
                                <Select
                                    value={form.note_taker_id}
                                    onValueChange={(v) => updateField('note_taker_id', v === 'none' ? '' : v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="None" />
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
                                            {emp.department?.name || ''}
                                        </p>
                                    </div>
                                </label>
                            ))}
                        </div>
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
                            <p className="text-sm text-zinc-500">No agenda items.</p>
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
                                        <Button variant="ghost" size="icon" onClick={() => removeAgendaItem(index)}>
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" onClick={() => navigate(`/meetings/${id}`)}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={updateMut.isPending}>
                        {updateMut.isPending ? (
                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        ) : null}
                        Update Meeting
                    </Button>
                </div>
            </div>
        </div>
    );
}

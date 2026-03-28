import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
    ArrowLeft,
    Pencil,
    Play,
    CheckCircle2,
    XCircle,
    Mic,
    Upload,
    Plus,
    Trash2,
    Loader2,
    MapPin,
    Calendar,
    Clock,
    FileText,
    Download,
    ChevronDown,
    ChevronRight,
    MessageSquare,
    Brain,
    Send,
    Users,
} from 'lucide-react';
import {
    fetchMeeting,
    updateMeetingStatus,
    updateAttendeeStatus,
    addAgendaItem,
    updateAgendaItem,
    deleteAgendaItem,
    addDecision,
    updateDecision,
    deleteDecision,
    createMeetingTask,
    updateTaskStatus,
    addTaskComment,
    createSubtask,
    uploadMeetingAttachment,
    deleteMeetingAttachment,
    uploadRecording,
    deleteRecording,
    triggerTranscription,
    fetchTranscript,
    triggerAiAnalysis,
    fetchAiSummary,
    approveAiTasks,
} from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Badge } from '../../components/ui/badge';
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
    DialogDescription,
} from '../../components/ui/dialog';
import AttendeeList from '../../components/meetings/AttendeeList';
import AgendaEditor from '../../components/meetings/AgendaEditor';
import DecisionLog from '../../components/meetings/DecisionLog';
import TaskList from '../../components/meetings/TaskList';
import RecordingPlayer from '../../components/meetings/RecordingPlayer';
import TranscriptViewer from '../../components/meetings/TranscriptViewer';
import AiSummaryPanel from '../../components/meetings/AiSummaryPanel';

const STATUS_BADGE = {
    draft: { label: 'Draft', variant: 'secondary' },
    scheduled: { label: 'Scheduled', className: 'bg-blue-100 text-blue-800 border-transparent' },
    in_progress: { label: 'In Progress', variant: 'warning' },
    completed: { label: 'Completed', variant: 'success' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

function MeetingStatusBadge({ status }) {
    const config = STATUS_BADGE[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatTime(t) {
    if (!t) return '';
    return t.slice(0, 5);
}

export default function MeetingDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [showTaskDialog, setShowTaskDialog] = useState(false);
    const [taskForm, setTaskForm] = useState({ title: '', description: '', assigned_to: '', priority: 'medium', deadline: '' });
    const [attachFile, setAttachFile] = useState(null);
    const [recordFile, setRecordFile] = useState(null);

    const { data: meetingData, isLoading } = useQuery({
        queryKey: ['hr', 'meeting', id],
        queryFn: () => fetchMeeting(id),
    });

    const { data: transcriptData } = useQuery({
        queryKey: ['hr', 'meeting', id, 'transcript'],
        queryFn: () => fetchTranscript(id),
        enabled: !!meetingData,
        retry: false,
    });

    const { data: aiSummaryData } = useQuery({
        queryKey: ['hr', 'meeting', id, 'ai-summary'],
        queryFn: () => fetchAiSummary(id),
        enabled: !!meetingData,
        retry: false,
    });

    const meeting = meetingData?.data || meetingData;
    const transcript = transcriptData?.data || transcriptData;
    const aiSummary = aiSummaryData?.data || aiSummaryData;

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id] });
    }

    const statusMut = useMutation({
        mutationFn: (data) => updateMeetingStatus(id, data),
        onSuccess: invalidate,
    });

    const uploadAttachMut = useMutation({
        mutationFn: (formData) => uploadMeetingAttachment(id, formData),
        onSuccess: () => { invalidate(); setAttachFile(null); },
    });

    const deleteAttachMut = useMutation({
        mutationFn: (attId) => deleteMeetingAttachment(id, attId),
        onSuccess: invalidate,
    });

    const uploadRecordMut = useMutation({
        mutationFn: (formData) => uploadRecording(id, formData),
        onSuccess: () => { invalidate(); setRecordFile(null); },
    });

    const deleteRecordMut = useMutation({
        mutationFn: (recId) => deleteRecording(id, recId),
        onSuccess: invalidate,
    });

    const transcribeMut = useMutation({
        mutationFn: (recId) => triggerTranscription(id, recId),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id, 'transcript'] }),
    });

    const aiAnalyzeMut = useMutation({
        mutationFn: () => triggerAiAnalysis(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id, 'ai-summary'] }),
    });

    const approveTasksMut = useMutation({
        mutationFn: (data) => approveAiTasks(id, data),
        onSuccess: invalidate,
    });

    const createTaskMut = useMutation({
        mutationFn: (data) => createMeetingTask(id, data),
        onSuccess: () => {
            invalidate();
            setShowTaskDialog(false);
            setTaskForm({ title: '', description: '', assigned_to: '', priority: 'medium', deadline: '' });
        },
    });

    if (isLoading || !meeting) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
            </div>
        );
    }

    function handleAttachUpload() {
        if (!attachFile) return;
        const fd = new FormData();
        fd.append('file', attachFile);
        uploadAttachMut.mutate(fd);
    }

    function handleRecordUpload() {
        if (!recordFile) return;
        const fd = new FormData();
        fd.append('file', recordFile);
        uploadRecordMut.mutate(fd);
    }

    const canStart = meeting.status === 'scheduled';
    const canComplete = meeting.status === 'in_progress';
    const canCancel = meeting.status !== 'completed' && meeting.status !== 'cancelled';
    const attendees = meeting.attendees || [];
    const agendaItems = meeting.agenda_items || [];
    const decisions = meeting.decisions || [];
    const tasks = meeting.tasks || [];
    const attachments = meeting.attachments || [];
    const recordings = meeting.recordings || [];

    return (
        <div>
            <PageHeader
                title={meeting.title}
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => navigate('/meetings')}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Back
                        </Button>
                        <Button variant="outline" onClick={() => navigate(`/meetings/${id}/edit`)}>
                            <Pencil className="mr-1.5 h-4 w-4" />
                            Edit
                        </Button>
                        {canStart && (
                            <Button
                                onClick={() => statusMut.mutate({ status: 'in_progress' })}
                                disabled={statusMut.isPending}
                            >
                                <Play className="mr-1.5 h-4 w-4" />
                                Start Meeting
                            </Button>
                        )}
                        {canComplete && (
                            <Button
                                variant="secondary"
                                onClick={() => statusMut.mutate({ status: 'completed' })}
                                disabled={statusMut.isPending}
                            >
                                <CheckCircle2 className="mr-1.5 h-4 w-4" />
                                Complete
                            </Button>
                        )}
                        {canCancel && (
                            <Button
                                variant="destructive"
                                onClick={() => statusMut.mutate({ status: 'cancelled' })}
                                disabled={statusMut.isPending}
                            >
                                <XCircle className="mr-1.5 h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => navigate(`/meetings/${id}/record`)}>
                            <Mic className="mr-1.5 h-4 w-4" />
                            Record
                        </Button>
                    </div>
                }
            />

            {/* Meeting Info Header */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-4">
                        <MeetingStatusBadge status={meeting.status} />
                        {meeting.series && (
                            <Badge variant="outline">{meeting.series.name}</Badge>
                        )}
                        <div className="flex items-center gap-1.5 text-sm text-zinc-600">
                            <Calendar className="h-4 w-4 text-zinc-400" />
                            {formatDate(meeting.date)}
                        </div>
                        <div className="flex items-center gap-1.5 text-sm text-zinc-600">
                            <Clock className="h-4 w-4 text-zinc-400" />
                            {formatTime(meeting.start_time)}
                            {meeting.end_time && ` - ${formatTime(meeting.end_time)}`}
                        </div>
                        {meeting.location && (
                            <div className="flex items-center gap-1.5 text-sm text-zinc-600">
                                <MapPin className="h-4 w-4 text-zinc-400" />
                                {meeting.location}
                            </div>
                        )}
                    </div>
                    {meeting.description && (
                        <p className="mt-3 text-sm text-zinc-600">{meeting.description}</p>
                    )}
                </CardContent>
            </Card>

            <div className="space-y-6">
                {/* Attendees */}
                <AttendeeList
                    meetingId={id}
                    attendees={attendees}
                    noteTakerId={meeting.note_taker_id}
                    organizerId={meeting.organizer_id}
                    onUpdate={invalidate}
                />

                {/* Agenda */}
                <AgendaEditor
                    meetingId={id}
                    items={agendaItems}
                    onUpdate={invalidate}
                />

                {/* Decisions */}
                <DecisionLog
                    meetingId={id}
                    decisions={decisions}
                    onUpdate={invalidate}
                />

                {/* Tasks */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            Tasks
                            <Badge variant="secondary">{tasks.length}</Badge>
                        </CardTitle>
                        <Button variant="outline" size="sm" onClick={() => setShowTaskDialog(true)}>
                            <Plus className="mr-1 h-3.5 w-3.5" />
                            Add Task
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <TaskList
                            meetingId={id}
                            tasks={tasks}
                            onUpdate={invalidate}
                        />
                    </CardContent>
                </Card>

                {/* Recordings & AI */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recordings & AI Analysis</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Upload recording */}
                        <div className="flex items-center gap-3">
                            <input
                                type="file"
                                accept="audio/*,video/*"
                                onChange={(e) => setRecordFile(e.target.files?.[0] || null)}
                                className="text-sm"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleRecordUpload}
                                disabled={!recordFile || uploadRecordMut.isPending}
                            >
                                <Upload className="mr-1 h-3.5 w-3.5" />
                                {uploadRecordMut.isPending ? 'Uploading...' : 'Upload'}
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => navigate(`/meetings/${id}/record`)}>
                                <Mic className="mr-1 h-3.5 w-3.5" />
                                Record in Browser
                            </Button>
                        </div>

                        {/* Recording list */}
                        {recordings.length > 0 && (
                            <div className="space-y-3">
                                {recordings.map((rec) => (
                                    <RecordingPlayer
                                        key={rec.id}
                                        recording={rec}
                                        onTranscribe={() => transcribeMut.mutate(rec.id)}
                                        onDelete={() => deleteRecordMut.mutate(rec.id)}
                                        transcribing={transcribeMut.isPending}
                                    />
                                ))}
                            </div>
                        )}

                        {/* Transcript */}
                        <TranscriptViewer transcript={transcript} />

                        {/* AI Analysis */}
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => aiAnalyzeMut.mutate()}
                                disabled={aiAnalyzeMut.isPending}
                            >
                                <Brain className="mr-1 h-3.5 w-3.5" />
                                {aiAnalyzeMut.isPending ? 'Analyzing...' : 'Analyze with AI'}
                            </Button>
                        </div>

                        <AiSummaryPanel
                            summary={aiSummary}
                            onApproveTasks={(data) => approveTasksMut.mutate(data)}
                            approving={approveTasksMut.isPending}
                        />
                    </CardContent>
                </Card>

                {/* Attachments */}
                <Card>
                    <CardHeader>
                        <CardTitle>Attachments</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center gap-3">
                            <input
                                type="file"
                                onChange={(e) => setAttachFile(e.target.files?.[0] || null)}
                                className="text-sm"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleAttachUpload}
                                disabled={!attachFile || uploadAttachMut.isPending}
                            >
                                <Upload className="mr-1 h-3.5 w-3.5" />
                                {uploadAttachMut.isPending ? 'Uploading...' : 'Upload'}
                            </Button>
                        </div>

                        {attachments.length === 0 ? (
                            <p className="text-sm text-zinc-500">No attachments yet.</p>
                        ) : (
                            <div className="space-y-2">
                                {attachments.map((att) => (
                                    <div
                                        key={att.id}
                                        className="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2"
                                    >
                                        <div className="flex items-center gap-2">
                                            <FileText className="h-4 w-4 text-zinc-400" />
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {att.original_name || att.file_name || 'File'}
                                                </p>
                                                <p className="text-xs text-zinc-500">
                                                    {att.file_size ? `${(att.file_size / 1024).toFixed(1)} KB` : ''}
                                                    {att.uploader && ` by ${att.uploader.full_name || att.uploader.name}`}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {att.url && (
                                                <a href={att.url} target="_blank" rel="noopener noreferrer">
                                                    <Button variant="ghost" size="icon">
                                                        <Download className="h-4 w-4" />
                                                    </Button>
                                                </a>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => deleteAttachMut.mutate(att.id)}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Add Task Dialog */}
            <Dialog open={showTaskDialog} onOpenChange={setShowTaskDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Task</DialogTitle>
                        <DialogDescription>Create a new action item for this meeting.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Title *</Label>
                            <Input
                                value={taskForm.title}
                                onChange={(e) => setTaskForm((f) => ({ ...f, title: e.target.value }))}
                                placeholder="Task title"
                            />
                        </div>
                        <div>
                            <Label>Description</Label>
                            <Textarea
                                value={taskForm.description}
                                onChange={(e) => setTaskForm((f) => ({ ...f, description: e.target.value }))}
                                placeholder="Task description"
                                rows={3}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Assigned To</Label>
                                <Input
                                    value={taskForm.assigned_to}
                                    onChange={(e) => setTaskForm((f) => ({ ...f, assigned_to: e.target.value }))}
                                    placeholder="Employee ID"
                                />
                            </div>
                            <div>
                                <Label>Priority</Label>
                                <Select
                                    value={taskForm.priority}
                                    onValueChange={(v) => setTaskForm((f) => ({ ...f, priority: v }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label>Deadline</Label>
                            <Input
                                type="date"
                                value={taskForm.deadline}
                                onChange={(e) => setTaskForm((f) => ({ ...f, deadline: e.target.value }))}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowTaskDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => createTaskMut.mutate(taskForm)}
                            disabled={createTaskMut.isPending || !taskForm.title.trim()}
                        >
                            {createTaskMut.isPending ? 'Creating...' : 'Create Task'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

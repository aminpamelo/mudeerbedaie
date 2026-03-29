import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate, useLocation } from 'react-router-dom';
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
    MessageSquare,
    Brain,
    Users,
    ListChecks,
    ClipboardList,
    Gavel,
    Paperclip,
    AudioLines,
    Sparkles,
    FolderOpen,
    UserCheck,
    UserX,
    UserMinus,
    MoreVertical,
    AlertCircle,
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
import TaskList from '../../components/meetings/TaskList';
import RecordingPlayer from '../../components/meetings/RecordingPlayer';
import TranscriptViewer from '../../components/meetings/TranscriptViewer';
import AiSummaryPanel from '../../components/meetings/AiSummaryPanel';

// ─── Helpers ───

const STATUS_CONFIG = {
    draft: { label: 'Draft', className: 'bg-zinc-100 text-zinc-700 border-zinc-200', dot: 'bg-zinc-400' },
    scheduled: { label: 'Scheduled', className: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    in_progress: { label: 'In Progress', className: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500 animate-pulse' },
    completed: { label: 'Completed', className: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    cancelled: { label: 'Cancelled', className: 'bg-red-50 text-red-700 border-red-200', dot: 'bg-red-500' },
};

const ATTENDANCE_CONFIG = {
    invited: { label: 'Invited', className: 'bg-zinc-100 text-zinc-600', icon: null },
    attended: { label: 'Attended', className: 'bg-emerald-100 text-emerald-700', icon: UserCheck },
    absent: { label: 'Absent', className: 'bg-red-100 text-red-700', icon: UserX },
    excused: { label: 'Excused', className: 'bg-amber-100 text-amber-700', icon: UserMinus },
};

const PRIORITY_CONFIG = {
    low: { label: 'Low', className: 'bg-zinc-100 text-zinc-600' },
    medium: { label: 'Medium', className: 'bg-blue-100 text-blue-700' },
    high: { label: 'High', className: 'bg-orange-100 text-orange-700' },
    urgent: { label: 'Urgent', className: 'bg-red-100 text-red-700' },
};

const ROLE_CONFIG = {
    organizer: { label: 'Organizer', className: 'bg-indigo-100 text-indigo-700' },
    note_taker: { label: 'Note Taker', className: 'bg-violet-100 text-violet-700' },
    attendee: { label: 'Attendee', className: 'bg-zinc-100 text-zinc-600' },
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.slice(0, 5).split(':');
    const hour = parseInt(h, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12 = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

function StatusBadge({ status }) {
    const c = STATUS_CONFIG[status] || STATUS_CONFIG.draft;
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium ${c.className}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${c.dot}`} />
            {c.label}
        </span>
    );
}

// ─── Tab Definitions ───

const TABS = [
    { id: 'overview', label: 'Overview', icon: ClipboardList },
    { id: 'attendees', label: 'Attendees', icon: Users },
    { id: 'agenda', label: 'Agenda', icon: ListChecks },
    { id: 'decisions', label: 'Decisions', icon: Gavel },
    { id: 'tasks', label: 'Tasks', icon: CheckCircle2 },
    { id: 'recordings', label: 'Recordings & AI', icon: AudioLines },
    { id: 'attachments', label: 'Files', icon: Paperclip },
];

// ─── Main Component ───

export default function MeetingDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const location = useLocation();
    const queryClient = useQueryClient();

    const validTabs = TABS.map(t => t.id);
    const hashTab = location.hash.replace('#', '');
    const [activeTab, setActiveTab] = useState(validTabs.includes(hashTab) ? hashTab : 'overview');

    function handleTabChange(tab) {
        setActiveTab(tab);
        navigate(`#${tab}`, { replace: true });
    }
    const [showTaskDialog, setShowTaskDialog] = useState(false);
    const [taskForm, setTaskForm] = useState({ title: '', description: '', assigned_to: '', priority: 'medium', deadline: '' });
    const [attachFile, setAttachFile] = useState(null);
    const [recordFile, setRecordFile] = useState(null);
    const [uploadError, setUploadError] = useState(null);

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

    // ─── Mutations ───
    const statusMut = useMutation({ mutationFn: (data) => updateMeetingStatus(id, data), onSuccess: invalidate });
    const attendeeStatusMut = useMutation({
        mutationFn: ({ employeeId, status }) => updateAttendeeStatus(id, employeeId, { attendance_status: status }),
        onSuccess: invalidate,
    });
    const uploadAttachMut = useMutation({ mutationFn: (fd) => uploadMeetingAttachment(id, fd), onSuccess: () => { invalidate(); setAttachFile(null); setUploadError(null); }, onError: (err) => setUploadError(err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Upload failed') });
    const deleteAttachMut = useMutation({ mutationFn: (attId) => deleteMeetingAttachment(id, attId), onSuccess: invalidate });
    const uploadRecordMut = useMutation({ mutationFn: (fd) => uploadRecording(id, fd), onSuccess: () => { invalidate(); setRecordFile(null); setUploadError(null); }, onError: (err) => setUploadError(err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Upload failed') });
    const deleteRecordMut = useMutation({ mutationFn: (recId) => deleteRecording(id, recId), onSuccess: invalidate });
    const transcribeMut = useMutation({ mutationFn: (recId) => triggerTranscription(id, recId), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id, 'transcript'] }) });
    const aiAnalyzeMut = useMutation({ mutationFn: () => triggerAiAnalysis(id), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id, 'ai-summary'] }) });
    const approveTasksMut = useMutation({ mutationFn: (data) => approveAiTasks(id, data), onSuccess: invalidate });
    const createTaskMut = useMutation({
        mutationFn: (data) => createMeetingTask(id, data),
        onSuccess: () => { invalidate(); setShowTaskDialog(false); setTaskForm({ title: '', description: '', assigned_to: '', priority: 'medium', deadline: '' }); },
    });

    // ─── Agenda mutations ───
    const [agendaAdding, setAgendaAdding] = useState(false);
    const [agendaEditingId, setAgendaEditingId] = useState(null);
    const [agendaForm, setAgendaForm] = useState({ title: '', description: '' });

    const addAgendaMut = useMutation({ mutationFn: (data) => addAgendaItem(id, data), onSuccess: () => { invalidate(); setAgendaAdding(false); setAgendaForm({ title: '', description: '' }); } });
    const updateAgendaMut = useMutation({ mutationFn: ({ itemId, data }) => updateAgendaItem(id, itemId, data), onSuccess: () => { invalidate(); setAgendaEditingId(null); setAgendaForm({ title: '', description: '' }); } });
    const deleteAgendaMut = useMutation({ mutationFn: (itemId) => deleteAgendaItem(id, itemId), onSuccess: invalidate });

    // ─── Decision mutations ───
    const [decisionAdding, setDecisionAdding] = useState(false);
    const [decisionEditingId, setDecisionEditingId] = useState(null);
    const [decisionForm, setDecisionForm] = useState({ title: '', description: '', decided_by: '' });

    const addDecisionMut = useMutation({ mutationFn: (data) => addDecision(id, data), onSuccess: () => { invalidate(); setDecisionAdding(false); setDecisionForm({ title: '', description: '', decided_by: '' }); } });
    const updateDecisionMut = useMutation({ mutationFn: ({ decId, data }) => updateDecision(id, decId, data), onSuccess: () => { invalidate(); setDecisionEditingId(null); } });
    const deleteDecisionMut = useMutation({ mutationFn: (decId) => deleteDecision(id, decId), onSuccess: invalidate });

    if (isLoading || !meeting) {
        return (
            <div className="flex h-[60vh] items-center justify-center">
                <div className="flex flex-col items-center gap-3">
                    <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                    <p className="text-sm text-zinc-500">Loading meeting details...</p>
                </div>
            </div>
        );
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

    // Count stats
    const attendedCount = attendees.filter(a => a.attendance_status === 'attended').length;
    const pendingTasks = tasks.filter(t => t.status === 'pending').length;
    const completedTasks = tasks.filter(t => t.status === 'completed').length;

    function getTabCount(tabId) {
        switch (tabId) {
            case 'attendees': return attendees.length;
            case 'agenda': return agendaItems.length;
            case 'decisions': return decisions.length;
            case 'tasks': return tasks.length;
            case 'recordings': return recordings.length;
            case 'attachments': return attachments.length;
            default: return null;
        }
    }

    function handleAttachUpload() {
        if (!attachFile) return;
        const fd = new FormData();
        fd.append('file', attachFile);
        uploadAttachMut.mutate(fd);
    }

    function handleRecordUpload() {
        if (!recordFile) return;
        const maxSize = 500 * 1024 * 1024; // 500MB
        if (recordFile.size > maxSize) {
            setUploadError(`File is too large (${(recordFile.size / (1024 * 1024)).toFixed(0)} MB). Maximum allowed is 500 MB.`);
            return;
        }
        setUploadError(null);
        const fd = new FormData();
        fd.append('file', recordFile);
        uploadRecordMut.mutate(fd);
    }

    return (
        <div className="mx-auto max-w-6xl">
            {/* ─── Top Navigation ─── */}
            <div className="mb-6">
                <button
                    onClick={() => navigate('/meetings')}
                    className="group mb-4 inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900"
                >
                    <ArrowLeft className="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                    Back to Meetings
                </button>

                {/* Title + Actions */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                                {meeting.title}
                            </h1>
                            <StatusBadge status={meeting.status} />
                        </div>
                        {meeting.series && (
                            <div className="flex items-center gap-1.5 text-sm text-zinc-500">
                                <FolderOpen className="h-3.5 w-3.5" />
                                <span>Series: {meeting.series.name}</span>
                            </div>
                        )}
                    </div>

                    <div className="flex shrink-0 flex-wrap gap-2">
                        <Button variant="outline" size="sm" onClick={() => navigate(`/meetings/${id}/edit`)}>
                            <Pencil className="mr-1.5 h-3.5 w-3.5" />
                            Edit
                        </Button>
                        {canStart && (
                            <Button size="sm" onClick={() => statusMut.mutate({ status: 'in_progress' })} disabled={statusMut.isPending}>
                                <Play className="mr-1.5 h-3.5 w-3.5" />
                                Start Meeting
                            </Button>
                        )}
                        {canComplete && (
                            <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700" onClick={() => statusMut.mutate({ status: 'completed' })} disabled={statusMut.isPending}>
                                <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
                                Complete
                            </Button>
                        )}
                        {canCancel && (
                            <Button variant="outline" size="sm" className="border-red-200 text-red-600 hover:bg-red-50" onClick={() => statusMut.mutate({ status: 'cancelled' })} disabled={statusMut.isPending}>
                                <XCircle className="mr-1.5 h-3.5 w-3.5" />
                                Cancel
                            </Button>
                        )}
                        <Button variant="outline" size="sm" onClick={() => navigate(`/meetings/${id}/record`)}>
                            <Mic className="mr-1.5 h-3.5 w-3.5" />
                            Record
                        </Button>
                    </div>
                </div>
            </div>

            {/* ─── Meeting Info Cards ─── */}
            <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-xl border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-2 text-zinc-400">
                        <Calendar className="h-4 w-4" />
                        <span className="text-xs font-medium uppercase tracking-wider">Date</span>
                    </div>
                    <p className="mt-1.5 text-sm font-semibold text-zinc-900">{formatDate(meeting.meeting_date)}</p>
                </div>
                <div className="rounded-xl border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-2 text-zinc-400">
                        <Clock className="h-4 w-4" />
                        <span className="text-xs font-medium uppercase tracking-wider">Time</span>
                    </div>
                    <p className="mt-1.5 text-sm font-semibold text-zinc-900">
                        {formatTime(meeting.start_time)}
                        {meeting.end_time && ` - ${formatTime(meeting.end_time)}`}
                    </p>
                </div>
                <div className="rounded-xl border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-2 text-zinc-400">
                        <MapPin className="h-4 w-4" />
                        <span className="text-xs font-medium uppercase tracking-wider">Location</span>
                    </div>
                    <p className="mt-1.5 text-sm font-semibold text-zinc-900">{meeting.location || 'Not set'}</p>
                </div>
                <div className="rounded-xl border border-zinc-200 bg-white p-4">
                    <div className="flex items-center gap-2 text-zinc-400">
                        <Users className="h-4 w-4" />
                        <span className="text-xs font-medium uppercase tracking-wider">Attendees</span>
                    </div>
                    <p className="mt-1.5 text-sm font-semibold text-zinc-900">
                        {attendees.length} invited
                        {attendedCount > 0 && <span className="text-emerald-600"> ({attendedCount} attended)</span>}
                    </p>
                </div>
            </div>

            {/* Description */}
            {meeting.description && (
                <div className="mb-6 rounded-xl border border-zinc-200 bg-white p-4">
                    <p className="text-sm leading-relaxed text-zinc-600">{meeting.description}</p>
                </div>
            )}

            {/* ─── Organizer & Note Taker ─── */}
            <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {meeting.organizer && (
                    <div className="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                            {getInitials(meeting.organizer.full_name)}
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-zinc-400">Organizer</p>
                            <p className="text-sm font-semibold text-zinc-900">{meeting.organizer.full_name}</p>
                        </div>
                    </div>
                )}
                {meeting.note_taker && (
                    <div className="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700">
                            {getInitials(meeting.note_taker.full_name)}
                        </div>
                        <div>
                            <p className="text-xs font-medium uppercase tracking-wider text-zinc-400">Note Taker</p>
                            <p className="text-sm font-semibold text-zinc-900">{meeting.note_taker.full_name}</p>
                        </div>
                    </div>
                )}
            </div>

            {/* ─── Tab Navigation ─── */}
            <div className="mb-6 overflow-x-auto border-b border-zinc-200">
                <nav className="flex gap-0.5">
                    {TABS.map((tab) => {
                        const count = getTabCount(tab.id);
                        const isActive = activeTab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                onClick={() => handleTabChange(tab.id)}
                                className={`group relative flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium transition-colors
                                    ${isActive
                                        ? 'text-zinc-900'
                                        : 'text-zinc-500 hover:text-zinc-700'
                                    }`}
                            >
                                <tab.icon className={`h-4 w-4 ${isActive ? 'text-zinc-700' : 'text-zinc-400 group-hover:text-zinc-500'}`} />
                                {tab.label}
                                {count !== null && count > 0 && (
                                    <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold leading-none
                                        ${isActive ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-500'}`}>
                                        {count}
                                    </span>
                                )}
                                {isActive && (
                                    <span className="absolute bottom-0 left-0 right-0 h-0.5 rounded-t-full bg-zinc-900" />
                                )}
                            </button>
                        );
                    })}
                </nav>
            </div>

            {/* ─── Tab Content ─── */}
            <div className="min-h-[400px]">
                {/* Overview Tab */}
                {activeTab === 'overview' && (
                    <div className="space-y-6">
                        {/* Quick Stats */}
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div className="rounded-xl border border-zinc-200 bg-white p-4 text-center">
                                <p className="text-2xl font-bold text-zinc-900">{agendaItems.length}</p>
                                <p className="text-xs text-zinc-500">Agenda Items</p>
                            </div>
                            <div className="rounded-xl border border-zinc-200 bg-white p-4 text-center">
                                <p className="text-2xl font-bold text-zinc-900">{decisions.length}</p>
                                <p className="text-xs text-zinc-500">Decisions</p>
                            </div>
                            <div className="rounded-xl border border-zinc-200 bg-white p-4 text-center">
                                <p className="text-2xl font-bold text-emerald-600">{completedTasks}</p>
                                <p className="text-xs text-zinc-500">Tasks Completed</p>
                            </div>
                            <div className="rounded-xl border border-zinc-200 bg-white p-4 text-center">
                                <p className="text-2xl font-bold text-amber-600">{pendingTasks}</p>
                                <p className="text-xs text-zinc-500">Tasks Pending</p>
                            </div>
                        </div>

                        {/* Agenda Preview */}
                        {agendaItems.length > 0 && (
                            <div className="rounded-xl border border-zinc-200 bg-white">
                                <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                                    <h3 className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                        <ListChecks className="h-4 w-4 text-zinc-400" />
                                        Agenda
                                    </h3>
                                    <button onClick={() => handleTabChange('agenda')} className="text-xs font-medium text-blue-600 hover:text-blue-700">
                                        View All
                                    </button>
                                </div>
                                <div className="divide-y divide-zinc-50 px-5">
                                    {agendaItems.slice(0, 5).map((item, index) => (
                                        <div key={item.id} className="flex items-start gap-3 py-3">
                                            <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-[10px] font-bold text-zinc-500">
                                                {index + 1}
                                            </span>
                                            <div>
                                                <p className="text-sm font-medium text-zinc-900">{item.title}</p>
                                                {item.description && <p className="mt-0.5 text-xs text-zinc-500">{item.description}</p>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Recent Decisions Preview */}
                        {decisions.length > 0 && (
                            <div className="rounded-xl border border-zinc-200 bg-white">
                                <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                                    <h3 className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                        <Gavel className="h-4 w-4 text-zinc-400" />
                                        Key Decisions
                                    </h3>
                                    <button onClick={() => handleTabChange('decisions')} className="text-xs font-medium text-blue-600 hover:text-blue-700">
                                        View All
                                    </button>
                                </div>
                                <div className="divide-y divide-zinc-50 px-5">
                                    {decisions.slice(0, 3).map((dec) => (
                                        <div key={dec.id} className="py-3">
                                            <div className="flex items-start gap-2">
                                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">{dec.title}</p>
                                                    {dec.decided_by?.full_name && (
                                                        <p className="mt-0.5 text-xs text-zinc-500">
                                                            by {dec.decided_by.full_name}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Tasks Preview */}
                        {tasks.length > 0 && (
                            <div className="rounded-xl border border-zinc-200 bg-white">
                                <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                                    <h3 className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                        <CheckCircle2 className="h-4 w-4 text-zinc-400" />
                                        Action Items
                                    </h3>
                                    <button onClick={() => handleTabChange('tasks')} className="text-xs font-medium text-blue-600 hover:text-blue-700">
                                        View All
                                    </button>
                                </div>
                                <div className="divide-y divide-zinc-50 px-5">
                                    {tasks.slice(0, 5).map((task) => {
                                        const pConfig = PRIORITY_CONFIG[task.priority] || PRIORITY_CONFIG.medium;
                                        const isOverdue = task.deadline && new Date(task.deadline) < new Date() && task.status !== 'completed' && task.status !== 'cancelled';
                                        return (
                                            <div key={task.id} className="flex items-center justify-between py-3">
                                                <div className="flex items-center gap-3">
                                                    <span className={`inline-flex h-2 w-2 rounded-full ${task.status === 'completed' ? 'bg-emerald-500' : task.status === 'in_progress' ? 'bg-blue-500' : 'bg-zinc-300'}`} />
                                                    <div>
                                                        <p className="text-sm font-medium text-zinc-900">{task.title}</p>
                                                        <div className="mt-0.5 flex items-center gap-2">
                                                            {task.assignee && <span className="text-xs text-zinc-500">{task.assignee.full_name}</span>}
                                                            <span className={`rounded px-1.5 py-0.5 text-[10px] font-medium ${pConfig.className}`}>{pConfig.label}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                {task.deadline && (
                                                    <span className={`text-xs ${isOverdue ? 'font-medium text-red-600' : 'text-zinc-500'}`}>
                                                        {isOverdue && <AlertCircle className="mr-1 inline h-3 w-3" />}
                                                        {formatDate(task.deadline)}
                                                    </span>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Attendees Tab */}
                {activeTab === 'attendees' && (
                    <div className="rounded-xl border border-zinc-200 bg-white">
                        <div className="border-b border-zinc-100 px-5 py-3">
                            <h3 className="text-sm font-semibold text-zinc-900">
                                {attendees.length} Attendees
                                {attendedCount > 0 && (
                                    <span className="ml-2 text-xs font-normal text-zinc-500">
                                        ({attendedCount} attended, {attendees.filter(a => a.attendance_status === 'absent').length} absent)
                                    </span>
                                )}
                            </h3>
                        </div>
                        <div className="divide-y divide-zinc-50">
                            {attendees.map((att) => {
                                const employee = att.employee || att;
                                const empId = att.employee_id || att.id;
                                const role = att.role || 'attendee';
                                const roleConf = ROLE_CONFIG[role] || ROLE_CONFIG.attendee;
                                const attStatus = att.attendance_status || 'invited';
                                const attConf = ATTENDANCE_CONFIG[attStatus] || ATTENDANCE_CONFIG.invited;

                                return (
                                    <div key={empId} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-zinc-50/50">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-zinc-200 to-zinc-300 text-xs font-bold text-zinc-600">
                                                {employee.profile_photo_url ? (
                                                    <img src={employee.profile_photo_url} alt={employee.full_name} className="h-10 w-10 rounded-full object-cover" />
                                                ) : (
                                                    getInitials(employee.full_name || employee.name)
                                                )}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-sm font-medium text-zinc-900">{employee.full_name || employee.name}</p>
                                                    <span className={`rounded px-1.5 py-0.5 text-[10px] font-medium ${roleConf.className}`}>
                                                        {roleConf.label}
                                                    </span>
                                                </div>
                                                {employee.position?.title && (
                                                    <p className="text-xs text-zinc-500">{employee.position.title}</p>
                                                )}
                                            </div>
                                        </div>
                                        <Select
                                            value={attStatus}
                                            onValueChange={(v) => attendeeStatusMut.mutate({ employeeId: empId, status: v })}
                                        >
                                            <SelectTrigger className={`h-8 w-[120px] border-0 text-xs font-medium ${attConf.className}`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="invited">Invited</SelectItem>
                                                <SelectItem value="attended">Attended</SelectItem>
                                                <SelectItem value="absent">Absent</SelectItem>
                                                <SelectItem value="excused">Excused</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                );
                            })}
                            {attendees.length === 0 && (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <Users className="h-10 w-10 text-zinc-300" />
                                    <p className="mt-2 text-sm font-medium text-zinc-500">No attendees yet</p>
                                    <p className="text-xs text-zinc-400">Edit the meeting to add attendees</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Agenda Tab */}
                {activeTab === 'agenda' && (
                    <div className="rounded-xl border border-zinc-200 bg-white">
                        <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                            <h3 className="text-sm font-semibold text-zinc-900">Agenda Items</h3>
                            <Button size="sm" variant="outline" onClick={() => { setAgendaAdding(true); setAgendaForm({ title: '', description: '' }); }}>
                                <Plus className="mr-1.5 h-3.5 w-3.5" />
                                Add Item
                            </Button>
                        </div>
                        <div className="divide-y divide-zinc-50">
                            {agendaItems.map((item, index) => (
                                <div key={item.id} className="group px-5 py-3 transition-colors hover:bg-zinc-50/50">
                                    {agendaEditingId === item.id ? (
                                        <div className="space-y-2">
                                            <Input value={agendaForm.title} onChange={(e) => setAgendaForm(f => ({ ...f, title: e.target.value }))} placeholder="Agenda item title" />
                                            <Textarea value={agendaForm.description} onChange={(e) => setAgendaForm(f => ({ ...f, description: e.target.value }))} placeholder="Description (optional)" rows={2} />
                                            <div className="flex gap-2">
                                                <Button size="sm" onClick={() => updateAgendaMut.mutate({ itemId: item.id, data: agendaForm })} disabled={updateAgendaMut.isPending || !agendaForm.title.trim()}>Save</Button>
                                                <Button size="sm" variant="ghost" onClick={() => setAgendaEditingId(null)}>Cancel</Button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-start gap-3">
                                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white">
                                                    {index + 1}
                                                </span>
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">{item.title}</p>
                                                    {item.description && <p className="mt-0.5 text-xs text-zinc-500">{item.description}</p>}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => { setAgendaEditingId(item.id); setAgendaForm({ title: item.title, description: item.description || '' }); }}>
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => deleteAgendaMut.mutate(item.id)}>
                                                    <Trash2 className="h-3.5 w-3.5 text-red-500" />
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {agendaItems.length === 0 && !agendaAdding && (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <ListChecks className="h-10 w-10 text-zinc-300" />
                                    <p className="mt-2 text-sm font-medium text-zinc-500">No agenda items yet</p>
                                    <p className="text-xs text-zinc-400">Add items to structure your meeting</p>
                                </div>
                            )}
                        </div>
                        {agendaAdding && (
                            <div className="border-t border-zinc-100 px-5 py-4">
                                <div className="space-y-2">
                                    <Input value={agendaForm.title} onChange={(e) => setAgendaForm(f => ({ ...f, title: e.target.value }))} placeholder="New agenda item title" autoFocus />
                                    <Textarea value={agendaForm.description} onChange={(e) => setAgendaForm(f => ({ ...f, description: e.target.value }))} placeholder="Description (optional)" rows={2} />
                                    <div className="flex gap-2">
                                        <Button size="sm" onClick={() => addAgendaMut.mutate(agendaForm)} disabled={addAgendaMut.isPending || !agendaForm.title.trim()}>Add</Button>
                                        <Button size="sm" variant="ghost" onClick={() => setAgendaAdding(false)}>Cancel</Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Decisions Tab */}
                {activeTab === 'decisions' && (
                    <div className="rounded-xl border border-zinc-200 bg-white">
                        <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                            <h3 className="text-sm font-semibold text-zinc-900">Decisions</h3>
                            <Button size="sm" variant="outline" onClick={() => { setDecisionAdding(true); setDecisionForm({ title: '', description: '', decided_by: '' }); }}>
                                <Plus className="mr-1.5 h-3.5 w-3.5" />
                                Add Decision
                            </Button>
                        </div>
                        <div className="divide-y divide-zinc-50">
                            {decisions.map((dec) => (
                                <div key={dec.id} className="group px-5 py-4 transition-colors hover:bg-zinc-50/50">
                                    {decisionEditingId === dec.id ? (
                                        <div className="space-y-2">
                                            <Input value={decisionForm.title} onChange={(e) => setDecisionForm(f => ({ ...f, title: e.target.value }))} placeholder="Decision title" />
                                            <Textarea value={decisionForm.description} onChange={(e) => setDecisionForm(f => ({ ...f, description: e.target.value }))} placeholder="Details" rows={2} />
                                            <div className="flex gap-2">
                                                <Button size="sm" onClick={() => updateDecisionMut.mutate({ decId: dec.id, data: decisionForm })} disabled={updateDecisionMut.isPending || !decisionForm.title.trim()}>Save</Button>
                                                <Button size="sm" variant="ghost" onClick={() => setDecisionEditingId(null)}>Cancel</Button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100">
                                                    <Gavel className="h-3.5 w-3.5 text-emerald-700" />
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">{dec.title}</p>
                                                    {dec.description && <p className="mt-0.5 text-xs leading-relaxed text-zinc-500">{dec.description}</p>}
                                                    <div className="mt-1.5 flex items-center gap-3 text-[11px] text-zinc-400">
                                                        {(dec.decided_by?.full_name) && (
                                                            <span>by {dec.decided_by.full_name}</span>
                                                        )}
                                                        {dec.decided_at && (
                                                            <span>{formatDate(dec.decided_at)}</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => { setDecisionEditingId(dec.id); setDecisionForm({ title: dec.title || '', description: dec.description || '', decided_by: dec.decided_by || '' }); }}>
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => deleteDecisionMut.mutate(dec.id)}>
                                                    <Trash2 className="h-3.5 w-3.5 text-red-500" />
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {decisions.length === 0 && !decisionAdding && (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <Gavel className="h-10 w-10 text-zinc-300" />
                                    <p className="mt-2 text-sm font-medium text-zinc-500">No decisions recorded</p>
                                    <p className="text-xs text-zinc-400">Record important decisions made during the meeting</p>
                                </div>
                            )}
                        </div>
                        {decisionAdding && (
                            <div className="border-t border-zinc-100 px-5 py-4">
                                <div className="space-y-2">
                                    <Input value={decisionForm.title} onChange={(e) => setDecisionForm(f => ({ ...f, title: e.target.value }))} placeholder="Decision title" autoFocus />
                                    <Textarea value={decisionForm.description} onChange={(e) => setDecisionForm(f => ({ ...f, description: e.target.value }))} placeholder="Details" rows={2} />
                                    <div className="flex gap-2">
                                        <Button size="sm" onClick={() => addDecisionMut.mutate(decisionForm)} disabled={addDecisionMut.isPending || !decisionForm.title.trim()}>Add</Button>
                                        <Button size="sm" variant="ghost" onClick={() => setDecisionAdding(false)}>Cancel</Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Tasks Tab */}
                {activeTab === 'tasks' && (
                    <div className="rounded-xl border border-zinc-200 bg-white">
                        <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                            <h3 className="text-sm font-semibold text-zinc-900">
                                Action Items
                                {tasks.length > 0 && (
                                    <span className="ml-2 text-xs font-normal text-zinc-500">
                                        {completedTasks}/{tasks.length} completed
                                    </span>
                                )}
                            </h3>
                            <Button size="sm" variant="outline" onClick={() => setShowTaskDialog(true)}>
                                <Plus className="mr-1.5 h-3.5 w-3.5" />
                                Add Task
                            </Button>
                        </div>
                        <div className="p-5">
                            <TaskList meetingId={id} tasks={tasks} onUpdate={invalidate} />
                        </div>
                    </div>
                )}

                {/* Recordings & AI Tab */}
                {activeTab === 'recordings' && (
                    <div className="space-y-4">
                        {/* Upload Section */}
                        <div className="rounded-xl border border-zinc-200 bg-white">
                            <div className="border-b border-zinc-100 px-5 py-3">
                                <h3 className="text-sm font-semibold text-zinc-900">Upload Recording</h3>
                            </div>
                            <div className="flex flex-wrap items-center gap-3 px-5 py-4">
                                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-zinc-300 px-4 py-2.5 text-sm text-zinc-600 transition-colors hover:border-zinc-400 hover:bg-zinc-50">
                                    <Upload className="h-4 w-4" />
                                    {recordFile ? recordFile.name : 'Choose file...'}
                                    <input type="file" accept="audio/*,video/*" onChange={(e) => setRecordFile(e.target.files?.[0] || null)} className="hidden" />
                                </label>
                                {recordFile && (
                                    <Button size="sm" onClick={handleRecordUpload} disabled={uploadRecordMut.isPending}>
                                        {uploadRecordMut.isPending ? <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> Uploading...</> : <><Upload className="mr-1.5 h-3.5 w-3.5" /> Upload</>}
                                    </Button>
                                )}
                                <Button size="sm" variant="outline" onClick={() => navigate(`/meetings/${id}/record`)}>
                                    <Mic className="mr-1.5 h-3.5 w-3.5" />
                                    Record in Browser
                                </Button>
                            </div>
                            {uploadError && (
                                <div className="mt-3 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                                    <AlertCircle className="h-4 w-4 shrink-0" />
                                    {uploadError}
                                    <button onClick={() => setUploadError(null)} className="ml-auto text-red-500 hover:text-red-700">&times;</button>
                                </div>
                            )}
                        </div>

                        {/* Recordings List */}
                        {recordings.length > 0 && (
                            <div className="rounded-xl border border-zinc-200 bg-white">
                                <div className="border-b border-zinc-100 px-5 py-3">
                                    <h3 className="text-sm font-semibold text-zinc-900">Recordings ({recordings.length})</h3>
                                </div>
                                <div className="space-y-3 p-5">
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
                            </div>
                        )}

                        {/* Transcript */}
                        {transcript && (
                            <div className="rounded-xl border border-zinc-200 bg-white p-5">
                                <TranscriptViewer transcript={transcript} />
                            </div>
                        )}

                        {/* AI Analysis */}
                        <div className="rounded-xl border border-zinc-200 bg-white">
                            <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                                <h3 className="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                                    <Sparkles className="h-4 w-4 text-violet-500" />
                                    AI Analysis
                                </h3>
                                <Button size="sm" variant="outline" onClick={() => aiAnalyzeMut.mutate()} disabled={aiAnalyzeMut.isPending}>
                                    <Brain className="mr-1.5 h-3.5 w-3.5" />
                                    {aiAnalyzeMut.isPending ? 'Analyzing...' : 'Analyze with AI'}
                                </Button>
                            </div>
                            <div className="p-5">
                                {aiSummary ? (
                                    <AiSummaryPanel summary={aiSummary} onApproveTasks={(data) => approveTasksMut.mutate(data)} approving={approveTasksMut.isPending} />
                                ) : (
                                    <div className="flex flex-col items-center justify-center py-8 text-center">
                                        <Sparkles className="h-10 w-10 text-zinc-300" />
                                        <p className="mt-2 text-sm font-medium text-zinc-500">No AI analysis yet</p>
                                        <p className="text-xs text-zinc-400">Upload a recording or transcript, then run AI analysis</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Attachments Tab */}
                {activeTab === 'attachments' && (
                    <div className="rounded-xl border border-zinc-200 bg-white">
                        <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3">
                            <h3 className="text-sm font-semibold text-zinc-900">Files & Attachments</h3>
                        </div>

                        {/* Upload area */}
                        <div className="border-b border-zinc-100 px-5 py-4">
                            <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 py-6 transition-colors hover:border-zinc-400 hover:bg-zinc-100">
                                <Upload className="h-8 w-8 text-zinc-400" />
                                <p className="mt-2 text-sm font-medium text-zinc-600">
                                    {attachFile ? attachFile.name : 'Click to upload a file'}
                                </p>
                                <p className="text-xs text-zinc-400">Documents, images, and other files</p>
                                <input type="file" onChange={(e) => setAttachFile(e.target.files?.[0] || null)} className="hidden" />
                            </label>
                            {attachFile && (
                                <div className="mt-3 flex justify-end">
                                    <Button size="sm" onClick={handleAttachUpload} disabled={uploadAttachMut.isPending}>
                                        {uploadAttachMut.isPending ? <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> Uploading...</> : <><Upload className="mr-1.5 h-3.5 w-3.5" /> Upload File</>}
                                    </Button>
                                </div>
                            )}
                        </div>

                        {/* File list */}
                        <div className="divide-y divide-zinc-50">
                            {attachments.map((att) => (
                                <div key={att.id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-zinc-50/50">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100">
                                            <FileText className="h-5 w-5 text-zinc-500" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-zinc-900">{att.original_name || att.file_name || 'File'}</p>
                                            <p className="text-xs text-zinc-500">
                                                {att.file_size ? `${(att.file_size / 1024).toFixed(1)} KB` : ''}
                                                {att.uploader && ` · ${att.uploader.full_name || att.uploader.name}`}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {att.url && (
                                            <a href={att.url} target="_blank" rel="noopener noreferrer">
                                                <Button variant="ghost" size="icon" className="h-8 w-8">
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                            </a>
                                        )}
                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => deleteAttachMut.mutate(att.id)}>
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {attachments.length === 0 && (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <Paperclip className="h-10 w-10 text-zinc-300" />
                                    <p className="mt-2 text-sm font-medium text-zinc-500">No attachments yet</p>
                                    <p className="text-xs text-zinc-400">Upload documents and files related to this meeting</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* ─── Add Task Dialog ─── */}
            <Dialog open={showTaskDialog} onOpenChange={setShowTaskDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Action Item</DialogTitle>
                        <DialogDescription>Add a new task from this meeting.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Title *</Label>
                            <Input value={taskForm.title} onChange={(e) => setTaskForm(f => ({ ...f, title: e.target.value }))} placeholder="What needs to be done?" />
                        </div>
                        <div>
                            <Label>Description</Label>
                            <Textarea value={taskForm.description} onChange={(e) => setTaskForm(f => ({ ...f, description: e.target.value }))} placeholder="Additional details..." rows={3} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Assign To (Employee ID)</Label>
                                <Input value={taskForm.assigned_to} onChange={(e) => setTaskForm(f => ({ ...f, assigned_to: e.target.value }))} placeholder="Employee ID" />
                            </div>
                            <div>
                                <Label>Priority</Label>
                                <Select value={taskForm.priority} onValueChange={(v) => setTaskForm(f => ({ ...f, priority: v }))}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
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
                            <Input type="date" value={taskForm.deadline} onChange={(e) => setTaskForm(f => ({ ...f, deadline: e.target.value }))} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowTaskDialog(false)}>Cancel</Button>
                        <Button onClick={() => createTaskMut.mutate(taskForm)} disabled={createTaskMut.isPending || !taskForm.title.trim()}>
                            {createTaskMut.isPending ? 'Creating...' : 'Create Task'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

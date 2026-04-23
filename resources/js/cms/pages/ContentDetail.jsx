import { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft,
    Pencil,
    MoreHorizontal,
    Trash2,
    ChevronRight,
    ChevronDown,
    Calendar,
    User,
    Megaphone,
    Flag,
    BarChart3,
    Clock,
    X,
    UserPlus,
    Link as LinkIcon,
    ExternalLink,
    Users,
} from 'lucide-react';
import {
    fetchContent,
    updateContent,
    updateContentStage,
    addContentStats,
    markContentForAds,
    deleteContent,
    addStageAssignee,
    removeStageAssignee,
    updateStageDueDate,
    updateStageMeta,
    fetchContentCreators,
} from '../lib/api';
import { cn } from '../lib/utils';
import { toastSuccess, toastError } from '../lib/toast';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Textarea } from '../components/ui/textarea';
import { Label } from '../components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '../components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '../components/ui/avatar';
import StageTimeline from '../components/StageTimeline';
import StatsCard from '../components/StatsCard';
import AssigneePicker from '../components/AssigneePicker';

const STAGES = ['idea', 'shooting', 'editing', 'posting', 'posted'];

const STAGE_LABELS = {
    idea: 'Idea',
    shooting: 'Shooting',
    editing: 'Editing',
    posting: 'Posting',
    posted: 'Posted',
};

const STAGE_COLORS = {
    idea: { badge: 'bg-blue-100 text-blue-800', border: 'border-blue-400', text: 'text-blue-600' },
    shooting: { badge: 'bg-purple-100 text-purple-800', border: 'border-purple-400', text: 'text-purple-600' },
    editing: { badge: 'bg-amber-100 text-amber-800', border: 'border-amber-400', text: 'text-amber-600' },
    posting: { badge: 'bg-emerald-100 text-emerald-800', border: 'border-emerald-400', text: 'text-emerald-600' },
    posted: { badge: 'bg-green-100 text-green-800', border: 'border-green-400', text: 'text-green-600' },
};

const PRIORITY_COLORS = {
    low: 'bg-slate-100 text-slate-700',
    medium: 'bg-blue-100 text-blue-700',
    high: 'bg-amber-100 text-amber-700',
    urgent: 'bg-rose-100 text-rose-700',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const STATUS_LABELS = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
};

function formatStatus(status) {
    return STATUS_LABELS[status] || status;
}

function getAssigneeName(assignee) {
    return assignee.employee?.full_name || assignee.full_name || assignee.name || null;
}

function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

/**
 * Convert a Google Drive share URL into an embeddable /preview URL.
 * Supports formats:
 *   - https://drive.google.com/file/d/FILE_ID/view?usp=sharing
 *   - https://drive.google.com/file/d/FILE_ID/edit
 *   - https://drive.google.com/open?id=FILE_ID
 *   - https://drive.google.com/uc?id=FILE_ID
 */
function getGoogleDriveEmbedUrl(url) {
    if (!url || typeof url !== 'string') return null;
    if (!/drive\.google\.com/i.test(url)) return null;

    // /file/d/{id}/...
    const fileMatch = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
    if (fileMatch) return `https://drive.google.com/file/d/${fileMatch[1]}/preview`;

    // ?id={id} or &id={id}
    const idMatch = url.match(/[?&]id=([a-zA-Z0-9_-]+)/);
    if (idMatch) return `https://drive.google.com/file/d/${idMatch[1]}/preview`;

    return null;
}

/**
 * Convert a TikTok video URL into the official embed player URL.
 * Supports formats:
 *   - https://www.tiktok.com/@username/video/1234567890
 *   - https://www.tiktok.com/video/1234567890
 *   - https://m.tiktok.com/v/1234567890.html
 */
function getTiktokEmbedUrl(url) {
    if (!url || typeof url !== 'string') return null;
    if (!/tiktok\.com/i.test(url)) return null;

    // /video/{id}
    const videoMatch = url.match(/\/video\/(\d+)/);
    if (videoMatch) return `https://www.tiktok.com/player/v1/${videoMatch[1]}`;

    // /v/{id}.html (mobile share)
    const mobileMatch = url.match(/\/v\/(\d+)\.html/);
    if (mobileMatch) return `https://www.tiktok.com/player/v1/${mobileMatch[1]}`;

    return null;
}

export default function ContentDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [expandedStage, setExpandedStage] = useState(null);
    const [moveStageOpen, setMoveStageOpen] = useState(false);
    const [nextStage, setNextStage] = useState('');
    const [addStatsOpen, setAddStatsOpen] = useState(false);
    const [statsForm, setStatsForm] = useState({ views: '', likes: '', comments: '', shares: '' });
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [videoForm, setVideoForm] = useState({ video_url: '', tiktok_url: '' });

    const { data: content, isLoading } = useQuery({
        queryKey: ['cms', 'content', id],
        queryFn: () => fetchContent(id),
    });

    const contentData = content?.data || content;
    const currentStage = contentData?.current_stage || contentData?.stage || 'idea';

    const { data: contentCreators = [] } = useQuery({
        queryKey: ['content-creators', id],
        queryFn: () => fetchContentCreators(id).then((r) => r.data),
        enabled: currentStage === 'posted',
    });

    useEffect(() => {
        if (contentData) {
            setVideoForm({
                video_url: contentData.video_url || '',
                tiktok_url: contentData.tiktok_url || '',
            });
        }
    }, [content]);

    const moveStageMutation = useMutation({
        mutationFn: (data) => updateContentStage(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            queryClient.invalidateQueries({ queryKey: ['cms', 'contents'] });
            setMoveStageOpen(false);
            setNextStage('');
            toastSuccess('Stage updated');
        },
        onError: (error) => toastError(error, 'Failed to update stage'),
    });

    const addStatsMutation = useMutation({
        mutationFn: (data) => addContentStats(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            setAddStatsOpen(false);
            setStatsForm({ views: '', likes: '', comments: '', shares: '' });
            toastSuccess('Stats added');
        },
        onError: (error) => toastError(error, 'Failed to add stats'),
    });

    const markAdsMutation = useMutation({
        mutationFn: () => markContentForAds(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            toastSuccess('Marked for ads');
        },
        onError: (error) => toastError(error, 'Failed to update ads mark'),
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteContent(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'contents'] });
            toastSuccess('Content deleted');
            navigate('/contents');
        },
        onError: (error) => toastError(error, 'Failed to delete content'),
    });

    const addAssigneeMutation = useMutation({
        mutationFn: ({ stage, employee_id, role }) =>
            addStageAssignee(id, stage, { employee_id, role }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            toastSuccess('Assignee added');
        },
        onError: (error) => toastError(error, 'Failed to add assignee'),
    });

    const removeAssigneeMutation = useMutation({
        mutationFn: ({ stage, employeeId }) =>
            removeStageAssignee(id, stage, employeeId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            toastSuccess('Assignee removed');
        },
        onError: (error) => toastError(error, 'Failed to remove assignee'),
    });

    const updateVideoMutation = useMutation({
        mutationFn: (data) => updateContent(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            queryClient.invalidateQueries({ queryKey: ['cms', 'contents'] });
            toastSuccess('Video links updated');
        },
        onError: (error) => toastError(error, 'Failed to update video links'),
    });

    const updateDueDateMutation = useMutation({
        mutationFn: ({ stage, due_date }) =>
            updateStageDueDate(id, stage, { due_date: due_date || null }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            toastSuccess('Due date updated');
        },
        onError: (error) => toastError(error, 'Failed to update due date'),
    });

    const updateStageMetaMutation = useMutation({
        mutationFn: ({ stage, data }) => updateStageMeta(id, stage, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cms', 'content', id] });
            toastSuccess('Stage details saved');
        },
        onError: (error) => toastError(error, 'Failed to save stage details'),
    });

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-24">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent" />
            </div>
        );
    }

    if (!content) {
        return (
            <div className="py-24 text-center">
                <p className="text-slate-500">Content not found.</p>
                <Link to="/contents" className="mt-4 inline-block text-sm text-indigo-600 hover:underline">
                    Back to Contents
                </Link>
            </div>
        );
    }

    const data = contentData;

    const currentStageIndex = STAGES.indexOf(currentStage);
    const availableNextStages = STAGES.slice(currentStageIndex + 1);
    const stageColors = STAGE_COLORS[currentStage] || STAGE_COLORS.idea;
    const priorityColor = PRIORITY_COLORS[data.priority] || PRIORITY_COLORS.medium;

    function handleMoveStage() {
        if (!nextStage) return;
        moveStageMutation.mutate({ stage: nextStage });
    }

    function handleAddStats() {
        addStatsMutation.mutate({
            views: parseInt(statsForm.views, 10) || 0,
            likes: parseInt(statsForm.likes, 10) || 0,
            comments: parseInt(statsForm.comments, 10) || 0,
            shares: parseInt(statsForm.shares, 10) || 0,
        });
    }

    const driveEmbedUrl = getGoogleDriveEmbedUrl(videoForm.video_url);
    const tiktokEmbedUrl = getTiktokEmbedUrl(videoForm.tiktok_url);

    function handleOpenMoveStage() {
        if (availableNextStages.length > 0) {
            setNextStage(availableNextStages[0]);
        }
        setMoveStageOpen(true);
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <Link
                    to="/contents"
                    className="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Contents
                </Link>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-3">
                        <h1 className="text-2xl font-bold text-slate-800">
                            {data.title}
                        </h1>

                        <div className="flex flex-wrap items-center gap-2">
                            {data.priority && (
                                <Badge className={priorityColor}>
                                    {data.priority.charAt(0).toUpperCase() + data.priority.slice(1)}
                                </Badge>
                            )}
                            <Badge className={stageColors.badge}>
                                {STAGE_LABELS[currentStage] || currentStage}
                            </Badge>
                            {data.is_flagged_for_ads && (
                                <Badge className="bg-orange-100 text-orange-800">
                                    <Flag className="mr-1 h-3 w-3" />
                                    Flagged for Ads
                                </Badge>
                            )}
                            {data.is_marked_for_ads && (
                                <Badge className="bg-indigo-100 text-indigo-800">
                                    <Megaphone className="mr-1 h-3 w-3" />
                                    Marked for Ads
                                </Badge>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-4 text-sm text-slate-500">
                            {(data.creator?.full_name || data.created_by_name) && (
                                <span className="flex items-center gap-1">
                                    <User className="h-3.5 w-3.5" />
                                    {data.creator?.full_name || data.created_by_name}
                                </span>
                            )}
                            <span className="flex items-center gap-1">
                                <Calendar className="h-3.5 w-3.5" />
                                Created {formatDate(data.created_at)}
                            </span>
                            {data.due_date && (
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3.5 w-3.5" />
                                    Due {formatDate(data.due_date)}
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Action buttons */}
                    <div className="flex items-center gap-2 shrink-0">
                        <Button variant="outline" size="sm" asChild>
                            <Link to={`/contents/${id}/edit`}>
                                <Pencil className="mr-1.5 h-3.5 w-3.5" />
                                Edit
                            </Link>
                        </Button>

                        {currentStage !== 'posted' && (
                            <Button
                                size="sm"
                                onClick={handleOpenMoveStage}
                                disabled={availableNextStages.length === 0}
                            >
                                <ChevronRight className="mr-1 h-3.5 w-3.5" />
                                Next Stage
                            </Button>
                        )}

                        <Button
                            variant={data.is_marked_for_ads ? 'secondary' : 'outline'}
                            size="sm"
                            onClick={() => markAdsMutation.mutate()}
                            disabled={markAdsMutation.isPending}
                        >
                            <Megaphone className="mr-1.5 h-3.5 w-3.5" />
                            {data.is_marked_for_ads ? 'Marked' : 'Mark for Ads'}
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => setAddStatsOpen(true)}>
                                    <BarChart3 className="mr-2 h-4 w-4" />
                                    Add Stats
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="text-rose-600 focus:text-rose-600 focus:bg-rose-50"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            {/* Stage Timeline */}
            <Card>
                <CardContent className="py-6">
                    <StageTimeline
                        stages={data.stages || []}
                        currentStage={currentStage}
                    />
                </CardContent>
            </Card>

            {/* Two-column layout */}
            <div className="grid gap-6 md:grid-cols-2">
                {/* Left: Stage Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Stage Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {STAGES.map((stage) => {
                            const stageData = (data.stages || []).find(
                                (s) => s.stage === stage || s.name === stage
                            );
                            const isCurrent = stage === currentStage;
                            const colors = STAGE_COLORS[stage] || STAGE_COLORS.idea;
                            const isExpanded = expandedStage === stage;
                            const assignees = stageData?.assignees || [];

                            return (
                                <div
                                    key={stage}
                                    className={cn(
                                        'rounded-lg border transition-colors',
                                        isCurrent
                                            ? `${colors.border} bg-white`
                                            : 'border-slate-100 bg-slate-50/50'
                                    )}
                                >
                                    {/* Stage Header - clickable */}
                                    <button
                                        type="button"
                                        onClick={() => setExpandedStage(isExpanded ? null : stage)}
                                        className="flex w-full items-center justify-between p-3 text-left hover:bg-slate-50/80 rounded-lg"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className={cn('text-sm font-semibold', colors.text)}>
                                                {STAGE_LABELS[stage]}
                                            </span>
                                            {assignees.length > 0 && (
                                                <span className="text-[10px] text-slate-400">
                                                    {assignees.length} assignee{assignees.length !== 1 ? 's' : ''}
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {stageData?.status && (
                                                <Badge variant="secondary" className="text-[10px]">
                                                    {formatStatus(stageData.status)}
                                                </Badge>
                                            )}
                                            {isExpanded ? (
                                                <ChevronDown className="h-4 w-4 text-slate-400" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4 text-slate-400" />
                                            )}
                                        </div>
                                    </button>

                                    {/* Collapsed: show assignee avatars inline */}
                                    {!isExpanded && assignees.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5 px-3 pb-3 -mt-1">
                                            {assignees.map((assignee, aIdx) => (
                                                <div
                                                    key={assignee.id || aIdx}
                                                    className="flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pl-0.5 pr-2"
                                                >
                                                    <Avatar className="h-5 w-5">
                                                        <AvatarFallback className="text-[8px] bg-slate-200">
                                                            {getInitials(getAssigneeName(assignee))}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="text-[11px] text-slate-600">
                                                        {getAssigneeName(assignee) || 'Unknown'}
                                                    </span>
                                                    {assignee.role && (
                                                        <span className="text-[10px] text-slate-400">
                                                            ({assignee.role})
                                                        </span>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Expanded: full editing */}
                                    {isExpanded && stage !== 'posted' && (
                                        <div className="border-t border-slate-100 px-3 pb-3 pt-3 space-y-3">
                                            {/* Due date - editable */}
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                                <label className="text-xs font-medium text-slate-500 shrink-0">Due Date</label>
                                                <input
                                                    type="date"
                                                    className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                                    value={stageData?.due_date ? stageData.due_date.split('T')[0] : ''}
                                                    onChange={(e) =>
                                                        updateDueDateMutation.mutate({
                                                            stage,
                                                            due_date: e.target.value,
                                                        })
                                                    }
                                                />
                                                {stageData?.due_date && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            updateDueDateMutation.mutate({
                                                                stage,
                                                                due_date: null,
                                                            })
                                                        }
                                                        className="text-slate-400 hover:text-red-500"
                                                        title="Clear due date"
                                                    >
                                                        <X className="h-3.5 w-3.5" />
                                                    </button>
                                                )}
                                            </div>

                                            {/* Current assignees with remove */}
                                            {assignees.length > 0 && (
                                                <div className="space-y-1.5">
                                                    <p className="text-xs font-medium text-slate-500">Assignees</p>
                                                    {assignees.map((assignee, aIdx) => (
                                                        <div
                                                            key={assignee.id || aIdx}
                                                            className="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2 py-1.5"
                                                        >
                                                            <Avatar className="h-6 w-6">
                                                                <AvatarFallback className="text-[9px]">
                                                                    {getInitials(getAssigneeName(assignee))}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div className="flex-1 min-w-0">
                                                                <span className="text-xs font-medium text-slate-700">
                                                                    {getAssigneeName(assignee) || 'Unknown'}
                                                                </span>
                                                                {assignee.role && (
                                                                    <span className="ml-1.5 text-[10px] text-slate-400">
                                                                        {assignee.role}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeAssigneeMutation.mutate({
                                                                        stage,
                                                                        employeeId: assignee.employee_id,
                                                                    })
                                                                }
                                                                className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-slate-400 hover:bg-red-50 hover:text-red-500"
                                                            >
                                                                <X className="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            {/* Add assignee picker */}
                                            <div>
                                                <p className="text-xs font-medium text-slate-500 mb-1.5 flex items-center gap-1">
                                                    <UserPlus className="h-3 w-3" />
                                                    Add Assignee
                                                </p>
                                                <AssigneePicker
                                                    assignees={assignees.map((a) => ({
                                                        employee_id: a.employee_id,
                                                        full_name: getAssigneeName(a),
                                                        role: a.role || '',
                                                    }))}
                                                    onAssigneesChange={(newAssignees) => {
                                                        // Find new additions
                                                        const existingIds = assignees.map((a) => a.employee_id);
                                                        const added = newAssignees.filter(
                                                            (a) => !existingIds.includes(a.employee_id)
                                                        );
                                                        added.forEach((a) => {
                                                            addAssigneeMutation.mutate({
                                                                stage,
                                                                employee_id: a.employee_id,
                                                                role: a.role || null,
                                                            });
                                                        });
                                                    }}
                                                />
                                            </div>

                                            {stage === 'editing' && (
                                                <EditingStageFields
                                                    data={data}
                                                    stageData={stageData}
                                                    videoForm={videoForm}
                                                    setVideoForm={setVideoForm}
                                                    saveVideoUrl={(url) =>
                                                        updateVideoMutation.mutate({ video_url: url?.trim() || null })
                                                    }
                                                    saveMeta={(payload) =>
                                                        updateStageMetaMutation.mutate({ stage: 'editing', data: payload })
                                                    }
                                                    driveEmbedUrl={driveEmbedUrl}
                                                />
                                            )}

                                            {stage === 'posting' && (
                                                <PostingStageFields
                                                    data={data}
                                                    stageData={stageData}
                                                    videoForm={videoForm}
                                                    setVideoForm={setVideoForm}
                                                    saveTiktokUrl={(url) =>
                                                        updateVideoMutation.mutate({ tiktok_url: url?.trim() || null })
                                                    }
                                                    saveMeta={(payload) =>
                                                        updateStageMetaMutation.mutate({ stage: 'posting', data: payload })
                                                    }
                                                    tiktokEmbedUrl={tiktokEmbedUrl}
                                                />
                                            )}
                                        </div>
                                    )}

                                    {/* Posted stage - no editing */}
                                    {isExpanded && stage === 'posted' && (
                                        <div className="border-t border-slate-100 px-3 pb-3 pt-3">
                                            <p className="text-xs text-slate-400">
                                                {data.posted_at
                                                    ? `Posted on ${formatDate(data.posted_at)}`
                                                    : 'Not yet posted'}
                                            </p>
                                        </div>
                                    )}

                                    {/* No stage data */}
                                    {!stageData && !isExpanded && (
                                        <p className="px-3 pb-3 -mt-1 text-xs text-slate-400">
                                            No details yet
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                {/* Right: TikTok Stats */}
                <div>
                    {currentStage === 'posted' ? (
                        <div>
                            <h3 className="mb-3 text-lg font-semibold text-slate-800">
                                TikTok Stats
                            </h3>
                            <StatsCard stats={data.stats || data.tiktok_stats || []} />
                        </div>
                    ) : (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-16">
                                <BarChart3 className="h-10 w-10 text-slate-300 mb-3" />
                                <p className="text-sm text-slate-500">
                                    Stats available after posting
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Creator Promotions */}
                    {currentStage === 'posted' && contentCreators.length > 0 && (
                        <Card className="mt-4">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Users className="h-4 w-4 text-purple-500" />
                                    Promoted by {contentCreators.length} Creator{contentCreators.length !== 1 && 's'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {contentCreators.map((cc) => (
                                        <div key={cc.id} className="flex items-center justify-between rounded-lg border border-slate-100 p-3">
                                            <div className="flex items-center gap-3">
                                                {cc.creator?.avatar_url ? (
                                                    <img src={cc.creator.avatar_url} className="h-8 w-8 rounded-full" alt="" />
                                                ) : (
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-xs font-bold text-purple-600">
                                                        {(cc.creator?.display_name || '?')[0]}
                                                    </div>
                                                )}
                                                <div>
                                                    <p className="text-sm font-medium">{cc.creator?.display_name}</p>
                                                    <p className="text-xs text-slate-400">{cc.creator?.handle}</p>
                                                </div>
                                            </div>
                                            <div className="flex gap-4 text-xs text-slate-500">
                                                <span>{(cc.views || 0).toLocaleString()} views</span>
                                                <span>{(cc.orders || 0).toLocaleString()} orders</span>
                                                <span className="font-medium text-emerald-600">
                                                    RM {parseFloat(cc.gmv || 0).toFixed(2)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {/* Description */}
            {data.description && (
                <Card>
                    <CardHeader>
                        <CardTitle>Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm leading-relaxed text-slate-600 whitespace-pre-wrap">
                            {data.description}
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Move Stage Dialog */}
            <Dialog open={moveStageOpen} onOpenChange={setMoveStageOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Move to Next Stage</DialogTitle>
                        <DialogDescription>
                            Select the stage to move this content to.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <Label htmlFor="next-stage">Next Stage</Label>
                        <Select value={nextStage} onValueChange={setNextStage}>
                            <SelectTrigger className="mt-1.5">
                                <SelectValue placeholder="Select stage" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableNextStages.map((stage) => (
                                    <SelectItem key={stage} value={stage}>
                                        {STAGE_LABELS[stage]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setMoveStageOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleMoveStage}
                            disabled={!nextStage || moveStageMutation.isPending}
                        >
                            {moveStageMutation.isPending ? 'Moving...' : 'Confirm'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Add Stats Dialog */}
            <Dialog open={addStatsOpen} onOpenChange={setAddStatsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add TikTok Stats</DialogTitle>
                        <DialogDescription>
                            Enter the latest performance metrics for this content.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label htmlFor="stat-views">Views</Label>
                            <Input
                                id="stat-views"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.views}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, views: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                        <div>
                            <Label htmlFor="stat-likes">Likes</Label>
                            <Input
                                id="stat-likes"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.likes}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, likes: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                        <div>
                            <Label htmlFor="stat-comments">Comments</Label>
                            <Input
                                id="stat-comments"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.comments}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, comments: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                        <div>
                            <Label htmlFor="stat-shares">Shares</Label>
                            <Input
                                id="stat-shares"
                                type="number"
                                min="0"
                                className="mt-1.5"
                                value={statsForm.shares}
                                onChange={(e) =>
                                    setStatsForm((f) => ({ ...f, shares: e.target.value }))
                                }
                                placeholder="0"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setAddStatsOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleAddStats}
                            disabled={addStatsMutation.isPending}
                        >
                            {addStatsMutation.isPending ? 'Saving...' : 'Save Stats'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirm Dialog */}
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Content</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this content? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => deleteMutation.mutate()}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function EditingStageFields({ data, stageData, videoForm, setVideoForm, saveVideoUrl, saveMeta, driveEmbedUrl }) {
    const [videoConcept, setVideoConcept] = useState(stageData?.video_concept || '');
    const [description, setDescription] = useState(stageData?.stage_description || '');

    useEffect(() => {
        setVideoConcept(stageData?.video_concept || '');
        setDescription(stageData?.stage_description || '');
    }, [stageData?.id, stageData?.video_concept, stageData?.stage_description]);

    return (
        <div className="space-y-3 border-t border-slate-100 pt-3">
            {/* Link Folder Video Raw */}
            <div className="space-y-1.5">
                <Label className="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                    <LinkIcon className="h-3.5 w-3.5" />
                    Link Folder Video Raw
                </Label>
                <div className="flex items-center gap-2">
                    <Input
                        type="url"
                        placeholder="https://drive.google.com/..."
                        value={videoForm.video_url}
                        onChange={(e) => setVideoForm((f) => ({ ...f, video_url: e.target.value }))}
                        onBlur={(e) => {
                            if ((e.target.value || '') !== (data.video_url || '')) {
                                saveVideoUrl(e.target.value);
                            }
                        }}
                    />
                    {data.video_url && (
                        <a
                            href={data.video_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-indigo-600"
                            title="Open video link"
                        >
                            <ExternalLink className="h-4 w-4" />
                        </a>
                    )}
                </div>
                {videoForm.video_url && !driveEmbedUrl && (
                    <p className="text-[11px] text-amber-600">
                        Preview only supports Google Drive file links. Other links will still save.
                    </p>
                )}

                <div className="mt-2">
                    <p className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Preview</p>
                    {driveEmbedUrl ? (
                        <div className="aspect-video overflow-hidden rounded-md border border-slate-200 bg-slate-900">
                            <iframe
                                key={driveEmbedUrl}
                                src={driveEmbedUrl}
                                title="Google Drive video preview"
                                allow="autoplay"
                                allowFullScreen
                                className="h-full w-full"
                            />
                        </div>
                    ) : (
                        <div className="flex aspect-video items-center justify-center rounded-md border border-dashed border-slate-200 bg-slate-50 text-center">
                            <p className="px-4 text-xs text-slate-400">
                                Paste a Google Drive file link to preview the video here.
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Video Concept */}
            <div className="space-y-1.5">
                <Label className="text-xs font-medium text-slate-600">Video Concept</Label>
                <Textarea
                    rows={3}
                    placeholder="Describe the video concept, shot list, story beats..."
                    value={videoConcept}
                    onChange={(e) => setVideoConcept(e.target.value)}
                    onBlur={() => {
                        if (videoConcept !== (stageData?.video_concept || '')) {
                            saveMeta({ video_concept: videoConcept || null });
                        }
                    }}
                />
            </div>

            {/* Description */}
            <div className="space-y-1.5">
                <Label className="text-xs font-medium text-slate-600">Description</Label>
                <Textarea
                    rows={3}
                    placeholder="Notes for the editing team..."
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    onBlur={() => {
                        if (description !== (stageData?.stage_description || '')) {
                            saveMeta({ stage_description: description || null });
                        }
                    }}
                />
            </div>
        </div>
    );
}

function PostingStageFields({ data, stageData, videoForm, setVideoForm, saveTiktokUrl, saveMeta, tiktokEmbedUrl }) {
    const [accountName, setAccountName] = useState(stageData?.account_name || '');
    const [postingTime, setPostingTime] = useState(
        stageData?.posting_time ? stageData.posting_time.slice(0, 16) : ''
    );

    useEffect(() => {
        setAccountName(stageData?.account_name || '');
        setPostingTime(stageData?.posting_time ? stageData.posting_time.slice(0, 16) : '');
    }, [stageData?.id, stageData?.account_name, stageData?.posting_time]);

    return (
        <div className="space-y-3 border-t border-slate-100 pt-3">
            {/* Nama Akaun */}
            <div className="space-y-1.5">
                <Label className="text-xs font-medium text-slate-600">Nama Akaun</Label>
                <Input
                    type="text"
                    placeholder="e.g. @mudeerbedaie"
                    value={accountName}
                    onChange={(e) => setAccountName(e.target.value)}
                    onBlur={() => {
                        if (accountName !== (stageData?.account_name || '')) {
                            saveMeta({ account_name: accountName || null });
                        }
                    }}
                />
            </div>

            {/* Masa Posting */}
            <div className="space-y-1.5">
                <Label className="text-xs font-medium text-slate-600">Masa Posting</Label>
                <Input
                    type="datetime-local"
                    value={postingTime}
                    onChange={(e) => {
                        setPostingTime(e.target.value);
                        const current = stageData?.posting_time ? stageData.posting_time.slice(0, 16) : '';
                        if (e.target.value !== current) {
                            saveMeta({ posting_time: e.target.value || null });
                        }
                    }}
                />
            </div>

            {/* Link Posting */}
            <div className="space-y-1.5">
                <Label className="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                    <LinkIcon className="h-3.5 w-3.5" />
                    Link Posting
                </Label>
                <div className="flex items-center gap-2">
                    <Input
                        type="url"
                        placeholder="https://www.tiktok.com/@user/video/..."
                        value={videoForm.tiktok_url}
                        onChange={(e) => setVideoForm((f) => ({ ...f, tiktok_url: e.target.value }))}
                        onBlur={(e) => {
                            if ((e.target.value || '') !== (data.tiktok_url || '')) {
                                saveTiktokUrl(e.target.value);
                            }
                        }}
                    />
                    {data.tiktok_url && (
                        <a
                            href={data.tiktok_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-indigo-600"
                            title="Open TikTok post"
                        >
                            <ExternalLink className="h-4 w-4" />
                        </a>
                    )}
                </div>
                {videoForm.tiktok_url && !tiktokEmbedUrl && (
                    <p className="text-[11px] text-amber-600">
                        Couldn't parse a video ID. Use a full URL like https://www.tiktok.com/@user/video/123...
                    </p>
                )}

                <div className="mt-2">
                    <p className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Preview</p>
                    {tiktokEmbedUrl ? (
                        <div className="mx-auto aspect-[9/16] w-full max-w-[220px] overflow-hidden rounded-md border border-slate-200 bg-slate-900">
                            <iframe
                                key={tiktokEmbedUrl}
                                src={tiktokEmbedUrl}
                                title="TikTok video preview"
                                allow="autoplay; encrypted-media; picture-in-picture"
                                allowFullScreen
                                className="h-full w-full"
                            />
                        </div>
                    ) : (
                        <div className="mx-auto flex aspect-[9/16] w-full max-w-[220px] items-center justify-center rounded-md border border-dashed border-slate-200 bg-slate-50 text-center">
                            <p className="px-4 text-xs text-slate-400">
                                Paste a TikTok video URL to preview it here.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

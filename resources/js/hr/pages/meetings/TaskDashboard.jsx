import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    ArrowLeft,
    ListTodo,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Loader2,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    MessageSquare,
} from 'lucide-react';
import { fetchMeetingTasks, updateTaskStatus, addTaskComment } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Card, CardContent } from '../../components/ui/card';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';

const PRIORITY_BADGE = {
    low: { label: 'Low', variant: 'secondary' },
    medium: { label: 'Medium', className: 'bg-blue-100 text-blue-800 border-transparent' },
    high: { label: 'High', variant: 'warning' },
    urgent: { label: 'Urgent', variant: 'destructive' },
};

const STATUS_BADGE = {
    pending: { label: 'Pending', variant: 'secondary' },
    in_progress: { label: 'In Progress', variant: 'warning' },
    completed: { label: 'Completed', variant: 'success' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

function TaskPriorityBadge({ priority }) {
    const config = PRIORITY_BADGE[priority] || { label: priority, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function TaskStatusBadge({ status }) {
    const config = STATUS_BADGE[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function TaskDashboard() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [priorityFilter, setPriorityFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [expandedTask, setExpandedTask] = useState(null);
    const [commentText, setCommentText] = useState('');

    const params = {
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        priority: priorityFilter !== 'all' ? priorityFilter : undefined,
        page,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'tasks', params],
        queryFn: () => fetchMeetingTasks(params),
    });

    const statusMut = useMutation({
        mutationFn: ({ id, status }) => updateTaskStatus(id, { status }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] }),
    });

    const commentMut = useMutation({
        mutationFn: ({ taskId, body }) => addTaskComment(taskId, { body }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'tasks'] });
            setCommentText('');
        },
    });

    const tasks = data?.data || [];
    const pagination = data?.meta || data || {};
    const lastPage = pagination.last_page || 1;

    const resetPage = useCallback(() => setPage(1), []);

    // Stats
    const totalTasks = pagination.total || tasks.length;
    const pendingCount = tasks.filter((t) => t.status === 'pending').length;
    const inProgressCount = tasks.filter((t) => t.status === 'in_progress').length;
    const completedCount = tasks.filter((t) => t.status === 'completed').length;
    const overdueCount = tasks.filter(
        (t) => t.deadline && new Date(t.deadline) < new Date() && t.status !== 'completed' && t.status !== 'cancelled'
    ).length;

    return (
        <div>
            <PageHeader
                title="Task Dashboard"
                description="Track action items across all meetings."
                action={
                    <Button variant="outline" onClick={() => navigate('/meetings')}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back to Meetings
                    </Button>
                }
            />

            {/* Stats */}
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100">
                                <ListTodo className="h-5 w-5 text-zinc-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{totalTasks}</p>
                                <p className="text-xs text-zinc-500">Total</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100">
                                <Clock className="h-5 w-5 text-zinc-500" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{pendingCount}</p>
                                <p className="text-xs text-zinc-500">Pending</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                                <Clock className="h-5 w-5 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{inProgressCount}</p>
                                <p className="text-xs text-zinc-500">In Progress</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50">
                                <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{completedCount}</p>
                                <p className="text-xs text-zinc-500">Completed</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50">
                                <AlertTriangle className="h-5 w-5 text-red-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-zinc-900">{overdueCount}</p>
                                <p className="text-xs text-zinc-500">Overdue</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={(v) => { setSearch(v); resetPage(); }}
                            placeholder="Search tasks..."
                            className="lg:w-80"
                        />
                        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={priorityFilter} onValueChange={(v) => { setPriorityFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[160px]">
                                <SelectValue placeholder="All Priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Priority</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-20">
                            <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                        </div>
                    ) : tasks.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <ListTodo className="mb-4 h-12 w-12 text-zinc-300" />
                            <h3 className="text-lg font-semibold text-zinc-900">No tasks found</h3>
                            <p className="mt-1 text-sm text-zinc-500">Tasks created in meetings will appear here.</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead />
                                        <TableHead>Task</TableHead>
                                        <TableHead>Meeting</TableHead>
                                        <TableHead>Assignee</TableHead>
                                        <TableHead>Priority</TableHead>
                                        <TableHead>Deadline</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tasks.map((task) => (
                                        <>
                                            <TableRow key={task.id}>
                                                <TableCell className="w-8">
                                                    <button onClick={() => setExpandedTask(expandedTask === task.id ? null : task.id)}>
                                                        <ChevronDown
                                                            className={cn(
                                                                'h-4 w-4 text-zinc-400 transition-transform',
                                                                expandedTask === task.id && 'rotate-180'
                                                            )}
                                                        />
                                                    </button>
                                                </TableCell>
                                                <TableCell>
                                                    <p className="font-medium text-zinc-900">{task.title}</p>
                                                </TableCell>
                                                <TableCell>
                                                    {task.meeting ? (
                                                        <Link
                                                            to={`/meetings/${task.meeting_id || task.meeting?.id}`}
                                                            className="text-sm text-blue-600 hover:underline"
                                                        >
                                                            {task.meeting.title}
                                                        </Link>
                                                    ) : '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {task.assignee?.full_name || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <TaskPriorityBadge priority={task.priority} />
                                                </TableCell>
                                                <TableCell>
                                                    <span className={cn(
                                                        task.deadline && new Date(task.deadline) < new Date() && task.status !== 'completed' && 'text-red-600 font-medium'
                                                    )}>
                                                        {formatDate(task.deadline)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <Select
                                                        value={task.status}
                                                        onValueChange={(v) => statusMut.mutate({ id: task.id, status: v })}
                                                    >
                                                        <SelectTrigger className="h-8 w-[130px]">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="pending">Pending</SelectItem>
                                                            <SelectItem value="in_progress">In Progress</SelectItem>
                                                            <SelectItem value="completed">Completed</SelectItem>
                                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </TableCell>
                                            </TableRow>
                                            {expandedTask === task.id && (
                                                <TableRow key={`${task.id}-expanded`}>
                                                    <TableCell colSpan={7}>
                                                        <div className="space-y-3 px-4 py-2">
                                                            {task.description && (
                                                                <p className="text-sm text-zinc-600">{task.description}</p>
                                                            )}
                                                            {/* Subtasks */}
                                                            {task.subtasks?.length > 0 && (
                                                                <div>
                                                                    <p className="text-xs font-medium uppercase text-zinc-500">Subtasks</p>
                                                                    <ul className="mt-1 space-y-1">
                                                                        {task.subtasks.map((st) => (
                                                                            <li key={st.id} className="flex items-center gap-2 text-sm">
                                                                                <span className={cn(
                                                                                    'h-2 w-2 rounded-full',
                                                                                    st.status === 'completed' ? 'bg-emerald-500' : 'bg-zinc-300'
                                                                                )} />
                                                                                {st.title}
                                                                            </li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            )}
                                                            {/* Comments */}
                                                            {task.comments?.length > 0 && (
                                                                <div>
                                                                    <p className="text-xs font-medium uppercase text-zinc-500">Comments</p>
                                                                    <div className="mt-1 space-y-2">
                                                                        {task.comments.map((c) => (
                                                                            <div key={c.id} className="rounded bg-zinc-50 px-3 py-2">
                                                                                <p className="text-xs font-medium text-zinc-700">
                                                                                    {c.user?.name || c.author?.full_name || 'Unknown'}
                                                                                </p>
                                                                                <p className="text-sm text-zinc-600">{c.body || c.content}</p>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}
                                                            {/* Add Comment */}
                                                            <div className="flex gap-2">
                                                                <Input
                                                                    value={expandedTask === task.id ? commentText : ''}
                                                                    onChange={(e) => setCommentText(e.target.value)}
                                                                    placeholder="Add a comment..."
                                                                    className="flex-1"
                                                                />
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => commentMut.mutate({ taskId: task.id, body: commentText })}
                                                                    disabled={!commentText.trim() || commentMut.isPending}
                                                                >
                                                                    <MessageSquare className="mr-1 h-3.5 w-3.5" />
                                                                    Comment
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </>
                                    ))}
                                </TableBody>
                            </Table>

                            {lastPage > 1 && (
                                <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                                    <p className="text-sm text-zinc-500">Page {page} of {lastPage}</p>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

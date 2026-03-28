import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ListTodo,
    Clock,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    ChevronDown,
    MessageSquare,
} from 'lucide-react';
import { fetchMyMeetingTasks, updateTaskStatus, addTaskComment } from '../../lib/api';
import { cn } from '../../lib/utils';
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
import { Tabs, TabsList, TabsTrigger } from '../../components/ui/tabs';

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

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function MyTasks() {
    const queryClient = useQueryClient();
    const [tab, setTab] = useState('active');
    const [expandedTask, setExpandedTask] = useState(null);
    const [commentText, setCommentText] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'my-tasks', tab],
        queryFn: () => fetchMyMeetingTasks({ filter: tab }),
    });

    const statusMut = useMutation({
        mutationFn: ({ id, status }) => updateTaskStatus(id, { status }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'my-tasks'] }),
    });

    const commentMut = useMutation({
        mutationFn: ({ taskId, body }) => addTaskComment(taskId, { body }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'my-tasks'] });
            setCommentText('');
        },
    });

    const tasks = data?.data || [];

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-zinc-900">My Tasks</h1>
                <p className="mt-1 text-sm text-zinc-500">Action items assigned to you from meetings.</p>
            </div>

            <Tabs value={tab} onValueChange={setTab}>
                <TabsList className="mb-4">
                    <TabsTrigger value="active">Active</TabsTrigger>
                    <TabsTrigger value="completed">Completed</TabsTrigger>
                    <TabsTrigger value="all">All</TabsTrigger>
                </TabsList>
            </Tabs>

            {isLoading ? (
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                </div>
            ) : tasks.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <ListTodo className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No tasks</h3>
                        <p className="mt-1 text-sm text-zinc-500">You have no {tab} tasks.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {tasks.map((task) => {
                        const pConfig = PRIORITY_BADGE[task.priority] || { label: task.priority, variant: 'secondary' };
                        const sConfig = STATUS_BADGE[task.status] || { label: task.status, variant: 'secondary' };
                        const isOverdue = task.deadline && new Date(task.deadline) < new Date() && task.status !== 'completed' && task.status !== 'cancelled';
                        const isExpanded = expandedTask === task.id;

                        return (
                            <Card key={task.id}>
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <button onClick={() => setExpandedTask(isExpanded ? null : task.id)}>
                                                    <ChevronDown className={cn('h-4 w-4 text-zinc-400 transition-transform', isExpanded && 'rotate-180')} />
                                                </button>
                                                <h3 className="font-semibold text-zinc-900">{task.title}</h3>
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <Badge variant={pConfig.variant} className={pConfig.className}>{pConfig.label}</Badge>
                                                <span className={cn('text-sm', isOverdue ? 'text-red-600 font-medium' : 'text-zinc-500')}>
                                                    {isOverdue && <AlertTriangle className="mr-1 inline h-3.5 w-3.5" />}
                                                    Due: {formatDate(task.deadline)}
                                                </span>
                                                {task.meeting && (
                                                    <span className="text-sm text-zinc-400">
                                                        from {task.meeting.title}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
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
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {isExpanded && (
                                        <div className="mt-4 space-y-3 border-t border-zinc-100 pt-4">
                                            {task.description && (
                                                <p className="text-sm text-zinc-600">{task.description}</p>
                                            )}
                                            {task.subtasks?.length > 0 && (
                                                <div>
                                                    <p className="text-xs font-medium uppercase text-zinc-500">Subtasks</p>
                                                    <ul className="mt-1 space-y-1">
                                                        {task.subtasks.map((st) => (
                                                            <li key={st.id} className="flex items-center gap-2 text-sm">
                                                                <span className={cn('h-2 w-2 rounded-full', st.status === 'completed' ? 'bg-emerald-500' : 'bg-zinc-300')} />
                                                                {st.title}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            )}
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
                                            <div className="flex gap-2">
                                                <Input
                                                    value={isExpanded ? commentText : ''}
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
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

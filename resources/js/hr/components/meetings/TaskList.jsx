import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ChevronDown,
    MessageSquare,
    Plus,
    Check,
    X,
} from 'lucide-react';
import { updateTaskStatus, addTaskComment, createSubtask } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../ui/select';

const PRIORITY_BADGE = {
    low: { label: 'Low', variant: 'secondary' },
    medium: { label: 'Medium', className: 'bg-blue-100 text-blue-800 border-transparent' },
    high: { label: 'High', variant: 'warning' },
    urgent: { label: 'Urgent', variant: 'destructive' },
};

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function TaskList({ meetingId, tasks, onUpdate }) {
    const queryClient = useQueryClient();
    const [expandedTask, setExpandedTask] = useState(null);
    const [commentText, setCommentText] = useState('');
    const [subtaskTitle, setSubtaskTitle] = useState('');
    const [addingSubtask, setAddingSubtask] = useState(null);

    function invalidate() {
        queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', meetingId] });
        onUpdate?.();
    }

    const statusMut = useMutation({
        mutationFn: ({ id, status }) => updateTaskStatus(id, { status }),
        onSuccess: invalidate,
    });

    const commentMut = useMutation({
        mutationFn: ({ taskId, body }) => addTaskComment(taskId, { body }),
        onSuccess: () => { invalidate(); setCommentText(''); },
    });

    const subtaskMut = useMutation({
        mutationFn: ({ taskId, title }) => createSubtask(taskId, { title }),
        onSuccess: () => { invalidate(); setSubtaskTitle(''); setAddingSubtask(null); },
    });

    if (tasks.length === 0) {
        return <p className="text-sm text-zinc-500">No tasks yet. Add one using the button above.</p>;
    }

    return (
        <div className="space-y-2">
            {tasks.map((task) => {
                const pConfig = PRIORITY_BADGE[task.priority] || { label: task.priority, variant: 'secondary' };
                const isExpanded = expandedTask === task.id;
                const isOverdue = task.deadline && new Date(task.deadline) < new Date() && task.status !== 'completed' && task.status !== 'cancelled';

                return (
                    <div key={task.id} className="rounded-lg border border-zinc-200">
                        <div className="flex items-center justify-between px-3 py-2">
                            <div className="flex items-center gap-2">
                                <button onClick={() => setExpandedTask(isExpanded ? null : task.id)}>
                                    <ChevronDown className={cn('h-4 w-4 text-zinc-400 transition-transform', isExpanded && 'rotate-180')} />
                                </button>
                                <p className="text-sm font-medium text-zinc-900">{task.title}</p>
                                <Badge variant={pConfig.variant} className={pConfig.className}>{pConfig.label}</Badge>
                                {task.assignee && (
                                    <span className="text-xs text-zinc-500">{task.assignee.full_name}</span>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={cn('text-xs', isOverdue ? 'text-red-600 font-medium' : 'text-zinc-500')}>
                                    {formatDate(task.deadline)}
                                </span>
                                <Select
                                    value={task.status}
                                    onValueChange={(v) => statusMut.mutate({ id: task.id, status: v })}
                                >
                                    <SelectTrigger className="h-7 w-[120px] text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="in_progress">In Progress</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {isExpanded && (
                            <div className="space-y-3 border-t border-zinc-100 px-3 py-3">
                                {task.description && (
                                    <p className="text-sm text-zinc-600">{task.description}</p>
                                )}

                                {/* Subtasks */}
                                <div>
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs font-medium uppercase text-zinc-500">Subtasks</p>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setAddingSubtask(addingSubtask === task.id ? null : task.id)}
                                        >
                                            <Plus className="mr-1 h-3 w-3" />
                                            Add
                                        </Button>
                                    </div>
                                    {task.subtasks?.length > 0 && (
                                        <ul className="mt-1 space-y-1">
                                            {task.subtasks.map((st) => (
                                                <li key={st.id} className="flex items-center gap-2 text-sm">
                                                    <span className={cn('h-2 w-2 rounded-full', st.status === 'completed' ? 'bg-emerald-500' : 'bg-zinc-300')} />
                                                    {st.title}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    {addingSubtask === task.id && (
                                        <div className="mt-2 flex gap-2">
                                            <Input
                                                value={subtaskTitle}
                                                onChange={(e) => setSubtaskTitle(e.target.value)}
                                                placeholder="Subtask title"
                                                className="flex-1"
                                            />
                                            <Button
                                                size="sm"
                                                onClick={() => subtaskMut.mutate({ taskId: task.id, title: subtaskTitle })}
                                                disabled={!subtaskTitle.trim() || subtaskMut.isPending}
                                            >
                                                <Check className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => { setAddingSubtask(null); setSubtaskTitle(''); }}>
                                                <X className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                {/* Comments */}
                                <div>
                                    <p className="text-xs font-medium uppercase text-zinc-500">Comments</p>
                                    {task.comments?.length > 0 && (
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
                                    )}
                                    <div className="mt-2 flex gap-2">
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
                                            Send
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

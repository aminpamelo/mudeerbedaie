import { useState } from 'react';
import { router, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2, CheckCircle2 } from 'lucide-react';
import WorkspaceLayout from '@/workspace/layouts/WorkspaceLayout';
import TaskForm from '@/workspace/components/TaskForm';
import ChecklistEditor from '@/workspace/components/ChecklistEditor';
import CommentThread from '@/workspace/components/CommentThread';
import ActivityTimeline from '@/workspace/components/ActivityTimeline';
import TimeTracker from '@/workspace/components/TimeTracker';
import ApprovalActions from '@/workspace/components/ApprovalActions';
import RecurringConfig from '@/workspace/components/RecurringConfig';
import { cn, formatDate, PRIORITY_COLORS, STATUS_COLORS } from '@/workspace/lib/utils';
import { workspaceSend } from '@/workspace/lib/api';

function Badge({ label, cls }) {
    return <span className={cn('rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase', cls)}>{label}</span>;
}

export default function TaskDetail({ task }) {
    const { props } = usePage();
    const user = props.auth?.user;
    const isAdmin = user?.roles?.some(r => ['admin', 'ceo'].includes(r.name)) ?? false;
    const [editing, setEditing] = useState(false);
    const reload = () => router.reload({ preserveScroll: true });

    const deleteTask = async () => {
        if (!confirm('Delete this task?')) return;
        await workspaceSend(`/workspace/tasks/${task.id}`, { method: 'DELETE' });
        router.visit('/workspace/my-tasks');
    };

    const updateStatus = async (status) => {
        await workspaceSend(`/workspace/tasks/${task.id}/status`, { method: 'PATCH', body: { status } });
        reload();
    };

    return (
        <WorkspaceLayout title={task.title} subtitle={task.project?.name ?? 'Standalone Task'}>
            <div className="mb-4">
                <Link href="/workspace/my-tasks" className="inline-flex items-center gap-1.5 text-[13px] font-medium text-slate-400 hover:text-slate-600">
                    <ArrowLeft className="h-4 w-4" /> Back to tasks
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Main content */}
                <div className="lg:col-span-2 space-y-5">
                    {/* Header card */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex items-start justify-between">
                            <div>
                                <h2 className="text-[18px] font-bold text-slate-900">{task.title}</h2>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    <Badge label={task.status?.replace('_', ' ')} cls={STATUS_COLORS[task.status]} />
                                    <Badge label={task.priority} cls={PRIORITY_COLORS[task.priority]} />
                                    {task.deadline && <Badge label={`Due: ${formatDate(task.deadline)}`} cls="bg-slate-100 text-slate-600" />}
                                </div>
                            </div>
                            <div className="flex gap-1">
                                <button onClick={() => setEditing(true)} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                    <Pencil className="h-4 w-4" />
                                </button>
                                <button onClick={deleteTask} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-500">
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        {task.description && <p className="mt-3 text-[13.5px] leading-relaxed text-slate-600 whitespace-pre-wrap">{task.description}</p>}

                        {/* Status actions */}
                        <div className="mt-4 flex flex-wrap gap-2">
                            {['pending', 'in_progress', 'review', 'completed'].filter(s => s !== task.status).map(s => (
                                <button key={s} onClick={() => updateStatus(s)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] font-semibold text-slate-500 hover:bg-slate-50 capitalize">
                                    {s.replace('_', ' ')}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Subtasks */}
                    {task.subtasks?.length > 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                            <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Subtasks ({task.subtasks.length})</h4>
                            <div className="space-y-2">
                                {task.subtasks.map(st => (
                                    <div key={st.id} className="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2">
                                        <button onClick={() => workspaceSend(`/workspace/tasks/${task.id}/subtasks/${st.id}`, { method: 'PATCH', body: { status: st.status === 'completed' ? 'pending' : 'completed' } }).then(reload)}
                                            className={cn('grid h-5 w-5 shrink-0 place-items-center rounded border', st.status === 'completed' ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300')}>
                                            {st.status === 'completed' && <CheckCircle2 className="h-3 w-3" />}
                                        </button>
                                        <span className={cn('text-[13px]', st.status === 'completed' ? 'text-slate-400 line-through' : 'text-slate-700')}>{st.title}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Checklist */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <ChecklistEditor taskId={task.id} items={task.checklists ?? []} onUpdate={reload} />
                    </div>

                    {/* Comments */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <CommentThread taskId={task.id} comments={task.comments ?? []} onUpdate={reload} />
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-5">
                    {/* Approval Actions */}
                    <ApprovalActions task={task} isAdmin={isAdmin} onUpdate={reload} />

                    {/* Recurring Config */}
                    {task.is_recurring || task.recurring_config ? (
                        <RecurringConfig task={task} onUpdate={reload} />
                    ) : null}

                    {/* Time Tracker */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <TimeTracker taskId={task.id} entries={task.time_entries ?? []} onUpdate={reload} />
                    </div>

                    {/* Assignees */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Assignees</h4>
                        {task.assignees?.length > 0 ? (
                            <div className="space-y-2">
                                {task.assignees.map(a => (
                                    <div key={a.id} className="flex items-center gap-2.5">
                                        <div className="grid h-7 w-7 place-items-center rounded-full bg-gradient-to-br from-indigo-400 to-violet-400 text-[10px] font-bold text-white">
                                            {a.user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                                        </div>
                                        <span className="text-[13px] font-medium text-slate-700">{a.user?.name ?? 'Unknown'}</span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-[12.5px] text-slate-400">No one assigned.</p>
                        )}
                    </div>

                    {/* Attachments */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Attachments ({task.attachments?.length ?? 0})</h4>
                        {task.attachments?.length > 0 ? (
                            <div className="space-y-1.5">
                                {task.attachments.map(a => (
                                    <a key={a.id} href={`/storage/${a.file_path}`} target="_blank" rel="noopener" className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-[12.5px] font-medium text-indigo-600 hover:bg-indigo-50">
                                        {a.file_name}
                                    </a>
                                ))}
                            </div>
                        ) : (
                            <p className="text-[12.5px] text-slate-400">No attachments.</p>
                        )}
                    </div>

                    {/* Activity Log */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <ActivityTimeline logs={task.activity_logs ?? []} />
                    </div>
                </div>
            </div>

            {editing && <TaskForm task={task} onSaved={() => { setEditing(false); reload(); }} onClose={() => setEditing(false)} />}
        </WorkspaceLayout>
    );
}

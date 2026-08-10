import { Calendar, Paperclip, CheckSquare, MessageSquare } from 'lucide-react';
import { cn, formatDate, PRIORITY_COLORS, STATUS_COLORS } from '@/workspace/lib/utils';

export default function TaskCard({ task, onClick }) {
    const checkDone = task.checklists?.filter(c => c.is_completed).length ?? 0;
    const checkTotal = task.checklists_count ?? task.checklists?.length ?? 0;

    return (
        <div
            onClick={() => onClick?.(task)}
            className="cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md"
        >
            <div className="flex items-start justify-between gap-2">
                <h4 className="text-[13.5px] font-semibold text-slate-900 line-clamp-2">{task.title}</h4>
                <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', PRIORITY_COLORS[task.priority] ?? PRIORITY_COLORS.medium)}>
                    {task.priority}
                </span>
            </div>

            {task.tags?.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {task.tags.slice(0, 3).map((tag, i) => (
                        <span key={i} className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{tag}</span>
                    ))}
                </div>
            )}

            <div className="mt-3 flex items-center gap-3 text-[11px] text-slate-400">
                {task.deadline && (
                    <span className={cn('flex items-center gap-1', new Date(task.deadline) < new Date() && task.status !== 'completed' ? 'text-red-500 font-semibold' : '')}>
                        <Calendar className="h-3 w-3" /> {formatDate(task.deadline)}
                    </span>
                )}
                {checkTotal > 0 && (
                    <span className="flex items-center gap-1">
                        <CheckSquare className="h-3 w-3" /> {checkDone}/{checkTotal}
                    </span>
                )}
                {(task.attachments_count ?? 0) > 0 && (
                    <span className="flex items-center gap-1">
                        <Paperclip className="h-3 w-3" /> {task.attachments_count}
                    </span>
                )}
                {(task.comments_count ?? 0) > 0 && (
                    <span className="flex items-center gap-1">
                        <MessageSquare className="h-3 w-3" /> {task.comments_count}
                    </span>
                )}
            </div>

            {task.assignees?.length > 0 && (
                <div className="mt-3 flex -space-x-1.5">
                    {task.assignees.slice(0, 3).map((a) => (
                        <div key={a.id} className="grid h-6 w-6 place-items-center rounded-full bg-gradient-to-br from-indigo-400 to-violet-400 text-[9px] font-bold text-white ring-2 ring-white" title={a.user?.name}>
                            {a.user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                        </div>
                    ))}
                    {task.assignees.length > 3 && (
                        <div className="grid h-6 w-6 place-items-center rounded-full bg-slate-200 text-[9px] font-bold text-slate-500 ring-2 ring-white">
                            +{task.assignees.length - 3}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

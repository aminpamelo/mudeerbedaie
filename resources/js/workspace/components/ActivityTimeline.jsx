import { formatDate } from '@/workspace/lib/utils';

const ACTION_LABELS = {
    created: 'created this task',
    status_changed: 'changed status',
    updated: 'updated',
    assigned: 'assigned task',
    commented: 'added a comment',
    subtask_added: 'added subtask',
    attachment_added: 'uploaded file',
};

export default function ActivityTimeline({ logs = [] }) {
    return (
        <div>
            <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Activity</h4>
            <div className="space-y-2 max-h-48 overflow-y-auto">
                {logs.map((log) => (
                    <div key={log.id} className="flex items-start gap-2.5 text-[12.5px]">
                        <div className="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" />
                        <div className="flex-1">
                            <span className="font-semibold text-slate-700">{log.user?.name ?? 'System'}</span>
                            {' '}
                            <span className="text-slate-500">{ACTION_LABELS[log.action] ?? log.action}</span>
                            {log.field && <span className="text-slate-400"> ({log.field})</span>}
                            {log.old_value && log.new_value && (
                                <span className="text-slate-400"> from <span className="text-slate-500">{log.old_value}</span> to <span className="font-medium text-indigo-600">{log.new_value}</span></span>
                            )}
                            {!log.old_value && log.new_value && (
                                <span className="text-slate-500">: {log.new_value}</span>
                            )}
                        </div>
                        <span className="shrink-0 text-[11px] text-slate-400">{formatDate(log.created_at)}</span>
                    </div>
                ))}
                {logs.length === 0 && <p className="text-[12.5px] text-slate-400">No activity yet.</p>}
            </div>
        </div>
    );
}

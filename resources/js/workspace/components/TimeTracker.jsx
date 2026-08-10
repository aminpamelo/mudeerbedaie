import { useState, useEffect } from 'react';
import { Play, Pause, Clock } from 'lucide-react';
import { workspaceSend } from '@/workspace/lib/api';

export default function TimeTracker({ taskId, entries = [], onUpdate }) {
    const [running, setRunning] = useState(null);
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        const active = entries.find(e => !e.ended_at);
        setRunning(active ?? null);
    }, [entries]);

    useEffect(() => {
        if (!running) { setElapsed(0); return; }
        const start = new Date(running.started_at).getTime();
        const tick = () => setElapsed(Math.floor((Date.now() - start) / 1000));
        tick();
        const id = setInterval(tick, 1000);
        return () => clearInterval(id);
    }, [running]);

    const fmt = (s) => {
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        return `${h > 0 ? h + 'h ' : ''}${m}m ${sec}s`;
    };

    const totalSeconds = entries.reduce((sum, e) => sum + (e.duration_seconds ?? 0), 0) + (running ? elapsed : 0);

    const startTimer = async () => {
        await workspaceSend(`/workspace/tasks/${taskId}/time/start`);
        onUpdate?.();
    };

    const stopTimer = async () => {
        if (!running) return;
        await workspaceSend(`/workspace/tasks/${taskId}/time/${running.id}/stop`, { method: 'PATCH' });
        onUpdate?.();
    };

    return (
        <div>
            <h4 className="text-[12px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Time Tracking</h4>
            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <Clock className="h-5 w-5 text-slate-400" />
                <div className="flex-1">
                    <p className="text-[14px] font-bold tabular-nums text-slate-900">{fmt(running ? elapsed : 0)}</p>
                    <p className="text-[11px] text-slate-400">Total: {fmt(totalSeconds)}</p>
                </div>
                {running ? (
                    <button onClick={stopTimer} className="grid h-9 w-9 place-items-center rounded-lg bg-red-100 text-red-600 hover:bg-red-200">
                        <Pause className="h-4 w-4" strokeWidth={2.4} />
                    </button>
                ) : (
                    <button onClick={startTimer} className="grid h-9 w-9 place-items-center rounded-lg bg-emerald-100 text-emerald-600 hover:bg-emerald-200">
                        <Play className="h-4 w-4" strokeWidth={2.4} />
                    </button>
                )}
            </div>
        </div>
    );
}

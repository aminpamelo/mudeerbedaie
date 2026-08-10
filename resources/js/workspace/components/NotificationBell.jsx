import { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { Bell, CheckCheck, ExternalLink } from 'lucide-react';
import { workspaceJson, workspaceSend } from '@/workspace/lib/api';
import { cn } from '@/workspace/lib/utils';

const TYPE_ICONS = {
    task_assigned: { bg: 'bg-indigo-100', text: 'text-indigo-600', label: 'Assigned' },
    task_commented: { bg: 'bg-blue-100', text: 'text-blue-600', label: 'Comment' },
    task_due_today: { bg: 'bg-amber-100', text: 'text-amber-700', label: 'Due Today' },
    task_due_tomorrow: { bg: 'bg-slate-100', text: 'text-slate-600', label: 'Due Tomorrow' },
    task_overdue: { bg: 'bg-red-100', text: 'text-red-600', label: 'Overdue' },
};

function timeAgo(iso) {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

export default function NotificationBell() {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const ref = useRef(null);

    const fetchNotifications = async () => {
        try {
            const data = await workspaceJson('/workspace/notifications');
            setNotifications(data.notifications ?? []);
            setUnreadCount(data.unread_count ?? 0);
        } catch {
            // silently fail
        }
    };

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        const handleClick = (e) => {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const markRead = async (id, url) => {
        await workspaceSend(`/workspace/notifications/${id}/read`, { method: 'PATCH' });
        setNotifications((prev) => prev.filter((n) => n.id !== id));
        setUnreadCount((c) => Math.max(0, c - 1));
        if (url) {
            router.visit(url);
        }
        setOpen(false);
    };

    const markAllRead = async () => {
        await workspaceSend('/workspace/notifications/read-all', { method: 'POST' });
        setNotifications([]);
        setUnreadCount(0);
    };

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen(!open)}
                className="relative grid h-9 w-9 place-items-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            >
                <Bell className="h-[18px] w-[18px]" strokeWidth={2} />
                {unreadCount > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h3 className="text-[13px] font-bold text-slate-900">Notifications</h3>
                        {notifications.length > 0 && (
                            <button
                                onClick={markAllRead}
                                className="flex items-center gap-1 text-[11px] font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                <CheckCheck className="h-3.5 w-3.5" />
                                Mark all read
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto">
                        {notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <Bell className="mx-auto h-8 w-8 text-slate-200" strokeWidth={1.5} />
                                <p className="mt-2 text-[12.5px] text-slate-400">No new notifications</p>
                            </div>
                        ) : (
                            notifications.map((n) => {
                                const meta = TYPE_ICONS[n.data?.type] || TYPE_ICONS.task_assigned;
                                return (
                                    <button
                                        key={n.id}
                                        onClick={() => markRead(n.id, n.data?.url)}
                                        className="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
                                    >
                                        <span className={cn('mt-0.5 shrink-0 rounded-lg px-1.5 py-0.5 text-[10px] font-bold uppercase', meta.bg, meta.text)}>
                                            {meta.label}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-[12.5px] font-medium text-slate-700">
                                                {n.data?.message ?? 'New notification'}
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-slate-400">{timeAgo(n.created_at)}</p>
                                        </div>
                                        <ExternalLink className="mt-1 h-3.5 w-3.5 shrink-0 text-slate-300" />
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

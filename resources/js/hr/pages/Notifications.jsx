import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    Bell,
    BellOff,
    BellRing,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    Loader2,
    CalendarPlus,
    CalendarX,
    CalendarCheck,
    Clock,
    DollarSign,
    Receipt,
    Package,
    UserPlus,
    AlertTriangle,
    Info,
} from 'lucide-react';
import api from '../lib/api';
import usePushSubscription from '../hooks/usePushSubscription';
import PageHeader from '../components/PageHeader';
import { Card, CardContent } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { cn } from '../lib/utils';

const NOTIFICATION_ICONS = {
    'calendar-plus': CalendarPlus,
    'calendar-check': CalendarCheck,
    'calendar-x': CalendarX,
    'clock': Clock,
    'dollar-sign': DollarSign,
    'receipt': Receipt,
    'package': Package,
    'user-plus': UserPlus,
    'alert-triangle': AlertTriangle,
    'bell': Bell,
};

const NOTIFICATION_COLORS = {
    'calendar-plus': 'bg-blue-100 text-blue-600',
    'calendar-check': 'bg-green-100 text-green-600',
    'calendar-x': 'bg-red-100 text-red-600',
    'clock': 'bg-amber-100 text-amber-600',
    'dollar-sign': 'bg-emerald-100 text-emerald-600',
    'receipt': 'bg-purple-100 text-purple-600',
    'package': 'bg-orange-100 text-orange-600',
    'user-plus': 'bg-indigo-100 text-indigo-600',
    'alert-triangle': 'bg-yellow-100 text-yellow-600',
    'bell': 'bg-zinc-100 text-zinc-600',
};

function getNotificationIcon(iconName) {
    return NOTIFICATION_ICONS[iconName] || Bell;
}

function getNotificationColor(iconName) {
    return NOTIFICATION_COLORS[iconName] || 'bg-zinc-100 text-zinc-600';
}

function timeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-MY', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Notifications() {
    const navigate = useNavigate();
    const { isSubscribed, isSupported, subscribe, unsubscribe, checked } = usePushSubscription();
    const [notifications, setNotifications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [unreadCount, setUnreadCount] = useState(0);
    const [filter, setFilter] = useState('all'); // all, unread, read
    const [toggling, setToggling] = useState(false);

    const fetchNotifications = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/notifications', {
                params: { page, filter },
            });
            setNotifications(data.data || []);
            setLastPage(data.last_page || 1);
            setTotal(data.total || 0);
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
        } finally {
            setLoading(false);
        }
    }, [page, filter]);

    const fetchUnreadCount = useCallback(async () => {
        try {
            const { data } = await api.get('/notifications/unread-count');
            setUnreadCount(data.count);
        } catch (err) {
            console.error('Failed to fetch unread count:', err);
        }
    }, []);

    useEffect(() => {
        fetchNotifications();
        fetchUnreadCount();
    }, [fetchNotifications, fetchUnreadCount]);

    const markAsRead = async (id) => {
        try {
            await api.patch(`/notifications/${id}/read`);
            setNotifications(prev =>
                prev.map(n => n.id === id ? { ...n, read_at: new Date().toISOString() } : n)
            );
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch (err) {
            console.error('Failed to mark as read:', err);
        }
    };

    const markAllRead = async () => {
        try {
            await api.post('/notifications/mark-all-read');
            setNotifications(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })));
            setUnreadCount(0);
        } catch (err) {
            console.error('Failed to mark all as read:', err);
        }
    };

    const handleNotificationClick = (notification) => {
        if (!notification.read_at) {
            markAsRead(notification.id);
        }
        if (notification.data?.url) {
            navigate(notification.data.url);
        }
    };

    const handleTogglePush = async () => {
        setToggling(true);
        try {
            if (isSubscribed) {
                await unsubscribe();
            } else {
                await subscribe();
            }
        } catch (err) {
            console.error('Failed to toggle push:', err);
        } finally {
            setToggling(false);
        }
    };

    return (
        <div>
            <PageHeader
                title="Notifications"
                description="View and manage all your notifications"
                action={
                    unreadCount > 0 ? (
                        <Button variant="outline" onClick={markAllRead}>
                            <CheckCheck className="mr-2 h-4 w-4" />
                            Mark all as read
                        </Button>
                    ) : null
                }
            />

            {/* Push Notification Settings Card */}
            <Card className="mb-6">
                <CardContent className="pt-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className={cn(
                                'flex h-12 w-12 items-center justify-center rounded-xl',
                                !checked
                                    ? 'bg-zinc-100 text-zinc-400'
                                    : isSubscribed
                                    ? 'bg-green-100 text-green-600'
                                    : 'bg-zinc-100 text-zinc-400'
                            )}>
                                {isSubscribed && checked ? (
                                    <BellRing className="h-6 w-6" />
                                ) : (
                                    <BellOff className="h-6 w-6" />
                                )}
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-zinc-900">
                                    Push Notifications
                                </h3>
                                <p className="text-sm text-zinc-500">
                                    {!isSupported
                                        ? 'Push notifications are not supported in this browser.'
                                        : isSubscribed
                                        ? 'You will receive push notifications for important updates.'
                                        : 'Enable push notifications to stay updated in real-time.'}
                                </p>
                            </div>
                        </div>
                        {isSupported && (
                            <Button
                                variant={isSubscribed ? 'outline' : 'default'}
                                onClick={handleTogglePush}
                                disabled={toggling || !checked}
                                className="shrink-0"
                            >
                                {toggling || !checked ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : isSubscribed ? (
                                    <BellOff className="mr-2 h-4 w-4" />
                                ) : (
                                    <BellRing className="mr-2 h-4 w-4" />
                                )}
                                {toggling || !checked
                                    ? 'Checking...'
                                    : isSubscribed
                                    ? 'Turn Off'
                                    : 'Turn On'}
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Filter Tabs & Stats */}
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1">
                    {[
                        { key: 'all', label: 'All' },
                        { key: 'unread', label: 'Unread', count: unreadCount },
                        { key: 'read', label: 'Read' },
                    ].map(({ key, label, count }) => (
                        <button
                            key={key}
                            onClick={() => { setFilter(key); setPage(1); }}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                filter === key
                                    ? 'bg-zinc-900 text-white'
                                    : 'text-zinc-600 hover:bg-zinc-100'
                            )}
                        >
                            {label}
                            {count > 0 && (
                                <span className={cn(
                                    'flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-bold',
                                    filter === key
                                        ? 'bg-white/20 text-white'
                                        : 'bg-red-100 text-red-600'
                                )}>
                                    {count}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
                <p className="text-sm text-zinc-500">
                    {total} notification{total !== 1 ? 's' : ''}
                </p>
            </div>

            {/* Notification List */}
            <Card>
                <div className="divide-y divide-zinc-100">
                    {loading ? (
                        <div className="flex items-center justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : notifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100">
                                <Bell className="h-7 w-7 text-zinc-400" />
                            </div>
                            <p className="mt-4 text-sm font-medium text-zinc-900">
                                No notifications
                            </p>
                            <p className="mt-1 text-sm text-zinc-500">
                                {filter === 'unread'
                                    ? "You're all caught up!"
                                    : 'Notifications will appear here when you receive them.'}
                            </p>
                        </div>
                    ) : (
                        notifications.map((notification) => {
                            const iconName = notification.data?.icon || 'bell';
                            const IconComponent = getNotificationIcon(iconName);
                            const iconColor = getNotificationColor(iconName);

                            return (
                                <button
                                    key={notification.id}
                                    onClick={() => handleNotificationClick(notification)}
                                    className={cn(
                                        'flex w-full items-start gap-4 px-5 py-4 text-left transition-colors hover:bg-zinc-50',
                                        !notification.read_at && 'bg-blue-50/40'
                                    )}
                                >
                                    {/* Icon */}
                                    <div className={cn(
                                        'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                        iconColor
                                    )}>
                                        <IconComponent className="h-5 w-5" />
                                    </div>

                                    {/* Content */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className={cn(
                                                'text-sm',
                                                !notification.read_at
                                                    ? 'font-semibold text-zinc-900'
                                                    : 'font-medium text-zinc-700'
                                            )}>
                                                {notification.data?.title || 'Notification'}
                                            </p>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {!notification.read_at && (
                                                    <div className="h-2 w-2 rounded-full bg-blue-500" />
                                                )}
                                                <span className="whitespace-nowrap text-xs text-zinc-400">
                                                    {timeAgo(notification.created_at)}
                                                </span>
                                            </div>
                                        </div>
                                        <p className="mt-0.5 text-sm text-zinc-500">
                                            {notification.data?.body || ''}
                                        </p>
                                        <p className="mt-1 text-xs text-zinc-400">
                                            {formatDate(notification.created_at)}
                                        </p>
                                    </div>
                                </button>
                            );
                        })
                    )}
                </div>

                {/* Pagination */}
                {lastPage > 1 && (
                    <div className="flex items-center justify-between border-t border-zinc-200 px-5 py-3">
                        <p className="text-sm text-zinc-500">
                            Page {page} of {lastPage}
                        </p>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={page <= 1}
                                onClick={() => setPage(p => p - 1)}
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={page >= lastPage}
                                onClick={() => setPage(p => p + 1)}
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </Card>
        </div>
    );
}

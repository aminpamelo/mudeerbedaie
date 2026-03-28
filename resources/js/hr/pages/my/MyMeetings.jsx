import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    CalendarDays,
    Clock,
    MapPin,
    Users,
    Loader2,
} from 'lucide-react';
import { fetchMyMeetings } from '../../lib/api';
import { Badge } from '../../components/ui/badge';
import { Card, CardContent } from '../../components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '../../components/ui/tabs';

const STATUS_BADGE = {
    draft: { label: 'Draft', variant: 'secondary' },
    scheduled: { label: 'Scheduled', className: 'bg-blue-100 text-blue-800 border-transparent' },
    in_progress: { label: 'In Progress', variant: 'warning' },
    completed: { label: 'Completed', variant: 'success' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-MY', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(t) {
    if (!t) return '';
    return t.slice(0, 5);
}

export default function MyMeetings() {
    const [tab, setTab] = useState('upcoming');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'my-meetings', tab],
        queryFn: () => fetchMyMeetings({ filter: tab }),
    });

    const meetings = data?.data || [];

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-zinc-900">My Meetings</h1>
                <p className="mt-1 text-sm text-zinc-500">View meetings you are invited to or organizing.</p>
            </div>

            <Tabs value={tab} onValueChange={setTab}>
                <TabsList className="mb-4">
                    <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
                    <TabsTrigger value="past">Past</TabsTrigger>
                    <TabsTrigger value="all">All</TabsTrigger>
                </TabsList>
            </Tabs>

            {isLoading ? (
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                </div>
            ) : meetings.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <CalendarDays className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No meetings</h3>
                        <p className="mt-1 text-sm text-zinc-500">You have no {tab} meetings.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {meetings.map((meeting) => {
                        const config = STATUS_BADGE[meeting.status] || { label: meeting.status, variant: 'secondary' };
                        return (
                            <Card key={meeting.id}>
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <h3 className="font-semibold text-zinc-900">{meeting.title}</h3>
                                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                                                <span className="flex items-center gap-1">
                                                    <CalendarDays className="h-3.5 w-3.5" />
                                                    {formatDate(meeting.date)}
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    {formatTime(meeting.start_time)}
                                                    {meeting.end_time && ` - ${formatTime(meeting.end_time)}`}
                                                </span>
                                                {meeting.location && (
                                                    <span className="flex items-center gap-1">
                                                        <MapPin className="h-3.5 w-3.5" />
                                                        {meeting.location}
                                                    </span>
                                                )}
                                                <span className="flex items-center gap-1">
                                                    <Users className="h-3.5 w-3.5" />
                                                    {meeting.attendees_count ?? meeting.attendees?.length ?? 0} attendees
                                                </span>
                                            </div>
                                        </div>
                                        <Badge variant={config.variant} className={config.className}>
                                            {config.label}
                                        </Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

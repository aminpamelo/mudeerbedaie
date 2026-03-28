import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    Plus,
    Eye,
    Pencil,
    Trash2,
    CalendarDays,
    Users,
    ListTodo,
    ChevronLeft,
    ChevronRight,
    ListOrdered,
} from 'lucide-react';
import { fetchMeetings, fetchMeetingSeries, deleteMeeting } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
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
import { Tabs, TabsList, TabsTrigger, TabsContent } from '../../components/ui/tabs';
import ConfirmDialog from '../../components/ConfirmDialog';

const STATUS_BADGE = {
    draft: { label: 'Draft', variant: 'secondary' },
    scheduled: { label: 'Scheduled', className: 'bg-blue-100 text-blue-800 border-transparent' },
    in_progress: { label: 'In Progress', variant: 'warning' },
    completed: { label: 'Completed', variant: 'success' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

function MeetingStatusBadge({ status }) {
    const config = STATUS_BADGE[status] || { label: status, variant: 'secondary' };
    return <Badge variant={config.variant} className={config.className}>{config.label}</Badge>;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatTime(timeString) {
    if (!timeString) return '-';
    return timeString.slice(0, 5);
}

function SkeletonTable() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-48 animate-pulse rounded bg-zinc-200" />
                        <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
                    </div>
                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

function EmptyState({ hasFilters, onClearFilters }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <CalendarDays className="mb-4 h-12 w-12 text-zinc-300" />
            <h3 className="text-lg font-semibold text-zinc-900">
                {hasFilters ? 'No meetings found' : 'No meetings yet'}
            </h3>
            <p className="mt-1 text-sm text-zinc-500">
                {hasFilters
                    ? 'Try adjusting your search or filters.'
                    : 'Get started by scheduling your first meeting.'}
            </p>
            <div className="mt-4 flex gap-2">
                {hasFilters && (
                    <Button variant="outline" onClick={onClearFilters}>
                        Clear Filters
                    </Button>
                )}
                <Button asChild>
                    <Link to="/meetings/create">
                        <Plus className="mr-1.5 h-4 w-4" />
                        Create Meeting
                    </Link>
                </Button>
            </div>
        </div>
    );
}

export default function MeetingList() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('all');
    const [seriesFilter, setSeriesFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [deleteTarget, setDeleteTarget] = useState(null);

    const hasFilters = search !== '' || seriesFilter !== 'all';

    const params = {
        search: search || undefined,
        status: tab !== 'all' ? tab : undefined,
        series_id: seriesFilter !== 'all' ? seriesFilter : undefined,
        page,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'meetings', params],
        queryFn: () => fetchMeetings(params),
    });

    const { data: seriesData } = useQuery({
        queryKey: ['hr', 'meeting-series'],
        queryFn: fetchMeetingSeries,
    });

    const deleteMut = useMutation({
        mutationFn: (id) => deleteMeeting(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'meetings'] });
            setDeleteTarget(null);
        },
    });

    const meetings = data?.data || [];
    const pagination = data?.meta || data || {};
    const lastPage = pagination.last_page || 1;
    const seriesList = seriesData?.data || seriesData || [];

    const resetPage = useCallback(() => setPage(1), []);

    function handleSearchChange(value) {
        setSearch(value);
        resetPage();
    }

    function clearFilters() {
        setSearch('');
        setSeriesFilter('all');
        resetPage();
    }

    return (
        <div>
            <PageHeader
                title="Meetings"
                description="Schedule and manage meetings, track minutes and action items."
                action={
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link to="/meetings/series">
                                <ListOrdered className="mr-1.5 h-4 w-4" />
                                Series
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link to="/meetings/tasks">
                                <ListTodo className="mr-1.5 h-4 w-4" />
                                Tasks
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link to="/meetings/create">
                                <Plus className="mr-1.5 h-4 w-4" />
                                Create Meeting
                            </Link>
                        </Button>
                    </div>
                }
            />

            <Tabs value={tab} onValueChange={(v) => { setTab(v); resetPage(); }}>
                <TabsList className="mb-4">
                    <TabsTrigger value="all">All</TabsTrigger>
                    <TabsTrigger value="scheduled">Upcoming</TabsTrigger>
                    <TabsTrigger value="completed">Past</TabsTrigger>
                    <TabsTrigger value="draft">Draft</TabsTrigger>
                </TabsList>
            </Tabs>

            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <SearchInput
                            value={search}
                            onChange={handleSearchChange}
                            placeholder="Search meetings..."
                            className="lg:w-80"
                        />
                        <Select value={seriesFilter} onValueChange={(v) => { setSeriesFilter(v); resetPage(); }}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="All Series" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Series</SelectItem>
                                {seriesList.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <SkeletonTable />
                    ) : meetings.length === 0 ? (
                        <EmptyState hasFilters={hasFilters} onClearFilters={clearFilters} />
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Time</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Organizer</TableHead>
                                        <TableHead className="text-center">Attendees</TableHead>
                                        <TableHead className="text-center">Tasks</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {meetings.map((meeting) => (
                                        <TableRow key={meeting.id}>
                                            <TableCell>
                                                <Link
                                                    to={`/meetings/${meeting.id}`}
                                                    className="font-medium text-zinc-900 hover:text-blue-600 hover:underline"
                                                >
                                                    {meeting.title}
                                                </Link>
                                                {meeting.series && (
                                                    <span className="ml-2 text-xs text-zinc-400">
                                                        {meeting.series.name}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>{formatDate(meeting.date)}</TableCell>
                                            <TableCell>
                                                {formatTime(meeting.start_time)}
                                                {meeting.end_time && ` - ${formatTime(meeting.end_time)}`}
                                            </TableCell>
                                            <TableCell>
                                                <MeetingStatusBadge status={meeting.status} />
                                            </TableCell>
                                            <TableCell>
                                                {meeting.organizer?.full_name || '-'}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex items-center justify-center gap-1">
                                                    <Users className="h-3.5 w-3.5 text-zinc-400" />
                                                    <span>{meeting.attendees_count ?? meeting.attendees?.length ?? 0}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex items-center justify-center gap-1">
                                                    <ListTodo className="h-3.5 w-3.5 text-zinc-400" />
                                                    <span>{meeting.tasks_count ?? meeting.tasks?.length ?? 0}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => navigate(`/meetings/${meeting.id}`)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => navigate(`/meetings/${meeting.id}/edit`)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => setDeleteTarget(meeting)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-500" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {lastPage > 1 && (
                                <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                                    <p className="text-sm text-zinc-500">
                                        Page {page} of {lastPage}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page <= 1}
                                            onClick={() => setPage((p) => p - 1)}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={page >= lastPage}
                                            onClick={() => setPage((p) => p + 1)}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={() => setDeleteTarget(null)}
                title="Delete Meeting"
                description={`Are you sure you want to delete "${deleteTarget?.title}"? This action cannot be undone.`}
                confirmLabel="Delete"
                loading={deleteMut.isPending}
                onConfirm={() => deleteMut.mutate(deleteTarget.id)}
            />
        </div>
    );
}

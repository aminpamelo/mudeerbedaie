import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    ArrowLeft,
    Plus,
    ListOrdered,
    ChevronRight,
    Loader2,
} from 'lucide-react';
import { fetchMeetingSeries, createMeetingSeries, fetchMeetingSeriesDetail } from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '../../components/ui/dialog';

export default function MeetingSeriesList() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ name: '', description: '' });
    const [expanded, setExpanded] = useState(null);

    const { data: seriesData, isLoading } = useQuery({
        queryKey: ['hr', 'meeting-series'],
        queryFn: fetchMeetingSeries,
    });

    const { data: expandedData } = useQuery({
        queryKey: ['hr', 'meeting-series', expanded],
        queryFn: () => fetchMeetingSeriesDetail(expanded),
        enabled: !!expanded,
    });

    const seriesList = seriesData?.data || seriesData || [];
    const expandedSeries = expandedData?.data || expandedData;

    const createMut = useMutation({
        mutationFn: (data) => createMeetingSeries(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'meeting-series'] });
            setShowCreate(false);
            setForm({ name: '', description: '' });
        },
    });

    return (
        <div>
            <PageHeader
                title="Meeting Series"
                description="Organize recurring meetings into series."
                action={
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => navigate('/meetings')}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Back to Meetings
                        </Button>
                        <Button onClick={() => setShowCreate(true)}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            Create Series
                        </Button>
                    </div>
                }
            />

            {isLoading ? (
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-zinc-400" />
                </div>
            ) : seriesList.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <ListOrdered className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">No series yet</h3>
                        <p className="mt-1 text-sm text-zinc-500">Create a series to organize recurring meetings.</p>
                        <Button className="mt-4" onClick={() => setShowCreate(true)}>
                            <Plus className="mr-1.5 h-4 w-4" />
                            Create Series
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {seriesList.map((series) => (
                        <Card key={series.id}>
                            <CardContent className="p-4">
                                <button
                                    className="flex w-full items-center justify-between text-left"
                                    onClick={() => setExpanded(expanded === series.id ? null : series.id)}
                                >
                                    <div>
                                        <h3 className="font-semibold text-zinc-900">{series.name}</h3>
                                        {series.description && (
                                            <p className="mt-0.5 text-sm text-zinc-500">{series.description}</p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary">
                                            {series.meetings_count ?? 0} meetings
                                        </Badge>
                                        {expanded === series.id ? (
                                            <ChevronRight className="h-4 w-4 rotate-90 text-zinc-400 transition-transform" />
                                        ) : (
                                            <ChevronRight className="h-4 w-4 text-zinc-400" />
                                        )}
                                    </div>
                                </button>

                                {expanded === series.id && expandedSeries && (
                                    <div className="mt-4 space-y-2 border-t border-zinc-100 pt-4">
                                        {(expandedSeries.meetings || []).length === 0 ? (
                                            <p className="text-sm text-zinc-500">No meetings in this series.</p>
                                        ) : (
                                            (expandedSeries.meetings || []).map((m) => (
                                                <Link
                                                    key={m.id}
                                                    to={`/meetings/${m.id}`}
                                                    className="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-zinc-50"
                                                >
                                                    <div>
                                                        <p className="text-sm font-medium text-zinc-900">{m.title}</p>
                                                        <p className="text-xs text-zinc-500">{m.date}</p>
                                                    </div>
                                                    <Badge variant="secondary">{m.status}</Badge>
                                                </Link>
                                            ))
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            <Dialog open={showCreate} onOpenChange={setShowCreate}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Meeting Series</DialogTitle>
                        <DialogDescription>Group recurring meetings under a series name.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="name">Name *</Label>
                            <Input
                                id="name"
                                value={form.name}
                                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                placeholder="e.g. Weekly Team Standup"
                            />
                        </div>
                        <div>
                            <Label htmlFor="desc">Description</Label>
                            <Textarea
                                id="desc"
                                value={form.description}
                                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowCreate(false)}>Cancel</Button>
                        <Button
                            onClick={() => createMut.mutate(form)}
                            disabled={createMut.isPending || !form.name.trim()}
                        >
                            {createMut.isPending ? 'Creating...' : 'Create'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

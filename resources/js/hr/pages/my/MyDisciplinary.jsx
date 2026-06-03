import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    AlertTriangle,
    FileText,
    Loader2,
    MessageSquare,
    ShieldAlert,
    Clock,
    CheckCircle2,
    XCircle,
} from 'lucide-react';
import { fetchMyDisciplinary, respondToDisciplinary } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../components/ui/card';
import { StatCard } from '../../components/ui/stat-card';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '../../components/ui/dialog';

const STATUS_CONFIG = {
    issued: { label: 'Issued', variant: 'outline', className: 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-500/25 dark:bg-blue-500/15 dark:text-blue-300' },
    pending_response: { label: 'Pending Response', variant: 'warning', className: 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/15 dark:text-amber-300' },
    responded: { label: 'Responded', variant: 'success', className: 'border-green-300 bg-green-50 text-green-700 dark:border-green-500/25 dark:bg-green-500/15 dark:text-green-300' },
    closed: { label: 'Closed', variant: 'outline', className: 'border-slate-300 bg-slate-50 text-slate-600 dark:border-white/[0.07] dark:bg-white/[0.04] dark:text-slate-300' },
};

const TYPE_LABELS = {
    verbal_warning: 'Verbal Warning',
    written_warning: 'Written Warning',
    show_cause: 'Show Cause',
    suspension: 'Suspension',
    termination: 'Termination',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function StatusBadge({ status }) {
    const config = STATUS_CONFIG[status] || STATUS_CONFIG.issued;
    return (
        <Badge variant={config.variant} className={cn('text-[10px]', config.className)}>
            {config.label}
        </Badge>
    );
}

export default function MyDisciplinary() {
    const queryClient = useQueryClient();
    const [respondDialog, setRespondDialog] = useState(false);
    const [selectedRecord, setSelectedRecord] = useState(null);
    const [responseText, setResponseText] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'me', 'disciplinary'],
        queryFn: fetchMyDisciplinary,
    });

    const records = data?.data ?? [];

    const respondMutation = useMutation({
        mutationFn: ({ id, data }) => respondToDisciplinary(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'me', 'disciplinary'] });
            setRespondDialog(false);
            setSelectedRecord(null);
            setResponseText('');
        },
    });

    function openRespondDialog(record) {
        setSelectedRecord(record);
        setResponseText('');
        setRespondDialog(true);
    }

    function handleSubmitResponse() {
        if (!responseText.trim() || !selectedRecord) return;
        respondMutation.mutate({
            id: selectedRecord.id,
            data: { response: responseText },
        });
    }

    const summaryCards = [
        { label: 'Total Records', value: records.length, icon: FileText, accent: 'slate' },
        { label: 'Pending Response', value: records.filter((r) => r.status === 'pending_response').length, icon: Clock, accent: 'amber' },
        { label: 'Responded', value: records.filter((r) => r.status === 'responded').length, icon: CheckCircle2, accent: 'emerald' },
        { label: 'Closed', value: records.filter((r) => r.status === 'closed').length, icon: XCircle, accent: 'slate' },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title="My Disciplinary Records"
                description="View your disciplinary records and respond to show cause notices"
            />

            {/* Summary Cards */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {summaryCards.map((card) => (
                    <StatCard key={card.label} label={card.label} value={card.value} icon={card.icon} accent={card.accent} />
                ))}
            </div>
            {/* Records Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Disciplinary Records</CardTitle>
                    <CardDescription>{records.length} record(s)</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-white/[0.08]" />
                                    <div className="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-white/[0.08]" />
                                    <div className="h-4 w-40 animate-pulse rounded bg-slate-200 dark:bg-white/[0.08]" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-slate-200 dark:bg-white/[0.08]" />
                                    <div className="flex-1" />
                                    <div className="h-8 w-20 animate-pulse rounded bg-slate-200 dark:bg-white/[0.08]" />
                                </div>
                            ))}
                        </div>
                    ) : records.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <ShieldAlert className="mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
                            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No disciplinary records</p>
                            <p className="text-xs text-slate-400 dark:text-slate-500">You have a clean record</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference #</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Incident Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell className="font-medium">
                                            {record.reference_number || `#${record.id}`}
                                        </TableCell>
                                        <TableCell>
                                            {TYPE_LABELS[record.type] || record.type}
                                        </TableCell>
                                        <TableCell className="max-w-[200px] truncate">
                                            {record.reason || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(record.incident_date)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={record.status} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {record.status === 'pending_response' ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => openRespondDialog(record)}
                                                >
                                                    <MessageSquare className="mr-1 h-4 w-4" />
                                                    Respond
                                                </Button>
                                            ) : (
                                                <span className="text-xs text-slate-400 dark:text-slate-500">-</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Respond Dialog */}
            <Dialog open={respondDialog} onOpenChange={setRespondDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Respond to Disciplinary Notice</DialogTitle>
                        <DialogDescription>
                            {selectedRecord && (
                                <>
                                    Reference: {selectedRecord.reference_number || `#${selectedRecord.id}`}
                                    {' | '}
                                    Type: {TYPE_LABELS[selectedRecord.type] || selectedRecord.type}
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedRecord && (
                        <div className="space-y-4">
                            {/* Notice Details */}
                            <div className="rounded-lg bg-amber-50 p-4 dark:bg-amber-500/15">
                                <div className="flex items-start gap-2">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-600 shrink-0 dark:text-amber-400" />
                                    <div>
                                        <p className="text-sm font-medium text-amber-800 dark:text-amber-300">Reason</p>
                                        <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">{selectedRecord.reason}</p>
                                    </div>
                                </div>
                                {selectedRecord.incident_date && (
                                    <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                        Incident Date: {formatDate(selectedRecord.incident_date)}
                                    </p>
                                )}
                            </div>

                            {/* Response Input */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Your Response
                                </label>
                                <textarea
                                    value={responseText}
                                    onChange={(e) => setResponseText(e.target.value)}
                                    placeholder="Provide your written response to this notice..."
                                    rows={5}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:border-white/[0.10] dark:bg-white/[0.05] dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                    Please provide a detailed response. This will be reviewed by HR.
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRespondDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSubmitResponse}
                            disabled={!responseText.trim() || respondMutation.isPending}
                        >
                            {respondMutation.isPending ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <MessageSquare className="mr-2 h-4 w-4" />
                            )}
                            Submit Response
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

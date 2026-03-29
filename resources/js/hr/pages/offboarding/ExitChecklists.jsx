import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ClipboardList,
    Loader2,
    Eye,
    CheckSquare,
    Square,
} from 'lucide-react';
import {
    fetchExitChecklists,
    fetchExitChecklist,
    updateExitChecklistItem,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import SearchInput from '../../components/SearchInput';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
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
} from '../../components/ui/dialog';
import {
    Select,
    SelectTrigger,
    SelectContent,
    SelectItem,
    SelectValue,
} from '../../components/ui/select';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All Status' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
];

const STATUS_CONFIG = {
    pending: { label: 'Pending', variant: 'warning' },
    in_progress: { label: 'In Progress', variant: 'default' },
    completed: { label: 'Completed', variant: 'success' },
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function ProgressBar({ completed, total }) {
    const percent = total > 0 ? Math.round((completed / total) * 100) : 0;
    return (
        <div className="flex items-center gap-2">
            <div className="h-2 w-24 overflow-hidden rounded-full bg-zinc-100">
                <div
                    className={cn(
                        'h-full rounded-full transition-all',
                        percent === 100 ? 'bg-emerald-500' : percent > 0 ? 'bg-blue-500' : 'bg-zinc-300'
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
            <span className="text-xs text-zinc-500">{percent}%</span>
        </div>
    );
}

export default function ExitChecklists() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [detailDialog, setDetailDialog] = useState({ open: false, id: null });

    const params = {
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
    };

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'checklists', params],
        queryFn: () => fetchExitChecklists(params),
    });

    const { data: detailData, isLoading: detailLoading } = useQuery({
        queryKey: ['hr', 'offboarding', 'checklist', detailDialog.id],
        queryFn: () => fetchExitChecklist(detailDialog.id),
        enabled: !!detailDialog.id,
    });

    const updateItemMutation = useMutation({
        mutationFn: ({ checklistId, itemId, data }) => updateExitChecklistItem(checklistId, itemId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'checklist', detailDialog.id] });
            queryClient.invalidateQueries({ queryKey: ['hr', 'offboarding', 'checklists'] });
        },
    });

    const checklists = data?.data || [];
    const checklist = detailData?.data;

    function getStatusBadge(status) {
        const config = STATUS_CONFIG[status] || { label: status, variant: 'secondary' };
        return <Badge variant={config.variant}>{config.label}</Badge>;
    }

    function handleToggleItem(checklistId, itemId, currentCompleted) {
        updateItemMutation.mutate({
            checklistId,
            itemId,
            data: { is_completed: !currentCompleted },
        });
    }

    function formatCategory(category) {
        return category
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
    }

    // Group items by category
    function groupItemsByCategory(items) {
        if (!items || items.length === 0) return {};
        const grouped = {};
        for (const item of items) {
            const category = item.category || 'General';
            if (!grouped[category]) {
                grouped[category] = [];
            }
            grouped[category].push(item);
        }
        return grouped;
    }

    const groupedItems = checklist?.items ? groupItemsByCategory(checklist.items) : {};

    return (
        <div>
            <PageHeader
                title="Exit Checklists"
                description="Track offboarding checklist completion for departing employees."
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-end gap-4">
                        <SearchInput
                            value={search}
                            onChange={setSearch}
                            placeholder="Search employee..."
                            className="w-64"
                        />
                        <div>
                            <label className="mb-1 block text-xs font-medium text-zinc-600">Status</label>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            {isLoading ? (
                <div className="flex justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                </div>
            ) : checklists.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <ClipboardList className="mb-3 h-10 w-10 text-zinc-300" />
                        <p className="text-sm font-medium text-zinc-500">No exit checklists found</p>
                        <p className="text-xs text-zinc-400">Checklists are created when a resignation is approved.</p>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Progress</TableHead>
                                    <TableHead>Created Date</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {checklists.map((checklist) => {
                                    const completed = checklist.completed_items ?? 0;
                                    const total = checklist.total_items ?? 0;
                                    return (
                                        <TableRow key={checklist.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="text-sm font-medium text-zinc-900">
                                                        {checklist.employee?.full_name || '-'}
                                                    </p>
                                                    <p className="text-xs text-zinc-500">
                                                        {checklist.employee?.employee_id || ''}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>{getStatusBadge(checklist.status)}</TableCell>
                                            <TableCell>
                                                <div>
                                                    <ProgressBar completed={completed} total={total} />
                                                    <span className="text-xs text-zinc-500">
                                                        {completed}/{total} items
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatDate(checklist.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setDetailDialog({ open: true, id: checklist.id })}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            {/* Checklist Detail Dialog */}
            <Dialog open={detailDialog.open} onOpenChange={() => setDetailDialog({ open: false, id: null })}>
                <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Exit Checklist Details</DialogTitle>
                        <DialogDescription>
                            {checklist?.employee?.full_name
                                ? `Checklist for ${checklist.employee.full_name}`
                                : 'View and update checklist items.'}
                        </DialogDescription>
                    </DialogHeader>

                    {detailLoading ? (
                        <div className="flex justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : checklist ? (
                        <div className="space-y-6">
                            {/* Summary */}
                            <div className="flex items-center justify-between rounded-lg bg-zinc-50 p-4">
                                <div>
                                    <p className="text-sm font-medium text-zinc-900">
                                        {checklist.completed_items ?? 0} of {checklist.total_items ?? 0} completed
                                    </p>
                                    <p className="text-xs text-zinc-500">
                                        {getStatusBadge(checklist.status)}
                                    </p>
                                </div>
                                <ProgressBar
                                    completed={checklist.completed_items ?? 0}
                                    total={checklist.total_items ?? 0}
                                />
                            </div>

                            {/* Items Grouped by Category */}
                            {Object.keys(groupedItems).length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-sm text-zinc-400">
                                    No checklist items found.
                                </div>
                            ) : (
                                Object.entries(groupedItems).map(([category, items]) => (
                                    <div key={category}>
                                        <h4 className="mb-3 text-sm font-semibold text-zinc-900">{formatCategory(category)}</h4>
                                        <div className="space-y-2">
                                            {items.map((item) => (
                                                <div
                                                    key={item.id}
                                                    className={cn(
                                                        'flex items-start gap-3 rounded-lg border p-3 transition-colors',
                                                        item.is_completed
                                                            ? 'border-emerald-200 bg-emerald-50/50'
                                                            : 'border-zinc-200 bg-white'
                                                    )}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => handleToggleItem(checklist.id, item.id, item.is_completed)}
                                                        disabled={updateItemMutation.isPending}
                                                        className="mt-0.5 shrink-0"
                                                    >
                                                        {item.is_completed ? (
                                                            <CheckSquare className="h-5 w-5 text-emerald-600" />
                                                        ) : (
                                                            <Square className="h-5 w-5 text-zinc-400 hover:text-zinc-600" />
                                                        )}
                                                    </button>
                                                    <div className="flex-1">
                                                        <p className={cn(
                                                            'text-sm font-medium',
                                                            item.is_completed ? 'text-zinc-500 line-through' : 'text-zinc-900'
                                                        )}>
                                                            {item.name || item.title}
                                                        </p>
                                                        {item.description && (
                                                            <p className="mt-0.5 text-xs text-zinc-500">
                                                                {item.description}
                                                            </p>
                                                        )}
                                                        {item.assigned_to && (
                                                            <p className="mt-1 text-xs text-zinc-400">
                                                                Assigned to: {item.assigned_to}
                                                            </p>
                                                        )}
                                                        {item.completed_at && (
                                                            <p className="mt-1 text-xs text-zinc-400">
                                                                Completed: {formatDate(item.completed_at)}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    ) : (
                        <div className="flex justify-center py-8 text-sm text-zinc-400">
                            Checklist not found.
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

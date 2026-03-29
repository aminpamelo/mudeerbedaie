import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft,
    Mail,
    Phone,
    Globe,
    Calendar,
    Star,
    CheckCircle,
    ChevronRight,
    Loader2,
    UserCheck,
} from 'lucide-react';
import { fetchApplicant, moveApplicantStage, hireApplicant, fetchDepartments } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '../../components/ui/dialog';
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

const STAGES = ['applied', 'screening', 'interview', 'assessment', 'offer'];

const STAGE_BADGE = {
    applied: 'bg-zinc-100 text-zinc-600',
    screening: 'bg-blue-100 text-blue-700',
    interview: 'bg-amber-100 text-amber-700',
    assessment: 'bg-purple-100 text-purple-700',
    offer: 'bg-emerald-100 text-emerald-700',
    hired: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

const TABS = ['overview', 'interviews', 'offer'];

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StarRating({ rating }) {
    const value = rating ? Math.round(rating) : 0;
    return (
        <div className="flex items-center gap-0.5">
            {Array.from({ length: 5 }).map((_, i) => (
                <Star
                    key={i}
                    className={cn(
                        'h-4 w-4',
                        i < value ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'
                    )}
                />
            ))}
            {rating && <span className="ml-1 text-sm text-zinc-500">({rating})</span>}
        </div>
    );
}

function SkeletonDetail() {
    return (
        <div className="space-y-6">
            <div className="h-40 animate-pulse rounded-lg bg-zinc-200" />
            <div className="h-64 animate-pulse rounded-lg bg-zinc-200" />
        </div>
    );
}

const EMPTY_HIRE_FORM = {
    department_id: '',
    position: '',
    salary: '',
    join_date: '',
};

export default function ApplicantDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [activeTab, setActiveTab] = useState('overview');
    const [hireDialogOpen, setHireDialogOpen] = useState(false);
    const [hireForm, setHireForm] = useState(EMPTY_HIRE_FORM);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'recruitment', 'applicants', id],
        queryFn: () => fetchApplicant(id),
        enabled: !!id,
    });

    const { data: deptsData } = useQuery({
        queryKey: ['hr', 'departments', 'list'],
        queryFn: () => fetchDepartments({ per_page: 100 }),
    });

    const stageMutation = useMutation({
        mutationFn: ({ stage }) => moveApplicantStage(id, { stage }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'applicants', id] }),
    });

    const hireMutation = useMutation({
        mutationFn: (data) => hireApplicant(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'recruitment', 'applicants'] });
            setHireDialogOpen(false);
            setHireForm(EMPTY_HIRE_FORM);
        },
    });

    const applicant = data?.data || data;
    const departments = deptsData?.data || [];

    if (isLoading) {
        return (
            <div>
                <PageHeader title="Applicant Detail" />
                <SkeletonDetail />
            </div>
        );
    }

    if (isError || !applicant) {
        return (
            <div>
                <PageHeader title="Applicant Detail" />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <p className="text-sm font-medium text-red-600">Failed to load applicant details.</p>
                        <Button variant="outline" className="mt-4" onClick={() => navigate(-1)}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Go Back
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const currentStageIndex = STAGES.indexOf(applicant.stage);
    const interviews = applicant.interviews || [];
    const stageHistory = applicant.stage_history || [];
    const isHired = applicant.stage === 'hired';
    const isRejected = applicant.stage === 'rejected';

    function handleHireSubmit(e) {
        e.preventDefault();
        hireMutation.mutate({
            ...hireForm,
            salary: hireForm.salary ? Number(hireForm.salary) : undefined,
        });
    }

    return (
        <div>
            <PageHeader
                title={applicant.name || 'Applicant Detail'}
                description={`Applied for: ${applicant.job_posting?.title || 'N/A'}`}
                action={
                    <div className="flex items-center gap-2">
                        {!isHired && !isRejected && (
                            <Button
                                variant="default"
                                onClick={() => setHireDialogOpen(true)}
                            >
                                <UserCheck className="mr-1.5 h-4 w-4" />
                                Hire
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => navigate(-1)}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Back
                        </Button>
                    </div>
                }
            />

            {/* Stage Progress */}
            {!isHired && !isRejected && (
                <Card className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-1 overflow-x-auto">
                            {STAGES.map((stage, i) => {
                                const isActive = stage === applicant.stage;
                                const isPast = i < currentStageIndex;
                                const isNext = i === currentStageIndex + 1;
                                return (
                                    <div key={stage} className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => isNext && stageMutation.mutate({ stage })}
                                            disabled={stageMutation.isPending || (!isNext && !isActive)}
                                            className={cn(
                                                'flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors',
                                                isActive && 'bg-zinc-900 text-white',
                                                isPast && 'bg-emerald-100 text-emerald-700',
                                                isNext && 'cursor-pointer bg-zinc-100 text-zinc-600 hover:bg-zinc-200',
                                                !isActive && !isPast && !isNext && 'cursor-default bg-zinc-50 text-zinc-400'
                                            )}
                                        >
                                            {isPast && <CheckCircle className="h-3 w-3" />}
                                            <span className="capitalize">{stage}</span>
                                        </button>
                                        {i < STAGES.length - 1 && (
                                            <ChevronRight className="h-4 w-4 shrink-0 text-zinc-300" />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {isHired && (
                <div className="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    This applicant has been hired.
                </div>
            )}
            {isRejected && (
                <div className="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    This applicant has been rejected.
                </div>
            )}

            {/* Tabs */}
            <div className="mb-6 border-b border-zinc-200">
                <div className="flex gap-1">
                    {TABS.map((tab) => (
                        <button
                            key={tab}
                            type="button"
                            onClick={() => setActiveTab(tab)}
                            className={cn(
                                'px-4 py-2 text-sm font-medium capitalize transition-colors',
                                activeTab === tab
                                    ? 'border-b-2 border-zinc-900 text-zinc-900'
                                    : 'text-zinc-500 hover:text-zinc-700'
                            )}
                        >
                            {tab}
                            {tab === 'interviews' && interviews.length > 0 && (
                                <span className="ml-1.5 rounded-full bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600">
                                    {interviews.length}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            </div>

            {/* Tab Content */}
            {activeTab === 'overview' && (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Personal Info */}
                        <Card>
                            <CardContent className="p-6">
                                <h3 className="mb-4 text-base font-semibold text-zinc-900">Personal Information</h3>
                                <dl className="grid grid-cols-2 gap-4 text-sm">
                                    <div className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-zinc-400" />
                                        <div>
                                            <dt className="text-zinc-500">Email</dt>
                                            <dd className="font-medium text-zinc-900">{applicant.email || '-'}</dd>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-zinc-400" />
                                        <div>
                                            <dt className="text-zinc-500">Phone</dt>
                                            <dd className="font-medium text-zinc-900">{applicant.phone || '-'}</dd>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Globe className="h-4 w-4 text-zinc-400" />
                                        <div>
                                            <dt className="text-zinc-500">Source</dt>
                                            <dd className="font-medium capitalize text-zinc-900">{applicant.source || '-'}</dd>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-zinc-400" />
                                        <div>
                                            <dt className="text-zinc-500">Applied</dt>
                                            <dd className="font-medium text-zinc-900">{formatDate(applicant.applied_at)}</dd>
                                        </div>
                                    </div>
                                    <div className="col-span-2">
                                        <dt className="mb-1 text-zinc-500">Rating</dt>
                                        <dd><StarRating rating={applicant.rating} /></dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>

                        {applicant.notes && (
                            <Card>
                                <CardContent className="p-6">
                                    <h3 className="mb-3 text-base font-semibold text-zinc-900">Notes</h3>
                                    <p className="whitespace-pre-line text-sm text-zinc-600">{applicant.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Stage History */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-base font-semibold text-zinc-900">Stage History</h3>
                            {stageHistory.length === 0 ? (
                                <p className="text-xs text-zinc-400">No stage history recorded.</p>
                            ) : (
                                <div className="space-y-4">
                                    {stageHistory.map((entry, i) => (
                                        <div key={i} className="flex gap-3">
                                            <div className="mt-1 flex flex-col items-center">
                                                <div className="h-2.5 w-2.5 rounded-full bg-zinc-400" />
                                                {i < stageHistory.length - 1 && (
                                                    <div className="mt-1 h-full w-px bg-zinc-200" />
                                                )}
                                            </div>
                                            <div className="pb-4">
                                                <p className="text-sm font-medium capitalize text-zinc-900">
                                                    {entry.stage}
                                                </p>
                                                <p className="text-xs text-zinc-400">
                                                    {formatDateTime(entry.created_at)}
                                                </p>
                                                {entry.notes && (
                                                    <p className="mt-1 text-xs text-zinc-500">{entry.notes}</p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}

            {activeTab === 'interviews' && (
                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-base font-semibold text-zinc-900">Interviews</h3>
                        {interviews.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-10 text-center">
                                <Calendar className="mb-3 h-10 w-10 text-zinc-300" />
                                <p className="text-sm text-zinc-500">No interviews scheduled yet.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Interviewer</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Date & Time</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Rating</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {interviews.map((interview) => (
                                        <TableRow key={interview.id}>
                                            <TableCell className="font-medium">
                                                {interview.interviewer?.full_name || '-'}
                                            </TableCell>
                                            <TableCell className="capitalize text-sm">
                                                {interview.type || '-'}
                                            </TableCell>
                                            <TableCell className="text-sm text-zinc-500">
                                                {formatDateTime(interview.scheduled_at)}
                                            </TableCell>
                                            <TableCell>
                                                <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs capitalize text-zinc-600">
                                                    {interview.status || '-'}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {interview.rating ? (
                                                    <StarRating rating={interview.rating} />
                                                ) : (
                                                    <span className="text-xs text-zinc-400">—</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            )}

            {activeTab === 'offer' && (
                <Card>
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-base font-semibold text-zinc-900">Offer Letter</h3>
                        {applicant.offer ? (
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-zinc-500">Position</span>
                                    <span className="font-medium">{applicant.offer.position || '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-zinc-500">Department</span>
                                    <span className="font-medium">{applicant.offer.department || '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-zinc-500">Salary</span>
                                    <span className="font-medium">
                                        {applicant.offer.salary
                                            ? new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(applicant.offer.salary)
                                            : '-'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-zinc-500">Join Date</span>
                                    <span className="font-medium">{formatDate(applicant.offer.join_date)}</span>
                                </div>
                            </dl>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-10 text-center">
                                <p className="text-sm text-zinc-500">No offer letter generated yet.</p>
                                {applicant.stage === 'offer' && (
                                    <Button
                                        className="mt-4"
                                        onClick={() => setHireDialogOpen(true)}
                                    >
                                        <UserCheck className="mr-1.5 h-4 w-4" />
                                        Hire Applicant
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Hire Dialog */}
            <Dialog open={hireDialogOpen} onOpenChange={() => setHireDialogOpen(false)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hire Applicant</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleHireSubmit}>
                        <div className="space-y-4 py-2">
                            <div className="space-y-1.5">
                                <Label>Department</Label>
                                <Select
                                    value={hireForm.department_id}
                                    onValueChange={(v) => setHireForm((f) => ({ ...f, department_id: v }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={String(dept.id)}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="hire-position">Position / Job Title</Label>
                                <Input
                                    id="hire-position"
                                    value={hireForm.position}
                                    onChange={(e) => setHireForm((f) => ({ ...f, position: e.target.value }))}
                                    placeholder="e.g. Software Engineer"
                                    required
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="hire-salary">Starting Salary (MYR)</Label>
                                <Input
                                    id="hire-salary"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={hireForm.salary}
                                    onChange={(e) => setHireForm((f) => ({ ...f, salary: e.target.value }))}
                                    placeholder="e.g. 5000"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="hire-join-date">Join Date</Label>
                                <Input
                                    id="hire-join-date"
                                    type="date"
                                    value={hireForm.join_date}
                                    onChange={(e) => setHireForm((f) => ({ ...f, join_date: e.target.value }))}
                                    required
                                />
                            </div>
                        </div>

                        <DialogFooter className="mt-4">
                            <Button type="button" variant="outline" onClick={() => setHireDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={hireMutation.isPending}>
                                {hireMutation.isPending && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                                Confirm Hire
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

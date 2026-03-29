import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    GraduationCap,
    Award,
    Loader2,
    Star,
    MessageSquare,
    CalendarDays,
    AlertTriangle,
    CheckCircle2,
    Clock,
    XCircle,
    FileText,
} from 'lucide-react';
import { fetchMyTraining, submitMyTrainingFeedback } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../components/ui/card';
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

const TRAINING_STATUS_CONFIG = {
    enrolled: { label: 'Enrolled', variant: 'outline', className: 'border-blue-300 bg-blue-50 text-blue-700' },
    in_progress: { label: 'In Progress', variant: 'warning', className: 'border-amber-300 bg-amber-50 text-amber-700' },
    attended: { label: 'Attended', variant: 'success', className: 'border-green-300 bg-green-50 text-green-700' },
    completed: { label: 'Completed', variant: 'success', className: 'border-green-300 bg-green-50 text-green-700' },
    cancelled: { label: 'Cancelled', variant: 'destructive', className: 'border-red-300 bg-red-50 text-red-700' },
    no_show: { label: 'No Show', variant: 'destructive', className: 'border-red-300 bg-red-50 text-red-700' },
};

const CERT_STATUS_CONFIG = {
    active: { label: 'Active', variant: 'success', className: 'border-green-300 bg-green-50 text-green-700' },
    expiring_soon: { label: 'Expiring Soon', variant: 'warning', className: 'border-amber-300 bg-amber-50 text-amber-700' },
    expired: { label: 'Expired', variant: 'destructive', className: 'border-red-300 bg-red-50 text-red-700' },
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function getCertStatus(certification) {
    if (certification.status) return certification.status;
    if (!certification.expiry_date) return 'active';
    const expiry = new Date(certification.expiry_date);
    const now = new Date();
    const thirtyDaysFromNow = new Date();
    thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30);
    if (expiry < now) return 'expired';
    if (expiry <= thirtyDaysFromNow) return 'expiring_soon';
    return 'active';
}

function TrainingStatusBadge({ status }) {
    const config = TRAINING_STATUS_CONFIG[status] || TRAINING_STATUS_CONFIG.enrolled;
    return (
        <Badge variant={config.variant} className={cn('text-[10px]', config.className)}>
            {config.label}
        </Badge>
    );
}

function CertStatusBadge({ status }) {
    const config = CERT_STATUS_CONFIG[status] || CERT_STATUS_CONFIG.active;
    return (
        <Badge variant={config.variant} className={cn('text-[10px]', config.className)}>
            {config.label}
        </Badge>
    );
}

function StarRating({ value, onChange, readonly = false }) {
    const [hoverValue, setHoverValue] = useState(0);

    return (
        <div className="flex items-center gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    disabled={readonly}
                    onClick={() => !readonly && onChange(star)}
                    onMouseEnter={() => !readonly && setHoverValue(star)}
                    onMouseLeave={() => !readonly && setHoverValue(0)}
                    className={cn(
                        'transition-colors',
                        readonly ? 'cursor-default' : 'cursor-pointer hover:scale-110'
                    )}
                >
                    <Star
                        className={cn(
                            'h-5 w-5',
                            (hoverValue || value) >= star
                                ? 'fill-amber-400 text-amber-400'
                                : 'fill-zinc-200 text-zinc-200'
                        )}
                    />
                </button>
            ))}
            {value > 0 && (
                <span className="ml-1 text-xs text-zinc-500">{value}/5</span>
            )}
        </div>
    );
}

export default function MyTraining() {
    const queryClient = useQueryClient();
    const [feedbackDialog, setFeedbackDialog] = useState(false);
    const [selectedTraining, setSelectedTraining] = useState(null);
    const [rating, setRating] = useState(0);
    const [feedbackText, setFeedbackText] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'me', 'training'],
        queryFn: fetchMyTraining,
    });

    const trainings = data?.data?.trainings ?? [];
    const certifications = data?.data?.certifications ?? [];

    const feedbackMutation = useMutation({
        mutationFn: ({ enrollmentId, data }) => submitMyTrainingFeedback(enrollmentId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'me', 'training'] });
            setFeedbackDialog(false);
            setSelectedTraining(null);
            setRating(0);
            setFeedbackText('');
        },
    });

    function openFeedbackDialog(training) {
        setSelectedTraining(training);
        setRating(0);
        setFeedbackText('');
        setFeedbackDialog(true);
    }

    function handleSubmitFeedback() {
        if (rating === 0 || !selectedTraining) return;
        feedbackMutation.mutate({
            enrollmentId: selectedTraining.enrollment_id || selectedTraining.id,
            data: { rating, feedback: feedbackText },
        });
    }

    const summaryCards = [
        {
            label: 'Total Trainings',
            value: trainings.length,
            icon: GraduationCap,
            color: 'text-blue-600',
            bg: 'bg-blue-50',
        },
        {
            label: 'Attended',
            value: trainings.filter((t) => t.status === 'attended' || t.status === 'completed').length,
            icon: CheckCircle2,
            color: 'text-green-600',
            bg: 'bg-green-50',
        },
        {
            label: 'Certifications',
            value: certifications.length,
            icon: Award,
            color: 'text-purple-600',
            bg: 'bg-purple-50',
        },
        {
            label: 'Expiring Soon',
            value: certifications.filter((c) => getCertStatus(c) === 'expiring_soon').length,
            icon: AlertTriangle,
            color: 'text-amber-600',
            bg: 'bg-amber-50',
        },
    ];

    return (
        <div className="space-y-6">
            <PageHeader
                title="My Training & Certifications"
                description="View your training programs and certifications"
            />

            {/* Summary Cards */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {summaryCards.map((card) => (
                    <Card key={card.label}>
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className={cn('flex h-10 w-10 items-center justify-center rounded-lg', card.bg)}>
                                    <card.icon className={cn('h-5 w-5', card.color)} />
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-zinc-500">{card.label}</p>
                                    <p className="text-lg font-bold text-zinc-900">{card.value}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* My Trainings */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <GraduationCap className="h-5 w-5 text-zinc-400" />
                        <div>
                            <CardTitle>My Trainings</CardTitle>
                            <CardDescription>{trainings.length} program(s)</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 3 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                                    <div className="flex-1" />
                                    <div className="h-8 w-24 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : trainings.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <GraduationCap className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No training programs</p>
                            <p className="text-xs text-zinc-400">You have not been enrolled in any training yet</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Program Title</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Feedback</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {trainings.map((training) => {
                                    const canGiveFeedback =
                                        (training.status === 'attended' || training.status === 'completed') &&
                                        !training.feedback &&
                                        !training.rating;

                                    return (
                                        <TableRow key={training.enrollment_id || training.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium text-zinc-900">
                                                        {training.program_title || training.title}
                                                    </p>
                                                    {training.provider && (
                                                        <p className="text-xs text-zinc-400">{training.provider}</p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    {formatDate(training.start_date || training.date)}
                                                    {training.end_date && training.end_date !== training.start_date && (
                                                        <span className="text-zinc-400">
                                                            {' - '}{formatDate(training.end_date)}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <TrainingStatusBadge status={training.status} />
                                            </TableCell>
                                            <TableCell>
                                                {training.rating ? (
                                                    <StarRating value={training.rating} onChange={() => {}} readonly />
                                                ) : (
                                                    <span className="text-xs text-zinc-400">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {canGiveFeedback ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => openFeedbackDialog(training)}
                                                    >
                                                        <MessageSquare className="mr-1 h-4 w-4" />
                                                        Give Feedback
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-zinc-400">-</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* My Certifications */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Award className="h-5 w-5 text-zinc-400" />
                        <div>
                            <CardTitle>My Certifications</CardTitle>
                            <CardDescription>{certifications.length} certification(s)</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="space-y-3 p-6">
                            {Array.from({ length: 3 }).map((_, i) => (
                                <div key={i} className="flex items-center gap-4 py-2">
                                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-24 animate-pulse rounded bg-zinc-200" />
                                    <div className="h-4 w-20 animate-pulse rounded bg-zinc-200" />
                                </div>
                            ))}
                        </div>
                    ) : certifications.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Award className="mb-3 h-10 w-10 text-zinc-300" />
                            <p className="text-sm font-medium text-zinc-500">No certifications</p>
                            <p className="text-xs text-zinc-400">Your certifications will appear here</p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Certification Name</TableHead>
                                    <TableHead>Certificate #</TableHead>
                                    <TableHead>Issued</TableHead>
                                    <TableHead>Expiry</TableHead>
                                    <TableHead className="text-right">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {certifications.map((cert) => {
                                    const certStatus = getCertStatus(cert);

                                    return (
                                        <TableRow
                                            key={cert.id}
                                            className={cn(
                                                certStatus === 'expired' && 'bg-red-50/50',
                                                certStatus === 'expiring_soon' && 'bg-amber-50/50'
                                            )}
                                        >
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {certStatus === 'expiring_soon' && (
                                                        <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0" />
                                                    )}
                                                    {certStatus === 'expired' && (
                                                        <XCircle className="h-4 w-4 text-red-500 shrink-0" />
                                                    )}
                                                    <span className="font-medium text-zinc-900">
                                                        {cert.certification_name || cert.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {cert.certificate_number || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(cert.issued_date)}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={cn(
                                                        certStatus === 'expired' && 'font-medium text-red-600',
                                                        certStatus === 'expiring_soon' && 'font-medium text-amber-600'
                                                    )}
                                                >
                                                    {formatDate(cert.expiry_date)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <CertStatusBadge status={certStatus} />
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {/* Feedback Dialog */}
            <Dialog open={feedbackDialog} onOpenChange={setFeedbackDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Training Feedback</DialogTitle>
                        <DialogDescription>
                            {selectedTraining && (
                                <>Share your feedback for: {selectedTraining.program_title || selectedTraining.title}</>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Rating */}
                        <div>
                            <label className="mb-2 block text-sm font-medium text-zinc-700">
                                Rating
                            </label>
                            <StarRating value={rating} onChange={setRating} />
                            {rating === 0 && (
                                <p className="mt-1 text-xs text-zinc-400">Please select a rating</p>
                            )}
                        </div>

                        {/* Feedback Text */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-zinc-700">
                                Feedback
                            </label>
                            <textarea
                                value={feedbackText}
                                onChange={(e) => setFeedbackText(e.target.value)}
                                placeholder="Share your thoughts on the training program..."
                                rows={4}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFeedbackDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSubmitFeedback}
                            disabled={rating === 0 || feedbackMutation.isPending}
                        >
                            {feedbackMutation.isPending ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Star className="mr-2 h-4 w-4" />
                            )}
                            Submit Feedback
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

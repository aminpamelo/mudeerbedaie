import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Users,
    Calendar,
    Globe,
    Clock,
} from 'lucide-react';
import { fetchJobPosting } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '../../components/ui/table';

const STATUS_BADGE = {
    draft: 'bg-zinc-100 text-zinc-600',
    published: 'bg-emerald-100 text-emerald-700',
    closed: 'bg-red-100 text-red-700',
};

const STAGE_BADGE = {
    applied: 'bg-zinc-100 text-zinc-600',
    screening: 'bg-blue-100 text-blue-700',
    interview: 'bg-amber-100 text-amber-700',
    assessment: 'bg-purple-100 text-purple-700',
    offer: 'bg-emerald-100 text-emerald-700',
    hired: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function SkeletonDetail() {
    return (
        <div className="space-y-6">
            <div className="h-32 animate-pulse rounded-lg bg-zinc-200" />
            <div className="h-48 animate-pulse rounded-lg bg-zinc-200" />
        </div>
    );
}

export default function JobPostingDetail() {
    const { id } = useParams();
    const navigate = useNavigate();

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'recruitment', 'job-postings', id],
        queryFn: () => fetchJobPosting(id),
        enabled: !!id,
    });

    const posting = data?.data || data;

    if (isLoading) {
        return (
            <div>
                <PageHeader title="Job Posting Detail" />
                <SkeletonDetail />
            </div>
        );
    }

    if (isError || !posting) {
        return (
            <div>
                <PageHeader title="Job Posting Detail" />
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <p className="text-sm font-medium text-red-600">Failed to load job posting.</p>
                        <Button variant="outline" className="mt-4" onClick={() => navigate(-1)}>
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Go Back
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const applicants = posting.applicants || [];

    return (
        <div>
            <PageHeader
                title={posting.title || 'Job Posting Detail'}
                description="View job posting details and applicants."
                action={
                    <Button variant="outline" onClick={() => navigate(-1)}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back
                    </Button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Main Info */}
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-start justify-between">
                                <h3 className="text-lg font-semibold text-zinc-900">Posting Details</h3>
                                <span
                                    className={cn(
                                        'rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                        STATUS_BADGE[posting.status] || 'bg-zinc-100 text-zinc-600'
                                    )}
                                >
                                    {posting.status}
                                </span>
                            </div>
                            <dl className="grid grid-cols-2 gap-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <Building2 className="h-4 w-4 text-zinc-400" />
                                    <div>
                                        <dt className="text-zinc-500">Department</dt>
                                        <dd className="font-medium text-zinc-900">{posting.department?.name || '-'}</dd>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Briefcase className="h-4 w-4 text-zinc-400" />
                                    <div>
                                        <dt className="text-zinc-500">Employment Type</dt>
                                        <dd className="font-medium capitalize text-zinc-900">
                                            {posting.employment_type?.replace('-', ' ') || '-'}
                                        </dd>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Users className="h-4 w-4 text-zinc-400" />
                                    <div>
                                        <dt className="text-zinc-500">Vacancies</dt>
                                        <dd className="font-medium text-zinc-900">{posting.vacancies ?? '-'}</dd>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-zinc-400" />
                                    <div>
                                        <dt className="text-zinc-500">Published</dt>
                                        <dd className="font-medium text-zinc-900">{formatDate(posting.published_at)}</dd>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-zinc-400" />
                                    <div>
                                        <dt className="text-zinc-500">Closes</dt>
                                        <dd className="font-medium text-zinc-900">{formatDate(posting.closes_at)}</dd>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Globe className="h-4 w-4 text-zinc-400" />
                                    <div>
                                        <dt className="text-zinc-500">Applicants</dt>
                                        <dd className="font-medium text-zinc-900">{posting.applicants_count ?? applicants.length}</dd>
                                    </div>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    {posting.description && (
                        <Card>
                            <CardContent className="p-6">
                                <h3 className="mb-3 text-base font-semibold text-zinc-900">Description</h3>
                                <p className="whitespace-pre-line text-sm text-zinc-600">{posting.description}</p>
                            </CardContent>
                        </Card>
                    )}

                    {posting.requirements && (
                        <Card>
                            <CardContent className="p-6">
                                <h3 className="mb-3 text-base font-semibold text-zinc-900">Requirements</h3>
                                <p className="whitespace-pre-line text-sm text-zinc-600">{posting.requirements}</p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Applicants Table */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-lg font-semibold text-zinc-900">Applicants</h3>
                            {applicants.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-10 text-center">
                                    <Users className="mb-3 h-10 w-10 text-zinc-300" />
                                    <p className="text-sm text-zinc-500">No applicants yet for this position.</p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Email</TableHead>
                                            <TableHead>Stage</TableHead>
                                            <TableHead>Applied</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {applicants.map((applicant) => (
                                            <TableRow key={applicant.id}>
                                                <TableCell>
                                                    <Link
                                                        to={`/recruitment/applicants/${applicant.id}`}
                                                        className="font-medium text-zinc-900 hover:text-zinc-600 hover:underline"
                                                    >
                                                        {applicant.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-500">{applicant.email}</TableCell>
                                                <TableCell>
                                                    <span
                                                        className={cn(
                                                            'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                            STAGE_BADGE[applicant.stage] || 'bg-zinc-100 text-zinc-600'
                                                        )}
                                                    >
                                                        {applicant.stage}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-sm text-zinc-500">
                                                    {formatDate(applicant.applied_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-base font-semibold text-zinc-900">Stage Breakdown</h3>
                            {STAGE_BADGE && Object.entries(
                                applicants.reduce((acc, a) => {
                                    acc[a.stage] = (acc[a.stage] || 0) + 1;
                                    return acc;
                                }, {})
                            ).map(([stage, count]) => (
                                <div key={stage} className="mb-2 flex items-center justify-between text-sm">
                                    <span
                                        className={cn(
                                            'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                            STAGE_BADGE[stage] || 'bg-zinc-100 text-zinc-600'
                                        )}
                                    >
                                        {stage}
                                    </span>
                                    <span className="font-semibold text-zinc-900">{count}</span>
                                </div>
                            ))}
                            {applicants.length === 0 && (
                                <p className="text-xs text-zinc-400">No applicants yet.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

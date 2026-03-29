import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import {
    Eye,
    Users,
    Star,
    X,
} from 'lucide-react';
import { fetchApplicants, fetchJobPostings } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
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

const STAGE_OPTIONS = [
    { value: 'all', label: 'All Stages' },
    { value: 'applied', label: 'Applied' },
    { value: 'screening', label: 'Screening' },
    { value: 'interview', label: 'Interview' },
    { value: 'assessment', label: 'Assessment' },
    { value: 'offer', label: 'Offer' },
    { value: 'hired', label: 'Hired' },
    { value: 'rejected', label: 'Rejected' },
];

const SOURCE_OPTIONS = [
    { value: 'all', label: 'All Sources' },
    { value: 'website', label: 'Website' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'referral', label: 'Referral' },
    { value: 'jobstreet', label: 'JobStreet' },
    { value: 'indeed', label: 'Indeed' },
    { value: 'other', label: 'Other' },
];

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

function StarRating({ rating }) {
    const value = rating ? Math.round(rating) : 0;
    return (
        <div className="flex items-center gap-0.5">
            {Array.from({ length: 5 }).map((_, i) => (
                <Star
                    key={i}
                    className={cn(
                        'h-3.5 w-3.5',
                        i < value ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'
                    )}
                />
            ))}
        </div>
    );
}

function SkeletonTable() {
    return (
        <div className="space-y-3 p-4">
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 py-2">
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-40 animate-pulse rounded bg-zinc-200" />
                    <div className="h-4 w-32 animate-pulse rounded bg-zinc-200" />
                    <div className="flex-1" />
                    <div className="h-6 w-16 animate-pulse rounded-full bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function Applicants() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [stageFilter, setStageFilter] = useState('all');
    const [sourceFilter, setSourceFilter] = useState('all');
    const [jobFilter, setJobFilter] = useState('all');
    const [page, setPage] = useState(1);

    const hasFilters = search !== '' || stageFilter !== 'all' || sourceFilter !== 'all' || jobFilter !== 'all';

    const { data, isLoading } = useQuery({
        queryKey: ['hr', 'recruitment', 'applicants', { search, stageFilter, sourceFilter, jobFilter, page }],
        queryFn: () =>
            fetchApplicants({
                search: search || undefined,
                stage: stageFilter !== 'all' ? stageFilter : undefined,
                source: sourceFilter !== 'all' ? sourceFilter : undefined,
                job_posting_id: jobFilter !== 'all' ? jobFilter : undefined,
                page,
                per_page: 20,
            }),
    });

    const { data: jobsData } = useQuery({
        queryKey: ['hr', 'recruitment', 'job-postings', 'all'],
        queryFn: () => fetchJobPostings({ per_page: 100 }),
    });

    const applicants = data?.data || [];
    const pagination = data?.meta || {};
    const lastPage = pagination.last_page || 1;
    const total = pagination.total || 0;
    const jobPostings = jobsData?.data || [];

    function clearFilters() {
        setSearch('');
        setStageFilter('all');
        setSourceFilter('all');
        setJobFilter('all');
        setPage(1);
    }

    return (
        <div>
            <PageHeader
                title="Applicants"
                description="Track and manage all job applicants across positions."
            />

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <Input
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            placeholder="Search name, email..."
                            className="w-full lg:w-56"
                        />

                        <Select value={jobFilter} onValueChange={(v) => { setJobFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-full lg:w-48">
                                <SelectValue placeholder="Job Posting" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Postings</SelectItem>
                                {jobPostings.map((job) => (
                                    <SelectItem key={job.id} value={String(job.id)}>
                                        {job.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={stageFilter} onValueChange={(v) => { setStageFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-full lg:w-36">
                                <SelectValue placeholder="Stage" />
                            </SelectTrigger>
                            <SelectContent>
                                {STAGE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={sourceFilter} onValueChange={(v) => { setSourceFilter(v); setPage(1); }}>
                            <SelectTrigger className="w-full lg:w-36">
                                <SelectValue placeholder="Source" />
                            </SelectTrigger>
                            <SelectContent>
                                {SOURCE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="mr-1 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            {isLoading ? (
                <Card>
                    <SkeletonTable />
                </Card>
            ) : applicants.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <Users className="mb-4 h-12 w-12 text-zinc-300" />
                        <h3 className="text-lg font-semibold text-zinc-900">
                            {hasFilters ? 'No applicants match your filters' : 'No applicants yet'}
                        </h3>
                        <p className="mt-1 text-sm text-zinc-500">
                            {hasFilters
                                ? 'Try adjusting your filters to find what you are looking for.'
                                : 'Applicants will appear here once they apply for a job posting.'}
                        </p>
                        {hasFilters && (
                            <Button variant="outline" className="mt-4" onClick={clearFilters}>
                                Clear Filters
                            </Button>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Ref No.</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Job Title</TableHead>
                                <TableHead>Stage</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Applied</TableHead>
                                <TableHead>Rating</TableHead>
                                <TableHead className="w-12"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {applicants.map((applicant) => (
                                <TableRow key={applicant.id}>
                                    <TableCell className="font-mono text-xs text-zinc-500">
                                        {applicant.applicant_number || `#${applicant.id}`}
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            to={`/recruitment/applicants/${applicant.id}`}
                                            className="font-medium text-zinc-900 hover:text-zinc-600 hover:underline"
                                        >
                                            {applicant.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-500">{applicant.email}</TableCell>
                                    <TableCell className="text-sm">{applicant.job_posting?.title || '-'}</TableCell>
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
                                    <TableCell className="capitalize text-sm text-zinc-500">
                                        {applicant.source || '-'}
                                    </TableCell>
                                    <TableCell className="text-sm text-zinc-500">
                                        {formatDate(applicant.applied_at)}
                                    </TableCell>
                                    <TableCell>
                                        <StarRating rating={applicant.rating} />
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={() => navigate(`/recruitment/applicants/${applicant.id}`)}
                                        >
                                            <Eye className="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    {lastPage > 1 && (
                        <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-3">
                            <p className="text-sm text-zinc-500">
                                Page {page} of {lastPage} ({total} applicants)
                            </p>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page <= 1}
                                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page >= lastPage}
                                    onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            )}
        </div>
    );
}

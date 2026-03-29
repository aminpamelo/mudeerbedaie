import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Save,
    Star,
    AlertTriangle,
    Loader2,
    Settings,
} from 'lucide-react';
import {
    fetchRatingScales,
    updateRatingScales,
} from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent } from '../../components/ui/card';

const DEFAULT_SCALES = [
    { score: 1, label: 'Unsatisfactory', description: 'Performance is significantly below expectations.', color: '#ef4444' },
    { score: 2, label: 'Needs Improvement', description: 'Performance is below expectations in some areas.', color: '#f97316' },
    { score: 3, label: 'Meets Expectations', description: 'Performance consistently meets job requirements.', color: '#f59e0b' },
    { score: 4, label: 'Exceeds Expectations', description: 'Performance frequently exceeds job requirements.', color: '#3b82f6' },
    { score: 5, label: 'Outstanding', description: 'Performance consistently far exceeds expectations.', color: '#10b981' },
];

function SkeletonRows() {
    return (
        <div className="divide-y divide-zinc-100">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 px-6 py-4">
                    <div className="h-8 w-8 animate-pulse rounded-full bg-zinc-200" />
                    <div className="h-4 w-28 animate-pulse rounded bg-zinc-200" />
                    <div className="h-10 flex-1 animate-pulse rounded-lg bg-zinc-200" />
                    <div className="h-10 w-32 animate-pulse rounded-lg bg-zinc-200" />
                    <div className="h-8 w-12 animate-pulse rounded-lg bg-zinc-200" />
                </div>
            ))}
        </div>
    );
}

export default function RatingScaleConfig() {
    const queryClient = useQueryClient();
    const [scales, setScales] = useState(DEFAULT_SCALES);
    const [isDirty, setIsDirty] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [saveSuccess, setSaveSuccess] = useState(false);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['hr', 'performance', 'rating-scales'],
        queryFn: fetchRatingScales,
    });

    useEffect(() => {
        if (data) {
            const loaded = data?.data || data;
            if (Array.isArray(loaded) && loaded.length > 0) {
                setScales(loaded.map((s) => ({
                    score: s.score,
                    label: s.label || '',
                    description: s.description || '',
                    color: s.color || '#6b7280',
                })));
            }
        }
    }, [data]);

    const saveMutation = useMutation({
        mutationFn: () => updateRatingScales({ scales }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'performance', 'rating-scales'] });
            setIsDirty(false);
            setSaveError('');
            setSaveSuccess(true);
            setTimeout(() => setSaveSuccess(false), 3000);
        },
        onError: (err) => {
            setSaveError(err?.response?.data?.message || 'Failed to save rating scales.');
        },
    });

    function updateScale(index, field, value) {
        setScales((prev) =>
            prev.map((s, i) => (i === index ? { ...s, [field]: value } : s))
        );
        setIsDirty(true);
        setSaveSuccess(false);
    }

    return (
        <div>
            <PageHeader
                title="Rating Scale Configuration"
                description="Configure the labels, descriptions, and colors for each rating score (1-5)."
                action={
                    <Button
                        onClick={() => saveMutation.mutate()}
                        disabled={saveMutation.isPending || !isDirty}
                    >
                        {saveMutation.isPending ? (
                            <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="mr-1.5 h-4 w-4" />
                        )}
                        Save Changes
                    </Button>
                }
            />

            {saveError && (
                <div className="mb-4 flex items-center gap-2 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    {saveError}
                </div>
            )}

            {saveSuccess && (
                <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Rating scales saved successfully.
                </div>
            )}

            <Card>
                {isLoading ? (
                    <SkeletonRows />
                ) : isError ? (
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <AlertTriangle className="mb-3 h-10 w-10 text-red-300" />
                        <p className="text-sm font-medium text-zinc-600">Failed to load rating scales.</p>
                    </CardContent>
                ) : (
                    <>
                        <div className="border-b border-zinc-200 px-6 py-3">
                            <div className="grid grid-cols-12 gap-4 text-xs font-medium text-zinc-500">
                                <div className="col-span-1">Score</div>
                                <div className="col-span-2">Label</div>
                                <div className="col-span-6">Description</div>
                                <div className="col-span-2">Color</div>
                                <div className="col-span-1">Preview</div>
                            </div>
                        </div>
                        <div className="divide-y divide-zinc-100">
                            {scales.map((scale, i) => (
                                <div key={scale.score} className="grid grid-cols-12 items-center gap-4 px-6 py-4">
                                    <div className="col-span-1">
                                        <div className="flex items-center gap-1">
                                            <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                                            <span className="font-bold text-zinc-900">{scale.score}</span>
                                        </div>
                                    </div>
                                    <div className="col-span-2">
                                        <input
                                            type="text"
                                            value={scale.label}
                                            onChange={(e) => updateScale(i, 'label', e.target.value)}
                                            placeholder="Label"
                                            className="w-full rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                        />
                                    </div>
                                    <div className="col-span-6">
                                        <input
                                            type="text"
                                            value={scale.description}
                                            onChange={(e) => updateScale(i, 'description', e.target.value)}
                                            placeholder="Description of this rating level..."
                                            className="w-full rounded-lg border border-zinc-300 px-2.5 py-1.5 text-sm focus:border-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                                        />
                                    </div>
                                    <div className="col-span-2 flex items-center gap-2">
                                        <input
                                            type="color"
                                            value={scale.color}
                                            onChange={(e) => updateScale(i, 'color', e.target.value)}
                                            className="h-9 w-14 cursor-pointer rounded-lg border border-zinc-300 p-0.5"
                                        />
                                        <span className="font-mono text-xs text-zinc-500">{scale.color}</span>
                                    </div>
                                    <div className="col-span-1">
                                        <span
                                            className="inline-flex items-center justify-center rounded-full px-2.5 py-1 text-xs font-medium text-white"
                                            style={{ backgroundColor: scale.color }}
                                        >
                                            {scale.score}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="border-t border-zinc-100 px-6 py-4">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-zinc-500">
                                    {isDirty ? (
                                        <span className="text-amber-600">You have unsaved changes.</span>
                                    ) : (
                                        'Rating scales are up to date.'
                                    )}
                                </p>
                                <Button
                                    onClick={() => saveMutation.mutate()}
                                    disabled={saveMutation.isPending || !isDirty}
                                >
                                    {saveMutation.isPending ? (
                                        <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                    ) : (
                                        <Save className="mr-1.5 h-4 w-4" />
                                    )}
                                    Save All Changes
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </Card>

            {/* Preview Section */}
            <Card className="mt-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-semibold text-zinc-900">Preview</h3>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-5">
                        {scales.map((scale) => (
                            <div
                                key={scale.score}
                                className="rounded-xl border-2 p-4 text-center"
                                style={{ borderColor: scale.color + '40', backgroundColor: scale.color + '10' }}
                            >
                                <div className="flex justify-center">
                                    <span
                                        className="flex h-10 w-10 items-center justify-center rounded-full text-lg font-bold text-white"
                                        style={{ backgroundColor: scale.color }}
                                    >
                                        {scale.score}
                                    </span>
                                </div>
                                <p className="mt-2 text-sm font-semibold text-zinc-900">{scale.label || `Score ${scale.score}`}</p>
                                <p className="mt-1 text-xs text-zinc-500 line-clamp-2">{scale.description}</p>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

import { useQuery } from '@tanstack/react-query';
import { Package2, Loader2 } from 'lucide-react';
import { fetchMyAssets } from '../../lib/api';
import { cn } from '../../lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { EmployeePageHeader } from '../../components/ui/employee-page-header';

const CONDITION_BADGE = {
    new: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
    good: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
    fair: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
    poor: 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-300',
    damaged: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function MyAssets() {
    const { data, isLoading } = useQuery({
        queryKey: ['my-assets'],
        queryFn: fetchMyAssets,
    });

    const assets = data?.data || [];

    return (
        <div className="space-y-4 pb-4">
            <EmployeePageHeader
                icon={Package2}
                accent="sky"
                title="My Assets"
                context={assets.length > 0 ? `${assets.length} item${assets.length === 1 ? '' : 's'}` : null}
            />

            {isLoading ? (
                <div className="flex justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-slate-400 dark:text-slate-500" />
                </div>
            ) : assets.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <Package2 className="mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
                        <p className="text-sm font-medium text-slate-600 dark:text-slate-300">No assets assigned</p>
                        <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">You have no assets currently assigned to you.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {assets.map((assignment) => {
                        const asset = assignment.asset || {};
                        return (
                            <Card key={assignment.id}>
                                <CardContent className="p-5">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1 min-w-0">
                                            <p className="font-mono text-xs font-medium text-slate-400 dark:text-slate-500">
                                                {asset.asset_tag}
                                            </p>
                                            <p className="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white truncate">
                                                {asset.name}
                                            </p>
                                            {(asset.brand || asset.model) && (
                                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                    {[asset.brand, asset.model].filter(Boolean).join(' ')}
                                                </p>
                                            )}
                                        </div>
                                        {asset.condition && (
                                            <span className={cn('ml-2 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize', CONDITION_BADGE[asset.condition] || 'bg-slate-100 text-slate-600 dark:bg-white/[0.08] dark:text-slate-300')}>
                                                {asset.condition}
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-4 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        <div className="flex items-center justify-between">
                                            <span>Category</span>
                                            <span className="font-medium text-slate-700 dark:text-slate-200">{asset.asset_category?.name || '-'}</span>
                                        </div>
                                        {asset.serial_number && (
                                            <div className="flex items-center justify-between">
                                                <span>Serial No.</span>
                                                <span className="font-mono font-medium text-slate-700 dark:text-slate-200">{asset.serial_number}</span>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between">
                                            <span>Assigned</span>
                                            <span className="font-medium text-slate-700 dark:text-slate-200">{formatDate(assignment.assigned_date)}</span>
                                        </div>
                                        {assignment.expected_return_date && (
                                            <div className="flex items-center justify-between">
                                                <span>Expected Return</span>
                                                <span className="font-medium text-slate-700 dark:text-slate-200">{formatDate(assignment.expected_return_date)}</span>
                                            </div>
                                        )}
                                        {asset.warranty_expiry && (
                                            <div className="flex items-center justify-between">
                                                <span>Warranty Expiry</span>
                                                <span className="font-medium text-slate-700 dark:text-slate-200">{formatDate(asset.warranty_expiry)}</span>
                                            </div>
                                        )}
                                    </div>

                                    {assignment.notes && (
                                        <p className="mt-3 rounded-lg bg-slate-50 dark:bg-white/[0.04] px-3 py-2 text-xs text-slate-600 dark:text-slate-300">
                                            {assignment.notes}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

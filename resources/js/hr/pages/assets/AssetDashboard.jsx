import { useQuery } from '@tanstack/react-query';
import { Package, Package2, Wrench, Trash2, Loader2 } from 'lucide-react';
import { fetchAssets, fetchAssetAssignments, fetchAssetCategories } from '../../lib/api';
import { cn } from '../../lib/utils';
import PageHeader from '../../components/PageHeader';
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
    available: 'bg-emerald-100 text-emerald-700',
    assigned: 'bg-blue-100 text-blue-700',
    under_maintenance: 'bg-amber-100 text-amber-700',
    disposed: 'bg-zinc-100 text-zinc-500',
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(amount);
}

export default function AssetDashboard() {
    const { data: assetsData, isLoading: assetsLoading } = useQuery({
        queryKey: ['hr', 'assets', 'all'],
        queryFn: () => fetchAssets({ per_page: 200 }),
    });

    const { data: assignmentsData, isLoading: assignmentsLoading } = useQuery({
        queryKey: ['hr', 'assets', 'assignments', 'active'],
        queryFn: () => fetchAssetAssignments({ status: 'active', per_page: 10 }),
    });

    const { data: categoriesData } = useQuery({
        queryKey: ['hr', 'assets', 'categories'],
        queryFn: () => fetchAssetCategories({ per_page: 100 }),
    });

    const assets = assetsData?.data || [];
    const activeAssignments = assignmentsData?.data || [];
    const categories = categoriesData?.data || [];

    const totalAssets = assets.length;
    const availableCount = assets.filter((a) => a.status === 'available').length;
    const assignedCount = assets.filter((a) => a.status === 'assigned').length;
    const maintenanceCount = assets.filter((a) => a.status === 'under_maintenance').length;

    const totalValue = assets.reduce((sum, a) => sum + (parseFloat(a.purchase_price) || 0), 0);

    const byCategory = categories.map((cat) => ({
        name: cat.name,
        count: assets.filter((a) => a.asset_category_id === cat.id).length,
        available: assets.filter((a) => a.asset_category_id === cat.id && a.status === 'available').length,
    })).filter((c) => c.count > 0);

    const STAT_CARDS = [
        { label: 'Total Assets', value: totalAssets, icon: Package, color: 'text-zinc-700', bg: 'bg-zinc-50' },
        { label: 'Available', value: availableCount, icon: Package, color: 'text-emerald-600', bg: 'bg-emerald-50' },
        { label: 'Assigned', value: assignedCount, icon: Package2, color: 'text-blue-600', bg: 'bg-blue-50' },
        { label: 'Under Maintenance', value: maintenanceCount, icon: Wrench, color: 'text-amber-600', bg: 'bg-amber-50' },
    ];

    return (
        <div>
            <PageHeader
                title="Asset Dashboard"
                description="Overview of company asset inventory and assignments."
            />

            {/* Stats */}
            {assetsLoading ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Card key={i}>
                            <CardContent className="p-6">
                                <div className="h-16 animate-pulse rounded bg-zinc-100" />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {STAT_CARDS.map((card) => {
                        const Icon = card.icon;
                        return (
                            <Card key={card.label}>
                                <CardContent className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-lg', card.bg)}>
                                            <Icon className={cn('h-6 w-6', card.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm text-zinc-500">{card.label}</p>
                                            <p className="text-2xl font-bold text-zinc-900">{card.value}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Total Value */}
                <Card>
                    <CardContent className="p-6">
                        <p className="text-sm text-zinc-500">Total Asset Value</p>
                        <p className="mt-1 text-3xl font-bold text-zinc-900">{formatCurrency(totalValue)}</p>
                        <p className="mt-1 text-xs text-zinc-400">Based on purchase prices</p>
                    </CardContent>
                </Card>

                {/* By Category */}
                <Card className="lg:col-span-2">
                    <CardContent className="p-6">
                        <h3 className="mb-4 text-base font-semibold text-zinc-900">Assets by Category</h3>
                        {byCategory.length === 0 ? (
                            <p className="text-sm text-zinc-400">No assets recorded.</p>
                        ) : (
                            <div className="space-y-3">
                                {byCategory.map((cat) => (
                                    <div key={cat.name} className="flex items-center gap-3">
                                        <div className="w-32 truncate text-sm font-medium text-zinc-700">{cat.name}</div>
                                        <div className="flex-1">
                                            <div className="h-2 rounded-full bg-zinc-100">
                                                <div
                                                    className="h-2 rounded-full bg-blue-500 transition-all"
                                                    style={{ width: `${totalAssets > 0 ? (cat.count / totalAssets) * 100 : 0}%` }}
                                                />
                                            </div>
                                        </div>
                                        <div className="w-16 text-right text-sm text-zinc-500">
                                            {cat.count} ({cat.available} avail.)
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Recent Active Assignments */}
            <Card className="mt-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-zinc-900">Current Assignments</h3>
                    {assignmentsLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : activeAssignments.length === 0 ? (
                        <p className="py-8 text-center text-sm text-zinc-400">No active assignments.</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Asset</TableHead>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Assigned Date</TableHead>
                                    <TableHead>Expected Return</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activeAssignments.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell>
                                            <p className="font-mono text-sm font-medium">{a.asset?.asset_tag}</p>
                                            <p className="text-xs text-zinc-400">{a.asset?.name}</p>
                                        </TableCell>
                                        <TableCell className="font-medium">{a.employee?.full_name || '-'}</TableCell>
                                        <TableCell className="text-sm">{formatDate(a.assigned_date)}</TableCell>
                                        <TableCell className="text-sm">{formatDate(a.expected_return_date)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Trophy, Users, TrendingUp, Search } from 'lucide-react';
import { fetchCreators } from '../lib/api';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';

export default function CreatorLeaderboard() {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState('gmv');
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['creators', { search, sort, page }],
        queryFn: () => fetchCreators({ search, sort, page, per_page: 20 }),
    });

    const creators = data?.data || [];
    const pagination = data?.meta || data || {};

    const formatCurrency = (v) =>
        new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(v || 0);
    const formatNumber = (v) =>
        new Intl.NumberFormat('en-MY', { notation: 'compact' }).format(v || 0);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Creator Leaderboard</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        TikTok Shop affiliate creators ranked by performance
                    </p>
                </div>
            </div>

            {/* Filters */}
            <div className="flex items-center gap-3">
                <div className="relative flex-1 max-w-sm">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <Input
                        placeholder="Search creators..."
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        className="pl-9"
                    />
                </div>
                <div className="flex gap-1">
                    <Button
                        size="sm"
                        variant={sort === 'gmv' ? 'default' : 'outline'}
                        onClick={() => setSort('gmv')}
                    >
                        <TrendingUp className="mr-1 h-3.5 w-3.5" /> By GMV
                    </Button>
                    <Button
                        size="sm"
                        variant={sort === 'followers' ? 'default' : 'outline'}
                        onClick={() => setSort('followers')}
                    >
                        <Users className="mr-1 h-3.5 w-3.5" /> By Followers
                    </Button>
                </div>
            </div>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 w-12">#</th>
                                <th className="px-4 py-3">Creator</th>
                                <th className="px-4 py-3 text-right">Followers</th>
                                <th className="px-4 py-3 text-right">GMV</th>
                                <th className="px-4 py-3 text-right">Orders</th>
                                <th className="px-4 py-3 text-right">Commission</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {isLoading ? (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">Loading...</td></tr>
                            ) : creators.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">No creators found</td></tr>
                            ) : (
                                creators.map((c, idx) => (
                                    <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-3 text-slate-400 font-mono">
                                            {idx === 0 && page === 1 ? (
                                                <Trophy className="h-4 w-4 text-amber-500" />
                                            ) : (
                                                (page - 1) * 20 + idx + 1
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {c.avatar_url ? (
                                                    <img src={c.avatar_url} className="h-8 w-8 rounded-full object-cover" alt="" />
                                                ) : (
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                                                        {(c.display_name || '?')[0]}
                                                    </div>
                                                )}
                                                <div>
                                                    <p className="font-medium text-slate-800">{c.display_name || 'Unknown'}</p>
                                                    {c.handle && (
                                                        <p className="text-xs text-slate-400">{c.handle}</p>
                                                    )}
                                                </div>
                                                {c.country_code && (
                                                    <Badge variant="outline" className="ml-1 text-[10px]">{c.country_code}</Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-slate-600">{formatNumber(c.follower_count)}</td>
                                        <td className="px-4 py-3 text-right font-mono font-semibold text-emerald-600">{formatCurrency(c.total_gmv)}</td>
                                        <td className="px-4 py-3 text-right font-mono text-slate-600">{formatNumber(c.total_orders)}</td>
                                        <td className="px-4 py-3 text-right font-mono text-amber-600">{formatCurrency(c.total_commission)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            {/* Pagination */}
            {pagination.last_page > 1 && (
                <div className="flex justify-center gap-2">
                    <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
                    <span className="flex items-center text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <Button size="sm" variant="outline" disabled={page >= pagination.last_page} onClick={() => setPage(page + 1)}>Next</Button>
                </div>
            )}
        </div>
    );
}

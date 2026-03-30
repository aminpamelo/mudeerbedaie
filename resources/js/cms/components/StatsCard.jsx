import { Eye, Heart, MessageCircle, Share2, BarChart3 } from 'lucide-react';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';

const METRICS = [
    { key: 'views', label: 'Views', icon: Eye, color: 'text-indigo-600', bg: 'bg-indigo-50' },
    { key: 'likes', label: 'Likes', icon: Heart, color: 'text-purple-600', bg: 'bg-purple-50' },
    { key: 'comments', label: 'Comments', icon: MessageCircle, color: 'text-amber-600', bg: 'bg-amber-50' },
    { key: 'shares', label: 'Shares', icon: Share2, color: 'text-emerald-600', bg: 'bg-emerald-50' },
];

const LINE_COLORS = {
    views: '#6366f1',
    likes: '#a855f7',
    comments: '#f59e0b',
    shares: '#10b981',
};

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
    });
}

export default function StatsCard({ stats = [] }) {
    if (!stats || stats.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                    <BarChart3 className="h-10 w-10 text-slate-300 mb-3" />
                    <p className="text-sm text-slate-500">No stats recorded yet</p>
                </CardContent>
            </Card>
        );
    }

    const latest = stats[stats.length - 1];

    const chartData = stats.map((s) => ({
        date: formatDate(s.fetched_at),
        views: s.views || 0,
        likes: s.likes || 0,
        comments: s.comments || 0,
        shares: s.shares || 0,
    }));

    return (
        <div className="space-y-4">
            {/* Metric cards grid */}
            <div className="grid grid-cols-2 gap-3">
                {METRICS.map(({ key, label, icon: Icon, color, bg }) => (
                    <div
                        key={key}
                        className="flex items-center gap-3 rounded-lg border border-slate-200/80 bg-white p-3"
                    >
                        <div className={`flex h-9 w-9 items-center justify-center rounded-lg ${bg}`}>
                            <Icon className={`h-4 w-4 ${color}`} />
                        </div>
                        <div>
                            <p className="text-lg font-semibold text-slate-800">
                                {(latest[key] || 0).toLocaleString()}
                            </p>
                            <p className="text-xs text-slate-500">{label}</p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Trend chart (only if multiple entries) */}
            {stats.length > 1 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Performance Trends</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                                    <XAxis
                                        dataKey="date"
                                        tick={{ fontSize: 11, fill: '#94a3b8' }}
                                        tickLine={false}
                                        axisLine={{ stroke: '#e2e8f0' }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 11, fill: '#94a3b8' }}
                                        tickLine={false}
                                        axisLine={{ stroke: '#e2e8f0' }}
                                        tickFormatter={(v) =>
                                            v >= 1000 ? `${(v / 1000).toFixed(1)}k` : v
                                        }
                                    />
                                    <Tooltip
                                        contentStyle={{
                                            borderRadius: '8px',
                                            border: '1px solid #e2e8f0',
                                            boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                        }}
                                        formatter={(value, name) => [
                                            value.toLocaleString(),
                                            name.charAt(0).toUpperCase() + name.slice(1),
                                        ]}
                                    />
                                    <Legend />
                                    {METRICS.map(({ key, label }) => (
                                        <Line
                                            key={key}
                                            type="monotone"
                                            dataKey={key}
                                            name={label}
                                            stroke={LINE_COLORS[key]}
                                            strokeWidth={2}
                                            dot={{ r: 3 }}
                                            activeDot={{ r: 5 }}
                                        />
                                    ))}
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

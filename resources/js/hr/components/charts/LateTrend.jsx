import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';

export default function LateTrend({ data = [] }) {
    return (
        <ResponsiveContainer width="100%" height={250}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                <XAxis
                    dataKey="date"
                    tick={{ fontSize: 12 }}
                    stroke="#a1a1aa"
                />
                <YAxis
                    tick={{ fontSize: 12 }}
                    stroke="#a1a1aa"
                    allowDecimals={false}
                />
                <Tooltip />
                <Line
                    type="monotone"
                    dataKey="count"
                    stroke="#eab308"
                    strokeWidth={2}
                    dot={{ r: 3 }}
                    activeDot={{ r: 5 }}
                    name="Late Arrivals"
                />
            </LineChart>
        </ResponsiveContainer>
    );
}

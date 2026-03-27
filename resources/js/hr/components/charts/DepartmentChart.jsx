import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';

export default function DepartmentChart({ data = [] }) {
    return (
        <ResponsiveContainer width="100%" height={300}>
            <BarChart data={data} layout="vertical">
                <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                <XAxis
                    type="number"
                    domain={[0, 100]}
                    tick={{ fontSize: 12 }}
                    stroke="#a1a1aa"
                    unit="%"
                />
                <YAxis
                    type="category"
                    dataKey="department"
                    tick={{ fontSize: 12 }}
                    stroke="#a1a1aa"
                    width={120}
                />
                <Tooltip
                    formatter={(value) => [`${value}%`, 'Attendance Rate']}
                />
                <Bar
                    dataKey="rate"
                    fill="#3b82f6"
                    radius={[0, 4, 4, 0]}
                    barSize={20}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}

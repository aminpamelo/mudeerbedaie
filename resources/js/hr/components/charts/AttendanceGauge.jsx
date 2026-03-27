import { RadialBarChart, RadialBar, ResponsiveContainer } from 'recharts';

function getColor(rate) {
    if (rate > 90) return '#22c55e';
    if (rate > 75) return '#eab308';
    return '#ef4444';
}

export default function AttendanceGauge({ rate = 0, label = 'Attendance' }) {
    const color = getColor(rate);
    const data = [{ name: label, value: rate, fill: color }];

    return (
        <div className="relative h-40 w-40">
            <ResponsiveContainer width="100%" height="100%">
                <RadialBarChart
                    cx="50%"
                    cy="50%"
                    innerRadius="70%"
                    outerRadius="100%"
                    startAngle={180}
                    endAngle={0}
                    data={data}
                    barSize={10}
                >
                    <RadialBar
                        dataKey="value"
                        cornerRadius={5}
                        background={{ fill: '#f4f4f5' }}
                    />
                </RadialBarChart>
            </ResponsiveContainer>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span
                    className="text-2xl font-bold"
                    style={{ color }}
                >
                    {Math.round(rate)}%
                </span>
                <span className="text-xs text-zinc-500">{label}</span>
            </div>
        </div>
    );
}

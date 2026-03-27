import { cn } from '../../lib/utils';

const statusColors = {
    present: 'bg-green-500',
    late: 'bg-yellow-500',
    absent: 'bg-red-500',
    wfh: 'bg-blue-500',
    on_leave: 'bg-purple-500',
    holiday: 'bg-zinc-400',
};

const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function WeekSummary({ data = [] }) {
    return (
        <div className="flex items-center gap-3">
            {data.map((day, index) => (
                <div
                    key={day.date || index}
                    className="flex flex-col items-center gap-1"
                >
                    <span className="text-xs font-medium text-zinc-500">
                        {dayLabels[index] || ''}
                    </span>
                    <div
                        className={cn(
                            'h-3 w-3 rounded-full',
                            statusColors[day.status] || 'bg-zinc-200'
                        )}
                        title={`${day.status || 'N/A'} - ${day.date || ''}`}
                    />
                    <span className="text-[10px] text-zinc-400">
                        {day.date
                            ? new Date(day.date).getDate()
                            : ''}
                    </span>
                </div>
            ))}
        </div>
    );
}

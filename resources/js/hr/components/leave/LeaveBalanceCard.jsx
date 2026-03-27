import { cn } from '../../lib/utils';

export default function LeaveBalanceCard({ balance }) {
    const {
        type_name,
        color = '#3b82f6',
        entitled_days = 0,
        used_days = 0,
        pending_days = 0,
        available_days = 0,
        carried_forward_days = 0,
    } = balance;

    const usedPercent =
        entitled_days > 0
            ? Math.min(100, Math.round((used_days / entitled_days) * 100))
            : 0;

    return (
        <div
            className="rounded-xl border border-zinc-200 bg-white shadow-sm"
            style={{ borderLeftWidth: '4px', borderLeftColor: color }}
        >
            <div className="p-4">
                <h3 className="text-sm font-semibold text-zinc-900">
                    {type_name}
                </h3>

                <div className="mt-3">
                    <div className="mb-1 flex items-center justify-between text-xs text-zinc-500">
                        <span>
                            {used_days} / {entitled_days} days used
                        </span>
                        <span>{usedPercent}%</span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                        <div
                            className="h-full rounded-full transition-all"
                            style={{
                                width: `${usedPercent}%`,
                                backgroundColor: color,
                            }}
                        />
                    </div>
                </div>

                <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p className="text-lg font-bold text-zinc-900">
                            {available_days}
                        </p>
                        <p className="text-[10px] text-zinc-500">Available</p>
                    </div>
                    <div>
                        <p className="text-lg font-bold text-zinc-900">
                            {pending_days}
                        </p>
                        <p className="text-[10px] text-zinc-500">Pending</p>
                    </div>
                    <div>
                        <p className="text-lg font-bold text-zinc-900">
                            {carried_forward_days}
                        </p>
                        <p className="text-[10px] text-zinc-500">Carried</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

import { Loader2, LogIn, LogOut } from 'lucide-react';
import { cn } from '../../lib/utils';

export default function ClockButton({
    type = 'in',
    onClick,
    loading = false,
    disabled = false,
    time,
}) {
    const isClockIn = type === 'in';

    return (
        <div className="flex flex-col items-center gap-3">
            <button
                type="button"
                onClick={onClick}
                disabled={disabled || loading}
                className={cn(
                    'flex h-40 w-40 flex-col items-center justify-center rounded-full border-4 shadow-lg transition-all',
                    isClockIn
                        ? 'border-green-300 bg-green-500 text-white hover:bg-green-600'
                        : 'border-red-300 bg-red-500 text-white hover:bg-red-600',
                    !disabled && !loading && 'animate-pulse',
                    (disabled || loading) && 'cursor-not-allowed opacity-50'
                )}
            >
                {loading ? (
                    <Loader2 className="h-10 w-10 animate-spin" />
                ) : isClockIn ? (
                    <LogIn className="h-10 w-10" />
                ) : (
                    <LogOut className="h-10 w-10" />
                )}
                <span className="mt-2 text-lg font-bold">
                    {loading
                        ? 'Processing...'
                        : isClockIn
                          ? 'Clock In'
                          : 'Clock Out'}
                </span>
            </button>
            {time && (
                <p className="text-2xl font-semibold tabular-nums text-zinc-700">
                    {time}
                </p>
            )}
        </div>
    );
}

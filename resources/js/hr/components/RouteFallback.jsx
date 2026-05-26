import { Loader2 } from 'lucide-react';

/**
 * Shown by <Suspense> while a lazy-loaded route chunk is downloading.
 * Kept lean — heavy skeletons would themselves slow first paint.
 */
export function RouteFallback() {
    return (
        <div className="flex min-h-[50vh] items-center justify-center">
            <div className="flex flex-col items-center gap-3">
                <div className="relative h-12 w-12">
                    <div className="absolute inset-0 rounded-full bg-gradient-to-br from-indigo-400 via-pink-400 to-orange-300 opacity-30 blur-xl hr-breathe" />
                    <div className="relative flex h-full w-full items-center justify-center">
                        <Loader2 className="h-6 w-6 animate-spin text-indigo-500" strokeWidth={2.5} />
                    </div>
                </div>
                <p className="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Loading</p>
            </div>
        </div>
    );
}

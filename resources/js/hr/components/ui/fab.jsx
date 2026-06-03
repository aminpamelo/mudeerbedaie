import { cn } from '../../lib/utils';

/**
 * Floating action button — mobile-first primary action.
 * Positioned bottom-right above the bottom nav (uses safe-area-inset).
 *
 * <Fab icon={Plus} onClick={() => navigate('/my/leave/apply')}>
 *   Apply Leave
 * </Fab>
 *
 * <Fab icon={Clock} to="/clock" variant="solid">Clock In</Fab>
 */
export function Fab({ icon: Icon, children, onClick, to, ariaLabel, variant = 'sunrise', className }) {
    const variants = {
        sunrise: 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 text-white shadow-xl shadow-pink-500/40 hover:shadow-2xl hover:shadow-pink-500/50',
        emerald: 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white shadow-xl shadow-emerald-500/40 hover:shadow-2xl hover:shadow-emerald-500/50',
        rose: 'bg-gradient-to-r from-orange-500 via-rose-500 to-fuchsia-500 text-white shadow-xl shadow-rose-500/40 hover:shadow-2xl hover:shadow-rose-500/50',
    };

    const baseClasses = cn(
        'group fixed right-4 z-30 inline-flex items-center gap-2 rounded-full px-4 py-3 text-sm font-bold uppercase tracking-wider transition-all active:scale-95',
        'focus:outline-none focus-visible:ring-4 focus-visible:ring-offset-2 focus-visible:ring-pink-300 dark:ring-offset-[#080C16]',
        // bottom-20 (5rem) on mobile to clear the bottom tab bar; safe area added inline
        'bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:bottom-6',
        variants[variant],
        className
    );

    const content = (
        <>
            {Icon && <Icon className="h-4 w-4" strokeWidth={2.5} />}
            <span>{children}</span>
        </>
    );

    if (to) {
        // dynamic import wouldn't help here; use anchor with onClick for SPA nav
        return (
            <a
                href={to}
                onClick={(e) => {
                    e.preventDefault();
                    onClick?.(e);
                }}
                aria-label={ariaLabel || (typeof children === 'string' ? children : undefined)}
                className={baseClasses}
            >
                {content}
            </a>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={ariaLabel || (typeof children === 'string' ? children : undefined)}
            className={baseClasses}
        >
            {content}
        </button>
    );
}

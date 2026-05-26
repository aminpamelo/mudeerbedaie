import { cn } from '../../lib/utils';

/**
 * Saturated gradient hero — for top-level entry pages (Dashboard, module landing pages).
 * Use sparingly: one per page, never as a section divider.
 *
 * <PageHero
 *   eyebrow="Monday, 25 May 2026"
 *   title="Good morning, Admin"
 *   description="Here's what's happening today."
 *   actions={<>...buttons...</>}
 * />
 */
export function PageHero({ eyebrow, title, description, actions, className }) {
    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500 p-6 sm:p-8 shadow-xl shadow-indigo-500/20',
                className
            )}
        >
            <div className="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-fuchsia-400/40 blur-3xl" aria-hidden />
            <div className="absolute -left-24 -bottom-24 h-72 w-72 rounded-full bg-indigo-400/40 blur-3xl" aria-hidden />
            <div className="absolute right-1/3 top-1/2 h-48 w-48 rounded-full bg-violet-300/30 blur-3xl" aria-hidden />
            <div
                className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.18),transparent_50%)]"
                aria-hidden
            />
            <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    {eyebrow && (
                        <span className="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/90 backdrop-blur-sm ring-1 ring-white/20">
                            {eyebrow}
                        </span>
                    )}
                    <h1 className={cn('text-3xl font-bold tracking-tight text-white sm:text-[34px] drop-shadow-sm', eyebrow && 'mt-3')}>
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1.5 text-sm text-indigo-50/90">{description}</p>
                    )}
                </div>
                {actions && (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Glass button — designed to sit on top of PageHero (saturated gradient background).
 */
export function PageHeroButton({ children, variant = 'glass', className, ...props }) {
    const styles = {
        glass: 'bg-white/15 text-white ring-1 ring-white/25 backdrop-blur-sm hover:bg-white/25 focus-visible:ring-white focus-visible:ring-offset-indigo-600',
        solid: 'bg-white text-indigo-700 shadow-sm hover:bg-indigo-50 focus-visible:ring-white focus-visible:ring-offset-indigo-600',
    };
    return (
        <button
            className={cn(
                'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                styles[variant],
                className
            )}
            {...props}
        >
            {children}
        </button>
    );
}

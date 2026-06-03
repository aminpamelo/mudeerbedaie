import { cn } from '../../lib/utils';

/**
 * Semantic status badge with built-in color mapping.
 * Avoids per-page color string drift across the HR module.
 *
 * <StatusBadge status="pending" />     // amber
 * <StatusBadge status="approved" />    // emerald
 * <StatusBadge status="rejected" />    // rose
 * <StatusBadge status="active" />      // emerald
 * <StatusBadge status="inactive" />    // slate
 * <StatusBadge status="draft" />       // slate
 * <StatusBadge status="overdue" />     // red (stronger than rejected)
 * <StatusBadge status="probation" />   // amber
 * <StatusBadge status="resigned" />    // rose
 *
 * Pass children to override label:
 * <StatusBadge status="pending">Awaiting review</StatusBadge>
 */
const STATUS_STYLES = {
    // Positive
    active: { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Active' },
    approved: { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Approved' },
    confirmed: { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Confirmed' },
    completed: { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Completed' },
    paid: { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Paid' },
    success: { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Success' },

    // Warning / In-progress
    pending: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500', label: 'Pending' },
    probation: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500', label: 'Probation' },
    review: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500', label: 'In review' },
    processing: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500', label: 'Processing' },
    warning: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500', label: 'Warning' },

    // Info / Neutral-positive
    info: { bg: 'bg-sky-100', text: 'text-sky-800', dot: 'bg-sky-500', label: 'Info' },
    upcoming: { bg: 'bg-sky-100', text: 'text-sky-800', dot: 'bg-sky-500', label: 'Upcoming' },
    in_progress: { bg: 'bg-sky-100', text: 'text-sky-800', dot: 'bg-sky-500', label: 'In progress' },
    live: { bg: 'bg-sky-100', text: 'text-sky-800', dot: 'bg-sky-500', label: 'Live' },

    // Negative
    rejected: { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-500', label: 'Rejected' },
    cancelled: { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-500', label: 'Cancelled' },
    failed: { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-500', label: 'Failed' },
    resigned: { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-500', label: 'Resigned' },
    terminated: { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-500', label: 'Terminated' },
    overdue: { bg: 'bg-red-100', text: 'text-red-800', dot: 'bg-red-500', label: 'Overdue' },
    expired: { bg: 'bg-rose-100', text: 'text-rose-800', dot: 'bg-rose-500', label: 'Expired' },

    // Neutral
    draft: { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-500', label: 'Draft' },
    inactive: { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-500', label: 'Inactive' },
    archived: { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-500', label: 'Archived' },
    closed: { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-500', label: 'Closed' },

    // Accent / Brand
    new: { bg: 'bg-violet-100', text: 'text-violet-800', dot: 'bg-violet-500', label: 'New' },
    featured: { bg: 'bg-indigo-100', text: 'text-indigo-800', dot: 'bg-indigo-500', label: 'Featured' },
};

// Dark-mode mapping keyed by the badge's dot color → translucent fill + light text
const DARK_BY_DOT = {
    'bg-emerald-500': 'dark:bg-emerald-500/15 dark:text-emerald-300',
    'bg-amber-500': 'dark:bg-amber-500/15 dark:text-amber-300',
    'bg-sky-500': 'dark:bg-sky-500/15 dark:text-sky-300',
    'bg-rose-500': 'dark:bg-rose-500/15 dark:text-rose-300',
    'bg-red-500': 'dark:bg-red-500/15 dark:text-red-300',
    'bg-violet-500': 'dark:bg-violet-500/15 dark:text-violet-300',
    'bg-indigo-500': 'dark:bg-indigo-500/15 dark:text-indigo-300',
    'bg-slate-500': 'dark:bg-white/[0.08] dark:text-slate-300',
};

export function StatusBadge({ status, children, showDot = false, size = 'md', className }) {
    const style = STATUS_STYLES[status] || STATUS_STYLES.draft;
    const sizes = {
        sm: 'text-[10px] px-2 py-0.5',
        md: 'text-[11px] px-2.5 py-0.5',
        lg: 'text-xs px-3 py-1',
    };
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full font-semibold',
                sizes[size],
                style.bg,
                style.text,
                DARK_BY_DOT[style.dot],
                className
            )}
        >
            {showDot && <span className={cn('h-1.5 w-1.5 rounded-full', style.dot)} />}
            {children || style.label}
        </span>
    );
}

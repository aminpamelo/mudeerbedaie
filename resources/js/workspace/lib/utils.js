export function cn(...classes) {
    return classes.filter(Boolean).join(' ');
}

export function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ms-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

export const PRIORITY_COLORS = {
    low: 'bg-slate-100 text-slate-600',
    medium: 'bg-blue-100 text-blue-700',
    high: 'bg-amber-100 text-amber-700',
    urgent: 'bg-red-100 text-red-700',
};

export const STATUS_COLORS = {
    todo: 'bg-slate-100 text-slate-600',
    in_progress: 'bg-blue-100 text-blue-700',
    review: 'bg-purple-100 text-purple-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
};

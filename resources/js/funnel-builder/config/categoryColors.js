/**
 * Funnel Category color palette.
 * Keeps the tailwind class strings here so they're not purged from production builds.
 */

export const CATEGORY_COLORS = [
    {
        value: 'zinc',
        label: 'Slate',
        dot: 'bg-zinc-400',
        badge: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        accent: 'bg-zinc-500',
    },
    {
        value: 'blue',
        label: 'Blue',
        dot: 'bg-blue-500',
        badge: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        accent: 'bg-blue-500',
    },
    {
        value: 'emerald',
        label: 'Green',
        dot: 'bg-emerald-500',
        badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        accent: 'bg-emerald-500',
    },
    {
        value: 'amber',
        label: 'Amber',
        dot: 'bg-amber-500',
        badge: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        accent: 'bg-amber-500',
    },
    {
        value: 'rose',
        label: 'Rose',
        dot: 'bg-rose-500',
        badge: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        accent: 'bg-rose-500',
    },
    {
        value: 'violet',
        label: 'Violet',
        dot: 'bg-violet-500',
        badge: 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
        accent: 'bg-violet-500',
    },
    {
        value: 'sky',
        label: 'Sky',
        dot: 'bg-sky-500',
        badge: 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
        accent: 'bg-sky-500',
    },
    {
        value: 'orange',
        label: 'Orange',
        dot: 'bg-orange-500',
        badge: 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        accent: 'bg-orange-500',
    },
];

const COLOR_MAP = Object.fromEntries(CATEGORY_COLORS.map((c) => [c.value, c]));

export function getCategoryColorClasses(value) {
    return COLOR_MAP[value] || COLOR_MAP.zinc;
}

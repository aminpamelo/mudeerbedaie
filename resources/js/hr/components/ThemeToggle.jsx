import { Sun, Moon } from 'lucide-react';
import { cn } from '../lib/utils';
import useHrStore from '../stores/useHrStore';

/**
 * Night-mode switch. `variant="icon"` (default) renders a compact icon button
 * for the mobile header; `variant="row"` renders a full-width labelled row for
 * the desktop sidebar footer.
 */
export default function ThemeToggle({ variant = 'icon', className }) {
    const theme = useHrStore((s) => s.theme);
    const toggleTheme = useHrStore((s) => s.toggleTheme);
    const isDark = theme === 'dark';
    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    if (variant === 'row') {
        return (
            <button
                type="button"
                onClick={toggleTheme}
                aria-label={label}
                title={label}
                className={cn(
                    'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200',
                    className
                )}
            >
                <span className="relative flex h-4 w-4 items-center justify-center">
                    <Sun className={cn('absolute h-4 w-4 transition-all duration-300', isDark ? 'scale-0 -rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100')} />
                    <Moon className={cn('absolute h-4 w-4 transition-all duration-300', isDark ? 'scale-100 rotate-0 opacity-100' : 'scale-0 rotate-90 opacity-0')} />
                </span>
                {isDark ? 'Light mode' : 'Dark mode'}
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={label}
            title={label}
            className={cn(
                'relative flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 active:scale-95 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200',
                className
            )}
        >
            <Sun className={cn('absolute h-5 w-5 transition-all duration-300', isDark ? 'scale-0 -rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100')} strokeWidth={2} />
            <Moon className={cn('absolute h-5 w-5 transition-all duration-300', isDark ? 'scale-100 rotate-0 opacity-100' : 'scale-0 rotate-90 opacity-0')} strokeWidth={2} />
        </button>
    );
}

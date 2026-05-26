import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { CheckCircle2, AlertCircle, Info, X, AlertTriangle } from 'lucide-react';
import { cn } from '../lib/utils';

const ToastContext = createContext(null);

let nextId = 1;

/**
 * <ToastProvider> at the app root.
 * Anywhere inside, call useToast() to push a toast:
 *   const { toast } = useToast();
 *   toast.success('Saved!');
 *   toast.error('Failed to load');
 *   toast.info('Processing…');
 *   toast.warning('Check your input');
 *   toast({ title: 'Custom', description: '…', accent: 'indigo', duration: 4000 });
 */
export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const remove = useCallback((id) => {
        setToasts((curr) => curr.filter((t) => t.id !== id));
    }, []);

    const push = useCallback((opts) => {
        const id = nextId++;
        const toast = {
            id,
            duration: 4000,
            accent: 'indigo',
            ...opts,
        };
        setToasts((curr) => [...curr, toast]);
        if (toast.duration > 0) {
            setTimeout(() => remove(id), toast.duration);
        }
        return id;
    }, [remove]);

    const api = {
        toast: Object.assign(
            (opts) => push(typeof opts === 'string' ? { title: opts } : opts),
            {
                success: (title, description) => push({ accent: 'emerald', title, description, icon: CheckCircle2 }),
                error: (title, description) => push({ accent: 'rose', title, description, icon: AlertCircle }),
                info: (title, description) => push({ accent: 'sky', title, description, icon: Info }),
                warning: (title, description) => push({ accent: 'amber', title, description, icon: AlertTriangle }),
            }
        ),
        dismiss: remove,
    };

    return (
        <ToastContext.Provider value={api}>
            {children}
            <ToastViewport toasts={toasts} onDismiss={remove} />
        </ToastContext.Provider>
    );
}

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) {
        return {
            toast: Object.assign(() => 0, { success: () => 0, error: () => 0, info: () => 0, warning: () => 0 }),
            dismiss: () => {},
        };
    }
    return ctx;
}

function ToastViewport({ toasts, onDismiss }) {
    return (
        <div
            aria-live="polite"
            aria-atomic="true"
            className="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4 sm:bottom-4 sm:right-4 sm:top-auto sm:items-end"
        >
            {toasts.map((t) => (
                <ToastItem key={t.id} toast={t} onDismiss={onDismiss} />
            ))}
        </div>
    );
}

function ToastItem({ toast, onDismiss }) {
    const [show, setShow] = useState(false);
    useEffect(() => {
        const id = requestAnimationFrame(() => setShow(true));
        return () => cancelAnimationFrame(id);
    }, []);

    const accents = {
        indigo: { ring: 'ring-indigo-200', bg: 'bg-indigo-50', iconBg: 'bg-indigo-100', iconText: 'text-indigo-600', title: 'text-indigo-900' },
        emerald: { ring: 'ring-emerald-200', bg: 'bg-emerald-50', iconBg: 'bg-emerald-100', iconText: 'text-emerald-600', title: 'text-emerald-900' },
        amber: { ring: 'ring-amber-200', bg: 'bg-amber-50', iconBg: 'bg-amber-100', iconText: 'text-amber-600', title: 'text-amber-900' },
        rose: { ring: 'ring-rose-200', bg: 'bg-rose-50', iconBg: 'bg-rose-100', iconText: 'text-rose-600', title: 'text-rose-900' },
        sky: { ring: 'ring-sky-200', bg: 'bg-sky-50', iconBg: 'bg-sky-100', iconText: 'text-sky-600', title: 'text-sky-900' },
        violet: { ring: 'ring-violet-200', bg: 'bg-violet-50', iconBg: 'bg-violet-100', iconText: 'text-violet-600', title: 'text-violet-900' },
    };
    const c = accents[toast.accent] || accents.indigo;
    const Icon = toast.icon || Info;

    return (
        <div
            className={cn(
                'pointer-events-auto relative flex w-full max-w-sm items-start gap-3 rounded-2xl border border-white/60 bg-white/95 p-3 shadow-xl ring-1 backdrop-blur-md transition-all',
                c.ring,
                show ? 'translate-y-0 opacity-100 scale-100' : '-translate-y-2 opacity-0 scale-95'
            )}
        >
            <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', c.iconBg)}>
                <Icon className={cn('h-4 w-4', c.iconText)} strokeWidth={2.25} />
            </div>
            <div className="min-w-0 flex-1">
                {toast.title && (
                    <p className={cn('text-sm font-semibold', c.title)}>{toast.title}</p>
                )}
                {toast.description && (
                    <p className="mt-0.5 text-xs text-slate-600">{toast.description}</p>
                )}
            </div>
            <button
                onClick={() => onDismiss(toast.id)}
                aria-label="Dismiss"
                className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

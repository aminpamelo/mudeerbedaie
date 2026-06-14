import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-react';
import { cn } from '@/ceo/lib/utils';

const ToastContext = createContext(null);

/** Monotonic id so React keys stay stable without Date.now()/Math.random(). */
let toastSeq = 0;

const TONES = {
  success: { icon: CheckCircle2, bar: 'var(--color-emerald)', tint: 'var(--color-emerald-ink)', soft: 'rgba(16,185,129,0.12)' },
  error: { icon: AlertCircle, bar: 'var(--color-rose)', tint: 'var(--color-rose-ink)', soft: 'rgba(244,63,94,0.12)' },
  info: { icon: Info, bar: 'var(--color-brand)', tint: 'var(--color-brand-ink)', soft: 'rgba(99,102,241,0.12)' },
};

/** One frosted toast card — icon + message + dismiss, color-coded with an icon (never colour alone). */
function ToastItem({ toast, onDismiss }) {
  const tone = TONES[toast.type] ?? TONES.info;
  const Icon = tone.icon;

  return (
    <div
      role="status"
      className="toast-in pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-[14px] border border-[rgba(15,23,42,0.08)] bg-white/95 py-3 pl-4 pr-2.5 shadow-[0_18px_46px_-16px_rgba(11,18,32,0.45)] backdrop-blur-xl"
    >
      <span className="absolute inset-y-0 left-0 w-1" style={{ background: tone.bar }} aria-hidden="true" />
      <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full" style={{ background: tone.soft, color: tone.tint }}>
        <Icon className="h-4 w-4" strokeWidth={2.2} />
      </span>
      <p className="min-w-0 flex-1 pt-0.5 text-[13px] font-medium leading-snug text-ink">{toast.message}</p>
      <button
        type="button"
        onClick={() => onDismiss(toast.id)}
        aria-label={toast.dismissLabel ?? 'Dismiss'}
        className="grid h-6 w-6 shrink-0 place-items-center rounded-lg text-muted-2 transition-colors hover:bg-[rgba(15,23,42,0.06)] hover:text-ink"
      >
        <X className="h-3.5 w-3.5" strokeWidth={2.2} />
      </button>
    </div>
  );
}

/**
 * Toast host + context for the CEO bundle. Mount once near the root (CeoLayout)
 * so any descendant can call `useToast()` to surface action feedback after an
 * Inertia mutation. Toasts auto-dismiss, stack newest-on-top, announce politely
 * to screen readers, and never steal focus.
 */
export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const timers = useRef({});

  const dismiss = useCallback((id) => {
    setToasts((list) => list.filter((tt) => tt.id !== id));
    if (timers.current[id]) {
      clearTimeout(timers.current[id]);
      delete timers.current[id];
    }
  }, []);

  const push = useCallback(
    (message, { type = 'success', duration, dismissLabel } = {}) => {
      if (!message) {
        return null;
      }
      const id = ++toastSeq;
      const ttl = duration ?? (type === 'error' ? 6000 : 4000);
      setToasts((list) => [{ id, message, type, dismissLabel }, ...list].slice(0, 4));
      timers.current[id] = setTimeout(() => dismiss(id), ttl);
      return id;
    },
    [dismiss]
  );

  const toast = useMemo(
    () => ({
      success: (message, opts) => push(message, { ...opts, type: 'success' }),
      error: (message, opts) => push(message, { ...opts, type: 'error' }),
      info: (message, opts) => push(message, { ...opts, type: 'info' }),
    }),
    [push]
  );

  useEffect(() => {
    const pending = timers.current;
    return () => Object.values(pending).forEach(clearTimeout);
  }, []);

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div
        aria-live="polite"
        aria-atomic="false"
        className={cn(
          'pointer-events-none fixed z-[80] flex flex-col gap-2',
          // Mobile: bottom-centred, clearing the fixed bottom tab bar + safe area.
          'inset-x-0 bottom-[calc(6rem+env(safe-area-inset-bottom))] items-center px-4',
          // Desktop: top-right, out of the way of the content and actions.
          'sm:inset-x-auto sm:right-5 sm:top-5 sm:bottom-auto sm:items-end sm:px-0'
        )}
      >
        {toasts.map((tt) => (
          <div key={tt.id} className="relative w-full max-w-[min(92vw,360px)]">
            <ToastItem toast={tt} onDismiss={dismiss} />
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

/**
 * Access the toast API. Falls back to no-ops when called outside a provider so a
 * component never crashes if rendered in isolation (e.g. a test harness).
 */
export function useToast() {
  return useContext(ToastContext) ?? { success() {}, error() {}, info() {} };
}

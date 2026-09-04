import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { X, Inbox, Loader2 } from 'lucide-react';
import { cn } from '@/cekbot-admin/lib/utils';

/** Dark-themed card surface used across Cekbot. */
export function Card({ className, children, ...rest }) {
  return (
    <div className={cn('rounded-2xl border border-white/8 bg-white/6', className)} {...rest}>
      {children}
    </div>
  );
}

/** Color-coded badge. */
const BADGE_COLORS = {
  slate: 'bg-slate-500/15 text-slate-300 ring-slate-400/20',
  emerald: 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/20',
  green: 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/20',
  red: 'bg-rose-500/15 text-rose-300 ring-rose-400/20',
  blue: 'bg-sky-500/15 text-sky-300 ring-sky-400/20',
  yellow: 'bg-amber-500/15 text-amber-300 ring-amber-400/20',
  amber: 'bg-amber-500/15 text-amber-300 ring-amber-400/20',
};

export function Badge({ color = 'slate', className, children }) {
  return (
    <span className={cn(
      'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
      BADGE_COLORS[color] || BADGE_COLORS.slate,
      className,
    )}>
      {children}
    </span>
  );
}

/** Primary / secondary / ghost / danger button. Renders as <button> or <Link>. */
export function Button({ variant = 'secondary', size = 'md', href, className, loading = false, children, ...rest }) {
  const base = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-50';
  const sizes = {
    sm: 'h-8 px-3 text-[12.5px]',
    md: 'h-10 px-4 text-[13.5px]',
  };
  const variants = {
    primary: 'bg-gradient-to-br from-emerald-500 to-green-500 text-white shadow-[0_10px_24px_-12px_rgba(16,185,129,0.9)] hover:-translate-y-px',
    secondary: 'bg-white/8 text-white/80 ring-1 ring-inset ring-white/10 hover:bg-white/12 hover:text-white',
    ghost: 'text-white/50 hover:bg-white/8 hover:text-white',
    danger: 'bg-rose-500/15 text-rose-400 ring-1 ring-inset ring-rose-500/20 hover:bg-rose-500/25',
  };
  const cls = cn(base, sizes[size], variants[variant], className);

  const content = (
    <>
      {loading && <Loader2 className="h-4 w-4 animate-spin" strokeWidth={2.2} />}
      {children}
    </>
  );

  if (href) {
    const external = href.startsWith('http') || rest.target === '_blank';
    return external
      ? <a href={href} className={cls} {...rest}>{content}</a>
      : <Link href={href} className={cls} {...rest}>{content}</Link>;
  }

  return <button type="button" className={cls} disabled={loading || rest.disabled} {...rest}>{content}</button>;
}

/** Form field wrapper with label + error. */
export function Field({ label, hint, error, children, className }) {
  return (
    <div className={className}>
      {label && <label className="mb-1.5 block text-[12.5px] font-semibold text-white/70">{label}</label>}
      {children}
      {hint && !error && <p className="mt-1 text-[11.5px] text-white/40">{hint}</p>}
      {error && <p className="mt-1 text-[11.5px] font-semibold text-rose-400" role="alert">{error}</p>}
    </div>
  );
}

const inputCls = 'w-full rounded-xl border-0 bg-white/8 px-3.5 py-2.5 text-[13.5px] text-white ring-1 ring-inset ring-white/10 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-emerald-500/60';

export function Input({ className, ...rest }) {
  return <input className={cn(inputCls, className)} {...rest} />;
}

export function Textarea({ className, ...rest }) {
  return <textarea className={cn(inputCls, 'resize-y', className)} {...rest} />;
}

export function Select({ className, children, ...rest }) {
  return <select className={cn(inputCls, 'cursor-pointer appearance-none pr-9', className)} {...rest}>{children}</select>;
}

/** On/off switch. */
export function Toggle({ checked, onChange, disabled }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      onClick={() => !disabled && onChange(!checked)}
      className={cn(
        'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-40',
        checked ? 'bg-emerald-500' : 'bg-white/15'
      )}
    >
      <span className={cn('inline-block h-4 w-4 transform rounded-full bg-white transition-transform', checked ? 'translate-x-6' : 'translate-x-1')} />
    </button>
  );
}

/** Empty state placeholder. */
export function EmptyState({ icon: Icon = Inbox, title, hint, action }) {
  return (
    <div className="grid place-items-center rounded-2xl border border-dashed border-white/10 px-6 py-16 text-center">
      <span className="grid h-12 w-12 place-items-center rounded-xl bg-emerald-500/15">
        <Icon className="h-5 w-5 text-emerald-400" strokeWidth={2} />
      </span>
      <p className="mt-3 text-[14px] font-bold text-white">{title}</p>
      {hint && <p className="mt-1 max-w-sm text-[13px] text-white/50">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

/** Modal dialog. */
export function Modal({ open, onClose, title, hint, children, footer, size = 'md' }) {
  useEffect(() => {
    if (!open) return undefined;
    const onKey = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [open, onClose]);

  if (!open) return null;

  const widths = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl' };

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className={cn(
        'fade-up relative z-10 flex max-h-[85vh] w-full flex-col rounded-2xl border border-white/10 bg-[#0B1A14] shadow-2xl',
        widths[size]
      )}>
        <div className="flex shrink-0 items-start justify-between gap-4 border-b border-white/8 px-5 py-4">
          <div className="min-w-0">
            <h2 className="text-[16px] font-bold text-white">{title}</h2>
            {hint && <p className="mt-0.5 text-[12.5px] text-white/50">{hint}</p>}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-white/40 transition-colors hover:bg-white/10 hover:text-white"
            aria-label="Close"
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4">{children}</div>

        {footer && <div className="flex shrink-0 justify-end gap-2 border-t border-white/8 px-5 py-3">{footer}</div>}
      </div>
    </div>
  );
}

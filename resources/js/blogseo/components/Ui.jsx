import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { X, Inbox } from 'lucide-react';
import { cn, scoreMeta } from '@/blogseo/lib/utils';

/** Card surface used across the workspace. */
export function Card({ className, children, ...rest }) {
  return (
    <div className={cn('rounded-2xl border border-line bg-white', className)} {...rest}>
      {children}
    </div>
  );
}

export function SectionTitle({ title, hint, action }) {
  return (
    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 className="text-[15px] font-bold tracking-[-0.01em] text-ink">{title}</h2>
        {hint && <p className="mt-0.5 text-[12.5px] text-muted">{hint}</p>}
      </div>
      {action}
    </div>
  );
}

export function Badge({ className, children }) {
  return (
    <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', className)}>
      {children}
    </span>
  );
}

/** Primary / secondary / ghost / danger button. Renders as <button> or <Link>. */
export function Button({ variant = 'secondary', size = 'md', href, className, children, ...rest }) {
  const base = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-50';
  const sizes = {
    sm: 'h-8 px-3 text-[12.5px]',
    md: 'h-10 px-4 text-[13.5px]',
  };
  const variants = {
    primary: 'bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-brand-2)] text-white shadow-[0_10px_24px_-12px_rgba(16,185,129,0.9)] hover:-translate-y-px',
    secondary: 'bg-white text-ink-2 ring-1 ring-inset ring-line hover:bg-surface',
    ghost: 'text-muted hover:bg-surface hover:text-ink',
    danger: 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20 hover:bg-rose-100',
  };
  const cls = cn(base, sizes[size], variants[variant], className);

  if (href) {
    const external = href.startsWith('http') || rest.target === '_blank';
    return external
      ? <a href={href} className={cls} {...rest}>{children}</a>
      : <Link href={href} className={cls} {...rest}>{children}</Link>;
  }

  return <button type="button" className={cls} {...rest}>{children}</button>;
}

export function Field({ label, hint, error, children, className }) {
  return (
    <div className={className}>
      {label && <label className="mb-1.5 block text-[12.5px] font-semibold text-ink-2">{label}</label>}
      {children}
      {hint && !error && <p className="mt-1 text-[11.5px] text-muted">{hint}</p>}
      {error && <p className="mt-1 text-[11.5px] font-semibold text-rose-600" role="alert">{error}</p>}
    </div>
  );
}

const inputCls = 'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-[13.5px] text-ink ring-1 ring-inset ring-line placeholder:text-muted-2 focus:outline-none focus:ring-2 focus:ring-[var(--color-brand)]';

export function Input({ className, ...rest }) {
  return <input className={cn(inputCls, className)} {...rest} />;
}

export function Textarea({ className, ...rest }) {
  return <textarea className={cn(inputCls, 'resize-y', className)} {...rest} />;
}

export function Select({ className, children, ...rest }) {
  return <select className={cn(inputCls, 'cursor-pointer appearance-none pr-9', className)} {...rest}>{children}</select>;
}

export function Switch({ checked, onChange, label, id }) {
  return (
    <label htmlFor={id} className="flex cursor-pointer items-center justify-between gap-3">
      <span className="text-[13px] font-medium text-ink-2">{label}</span>
      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative h-6 w-11 shrink-0 rounded-full transition-colors',
          checked ? 'bg-[var(--color-brand)]' : 'bg-slate-200'
        )}
      >
        <span className={cn(
          'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform',
          checked ? 'translate-x-[22px]' : 'translate-x-0.5'
        )} />
      </button>
    </label>
  );
}

/**
 * Radial SEO score gauge. The number is always rendered as text as well, so
 * the score never depends on reading the colour alone.
 */
export function ScoreGauge({ score, size = 96, label, sublabel }) {
  const meta = scoreMeta(score);
  const n = Math.max(0, Math.min(100, Number(score || 0)));

  return (
    // flex-wrap + a min width on the copy: rather than letting the text
    // collapse into a 2-words-per-line column beside the fixed-size gauge, it
    // drops below once there isn't room for it.
    <div className="flex flex-wrap items-center gap-4">
      <div className="relative shrink-0" style={{ width: size, height: size }}>
        <svg viewBox="0 0 36 36" className="-rotate-90" width={size} height={size} role="img" aria-label={`SEO score ${n} out of 100`}>
          <circle cx="18" cy="18" r="15.9155" fill="none" strokeWidth="3" className="stroke-slate-100" />
          <circle
            cx="18" cy="18" r="15.9155" fill="none" strokeWidth="3" strokeLinecap="round"
            className={cn(meta.ring, 'transition-all duration-700')}
            strokeDasharray={`${n} 100`}
          />
        </svg>
        <div className="absolute inset-0 grid place-items-center">
          <div className="text-center leading-none">
            <div className={cn('text-[20px] font-extrabold tabular-nums', meta.text)}>{n}</div>
            <div className="mt-0.5 text-[9px] font-medium uppercase tracking-wide text-muted-2">/ 100</div>
          </div>
        </div>
      </div>
      <div className="min-w-[9rem] flex-1">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted">{label ?? 'SEO score'}</p>
        <p className={cn('text-[18px] font-extrabold', meta.text)}>{meta.grade}</p>
        {sublabel && <p className="mt-1 text-[12px] leading-relaxed text-muted">{sublabel}</p>}
      </div>
    </div>
  );
}

/** Thin score bar used in dense lists. */
export function ScoreBar({ score }) {
  const meta = scoreMeta(score);
  const n = Number(score || 0);

  return (
    <div className="flex items-center gap-2">
      <div className="h-1.5 w-14 overflow-hidden rounded-full bg-slate-100">
        <div className={cn('h-full rounded-full transition-all', meta.bg)} style={{ width: `${Math.max(3, n)}%` }} />
      </div>
      <span className={cn('text-[11.5px] font-bold tabular-nums', meta.text)}>{n}</span>
    </div>
  );
}

export function StatTile({ icon: Icon, label, value, sub, tone = 'brand' }) {
  const tones = {
    brand: 'bg-emerald-50 text-emerald-600',
    sky: 'bg-sky-50 text-sky-600',
    amber: 'bg-amber-50 text-amber-600',
    rose: 'bg-rose-50 text-rose-600',
    slate: 'bg-slate-100 text-slate-500',
  };

  return (
    <Card className="p-4">
      {Icon && (
        <span className={cn('grid h-9 w-9 place-items-center rounded-lg', tones[tone])}>
          <Icon className="h-4 w-4" strokeWidth={2.2} />
        </span>
      )}
      <p className="mt-3 text-[22px] font-extrabold tabular-nums leading-none text-ink">{value}</p>
      <p className="mt-1.5 text-[12.5px] font-medium text-ink-2">{label}</p>
      {sub && <p className="text-[11.5px] text-muted">{sub}</p>}
    </Card>
  );
}

export function EmptyState({ icon: Icon = Inbox, title, hint, action }) {
  return (
    <div className="grid place-items-center rounded-2xl border border-dashed border-line px-6 py-16 text-center">
      <span className="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50">
        <Icon className="h-5 w-5 text-emerald-500" strokeWidth={2} />
      </span>
      <p className="mt-3 text-[14px] font-bold text-ink">{title}</p>
      {hint && <p className="mt-1 max-w-sm text-[13px] text-muted">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

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

  const widths = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
      <div className={cn(
        'fade-up relative z-10 flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl',
        widths[size]
      )}>
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div className="min-w-0">
            <h2 className="text-[16px] font-bold text-ink">{title}</h2>
            {hint && <p className="mt-0.5 text-[12.5px] text-muted">{hint}</p>}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-surface hover:text-ink"
            aria-label="Close"
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>

        <div className="scroll-thin min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>

        {footer && <div className="flex justify-end gap-2 border-t border-line px-5 py-3">{footer}</div>}
      </div>
    </div>
  );
}

/** Laravel paginator links, rendered as Inertia links. */
export function Pagination({ links, meta }) {
  if (!links || links.length <= 3) return null;

  return (
    <nav className="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
      {meta && (
        <p className="text-[12.5px] text-muted">
          Showing <span className="font-semibold text-ink-2">{meta.from ?? 0}</span>–
          <span className="font-semibold text-ink-2">{meta.to ?? 0}</span> of{' '}
          <span className="font-semibold text-ink-2">{meta.total ?? 0}</span>
        </p>
      )}
      <div className="flex flex-wrap items-center gap-1">
        {links.map((link, i) => (
          link.url ? (
            <Link
              key={i}
              href={link.url}
              preserveScroll
              className={cn(
                'grid h-8 min-w-8 place-items-center rounded-lg px-2.5 text-[12.5px] font-semibold transition-colors',
                link.active ? 'bg-[var(--color-brand)] text-white' : 'text-ink-2 ring-1 ring-inset ring-line hover:bg-surface'
              )}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          ) : (
            <span
              key={i}
              className="grid h-8 min-w-8 place-items-center rounded-lg px-2.5 text-[12.5px] font-semibold text-muted-2"
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          )
        ))}
      </div>
    </nav>
  );
}

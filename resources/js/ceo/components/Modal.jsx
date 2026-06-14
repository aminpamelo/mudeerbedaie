import { useEffect } from 'react';
import { X } from 'lucide-react';

const WIDTHS = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' };

/**
 * Generic glass modal shell for the CEO bundle (the bundle ships no dialog
 * primitive). Closes on Escape or backdrop click. The body is provided as
 * children; render any footer/actions inside the children.
 */
export default function Modal({ title, onClose, children, size = 'md', closeLabel = 'Close' }) {
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[60] grid place-items-center p-4">
      <div
        className="absolute inset-0 bg-[rgba(11,18,32,0.55)] backdrop-blur-md"
        onClick={onClose}
        aria-hidden="true"
      />
      {/* Near-opaque surface (not the translucent `glass-card`) so dialog content
          stays crisp and legible over the busy, dimmed dashboard behind it. */}
      <div
        role="dialog"
        aria-modal="true"
        className={`relative z-10 max-h-[90vh] w-full overflow-y-auto rounded-[20px] border border-[rgba(15,23,42,0.08)] bg-white/95 p-6 shadow-[0_24px_70px_-18px_rgba(11,18,32,0.5)] backdrop-blur-xl ${WIDTHS[size] ?? WIDTHS.md}`}
      >
        <div className="mb-4 flex items-center justify-between gap-4">
          <h3 className="font-display text-[16px] text-ink">{title}</h3>
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-white/60 hover:text-ink"
            aria-label={closeLabel}
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

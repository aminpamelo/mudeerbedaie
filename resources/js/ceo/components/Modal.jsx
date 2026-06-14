import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

const WIDTHS = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' };

/**
 * Generic glass dialog for the CEO bundle (the bundle ships no dialog primitive).
 *
 * Rendered through a portal to `document.body` so it can never be trapped — and
 * mis-positioned — by a transformed ancestor. A CSS `transform` on any parent
 * (e.g. the `glass-card:hover` lift) re-bases `position: fixed` to that parent,
 * which otherwise makes the modal jump/slide as the card's hover transition
 * runs. Closes on Escape or backdrop click and locks background scroll.
 */
export default function Modal({ title, onClose, children, size = 'md', closeLabel = 'Close' }) {
  const dialogRef = useRef(null);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);

    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    // Pull focus into the dialog unless a child (e.g. an autoFocus input) has
    // already claimed it.
    if (dialogRef.current && !dialogRef.current.contains(document.activeElement)) {
      dialogRef.current.focus();
    }

    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prevOverflow;
    };
  }, [onClose]);

  if (typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div className="fixed inset-0 z-[60] grid place-items-center p-4">
      <div
        className="scrim-in absolute inset-0 bg-[rgba(11,18,32,0.55)] backdrop-blur-md"
        onClick={onClose}
        aria-hidden="true"
      />
      {/* Near-opaque surface (not the translucent `glass-card`) so dialog content
          stays crisp and legible over the busy, dimmed dashboard behind it. */}
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        tabIndex={-1}
        className={`modal-in relative z-10 max-h-[90vh] w-full overflow-y-auto rounded-[20px] border border-[rgba(15,23,42,0.08)] bg-white/95 p-6 shadow-[0_24px_70px_-18px_rgba(11,18,32,0.5)] outline-none ${WIDTHS[size] ?? WIDTHS.md}`}
      >
        <div className="mb-4 flex items-center justify-between gap-4">
          <h3 className="font-display text-[16px] text-ink">{title}</h3>
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-[rgba(15,23,42,0.06)] hover:text-ink"
            aria-label={closeLabel}
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>
        {children}
      </div>
    </div>,
    document.body
  );
}

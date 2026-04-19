import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Search, CornerDownLeft, ArrowUp, ArrowDown, X } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';

export default function CommandPalette({ open, onClose, items = [] }) {
  const [query, setQuery] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  const inputRef = useRef(null);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();

    if (!q) {
      return items;
    }

    return items.filter((item) => {
      const haystack = `${item.label} ${item.group ?? ''} ${item.keywords ?? ''}`.toLowerCase();

      return haystack.includes(q);
    });
  }, [items, query]);

  useEffect(() => {
    if (open) {
      setQuery('');
      setActiveIndex(0);
      const raf = requestAnimationFrame(() => inputRef.current?.focus());

      return () => cancelAnimationFrame(raf);
    }
  }, [open]);

  useEffect(() => {
    setActiveIndex(0);
  }, [query]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const handler = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();

        return;
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActiveIndex((i) => (filtered.length ? (i + 1) % filtered.length : 0));

        return;
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActiveIndex((i) => (filtered.length ? (i - 1 + filtered.length) % filtered.length : 0));

        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        const target = filtered[activeIndex];

        if (target) {
          onClose();
          router.visit(target.href);
        }
      }
    };

    window.addEventListener('keydown', handler);

    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose, filtered, activeIndex]);

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex items-start justify-center pt-[12vh]"
      role="dialog"
      aria-modal="true"
      onClick={onClose}
    >
      <div className="absolute inset-0 bg-ink/40 backdrop-blur-sm" />
      <div
        className="relative w-full max-w-[560px] overflow-hidden rounded-2xl border border-border bg-white shadow-[0_24px_60px_-12px_rgba(0,0,0,0.25)]"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center gap-3 border-b border-border-2 px-4 py-3">
          <Search className="h-4 w-4 shrink-0 text-muted" strokeWidth={2} />
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Jump to page, search pages..."
            className="flex-1 bg-transparent text-[14px] text-ink placeholder:text-muted-2 focus:outline-none"
          />
          <button
            type="button"
            onClick={onClose}
            className="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-ink"
            aria-label="Close"
          >
            <X className="h-[14px] w-[14px]" strokeWidth={2} />
          </button>
        </div>

        <div className="max-h-[360px] overflow-y-auto p-2">
          {filtered.length === 0 ? (
            <div className="px-3 py-10 text-center text-[13px] text-muted">
              No results for <span className="font-medium text-ink">"{query}"</span>
            </div>
          ) : (
            filtered.map((item, index) => {
              const Icon = item.icon;
              const active = index === activeIndex;

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  onClick={onClose}
                  onMouseEnter={() => setActiveIndex(index)}
                  className={cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13.5px] transition-colors',
                    active ? 'bg-surface-2 text-ink' : 'text-ink-2 hover:bg-surface-2'
                  )}
                >
                  {Icon && (
                    <Icon
                      className={cn('h-[15px] w-[15px] shrink-0', active ? 'text-ink' : 'text-muted')}
                      strokeWidth={2}
                    />
                  )}
                  <span className="flex-1 truncate font-medium">{item.label}</span>
                  {item.group && (
                    <span className="shrink-0 rounded-md bg-surface-2 px-2 py-0.5 text-[10.5px] font-medium uppercase tracking-[0.03em] text-muted">
                      {item.group}
                    </span>
                  )}
                  {active && (
                    <CornerDownLeft className="h-[13px] w-[13px] shrink-0 text-muted" strokeWidth={2} />
                  )}
                </Link>
              );
            })
          )}
        </div>

        <div className="flex items-center justify-between border-t border-border-2 bg-surface px-4 py-2.5 text-[11px] text-muted">
          <div className="flex items-center gap-3">
            <span className="flex items-center gap-1">
              <kbd className="rounded border border-border bg-white px-[6px] py-[2px] font-mono text-[10px] text-muted-2">
                <ArrowUp className="inline h-2.5 w-2.5" />
              </kbd>
              <kbd className="rounded border border-border bg-white px-[6px] py-[2px] font-mono text-[10px] text-muted-2">
                <ArrowDown className="inline h-2.5 w-2.5" />
              </kbd>
              <span className="ml-1">navigate</span>
            </span>
            <span className="flex items-center gap-1">
              <kbd className="rounded border border-border bg-white px-[6px] py-[2px] font-mono text-[10px] text-muted-2">
                ↵
              </kbd>
              <span className="ml-1">open</span>
            </span>
            <span className="flex items-center gap-1">
              <kbd className="rounded border border-border bg-white px-[6px] py-[2px] font-mono text-[10px] text-muted-2">
                esc
              </kbd>
              <span className="ml-1">close</span>
            </span>
          </div>
          <span className="font-mono">{filtered.length} result{filtered.length === 1 ? '' : 's'}</span>
        </div>
      </div>
    </div>
  );
}

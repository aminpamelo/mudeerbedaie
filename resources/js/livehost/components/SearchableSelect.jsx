import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';

const HOST_PALETTE = [
  '#10B981',
  '#0EA5E9',
  '#8B5CF6',
  '#F59E0B',
  '#F43F5E',
  '#14B8A6',
  '#EC4899',
  '#6366F1',
];

export function initialsFrom(name) {
  if (!name) {
    return '?';
  }

  return (
    name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('') || '?'
  );
}

export function colorFor(name) {
  if (!name) {
    return HOST_PALETTE[0];
  }

  let hash = 0;

  for (let i = 0; i < name.length; i += 1) {
    hash = (hash * 31 + name.charCodeAt(i)) | 0;
  }

  return HOST_PALETTE[Math.abs(hash) % HOST_PALETTE.length];
}

export default function SearchableSelect({
  value,
  onChange,
  options = [],
  placeholder = 'Select…',
  searchPlaceholder = 'Search…',
  emptyLabel = 'No matches',
  allowClear = false,
  className,
  disabled = false,
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [activeIdx, setActiveIdx] = useState(0);
  const [coords, setCoords] = useState(null);
  const [portalTarget, setPortalTarget] = useState(null);
  const triggerRef = useRef(null);
  const popoverRef = useRef(null);
  const inputRef = useRef(null);

  const selected = useMemo(
    () => options.find((opt) => String(opt.value) === String(value)) ?? null,
    [options, value]
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();

    if (!q) {
      return options;
    }

    return options.filter((opt) => {
      const haystack = `${opt.label} ${opt.hint ?? ''} ${opt.keywords ?? ''}`.toLowerCase();

      return haystack.includes(q);
    });
  }, [options, query]);

  // Position the popover beneath the trigger, and pick a portal target that
  // keeps it inside any ancestor Radix FocusScope (e.g. Dialog content) so the
  // dialog doesn't steal focus back from our search input.
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const target =
      triggerRef.current?.closest('[data-slot="dialog-content"]') ??
      triggerRef.current?.closest('[role="dialog"]') ??
      document.body;

    setPortalTarget(target);

    const update = () => {
      if (!triggerRef.current) {
        return;
      }

      const rect = triggerRef.current.getBoundingClientRect();
      const spaceBelow = window.innerHeight - rect.bottom;
      const popoverHeight = 320;
      const openUp = spaceBelow < popoverHeight && rect.top > popoverHeight;

      // If our portal target has a transformed ancestor (e.g. Radix
      // DialogContent uses translate to center), `position: fixed` becomes
      // relative to that ancestor. Subtract the target rect so coords stay
      // accurate regardless of where we portal.
      const targetRect =
        target && target !== document.body
          ? target.getBoundingClientRect()
          : { top: 0, left: 0 };

      setCoords({
        top: (openUp ? rect.top - 6 : rect.bottom + 6) - targetRect.top,
        left: rect.left - targetRect.left,
        width: rect.width,
        openUp,
      });
    };

    update();
    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);

    return () => {
      window.removeEventListener('resize', update);
      window.removeEventListener('scroll', update, true);
    };
  }, [open]);

  // Outside click closes
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const handler = (event) => {
      if (triggerRef.current?.contains(event.target)) {
        return;
      }

      if (popoverRef.current?.contains(event.target)) {
        return;
      }

      setOpen(false);
    };

    document.addEventListener('mousedown', handler);

    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  // Focus input & seed active index on open
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    setQuery('');

    const currentIdx = options.findIndex((opt) => String(opt.value) === String(value));

    setActiveIdx(currentIdx >= 0 ? currentIdx : 0);

    const raf = requestAnimationFrame(() => inputRef.current?.focus());

    return () => cancelAnimationFrame(raf);
  }, [open, options, value]);

  useEffect(() => {
    setActiveIdx(0);
  }, [query]);

  // Keyboard navigation
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const handler = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false);

        return;
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActiveIdx((i) => (filtered.length ? (i + 1) % filtered.length : 0));

        return;
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActiveIdx((i) => (filtered.length ? (i - 1 + filtered.length) % filtered.length : 0));

        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        const target = filtered[activeIdx];

        if (target) {
          onChange(target.value);
          setOpen(false);
        }
      }
    };

    window.addEventListener('keydown', handler);

    return () => window.removeEventListener('keydown', handler);
  }, [open, filtered, activeIdx, onChange]);

  const renderAvatar = (opt, size = 'md') => {
    const px = size === 'sm' ? 'h-5 w-5 text-[10px]' : 'h-6 w-6 text-[10px]';

    if (opt.avatar) {
      return (
        <span
          className={cn('grid shrink-0 place-items-center rounded-full font-semibold text-white', px)}
          style={{ background: opt.avatar.color ?? colorFor(opt.label) }}
        >
          {opt.avatar.initials ?? initialsFrom(opt.label)}
        </span>
      );
    }

    if (opt.empty) {
      return (
        <span
          className={cn(
            'grid shrink-0 place-items-center rounded-full border border-dashed border-[#D4D4D4] text-[9px] text-[#A3A3A3]',
            px
          )}
        >
          —
        </span>
      );
    }

    return null;
  };

  return (
    <>
      <button
        type="button"
        ref={triggerRef}
        onClick={() => !disabled && setOpen((current) => !current)}
        disabled={disabled}
        className={cn(
          'flex h-9 w-full items-center justify-between gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 text-left text-sm text-[#0A0A0A] transition-colors',
          'hover:border-[#D4D4D4] focus:border-[#10B981]/60 focus:outline-none focus:ring-2 focus:ring-[#10B981]/20',
          open && 'border-[#10B981]/60 ring-2 ring-[#10B981]/20',
          disabled && 'cursor-not-allowed opacity-60',
          className
        )}
      >
        <span className="flex min-w-0 flex-1 items-center gap-2">
          {selected && renderAvatar(selected, 'sm')}
          <span className={cn('truncate', !selected && 'text-[#A3A3A3]')}>
            {selected ? selected.label : placeholder}
          </span>
        </span>
        <span className="flex shrink-0 items-center gap-1">
          {allowClear && selected && selected.value !== '' && (
            <span
              role="button"
              tabIndex={-1}
              onClick={(event) => {
                event.stopPropagation();
                onChange('');
              }}
              className="grid h-5 w-5 place-items-center rounded-full text-[#A3A3A3] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
            >
              <X className="h-3 w-3" strokeWidth={2.5} />
            </span>
          )}
          <ChevronDown
            className={cn('h-4 w-4 text-[#737373] transition-transform duration-150', open && 'rotate-180')}
            strokeWidth={2}
          />
        </span>
      </button>

      {open && coords && portalTarget && createPortal(
        <div
          ref={popoverRef}
          data-searchable-select-popover
          onPointerDown={(event) => event.stopPropagation()}
          onMouseDown={(event) => event.stopPropagation()}
          className={cn(
            'pointer-events-auto fixed z-[2000] overflow-hidden rounded-xl border border-[#EAEAEA] bg-white shadow-[0_16px_40px_-8px_rgba(0,0,0,0.18)]',
            coords.openUp && '-translate-y-full'
          )}
          style={{ top: coords.top, left: coords.left, width: coords.width }}
        >
          <div className="flex items-center gap-2 border-b border-[#F0F0F0] px-3 py-2">
            <Search className="h-3.5 w-3.5 shrink-0 text-[#A3A3A3]" strokeWidth={2} />
            <input
              ref={inputRef}
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={searchPlaceholder}
              className="flex-1 bg-transparent text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none"
            />
            {query && (
              <button
                type="button"
                onClick={() => setQuery('')}
                className="grid h-5 w-5 place-items-center rounded-full text-[#A3A3A3] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
                aria-label="Clear search"
              >
                <X className="h-3 w-3" strokeWidth={2.5} />
              </button>
            )}
          </div>

          <div className="max-h-56 overflow-y-auto p-1">
            {filtered.length === 0 ? (
              <div className="px-3 py-6 text-center text-[12.5px] text-[#737373]">
                {emptyLabel}
              </div>
            ) : (
              filtered.map((opt, idx) => {
                const active = idx === activeIdx;
                const isSelected = String(opt.value) === String(value);

                return (
                  <button
                    key={`${opt.value}-${idx}`}
                    type="button"
                    onMouseEnter={() => setActiveIdx(idx)}
                    onClick={() => {
                      onChange(opt.value);
                      setOpen(false);
                    }}
                    className={cn(
                      'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-[13px] transition-colors',
                      active ? 'bg-[#F5F5F5] text-[#0A0A0A]' : 'text-[#404040] hover:bg-[#F5F5F5]'
                    )}
                  >
                    {renderAvatar(opt)}
                    <span className="flex min-w-0 flex-1 flex-col">
                      <span className="truncate font-medium leading-tight">{opt.label}</span>
                      {opt.hint && (
                        <span className="truncate text-[11px] text-[#737373]">{opt.hint}</span>
                      )}
                    </span>
                    {isSelected && (
                      <Check className="h-3.5 w-3.5 shrink-0 text-[#10B981]" strokeWidth={2.5} />
                    )}
                  </button>
                );
              })
            )}
          </div>
        </div>,
        portalTarget
      )}
    </>
  );
}

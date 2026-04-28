import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import DateRangePicker from './DateRangePicker';

export default function ReportFilters({ filters, options, basePath }) {
  const apply = (next) => {
    router.get(basePath, { ...filters, ...next }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const selectedHosts = useMemo(
    () => options.hosts.filter((h) => filters.hostIds.includes(h.id)),
    [options.hosts, filters.hostIds]
  );
  const selectedAccounts = useMemo(
    () => options.platformAccounts.filter((a) => filters.platformAccountIds.includes(a.id)),
    [options.platformAccounts, filters.platformAccountIds]
  );

  const hasActiveChips = selectedHosts.length > 0 || selectedAccounts.length > 0;

  return (
    <div className="space-y-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
      <div className="flex flex-wrap items-center gap-3">
        <DateRangePicker
          value={{ dateFrom: filters.dateFrom, dateTo: filters.dateTo }}
          onChange={apply}
        />
        <span className="hidden h-6 w-px bg-[var(--color-border)] sm:inline-block" />
        <MultiSelectPopover
          label="Hosts"
          options={options.hosts}
          selected={filters.hostIds}
          onChange={(ids) => apply({ hostIds: ids })}
        />
        <MultiSelectPopover
          label="Accounts"
          options={options.platformAccounts}
          selected={filters.platformAccountIds}
          onChange={(ids) => apply({ platformAccountIds: ids })}
        />
      </div>

      {hasActiveChips && (
        <div className="flex flex-wrap items-center gap-1.5 border-t border-dashed border-[var(--color-border)] pt-3">
          <span className="label-eyebrow mr-1">Filtered by</span>
          {selectedHosts.map((h) => (
            <button
              type="button"
              key={`h-${h.id}`}
              className="chip"
              onClick={() => apply({ hostIds: filters.hostIds.filter((id) => id !== h.id) })}
            >
              <span>{h.name}</span>
              <span className="chip-x"><X className="size-3" /></span>
            </button>
          ))}
          {selectedAccounts.map((a) => (
            <button
              type="button"
              key={`a-${a.id}`}
              className="chip"
              onClick={() => apply({ platformAccountIds: filters.platformAccountIds.filter((id) => id !== a.id) })}
            >
              <span>{a.name}</span>
              <span className="chip-x"><X className="size-3" /></span>
            </button>
          ))}
          {hasActiveChips && (
            <button
              type="button"
              className="ml-auto text-[11px] text-[var(--color-muted)] hover:text-[var(--color-ink)]"
              onClick={() => apply({ hostIds: [], platformAccountIds: [] })}
            >
              Clear filters
            </button>
          )}
        </div>
      )}
    </div>
  );
}

function MultiSelectPopover({ label, options, selected, onChange }) {
  const triggerRef = useRef(null);
  const popoverRef = useRef(null);
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [pos, setPos] = useState({ top: 0, left: 0, width: 240 });

  const selectedSet = useMemo(() => new Set(selected), [selected]);
  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return options;
    return options.filter((o) => o.name.toLowerCase().includes(q));
  }, [options, search]);

  // Position the popover under the trigger (portal so we escape any overflow)
  useEffect(() => {
    if (!open) return;
    const layout = () => {
      if (!triggerRef.current) return;
      const r = triggerRef.current.getBoundingClientRect();
      setPos({ top: r.bottom + 6, left: r.left, width: Math.max(r.width, 260) });
    };
    layout();
    window.addEventListener('resize', layout);
    window.addEventListener('scroll', layout, true);
    return () => {
      window.removeEventListener('resize', layout);
      window.removeEventListener('scroll', layout, true);
    };
  }, [open]);

  // Click-outside + Esc
  useEffect(() => {
    if (!open) return;
    const onClick = (e) => {
      if (
        triggerRef.current?.contains(e.target) ||
        popoverRef.current?.contains(e.target)
      ) return;
      setOpen(false);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const toggle = (id) => {
    if (selectedSet.has(id)) onChange(selected.filter((x) => x !== id));
    else onChange([...selected, id]);
  };

  const triggerLabel = selected.length === 0
    ? `All ${label.toLowerCase()}`
    : selected.length === 1
      ? options.find((o) => o.id === selected[0])?.name ?? label
      : `${selected.length} selected`;

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex h-9 items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-3 text-sm text-[var(--color-ink)] transition hover:border-[var(--color-muted-2)]"
        data-open={open || undefined}
      >
        <span className="label-eyebrow !text-[10px] !tracking-[0.12em]">{label}</span>
        <span className="text-[var(--color-ink-2)]">{triggerLabel}</span>
        <ChevronDown className={`size-3.5 text-[var(--color-muted)] transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && createPortal(
        <div
          ref={popoverRef}
          className="z-[100] rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg shadow-black/5"
          style={{ position: 'fixed', top: pos.top, left: pos.left, width: pos.width }}
        >
          <div className="flex items-center gap-2 border-b border-[var(--color-border-2)] px-3 py-2">
            <Search className="size-3.5 text-[var(--color-muted)]" />
            <input
              autoFocus
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={`Search ${label.toLowerCase()}…`}
              className="flex-1 bg-transparent text-sm text-[var(--color-ink)] placeholder:text-[var(--color-muted-2)] focus:outline-none"
            />
            {selected.length > 0 && (
              <button
                type="button"
                className="text-[11px] text-[var(--color-muted)] hover:text-[var(--color-ink)]"
                onClick={() => onChange([])}
              >
                Clear
              </button>
            )}
          </div>
          <div className="max-h-72 overflow-y-auto p-1">
            {filtered.length === 0 ? (
              <div className="px-3 py-6 text-center text-xs text-[var(--color-muted)]">
                No matches
              </div>
            ) : (
              filtered.map((o) => {
                const isSel = selectedSet.has(o.id);
                return (
                  <button
                    type="button"
                    key={o.id}
                    onClick={() => toggle(o.id)}
                    className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition ${isSel ? 'bg-[var(--color-surface-2)] text-[var(--color-ink)]' : 'text-[var(--color-ink-2)] hover:bg-[var(--color-surface-2)]'}`}
                  >
                    <span className="truncate">{o.name}</span>
                    {isSel && <Check className="size-3.5 text-[var(--color-emerald-ink)]" />}
                  </button>
                );
              })
            )}
          </div>
          {selected.length > 0 && (
            <div className="border-t border-[var(--color-border-2)] px-3 py-2 text-[11px] text-[var(--color-muted)]">
              {selected.length} of {options.length} selected
            </div>
          )}
        </div>,
        document.body
      )}
    </>
  );
}

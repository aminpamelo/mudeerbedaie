import { Link } from '@inertiajs/react';
import { ChevronRight, ShieldCheck } from 'lucide-react';
import { cn, severityStyles } from '@/ceo/lib/utils';

/**
 * Cross-company, severity-sorted feed of items that need a human — the single
 * place the CEO looks to answer "is anything on fire, and where?".
 */
export default function AttentionFeed({ items = [] }) {
  if (items.length === 0) {
    return (
      <div className="flex items-center gap-3 rounded-[16px] border border-border bg-surface px-6 py-8">
        <div className="grid h-9 w-9 place-items-center rounded-full bg-[var(--color-emerald-soft)]">
          <ShieldCheck className="h-[18px] w-[18px] text-[var(--color-emerald-ink)]" strokeWidth={2} />
        </div>
        <div>
          <div className="text-[14px] font-medium text-ink">Nothing needs attention</div>
          <div className="text-[12px] text-muted">Every department is operating within normal thresholds.</div>
        </div>
      </div>
    );
  }

  return (
    <div className="divide-y divide-border-2 overflow-hidden rounded-[16px] border border-border bg-surface">
      {items.map((item, index) => {
        const styles = severityStyles(item.severity);
        return (
          <Link
            key={`${item.departmentKey}-${index}`}
            href={item.href}
            className="group flex items-center gap-3 px-6 py-3.5 transition-colors hover:bg-surface-2"
          >
            <span className={cn('h-2 w-2 shrink-0 rounded-full', styles.dot)} aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <div className="truncate text-[13.5px] font-medium text-ink">{item.message}</div>
            </div>
            <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-[10.5px] font-medium', styles.soft, styles.text)}>{item.department}</span>
            <ChevronRight className="h-4 w-4 shrink-0 text-muted-2 transition-colors group-hover:text-ink" strokeWidth={2} />
          </Link>
        );
      })}
    </div>
  );
}

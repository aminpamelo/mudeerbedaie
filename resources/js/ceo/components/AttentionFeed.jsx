import { ChevronRight, ShieldCheck, AlertTriangle, AlertCircle, Info } from 'lucide-react';
import { cn } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';

const SEVERITY = {
  critical: { tone: 'critical', Icon: AlertCircle },
  warning: { tone: 'warning', Icon: AlertTriangle },
  info: { tone: 'info', Icon: Info },
};

/**
 * Cross-company, severity-sorted feed of items that need a human — the single
 * place the CEO looks to answer "is anything on fire, and where?".
 */
export default function AttentionFeed({ items = [] }) {
  const t = useT();
  if (items.length === 0) {
    return (
      <div className="glass flex items-center gap-3 rounded-[20px] px-6 py-7">
        <div className="grid h-10 w-10 place-items-center rounded-full bg-[rgba(16,185,129,0.16)]">
          <ShieldCheck className="h-[19px] w-[19px] text-[var(--color-emerald-ink)]" strokeWidth={2} />
        </div>
        <div>
          <div className="text-[14px] font-semibold text-ink">{t('nothing_needs_attention')}</div>
          <div className="text-[12px] text-muted">{t('nothing_needs_attention_sub')}</div>
        </div>
      </div>
    );
  }

  return (
    <div className="glass divide-y divide-[rgba(15,23,42,0.06)] overflow-hidden rounded-[20px]">
      {items.map((item, index) => {
        const { tone, Icon } = SEVERITY[item.severity] ?? SEVERITY.info;
        return (
          <a
            key={`${item.departmentKey}-${index}`}
            href={item.href}
            data-tone={tone}
            className="group flex items-center gap-3.5 px-5 py-3.5 transition-colors hover:bg-white/40"
          >
            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style={{ background: 'var(--tone-soft)' }}>
              <Icon className="h-[15px] w-[15px]" style={{ color: 'var(--tone-ink)' }} strokeWidth={2.2} />
            </span>
            <div className="min-w-0 flex-1">
              <div className="truncate text-[13.5px] font-medium text-ink">{item.message}</div>
            </div>
            <span className="shrink-0 rounded-full px-2.5 py-0.5 text-[10.5px] font-semibold" style={{ background: 'var(--tone-soft)', color: 'var(--tone-ink)' }}>
              {item.department}
            </span>
            <ChevronRight className="h-4 w-4 shrink-0 text-muted-2 transition-colors group-hover:text-ink" strokeWidth={2} />
          </a>
        );
      })}
    </div>
  );
}

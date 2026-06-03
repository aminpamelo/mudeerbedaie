import { cn } from '@/ceo/lib/utils';
import { useT } from '@/ceo/lib/i18n';

/**
 * Compact data table for department detail lists (top hosts, recent orders,
 * pending approvals, etc.). Right-aligns numeric columns and shows an empty
 * state when there are no rows.
 */
export default function DataList({ columns = [], rows = [] }) {
  const t = useT();
  if (rows.length === 0) {
    return <div className="grid h-16 place-items-center rounded-xl bg-[rgba(15,23,42,0.04)] text-[12px] text-muted">{t('no_records_period')}</div>;
  }

  return (
    <div className="overflow-hidden">
      <table className="w-full text-[13px]">
        <thead>
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                className={cn('pb-2 font-mono text-[10px] font-medium uppercase tracking-[0.1em] text-muted-2', col.align === 'right' ? 'text-right' : 'text-left')}
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={i} className="border-t border-[rgba(15,23,42,0.06)]">
              {columns.map((col) => (
                <td
                  key={col.key}
                  className={cn(
                    'py-2.5',
                    col.align === 'right' ? 'text-right font-display tabular-nums text-ink' : 'font-medium text-ink-2',
                  )}
                >
                  {row[col.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

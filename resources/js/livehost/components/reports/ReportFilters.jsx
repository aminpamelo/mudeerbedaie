import { router } from '@inertiajs/react';
import DateRangePicker from './DateRangePicker';

export default function ReportFilters({ filters, options, basePath }) {
  const apply = (next) => {
    router.get(basePath, { ...filters, ...next }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  return (
    <div className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4">
      <DateRangePicker
        value={{ dateFrom: filters.dateFrom, dateTo: filters.dateTo }}
        onChange={apply}
      />
      <MultiSelect
        label="Hosts"
        options={options.hosts}
        selected={filters.hostIds}
        onChange={(ids) => apply({ hostIds: ids })}
      />
      <MultiSelect
        label="Accounts"
        options={options.platformAccounts}
        selected={filters.platformAccountIds}
        onChange={(ids) => apply({ platformAccountIds: ids })}
      />
    </div>
  );
}

function MultiSelect({ label, options, selected, onChange }) {
  // Minimal native-select multi for v1. Replace with Radix Select in v2 if needed.
  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="text-muted-foreground">{label}:</span>
      <select
        multiple
        value={selected.map(String)}
        onChange={(e) => onChange(Array.from(e.target.selectedOptions).map((o) => Number(o.value)))}
        className="h-9 min-w-32 rounded-md border bg-background px-2 text-sm"
      >
        {options.map((o) => (
          <option key={o.id} value={o.id}>{o.name}</option>
        ))}
      </select>
    </label>
  );
}

import { Download } from 'lucide-react';

export default function ExportCsvButton({ exportPath, filters }) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (Array.isArray(value)) value.forEach((v) => params.append(`${key}[]`, v));
    else if (value != null) params.append(key, value);
  });
  const href = `${exportPath}?${params.toString()}`;

  return (
    <a
      href={href}
      download
      className="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-1.5 text-xs font-medium text-[var(--color-ink-2)] transition hover:border-[var(--color-ink)] hover:text-[var(--color-ink)]"
    >
      <Download className="size-3.5" strokeWidth={2.2} />
      <span>Export CSV</span>
    </a>
  );
}

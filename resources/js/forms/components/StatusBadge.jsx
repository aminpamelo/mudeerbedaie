const STYLES = {
  draft: 'bg-slate-100 text-slate-600',
  published: 'bg-emerald-100 text-emerald-700',
  closed: 'bg-rose-100 text-rose-700',
};

const LABELS = {
  draft: 'Draf',
  published: 'Diterbitkan',
  closed: 'Ditutup',
};

export default function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STYLES[status] || STYLES.draft}`}>
      {LABELS[status] || status}
    </span>
  );
}

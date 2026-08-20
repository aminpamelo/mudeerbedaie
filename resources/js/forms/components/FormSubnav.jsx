import { Link } from '@inertiajs/react';
import { BarChart3, Inbox } from 'lucide-react';

/**
 * Tab strip shared by the Submissions and Report pages so a form owner can
 * switch between raw responses and the analytics report.
 */
export default function FormSubnav({ formId, active }) {
  const tabs = [
    { key: 'report', label: 'Report', icon: BarChart3, href: `/forms/${formId}/report` },
    { key: 'submissions', label: 'Jawapan', icon: Inbox, href: `/forms/${formId}/submissions` },
  ];

  return (
    <div className="inline-flex rounded-xl border border-line bg-white p-1 card-soft">
      {tabs.map((tab) => {
        const Icon = tab.icon;
        const on = tab.key === active;
        return (
          <Link
            key={tab.key}
            href={tab.href}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-[13px] font-semibold transition ${
              on ? 'bg-brand text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
            }`}
          >
            <Icon className="h-4 w-4" />
            {tab.label}
          </Link>
        );
      })}
    </div>
  );
}

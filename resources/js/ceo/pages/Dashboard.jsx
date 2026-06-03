import { Head } from '@inertiajs/react';
import CeoLayout from '@/ceo/layouts/CeoLayout';
import PulseStat from '@/ceo/components/PulseStat';
import DepartmentCard from '@/ceo/components/DepartmentCard';
import AttentionFeed from '@/ceo/components/AttentionFeed';
import PeriodSwitcher from '@/ceo/components/PeriodSwitcher';

export default function Dashboard({ period, pulse = [], departments = [], attention = [] }) {
  return (
    <CeoLayout>
      <Head title="Overview" />

      <header
        className="sticky top-0 z-40 flex items-center justify-between border-b border-border-2 px-8 py-5"
        style={{
          background: 'rgba(250,250,250,0.75)',
          backdropFilter: 'saturate(180%) blur(12px)',
          WebkitBackdropFilter: 'saturate(180%) blur(12px)',
        }}
      >
        <div>
          <h1 className="font-display text-[20px] text-ink">Company Overview</h1>
          <p className="text-[12.5px] text-muted">Operational health across every department · {period?.label}</p>
        </div>
        <PeriodSwitcher period={period} />
      </header>

      <div className="flex flex-col gap-8 px-8 py-7">
        {/* Pulse strip */}
        <section className="grid grid-cols-2 divide-x divide-y divide-border-2 overflow-hidden rounded-[16px] border border-border bg-surface sm:grid-cols-3 lg:grid-cols-6 lg:divide-y-0">
          {pulse.map((stat) => (
            <PulseStat key={stat.key} stat={stat} />
          ))}
        </section>

        {/* Department health grid */}
        <section className="flex flex-col gap-3">
          <h2 className="label-eyebrow px-0.5">Departments</h2>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {departments.map((department) => (
              <DepartmentCard key={department.key} department={department} />
            ))}
          </div>
        </section>

        {/* Cross-company attention feed */}
        <section className="flex flex-col gap-3">
          <div className="flex items-center justify-between px-0.5">
            <h2 className="label-eyebrow">Needs attention</h2>
            {attention.length > 0 && (
              <span className="text-[11px] font-medium text-muted tabular-nums">{attention.length} open</span>
            )}
          </div>
          <AttentionFeed items={attention} />
        </section>
      </div>
    </CeoLayout>
  );
}

import { Head } from '@inertiajs/react';
import CeoLayout from '@/ceo/layouts/CeoLayout';
import HealthHero from '@/ceo/components/HealthHero';
import PulseStat from '@/ceo/components/PulseStat';
import DepartmentCard from '@/ceo/components/DepartmentCard';
import AttentionFeed from '@/ceo/components/AttentionFeed';
import PeriodSwitcher from '@/ceo/components/PeriodSwitcher';
import { useT } from '@/ceo/lib/i18n';

export default function Dashboard({ period, health, pulse = [], departments = [], attention = [] }) {
  const t = useT();
  return (
    <CeoLayout>
      <Head title={t('overview')} />

      <header className="flex items-center justify-between px-8 pb-2 pt-6">
        <div>
          <h1 className="font-display text-[22px] text-ink">{t('company_overview')}</h1>
          <p className="text-[12.5px] text-muted">{t('overview_subtitle')} · {period?.label}</p>
        </div>
        <PeriodSwitcher period={period} />
      </header>

      <div className="flex flex-col gap-6 px-8 pb-10">
        <HealthHero health={health} period={period} />

        {/* Pulse strip */}
        <section className="glass grid grid-cols-2 divide-x divide-y divide-[rgba(15,23,42,0.06)] overflow-hidden rounded-[20px] sm:grid-cols-3 lg:grid-cols-6 lg:divide-y-0">
          {pulse.map((stat) => (
            <PulseStat key={stat.key} stat={stat} />
          ))}
        </section>

        {/* Department health grid */}
        <section className="flex flex-col gap-3">
          <h2 className="label-eyebrow px-1">{t('departments')}</h2>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-2">
            {departments.map((department) => (
              <DepartmentCard key={department.key} department={department} />
            ))}
          </div>
        </section>

        {/* Cross-company attention feed */}
        <section className="flex flex-col gap-3">
          <div className="flex items-center justify-between px-1">
            <h2 className="label-eyebrow">{t('needs_attention')}</h2>
            {attention.length > 0 && <span className="text-[11px] font-semibold text-muted tabular-nums">{t('open_count', { count: attention.length })}</span>}
          </div>
          <AttentionFeed items={attention} />
        </section>
      </div>
    </CeoLayout>
  );
}

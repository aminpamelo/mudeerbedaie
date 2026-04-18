import { Head, usePage } from '@inertiajs/react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

export default function Dashboard() {
  const { auth } = usePage().props;
  const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

  return (
    <>
      <Head title="Dashboard" />
      <TopBar breadcrumb={['Live Host Desk', 'Dashboard']} />
      <div className="p-8">
        <h1 className="text-3xl font-semibold tracking-tight text-[#0A0A0A]">
          Good afternoon, {firstName}
        </h1>
        <p className="mt-2 text-[#737373]">
          Dashboard coming online. KPIs and live data arrive in Phase 1.
        </p>
      </div>
    </>
  );
}

Dashboard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

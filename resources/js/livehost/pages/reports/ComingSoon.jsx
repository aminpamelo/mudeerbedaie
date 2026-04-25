import { Head, Link } from '@inertiajs/react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

export default function ComingSoon({ title, href }) {
  return (
    <>
      <Head title={`${title} — Coming soon`} />
      <TopBar breadcrumb={['Live Host Desk', 'Reports', title]} />
      <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 p-8 text-center">
        <h1 className="text-xl font-semibold">{title} report</h1>
        <p className="text-sm text-muted-foreground">This report is on the roadmap.</p>
        <Link href={href} className="text-sm underline">← Back to Reports</Link>
      </div>
    </>
  );
}

ComingSoon.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

import { Head, Link } from '@inertiajs/react';
import { BarChart3, ChevronRight } from 'lucide-react';
import LiveHostLayout from '@/livehost/layouts/LiveHostLayout';

export default function ReportsIndex({ reports }) {
  return (
    <LiveHostLayout>
      <Head title="Reports" />
      <div className="space-y-6 p-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
          <p className="text-sm text-muted-foreground">
            Operational and financial views across the Live Host operation.
          </p>
        </header>
        <div className="grid gap-4 md:grid-cols-2">
          {reports.map((report) => (
            <ReportCard key={report.key} report={report} />
          ))}
        </div>
      </div>
    </LiveHostLayout>
  );
}

function ReportCard({ report }) {
  const className =
    'flex items-start gap-4 rounded-xl border p-5 transition ' +
    (report.available
      ? 'hover:border-foreground/30 hover:bg-muted/50'
      : 'cursor-not-allowed opacity-60');

  const content = (
    <>
      <div className="rounded-lg bg-muted p-2.5">
        <BarChart3 className="size-5" />
      </div>
      <div className="flex-1">
        <div className="flex items-center justify-between">
          <h3 className="font-medium">{report.title}</h3>
          {report.available ? (
            <ChevronRight className="size-4 text-muted-foreground" />
          ) : (
            <span className="text-xs uppercase tracking-wide text-muted-foreground">
              Coming soon
            </span>
          )}
        </div>
        <p className="mt-1 text-sm text-muted-foreground">{report.description}</p>
      </div>
    </>
  );

  return report.available ? (
    <Link href={report.href} className={className}>
      {content}
    </Link>
  ) : (
    <div className={className}>{content}</div>
  );
}

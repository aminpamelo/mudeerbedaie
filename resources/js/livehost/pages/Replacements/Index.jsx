import { Head, usePage } from '@inertiajs/react';
import LiveHostLayout from '@/livehost/layouts/LiveHostLayout';

export default function Index() {
  const { requests, counts } = usePage().props;
  return (
    <>
      <Head title="Replacement Requests" />
      <div className="p-6">
        <h1 className="text-2xl font-semibold mb-4">Replacement Requests</h1>
        <p className="text-sm text-gray-600 mb-6">
          Pending: {counts?.pending ?? 0} · Assigned: {counts?.assigned ?? 0} ·
          Expired: {counts?.expired ?? 0}
        </p>
        <ul className="space-y-2">
          {requests.map((r) => (
            <li key={r.id} className="border rounded p-3">
              <div className="font-medium">
                {r.originalHost.name} — {r.slot.platformAccount}
              </div>
              <div className="text-xs text-gray-500">
                {r.scope} · {r.reasonCategory} · {r.status}
              </div>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}

Index.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

import { Head, usePage } from '@inertiajs/react';
import LiveHostLayout from '@/livehost/layouts/LiveHostLayout';

export default function Show() {
  const { request: req, availableHosts } = usePage().props;
  return (
    <>
      <Head title={`Replacement #${req.id}`} />
      <div className="p-6 max-w-3xl">
        <h1 className="text-2xl font-semibold mb-2">Replacement #{req.id}</h1>
        <p className="text-sm text-gray-600 mb-4">
          {req.originalHost.name} · {req.slot.platformAccount} · {req.scope}
        </p>
        <p className="text-xs text-gray-500 mb-6">
          Prior replacements (90d): {req.originalHost.priorRequests90d}
        </p>
        <h2 className="text-sm font-medium mb-2">Available hosts</h2>
        <ul className="space-y-1">
          {availableHosts.map((h) => (
            <li key={h.id} className="text-sm">
              {h.name}{' '}
              <span className="text-xs text-gray-500">
                ({h.priorReplacementsCount} replacements done)
              </span>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}

Show.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

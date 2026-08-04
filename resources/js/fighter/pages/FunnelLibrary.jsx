import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ExternalLink, Copy, Layers, Library, Loader2 } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import { cn } from '@/fighter/lib/utils';

function LibraryCard({ funnel }) {
  const [copying, setCopying] = useState(false);

  const handleCopy = () => {
    if (copying) return;
    setCopying(true);
    // The controller returns an Inertia location redirect into the funnel
    // builder, so on success the browser navigates away and the button stays
    // in its "Copying…" state until then.
    router.post(`/fighter/funnel-library/${funnel.uuid}/copy`, {}, {
      onError: () => {
        setCopying(false);
        window.alert('Could not copy this funnel. Please try again.');
      },
      onFinish: () => setCopying(false),
    });
  };

  return (
    <div className="fade-up flex flex-col rounded-2xl bg-white p-5 ring-1 ring-line/70">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h3 className="truncate text-[15.5px] font-semibold tracking-[-0.01em] text-ink">{funnel.name}</h3>
          <div className="mt-0.5 text-[11px] font-medium uppercase tracking-[0.04em] text-muted-2">{funnel.type}</div>
        </div>
        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-ink-2">
          <Layers className="h-3 w-3" strokeWidth={2.2} />
          {funnel.steps_count} {funnel.steps_count === 1 ? 'step' : 'steps'}
        </span>
      </div>

      {funnel.description ? (
        <p className="mt-3 line-clamp-3 text-[13px] leading-relaxed text-muted">{funnel.description}</p>
      ) : (
        <p className="mt-3 text-[13px] italic text-muted-2">No description provided.</p>
      )}

      <div className="mt-4 flex items-center justify-between border-t border-line/70 pt-3">
        <a
          href={funnel.public_url}
          target="_blank"
          rel="noreferrer"
          className="flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-2 text-[12.5px] font-semibold text-ink-2 transition-colors hover:bg-slate-200"
          title="Preview this funnel"
        >
          <ExternalLink className="h-3.5 w-3.5" strokeWidth={2.2} />
          <span>Preview</span>
        </a>
        <button
          type="button"
          onClick={handleCopy}
          disabled={copying}
          className={cn(
            'flex items-center gap-1.5 rounded-lg bg-[var(--color-brand)] px-3 py-2 text-[12.5px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)] disabled:opacity-60',
          )}
          title="Copy this funnel into your own"
        >
          {copying ? (
            <>
              <Loader2 className="h-3.5 w-3.5 animate-spin" strokeWidth={2.2} />
              Copying…
            </>
          ) : (
            <>
              <Copy className="h-3.5 w-3.5" strokeWidth={2.2} />
              Copy to my funnels
            </>
          )}
        </button>
      </div>
    </div>
  );
}

function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-16 text-center">
      <div className="grid h-14 w-14 place-items-center rounded-2xl bg-orange-50 text-[var(--color-brand)]">
        <Library className="h-7 w-7" strokeWidth={1.8} />
      </div>
      <h3 className="mt-4 text-[16px] font-semibold text-ink">No funnels available yet</h3>
      <p className="mt-1 max-w-sm text-[13.5px] text-muted">
        When HQ shares a funnel template, it will show up here for you to copy and make your own.
      </p>
    </div>
  );
}

export default function FunnelLibrary({ funnels = [] }) {
  return (
    <FighterLayout title="Funnel Library" subtitle="Copy a ready-made HQ funnel and make it your own.">
      {funnels.length === 0 ? (
        <EmptyState />
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {funnels.map((funnel) => (
            <LibraryCard key={funnel.uuid} funnel={funnel} />
          ))}
        </div>
      )}
    </FighterLayout>
  );
}

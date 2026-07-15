import { useState } from 'react';
import { Plus } from 'lucide-react';
import { cn, createFunnelAndOpen } from '@/fighter/lib/utils';

/**
 * Creates a draft funnel and opens the builder for it directly — no funnel
 * list in between. Shows a brief "Creating…" state while the request is in
 * flight (a successful create navigates away).
 */
export default function CreateFunnelButton({ className, label = 'Create funnel' }) {
  const [creating, setCreating] = useState(false);

  const onClick = async () => {
    if (creating) return;
    setCreating(true);
    try {
      await createFunnelAndOpen();
    } catch {
      setCreating(false);
      window.alert('Could not create a funnel. Please try again.');
    }
  };

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={creating}
      className={cn(
        'flex items-center justify-center gap-2 rounded-xl bg-[var(--color-brand)] px-3 py-2.5 text-[13px] font-semibold text-white shadow-[0_10px_24px_-10px_rgba(249,115,22,0.9)] transition-colors hover:bg-[var(--color-brand-ink)] disabled:opacity-70',
        className
      )}
    >
      <Plus className="h-4 w-4" strokeWidth={2.4} />
      {creating ? 'Creating…' : label}
    </button>
  );
}

import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Loader2, MessageSquare, Trash2 } from 'lucide-react';

/**
 * The list of a host/day's daily comments, grouped by author. Each comment is
 * attributed to the user who wrote it; a trash button appears on comments the
 * viewer may remove (their own, or any comment for an admin — the server sets
 * `can_manage`). Deleting reloads the given Inertia prop and calls onChanged so
 * the surrounding modal can refetch its JSON.
 */
export default function DailyComments({ comments, onChanged, reloadOnly = ['performance'], emptyLabel = null, compact = false, canDelete = true }) {
  const [deletingId, setDeletingId] = useState(null);

  const remove = (c) => {
    setDeletingId(c.id);
    router.delete(`/livehost/mentoring/daily-comment/${c.id}`, {
      preserveScroll: true,
      preserveState: true,
      only: reloadOnly,
      onSuccess: () => onChanged?.(),
      onFinish: () => setDeletingId(null),
    });
  };

  if (!comments || comments.length === 0) {
    return emptyLabel ? <p className="text-[12px] text-[#A3A3A3]">{emptyLabel}</p> : null;
  }

  return (
    <ul className={compact ? 'space-y-1' : 'space-y-1.5'}>
      {comments.map((c) => (
        <li key={c.id} className={`group rounded-lg px-2.5 py-1.5 ${c.is_mine ? 'bg-[#F0FDF4]' : 'bg-[#FAFAFA]'}`}>
          <div className="flex items-center justify-between gap-2">
            <div className="flex min-w-0 items-center gap-1.5">
              <MessageSquare className="h-3 w-3 shrink-0 text-[#10B981]" strokeWidth={2} />
              <span className="truncate text-[11px] font-semibold text-[#0A0A0A]">
                {c.author ?? 'Unknown'}
                {c.is_mine ? <span className="font-normal text-[#059669]"> · you</span> : ''}
              </span>
              {c.updated_at_human && <span className="shrink-0 text-[10px] text-[#A3A3A3]">· {c.updated_at_human}</span>}
            </div>
            {c.can_manage && canDelete && (
              <button
                type="button"
                onClick={() => remove(c)}
                disabled={deletingId === c.id}
                className="shrink-0 rounded p-0.5 text-[#C4C4C4] hover:bg-[#FEF2F2] hover:text-[#B91C1C] disabled:opacity-40"
                title="Delete comment"
                aria-label="Delete comment"
              >
                {deletingId === c.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />}
              </button>
            )}
          </div>
          <p className="mt-0.5 whitespace-pre-wrap text-[12.5px] leading-snug text-[#0A0A0A]">{c.text}</p>
        </li>
      ))}
    </ul>
  );
}

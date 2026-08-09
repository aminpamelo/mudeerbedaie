import { router } from '@inertiajs/react';
import { cn } from '@/student/lib/utils';

export default function Pagination({ links }) {
  if (!links || links.length <= 3) return null;

  return (
    <div className="mt-8 flex flex-wrap items-center justify-center gap-1.5">
      {links.map((link, i) => (
        <button
          key={i}
          disabled={!link.url || link.active}
          onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
          className={cn(
            'min-w-[36px] rounded-xl px-3 py-2 text-[13px] font-semibold transition-all',
            link.active
              ? 'hero-gradient text-white shadow-md shadow-violet-300/40'
              : link.url
                ? 'text-muted hover:bg-violet-50 hover:text-violet-700'
                : 'cursor-default text-muted-2'
          )}
          dangerouslySetInnerHTML={{ __html: link.label }}
        />
      ))}
    </div>
  );
}

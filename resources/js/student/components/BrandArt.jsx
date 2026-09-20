import { cn } from '@/student/lib/utils';

/**
 * BeDaie logo mark + wordmark. Uses the real brand asset.
 */
export function BrandMark({ compact = false, className }) {
  return (
    <div className={cn('flex items-center gap-2.5', className)}>
      <div className="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-brand-soft ring-1 ring-brand-100">
        <img src="/images/bedaie-brand.png" alt="BeDaie" className="h-7 w-7 object-contain" />
      </div>
      {!compact && (
        <div className="min-w-0 leading-tight">
          <div className="text-[17px] font-extrabold tracking-[-0.02em] text-brand-ink">BeDaie</div>
          <div className="text-[11px] font-medium text-muted">Student Portal</div>
        </div>
      )}
    </div>
  );
}

/**
 * Simple domed-mosque silhouette for the sidebar footer.
 */
export function MosqueSilhouette({ className }) {
  return (
    <svg viewBox="0 0 240 120" fill="currentColor" className={className} aria-hidden="true">
      {/* minarets */}
      <rect x="30" y="40" width="7" height="72" rx="2" />
      <path d="M33.5 30c4 0 6 4 6 8h-12c0-4 2-8 6-8z" />
      <circle cx="33.5" cy="28" r="2.4" />
      <rect x="203" y="40" width="7" height="72" rx="2" />
      <path d="M206.5 30c4 0 6 4 6 8h-12c0-4 2-8 6-8z" />
      <circle cx="206.5" cy="28" r="2.4" />
      {/* side domes */}
      <path d="M55 112V78c0-12 9-20 18-20s18 8 18 20v34z" />
      <path d="M149 112V78c0-12 9-20 18-20s18 8 18 20v34z" />
      {/* main dome + body */}
      <path d="M96 112V64c0-3 1-5 3-7 A 27 27 0 0 1 141 57c2 2 3 4 3 7v48z" />
      <path d="M120 22c9 8 15 18 15 27 0 10-7 17-15 17s-15-7-15-17c0-9 6-19 15-27z" />
      <circle cx="120" cy="16" r="3" />
      {/* arch doorway */}
      <path d="M112 112V88a8 8 0 0 1 16 0v24z" fill="#F6F4FC" />
    </svg>
  );
}

/**
 * Decorative diamond divider used under the sidebar tagline.
 */
export function IslamicDivider({ className }) {
  return (
    <div className={cn('flex items-center justify-center gap-2 text-brand/40', className)}>
      <span className="h-px w-10 bg-gradient-to-r from-transparent to-brand/30" />
      <svg viewBox="0 0 16 16" className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth="1.4" aria-hidden="true">
        <path d="M8 1l7 7-7 7-7-7z" />
        <path d="M8 5l3 3-3 3-3-3z" />
      </svg>
      <span className="h-px w-10 bg-gradient-to-l from-transparent to-brand/30" />
    </div>
  );
}

/** Rotating hadith / verse quotes (Bahasa Melayu). */
export const HADITH_QUOTES = [
  {
    text: 'Barang siapa menempuh suatu jalan untuk mencari ilmu, Allah akan mudahkan baginya jalan menuju syurga.',
    source: 'Riwayat Muslim',
  },
  {
    text: 'Menuntut ilmu itu wajib atas setiap orang Islam.',
    source: 'Riwayat Ibnu Majah',
  },
  {
    text: 'Sebaik-baik kamu adalah orang yang mempelajari al-Quran dan mengajarkannya.',
    source: 'Riwayat al-Bukhari',
  },
];

/** Short motivational lines for the footer card. */
export const MOTIVATION_LINES = [
  'Teruskan perjalanan menuntut ilmu, kerana setiap langkah adalah ibadah.',
  'Sedikit ilmu yang diamalkan lebih baik daripada banyak ilmu yang dibiarkan.',
  'Istiqamah dalam belajar, walau perlahan asalkan berterusan.',
];

/** Deterministic pick from a list (no Math.random for SSR safety). */
export function pickDaily(list) {
  const day = new Date().getDate();
  return list[day % list.length];
}

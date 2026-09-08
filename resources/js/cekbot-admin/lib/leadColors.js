// Static class maps so Tailwind v4 JIT can see every class (no dynamic `bg-${x}`).
export const LEAD_COLORS = {
  slate: { dot: 'bg-slate-400', chip: 'bg-slate-500/15 text-slate-300', ring: 'ring-slate-400/40', bar: 'bg-slate-400', soft: 'bg-slate-400/10' },
  blue: { dot: 'bg-blue-400', chip: 'bg-blue-500/15 text-blue-300', ring: 'ring-blue-400/40', bar: 'bg-blue-400', soft: 'bg-blue-400/10' },
  sky: { dot: 'bg-sky-400', chip: 'bg-sky-500/15 text-sky-300', ring: 'ring-sky-400/40', bar: 'bg-sky-400', soft: 'bg-sky-400/10' },
  emerald: { dot: 'bg-emerald-400', chip: 'bg-emerald-500/15 text-emerald-300', ring: 'ring-emerald-400/40', bar: 'bg-emerald-400', soft: 'bg-emerald-400/10' },
  green: { dot: 'bg-green-400', chip: 'bg-green-500/15 text-green-300', ring: 'ring-green-400/40', bar: 'bg-green-400', soft: 'bg-green-400/10' },
  amber: { dot: 'bg-amber-400', chip: 'bg-amber-500/15 text-amber-300', ring: 'ring-amber-400/40', bar: 'bg-amber-400', soft: 'bg-amber-400/10' },
  orange: { dot: 'bg-orange-400', chip: 'bg-orange-500/15 text-orange-300', ring: 'ring-orange-400/40', bar: 'bg-orange-400', soft: 'bg-orange-400/10' },
  red: { dot: 'bg-red-400', chip: 'bg-red-500/15 text-red-300', ring: 'ring-red-400/40', bar: 'bg-red-400', soft: 'bg-red-400/10' },
  rose: { dot: 'bg-rose-400', chip: 'bg-rose-500/15 text-rose-300', ring: 'ring-rose-400/40', bar: 'bg-rose-400', soft: 'bg-rose-400/10' },
  violet: { dot: 'bg-violet-400', chip: 'bg-violet-500/15 text-violet-300', ring: 'ring-violet-400/40', bar: 'bg-violet-400', soft: 'bg-violet-400/10' },
  purple: { dot: 'bg-purple-400', chip: 'bg-purple-500/15 text-purple-300', ring: 'ring-purple-400/40', bar: 'bg-purple-400', soft: 'bg-purple-400/10' },
  pink: { dot: 'bg-pink-400', chip: 'bg-pink-500/15 text-pink-300', ring: 'ring-pink-400/40', bar: 'bg-pink-400', soft: 'bg-pink-400/10' },
};

export function leadColor(token) {
  return LEAD_COLORS[token] ?? LEAD_COLORS.slate;
}

// Deterministic avatar tint from a name/phone string.
const AVATAR_TINTS = ['bg-blue-500/20 text-blue-200', 'bg-emerald-500/20 text-emerald-200', 'bg-amber-500/20 text-amber-200', 'bg-violet-500/20 text-violet-200', 'bg-rose-500/20 text-rose-200', 'bg-sky-500/20 text-sky-200', 'bg-orange-500/20 text-orange-200', 'bg-pink-500/20 text-pink-200'];

export function avatarTint(seed) {
  const s = String(seed || '');
  let hash = 0;
  for (let i = 0; i < s.length; i += 1) hash = (hash * 31 + s.charCodeAt(i)) & 0xffffffff;
  return AVATAR_TINTS[Math.abs(hash) % AVATAR_TINTS.length];
}

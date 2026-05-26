import { cn } from '../../lib/utils';

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

/**
 * ProfileHero — identity card with gradient avatar ring.
 * Used on MyProfile, MyProfileOverview, MyPersonalInfo, etc.
 *
 * <ProfileHero
 *   name="Vickie Buckridge"
 *   subtitle="Senior Logistics Officer"
 *   photoUrl={user.photo_url}
 *   chips={[
 *     { label: 'Joined 3y ago' },
 *     { label: 'Active', accent: 'emerald' },
 *     { label: 'BDE-0001', mono: true },
 *   ]}
 *   action={<Button>Edit</Button>}
 * />
 */
export function ProfileHero({ name, subtitle, photoUrl, chips = [], action, className }) {
    return (
        <div className={cn(
            'relative overflow-hidden rounded-3xl border border-pink-200/60 bg-gradient-to-br from-amber-50 via-rose-50 to-indigo-100 p-5 shadow-md shadow-rose-200/20',
            className
        )}>
            <div className="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-orange-300/30 blur-3xl hr-float" aria-hidden />
            <div className="absolute -left-12 -bottom-16 h-36 w-36 rounded-full bg-indigo-300/30 blur-3xl hr-float-delayed" aria-hidden />

            <div className="relative flex items-center gap-4">
                {/* Avatar with gradient ring */}
                <div className="relative shrink-0">
                    <div className="rounded-full bg-gradient-to-br from-indigo-500 via-pink-500 to-orange-400 p-[3px] shadow-lg shadow-pink-500/30">
                        <div className="h-16 w-16 overflow-hidden rounded-full bg-white p-[2px]">
                            {photoUrl ? (
                                <img
                                    src={photoUrl}
                                    alt={name}
                                    className="h-full w-full rounded-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center rounded-full bg-gradient-to-br from-indigo-100 to-pink-100 text-base font-bold text-indigo-700">
                                    {getInitials(name)}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Name + subtitle */}
                <div className="min-w-0 flex-1">
                    <h1 className="truncate text-xl font-bold tracking-tight text-slate-900">{name}</h1>
                    {subtitle && (
                        <p className="mt-0.5 truncate text-xs font-medium text-slate-600">{subtitle}</p>
                    )}
                </div>

                {action}
            </div>

            {/* Chips row */}
            {chips.length > 0 && (
                <div className="relative mt-4 flex flex-wrap gap-1.5">
                    {chips.map((chip, i) => (
                        <Chip key={i} {...chip} />
                    ))}
                </div>
            )}
        </div>
    );
}

function Chip({ label, accent, mono }) {
    const colors = {
        emerald: 'bg-emerald-100 text-emerald-800',
        amber: 'bg-amber-100 text-amber-800',
        rose: 'bg-rose-100 text-rose-800',
        violet: 'bg-violet-100 text-violet-800',
        indigo: 'bg-indigo-100 text-indigo-800',
        sky: 'bg-sky-100 text-sky-800',
        slate: 'bg-white/80 text-slate-700 ring-1 ring-slate-200',
    };
    const c = colors[accent] || colors.slate;
    return (
        <span className={cn(
            'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold backdrop-blur-sm',
            mono && 'font-mono tabular-nums',
            c
        )}>
            {label}
        </span>
    );
}

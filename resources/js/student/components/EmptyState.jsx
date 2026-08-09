import { cn } from '@/student/lib/utils';
import { PackageOpen } from 'lucide-react';

export default function EmptyState({ icon: Icon = PackageOpen, title, description, action, className }) {
  return (
    <div className={cn('fade-up rounded-2xl bg-white py-16 text-center shadow-sm ring-1 ring-black/[0.04]', className)}>
      <div className="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-3xl bg-gradient-to-br from-violet-100 to-rose-100">
        <Icon className="h-9 w-9 text-violet-400" strokeWidth={1.5} />
      </div>
      <h3 className="text-[18px] font-bold text-ink">{title}</h3>
      {description && (
        <p className="mx-auto mt-2 max-w-sm text-[14px] leading-relaxed text-muted">{description}</p>
      )}
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

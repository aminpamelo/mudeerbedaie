import { cn } from '@/student/lib/utils';

const variants = {
  success:    'bg-emerald-100 text-emerald-700 ring-emerald-200/60',
  danger:     'bg-red-100 text-red-700 ring-red-200/60',
  warning:    'bg-amber-100 text-amber-700 ring-amber-200/60',
  info:       'bg-blue-100 text-blue-700 ring-blue-200/60',
  purple:     'bg-violet-100 text-violet-700 ring-violet-200/60',
  gray:       'bg-gray-100 text-gray-600 ring-gray-200/60',
};

export default function StatusBadge({ variant = 'gray', children, className, size = 'sm' }) {
  return (
    <span className={cn(
      'inline-flex items-center gap-1 rounded-full font-semibold ring-1',
      size === 'sm' ? 'px-2.5 py-0.5 text-[11px]' : 'px-3 py-1 text-[12px]',
      variants[variant] ?? variants.gray,
      className,
    )}>
      {children}
    </span>
  );
}

/** Map common order/payment status strings to a badge variant. */
export function statusVariant(status) {
  const map = {
    paid: 'success', succeeded: 'success', completed: 'success', active: 'success',
    failed: 'danger', cancelled: 'danger', canceled: 'danger', refunded: 'purple',
    pending: 'warning', processing: 'info',
    requires_action: 'warning', requires_payment_method: 'warning',
    partially_refunded: 'purple',
  };
  return map[status] ?? 'gray';
}

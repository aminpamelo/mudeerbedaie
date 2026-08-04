import { Clock, PackageOpen, CheckCircle2, XCircle } from 'lucide-react';

/**
 * The four statuses a fighter owns, in lifecycle order. Each carries the plain
 * sentence shown under the picker so a fighter never has to guess what a status
 * means. Mirrors App\Support\FighterOrderStatus::EDITABLE — keep both in sync.
 */
export const FIGHTER_STATUSES = [
  {
    key: 'pending',
    label: 'Pending',
    Icon: Clock,
    hint: 'New order. Nothing has been prepared yet.',
    active: 'bg-amber-500 text-white',
  },
  {
    key: 'processing',
    label: 'Processing',
    Icon: PackageOpen,
    hint: 'You are preparing this order — packing, arranging delivery.',
    active: 'bg-violet-600 text-white',
  },
  {
    key: 'completed',
    label: 'Completed',
    Icon: CheckCircle2,
    hint: 'Done. The customer has received the order.',
    active: 'bg-emerald-600 text-white',
  },
  {
    key: 'cancelled',
    label: 'Cancelled',
    Icon: XCircle,
    hint: 'This order will not proceed and is dropped from your sales.',
    active: 'bg-rose-600 text-white',
  },
];

export const FIGHTER_STATUS_KEYS = FIGHTER_STATUSES.map((s) => s.key);

/** Badge palette for every order status, including the team-owned ones. */
export const STATUS_STYLES = {
  pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  confirmed: 'bg-blue-50 text-blue-700 ring-blue-600/20',
  processing: 'bg-violet-50 text-violet-700 ring-violet-600/20',
  partially_shipped: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  shipped: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  delivered: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  completed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  cancelled: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  refunded: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  returned: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  draft: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

export const PAYMENT_STYLES = {
  paid: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  failed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  refunded: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

export function statusLabel(value) {
  return String(value || '—').replace(/_/g, ' ');
}

/**
 * Cancelling an order whose money already came in also reverses the payment, so
 * the fighter is warned before it happens (matches the server behaviour in
 * App\Support\FighterOrderStatus::apply).
 */
export function cancelRefundsPayment(order) {
  return order?.payment_status === 'paid';
}

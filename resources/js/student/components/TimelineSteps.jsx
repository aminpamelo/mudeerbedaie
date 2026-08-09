import { cn } from '@/student/lib/utils';
import { Check, Clock, Inbox, RefreshCw, CheckCircle, XCircle } from 'lucide-react';

const defaultSteps = [
  { key: 'pending_review', label: 'Pending Review', icon: Clock },
  { key: 'approved_pending_return', label: 'Approved', icon: Check },
  { key: 'item_received', label: 'Item Received', icon: Inbox },
  { key: 'refund_processing', label: 'Processing', icon: RefreshCw },
  { key: 'refund_completed', label: 'Completed', icon: CheckCircle },
];

export default function TimelineSteps({ currentStatus, steps = defaultSteps, isRejected, isCancelled }) {
  if (isRejected) {
    return (
      <div className="flex items-center gap-3 rounded-xl bg-red-50 p-4">
        <XCircle className="h-8 w-8 shrink-0 text-red-500" strokeWidth={1.8} />
        <div>
          <p className="text-[14px] font-semibold text-red-800">Request Rejected</p>
        </div>
      </div>
    );
  }

  if (isCancelled) {
    return (
      <div className="flex items-center gap-3 rounded-xl bg-gray-100 p-4">
        <XCircle className="h-8 w-8 shrink-0 text-gray-500" strokeWidth={1.8} />
        <div>
          <p className="text-[14px] font-semibold text-gray-700">Request Cancelled</p>
        </div>
      </div>
    );
  }

  const currentIndex = steps.findIndex((s) => s.key === currentStatus);

  return (
    <div className="flex items-center justify-between">
      {steps.map((step, i) => {
        const Icon = step.icon;
        const done = i <= currentIndex;
        return (
          <div key={step.key} className="flex flex-1 items-center">
            <div className="flex flex-col items-center">
              <div className={cn(
                'grid h-10 w-10 place-items-center rounded-full transition-colors',
                done ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-400'
              )}>
                <Icon className="h-5 w-5" strokeWidth={2} />
              </div>
              <span className={cn(
                'mt-1.5 text-center text-[10px] font-medium leading-tight',
                done ? 'text-emerald-600' : 'text-muted'
              )}>
                {step.label}
              </span>
            </div>
            {i < steps.length - 1 && (
              <div className={cn('mx-1 h-1 flex-1 rounded-full', i < currentIndex ? 'bg-emerald-500' : 'bg-gray-200')} />
            )}
          </div>
        );
      })}
    </div>
  );
}

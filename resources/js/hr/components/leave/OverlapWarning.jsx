import { AlertTriangle } from 'lucide-react';

export default function OverlapWarning({ count = 0, date }) {
    if (count <= 0) return null;

    return (
        <div className="flex items-center gap-2 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3">
            <AlertTriangle className="h-5 w-5 shrink-0 text-yellow-600" />
            <p className="text-sm text-yellow-800">
                {count} other(s) from your department on leave on {date}
            </p>
        </div>
    );
}

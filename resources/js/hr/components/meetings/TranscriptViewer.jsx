import { useState } from 'react';
import { ChevronDown, FileText } from 'lucide-react';
import { cn } from '../../lib/utils';
import { Button } from '../ui/button';

export default function TranscriptViewer({ transcript }) {
    const [expanded, setExpanded] = useState(false);

    if (!transcript || (!transcript.content && !transcript.text)) {
        return null;
    }

    const text = transcript.content || transcript.text || '';

    return (
        <div className="rounded-lg border border-zinc-200">
            <button
                className="flex w-full items-center justify-between px-3 py-2"
                onClick={() => setExpanded(!expanded)}
            >
                <div className="flex items-center gap-2">
                    <FileText className="h-4 w-4 text-zinc-400" />
                    <span className="text-sm font-medium text-zinc-900">Transcript</span>
                </div>
                <ChevronDown
                    className={cn(
                        'h-4 w-4 text-zinc-400 transition-transform',
                        expanded && 'rotate-180'
                    )}
                />
            </button>
            {expanded && (
                <div className="border-t border-zinc-100 px-3 py-3">
                    <p className="max-h-96 overflow-y-auto whitespace-pre-wrap text-sm text-zinc-600">
                        {text}
                    </p>
                </div>
            )}
        </div>
    );
}

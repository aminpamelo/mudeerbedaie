import { useState } from 'react';
import { ChevronDown, FileText, Loader2, AlertCircle } from 'lucide-react';
import { cn } from '../../lib/utils';

export default function TranscriptViewer({ transcript }) {
    const [expanded, setExpanded] = useState(true);

    if (!transcript) return null;

    const status = transcript.status;
    const text = transcript.content || transcript.text || '';

    if (status === 'processing') {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <Loader2 className="h-4 w-4 animate-spin text-slate-500" />
                <span className="text-sm text-slate-700">Transcribing audio… this usually takes 30–90 seconds.</span>
            </div>
        );
    }

    if (status === 'failed') {
        return (
            <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2">
                <AlertCircle className="mt-0.5 h-4 w-4 text-red-500" />
                <div className="text-sm">
                    <p className="font-medium text-red-700">Transcription failed</p>
                    {transcript.error_message && (
                        <p className="mt-0.5 text-red-600">{transcript.error_message}</p>
                    )}
                </div>
            </div>
        );
    }

    if (!text) return null;

    return (
        <div className="rounded-lg border border-slate-200">
            <button
                className="flex w-full items-center justify-between px-3 py-2"
                onClick={() => setExpanded(!expanded)}
            >
                <div className="flex items-center gap-2">
                    <FileText className="h-4 w-4 text-slate-400" />
                    <span className="text-sm font-medium text-slate-900">Transcript</span>
                    {transcript.language && (
                        <span className="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 uppercase">
                            {transcript.language}
                        </span>
                    )}
                </div>
                <ChevronDown
                    className={cn(
                        'h-4 w-4 text-slate-400 transition-transform',
                        expanded && 'rotate-180'
                    )}
                />
            </button>
            {expanded && (
                <div className="border-t border-slate-100 px-3 py-3">
                    <p className="max-h-96 overflow-y-auto whitespace-pre-wrap text-sm text-slate-600">
                        {text}
                    </p>
                </div>
            )}
        </div>
    );
}

import { Mic, FileAudio, Trash2, FileText } from 'lucide-react';
import { Button } from '../ui/button';

export default function RecordingPlayer({ recording, onTranscribe, onDelete, transcribing }) {
    const fileName = recording.original_name || recording.file_name || 'Recording';
    const fileSize = recording.file_size
        ? `${(recording.file_size / (1024 * 1024)).toFixed(1)} MB`
        : '';

    return (
        <div className="rounded-lg border border-zinc-200 p-3">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <FileAudio className="h-5 w-5 text-zinc-400" />
                    <div>
                        <p className="text-sm font-medium text-zinc-900">{fileName}</p>
                        {fileSize && <p className="text-xs text-zinc-500">{fileSize}</p>}
                    </div>
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onTranscribe}
                        disabled={transcribing}
                    >
                        <FileText className="mr-1 h-3.5 w-3.5" />
                        {transcribing ? 'Transcribing...' : 'Transcribe'}
                    </Button>
                    <Button variant="ghost" size="icon" onClick={onDelete}>
                        <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                </div>
            </div>
            {recording.url && (
                <audio src={recording.url} controls className="mt-2 w-full" />
            )}
        </div>
    );
}

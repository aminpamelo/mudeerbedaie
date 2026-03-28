import { useState, useRef, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
    ArrowLeft,
    Mic,
    Square,
    Pause,
    Play,
    Upload,
    Loader2,
} from 'lucide-react';
import { uploadRecording, triggerTranscription } from '../../lib/api';
import PageHeader from '../../components/PageHeader';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';

export default function MeetingRecord() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [recording, setRecording] = useState(false);
    const [paused, setPaused] = useState(false);
    const [audioUrl, setAudioUrl] = useState(null);
    const [audioBlob, setAudioBlob] = useState(null);
    const [elapsed, setElapsed] = useState(0);
    const [uploadFile, setUploadFile] = useState(null);

    const mediaRecorder = useRef(null);
    const chunks = useRef([]);
    const timerRef = useRef(null);

    useEffect(() => {
        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
            if (mediaRecorder.current && mediaRecorder.current.state !== 'inactive') {
                mediaRecorder.current.stop();
            }
        };
    }, []);

    async function startRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const recorder = new MediaRecorder(stream);
            chunks.current = [];

            recorder.ondataavailable = (e) => {
                if (e.data.size > 0) chunks.current.push(e.data);
            };

            recorder.onstop = () => {
                const blob = new Blob(chunks.current, { type: 'audio/webm' });
                setAudioBlob(blob);
                setAudioUrl(URL.createObjectURL(blob));
                stream.getTracks().forEach((t) => t.stop());
            };

            mediaRecorder.current = recorder;
            recorder.start();
            setRecording(true);
            setPaused(false);
            setElapsed(0);
            setAudioUrl(null);
            setAudioBlob(null);

            timerRef.current = setInterval(() => {
                setElapsed((e) => e + 1);
            }, 1000);
        } catch {
            alert('Unable to access microphone. Please allow microphone access.');
        }
    }

    function togglePause() {
        if (!mediaRecorder.current) return;
        if (paused) {
            mediaRecorder.current.resume();
            timerRef.current = setInterval(() => {
                setElapsed((e) => e + 1);
            }, 1000);
        } else {
            mediaRecorder.current.pause();
            clearInterval(timerRef.current);
        }
        setPaused(!paused);
    }

    function stopRecording() {
        if (!mediaRecorder.current) return;
        mediaRecorder.current.stop();
        setRecording(false);
        setPaused(false);
        clearInterval(timerRef.current);
    }

    const uploadMut = useMutation({
        mutationFn: (formData) => uploadRecording(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id] });
            navigate(`/meetings/${id}`);
        },
    });

    const uploadFileMut = useMutation({
        mutationFn: (formData) => uploadRecording(id, formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr', 'meeting', id] });
            navigate(`/meetings/${id}`);
        },
    });

    function handleUploadBlob() {
        if (!audioBlob) return;
        const fd = new FormData();
        fd.append('file', audioBlob, 'recording.webm');
        uploadMut.mutate(fd);
    }

    function handleUploadFile() {
        if (!uploadFile) return;
        const fd = new FormData();
        fd.append('file', uploadFile);
        uploadFileMut.mutate(fd);
    }

    function formatTimer(seconds) {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    return (
        <div>
            <PageHeader
                title="Record Meeting"
                description="Record audio directly from your browser or upload a file."
                action={
                    <Button variant="outline" onClick={() => navigate(`/meetings/${id}`)}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        Back to Meeting
                    </Button>
                }
            />

            <div className="space-y-6">
                {/* Browser Recording */}
                <Card>
                    <CardHeader>
                        <CardTitle>Browser Recording</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col items-center gap-6 py-8">
                            {/* Timer */}
                            <div className="text-center">
                                {recording && (
                                    <div className="mb-2 flex items-center justify-center gap-2">
                                        <span className="h-3 w-3 animate-pulse rounded-full bg-red-500" />
                                        <span className="text-sm font-medium text-red-600">
                                            {paused ? 'Paused' : 'Recording'}
                                        </span>
                                    </div>
                                )}
                                <p className="font-mono text-4xl font-bold text-zinc-900">
                                    {formatTimer(elapsed)}
                                </p>
                            </div>

                            {/* Controls */}
                            <div className="flex items-center gap-3">
                                {!recording && !audioUrl && (
                                    <Button onClick={startRecording} size="lg">
                                        <Mic className="mr-2 h-5 w-5" />
                                        Start Recording
                                    </Button>
                                )}
                                {recording && (
                                    <>
                                        <Button variant="outline" onClick={togglePause}>
                                            {paused ? (
                                                <>
                                                    <Play className="mr-1.5 h-4 w-4" />
                                                    Resume
                                                </>
                                            ) : (
                                                <>
                                                    <Pause className="mr-1.5 h-4 w-4" />
                                                    Pause
                                                </>
                                            )}
                                        </Button>
                                        <Button variant="destructive" onClick={stopRecording}>
                                            <Square className="mr-1.5 h-4 w-4" />
                                            Stop
                                        </Button>
                                    </>
                                )}
                            </div>

                            {/* Preview */}
                            {audioUrl && (
                                <div className="w-full max-w-md space-y-4">
                                    <audio src={audioUrl} controls className="w-full" />
                                    <div className="flex justify-center gap-3">
                                        <Button variant="outline" onClick={startRecording}>
                                            Re-record
                                        </Button>
                                        <Button
                                            onClick={handleUploadBlob}
                                            disabled={uploadMut.isPending}
                                        >
                                            {uploadMut.isPending ? (
                                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                            ) : (
                                                <Upload className="mr-1.5 h-4 w-4" />
                                            )}
                                            Upload & Save
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* File Upload */}
                <Card>
                    <CardHeader>
                        <CardTitle>Upload Existing Recording</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-3">
                            <input
                                type="file"
                                accept="audio/*,video/*"
                                onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
                                className="text-sm"
                            />
                            <Button
                                onClick={handleUploadFile}
                                disabled={!uploadFile || uploadFileMut.isPending}
                            >
                                {uploadFileMut.isPending ? (
                                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                ) : (
                                    <Upload className="mr-1.5 h-4 w-4" />
                                )}
                                Upload
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

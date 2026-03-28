import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Clock,
    Camera,
    MapPin,
    Wifi,
    WifiOff,
    Sun,
    Moon,
    Sunset,
    Home,
    Building2,
    CheckCircle2,
    Loader2,
    AlertCircle,
    ChevronRight,
} from 'lucide-react';
import { clockIn, clockOut, fetchMyTodayAttendance, fetchMyAttendanceSummary } from '../lib/api';
import { cn } from '../lib/utils';
import { Card, CardContent } from '../components/ui/card';
import { Button } from '../components/ui/button';
import ConfirmDialog from '../components/ConfirmDialog';

// ---- Helpers ----
function getGreeting() {
    const hour = new Date().getHours();
    if (hour < 12) return { text: 'Good Morning', icon: Sun };
    if (hour < 17) return { text: 'Good Afternoon', icon: Sunset };
    return { text: 'Good Evening', icon: Moon };
}

function formatTime(dateStr) {
    if (!dateStr) return '--:--';
    return new Date(dateStr).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes) {
    if (!minutes && minutes !== 0) return '--:--';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h ${m}m`;
}

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
const DAY_COLORS = {
    present: 'bg-emerald-500',
    late: 'bg-amber-500',
    absent: 'bg-red-500',
    wfh: 'bg-blue-500',
    leave: 'bg-purple-500',
    holiday: 'bg-zinc-300',
    none: 'bg-zinc-200',
};

// ---- Live Clock ----
function useLiveClock() {
    const [now, setNow] = useState(new Date());
    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(id);
    }, []);
    return now;
}

// ---- Camera Preview ----
function CameraPreview({ onCapture, isCapturing }) {
    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const [hasCamera, setHasCamera] = useState(true);
    const [cameraReady, setCameraReady] = useState(false);

    useEffect(() => {
        let cancelled = false;
        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: 320, height: 240 },
                });
                if (cancelled) {
                    stream.getTracks().forEach((t) => t.stop());
                    return;
                }
                streamRef.current = stream;
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    setCameraReady(true);
                }
            } catch {
                setHasCamera(false);
            }
        }
        startCamera();
        return () => {
            cancelled = true;
            streamRef.current?.getTracks().forEach((t) => t.stop());
        };
    }, []);

    const capture = useCallback(() => {
        if (!videoRef.current || !cameraReady) return null;
        const canvas = document.createElement('canvas');
        canvas.width = videoRef.current.videoWidth || 320;
        canvas.height = videoRef.current.videoHeight || 240;
        canvas.getContext('2d').drawImage(videoRef.current, 0, 0);
        return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.7));
    }, [cameraReady]);

    useEffect(() => {
        if (onCapture) {
            onCapture(capture);
        }
    }, [capture, onCapture]);

    if (!hasCamera) {
        return (
            <div className="flex flex-col items-center justify-center rounded-2xl bg-zinc-100 p-6 text-center">
                <Camera className="h-8 w-8 text-zinc-400 mb-2" />
                <p className="text-xs text-zinc-500">Camera not available</p>
            </div>
        );
    }

    return (
        <div className="relative overflow-hidden rounded-2xl bg-black">
            <video
                ref={videoRef}
                autoPlay
                playsInline
                muted
                className="h-48 w-full object-cover"
            />
            {!cameraReady && (
                <div className="absolute inset-0 flex items-center justify-center bg-zinc-900">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-500" />
                </div>
            )}
            {isCapturing && (
                <div className="absolute inset-0 bg-white/80 flex items-center justify-center">
                    <Loader2 className="h-6 w-6 animate-spin text-zinc-700" />
                </div>
            )}
        </div>
    );
}

// ---- Clock Button ----
function ClockButton({ type, isPending, onClick }) {
    const isClockIn = type === 'in';
    return (
        <button
            onClick={onClick}
            disabled={isPending}
            className={cn(
                'relative h-28 w-28 rounded-full shadow-lg transition-all active:scale-95 disabled:opacity-60',
                isClockIn
                    ? 'bg-emerald-500 hover:bg-emerald-600 text-white'
                    : 'bg-red-500 hover:bg-red-600 text-white'
            )}
        >
            {isPending ? (
                <Loader2 className="h-8 w-8 animate-spin mx-auto" />
            ) : (
                <div className="flex flex-col items-center gap-1">
                    <Clock className="h-7 w-7" />
                    <span className="text-sm font-semibold">
                        {isClockIn ? 'Clock In' : 'Clock Out'}
                    </span>
                </div>
            )}
        </button>
    );
}

// ========== MAIN COMPONENT ==========
export default function ClockInOut() {
    const queryClient = useQueryClient();
    const now = useLiveClock();
    const [isWfh, setIsWfh] = useState(false);
    const [captureRef, setCaptureRef] = useState(null);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [showConfirm, setShowConfirm] = useState(null); // 'in' | 'out' | null

    const user = window.hrConfig?.user || { name: 'User' };
    const greeting = getGreeting();
    const GreetingIcon = greeting.icon;

    const { data: todayData, isLoading: loadingToday } = useQuery({
        queryKey: ['my-today-attendance'],
        queryFn: fetchMyTodayAttendance,
        refetchInterval: 30000,
    });
    const today = todayData?.data;

    const { data: summaryData } = useQuery({
        queryKey: ['my-attendance-summary', 'week'],
        queryFn: () => fetchMyAttendanceSummary({ period: 'week' }),
    });
    const weekSummary = summaryData?.data?.days ?? [];

    const isClockedIn = today?.clock_in && !today?.clock_out;
    const isCompleted = today?.clock_in && today?.clock_out;

    const clockInMut = useMutation({
        mutationFn: async () => {
            const formData = new FormData();
            formData.append('is_wfh', isWfh ? '1' : '0');
            if (captureRef) {
                const blob = await captureRef();
                if (blob) {
                    formData.append('photo', blob, 'clock-in.jpg');
                }
            }
            return clockIn(formData);
        },
        onSuccess: () => {
            setSuccess('Clocked in successfully!');
            setError(null);
            queryClient.invalidateQueries({ queryKey: ['my-today-attendance'] });
            queryClient.invalidateQueries({ queryKey: ['my-attendance-summary'] });
            setTimeout(() => setSuccess(null), 3000);
        },
        onError: (err) => {
            setError(err?.response?.data?.message || 'Failed to clock in');
            setSuccess(null);
        },
    });

    const clockOutMut = useMutation({
        mutationFn: async () => {
            const formData = new FormData();
            try {
                if (captureRef) {
                    const blob = await captureRef();
                    if (blob) {
                        formData.append('photo', blob, 'clock-out.jpg');
                    }
                }
            } catch {
                // Camera capture failed, proceed without photo
            }
            return clockOut(formData);
        },
        onSuccess: () => {
            setSuccess('Clocked out successfully!');
            setError(null);
            queryClient.invalidateQueries({ queryKey: ['my-today-attendance'] });
            queryClient.invalidateQueries({ queryKey: ['my-attendance-summary'] });
            setTimeout(() => setSuccess(null), 3000);
        },
        onError: (err) => {
            const msg = err?.response?.data?.message
                || err?.response?.data?.errors?.photo?.[0]
                || err?.message
                || 'Failed to clock out';
            setError(msg);
            setSuccess(null);
        },
    });

    const isPending = clockInMut.isPending || clockOutMut.isPending;

    return (
        <div className="space-y-4 max-w-md mx-auto">
            {/* Greeting */}
            <div className="text-center pt-2">
                <div className="flex items-center justify-center gap-2 mb-1">
                    <GreetingIcon className="h-5 w-5 text-amber-500" />
                    <h1 className="text-lg font-semibold text-zinc-900">
                        {greeting.text}, {user.name?.split(' ')[0]}
                    </h1>
                </div>
                <p className="text-3xl font-bold text-zinc-900 tabular-nums">
                    {now.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </p>
                <p className="text-sm text-zinc-500 mt-0.5">
                    {now.toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                </p>
            </div>

            {/* Status Indicator */}
            <Card>
                <CardContent className="py-3">
                    <div className="flex items-center justify-center gap-2">
                        <div className={cn(
                            'h-2.5 w-2.5 rounded-full',
                            isCompleted ? 'bg-emerald-500' : isClockedIn ? 'bg-amber-500 animate-pulse' : 'bg-zinc-300'
                        )} />
                        <span className="text-sm font-medium text-zinc-700">
                            {loadingToday ? 'Loading...' :
                             isCompleted ? 'Completed for today' :
                             isClockedIn ? `Clocked in at ${formatTime(today.clock_in)}` :
                             'Not clocked in'}
                        </span>
                    </div>
                </CardContent>
            </Card>

            {/* Camera Preview */}
            <CameraPreview onCapture={setCaptureRef} isCapturing={isPending} />

            {/* WFH Toggle */}
            {!isClockedIn && !isCompleted && (
                <div className="flex items-center justify-center gap-3">
                    <button
                        onClick={() => setIsWfh(false)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-colors',
                            !isWfh ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600'
                        )}
                    >
                        <Building2 className="h-4 w-4" /> Office
                    </button>
                    <button
                        onClick={() => setIsWfh(true)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-colors',
                            isWfh ? 'bg-blue-600 text-white' : 'bg-zinc-100 text-zinc-600'
                        )}
                    >
                        <Home className="h-4 w-4" /> WFH
                    </button>
                </div>
            )}

            {/* Clock Button */}
            <div className="flex justify-center py-2">
                {isCompleted ? (
                    <div className="flex flex-col items-center gap-2">
                        <div className="h-28 w-28 rounded-full bg-emerald-50 flex items-center justify-center">
                            <CheckCircle2 className="h-12 w-12 text-emerald-500" />
                        </div>
                        <p className="text-sm text-zinc-500">You're done for today</p>
                    </div>
                ) : isClockedIn ? (
                    <ClockButton type="out" isPending={clockOutMut.isPending} onClick={() => setShowConfirm('out')} />
                ) : (
                    <ClockButton type="in" isPending={clockInMut.isPending} onClick={() => setShowConfirm('in')} />
                )}
            </div>

            {/* Alerts */}
            {error && (
                <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 p-3">
                    <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
                    <p className="text-sm text-red-700">{error}</p>
                </div>
            )}
            {success && (
                <div className="flex items-center gap-2 rounded-lg bg-emerald-50 border border-emerald-200 p-3">
                    <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />
                    <p className="text-sm text-emerald-700">{success}</p>
                </div>
            )}

            {/* Today's Record */}
            {today && (
                <Card>
                    <CardContent className="py-4">
                        <h3 className="text-sm font-medium text-zinc-700 mb-3">Today's Record</h3>
                        <div className="grid grid-cols-3 gap-3 text-center">
                            <div>
                                <p className="text-xs text-zinc-500">Clock In</p>
                                <p className="text-sm font-semibold text-zinc-900">{formatTime(today.clock_in)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-zinc-500">Clock Out</p>
                                <p className="text-sm font-semibold text-zinc-900">{formatTime(today.clock_out)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-zinc-500">Total Hours</p>
                                <p className="text-sm font-semibold text-zinc-900">
                                    {today.total_work_minutes ? formatDuration(today.total_work_minutes) : '--:--'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Schedule Display */}
            <Card>
                <CardContent className="py-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Clock className="h-4 w-4 text-zinc-400" />
                            <span className="text-sm text-zinc-600">Your schedule</span>
                        </div>
                        <span className="text-sm font-medium text-zinc-900">
                            {today?.schedule_start && today?.schedule_end
                                ? `${today.schedule_start} - ${today.schedule_end}`
                                : '9:00 AM - 6:00 PM'}
                        </span>
                    </div>
                </CardContent>
            </Card>

            {/* Week Summary Strip */}
            <Card>
                <CardContent className="py-4">
                    <h3 className="text-sm font-medium text-zinc-700 mb-3">This Week</h3>
                    <div className="flex justify-between">
                        {DAY_LABELS.map((day, i) => {
                            const dayData = weekSummary[i];
                            const status = dayData?.status || 'none';
                            return (
                                <div key={day} className="flex flex-col items-center gap-1.5">
                                    <span className="text-[10px] font-medium text-zinc-500">{day}</span>
                                    <div className={cn(
                                        'h-3 w-3 rounded-full',
                                        DAY_COLORS[status] || DAY_COLORS.none
                                    )} />
                                </div>
                            );
                        })}
                    </div>
                    <div className="flex flex-wrap gap-x-4 gap-y-1 mt-3">
                        {[
                            { label: 'Present', color: 'bg-emerald-500' },
                            { label: 'Late', color: 'bg-amber-500' },
                            { label: 'Absent', color: 'bg-red-500' },
                            { label: 'WFH', color: 'bg-blue-500' },
                        ].map((item) => (
                            <div key={item.label} className="flex items-center gap-1">
                                <div className={cn('h-2 w-2 rounded-full', item.color)} />
                                <span className="text-[10px] text-zinc-500">{item.label}</span>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Clock In Confirmation */}
            <ConfirmDialog
                open={showConfirm === 'in'}
                onOpenChange={(open) => !open && setShowConfirm(null)}
                title="Clock In"
                description={`Are you sure you want to clock in now? The current time is ${new Date().toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', hour12: true })}.`}
                confirmLabel="Yes, Clock In"
                variant="default"
                loading={clockInMut.isPending}
                onConfirm={() => {
                    clockInMut.mutate();
                    setShowConfirm(null);
                }}
            />

            {/* Clock Out Confirmation */}
            <ConfirmDialog
                open={showConfirm === 'out'}
                onOpenChange={(open) => !open && setShowConfirm(null)}
                title="Clock Out"
                description="Are you sure you want to clock out? Make sure you have completed all your tasks for today."
                confirmLabel="Yes, Clock Out"
                variant="destructive"
                loading={clockOutMut.isPending}
                onConfirm={() => {
                    clockOutMut.mutate();
                    setShowConfirm(null);
                }}
            />
        </div>
    );
}

import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    Clock,
    Camera,
    MapPin,
    MapPinOff,
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
    ArrowRight,
    CalendarRange,
    Sparkles,
    Coffee,
    Palmtree,
} from 'lucide-react';
import { clockIn, clockOut, fetchMyTodayAttendance, fetchMyAttendanceSummary, fetchOfficeLocation } from '../lib/api';
import { cn } from '../lib/utils';
import { Card, CardContent } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { EmptyState } from '../components/ui/empty-state';
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

function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLng = ((lng2 - lng1) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((lat1 * Math.PI) / 180) *
            Math.cos((lat2 * Math.PI) / 180) *
            Math.sin(dLng / 2) *
            Math.sin(dLng / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const DAY_COLORS = {
    present: 'bg-gradient-to-t from-emerald-500 to-emerald-400',
    late: 'bg-gradient-to-t from-amber-500 to-amber-400',
    absent: 'bg-gradient-to-t from-rose-500 to-rose-400',
    wfh: 'bg-gradient-to-t from-indigo-500 to-indigo-400',
    on_leave: 'bg-gradient-to-t from-violet-500 to-violet-400',
    leave: 'bg-gradient-to-t from-violet-500 to-violet-400',
    half_day: 'bg-gradient-to-t from-orange-500 to-orange-400',
    early_leave: 'bg-gradient-to-t from-fuchsia-500 to-fuchsia-400',
    holiday: 'bg-slate-200',
    off_day: 'bg-slate-200',
    none: 'bg-slate-100',
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

// ---- Live Elapsed Timer Hook ----
function useLiveElapsed(clockInTime) {
    const [elapsed, setElapsed] = useState(0);
    useEffect(() => {
        if (!clockInTime) return;
        const startMs = new Date(clockInTime).getTime();
        const update = () => setElapsed(Math.max(0, Math.floor((Date.now() - startMs) / 1000)));
        update();
        const id = setInterval(update, 1000);
        return () => clearInterval(id);
    }, [clockInTime]);
    return elapsed;
}

function formatElapsedParts(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return {
        hours: String(h).padStart(2, '0'),
        minutes: String(m).padStart(2, '0'),
        seconds: String(s).padStart(2, '0'),
        totalMinutes: Math.floor(seconds / 60),
    };
}

// ─────────────────────────────────────────────────────────────
// FRESH DESIGN — Progress Ring centerpiece
// ─────────────────────────────────────────────────────────────

function parseScheduleMinutes(start, end) {
    if (!start || !end) return 540; // default 9h
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    return Math.max(60, (eh * 60 + em) - (sh * 60 + sm));
}

function ProgressRing({ state, currentTime, clockInTime, totalWorkMinutes, scheduleStart, scheduleEnd }) {
    const elapsedSec = useLiveElapsed(state === 'working' ? clockInTime : null);
    const size = 264;
    const stroke = 14;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;

    const scheduledMin = parseScheduleMinutes(scheduleStart, scheduleEnd);
    let progress = 0;
    if (state === 'working') {
        progress = Math.min(elapsedSec / (scheduledMin * 60), 1);
    } else if (state === 'complete') {
        progress = 1;
    }
    const offset = circumference * (1 - progress);

    // Ring colors per state
    const ringId = `ring-${state}`;
    const ringStops = state === 'complete'
        ? [['0%', '#10B981'], ['100%', '#34D399']]
        : state === 'on_leave'
        ? [['0%', '#8B5CF6'], ['100%', '#A78BFA']]
        : state === 'off_day'
        ? [['0%', '#CBD5E1'], ['100%', '#94A3B8']]
        : state === 'working'
        ? [['0%', '#6366F1'], ['50%', '#EC4899'], ['100%', '#FB923C']]
        : [['0%', '#C7D2FE'], ['100%', '#FBCFE8']]; // ready (idle, soft)

    // Inner content
    let timeNode;
    let subLabel;
    if (state === 'working') {
        const { hours, minutes, seconds } = formatElapsedParts(elapsedSec);
        timeNode = (
            <div className="flex items-baseline tabular-nums leading-none">
                <span className="text-[44px] font-bold text-slate-900 tracking-tight">{hours}</span>
                <span className="mx-0.5 text-2xl font-bold text-slate-400">:</span>
                <span className="text-[44px] font-bold text-slate-900 tracking-tight">{minutes}</span>
                <span className="mx-0.5 text-2xl font-bold text-slate-400">:</span>
                <span className="text-2xl font-semibold text-slate-500 tracking-tight">{seconds}</span>
            </div>
        );
        subLabel = `Started at ${formatTime(clockInTime)}`;
    } else if (state === 'complete') {
        timeNode = (
            <div className="text-[44px] font-bold tabular-nums leading-none tracking-tight text-emerald-700">
                {totalWorkMinutes != null ? formatDuration(totalWorkMinutes) : '–'}
            </div>
        );
        subLabel = 'Great work today!';
    } else if (state === 'on_leave') {
        timeNode = (
            <div className="flex items-center gap-2 text-2xl font-bold text-violet-700">
                <Palmtree className="h-7 w-7" />
                On Leave
            </div>
        );
        subLabel = 'Enjoy your day off';
    } else if (state === 'off_day') {
        timeNode = (
            <div className="flex items-center gap-2 text-2xl font-bold text-slate-600">
                <Coffee className="h-7 w-7" />
                Off Day
            </div>
        );
        subLabel = 'Rest and recharge';
    } else {
        // ready — show current time in 12-hour format
        const h12 = currentTime.toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit', hour12: true });
        const [time, ampm] = h12.split(' ');
        timeNode = (
            <div className="flex items-baseline tabular-nums leading-none">
                <span className="hr-shimmer text-[52px] font-bold tracking-tight">{time}</span>
                <span className="ml-1.5 text-lg font-bold text-slate-500">{ampm?.toLowerCase()}</span>
            </div>
        );
        subLabel = 'Ready when you are';
    }

    const stateLabel =
        state === 'working' ? 'WORKING' :
        state === 'complete' ? 'COMPLETED' :
        state === 'on_leave' ? 'ON LEAVE' :
        state === 'off_day' ? 'OFF DAY' :
        'READY';

    const stateLabelColor =
        state === 'working' ? 'text-pink-600' :
        state === 'complete' ? 'text-emerald-600' :
        state === 'on_leave' ? 'text-violet-600' :
        state === 'off_day' ? 'text-slate-500' :
        'text-indigo-600';

    return (
        <div className="relative mx-auto" style={{ width: size, height: size }}>
            {/* Outer ambient glow */}
            <div className={cn(
                'absolute inset-0 rounded-full blur-3xl opacity-50',
                state === 'working' ? 'bg-gradient-to-br from-indigo-300 via-pink-300 to-orange-200' :
                state === 'complete' ? 'bg-emerald-200' :
                state === 'on_leave' ? 'bg-violet-200' :
                state === 'off_day' ? 'bg-slate-200' :
                'bg-gradient-to-br from-rose-200 via-violet-200 to-indigo-200'
            )} aria-hidden />

            {/* Rotating conic halo — only when ready/working (subtle life) */}
            {(state === 'ready' || state === 'working') && (
                <div
                    className="absolute -inset-4 rounded-full opacity-30 blur-2xl hr-halo hr-spin-slow"
                    aria-hidden
                />
            )}

            {/* Ambient sparkles around the ring (only ready state for delight) */}
            {state === 'ready' && (
                <>
                    <span className="absolute right-2 top-6 h-1.5 w-1.5 rounded-full bg-pink-400 hr-twinkle" aria-hidden />
                    <span className="absolute left-3 top-1/3 h-1 w-1 rounded-full bg-indigo-400 hr-twinkle-2" aria-hidden />
                    <span className="absolute right-6 bottom-8 h-1 w-1 rounded-full bg-orange-400 hr-twinkle-3" aria-hidden />
                    <span className="absolute left-8 bottom-4 h-1.5 w-1.5 rounded-full bg-violet-400 hr-twinkle-2" aria-hidden />
                </>
            )}

            {/* Outer ring */}
            <svg width={size} height={size} className="relative -rotate-90">
                <defs>
                    <linearGradient id={ringId} x1="0%" y1="0%" x2="100%" y2="100%">
                        {ringStops.map(([offsetVal, color]) => (
                            <stop key={offsetVal} offset={offsetVal} stopColor={color} />
                        ))}
                    </linearGradient>
                </defs>
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="#F1F5F9"
                    strokeWidth={stroke}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke={`url(#${ringId})`}
                    strokeWidth={stroke}
                    strokeDasharray={circumference}
                    strokeDashoffset={state === 'ready' ? circumference : offset}
                    strokeLinecap="round"
                    style={{ transition: 'stroke-dashoffset 1s ease-out' }}
                />
            </svg>

            {/* Inner content */}
            <div className="absolute inset-0 flex flex-col items-center justify-center text-center px-8">
                {/* State pill at top */}
                <div className={cn(
                    'inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-widest shadow-sm ring-1 ring-slate-200',
                    stateLabelColor
                )}>
                    <span className={cn(
                        'h-1.5 w-1.5 rounded-full',
                        state === 'working' ? 'bg-pink-500 animate-pulse' :
                        state === 'complete' ? 'bg-emerald-500' :
                        state === 'on_leave' ? 'bg-violet-500' :
                        state === 'off_day' ? 'bg-slate-400' :
                        'bg-indigo-500'
                    )} />
                    {stateLabel}
                </div>

                {/* Main display */}
                <div className="mt-3">
                    {timeNode}
                </div>

                {/* Sublabel */}
                <p className="mt-2 text-[11px] font-medium text-slate-500">
                    {subLabel}
                </p>

                {/* Progress percentage when working */}
                {state === 'working' && (
                    <p className="mt-1 text-[10px] font-semibold tabular-nums text-slate-400">
                        {Math.round(progress * 100)}% of workday
                    </p>
                )}
            </div>
        </div>
    );
}

function ActionPill({ type, isPending, onClick, disabled, hint }) {
    const isClockIn = type === 'in';
    const isDisabled = isPending || disabled;
    return (
        <div className="space-y-2">
            <button
                onClick={onClick}
                disabled={isDisabled}
                className={cn(
                    'group relative h-14 w-full overflow-hidden rounded-2xl text-white transition-all active:scale-[0.97] active:duration-75',
                    'focus:outline-none focus-visible:ring-4 focus-visible:ring-offset-2 focus-visible:ring-offset-white',
                    isDisabled
                        ? 'cursor-not-allowed bg-slate-300 shadow-md shadow-slate-300/40'
                        : isClockIn
                            ? 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 shadow-xl shadow-pink-500/40 hover:shadow-2xl hover:shadow-pink-500/50 focus-visible:ring-pink-300'
                            : 'bg-gradient-to-r from-orange-500 via-rose-500 to-fuchsia-500 shadow-xl shadow-rose-500/40 hover:shadow-2xl hover:shadow-rose-500/50 focus-visible:ring-rose-300'
                )}
            >
                {/* Top inner highlight — gives a 3D bevel feel */}
                {!isDisabled && (
                    <span className="pointer-events-none absolute inset-x-0 top-0 h-1/2 rounded-t-2xl bg-gradient-to-b from-white/25 to-transparent" aria-hidden />
                )}
                {/* Shimmer sweep overlay (slow ambient) */}
                {!isDisabled && (
                    <span className="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full" aria-hidden />
                )}

                {isPending ? (
                    <Loader2 className="mx-auto h-5 w-5 animate-spin" />
                ) : (
                    <div className="relative flex items-center justify-center gap-2.5">
                        <Clock className="h-5 w-5 drop-shadow-sm" strokeWidth={2.5} />
                        <span className="text-sm font-bold tracking-wider drop-shadow-sm">
                            {isClockIn ? 'CLOCK IN' : 'CLOCK OUT'}
                        </span>
                        <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" strokeWidth={2.5} />
                    </div>
                )}
            </button>
            {disabled && hint && (
                <p className="text-center text-[11px] font-medium text-slate-500">{hint}</p>
            )}
        </div>
    );
}

function CompactCamera({ onCapture, isCapturing, isCompleted }) {
    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const [hasCamera, setHasCamera] = useState(true);
    const [cameraReady, setCameraReady] = useState(false);

    useEffect(() => {
        if (isCompleted) return;
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
    }, [isCompleted]);

    const capture = useCallback(() => {
        if (!videoRef.current || !cameraReady) return null;
        const canvas = document.createElement('canvas');
        canvas.width = videoRef.current.videoWidth || 320;
        canvas.height = videoRef.current.videoHeight || 240;
        canvas.getContext('2d').drawImage(videoRef.current, 0, 0);
        return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.7));
    }, [cameraReady]);

    useEffect(() => {
        if (onCapture) onCapture(capture);
    }, [capture, onCapture]);

    if (isCompleted) return null;

    if (!hasCamera) {
        return (
            <div className="flex items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 p-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                    <Camera className="h-4 w-4 text-slate-400" strokeWidth={2.25} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold text-slate-600">No camera available</p>
                    <p className="text-[11px] text-slate-400">Photo verification optional</p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3 rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50/60 to-pink-50/60 p-2.5">
            <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-900 ring-2 ring-white shadow-md">
                <video
                    ref={videoRef}
                    autoPlay
                    playsInline
                    muted
                    className="h-full w-full object-cover"
                />
                {!cameraReady && (
                    <div className="absolute inset-0 flex items-center justify-center bg-slate-800">
                        <Loader2 className="h-4 w-4 animate-spin text-slate-400" />
                    </div>
                )}
                {isCapturing && (
                    <div className="absolute inset-0 flex items-center justify-center bg-white/80">
                        <Loader2 className="h-4 w-4 animate-spin text-indigo-600" />
                    </div>
                )}
                {/* Tiny LIVE dot */}
                {cameraReady && !isCapturing && (
                    <span className="absolute right-1 top-1 flex h-1.5 w-1.5">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75" />
                        <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-rose-500" />
                    </span>
                )}
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold text-slate-700">Photo verification</p>
                <p className="text-[11px] text-slate-500">Auto-captured when you clock in</p>
            </div>
            <CheckCircle2 className="h-4 w-4 shrink-0 text-indigo-500" />
        </div>
    );
}

// ---- Live Elapsed Timer Component (legacy — kept for compat) ----
function LiveElapsedTimer({ clockInTime }) {
    const elapsed = useLiveElapsed(clockInTime);
    const { hours, minutes, seconds, totalMinutes } = formatElapsedParts(elapsed);

    // Progress against a standard 9-hour workday (540 min)
    const scheduledMinutes = 540;
    const progress = Math.min(totalMinutes / scheduledMinutes, 1);
    const radius = 52;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = circumference * (1 - progress);

    return (
        <div className="relative overflow-hidden rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50 via-orange-50 to-amber-50">
            <div
                className="pointer-events-none absolute inset-0 opacity-40"
                style={{
                    background: 'radial-gradient(ellipse at 50% 0%, rgb(251 191 36 / 0.3) 0%, transparent 70%)',
                }}
            />

            <div className="relative flex flex-col items-center gap-3 py-5 px-4">
                {/* Header badge */}
                <div className="flex items-center gap-2 rounded-full bg-amber-100 border border-amber-200/80 px-3 py-1">
                    <span className="relative flex h-2 w-2">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75" />
                        <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500" />
                    </span>
                    <span className="text-[11px] font-semibold uppercase tracking-widest text-amber-700">
                        Session Active
                    </span>
                </div>

                {/* SVG ring + time digits */}
                <div className="relative">
                    <svg
                        width="136"
                        height="136"
                        viewBox="0 0 136 136"
                        className="-rotate-90"
                        aria-hidden="true"
                    >
                        <circle
                            cx="68" cy="68" r={radius}
                            fill="none"
                            stroke="rgb(251 191 36 / 0.2)"
                            strokeWidth="7"
                        />
                        <circle
                            cx="68" cy="68" r={radius}
                            fill="none"
                            stroke="rgb(245 158 11)"
                            strokeWidth="7"
                            strokeLinecap="round"
                            strokeDasharray={circumference}
                            strokeDashoffset={dashOffset}
                            style={{ transition: 'stroke-dashoffset 1s linear' }}
                        />
                    </svg>

                    {/* Inner digits */}
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-0.5">
                        <span className="text-[9px] font-semibold uppercase tracking-widest text-amber-500">
                            elapsed
                        </span>
                        <div className="flex items-baseline tabular-nums leading-none">
                            <span className="text-[22px] font-bold text-amber-900 tracking-tight">{hours}</span>
                            <span className="mx-px text-[18px] font-bold text-amber-600">:</span>
                            <span className="text-[22px] font-bold text-amber-900 tracking-tight">{minutes}</span>
                            <span className="mx-px text-[18px] font-bold text-amber-600">:</span>
                            <span className="text-[18px] font-semibold text-amber-700 tracking-tight">{seconds}</span>
                        </div>
                    </div>
                </div>

                {/* Progress label */}
                <p className="text-[11px] font-medium text-amber-600">
                    {Math.round(progress * 100)}% of work day completed
                </p>
            </div>
        </div>
    );
}

// ---- GPS Location Hook ----
function useGeoLocation(enabled) {
    const [location, setLocation] = useState(null); // { latitude, longitude }
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const watchIdRef = useRef(null);

    useEffect(() => {
        if (!enabled) {
            setLocation(null);
            setError(null);
            setLoading(false);
            if (watchIdRef.current !== null) {
                navigator.geolocation.clearWatch(watchIdRef.current);
                watchIdRef.current = null;
            }
            return;
        }

        if (!navigator.geolocation) {
            setError('Geolocation is not supported by your browser.');
            return;
        }

        setLoading(true);

        watchIdRef.current = navigator.geolocation.watchPosition(
            (pos) => {
                setLocation({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                });
                setError(null);
                setLoading(false);
            },
            (err) => {
                setError(
                    err.code === 1
                        ? 'Location access denied. Please enable GPS.'
                        : 'Unable to get your location.'
                );
                setLoading(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );

        return () => {
            if (watchIdRef.current !== null) {
                navigator.geolocation.clearWatch(watchIdRef.current);
                watchIdRef.current = null;
            }
        };
    }, [enabled]);

    return { location, error, loading };
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
            <div className="rounded-2xl border border-slate-200 bg-slate-50/60 py-2">
                <EmptyState
                    icon={Camera}
                    accent="slate"
                    title="Camera not available"
                    description="Photo verification is optional for this clock-in"
                />
            </div>
        );
    }

    return (
        <div className="relative rounded-[20px] bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500 p-[2px] shadow-lg shadow-indigo-500/20">
            <div className="relative overflow-hidden rounded-[18px] bg-slate-900">
                <video
                    ref={videoRef}
                    autoPlay
                    playsInline
                    muted
                    className="h-52 w-full object-cover"
                />
                {!cameraReady && (
                    <div className="absolute inset-0 flex items-center justify-center bg-slate-900">
                        <Loader2 className="h-6 w-6 animate-spin text-slate-500" />
                    </div>
                )}
                {isCapturing && (
                    <div className="absolute inset-0 bg-white/80 flex items-center justify-center backdrop-blur-sm">
                        <div className="flex flex-col items-center gap-2">
                            <Loader2 className="h-7 w-7 animate-spin text-indigo-600" />
                            <p className="text-xs font-semibold text-slate-700">Capturing…</p>
                        </div>
                    </div>
                )}
                {/* Subtle vignette */}
                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/50 to-transparent" />
                {/* LIVE pill — pulsing red dot */}
                <div className="pointer-events-none absolute bottom-2.5 left-2.5 inline-flex items-center gap-1.5 rounded-full bg-black/40 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white backdrop-blur-md ring-1 ring-white/20">
                    <span className="relative flex h-2 w-2">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75" />
                        <span className="relative inline-flex h-2 w-2 rounded-full bg-rose-500" />
                    </span>
                    LIVE
                </div>
                {/* Camera icon badge — top right */}
                <div className="pointer-events-none absolute right-2.5 top-2.5 inline-flex items-center gap-1 rounded-full bg-black/40 px-2 py-1 text-[10px] font-semibold text-white backdrop-blur-md ring-1 ring-white/20">
                    <Camera className="h-3 w-3" strokeWidth={2.25} />
                    Front
                </div>
            </div>
        </div>
    );
}

// ---- Clock Button ----
function ClockButton({ type, isPending, onClick, disabled, hint }) {
    const isClockIn = type === 'in';
    const isDisabled = isPending || disabled;
    return (
        <div className="flex flex-col items-center gap-3">
            <div className="relative">
                {/* Breathing pulse ring — only when ready (not disabled, not pending) */}
                {!isDisabled && (
                    <>
                        <span className={cn(
                            'absolute inset-0 rounded-full blur-xl hr-breathe',
                            isClockIn ? 'bg-pink-500/40' : 'bg-orange-500/40'
                        )} aria-hidden />
                        <span className={cn(
                            'absolute inset-0 rounded-full hr-breathe',
                            isClockIn ? 'bg-violet-300/30' : 'bg-rose-300/30'
                        )} aria-hidden />
                    </>
                )}
                <button
                    onClick={onClick}
                    disabled={isDisabled}
                    className={cn(
                        'relative h-32 w-32 rounded-full shadow-xl transition-all active:scale-95',
                        'focus:outline-none focus-visible:ring-4 focus-visible:ring-offset-4 focus-visible:ring-offset-white',
                        isDisabled
                            ? 'cursor-not-allowed bg-gradient-to-br from-slate-300 via-slate-400 to-slate-300 text-white/90 shadow-slate-400/30'
                            : isClockIn
                                ? 'bg-gradient-to-br from-indigo-500 via-pink-500 to-orange-400 text-white shadow-pink-500/50 hover:shadow-2xl hover:shadow-pink-500/60 focus-visible:ring-pink-300'
                                : 'bg-gradient-to-br from-orange-500 via-rose-500 to-fuchsia-500 text-white shadow-rose-500/50 hover:shadow-2xl hover:shadow-rose-500/60 focus-visible:ring-rose-300'
                    )}
                >
                    {/* Inner subtle ring */}
                    <span className="absolute inset-2 rounded-full ring-1 ring-white/30 pointer-events-none" aria-hidden />
                    {isPending ? (
                        <Loader2 className="h-9 w-9 animate-spin mx-auto" />
                    ) : (
                        <div className="flex flex-col items-center gap-1.5">
                            <Clock className="h-7 w-7" strokeWidth={2.25} />
                            <span className="text-sm font-bold tracking-wider">
                                {isClockIn ? 'CLOCK IN' : 'CLOCK OUT'}
                            </span>
                        </div>
                    )}
                </button>
            </div>
            {/* Hint text below the button when disabled */}
            {disabled && hint && (
                <p className="max-w-[200px] text-center text-[11px] font-medium text-slate-500">
                    {hint}
                </p>
            )}
        </div>
    );
}

// ---- Location Status Indicator ----
function LocationStatus({ location, geoError, geoLoading, officeConfig, isWfh }) {
    // For WFH: always show location status (required)
    // For Office: only show if require_location is enabled
    if (!isWfh && !officeConfig?.require_location) return null;

    if (geoLoading) {
        return (
            <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-slate-200">
                    <Loader2 className="h-4 w-4 animate-spin text-slate-500" />
                </div>
                <p className="text-sm font-medium text-slate-600">Getting your location…</p>
            </div>
        );
    }

    if (geoError) {
        return (
            <div className="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100">
                    <MapPinOff className="h-4 w-4 text-rose-600" strokeWidth={2.25} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-rose-800">{geoError}</p>
                    {isWfh && (
                        <p className="mt-0.5 text-xs text-rose-600">Required for WFH clock-in. Enable GPS in browser settings.</p>
                    )}
                </div>
            </div>
        );
    }

    // WFH location status
    if (isWfh && location) {
        return (
            <div className="flex items-center gap-3 rounded-2xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-violet-50/40 p-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100">
                    <MapPin className="h-4 w-4 text-indigo-600" strokeWidth={2.25} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-indigo-800">Location captured</p>
                    <p className="text-xs text-indigo-600">
                        WFH location recorded on clock-in
                    </p>
                </div>
                <CheckCircle2 className="h-4 w-4 text-indigo-500 shrink-0" />
            </div>
        );
    }

    if (location && officeConfig) {
        const distance = calculateDistance(
            location.latitude,
            location.longitude,
            officeConfig.latitude,
            officeConfig.longitude
        );
        const isInRange = distance <= officeConfig.radius_meters;

        return (
            <div className={cn(
                'flex items-center gap-3 rounded-2xl border p-3',
                isInRange
                    ? 'bg-gradient-to-r from-emerald-50 to-emerald-50/40 border-emerald-200'
                    : 'bg-gradient-to-r from-amber-50 to-orange-50/40 border-amber-200'
            )}>
                <div className={cn(
                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                    isInRange ? 'bg-emerald-100' : 'bg-amber-100'
                )}>
                    <MapPin className={cn(
                        'h-4 w-4',
                        isInRange ? 'text-emerald-600' : 'text-amber-600'
                    )} strokeWidth={2.25} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className={cn(
                        'text-sm font-semibold',
                        isInRange ? 'text-emerald-800' : 'text-amber-800'
                    )}>
                        {isInRange ? 'You\'re at the office' : 'You\'re not at the office'}
                    </p>
                    <p className={cn(
                        'text-xs',
                        isInRange ? 'text-emerald-600' : 'text-amber-700'
                    )}>
                        {Math.round(distance)}m away
                        {!isInRange && ` · must be within ${officeConfig.radius_meters}m`}
                    </p>
                </div>
                {isInRange && <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />}
            </div>
        );
    }

    return null;
}

// ---- Clock-Out Location Status (for office employees clocking out remotely) ----
function ClockOutLocationStatus({ location, geoError, geoLoading }) {
    if (geoLoading) {
        return (
            <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-slate-200">
                    <Loader2 className="h-4 w-4 animate-spin text-slate-500" />
                </div>
                <p className="text-sm font-medium text-slate-600">Getting location for clock-out…</p>
            </div>
        );
    }

    if (geoError) {
        return (
            <div className="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100">
                    <MapPinOff className="h-4 w-4 text-rose-600" strokeWidth={2.25} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-rose-800">{geoError}</p>
                    <p className="mt-0.5 text-xs text-rose-600">GPS is required to clock out. Enable location services.</p>
                </div>
            </div>
        );
    }

    if (location) {
        return (
            <div className="flex items-center gap-3 rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 to-sky-50/40 p-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100">
                    <MapPin className="h-4 w-4 text-sky-600" strokeWidth={2.25} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-sky-800">Location captured</p>
                    <p className="text-xs text-sky-600">Will be recorded on clock out</p>
                </div>
                <CheckCircle2 className="h-4 w-4 text-sky-500 shrink-0" />
            </div>
        );
    }

    return null;
}

// ========== MAIN COMPONENT ==========
export default function ClockInOut() {
    const queryClient = useQueryClient();
    const now = useLiveClock();
    const [isWfh, setIsWfh] = useState(false);
    const captureRef = useRef(null);
    const pendingPhotoRef = useRef(null);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [showConfirm, setShowConfirm] = useState(null); // 'in' | 'out' | null

    const user = window.hrConfig?.user || { name: 'User' };
    const greeting = getGreeting();
    const GreetingIcon = greeting.icon;

    // Fetch office location settings
    const { data: officeData } = useQuery({
        queryKey: ['office-location'],
        queryFn: fetchOfficeLocation,
        staleTime: 5 * 60 * 1000, // cache 5 minutes
    });
    const officeConfig = officeData?.data;

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
    const PRESENT_STATUSES = ['present', 'late', 'wfh', 'early_leave', 'half_day'];
    const weekDaysIn = weekSummary.filter((d) => PRESENT_STATUSES.includes(d?.status)).length;

    const isClockedIn = today?.clock_in && !today?.clock_out;
    const isCompleted = today?.clock_in && today?.clock_out;

    // Enable GPS for:
    // - Before clock-in: WFH (always) or Office (if require_location enabled)
    // - After clock-in: needed for clock-out location capture (both office and WFH)
    const isOfficeSession = isClockedIn && today?.status !== 'wfh';
    const isWfhSession = isClockedIn && today?.status === 'wfh';
    const needsGps = !isCompleted && (isWfh || officeConfig?.require_location || isClockedIn);
    const { location: geoLocation, error: geoError, loading: geoLoading } = useGeoLocation(needsGps);

    // Determine if clock-in should be disabled due to location issues
    const isLocationBlocked = (() => {
        // WFH: must have location before clock-in
        if (isWfh) {
            return !geoLocation;
        }
        // Office: check if require_location is enabled and validate range
        if (!officeConfig?.require_location) return false;
        if (!geoLocation) return true;
        const distance = calculateDistance(
            geoLocation.latitude,
            geoLocation.longitude,
            officeConfig.latitude,
            officeConfig.longitude
        );
        return distance > officeConfig.radius_meters;
    })();

    const clockInMut = useMutation({
        mutationFn: async () => {
            const formData = new FormData();
            formData.append('is_wfh', isWfh ? '1' : '0');
            if (geoLocation) {
                formData.append('latitude', geoLocation.latitude);
                formData.append('longitude', geoLocation.longitude);
            }
            if (captureRef.current) {
                const blob = await captureRef.current();
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
            const data = err?.response?.data;
            const validationErrors = data?.errors;
            const firstValidationError = validationErrors
                ? Object.values(validationErrors).flat()[0]
                : null;
            const msg = data?.message
                || firstValidationError
                || err?.message
                || 'Failed to clock in';
            setError(msg);
            setSuccess(null);
        },
    });

    // Block clock-out if GPS not available (both office and WFH)
    const isClockOutLocationBlocked = (() => {
        if (!isClockedIn) return false;
        return !geoLocation;
    })();

    const clockOutMut = useMutation({
        mutationFn: async () => {
            const formData = new FormData();
            if (pendingPhotoRef.current) {
                formData.append('photo', pendingPhotoRef.current, 'clock-out.jpg');
                pendingPhotoRef.current = null;
            }
            if (geoLocation) {
                formData.append('latitude', geoLocation.latitude);
                formData.append('longitude', geoLocation.longitude);
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

    // Live elapsed seconds when actively clocked in (used for Today's Record live update)
    const liveElapsedSeconds = useLiveElapsed(isClockedIn ? today?.clock_in : null);
    const liveElapsedMinutes = Math.floor(liveElapsedSeconds / 60);

    // Derive ring state
    const ringState =
        isCompleted ? 'complete' :
        isClockedIn ? 'working' :
        today?.is_on_leave ? 'on_leave' :
        today?.is_working_day === false ? 'off_day' :
        'ready';

    const greetingDate = now.toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long' });

    return (
        <div className="space-y-5 max-w-md mx-auto pb-2">
            {/* ─── Top row: greeting chip + date ─────────────────── */}
            <div className="flex items-center justify-between">
                <div className="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm ring-1 ring-rose-100">
                    <GreetingIcon className="h-3.5 w-3.5 text-amber-500" />
                    {greeting.text}, {user.name?.split(' ')[0]}
                </div>
                <span className="text-[11px] font-semibold text-slate-500">{greetingDate}</span>
            </div>

            {/* ─── Hero: Progress Ring ───────────────────────────── */}
            <div className="relative py-3">
                <ProgressRing
                    state={ringState}
                    currentTime={now}
                    clockInTime={today?.clock_in}
                    totalWorkMinutes={today?.total_work_minutes}
                    scheduleStart={today?.schedule_start}
                    scheduleEnd={today?.schedule_end}
                />
            </div>

            {/* ─── Quick info chips below ring ─────────────────────── */}
            <div className="flex flex-wrap items-center justify-center gap-2">
                {today?.schedule_start && today?.schedule_end && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
                        <Clock className="h-3 w-3 text-sky-500" strokeWidth={2.5} />
                        <span className="tabular-nums">{today.schedule_start} – {today.schedule_end}</span>
                    </div>
                )}
                {weekDaysIn > 0 && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
                        <Sparkles className="h-3 w-3 text-amber-500" strokeWidth={2.5} />
                        <span className="tabular-nums">{weekDaysIn}</span>
                        <span className="text-slate-500">day{weekDaysIn !== 1 ? 's' : ''} this week</span>
                    </div>
                )}
                {ringState === 'working' && (
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-pink-50 to-orange-50 px-3 py-1.5 text-[11px] font-semibold text-pink-700 ring-1 ring-pink-200">
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-pink-400 opacity-75" />
                            <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-pink-500" />
                        </span>
                        Live session
                    </div>
                )}
            </div>

            {/* ─── Office / WFH segmented toggle (only when not clocked in) ───── */}
            {!isClockedIn && !isCompleted && ringState === 'ready' && (
                <div className="flex items-center justify-center">
                    <div className="inline-flex rounded-full border border-slate-200 bg-white p-1 shadow-sm">
                        <button
                            onClick={() => setIsWfh(false)}
                            aria-pressed={!isWfh}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                !isWfh
                                    ? 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 text-white shadow-md shadow-pink-500/30'
                                    : 'text-slate-500 hover:text-slate-700'
                            )}
                        >
                            <Building2 className="h-3.5 w-3.5" strokeWidth={2.5} /> Office
                        </button>
                        <button
                            onClick={() => setIsWfh(true)}
                            aria-pressed={isWfh}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                isWfh
                                    ? 'bg-gradient-to-r from-indigo-500 via-pink-500 to-orange-400 text-white shadow-md shadow-pink-500/30'
                                    : 'text-slate-500 hover:text-slate-700'
                            )}
                        >
                            <Home className="h-3.5 w-3.5" strokeWidth={2.5} /> WFH
                        </button>
                    </div>
                </div>
            )}

            {/* ─── Primary action ─────────────────────────────────── */}
            {ringState === 'ready' && (
                <ActionPill
                    type="in"
                    isPending={clockInMut.isPending}
                    onClick={() => setShowConfirm('in')}
                    disabled={isLocationBlocked}
                    hint={isLocationBlocked ? (isWfh ? 'Enable GPS for WFH clock-in' : 'You must be at the office to clock in') : null}
                />
            )}
            {ringState === 'working' && (
                <ActionPill
                    type="out"
                    isPending={clockOutMut.isPending}
                    onClick={async () => {
                        pendingPhotoRef.current = null;
                        try {
                            if (captureRef.current) {
                                pendingPhotoRef.current = await captureRef.current();
                            }
                        } catch { /* ignore */ }
                        setShowConfirm('out');
                    }}
                    disabled={isClockOutLocationBlocked}
                    hint={isClockOutLocationBlocked ? 'Enable location to clock out' : null}
                />
            )}
            {(ringState === 'on_leave' || ringState === 'off_day') && (
                <div className="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white py-4">
                    <Sparkles className="h-4 w-4 text-amber-500" />
                    <span className="text-sm font-semibold text-slate-700">
                        {ringState === 'on_leave' ? 'No clock-in today — enjoy!' : 'It\'s your day off — relax!'}
                    </span>
                </div>
            )}

            {/* ─── Location status (compact) ───────────────────────── */}
            {!isClockedIn && !isCompleted && ringState === 'ready' && (
                <LocationStatus
                    location={geoLocation}
                    geoError={geoError}
                    geoLoading={geoLoading}
                    officeConfig={officeConfig}
                    isWfh={isWfh}
                />
            )}
            {ringState === 'working' && (
                <ClockOutLocationStatus
                    location={geoLocation}
                    geoError={geoError}
                    geoLoading={geoLoading}
                />
            )}

            {/* ─── Camera (compact strip) ──────────────────────────── */}
            {(ringState === 'ready' || ringState === 'working') && (
                <CompactCamera
                    onCapture={(fn) => { captureRef.current = fn; }}
                    isCapturing={isPending}
                    isCompleted={isCompleted}
                />
            )}

            {/* ─── Alerts ──────────────────────────────────────────── */}
            {error && (
                <div className="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 shadow-sm">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100">
                        <AlertCircle className="h-4 w-4 text-rose-600" strokeWidth={2.25} />
                    </div>
                    <p className="text-sm font-medium text-rose-800">{error}</p>
                </div>
            )}
            {success && (
                <div className="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-emerald-50/40 p-3 shadow-sm">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600" strokeWidth={2.25} />
                    </div>
                    <p className="text-sm font-semibold text-emerald-800">{success}</p>
                </div>
            )}

            {/* ─── Today's record (compact 3-up tiles) ─────────────── */}
            {today && (
                <div className="grid grid-cols-3 gap-2">
                    <div className="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-emerald-50/30 p-3 text-center">
                        <p className="text-[9px] font-bold uppercase tracking-widest text-emerald-700">In</p>
                        <p className="mt-1 text-base font-bold tabular-nums text-slate-900">{formatTime(today.clock_in)}</p>
                    </div>
                    <div className="rounded-2xl border border-rose-100 bg-gradient-to-br from-rose-50 to-rose-50/30 p-3 text-center">
                        <p className="text-[9px] font-bold uppercase tracking-widest text-rose-700">Out</p>
                        <p className="mt-1 text-base font-bold tabular-nums text-slate-900">{formatTime(today.clock_out)}</p>
                    </div>
                    <div className={cn(
                        'rounded-2xl border p-3 text-center',
                        isClockedIn
                            ? 'bg-gradient-to-br from-amber-50 to-orange-50/30 border-amber-100'
                            : 'bg-gradient-to-br from-indigo-50 to-pink-50/30 border-indigo-100'
                    )}>
                        <p className={cn(
                            'text-[9px] font-bold uppercase tracking-widest',
                            isClockedIn ? 'text-amber-700' : 'text-indigo-700'
                        )}>Total</p>
                        <p className="mt-1 text-base font-bold tabular-nums text-slate-900">
                            {today.total_work_minutes
                                ? formatDuration(today.total_work_minutes)
                                : isClockedIn
                                    ? formatDuration(liveElapsedMinutes)
                                    : '--:--'}
                        </p>
                    </div>
                </div>
            )}


            {/* Week Summary Strip */}
            <Card className="border-slate-200/80">
                <CardContent className="py-4">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <div className="flex h-6 w-6 items-center justify-center rounded-lg bg-violet-50">
                                <CalendarRange className="h-3.5 w-3.5 text-violet-600" strokeWidth={2.25} />
                            </div>
                            This Week
                        </h3>
                        <span className="text-[11px] font-semibold text-slate-500">
                            <span className="tabular-nums text-slate-800">{weekDaysIn}</span> / 5 days
                        </span>
                    </div>
                    {/* Mini bar chart — height encodes scheduled work, color encodes status */}
                    <div className="flex h-20 items-end justify-between gap-1.5">
                        {weekSummary.map((dayData, i) => {
                            const status = dayData?.status || 'none';
                            const isOffDay = dayData?.is_working_day === false;
                            const isPresent = PRESENT_STATUSES.includes(status);
                            const isToday = i === ((new Date().getDay() + 6) % 7); // 0=Mon..6=Sun
                            // Height: full for present-y days, 30% for absent/leave, 15% for off day
                            const height = isOffDay
                                ? 15
                                : isPresent
                                    ? 100
                                    : status === 'on_leave' || status === 'leave'
                                        ? 55
                                        : status === 'absent'
                                            ? 30
                                            : 8;
                            return (
                                <div key={i} className="flex flex-1 flex-col items-center gap-1.5">
                                    <div className="flex h-full w-full flex-col justify-end">
                                        <div
                                            className={cn(
                                                'w-full rounded-md transition-all',
                                                isToday && 'ring-2 ring-offset-1 ring-pink-300',
                                                DAY_COLORS[status] || 'bg-slate-100'
                                            )}
                                            style={{ height: `${height}%` }}
                                        />
                                    </div>
                                    <span className={cn(
                                        'text-[10px] font-bold uppercase tracking-wider',
                                        isToday ? 'text-pink-600' : isOffDay ? 'text-slate-300' : 'text-slate-500'
                                    )}>
                                        {DAY_LABELS[i]}
                                    </span>
                                </div>
                            );
                        })}
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

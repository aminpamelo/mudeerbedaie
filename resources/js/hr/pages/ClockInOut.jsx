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
} from 'lucide-react';
import { clockIn, clockOut, fetchMyTodayAttendance, fetchMyAttendanceSummary, fetchOfficeLocation } from '../lib/api';
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

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
const DAY_COLORS = {
    present: 'bg-teal-500',
    late: 'bg-amber-500',
    absent: 'bg-rose-500',
    wfh: 'bg-indigo-500',
    leave: 'bg-violet-500',
    holiday: 'bg-slate-300',
    none: 'bg-slate-200',
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
            <div className="flex flex-col items-center justify-center rounded-2xl bg-slate-100 p-6 text-center">
                <Camera className="h-8 w-8 text-slate-400 mb-2" />
                <p className="text-xs text-slate-500">Camera not available</p>
            </div>
        );
    }

    return (
        <div className="relative overflow-hidden rounded-2xl bg-slate-900">
            <video
                ref={videoRef}
                autoPlay
                playsInline
                muted
                className="h-48 w-full object-cover"
            />
            {!cameraReady && (
                <div className="absolute inset-0 flex items-center justify-center bg-slate-900">
                    <Loader2 className="h-6 w-6 animate-spin text-slate-500" />
                </div>
            )}
            {isCapturing && (
                <div className="absolute inset-0 bg-white/80 flex items-center justify-center backdrop-blur-sm">
                    <Loader2 className="h-6 w-6 animate-spin text-slate-700" />
                </div>
            )}
        </div>
    );
}

// ---- Clock Button ----
function ClockButton({ type, isPending, onClick, disabled }) {
    const isClockIn = type === 'in';
    return (
        <button
            onClick={onClick}
            disabled={isPending || disabled}
            className={cn(
                'relative h-28 w-28 rounded-full shadow-lg transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed',
                isClockIn
                    ? 'bg-gradient-to-br from-teal-400 to-teal-600 text-white shadow-teal-500/30 hover:shadow-teal-500/40 hover:shadow-xl'
                    : 'bg-gradient-to-br from-rose-400 to-rose-600 text-white shadow-rose-500/30 hover:shadow-rose-500/40 hover:shadow-xl'
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

// ---- Location Status Indicator ----
function LocationStatus({ location, geoError, geoLoading, officeConfig, isWfh }) {
    if (isWfh) return null;
    if (!officeConfig?.require_location) return null;

    if (geoLoading) {
        return (
            <div className="flex items-center gap-2 rounded-xl bg-slate-100 p-3">
                <Loader2 className="h-4 w-4 animate-spin text-slate-500 shrink-0" />
                <p className="text-sm text-slate-600">Getting your location...</p>
            </div>
        );
    }

    if (geoError) {
        return (
            <div className="flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200/80 p-3">
                <MapPinOff className="h-4 w-4 text-rose-500 shrink-0" />
                <p className="text-sm text-rose-700">{geoError}</p>
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
            <div
                className={cn(
                    'flex items-center gap-2 rounded-xl border p-3',
                    isInRange
                        ? 'bg-teal-50 border-teal-200/80'
                        : 'bg-amber-50 border-amber-200/80'
                )}
            >
                <MapPin
                    className={cn(
                        'h-4 w-4 shrink-0',
                        isInRange ? 'text-teal-500' : 'text-amber-500'
                    )}
                />
                <div className="flex-1 min-w-0">
                    <p
                        className={cn(
                            'text-sm font-medium',
                            isInRange ? 'text-teal-700' : 'text-amber-700'
                        )}
                    >
                        {isInRange ? 'You are at the office' : 'You are not at the office'}
                    </p>
                    <p
                        className={cn(
                            'text-xs',
                            isInRange ? 'text-teal-600' : 'text-amber-600'
                        )}
                    >
                        {Math.round(distance)}m away
                        {!isInRange && ` (must be within ${officeConfig.radius_meters}m)`}
                    </p>
                </div>
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
    const [captureRef, setCaptureRef] = useState(null);
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

    const isClockedIn = today?.clock_in && !today?.clock_out;
    const isCompleted = today?.clock_in && today?.clock_out;

    // Enable GPS when in Office mode & not completed/clocked-in
    const needsGps = !isWfh && !isCompleted && officeConfig?.require_location;
    const { location: geoLocation, error: geoError, loading: geoLoading } = useGeoLocation(needsGps);

    // Determine if clock-in should be disabled (office mode + location required + not in range)
    const isOfficeLocationBlocked = (() => {
        if (isWfh || !officeConfig?.require_location) return false;
        if (!geoLocation) return true; // no location yet
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
            if (geoLocation && !isWfh) {
                formData.append('latitude', geoLocation.latitude);
                formData.append('longitude', geoLocation.longitude);
            }
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
                    <h1 className="text-lg font-semibold text-slate-800">
                        {greeting.text}, {user.name?.split(' ')[0]}
                    </h1>
                </div>
                <p className="text-3xl font-bold text-slate-900 tabular-nums tracking-tight">
                    {now.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </p>
                <p className="text-sm text-slate-500 mt-0.5">
                    {now.toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                </p>
            </div>

            {/* Status Indicator */}
            <Card className="border-slate-200/80">
                <CardContent className="py-3">
                    <div className="flex items-center justify-center gap-2">
                        <div className={cn(
                            'h-2.5 w-2.5 rounded-full',
                            isCompleted ? 'bg-teal-500' : isClockedIn ? 'bg-amber-500 animate-pulse' : 'bg-slate-300'
                        )} />
                        <span className="text-sm font-medium text-slate-600">
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
                            'flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all',
                            !isWfh
                                ? 'bg-slate-800 text-white shadow-sm'
                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                        )}
                    >
                        <Building2 className="h-4 w-4" /> Office
                    </button>
                    <button
                        onClick={() => setIsWfh(true)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all',
                            isWfh
                                ? 'bg-gradient-to-r from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-500/20'
                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                        )}
                    >
                        <Home className="h-4 w-4" /> WFH
                    </button>
                </div>
            )}

            {/* Location Status */}
            {!isClockedIn && !isCompleted && (
                <LocationStatus
                    location={geoLocation}
                    geoError={geoError}
                    geoLoading={geoLoading}
                    officeConfig={officeConfig}
                    isWfh={isWfh}
                />
            )}

            {/* Clock Button */}
            <div className="flex justify-center py-2">
                {isCompleted ? (
                    <div className="flex flex-col items-center gap-2">
                        <div className="h-28 w-28 rounded-full bg-teal-50 flex items-center justify-center">
                            <CheckCircle2 className="h-12 w-12 text-teal-500" />
                        </div>
                        <p className="text-sm text-slate-500">You're done for today</p>
                    </div>
                ) : isClockedIn ? (
                    <ClockButton type="out" isPending={clockOutMut.isPending} onClick={() => setShowConfirm('out')} />
                ) : (
                    <ClockButton
                        type="in"
                        isPending={clockInMut.isPending}
                        onClick={() => setShowConfirm('in')}
                        disabled={isOfficeLocationBlocked}
                    />
                )}
            </div>

            {/* Alerts */}
            {error && (
                <div className="flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200/80 p-3">
                    <AlertCircle className="h-4 w-4 text-rose-500 shrink-0" />
                    <p className="text-sm text-rose-700">{error}</p>
                </div>
            )}
            {success && (
                <div className="flex items-center gap-2 rounded-xl bg-teal-50 border border-teal-200/80 p-3">
                    <CheckCircle2 className="h-4 w-4 text-teal-500 shrink-0" />
                    <p className="text-sm text-teal-700">{success}</p>
                </div>
            )}

            {/* Today's Record */}
            {today && (
                <Card className="border-slate-200/80">
                    <CardContent className="py-4">
                        <h3 className="text-sm font-medium text-slate-600 mb-3">Today's Record</h3>
                        <div className="grid grid-cols-3 gap-3 text-center">
                            <div>
                                <p className="text-xs text-slate-500">Clock In</p>
                                <p className="text-sm font-semibold text-slate-800">{formatTime(today.clock_in)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-slate-500">Clock Out</p>
                                <p className="text-sm font-semibold text-slate-800">{formatTime(today.clock_out)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-slate-500">Total Hours</p>
                                <p className="text-sm font-semibold text-slate-800">
                                    {today.total_work_minutes ? formatDuration(today.total_work_minutes) : '--:--'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Schedule Display */}
            <Card className="border-slate-200/80">
                <CardContent className="py-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Clock className="h-4 w-4 text-slate-400" />
                            <span className="text-sm text-slate-500">Your schedule</span>
                        </div>
                        <span className="text-sm font-medium text-slate-800">
                            {today?.schedule_start && today?.schedule_end
                                ? `${today.schedule_start} - ${today.schedule_end}`
                                : '9:00 AM - 6:00 PM'}
                        </span>
                    </div>
                </CardContent>
            </Card>

            {/* Week Summary Strip */}
            <Card className="border-slate-200/80">
                <CardContent className="py-4">
                    <h3 className="text-sm font-medium text-slate-600 mb-3">This Week</h3>
                    <div className="flex justify-between">
                        {DAY_LABELS.map((day, i) => {
                            const dayData = weekSummary[i];
                            const status = dayData?.status || 'none';
                            return (
                                <div key={day} className="flex flex-col items-center gap-1.5">
                                    <span className="text-[10px] font-medium text-slate-500">{day}</span>
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
                            { label: 'Present', color: 'bg-teal-500' },
                            { label: 'Late', color: 'bg-amber-500' },
                            { label: 'Absent', color: 'bg-rose-500' },
                            { label: 'WFH', color: 'bg-indigo-500' },
                        ].map((item) => (
                            <div key={item.label} className="flex items-center gap-1">
                                <div className={cn('h-2 w-2 rounded-full', item.color)} />
                                <span className="text-[10px] text-slate-500">{item.label}</span>
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

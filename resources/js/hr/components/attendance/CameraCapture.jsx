import { useRef, useState, useCallback, useEffect } from 'react';
import { Camera, RefreshCw, Upload } from 'lucide-react';
import { cn } from '../../lib/utils';
import { Button } from '../ui/button';

const MAX_SIZE = 500 * 1024; // 500KB

function compressImage(canvas, quality = 0.7) {
    return new Promise((resolve) => {
        canvas.toBlob(
            (blob) => {
                if (blob && blob.size > MAX_SIZE && quality > 0.1) {
                    compressImage(canvas, quality - 0.1).then(resolve);
                } else {
                    resolve(blob);
                }
            },
            'image/jpeg',
            quality
        );
    });
}

export default function CameraCapture({ onCapture, disabled = false }) {
    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const fileInputRef = useRef(null);
    const streamRef = useRef(null);
    const [capturedImage, setCapturedImage] = useState(null);
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState(false);

    const startCamera = useCallback(async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
            });
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }
            setCameraActive(true);
            setCameraError(false);
        } catch {
            setCameraError(true);
            setCameraActive(false);
        }
    }, []);

    const stopCamera = useCallback(() => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
        }
        setCameraActive(false);
    }, []);

    useEffect(() => {
        startCamera();
        return () => stopCamera();
    }, [startCamera, stopCamera]);

    const capture = useCallback(async () => {
        if (!videoRef.current || !canvasRef.current) return;

        const video = videoRef.current;
        const canvas = canvasRef.current;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);

        const blob = await compressImage(canvas);
        if (blob) {
            const url = URL.createObjectURL(blob);
            setCapturedImage(url);
            stopCamera();
            onCapture?.(blob);
        }
    }, [onCapture, stopCamera]);

    const retake = useCallback(() => {
        if (capturedImage) {
            URL.revokeObjectURL(capturedImage);
        }
        setCapturedImage(null);
        startCamera();
    }, [capturedImage, startCamera]);

    const handleFileChange = useCallback(
        async (e) => {
            const file = e.target.files?.[0];
            if (!file) return;

            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = async () => {
                const canvas = canvasRef.current;
                if (!canvas) return;

                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                const blob = await compressImage(canvas);
                if (blob) {
                    const previewUrl = URL.createObjectURL(blob);
                    setCapturedImage(previewUrl);
                    onCapture?.(blob);
                }
                URL.revokeObjectURL(url);
            };
            img.src = url;
        },
        [onCapture]
    );

    return (
        <div className="flex flex-col items-center gap-4">
            <div
                className={cn(
                    'relative h-48 w-48 overflow-hidden rounded-full border-4 border-zinc-200 bg-zinc-100',
                    disabled && 'opacity-50'
                )}
            >
                {capturedImage ? (
                    <img
                        src={capturedImage}
                        alt="Captured selfie"
                        className="h-full w-full object-cover"
                    />
                ) : cameraActive ? (
                    <video
                        ref={videoRef}
                        autoPlay
                        playsInline
                        muted
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <Camera className="h-12 w-12 text-zinc-400" />
                    </div>
                )}
            </div>

            <canvas ref={canvasRef} className="hidden" />

            {cameraError ? (
                <div className="flex flex-col items-center gap-2">
                    <p className="text-sm text-zinc-500">Camera access denied</p>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        capture="user"
                        onChange={handleFileChange}
                        className="hidden"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={disabled}
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <Upload className="mr-2 h-4 w-4" />
                        Upload Photo
                    </Button>
                </div>
            ) : capturedImage ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    onClick={retake}
                >
                    <RefreshCw className="mr-2 h-4 w-4" />
                    Retake
                </Button>
            ) : (
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    disabled={disabled || !cameraActive}
                    onClick={capture}
                >
                    <Camera className="mr-2 h-4 w-4" />
                    Capture
                </Button>
            )}
        </div>
    );
}

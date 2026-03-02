import { useState, useRef, useCallback, useEffect } from 'react';

const MUTE_KEY = 'whatsapp-inbox-muted';

export default function useNotificationSound() {
    const [muted, setMuted] = useState(() => {
        try {
            return localStorage.getItem(MUTE_KEY) === 'true';
        } catch {
            return false;
        }
    });

    const audioRef = useRef(null);
    const unlockedRef = useRef(false);

    // Preload audio element
    useEffect(() => {
        const audio = new Audio('/sounds/notification.wav');
        audio.preload = 'auto';
        audio.volume = 0.5;
        audioRef.current = audio;

        return () => {
            audio.pause();
            audio.src = '';
        };
    }, []);

    // Unlock audio on first user interaction (browser autoplay policy)
    useEffect(() => {
        if (unlockedRef.current) return;

        const unlock = () => {
            if (audioRef.current && !unlockedRef.current) {
                audioRef.current.play().then(() => {
                    audioRef.current.pause();
                    audioRef.current.currentTime = 0;
                    unlockedRef.current = true;
                }).catch(() => {});
            }
            document.removeEventListener('click', unlock);
            document.removeEventListener('keydown', unlock);
        };

        document.addEventListener('click', unlock, { once: true });
        document.addEventListener('keydown', unlock, { once: true });

        return () => {
            document.removeEventListener('click', unlock);
            document.removeEventListener('keydown', unlock);
        };
    }, []);

    const play = useCallback(() => {
        if (muted || !audioRef.current) return;
        audioRef.current.currentTime = 0;
        audioRef.current.play().catch(() => {});
    }, [muted]);

    const toggleMute = useCallback(() => {
        setMuted(prev => {
            const next = !prev;
            try {
                localStorage.setItem(MUTE_KEY, String(next));
            } catch {}
            return next;
        });
    }, []);

    return { muted, play, toggleMute };
}

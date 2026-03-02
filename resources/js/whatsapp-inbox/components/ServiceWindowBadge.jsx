import React, { useState, useEffect } from 'react';

function formatCountdown(expiresAt) {
    if (!expiresAt) return '';
    const diffMs = new Date(expiresAt) - new Date();
    if (diffMs <= 0) return 'Tamat';
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    if (hours > 0) return `${hours}j ${minutes}m`;
    return `${minutes}m`;
}

export default function ServiceWindowBadge({ isOpen, expiresAt }) {
    const [countdown, setCountdown] = useState('');

    useEffect(() => {
        if (!isOpen || !expiresAt) return;

        const update = () => setCountdown(formatCountdown(expiresAt));
        update();
        const interval = setInterval(update, 60000);
        return () => clearInterval(interval);
    }, [isOpen, expiresAt]);

    const isActive = isOpen && expiresAt && new Date(expiresAt) > new Date();

    if (isActive) {
        return (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/50">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 wa-pulse" />
                Aktif {countdown && `(${countdown})`}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-100 text-zinc-500">
            <span className="w-1.5 h-1.5 rounded-full bg-zinc-400" />
            Tamat
        </span>
    );
}

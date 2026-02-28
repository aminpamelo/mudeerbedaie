import React, { useState, useEffect } from 'react';

function formatCountdown(expiresAt) {
    if (!expiresAt) return '';
    const now = new Date();
    const expires = new Date(expiresAt);
    const diffMs = expires - now;

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

        const update = () => {
            const remaining = formatCountdown(expiresAt);
            setCountdown(remaining);
        };

        update();
        const interval = setInterval(update, 60000); // Update every minute
        return () => clearInterval(interval);
    }, [isOpen, expiresAt]);

    const isActive = isOpen && expiresAt && new Date(expiresAt) > new Date();

    if (isActive) {
        return (
            <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-700">
                <span className="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                Aktif {countdown && `(${countdown})`}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700">
            <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            Tamat
        </span>
    );
}

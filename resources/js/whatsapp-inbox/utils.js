/**
 * Shared utilities for the WhatsApp Inbox.
 */

const AVATAR_COLORS = [
    'from-rose-400 to-rose-600',
    'from-orange-400 to-orange-600',
    'from-amber-400 to-amber-600',
    'from-emerald-400 to-emerald-600',
    'from-teal-400 to-teal-600',
    'from-cyan-400 to-cyan-600',
    'from-blue-400 to-blue-600',
    'from-indigo-400 to-indigo-600',
    'from-violet-400 to-violet-600',
    'from-purple-400 to-purple-600',
    'from-pink-400 to-pink-600',
];

function hashString(str) {
    let hash = 0;
    for (let i = 0; i < (str || '').length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    return Math.abs(hash);
}

export function getAvatarColor(name) {
    return AVATAR_COLORS[hashString(name) % AVATAR_COLORS.length];
}

export function getInitials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map(w => w[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

export function getDisplayName(conversation) {
    return conversation.contact_name || conversation.phone_number;
}

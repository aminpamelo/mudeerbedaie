import React from 'react';

function formatMessageTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' });
}

function StatusIcon({ status }) {
    if (status === 'sent') {
        return (
            <svg className="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 16 16" fill="currentColor">
                <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z" />
            </svg>
        );
    }
    if (status === 'delivered') {
        return (
            <svg className="w-3.5 h-3.5 text-zinc-400" viewBox="0 0 20 16" fill="currentColor">
                <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z" />
                <path d="M17.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0l-.5-.5a.75.75 0 0 1 1.06-1.06l-.03.03 6.72-6.72a.75.75 0 0 1 1.06 0Z" />
            </svg>
        );
    }
    if (status === 'read') {
        return (
            <svg className="w-3.5 h-3.5 text-blue-500" viewBox="0 0 20 16" fill="currentColor">
                <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z" />
                <path d="M17.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0l-.5-.5a.75.75 0 0 1 1.06-1.06l-.03.03 6.72-6.72a.75.75 0 0 1 1.06 0Z" />
            </svg>
        );
    }
    if (status === 'failed') {
        return (
            <svg className="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        );
    }
    if (status === 'pending') {
        return (
            <svg className="w-3.5 h-3.5 text-zinc-300" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        );
    }
    return null;
}

export default function MessageBubble({ message }) {
    const isOutbound = message.direction === 'outbound';
    const isTemplate = message.type === 'template';
    const isMedia = ['image', 'video', 'audio', 'document'].includes(message.type);

    return (
        <div className={`flex ${isOutbound ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[75%] rounded-lg px-3 py-2 ${
                    isOutbound
                        ? 'bg-blue-50 border border-blue-100'
                        : 'bg-white border border-zinc-200'
                }`}
            >
                {/* Template badge */}
                {isTemplate && (
                    <div className="flex items-center gap-1 mb-1">
                        <svg className="w-3 h-3 text-purple-500" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span className="text-[10px] font-medium text-purple-600 uppercase">
                            Templat: {message.template_name}
                        </span>
                    </div>
                )}

                {/* Media placeholder */}
                {isMedia && (
                    <div className="mb-1.5 p-3 bg-zinc-100 rounded text-center text-xs text-zinc-500">
                        <svg className="w-6 h-6 mx-auto mb-1 text-zinc-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                        </svg>
                        {message.media_filename || message.type}
                    </div>
                )}

                {/* Message body */}
                {message.body && (
                    <p className="text-sm text-zinc-800 whitespace-pre-wrap break-words">
                        {message.body}
                    </p>
                )}

                {/* Footer: time + status */}
                <div className={`flex items-center gap-1.5 mt-1 ${isOutbound ? 'justify-end' : 'justify-start'}`}>
                    {message.sent_by && (
                        <span className="text-[10px] text-zinc-400">{message.sent_by}</span>
                    )}
                    <span className="text-[10px] text-zinc-400">
                        {formatMessageTime(message.created_at)}
                    </span>
                    {isOutbound && <StatusIcon status={message.status} />}
                </div>
            </div>
        </div>
    );
}

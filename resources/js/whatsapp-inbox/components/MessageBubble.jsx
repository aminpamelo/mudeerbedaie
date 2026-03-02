import React from 'react';

function formatMessageTime(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' });
}

function StatusIcon({ status }) {
    if (status === 'read') {
        return (
            <svg className="w-[16px] h-[11px] text-[#53bdeb]" viewBox="0 0 16 11" fill="none">
                <path d="M11.07.73l-7 7-2.78-2.73-.71.71L3.7 8.86l.35.36.36-.36 7-7-.71-.71-.63.58z" fill="currentColor"/>
                <path d="M15.07.73l-7 7-1.44-1.41-.71.71 1.79 1.79.35.36.36-.36 7-7-.71-.71-.64.62z" fill="currentColor"/>
            </svg>
        );
    }
    if (status === 'delivered') {
        return (
            <svg className="w-[16px] h-[11px] text-[#667781]" viewBox="0 0 16 11" fill="none">
                <path d="M11.07.73l-7 7-2.78-2.73-.71.71L3.7 8.86l.35.36.36-.36 7-7-.71-.71-.63.58z" fill="currentColor"/>
                <path d="M15.07.73l-7 7-1.44-1.41-.71.71 1.79 1.79.35.36.36-.36 7-7-.71-.71-.64.62z" fill="currentColor"/>
            </svg>
        );
    }
    if (status === 'sent') {
        return (
            <svg className="w-[12px] h-[11px] text-[#667781]" viewBox="0 0 12 11" fill="none">
                <path d="M11.07.73l-7 7-2.78-2.73-.71.71L3.7 8.86l.35.36.36-.36 7-7-.71-.71-.63.58z" fill="currentColor"/>
            </svg>
        );
    }
    if (status === 'failed') {
        return (
            <svg className="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        );
    }
    if (status === 'pending') {
        return (
            <svg className="w-3 h-3 text-[#667781]/50" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
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
        <div className={`flex wa-msg ${isOutbound ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[65%] px-[9px] py-[6px] shadow-sm ${
                    isOutbound
                        ? 'bg-[#d9fdd3] rounded-lg rounded-br-none'
                        : 'bg-white rounded-lg rounded-bl-none'
                }`}
            >
                {/* Template badge */}
                {isTemplate && (
                    <div className="flex items-center gap-1 mb-1 pb-1 border-b border-black/5">
                        <svg className="w-3 h-3 text-purple-500" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span className="text-[10px] font-medium text-purple-600">
                            {message.template_name}
                        </span>
                    </div>
                )}

                {/* Media placeholder */}
                {isMedia && (
                    <div className="mb-1.5 p-3 rounded bg-black/5 text-center text-xs text-[#54656f]">
                        <svg className="w-5 h-5 mx-auto mb-1 text-[#667781]" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                        </svg>
                        {message.media_filename || message.type}
                    </div>
                )}

                {/* Message body */}
                {message.body && (
                    <p className="text-[13.5px] text-[#111b21] whitespace-pre-wrap break-words leading-[19px]">
                        {message.body}
                    </p>
                )}

                {/* Footer: sender, time, status */}
                <div className={`flex items-center gap-1 mt-0.5 ${isOutbound ? 'justify-end' : 'justify-start'}`}>
                    {message.sent_by && (
                        <span className="text-[10px] text-[#667781] italic">{message.sent_by}</span>
                    )}
                    <span className="text-[10.5px] text-[#667781]">
                        {formatMessageTime(message.created_at)}
                    </span>
                    {isOutbound && <StatusIcon status={message.status} />}
                </div>
            </div>
        </div>
    );
}

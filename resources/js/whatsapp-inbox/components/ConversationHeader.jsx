import React from 'react';
import ServiceWindowBadge from './ServiceWindowBadge';

export default function ConversationHeader({ conversation, onArchive, onBack }) {
    return (
        <div className="flex items-center gap-3 px-4 py-3 border-b border-zinc-200 bg-white">
            {/* Back button (mobile) */}
            <button
                onClick={onBack}
                className="md:hidden p-1 rounded hover:bg-zinc-100 transition-colors"
            >
                <svg className="w-5 h-5 text-zinc-600" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>

            {/* Contact Info */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                    <h3 className="text-sm font-semibold text-zinc-900 truncate">
                        {conversation.contact_name || conversation.phone_number}
                    </h3>
                    <ServiceWindowBadge
                        isOpen={conversation.is_service_window_open}
                        expiresAt={conversation.service_window_expires_at}
                    />
                </div>
                <div className="flex items-center gap-2 text-xs text-zinc-500">
                    <span>{conversation.phone_number}</span>
                    {conversation.student_name && (
                        <>
                            <span>&middot;</span>
                            <span className="text-blue-600 font-medium">
                                Pelajar: {conversation.student_name}
                            </span>
                        </>
                    )}
                </div>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-1">
                {conversation.status !== 'archived' && (
                    <button
                        onClick={onArchive}
                        className="p-2 rounded hover:bg-zinc-100 transition-colors text-zinc-500 hover:text-zinc-700"
                        title="Arkibkan perbualan"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                    </button>
                )}
            </div>
        </div>
    );
}

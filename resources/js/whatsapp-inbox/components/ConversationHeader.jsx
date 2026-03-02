import React from 'react';
import { getAvatarColor, getInitials } from '../utils';
import ServiceWindowBadge from './ServiceWindowBadge';

export default function ConversationHeader({ conversation, onArchive, onBack }) {
    const displayName = conversation.contact_name || conversation.phone_number;

    return (
        <div className="flex items-center gap-3 px-4 py-2.5 bg-[#f0f2f5] border-b border-zinc-200/50">
            {/* Back button (mobile) */}
            <button
                onClick={onBack}
                className="md:hidden p-1.5 -ml-1 rounded-full hover:bg-black/5 transition-colors"
            >
                <svg className="w-5 h-5 text-[#54656f]" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>

            {/* Avatar */}
            <div className={`w-10 h-10 rounded-full bg-gradient-to-br ${getAvatarColor(displayName)} flex items-center justify-center text-xs font-semibold text-white shadow-sm shrink-0`}>
                {getInitials(conversation.contact_name || conversation.student_name)}
            </div>

            {/* Contact Info */}
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                    <h3 className="text-[15px] font-semibold text-[#111b21] truncate">
                        {displayName}
                    </h3>
                    <ServiceWindowBadge
                        isOpen={conversation.is_service_window_open}
                        expiresAt={conversation.service_window_expires_at}
                    />
                </div>
                <div className="flex items-center gap-1.5 text-xs text-[#667781]">
                    <span>{conversation.phone_number}</span>
                    {conversation.student_name && (
                        <>
                            <span className="text-zinc-300">·</span>
                            <span className="text-teal-600 font-medium">
                                {conversation.student_name}
                            </span>
                        </>
                    )}
                </div>
            </div>

            {/* Actions */}
            {conversation.status !== 'archived' && (
                <button
                    onClick={onArchive}
                    className="p-2 rounded-full hover:bg-black/5 transition-colors text-[#54656f]"
                    title="Arkibkan perbualan"
                >
                    <svg className="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                </button>
            )}
        </div>
    );
}

import React, { useEffect, useRef } from 'react';
import ConversationHeader from './ConversationHeader';
import MessageBubble from './MessageBubble';
import ReplyInput from './ReplyInput';

export default function ChatPanel({
    conversation,
    messages,
    loading,
    sending,
    onSendReply,
    onArchive,
    onBack,
    onShowTemplatePicker,
}) {
    const messagesEndRef = useRef(null);
    const messagesContainerRef = useRef(null);

    // Auto-scroll to bottom when new messages arrive
    useEffect(() => {
        if (messagesEndRef.current) {
            messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages]);

    const isServiceWindowOpen = conversation.is_service_window_open &&
        conversation.service_window_expires_at &&
        new Date(conversation.service_window_expires_at) > new Date();

    return (
        <>
            <ConversationHeader
                conversation={conversation}
                onArchive={onArchive}
                onBack={onBack}
            />

            {/* Messages Area */}
            <div
                ref={messagesContainerRef}
                className="flex-1 overflow-y-auto p-4 space-y-3 bg-zinc-50 whatsapp-scrollbar"
            >
                {loading ? (
                    <div className="flex items-center justify-center h-32">
                        <svg className="w-5 h-5 animate-spin text-zinc-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                    </div>
                ) : messages.length === 0 ? (
                    <div className="flex items-center justify-center h-32 text-sm text-zinc-400">
                        Tiada mesej dalam perbualan ini
                    </div>
                ) : (
                    messages.map(message => (
                        <MessageBubble key={message.id} message={message} />
                    ))
                )}
                <div ref={messagesEndRef} />
            </div>

            {/* Reply Input */}
            <ReplyInput
                onSend={onSendReply}
                sending={sending}
                isServiceWindowOpen={isServiceWindowOpen}
                onShowTemplatePicker={onShowTemplatePicker}
            />
        </>
    );
}

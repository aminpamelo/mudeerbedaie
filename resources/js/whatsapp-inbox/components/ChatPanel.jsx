import React, { useEffect, useRef } from 'react';
import ConversationHeader from './ConversationHeader';
import MessageBubble from './MessageBubble';
import ReplyInput from './ReplyInput';

function formatDateLabel(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) return 'Hari Ini';
    if (date.toDateString() === yesterday.toDateString()) return 'Semalam';
    return date.toLocaleDateString('ms-MY', { day: 'numeric', month: 'long', year: 'numeric' });
}

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
                className="flex-1 overflow-y-auto px-4 md:px-12 lg:px-20 py-3 wa-chat-bg wa-scroll"
            >
                {loading ? (
                    <div className="flex items-center justify-center h-32">
                        <div className="w-5 h-5 border-2 border-teal-200 border-t-teal-600 rounded-full animate-spin" />
                    </div>
                ) : messages.length === 0 ? (
                    <div className="flex items-center justify-center h-32">
                        <span className="px-4 py-2 bg-white/80 backdrop-blur-sm rounded-lg shadow-sm text-sm text-[#54656f]">
                            Tiada mesej dalam perbualan ini
                        </span>
                    </div>
                ) : (
                    <div className="space-y-1">
                        {messages.map((message, index) => {
                            const showDate = index === 0 ||
                                new Date(message.created_at).toDateString() !==
                                new Date(messages[index - 1].created_at).toDateString();

                            return (
                                <React.Fragment key={message.id}>
                                    {showDate && (
                                        <div className="flex justify-center my-3">
                                            <span className="px-3 py-1 bg-white/90 backdrop-blur-sm rounded-md shadow-sm text-[11px] font-medium text-[#54656f] uppercase tracking-wide">
                                                {formatDateLabel(message.created_at)}
                                            </span>
                                        </div>
                                    )}
                                    <MessageBubble message={message} />
                                </React.Fragment>
                            );
                        })}
                    </div>
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

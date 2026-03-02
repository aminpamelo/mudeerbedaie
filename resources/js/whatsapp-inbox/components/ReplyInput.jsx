import React, { useState, useRef, useCallback } from 'react';

export default function ReplyInput({ onSend, sending, isServiceWindowOpen, onShowTemplatePicker }) {
    const [message, setMessage] = useState('');
    const textareaRef = useRef(null);
    const maxLength = 4096;

    const handleSend = useCallback(() => {
        if (!message.trim() || sending) return;
        onSend(message.trim());
        setMessage('');
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
        }
    }, [message, sending, onSend]);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    }, [handleSend]);

    const handleInput = useCallback((e) => {
        setMessage(e.target.value);
        const textarea = e.target;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }, []);

    if (!isServiceWindowOpen) {
        return (
            <div className="px-4 py-3 bg-[#f0f2f5] border-t border-zinc-200/50">
                <div className="flex items-center gap-3 p-3 rounded-xl bg-amber-50 border border-amber-200/70">
                    <div className="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                        <svg className="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs font-semibold text-amber-800">Tetingkap 24 jam telah tamat</p>
                        <p className="text-[11px] text-amber-600 mt-0.5">Gunakan templat untuk menghantar mesej baharu</p>
                    </div>
                    <button
                        onClick={onShowTemplatePicker}
                        className="shrink-0 px-3.5 py-1.5 text-xs font-semibold text-white bg-teal-600 rounded-lg hover:bg-teal-700 transition-colors shadow-sm"
                    >
                        Guna Templat
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-end gap-2 px-3 py-2.5 bg-[#f0f2f5] border-t border-zinc-200/50">
            {/* Template button */}
            <button
                onClick={onShowTemplatePicker}
                className="p-2 rounded-full hover:bg-black/5 transition-colors text-[#54656f] shrink-0 mb-[3px]"
                title="Hantar templat"
            >
                <svg className="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                </svg>
            </button>

            {/* Textarea */}
            <div className="flex-1 relative">
                <textarea
                    ref={textareaRef}
                    value={message}
                    onChange={handleInput}
                    onKeyDown={handleKeyDown}
                    placeholder="Taip mesej..."
                    maxLength={maxLength}
                    rows={1}
                    disabled={sending}
                    className="w-full resize-none rounded-xl bg-white border-0 px-3.5 py-2.5 text-sm text-[#111b21] placeholder:text-[#667781] focus:outline-none focus:ring-1 focus:ring-teal-500/30 disabled:opacity-50 shadow-sm"
                    style={{ minHeight: '42px', maxHeight: '120px' }}
                />
                {message.length > 0 && (
                    <span className={`absolute bottom-1.5 right-3 text-[10px] ${
                        message.length > maxLength * 0.9 ? 'text-red-500 font-medium' : 'text-zinc-400'
                    }`}>
                        {message.length}/{maxLength}
                    </span>
                )}
            </div>

            {/* Send button */}
            <button
                onClick={handleSend}
                disabled={!message.trim() || sending}
                className="w-10 h-10 rounded-full bg-teal-600 text-white flex items-center justify-center hover:bg-teal-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0 shadow-sm"
                title="Hantar mesej"
            >
                {sending ? (
                    <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                ) : (
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                )}
            </button>
        </div>
    );
}

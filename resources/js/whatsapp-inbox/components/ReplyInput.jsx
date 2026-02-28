import React, { useState, useRef, useCallback } from 'react';

export default function ReplyInput({ onSend, sending, isServiceWindowOpen, onShowTemplatePicker }) {
    const [message, setMessage] = useState('');
    const textareaRef = useRef(null);
    const maxLength = 4096;

    const handleSend = useCallback(() => {
        if (!message.trim() || sending) return;
        onSend(message.trim());
        setMessage('');
        // Reset textarea height
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
        // Auto-resize textarea
        const textarea = e.target;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }, []);

    if (!isServiceWindowOpen) {
        return (
            <div className="px-4 py-3 border-t border-zinc-200 bg-white">
                <div className="flex items-center gap-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <svg className="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs text-amber-700">
                            Tetingkap perkhidmatan 24 jam telah tamat. Gunakan templat untuk menghantar mesej.
                        </p>
                    </div>
                    <button
                        onClick={onShowTemplatePicker}
                        className="shrink-0 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Guna Templat
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="px-4 py-3 border-t border-zinc-200 bg-white">
            <div className="flex items-end gap-2">
                {/* Template button */}
                <button
                    onClick={onShowTemplatePicker}
                    className="p-2 rounded-lg hover:bg-zinc-100 transition-colors text-zinc-500 hover:text-zinc-700 shrink-0 mb-0.5"
                    title="Hantar templat"
                >
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
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
                        className="w-full resize-none rounded-lg border border-zinc-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
                        style={{ minHeight: '38px', maxHeight: '120px' }}
                    />
                    {message.length > 0 && (
                        <span className={`absolute bottom-1 right-2 text-[10px] ${
                            message.length > maxLength * 0.9 ? 'text-red-500' : 'text-zinc-300'
                        }`}>
                            {message.length}/{maxLength}
                        </span>
                    )}
                </div>

                {/* Send button */}
                <button
                    onClick={handleSend}
                    disabled={!message.trim() || sending}
                    className="p-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0 mb-0.5"
                    title="Hantar mesej"
                >
                    {sending ? (
                        <svg className="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                    ) : (
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    )}
                </button>
            </div>
        </div>
    );
}

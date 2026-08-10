import { useState } from 'react';
import { Bot, X, Send, Loader2, Sparkles } from 'lucide-react';
import { workspaceSend, workspaceJson } from '@/workspace/lib/api';

export default function AiAssistant() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]);
    const [busy, setBusy] = useState(false);

    const loadSummary = async () => {
        setBusy(true);
        try {
            const res = await workspaceJson('/workspace/ai/summary');
            const lines = res.data?.summary ?? ['No data.'];
            setMessages(m => [...m, { role: 'assistant', text: lines.join('\n') }]);
        } catch {
            setMessages(m => [...m, { role: 'assistant', text: 'Failed to load summary.' }]);
        } finally {
            setBusy(false);
        }
    };

    const decompose = async () => {
        if (!input.trim()) return;
        const q = input.trim();
        setMessages(m => [...m, { role: 'user', text: q }]);
        setInput('');
        setBusy(true);
        try {
            const res = await workspaceSend('/workspace/ai/decompose', { body: { title: q } });
            const subtasks = res.data ?? [];
            const text = subtasks.length > 0
                ? 'Cadangan subtask:\n' + subtasks.map((s, i) => `${i + 1}. ${s.title} (${s.priority})`).join('\n')
                : 'Tiada cadangan.';
            setMessages(m => [...m, { role: 'assistant', text }]);
        } catch (e) {
            setMessages(m => [...m, { role: 'assistant', text: `Error: ${e.message}` }]);
        } finally {
            setBusy(false);
        }
    };

    if (!open) {
        return (
            <button
                onClick={() => { setOpen(true); if (messages.length === 0) loadSummary(); }}
                className="fixed bottom-6 right-6 z-50 grid h-14 w-14 place-items-center rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow-xl shadow-indigo-500/30 transition hover:scale-105 hover:shadow-2xl"
            >
                <Bot className="h-6 w-6" />
            </button>
        );
    }

    return (
        <div className="fixed bottom-6 right-6 z-50 flex h-[480px] w-[360px] flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div className="flex items-center gap-2">
                    <div className="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
                        <Sparkles className="h-4 w-4" />
                    </div>
                    <span className="text-[14px] font-bold text-slate-900">AI Assistant</span>
                </div>
                <button
                    onClick={() => setOpen(false)}
                    className="grid h-7 w-7 place-items-center rounded-lg text-slate-400 hover:bg-slate-100"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                {messages.map((m, i) => (
                    <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div
                            className={`max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-[13px] ${
                                m.role === 'user'
                                    ? 'bg-gradient-to-r from-indigo-500 to-violet-500 text-white'
                                    : 'bg-slate-100 text-slate-700'
                            }`}
                        >
                            {m.text}
                        </div>
                    </div>
                ))}
                {busy && (
                    <div className="flex justify-start">
                        <div className="rounded-2xl bg-slate-100 px-4 py-3">
                            <Loader2 className="h-4 w-4 animate-spin text-slate-400" />
                        </div>
                    </div>
                )}
            </div>

            <div className="border-t border-slate-100 px-3 py-3">
                <div className="flex items-end gap-2">
                    <textarea
                        value={input}
                        onChange={e => setInput(e.target.value)}
                        onKeyDown={e => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                decompose();
                            }
                        }}
                        placeholder="Describe a project to decompose..."
                        rows={2}
                        className="flex-1 resize-none rounded-xl border border-slate-200 px-3 py-2 text-[13px] outline-none focus:border-indigo-400"
                    />
                    <button
                        onClick={decompose}
                        disabled={busy || !input.trim()}
                        className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow transition disabled:opacity-40"
                    >
                        <Send className="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

import React, { useState, useEffect, useCallback, useRef } from 'react';
import ConversationList from './components/ConversationList';
import ChatPanel from './components/ChatPanel';
import TemplatePicker from './components/TemplatePicker';

export default function App({ csrfToken, apiBase }) {
    const [conversations, setConversations] = useState([]);
    const [selectedConversation, setSelectedConversation] = useState(null);
    const [messages, setMessages] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [loadingConversations, setLoadingConversations] = useState(true);
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [sending, setSending] = useState(false);
    const [showTemplatePicker, setShowTemplatePicker] = useState(false);
    const [pagination, setPagination] = useState(null);
    const selectedConversationRef = useRef(null);

    const apiFetch = useCallback(async (path, options = {}) => {
        const url = `${apiBase}${path}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            ...options.headers,
        };
        const response = await fetch(url, { ...options, headers, credentials: 'same-origin' });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `HTTP ${response.status}`);
        }
        return response.json();
    }, [apiBase, csrfToken]);

    const fetchConversations = useCallback(async () => {
        try {
            const params = new URLSearchParams();
            if (searchQuery) params.set('search', searchQuery);
            if (statusFilter) params.set('status', statusFilter);
            const data = await apiFetch(`/conversations?${params.toString()}`);
            setConversations(data.data || []);
            setPagination({
                current_page: data.current_page,
                last_page: data.last_page,
                total: data.total,
            });
        } catch (err) {
            console.error('Gagal memuatkan perbualan:', err);
        } finally {
            setLoadingConversations(false);
        }
    }, [apiFetch, searchQuery, statusFilter]);

    const fetchMessages = useCallback(async (conversationId) => {
        if (!conversationId) return;
        try {
            const data = await apiFetch(`/conversations/${conversationId}`);
            setMessages((data.messages?.data || []).reverse());
            // Update conversation data for the selected conversation
            if (data.conversation) {
                setSelectedConversation(prev => prev ? { ...prev, ...data.conversation, unread_count: 0 } : prev);
                // Also update in the list
                setConversations(prev =>
                    prev.map(c => c.id === conversationId ? { ...c, unread_count: 0 } : c)
                );
            }
        } catch (err) {
            console.error('Gagal memuatkan mesej:', err);
        } finally {
            setLoadingMessages(false);
        }
    }, [apiFetch]);

    // Initial fetch and polling for conversations
    useEffect(() => {
        fetchConversations();
        const interval = setInterval(fetchConversations, 5000);
        return () => clearInterval(interval);
    }, [fetchConversations]);

    // Fetch messages when conversation selected + polling
    useEffect(() => {
        selectedConversationRef.current = selectedConversation?.id;
        if (!selectedConversation) return;
        setLoadingMessages(true);
        fetchMessages(selectedConversation.id);
        const interval = setInterval(() => {
            if (selectedConversationRef.current) {
                fetchMessages(selectedConversationRef.current);
            }
        }, 3000);
        return () => clearInterval(interval);
    }, [selectedConversation?.id, fetchMessages]);

    const handleSelectConversation = useCallback((conversation) => {
        setSelectedConversation(conversation);
        setMessages([]);
    }, []);

    const handleSendReply = useCallback(async (message) => {
        if (!selectedConversation || !message.trim()) return;
        setSending(true);
        try {
            const data = await apiFetch(`/conversations/${selectedConversation.id}/reply`, {
                method: 'POST',
                body: JSON.stringify({ message }),
            });
            if (data.success && data.message) {
                setMessages(prev => [...prev, data.message]);
                // Update conversation preview
                setConversations(prev =>
                    prev.map(c =>
                        c.id === selectedConversation.id
                            ? { ...c, last_message_preview: message, last_message_at: new Date().toISOString() }
                            : c
                    )
                );
            }
        } catch (err) {
            console.error('Gagal menghantar mesej:', err);
            alert('Gagal menghantar mesej. Sila cuba lagi.');
        } finally {
            setSending(false);
        }
    }, [apiFetch, selectedConversation]);

    const handleSendTemplate = useCallback(async (templateName, language, components = []) => {
        if (!selectedConversation) return;
        setSending(true);
        try {
            const data = await apiFetch(`/conversations/${selectedConversation.id}/template`, {
                method: 'POST',
                body: JSON.stringify({ template_name: templateName, language, components }),
            });
            if (data.success && data.message) {
                setMessages(prev => [...prev, data.message]);
                setShowTemplatePicker(false);
            }
        } catch (err) {
            console.error('Gagal menghantar templat:', err);
            alert('Gagal menghantar templat. Sila cuba lagi.');
        } finally {
            setSending(false);
        }
    }, [apiFetch, selectedConversation]);

    const handleArchive = useCallback(async () => {
        if (!selectedConversation) return;
        if (!confirm('Adakah anda pasti mahu mengarkibkan perbualan ini?')) return;
        try {
            await apiFetch(`/conversations/${selectedConversation.id}/archive`, {
                method: 'POST',
            });
            setSelectedConversation(null);
            setMessages([]);
            fetchConversations();
        } catch (err) {
            console.error('Gagal mengarkibkan:', err);
        }
    }, [apiFetch, selectedConversation, fetchConversations]);

    return (
        <div className="flex h-[calc(100vh-200px)] rounded-lg border border-zinc-200 bg-white overflow-hidden">
            {/* Left Panel - Conversation List */}
            <div className={`w-full md:w-96 shrink-0 border-r border-zinc-200 flex flex-col ${selectedConversation ? 'hidden md:flex' : 'flex'}`}>
                <ConversationList
                    conversations={conversations}
                    selectedId={selectedConversation?.id}
                    searchQuery={searchQuery}
                    statusFilter={statusFilter}
                    loading={loadingConversations}
                    onSelect={handleSelectConversation}
                    onSearchChange={setSearchQuery}
                    onStatusFilterChange={setStatusFilter}
                />
            </div>

            {/* Right Panel - Chat */}
            <div className={`flex-1 flex flex-col min-w-0 ${!selectedConversation ? 'hidden md:flex' : 'flex'}`}>
                {selectedConversation ? (
                    <ChatPanel
                        conversation={selectedConversation}
                        messages={messages}
                        loading={loadingMessages}
                        sending={sending}
                        onSendReply={handleSendReply}
                        onArchive={handleArchive}
                        onBack={() => setSelectedConversation(null)}
                        onShowTemplatePicker={() => setShowTemplatePicker(true)}
                    />
                ) : (
                    <div className="flex-1 flex items-center justify-center text-zinc-400">
                        <div className="text-center">
                            <svg className="w-16 h-16 mx-auto mb-4 text-zinc-300" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                            <p className="text-lg font-medium text-zinc-500">Pilih perbualan</p>
                            <p className="text-sm text-zinc-400 mt-1">Pilih perbualan dari senarai untuk mula berbual</p>
                        </div>
                    </div>
                )}
            </div>

            {/* Template Picker Modal */}
            {showTemplatePicker && (
                <TemplatePicker
                    apiBase={apiBase}
                    csrfToken={csrfToken}
                    onSelect={handleSendTemplate}
                    onClose={() => setShowTemplatePicker(false)}
                    sending={sending}
                />
            )}
        </div>
    );
}

import React, { useState, useEffect, useCallback, useRef } from 'react';
import ConversationList from './components/ConversationList';
import ChatPanel from './components/ChatPanel';
import TemplatePicker from './components/TemplatePicker';
import useNotificationSound from './hooks/useNotificationSound';

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
    const [messagePagination, setMessagePagination] = useState(null);
    const [loadingMoreMessages, setLoadingMoreMessages] = useState(false);
    const selectedConversationRef = useRef(null);
    const prevUnreadMapRef = useRef(null);
    const { muted, play, toggleMute } = useNotificationSound();

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
            const newConversations = data.data || [];

            // Detect new inbound messages by comparing unread counts
            if (prevUnreadMapRef.current) {
                const currentSelectedId = selectedConversationRef.current;
                const hasNewInbound = newConversations.some(c => {
                    if (c.id === currentSelectedId) return false;
                    const prev = prevUnreadMapRef.current.get(c.id);
                    return prev !== undefined && c.unread_count > prev;
                });
                if (hasNewInbound) {
                    play();
                }
            }

            // Update the unread map for next comparison
            prevUnreadMapRef.current = new Map(
                newConversations.map(c => [c.id, c.unread_count])
            );

            setConversations(newConversations);
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
    }, [apiFetch, searchQuery, statusFilter, play]);

    const fetchMessages = useCallback(async (conversationId) => {
        if (!conversationId) return;
        try {
            const data = await apiFetch(`/conversations/${conversationId}`);
            setMessages((data.messages?.data || []).reverse());
            setMessagePagination({
                current_page: data.messages?.current_page,
                last_page: data.messages?.last_page,
            });
            if (data.conversation) {
                setSelectedConversation(prev => prev ? { ...prev, ...data.conversation, unread_count: 0 } : prev);
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

    const loadMoreMessages = useCallback(async () => {
        if (!selectedConversation || !messagePagination || loadingMoreMessages) return;
        if (messagePagination.current_page >= messagePagination.last_page) return;
        setLoadingMoreMessages(true);
        try {
            const nextPage = messagePagination.current_page + 1;
            const data = await apiFetch(`/conversations/${selectedConversation.id}?page=${nextPage}`);
            const olderMessages = (data.messages?.data || []).reverse();
            setMessages(prev => [...olderMessages, ...prev]);
            setMessagePagination({
                current_page: data.messages?.current_page,
                last_page: data.messages?.last_page,
            });
        } catch (err) {
            console.error('Gagal memuatkan mesej lama:', err);
        } finally {
            setLoadingMoreMessages(false);
        }
    }, [apiFetch, selectedConversation, messagePagination, loadingMoreMessages]);

    useEffect(() => {
        fetchConversations();
        const interval = setInterval(fetchConversations, 5000);
        return () => clearInterval(interval);
    }, [fetchConversations]);

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
        <div className="flex h-[calc(100vh-180px)] rounded-xl overflow-hidden shadow-sm border border-zinc-200/70">
            {/* Left Panel — Conversation List */}
            <div className={`w-full md:w-[340px] lg:w-[380px] shrink-0 border-r border-zinc-200/70 flex flex-col bg-white ${selectedConversation ? 'hidden md:flex' : 'flex'}`}>
                <ConversationList
                    conversations={conversations}
                    selectedId={selectedConversation?.id}
                    searchQuery={searchQuery}
                    statusFilter={statusFilter}
                    loading={loadingConversations}
                    muted={muted}
                    onSelect={handleSelectConversation}
                    onSearchChange={setSearchQuery}
                    onStatusFilterChange={setStatusFilter}
                    onToggleMute={toggleMute}
                />
            </div>

            {/* Right Panel — Chat */}
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
                        hasMoreMessages={messagePagination && messagePagination.current_page < messagePagination.last_page}
                        loadingMoreMessages={loadingMoreMessages}
                        onLoadMoreMessages={loadMoreMessages}
                    />
                ) : (
                    <div className="flex-1 flex items-center justify-center bg-[#f0f2f5]">
                        <div className="text-center px-8">
                            <div className="w-[180px] h-[180px] mx-auto mb-5 opacity-90">
                                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="100" cy="100" r="85" fill="#00A884" opacity="0.06" />
                                    <circle cx="100" cy="100" r="60" fill="#00A884" opacity="0.08" />
                                    <path d="M100 52C76.8 52 58 69.2 58 90.5C58 100.8 62.4 110 69.5 116.6L66 138L89.6 124.3C92.8 124.9 96.1 125.2 99.5 125.2C122.7 125.2 141.5 108 141.5 86.7C141.5 65.4 123.2 52 100 52Z" fill="#00A884" opacity="0.75" />
                                    <circle cx="83" cy="88" r="4.5" fill="white" />
                                    <circle cx="100" cy="88" r="4.5" fill="white" />
                                    <circle cx="117" cy="88" r="4.5" fill="white" />
                                </svg>
                            </div>
                            <h3 className="text-xl font-light text-[#41525d] tracking-tight">
                                WhatsApp Inbox
                            </h3>
                            <p className="text-[13px] text-[#667781] mt-2.5 max-w-xs mx-auto leading-relaxed">
                                Pilih perbualan dari senarai untuk mula berbual dengan pelajar dan ibu bapa
                            </p>
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

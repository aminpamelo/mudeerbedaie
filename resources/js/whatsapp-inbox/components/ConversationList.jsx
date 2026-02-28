import React from 'react';
import ServiceWindowBadge from './ServiceWindowBadge';

function formatTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return date.toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' });
    }
    if (diffDays === 1) return 'Semalam';
    if (diffDays < 7) return date.toLocaleDateString('ms-MY', { weekday: 'short' });
    return date.toLocaleDateString('ms-MY', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
}

export default function ConversationList({
    conversations,
    selectedId,
    searchQuery,
    statusFilter,
    loading,
    onSelect,
    onSearchChange,
    onStatusFilterChange,
}) {
    const statusTabs = [
        { value: '', label: 'Semua' },
        { value: 'active', label: 'Aktif' },
        { value: 'archived', label: 'Arkib' },
    ];

    return (
        <>
            {/* Search */}
            <div className="p-3 border-b border-zinc-200">
                <div className="relative">
                    <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={e => onSearchChange(e.target.value)}
                        placeholder="Cari nombor atau nama..."
                        className="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-zinc-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>

                {/* Status Filter Tabs */}
                <div className="flex gap-1 mt-2">
                    {statusTabs.map(tab => (
                        <button
                            key={tab.value}
                            onClick={() => onStatusFilterChange(tab.value)}
                            className={`px-3 py-1 text-xs rounded-full font-medium transition-colors ${
                                statusFilter === tab.value
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Conversation Items */}
            <div className="flex-1 overflow-y-auto whatsapp-scrollbar">
                {loading ? (
                    <div className="flex items-center justify-center h-32">
                        <svg className="w-5 h-5 animate-spin text-zinc-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                    </div>
                ) : conversations.length === 0 ? (
                    <div className="flex items-center justify-center h-32 text-sm text-zinc-400">
                        Tiada perbualan dijumpai
                    </div>
                ) : (
                    conversations.map(conversation => (
                        <button
                            key={conversation.id}
                            onClick={() => onSelect(conversation)}
                            className={`w-full text-left px-3 py-3 border-b border-zinc-100 hover:bg-zinc-50 transition-colors ${
                                selectedId === conversation.id ? 'bg-blue-50' : ''
                            }`}
                        >
                            <div className="flex items-start gap-3">
                                {/* Avatar */}
                                <div className="w-10 h-10 rounded-full bg-zinc-200 flex items-center justify-center shrink-0 text-xs font-semibold text-zinc-600">
                                    {getInitials(conversation.contact_name || conversation.student_name)}
                                </div>

                                {/* Content */}
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-zinc-900 truncate">
                                            {conversation.contact_name || conversation.phone_number}
                                        </span>
                                        <span className="text-xs text-zinc-400 shrink-0 ml-2">
                                            {formatTime(conversation.last_message_at)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between mt-0.5">
                                        <p className="text-xs text-zinc-500 truncate">
                                            {conversation.student_name && (
                                                <span className="text-blue-600 font-medium">{conversation.student_name} &middot; </span>
                                            )}
                                            {conversation.last_message_preview || 'Tiada mesej'}
                                        </p>
                                        <div className="flex items-center gap-1.5 shrink-0 ml-2">
                                            {conversation.is_service_window_open && (
                                                <span className="w-2 h-2 rounded-full bg-green-500" title="Tetingkap perkhidmatan aktif"></span>
                                            )}
                                            {conversation.unread_count > 0 && (
                                                <span className="min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-blue-600 text-white text-[10px] font-bold px-1">
                                                    {conversation.unread_count}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    ))
                )}
            </div>
        </>
    );
}

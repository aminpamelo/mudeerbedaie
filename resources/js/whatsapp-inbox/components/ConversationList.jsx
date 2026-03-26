import React from 'react';
import { getAvatarColor, getInitials } from '../utils';

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

export default function ConversationList({
    conversations,
    selectedId,
    searchQuery,
    statusFilter,
    loading,
    muted,
    onSelect,
    onSearchChange,
    onStatusFilterChange,
    onToggleMute,
    hasMoreConversations,
    loadingMoreConversations,
    onLoadMoreConversations,
}) {
    const statusTabs = [
        { value: '', label: 'Semua' },
        { value: 'active', label: 'Aktif' },
        { value: 'archived', label: 'Arkib' },
    ];

    return (
        <>
            {/* Panel Header */}
            <div className="px-4 pt-4 pb-1 flex items-center justify-between">
                <h2 className="text-base font-bold text-[#111b21]">Perbualan</h2>
                <button
                    onClick={onToggleMute}
                    className="p-1.5 rounded-full hover:bg-[#f0f2f5] transition-colors text-[#54656f]"
                    title={muted ? 'Bunyikan notifikasi' : 'Senyapkan notifikasi'}
                >
                    {muted ? (
                        <svg className="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.9 23.9 0 0 0 3.882-1.545m-3.882 1.545-1.78-1.78m8.005-5.995h-1.5m0 0-.712.712m.712-.712-1.276-1.276" />
                        </svg>
                    ) : (
                        <svg className="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    )}
                </button>
            </div>

            {/* Search + Filters */}
            <div className="px-3 pb-3 pt-2">
                <div className="relative">
                    <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#54656f]" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={e => onSearchChange(e.target.value)}
                        placeholder="Cari nombor atau nama..."
                        className="w-full pl-9 pr-3 py-[7px] text-sm rounded-lg bg-[#f0f2f5] border-0 placeholder:text-[#667781] focus:outline-none focus:ring-2 focus:ring-teal-500/25 focus:bg-white transition-all"
                    />
                </div>

                <div className="flex gap-1.5 mt-2.5">
                    {statusTabs.map(tab => (
                        <button
                            key={tab.value}
                            onClick={() => onStatusFilterChange(tab.value)}
                            className={`px-3 py-1 text-xs rounded-full font-medium transition-all ${
                                statusFilter === tab.value
                                    ? 'bg-teal-600 text-white shadow-sm'
                                    : 'text-[#54656f] hover:bg-[#f0f2f5]'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Conversation Items */}
            <div className="flex-1 overflow-y-auto wa-scroll">
                {loading ? (
                    <div className="flex items-center justify-center h-32">
                        <div className="w-5 h-5 border-2 border-teal-200 border-t-teal-600 rounded-full animate-spin" />
                    </div>
                ) : conversations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-40 text-sm text-[#667781]">
                        <svg className="w-10 h-10 mb-2 text-zinc-300" fill="none" viewBox="0 0 24 24" strokeWidth="1" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                        <span>Tiada perbualan dijumpai</span>
                    </div>
                ) : (
                    <>
                        {conversations.map(conversation => {
                            const isSelected = selectedId === conversation.id;
                            const hasUnread = conversation.unread_count > 0;
                            const displayName = conversation.contact_name || conversation.phone_number;

                            return (
                                <button
                                    key={conversation.id}
                                    onClick={() => onSelect(conversation)}
                                    className={`wa-conv-item w-full text-left px-3 py-3 flex items-center gap-3 transition-colors border-l-[3px] ${
                                        isSelected
                                            ? 'bg-[#f0f2f5] border-l-teal-600'
                                            : 'border-l-transparent hover:bg-[#f5f6f6]'
                                    }`}
                                >
                                    {/* Avatar */}
                                    <div className={`w-[46px] h-[46px] rounded-full bg-gradient-to-br ${getAvatarColor(displayName)} flex items-center justify-center shrink-0 text-[13px] font-semibold text-white shadow-sm`}>
                                        {getInitials(conversation.contact_name || conversation.student_name)}
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between">
                                            <span className={`text-[15px] truncate ${hasUnread ? 'font-semibold text-[#111b21]' : 'font-normal text-[#111b21]'}`}>
                                                {displayName}
                                            </span>
                                            <span className={`text-[11px] shrink-0 ml-2 ${hasUnread ? 'text-teal-600 font-medium' : 'text-[#667781]'}`}>
                                                {formatTime(conversation.last_message_at)}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between mt-0.5">
                                            <p className={`text-[13px] truncate ${hasUnread ? 'text-[#111b21] font-medium' : 'text-[#667781]'}`}>
                                                {conversation.student_name && (
                                                    <span className="text-teal-600">{conversation.student_name} · </span>
                                                )}
                                                {conversation.last_message_preview || 'Tiada mesej'}
                                            </p>
                                            <div className="flex items-center gap-1.5 shrink-0 ml-2">
                                                {conversation.is_service_window_open && (
                                                    <span className="w-2 h-2 rounded-full bg-emerald-400 wa-pulse" />
                                                )}
                                                {hasUnread && (
                                                    <span className="min-w-[20px] h-[20px] flex items-center justify-center rounded-full bg-teal-600 text-white text-[10px] font-bold px-1.5">
                                                        {conversation.unread_count}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                        {hasMoreConversations && (
                            <div className="flex justify-center py-3">
                                <button
                                    onClick={onLoadMoreConversations}
                                    disabled={loadingMoreConversations}
                                    className="px-4 py-2 text-xs font-medium text-teal-700 hover:bg-[#f0f2f5] rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {loadingMoreConversations ? (
                                        <span className="flex items-center gap-2">
                                            <span className="w-3 h-3 border-2 border-teal-200 border-t-teal-600 rounded-full animate-spin" />
                                            Memuatkan...
                                        </span>
                                    ) : 'Muatkan lagi perbualan'}
                                </button>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

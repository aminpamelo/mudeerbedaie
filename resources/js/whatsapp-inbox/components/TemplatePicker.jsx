import React, { useState, useEffect, useCallback } from 'react';

function getCategoryLabel(category) {
    const labels = {
        marketing: 'Pemasaran',
        utility: 'Utiliti',
        authentication: 'Pengesahan',
    };
    return labels[category] || category;
}

function getCategoryColor(category) {
    const colors = {
        marketing: 'bg-purple-100 text-purple-700',
        utility: 'bg-blue-100 text-blue-700',
        authentication: 'bg-green-100 text-green-700',
    };
    return colors[category] || 'bg-zinc-100 text-zinc-700';
}

function getComponentPreview(components) {
    if (!components || !Array.isArray(components)) return null;
    const bodyComponent = components.find(c => c.type === 'BODY');
    return bodyComponent?.text || null;
}

export default function TemplatePicker({ apiBase, csrfToken, onSelect, onClose, sending }) {
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [syncing, setSyncing] = useState(false);
    const [filterCategory, setFilterCategory] = useState('');

    const fetchTemplates = useCallback(async () => {
        try {
            const response = await fetch(`${apiBase}/templates`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            setTemplates(data.data || []);
        } catch (err) {
            console.error('Gagal memuatkan templat:', err);
        } finally {
            setLoading(false);
        }
    }, [apiBase, csrfToken]);

    const handleSync = useCallback(async () => {
        setSyncing(true);
        try {
            const response = await fetch(`${apiBase}/templates/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success) {
                fetchTemplates();
            } else {
                alert(data.message || 'Gagal menyegerakkan templat.');
            }
        } catch (err) {
            console.error('Gagal menyegerakkan templat:', err);
            alert('Gagal menyegerakkan templat.');
        } finally {
            setSyncing(false);
        }
    }, [apiBase, csrfToken, fetchTemplates]);

    useEffect(() => {
        fetchTemplates();
    }, [fetchTemplates]);

    const filteredTemplates = filterCategory
        ? templates.filter(t => t.category === filterCategory)
        : templates;

    const categories = [...new Set(templates.map(t => t.category))];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div
                className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-zinc-200">
                    <div>
                        <h3 className="text-base font-semibold text-zinc-900">Pilih Templat</h3>
                        <p className="text-xs text-zinc-500 mt-0.5">Pilih templat mesej yang diluluskan untuk dihantar</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleSync}
                            disabled={syncing}
                            className="px-3 py-1.5 text-xs font-medium text-zinc-600 bg-zinc-100 rounded-lg hover:bg-zinc-200 transition-colors disabled:opacity-50"
                        >
                            {syncing ? 'Menyegerakkan...' : 'Segerakkan'}
                        </button>
                        <button
                            onClick={onClose}
                            className="p-1 rounded hover:bg-zinc-100 transition-colors"
                        >
                            <svg className="w-5 h-5 text-zinc-500" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Category Filter */}
                {categories.length > 1 && (
                    <div className="flex gap-1 px-5 py-2 border-b border-zinc-100">
                        <button
                            onClick={() => setFilterCategory('')}
                            className={`px-2.5 py-1 text-xs rounded-full font-medium transition-colors ${
                                !filterCategory ? 'bg-blue-100 text-blue-700' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'
                            }`}
                        >
                            Semua
                        </button>
                        {categories.map(cat => (
                            <button
                                key={cat}
                                onClick={() => setFilterCategory(cat)}
                                className={`px-2.5 py-1 text-xs rounded-full font-medium transition-colors ${
                                    filterCategory === cat ? 'bg-blue-100 text-blue-700' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'
                                }`}
                            >
                                {getCategoryLabel(cat)}
                            </button>
                        ))}
                    </div>
                )}

                {/* Template List */}
                <div className="flex-1 overflow-y-auto px-5 py-3 whatsapp-scrollbar">
                    {loading ? (
                        <div className="flex items-center justify-center h-32">
                            <svg className="w-5 h-5 animate-spin text-zinc-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                        </div>
                    ) : filteredTemplates.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-32 text-sm text-zinc-400">
                            <p>Tiada templat dijumpai</p>
                            <p className="text-xs mt-1">Segerakkan templat dari Meta untuk mula</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {filteredTemplates.map(template => {
                                const preview = getComponentPreview(template.components);

                                return (
                                    <button
                                        key={template.id}
                                        onClick={() => onSelect(template.name, template.language, [])}
                                        disabled={sending}
                                        className="w-full text-left p-3 rounded-lg border border-zinc-200 hover:border-blue-300 hover:bg-blue-50/50 transition-colors disabled:opacity-50"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-medium text-zinc-900">{template.name}</span>
                                                    <span className={`px-1.5 py-0.5 text-[10px] font-medium rounded ${getCategoryColor(template.category)}`}>
                                                        {getCategoryLabel(template.category)}
                                                    </span>
                                                </div>
                                                {preview && (
                                                    <p className="text-xs text-zinc-500 mt-1 line-clamp-2">{preview}</p>
                                                )}
                                                <p className="text-[10px] text-zinc-400 mt-1">
                                                    Bahasa: {template.language}
                                                </p>
                                            </div>
                                            <svg className="w-4 h-4 text-zinc-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                            </svg>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

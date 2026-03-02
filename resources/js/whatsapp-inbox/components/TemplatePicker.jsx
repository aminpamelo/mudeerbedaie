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
        marketing: 'bg-purple-50 text-purple-700 border-purple-200/50',
        utility: 'bg-blue-50 text-blue-700 border-blue-200/50',
        authentication: 'bg-emerald-50 text-emerald-700 border-emerald-200/50',
    };
    return colors[category] || 'bg-zinc-50 text-zinc-700 border-zinc-200/50';
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={onClose}>
            <div
                className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col overflow-hidden"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 bg-[#f0f2f5] border-b border-zinc-200/50">
                    <div>
                        <h3 className="text-base font-bold text-[#111b21]">Pilih Templat</h3>
                        <p className="text-xs text-[#667781] mt-0.5">Pilih templat mesej yang diluluskan untuk dihantar</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleSync}
                            disabled={syncing}
                            className="px-3 py-1.5 text-xs font-medium text-teal-700 bg-teal-50 rounded-lg hover:bg-teal-100 transition-colors disabled:opacity-50 border border-teal-200/50"
                        >
                            {syncing ? (
                                <span className="flex items-center gap-1.5">
                                    <div className="w-3 h-3 border-[1.5px] border-teal-300 border-t-teal-600 rounded-full animate-spin" />
                                    Segerakkan...
                                </span>
                            ) : 'Segerakkan'}
                        </button>
                        <button
                            onClick={onClose}
                            className="p-1.5 rounded-full hover:bg-black/5 transition-colors"
                        >
                            <svg className="w-5 h-5 text-[#54656f]" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Category Filter */}
                {categories.length > 1 && (
                    <div className="flex gap-1.5 px-5 py-2.5 border-b border-zinc-100">
                        <button
                            onClick={() => setFilterCategory('')}
                            className={`px-3 py-1 text-xs rounded-full font-medium transition-all ${
                                !filterCategory ? 'bg-teal-600 text-white shadow-sm' : 'text-[#54656f] hover:bg-[#f0f2f5]'
                            }`}
                        >
                            Semua
                        </button>
                        {categories.map(cat => (
                            <button
                                key={cat}
                                onClick={() => setFilterCategory(cat)}
                                className={`px-3 py-1 text-xs rounded-full font-medium transition-all ${
                                    filterCategory === cat ? 'bg-teal-600 text-white shadow-sm' : 'text-[#54656f] hover:bg-[#f0f2f5]'
                                }`}
                            >
                                {getCategoryLabel(cat)}
                            </button>
                        ))}
                    </div>
                )}

                {/* Template List */}
                <div className="flex-1 overflow-y-auto px-5 py-3 wa-scroll">
                    {loading ? (
                        <div className="flex items-center justify-center h-32">
                            <div className="w-5 h-5 border-2 border-teal-200 border-t-teal-600 rounded-full animate-spin" />
                        </div>
                    ) : filteredTemplates.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-32 text-sm text-[#667781]">
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
                                        className="group w-full text-left p-3 rounded-xl border border-zinc-200/80 hover:border-teal-300 hover:bg-teal-50/30 transition-all disabled:opacity-50"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-semibold text-[#111b21]">{template.name}</span>
                                                    <span className={`px-1.5 py-0.5 text-[10px] font-medium rounded-full border ${getCategoryColor(template.category)}`}>
                                                        {getCategoryLabel(template.category)}
                                                    </span>
                                                </div>
                                                {preview && (
                                                    <p className="text-xs text-[#667781] mt-1 line-clamp-2">{preview}</p>
                                                )}
                                                <p className="text-[10px] text-zinc-400 mt-1">
                                                    {template.language.toUpperCase()}
                                                </p>
                                            </div>
                                            <div className="w-8 h-8 rounded-full bg-teal-50 flex items-center justify-center shrink-0 group-hover:bg-teal-100 transition-colors">
                                                <svg className="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                                </svg>
                                            </div>
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

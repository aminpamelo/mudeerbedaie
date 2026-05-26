/**
 * Manage Categories Modal
 * Inline CRUD for funnel categories.
 */

import React, { useState } from 'react';
import { categoryApi } from '../services/api';
import { CATEGORY_COLORS, getCategoryColorClasses } from '../config/categoryColors';

export default function ManageCategoriesModal({ categories, onClose, onChange }) {
    const [items, setItems] = useState(categories);
    const [newName, setNewName] = useState('');
    const [newColor, setNewColor] = useState('zinc');
    const [creating, setCreating] = useState(false);
    const [savingId, setSavingId] = useState(null);
    const [error, setError] = useState(null);

    const refresh = async () => {
        try {
            const response = await categoryApi.list();
            const next = response.data || [];
            setItems(next);
            if (onChange) onChange(next);
        } catch (err) {
            setError(err.message || 'Failed to refresh categories');
        }
    };

    const handleCreate = async (e) => {
        e.preventDefault();
        if (!newName.trim()) return;
        setCreating(true);
        setError(null);
        try {
            await categoryApi.create({ name: newName.trim(), color: newColor });
            setNewName('');
            setNewColor('zinc');
            await refresh();
        } catch (err) {
            setError(err.message || 'Failed to create category');
        } finally {
            setCreating(false);
        }
    };

    const handleUpdate = async (id, patch) => {
        setSavingId(id);
        setError(null);
        try {
            await categoryApi.update(id, patch);
            await refresh();
        } catch (err) {
            setError(err.message || 'Failed to update category');
        } finally {
            setSavingId(null);
        }
    };

    const handleDelete = async (id, name) => {
        if (!confirm(`Delete category "${name}"? Funnels in this category will become uncategorized.`)) return;
        setSavingId(id);
        setError(null);
        try {
            await categoryApi.delete(id);
            await refresh();
        } catch (err) {
            setError(err.message || 'Failed to delete category');
        } finally {
            setSavingId(null);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm dark:bg-black/60">
            <div className="w-full max-w-xl mx-4 rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                <div className="flex items-start justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <div>
                        <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-100">Manage Categories</h2>
                        <p className="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Organize your funnels into groups.</p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Close"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="px-6 py-5">
                    {error && (
                        <div className="mb-4 flex items-center justify-between rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            <span>{error}</span>
                            <button onClick={() => setError(null)} className="ml-3 text-red-400 hover:text-red-600">&times;</button>
                        </div>
                    )}

                    {/* Create form */}
                    <form onSubmit={handleCreate} className="mb-5 rounded-md border border-dashed border-zinc-300 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            New category
                        </label>
                        <div className="flex items-center gap-2">
                            <input
                                type="text"
                                value={newName}
                                onChange={(e) => setNewName(e.target.value)}
                                placeholder="e.g. Lead Magnets"
                                maxLength={80}
                                className="flex-1 rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 placeholder-zinc-400 outline-none focus:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                            />
                            <ColorPicker value={newColor} onChange={setNewColor} />
                            <button
                                type="submit"
                                disabled={creating || !newName.trim()}
                                className="rounded-md bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-zinc-800 disabled:opacity-40 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            >
                                {creating ? 'Adding...' : 'Add'}
                            </button>
                        </div>
                    </form>

                    {/* Existing categories */}
                    {items.length === 0 ? (
                        <div className="rounded-md border border-dashed border-zinc-200 py-8 text-center dark:border-zinc-700">
                            <p className="text-[13px] text-zinc-500 dark:text-zinc-400">No categories yet. Add your first one above.</p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-zinc-100 dark:divide-zinc-800">
                            {items.map((cat) => (
                                <CategoryRow
                                    key={cat.id}
                                    category={cat}
                                    busy={savingId === cat.id}
                                    onSave={(patch) => handleUpdate(cat.id, patch)}
                                    onDelete={() => handleDelete(cat.id, cat.name)}
                                />
                            ))}
                        </ul>
                    )}
                </div>

                <div className="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-zinc-200 px-3 py-1.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>
    );
}

function CategoryRow({ category, busy, onSave, onDelete }) {
    const [name, setName] = useState(category.name);
    const [color, setColor] = useState(category.color || 'zinc');
    const dirty = name.trim() !== category.name || color !== (category.color || 'zinc');

    const handleSave = () => {
        if (!dirty || !name.trim()) return;
        onSave({ name: name.trim(), color });
    };

    const colorClasses = getCategoryColorClasses(color);

    return (
        <li className="flex items-center gap-2 py-2.5">
            <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${colorClasses.dot}`} />
            <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                onBlur={handleSave}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        handleSave();
                    }
                }}
                maxLength={80}
                className="flex-1 rounded-md border border-transparent bg-transparent px-2 py-1 text-sm text-zinc-900 outline-none transition-colors hover:border-zinc-200 focus:border-zinc-300 focus:bg-white dark:text-zinc-100 dark:hover:border-zinc-700 dark:focus:border-zinc-600 dark:focus:bg-zinc-900"
            />
            <span className="text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500">{category.funnels_count} funnels</span>
            <ColorPicker value={color} onChange={(c) => { setColor(c); onSave({ name: name.trim(), color: c }); }} compact />
            <button
                type="button"
                onClick={onDelete}
                disabled={busy}
                className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-500 disabled:opacity-40 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                title="Delete category"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </li>
    );
}

function ColorPicker({ value, onChange, compact = false }) {
    const [open, setOpen] = useState(false);
    const current = getCategoryColorClasses(value);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className={`flex items-center gap-1 rounded-md border border-zinc-200 ${compact ? 'h-7 w-7 justify-center' : 'px-2 py-1.5'} bg-white text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800`}
                title="Color"
            >
                <span className={`h-3 w-3 rounded-full ${current.dot}`} />
                {!compact && <span className="text-xs">{current.label}</span>}
            </button>
            {open && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 top-full z-20 mt-1 grid w-44 grid-cols-4 gap-1.5 rounded-md border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        {CATEGORY_COLORS.map((c) => {
                            const cls = getCategoryColorClasses(c.value);
                            return (
                                <button
                                    key={c.value}
                                    type="button"
                                    onClick={() => { onChange(c.value); setOpen(false); }}
                                    className={`flex h-7 w-7 items-center justify-center rounded-md border transition-colors ${value === c.value ? 'border-zinc-400 dark:border-zinc-300' : 'border-transparent hover:border-zinc-300 dark:hover:border-zinc-600'}`}
                                    title={c.label}
                                >
                                    <span className={`h-3.5 w-3.5 rounded-full ${cls.dot}`} />
                                </button>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}

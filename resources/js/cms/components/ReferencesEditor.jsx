import { useState, useRef, useEffect, useCallback } from 'react';
import { X, Loader2, Search, Plus, FileText, Link as LinkIcon, ExternalLink } from 'lucide-react';
import { searchContentsForReference } from '../lib/api';
import { Input } from './ui/input';
import { Button } from './ui/button';

const STAGE_LABELS = {
    idea: 'Idea',
    shooting: 'Shooting',
    editing: 'Editing',
    posting: 'Posting',
    posted: 'Posted',
};

function InternalRow({ row, excludeId, onResolve, onRemove }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const debounceRef = useRef(null);
    const containerRef = useRef(null);

    const search = useCallback(async (q) => {
        setLoading(true);
        setShowDropdown(true);
        try {
            const response = await searchContentsForReference({
                q,
                exclude_id: excludeId || undefined,
            });
            setResults(Array.isArray(response.data) ? response.data : []);
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }, [excludeId]);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (!query.trim()) {
            setResults([]);
            setShowDropdown(false);
            return;
        }
        debounceRef.current = setTimeout(() => search(query), 300);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [query, search]);

    useEffect(() => {
        function handleClickOutside(e) {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setShowDropdown(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    if (row.referenced_content_id && row.referenced_content) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50/50 px-3 py-2">
                <FileText className="h-4 w-4 shrink-0 text-blue-500" />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-zinc-900">
                        {row.referenced_content.title}
                    </p>
                    <p className="text-xs text-zinc-500">
                        {STAGE_LABELS[row.referenced_content.stage] || row.referenced_content.stage}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onRemove}
                    className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-zinc-400 hover:bg-red-50 hover:text-red-500"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>
        );
    }

    return (
        <div ref={containerRef} className="relative">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onFocus={() => {
                            if (query.trim() && results.length > 0) setShowDropdown(true);
                        }}
                        placeholder="Search content by title..."
                        className="pl-9"
                    />
                </div>
                <button
                    type="button"
                    onClick={onRemove}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-zinc-400 hover:bg-red-50 hover:text-red-500"
                    aria-label="Remove reference"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            {showDropdown && (
                <div className="absolute z-20 mt-1 w-[calc(100%-2.75rem)] rounded-lg border border-zinc-200 bg-white shadow-lg">
                    {loading ? (
                        <div className="flex items-center justify-center gap-2 p-4 text-sm text-zinc-500">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Searching...
                        </div>
                    ) : results.length > 0 ? (
                        <ul className="max-h-48 overflow-auto py-1">
                            {results.map((c) => (
                                <li
                                    key={c.id}
                                    onClick={() => {
                                        onResolve({
                                            referenced_content_id: c.id,
                                            referenced_url: null,
                                            referenced_content: { id: c.id, title: c.title, stage: c.stage },
                                        });
                                        setQuery('');
                                        setResults([]);
                                        setShowDropdown(false);
                                    }}
                                    className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-zinc-50"
                                >
                                    <FileText className="h-4 w-4 shrink-0 text-blue-500" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-zinc-900">{c.title}</p>
                                        <p className="text-xs text-zinc-500">
                                            {STAGE_LABELS[c.stage] || c.stage}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="p-4 text-center text-sm text-zinc-500">No content found</p>
                    )}
                </div>
            )}
        </div>
    );
}

function ExternalRow({ row, onChange, onRemove, error }) {
    return (
        <div>
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <LinkIcon className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <Input
                        type="url"
                        value={row.referenced_url || ''}
                        onChange={(e) => onChange({ ...row, referenced_url: e.target.value })}
                        placeholder="https://www.tiktok.com/@creator/video/..."
                        className={`pl-9 ${error ? 'border-red-400 focus:border-red-500' : ''}`}
                    />
                </div>
                <button
                    type="button"
                    onClick={onRemove}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-zinc-400 hover:bg-red-50 hover:text-red-500"
                    aria-label="Remove reference"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

/**
 * Editor for content references (sources / inspiration).
 *
 * Each reference is either:
 *  - internal: { mode: 'internal', referenced_content_id, referenced_content?: {id,title,stage} }
 *  - external: { mode: 'external', referenced_url }
 *
 * `value` is an array of these row objects. `onChange` receives the updated array.
 * `excludeId` is the current content's id (so it cannot reference itself).
 * `errors` is an optional object keyed by row index for per-row errors.
 */
export default function ReferencesEditor({ value = [], onChange, excludeId = null, errors = {} }) {
    function updateRow(index, next) {
        const copy = [...value];
        copy[index] = next;
        onChange(copy);
    }

    function removeRow(index) {
        onChange(value.filter((_, i) => i !== index));
    }

    function addRow(mode) {
        const newRow =
            mode === 'internal'
                ? { mode: 'internal', referenced_content_id: null, referenced_url: null }
                : { mode: 'external', referenced_content_id: null, referenced_url: '' };
        onChange([...value, newRow]);
    }

    return (
        <div className="space-y-3">
            {value.length > 0 && (
                <div className="space-y-2">
                    {value.map((row, index) => (
                        <div key={index}>
                            {row.mode === 'internal' ? (
                                <InternalRow
                                    row={row}
                                    excludeId={excludeId}
                                    onResolve={(next) => updateRow(index, { ...row, ...next })}
                                    onRemove={() => removeRow(index)}
                                />
                            ) : (
                                <ExternalRow
                                    row={row}
                                    onChange={(next) => updateRow(index, next)}
                                    onRemove={() => removeRow(index)}
                                    error={errors[index]}
                                />
                            )}
                        </div>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" onClick={() => addRow('internal')}>
                    <Plus className="mr-1 h-4 w-4" /> Add internal reference
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => addRow('external')}>
                    <ExternalLink className="mr-1 h-4 w-4" /> Add external URL
                </Button>
            </div>

            {value.length === 0 && (
                <p className="text-xs text-zinc-500">
                    Optional. Link the content this idea is based on or referenced from.
                </p>
            )}
        </div>
    );
}

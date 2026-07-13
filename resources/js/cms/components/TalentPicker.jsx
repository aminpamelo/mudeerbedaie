import { useState, useRef, useEffect, useCallback } from 'react';
import { Loader2, Search, Plus } from 'lucide-react';
import { fetchLiveHosts } from '../lib/api';
import { Input } from './ui/input';

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Search-and-add widget for assigning live hosts as content talent.
 * The parent renders the current talent list (with remove controls) and
 * passes the already-assigned host ids via `excludeIds`.
 */
export default function TalentPicker({ excludeIds = [], onSelect, disabled = false }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const debounceRef = useRef(null);
    const containerRef = useRef(null);

    const search = useCallback(async (searchQuery) => {
        setLoading(true);

        try {
            const response = await fetchLiveHosts({ search: searchQuery, per_page: 10 });
            const hosts = response.data || response;
            setResults(Array.isArray(hosts) ? hosts : []);
        } catch {
            setResults([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!showDropdown) {
            return undefined;
        }

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => search(query), 300);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [query, showDropdown, search]);

    useEffect(() => {
        function handleClickOutside(event) {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const filteredResults = results.filter((host) => !excludeIds.includes(host.id));

    function handleSelect(host) {
        onSelect(host);
        setQuery('');
        setResults([]);
        setShowDropdown(false);
    }

    return (
        <div ref={containerRef} className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input
                value={query}
                disabled={disabled}
                onChange={(e) => setQuery(e.target.value)}
                onFocus={() => setShowDropdown(true)}
                placeholder="Search live hosts to add..."
                className="pl-9"
            />

            {showDropdown && (
                <div className="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg">
                    {loading ? (
                        <div className="flex items-center justify-center gap-2 p-4 text-sm text-slate-500">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Searching...
                        </div>
                    ) : filteredResults.length > 0 ? (
                        <ul className="max-h-56 overflow-auto py-1">
                            {filteredResults.map((host) => (
                                <li
                                    key={host.id}
                                    onClick={() => handleSelect(host)}
                                    className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50"
                                >
                                    {host.avatar_url ? (
                                        <img
                                            src={host.avatar_url}
                                            alt=""
                                            className="h-8 w-8 shrink-0 rounded-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-purple-100 text-xs font-medium text-purple-700">
                                            {getInitials(host.name)}
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-900">
                                            {host.name}
                                        </p>
                                        {host.email && (
                                            <p className="truncate text-xs text-slate-500">
                                                {host.email}
                                            </p>
                                        )}
                                    </div>
                                    <Plus className="h-4 w-4 shrink-0 text-slate-400" />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="p-4 text-center text-sm text-slate-500">
                            {results.length > 0 ? 'All matching hosts already added' : 'No live hosts found'}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

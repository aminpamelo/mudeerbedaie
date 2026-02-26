import React, { useState, useRef, useEffect } from 'react';
import countryCodes from '../data/countryCodes';

export default function CountryCodeSelect({ value, onChange }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const ref = useRef(null);
    const searchRef = useRef(null);

    const selected = countryCodes.find((c) => c.code === value) || countryCodes[0];

    useEffect(() => {
        function handleClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
                setSearch('');
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (open && searchRef.current) {
            searchRef.current.focus();
        }
    }, [open]);

    const filtered = countryCodes.filter((c) => {
        if (!search) return true;
        const q = search.toLowerCase();
        return (
            c.name.toLowerCase().includes(q) ||
            c.code.includes(q) ||
            c.country.toLowerCase().includes(q)
        );
    });

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => {
                    setOpen(!open);
                    setSearch('');
                }}
                className="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-700 text-sm font-medium hover:bg-gray-100 transition-colors focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <span>{selected.flag}</span>
                <span>{selected.code}</span>
                <svg className={`w-3.5 h-3.5 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {open && (
                <div className="absolute left-0 top-full mt-1 z-50 w-64 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                    <div className="p-2 border-b border-gray-100">
                        <input
                            ref={searchRef}
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari negara..."
                            className="w-full px-2.5 py-1.5 text-sm border border-gray-200 rounded-lg focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                        />
                    </div>
                    <ul className="max-h-48 overflow-y-auto py-1">
                        {filtered.length === 0 ? (
                            <li className="px-3 py-2 text-sm text-gray-400 text-center">
                                Tiada hasil
                            </li>
                        ) : (
                            filtered.map((c) => (
                                <li key={c.code + c.country}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onChange(c.code);
                                            setOpen(false);
                                            setSearch('');
                                        }}
                                        className={`w-full text-left px-3 py-2 text-sm flex items-center gap-2.5 hover:bg-indigo-50 transition-colors ${
                                            c.code === value ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700'
                                        }`}
                                    >
                                        <span className="text-base">{c.flag}</span>
                                        <span className="flex-1 truncate">{c.name}</span>
                                        <span className="text-gray-400 text-xs">{c.code}</span>
                                    </button>
                                </li>
                            ))
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

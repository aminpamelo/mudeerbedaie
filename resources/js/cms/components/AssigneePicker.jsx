import { useState, useRef, useEffect, useCallback } from 'react';
import { X, Loader2, Search, UserPlus } from 'lucide-react';
import { fetchEmployees } from '../lib/api';
import { Input } from './ui/input';

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export default function AssigneePicker({ assignees = [], onAssigneesChange }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const debounceRef = useRef(null);
    const containerRef = useRef(null);

    const searchEmployees = useCallback(
        async (searchQuery) => {
            if (!searchQuery.trim()) {
                setResults([]);
                setShowDropdown(false);
                return;
            }

            setLoading(true);
            setShowDropdown(true);

            try {
                const response = await fetchEmployees({
                    search: searchQuery,
                    per_page: 10,
                });
                const employees = response.data || response;
                setResults(Array.isArray(employees) ? employees : []);
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        },
        []
    );

    useEffect(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        if (!query.trim()) {
            setResults([]);
            setShowDropdown(false);
            return;
        }

        debounceRef.current = setTimeout(() => {
            searchEmployees(query);
        }, 300);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [query, searchEmployees]);

    useEffect(() => {
        function handleClickOutside(event) {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const assignedIds = assignees.map((a) => a.employee_id);

    const filteredResults = results.filter(
        (emp) => !assignedIds.includes(emp.id)
    );

    function addAssignee(employee) {
        const newAssignee = {
            employee_id: employee.id,
            full_name: employee.full_name,
            role: '',
        };
        onAssigneesChange([...assignees, newAssignee]);
        setQuery('');
        setResults([]);
        setShowDropdown(false);
    }

    function removeAssignee(employeeId) {
        onAssigneesChange(assignees.filter((a) => a.employee_id !== employeeId));
    }

    function updateRole(employeeId, role) {
        onAssigneesChange(
            assignees.map((a) =>
                a.employee_id === employeeId ? { ...a, role } : a
            )
        );
    }

    return (
        <div ref={containerRef} className="space-y-3">
            {/* Search input */}
            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onFocus={() => {
                        if (query.trim() && results.length > 0) {
                            setShowDropdown(true);
                        }
                    }}
                    placeholder="Search employees to assign..."
                    className="pl-9"
                />

                {/* Dropdown */}
                {showDropdown && (
                    <div className="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg">
                        {loading ? (
                            <div className="flex items-center justify-center gap-2 p-4 text-sm text-zinc-500">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Searching...
                            </div>
                        ) : filteredResults.length > 0 ? (
                            <ul className="max-h-48 overflow-auto py-1">
                                {filteredResults.map((employee) => (
                                    <li
                                        key={employee.id}
                                        onClick={() => addAssignee(employee)}
                                        className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-zinc-50"
                                    >
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-700">
                                            {getInitials(employee.full_name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-zinc-900">
                                                {employee.full_name}
                                            </p>
                                            <p className="text-xs text-zinc-500">
                                                {employee.employee_id || `ID: ${employee.id}`}
                                            </p>
                                        </div>
                                        <UserPlus className="h-4 w-4 shrink-0 text-zinc-400" />
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="p-4 text-center text-sm text-zinc-500">
                                No employees found
                            </p>
                        )}
                    </div>
                )}
            </div>

            {/* Assigned list */}
            {assignees.length > 0 && (
                <div className="space-y-2">
                    {assignees.map((assignee) => (
                        <div
                            key={assignee.employee_id}
                            className="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50/50 px-3 py-2"
                        >
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-medium text-slate-700">
                                {getInitials(assignee.full_name)}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-zinc-900">
                                    {assignee.full_name}
                                </p>
                            </div>
                            <input
                                type="text"
                                value={assignee.role || ''}
                                onChange={(e) =>
                                    updateRole(assignee.employee_id, e.target.value)
                                }
                                placeholder="Role (optional)"
                                className="h-8 w-36 rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-700 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            />
                            <button
                                type="button"
                                onClick={() => removeAssignee(assignee.employee_id)}
                                className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-zinc-400 hover:bg-red-50 hover:text-red-500"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

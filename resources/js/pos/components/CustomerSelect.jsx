import React, { useState, useEffect, useRef } from 'react';
import { customerApi } from '../services/api';

const MALAYSIAN_STATES = [
    'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
    'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
    'Sarawak', 'Selangor', 'Terengganu',
    'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
];

function formatAddress({ addressLine, city, state, postcode }) {
    const parts = [addressLine, city, postcode && state ? `${postcode} ${state}` : state || postcode].filter(Boolean);
    return parts.join(', ');
}

export default function CustomerSelect({ customer, onCustomerChange, postage = 0 }) {
    const [search, setSearch] = useState('');
    const [results, setResults] = useState([]);
    const [showDropdown, setShowDropdown] = useState(false);
    const [walkIn, setWalkIn] = useState({ name: '', phone: '', email: '', addressLine: '', city: '', state: '', postcode: '' });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});
    const [editingExisting, setEditingExisting] = useState(false);
    const [existingEdit, setExistingEdit] = useState({ name: '', phone: '', email: '', addressLine: '', city: '', state: '', postcode: '' });
    const [showAddress, setShowAddress] = useState(false);
    const [showExistingAddress, setShowExistingAddress] = useState(false);
    const dropdownRef = useRef(null);

    // Auto-expand address when postage is set
    useEffect(() => {
        if (postage > 0) {
            setShowAddress(true);
            setShowExistingAddress(true);
        }
    }, [postage]);

    useEffect(() => {
        if (search.length < 2) {
            setResults([]);
            return;
        }

        setLoading(true);
        const timer = setTimeout(async () => {
            try {
                const response = await customerApi.search(search);
                setResults(response.data || []);
            } catch (err) {
                console.error('Failed to search customers:', err);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setShowDropdown(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const selectCustomer = (c) => {
        onCustomerChange({ type: 'existing', id: c.id, name: c.name, email: c.email, phone: c.phone, address: c.address || '' });
        setExistingEdit({ name: c.name || '', phone: c.phone || '', email: c.email || '', addressLine: '', city: '', state: '', postcode: '' });
        setEditingExisting(false);
        setShowDropdown(false);
        setSearch('');
        setErrors({});
    };

    const handleWalkInChange = (field, value) => {
        const updated = { ...walkIn, [field]: value };
        setWalkIn(updated);

        // Clear error for this field when user types
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: null }));
        }

        // Auto-save walk-in to parent whenever name and phone are filled
        if (updated.name.trim() && updated.phone.trim()) {
            const address = formatAddress(updated);
            onCustomerChange({ type: 'walkin', name: updated.name, phone: updated.phone, email: updated.email, address });
        } else {
            // Clear customer if required fields become empty
            onCustomerChange(null);
        }
    };

    const validateWalkIn = () => {
        const newErrors = {};
        if (!walkIn.name.trim()) newErrors.name = 'Name is required';
        if (!walkIn.phone.trim()) newErrors.phone = 'Phone is required';
        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleExistingEditChange = (field, value) => {
        const updated = { ...existingEdit, [field]: value };
        setExistingEdit(updated);
        const address = formatAddress(updated);
        onCustomerChange({
            ...customer,
            name: updated.name,
            phone: updated.phone,
            email: updated.email,
            address,
        });
    };

    const clearCustomer = () => {
        onCustomerChange(null);
        setSearch('');
        setWalkIn({ name: '', phone: '', email: '', addressLine: '', city: '', state: '', postcode: '' });
        setErrors({});
    };

    // Show selected existing customer
    if (customer && customer.type === 'existing') {
        return (
            <div className="space-y-2">
                <div className="flex items-center justify-between bg-blue-50 rounded-lg px-3 py-2.5">
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-blue-900 truncate">{customer.name}</p>
                        <p className="text-xs text-blue-600 truncate">
                            {customer.phone}{customer.email ? ` | ${customer.email}` : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <button
                            onClick={() => setEditingExisting(!editingExisting)}
                            className="p-1 text-blue-400 hover:text-blue-600"
                            title={editingExisting ? 'Close edit' : 'Edit customer info'}
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {editingExisting ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                )}
                            </svg>
                        </button>
                        <button onClick={clearCustomer} className="p-1 text-blue-400 hover:text-blue-600">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                {editingExisting && (
                    <div className="space-y-2 pt-1">
                        <input
                            type="text"
                            value={existingEdit.name}
                            onChange={(e) => handleExistingEditChange('name', e.target.value)}
                            placeholder="Customer name"
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <input
                            type="tel"
                            value={existingEdit.phone}
                            onChange={(e) => handleExistingEditChange('phone', e.target.value)}
                            placeholder="Phone number"
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <input
                            type="email"
                            value={existingEdit.email}
                            onChange={(e) => handleExistingEditChange('email', e.target.value)}
                            placeholder="Email"
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <button
                            type="button"
                            onClick={() => setShowExistingAddress(!showExistingAddress)}
                            className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 font-medium py-1 transition-colors"
                        >
                            <svg className={`w-3.5 h-3.5 transition-transform ${showExistingAddress ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                            {showExistingAddress ? 'Hide address fields' : 'Add address (optional)'}
                        </button>
                        {showExistingAddress && (
                            <div className="space-y-2">
                                <input
                                    type="text"
                                    value={existingEdit.addressLine}
                                    onChange={(e) => handleExistingEditChange('addressLine', e.target.value)}
                                    placeholder="Address"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div className="grid grid-cols-2 gap-2">
                                    <input
                                        type="text"
                                        value={existingEdit.city}
                                        onChange={(e) => handleExistingEditChange('city', e.target.value)}
                                        placeholder="City"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                    <input
                                        type="text"
                                        value={existingEdit.postcode}
                                        onChange={(e) => handleExistingEditChange('postcode', e.target.value)}
                                        placeholder="Postcode"
                                        maxLength={5}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                                <select
                                    value={existingEdit.state}
                                    onChange={(e) => handleExistingEditChange('state', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white text-gray-700"
                                >
                                    <option value="">Select state</option>
                                    {MALAYSIAN_STATES.map(s => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {/* Search existing customer */}
            <div className="relative" ref={dropdownRef}>
                <div className="relative">
                    <svg className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setShowDropdown(true); }}
                        onFocus={() => search.length >= 2 && setShowDropdown(true)}
                        placeholder="Search existing student..."
                        className="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    {loading && (
                        <div className="absolute right-2 top-1/2 -translate-y-1/2">
                            <div className="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
                        </div>
                    )}
                </div>

                {showDropdown && results.length > 0 && (
                    <div className="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        {results.map(c => (
                            <button
                                key={c.id}
                                onClick={() => selectCustomer(c)}
                                className="w-full px-3 py-2 text-left hover:bg-gray-50 border-b border-gray-50 last:border-0"
                            >
                                <p className="text-sm font-medium text-gray-900">{c.name}</p>
                                <p className="text-xs text-gray-500">{c.phone || ''} {c.email ? `| ${c.email}` : ''}</p>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Divider */}
            <div className="flex items-center gap-2">
                <div className="flex-1 h-px bg-gray-200" />
                <span className="text-xs text-gray-400 font-medium">or enter customer info</span>
                <div className="flex-1 h-px bg-gray-200" />
            </div>

            {/* Walk-in customer form */}
            <div className="space-y-2">
                <div>
                    <input
                        type="text"
                        value={walkIn.name}
                        onChange={(e) => handleWalkInChange('name', e.target.value)}
                        placeholder="Customer name *"
                        className={`w-full px-3 py-2 border rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 ${
                            errors.name ? 'border-red-300 bg-red-50' : 'border-gray-200'
                        }`}
                    />
                    {errors.name && <p className="text-xs text-red-500 mt-0.5">{errors.name}</p>}
                </div>
                <div>
                    <input
                        type="tel"
                        value={walkIn.phone}
                        onChange={(e) => handleWalkInChange('phone', e.target.value)}
                        placeholder="Phone number *"
                        className={`w-full px-3 py-2 border rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 ${
                            errors.phone ? 'border-red-300 bg-red-50' : 'border-gray-200'
                        }`}
                    />
                    {errors.phone && <p className="text-xs text-red-500 mt-0.5">{errors.phone}</p>}
                </div>
                <input
                    type="email"
                    value={walkIn.email}
                    onChange={(e) => handleWalkInChange('email', e.target.value)}
                    placeholder="Email (optional)"
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                />
                <button
                    type="button"
                    onClick={() => setShowAddress(!showAddress)}
                    className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 font-medium py-1 transition-colors"
                >
                    <svg className={`w-3.5 h-3.5 transition-transform ${showAddress ? 'rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                    {showAddress ? 'Hide address fields' : 'Add address (optional)'}
                </button>
                {showAddress && (
                    <div className="space-y-2">
                        <input
                            type="text"
                            value={walkIn.addressLine}
                            onChange={(e) => handleWalkInChange('addressLine', e.target.value)}
                            placeholder="Address"
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <div className="grid grid-cols-2 gap-2">
                            <input
                                type="text"
                                value={walkIn.city}
                                onChange={(e) => handleWalkInChange('city', e.target.value)}
                                placeholder="City"
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                            />
                            <input
                                type="text"
                                value={walkIn.postcode}
                                onChange={(e) => handleWalkInChange('postcode', e.target.value)}
                                placeholder="Postcode"
                                maxLength={5}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                        <select
                            value={walkIn.state}
                            onChange={(e) => handleWalkInChange('state', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-1 focus:ring-blue-500 bg-white text-gray-700"
                        >
                            <option value="">Select state</option>
                            {MALAYSIAN_STATES.map(s => (
                                <option key={s} value={s}>{s}</option>
                            ))}
                        </select>
                    </div>
                )}
            </div>
        </div>
    );
}

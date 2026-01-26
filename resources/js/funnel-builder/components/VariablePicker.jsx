import React, { useState, useRef, useEffect, useMemo } from 'react';

/**
 * Variable definitions by category for merge tags.
 * This mirrors the backend VariableRegistry.php
 */
const VARIABLE_CATEGORIES = {
    contact: {
        label: 'Contact',
        icon: '👤',
        description: 'Customer/Contact information',
        variables: {
            'contact.name': { label: 'Full Name', example: 'John Doe', description: "Customer's full name" },
            'contact.first_name': { label: 'First Name', example: 'John', description: "Customer's first name" },
            'contact.last_name': { label: 'Last Name', example: 'Doe', description: "Customer's last name" },
            'contact.email': { label: 'Email', example: 'john@example.com', description: "Customer's email address" },
            'contact.phone': { label: 'Phone', example: '+60123456789', description: "Customer's phone number" },
        },
    },
    order: {
        label: 'Order',
        icon: '🛒',
        description: 'Order details and items',
        variables: {
            'order.number': { label: 'Order Number', example: 'PO-20260126-ABC123', description: 'Unique order reference' },
            'order.total': { label: 'Total Amount', example: 'RM 299.00', description: 'Order total with currency' },
            'order.total_raw': { label: 'Total (Number)', example: '299.00', description: 'Order total without currency' },
            'order.subtotal': { label: 'Subtotal', example: 'RM 279.00', description: 'Subtotal before discounts' },
            'order.currency': { label: 'Currency', example: 'MYR', description: 'Currency code' },
            'order.status': { label: 'Status', example: 'confirmed', description: 'Order status' },
            'order.items_count': { label: 'Items Count', example: '3', description: 'Number of items' },
            'order.items_list': { label: 'Items List', example: '- Product A (x1)\n- Product B (x2)', description: 'Formatted item list' },
            'order.first_item_name': { label: 'First Item', example: 'Premium Course', description: 'Name of first item' },
            'order.discount_amount': { label: 'Discount', example: 'RM 20.00', description: 'Total discount applied' },
            'order.coupon_code': { label: 'Coupon Code', example: 'SAVE20', description: 'Applied coupon code' },
            'order.date': { label: 'Order Date', example: '26 Jan 2026', description: 'Date order was placed' },
        },
    },
    payment: {
        label: 'Payment',
        icon: '💳',
        description: 'Payment transaction details',
        variables: {
            'payment.method': { label: 'Payment Method', example: 'FPX', description: 'Payment method used' },
            'payment.reference': { label: 'Reference', example: 'BC-123456', description: 'Payment gateway reference' },
            'payment.status': { label: 'Status', example: 'completed', description: 'Payment status' },
            'payment.paid_at': { label: 'Payment Date', example: '26 Jan 2026, 10:30 AM', description: 'Date and time of payment' },
            'payment.bank': { label: 'Bank Name', example: 'Maybank', description: 'Bank used for payment (FPX)' },
        },
    },
    cart: {
        label: 'Cart',
        icon: '🛍️',
        description: 'Shopping cart details',
        variables: {
            'cart.total': { label: 'Cart Total', example: 'RM 199.00', description: 'Current cart total' },
            'cart.items_count': { label: 'Items Count', example: '2', description: 'Number of items in cart' },
            'cart.items_list': { label: 'Items List', example: '- Product A\n- Product B', description: 'List of cart items' },
            'cart.first_item_name': { label: 'First Item', example: 'Premium Course', description: 'Name of first cart item' },
            'cart.checkout_url': { label: 'Checkout URL', example: 'https://example.com/checkout/abc', description: 'URL to resume checkout' },
            'cart.abandoned_at': { label: 'Abandoned Time', example: '2 hours ago', description: 'When cart was abandoned' },
        },
    },
    funnel: {
        label: 'Funnel',
        icon: '🎯',
        description: 'Funnel and step information',
        variables: {
            'funnel.name': { label: 'Funnel Name', example: 'Product Launch', description: 'Name of the funnel' },
            'funnel.url': { label: 'Funnel URL', example: 'https://example.com/f/launch', description: 'Public URL' },
            'funnel.step_name': { label: 'Step Name', example: 'Checkout', description: 'Current step name' },
            'funnel.step_url': { label: 'Step URL', example: 'https://example.com/f/launch/checkout', description: 'Current step URL' },
        },
    },
    session: {
        label: 'Session',
        icon: '🌐',
        description: 'Session and tracking data',
        variables: {
            'session.utm_source': { label: 'UTM Source', example: 'facebook', description: 'Traffic source' },
            'session.utm_medium': { label: 'UTM Medium', example: 'cpc', description: 'Traffic medium' },
            'session.utm_campaign': { label: 'UTM Campaign', example: 'summer_sale', description: 'Campaign name' },
            'session.utm_content': { label: 'UTM Content', example: 'banner_ad', description: 'Ad content' },
            'session.utm_term': { label: 'UTM Term', example: 'buy+course', description: 'Search term' },
            'session.device': { label: 'Device Type', example: 'mobile', description: 'Device type' },
            'session.browser': { label: 'Browser', example: 'Chrome', description: 'Browser name' },
            'session.country': { label: 'Country', example: 'MY', description: 'Country code' },
            'session.referrer': { label: 'Referrer', example: 'google.com', description: 'Referring website' },
        },
    },
    system: {
        label: 'System',
        icon: '⚙️',
        description: 'Date, time and system info',
        variables: {
            'current_date': { label: 'Current Date', example: '26 Jan 2026', description: "Today's date" },
            'current_time': { label: 'Current Time', example: '10:30 AM', description: 'Current time' },
            'current_datetime': { label: 'Date & Time', example: '26 Jan 2026, 10:30 AM', description: 'Current date and time' },
            'current_year': { label: 'Year', example: '2026', description: 'Current year' },
            'current_month': { label: 'Month', example: 'January', description: 'Current month name' },
            'current_day': { label: 'Day', example: 'Monday', description: 'Current day of week' },
            'company_name': { label: 'Company Name', example: 'Your Company', description: 'Business name' },
            'company_email': { label: 'Company Email', example: 'support@example.com', description: 'Contact email' },
            'company_phone': { label: 'Company Phone', example: '+60123456789', description: 'Contact phone' },
        },
    },
};

/**
 * Variables available per trigger type
 */
const VARIABLES_BY_TRIGGER = {
    purchase_completed: ['contact', 'order', 'payment', 'funnel', 'session', 'system'],
    funnel_purchase_completed: ['contact', 'order', 'payment', 'funnel', 'session', 'system'],
    order_paid: ['contact', 'order', 'payment', 'funnel', 'session', 'system'],
    purchase_failed: ['contact', 'order', 'funnel', 'session', 'system'],
    funnel_purchase_failed: ['contact', 'order', 'funnel', 'session', 'system'],
    order_failed: ['contact', 'order', 'funnel', 'session', 'system'],
    cart_abandoned: ['contact', 'cart', 'funnel', 'session', 'system'],
    funnel_cart_abandoned: ['contact', 'cart', 'funnel', 'session', 'system'],
    cart_abandonment: ['contact', 'cart', 'funnel', 'session', 'system'],
    optin_submitted: ['contact', 'funnel', 'session', 'system'],
    form_submitted: ['contact', 'funnel', 'session', 'system'],
    page_view: ['contact', 'funnel', 'session', 'system'],
    default: ['contact', 'system'],
};

/**
 * Get available variables for a trigger type
 */
export function getVariablesForTrigger(triggerType) {
    const categories = VARIABLES_BY_TRIGGER[triggerType] || VARIABLES_BY_TRIGGER.default;
    const result = {};

    categories.forEach(category => {
        if (VARIABLE_CATEGORIES[category]) {
            result[category] = VARIABLE_CATEGORIES[category];
        }
    });

    return result;
}

/**
 * VariablePicker Component
 *
 * A dropdown component for selecting and inserting merge tags into text fields.
 *
 * @param {Object} props
 * @param {Function} props.onSelect - Callback when a variable is selected, receives the variable tag (e.g., "{{contact.name}}")
 * @param {string} props.triggerType - The trigger type to filter available variables
 * @param {string} props.buttonText - Text to display on the trigger button
 * @param {string} props.buttonClassName - Additional classes for the button
 * @param {boolean} props.showPreview - Whether to show example values in the dropdown
 */
export default function VariablePicker({
    onSelect,
    triggerType = 'default',
    buttonText = 'Insert Variable',
    buttonClassName = '',
    showPreview = true,
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState(null);
    const dropdownRef = useRef(null);
    const searchInputRef = useRef(null);

    // Get available variables based on trigger type
    const availableVariables = useMemo(() => {
        return getVariablesForTrigger(triggerType);
    }, [triggerType]);

    // Filter variables based on search query
    const filteredVariables = useMemo(() => {
        if (!searchQuery.trim()) {
            return availableVariables;
        }

        const query = searchQuery.toLowerCase();
        const filtered = {};

        Object.entries(availableVariables).forEach(([categoryKey, category]) => {
            const matchingVars = {};

            Object.entries(category.variables).forEach(([varKey, varData]) => {
                if (
                    varKey.toLowerCase().includes(query) ||
                    varData.label.toLowerCase().includes(query) ||
                    varData.description.toLowerCase().includes(query)
                ) {
                    matchingVars[varKey] = varData;
                }
            });

            if (Object.keys(matchingVars).length > 0) {
                filtered[categoryKey] = {
                    ...category,
                    variables: matchingVars,
                };
            }
        });

        return filtered;
    }, [availableVariables, searchQuery]);

    // Handle click outside to close dropdown
    useEffect(() => {
        function handleClickOutside(event) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Focus search input when dropdown opens
    useEffect(() => {
        if (isOpen && searchInputRef.current) {
            searchInputRef.current.focus();
        }
    }, [isOpen]);

    // Handle variable selection
    const handleSelect = (variableKey) => {
        const tag = `{{${variableKey}}}`;
        onSelect(tag);
        setIsOpen(false);
        setSearchQuery('');
        setSelectedCategory(null);
    };

    // Handle keyboard navigation
    const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
            setIsOpen(false);
        }
    };

    const categories = Object.entries(filteredVariables);

    return (
        <div className="relative inline-block" ref={dropdownRef}>
            {/* Trigger Button */}
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg
                    border border-gray-300 bg-white text-gray-700
                    hover:bg-gray-50 hover:border-gray-400
                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1
                    transition-all duration-150 ${buttonClassName}`}
            >
                <svg className="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                {buttonText}
                <svg className={`w-4 h-4 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {/* Dropdown Panel - Large overlay */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-40"
                    onClick={() => setIsOpen(false)}
                />
            )}
            {isOpen && (
                <div
                    className="absolute z-50 mt-2 right-0 w-[420px] max-h-[520px] overflow-hidden rounded-2xl border border-gray-200
                        bg-white shadow-2xl ring-1 ring-black ring-opacity-5"
                    onKeyDown={handleKeyDown}
                >
                    {/* Header */}
                    <div className="px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                <h3 className="font-semibold">Insert Variable</h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsOpen(false)}
                                className="p-1 hover:bg-white/20 rounded-lg transition-colors"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <p className="text-sm text-blue-100 mt-1">Click on a variable to insert it into your message</p>
                    </div>

                    {/* Search Input */}
                    <div className="p-4 border-b border-gray-100 bg-gray-50">
                        <div className="relative">
                            <svg className="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                ref={searchInputRef}
                                type="text"
                                placeholder="Search variables... (e.g., name, phone, order)"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-11 pr-4 py-3 text-sm border border-gray-200 rounded-xl
                                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                    placeholder:text-gray-400"
                            />
                        </div>
                    </div>

                    {/* Variables List */}
                    <div className="max-h-[340px] overflow-y-auto">
                        {categories.length === 0 ? (
                            <div className="p-8 text-center text-gray-500">
                                <svg className="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <p className="font-medium">No variables found</p>
                                <p className="text-sm mt-1">Try a different search term</p>
                            </div>
                        ) : (
                            categories.map(([categoryKey, category]) => (
                                <div key={categoryKey} className="border-b border-gray-100 last:border-b-0">
                                    {/* Category Header */}
                                    <button
                                        type="button"
                                        onClick={() => setSelectedCategory(
                                            selectedCategory === categoryKey ? null : categoryKey
                                        )}
                                        className="w-full flex items-center justify-between px-4 py-3.5
                                            hover:bg-gray-50 transition-colors"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="text-2xl">{category.icon}</span>
                                            <div className="text-left">
                                                <span className="font-semibold text-gray-900 block">{category.label}</span>
                                                <span className="text-xs text-gray-500">{category.description}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                                {Object.keys(category.variables).length} vars
                                            </span>
                                            <svg
                                                className={`w-5 h-5 text-gray-400 transition-transform duration-200
                                                    ${selectedCategory === categoryKey ? 'rotate-180' : ''}`}
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round"
                                                    strokeWidth={2} d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </button>

                                    {/* Category Variables */}
                                    {(selectedCategory === categoryKey || searchQuery) && (
                                        <div className="bg-gray-50 border-t border-gray-100 py-2">
                                            {Object.entries(category.variables).map(([varKey, varData]) => (
                                                <button
                                                    key={varKey}
                                                    type="button"
                                                    onClick={() => handleSelect(varKey)}
                                                    className="w-full flex items-start gap-4 px-5 py-3 text-left
                                                        hover:bg-blue-50 transition-colors group"
                                                >
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <span className="text-sm font-medium text-gray-900 group-hover:text-blue-700">
                                                                {varData.label}
                                                            </span>
                                                        </div>
                                                        <code className="text-xs font-mono bg-gray-200/80
                                                            group-hover:bg-blue-100 px-2 py-1 rounded
                                                            text-gray-600 group-hover:text-blue-700 inline-block">
                                                            {`{{${varKey}}}`}
                                                        </code>
                                                        {showPreview && (
                                                            <p className="text-xs text-gray-400 mt-1.5">
                                                                Example: <span className="text-gray-600 font-medium">{varData.example}</span>
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="flex-shrink-0 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 bg-blue-100 px-2 py-1 rounded-lg">
                                                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            Insert
                                                        </span>
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </div>

                    {/* Footer Tip */}
                    <div className="px-4 py-3 bg-gradient-to-r from-amber-50 to-orange-50 border-t border-amber-100">
                        <div className="flex items-start gap-2">
                            <span className="text-lg">💡</span>
                            <div>
                                <p className="text-sm font-medium text-amber-800">Pro Tip: Use modifiers</p>
                                <p className="text-xs text-amber-700 mt-0.5">
                                    <code className="bg-amber-100/80 px-1.5 py-0.5 rounded">{'{{contact.name|default:"Customer"}}'}</code>
                                    <span className="ml-2">for fallback values</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * VariableTag Component
 *
 * Renders a styled merge tag that can be clicked to copy or remove.
 */
export function VariableTag({ variable, onRemove, showExample = false }) {
    const [copied, setCopied] = useState(false);

    // Find variable info
    const getVariableInfo = () => {
        for (const category of Object.values(VARIABLE_CATEGORIES)) {
            if (category.variables[variable]) {
                return category.variables[variable];
            }
        }
        return null;
    };

    const info = getVariableInfo();

    const handleCopy = () => {
        navigator.clipboard.writeText(`{{${variable}}}`);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 text-sm">
            <code className="font-mono text-xs">{`{{${variable}}}`}</code>
            {showExample && info && (
                <span className="text-blue-600 text-xs">({info.example})</span>
            )}
            <button
                type="button"
                onClick={handleCopy}
                className="p-0.5 hover:bg-blue-200 rounded transition-colors"
                title={copied ? 'Copied!' : 'Copy'}
            >
                {copied ? (
                    <svg className="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                ) : (
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                )}
            </button>
            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="p-0.5 hover:bg-red-200 hover:text-red-700 rounded transition-colors"
                    title="Remove"
                >
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </span>
    );
}

/**
 * TextareaWithVariables Component
 *
 * A textarea with integrated variable picker.
 */
export function TextareaWithVariables({
    value,
    onChange,
    triggerType = 'default',
    placeholder = '',
    rows = 4,
    className = '',
    label = '',
    helpText = '',
}) {
    const textareaRef = useRef(null);

    const handleInsertVariable = (tag) => {
        const textarea = textareaRef.current;
        if (!textarea) {
            onChange(value + tag);
            return;
        }

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const newValue = value.substring(0, start) + tag + value.substring(end);

        onChange(newValue);

        // Restore cursor position after the inserted tag
        setTimeout(() => {
            textarea.focus();
            const newPosition = start + tag.length;
            textarea.setSelectionRange(newPosition, newPosition);
        }, 0);
    };

    // Extract variables from the current value for highlighting
    const extractedVariables = useMemo(() => {
        const matches = value.match(/\{\{([a-z_][a-z0-9_.]*)\}\}/gi) || [];
        return matches.map(match => match.replace(/[{}]/g, ''));
    }, [value]);

    return (
        <div className="space-y-2">
            {label && (
                <div className="flex items-center justify-between">
                    <label className="block text-sm font-medium text-gray-700">{label}</label>
                    <VariablePicker
                        onSelect={handleInsertVariable}
                        triggerType={triggerType}
                        buttonText="Insert"
                        buttonClassName="text-xs py-1 px-2"
                    />
                </div>
            )}

            <textarea
                ref={textareaRef}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                rows={rows}
                className={`w-full px-3 py-2 border border-gray-300 rounded-lg
                    focus:ring-2 focus:ring-blue-500 focus:border-transparent
                    font-mono text-sm ${className}`}
            />

            {/* Show extracted variables */}
            {extractedVariables.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {extractedVariables.map((variable, index) => (
                        <VariableTag key={`${variable}-${index}`} variable={variable} />
                    ))}
                </div>
            )}

            {helpText && (
                <p className="text-xs text-gray-500">{helpText}</p>
            )}
        </div>
    );
}

/**
 * Preview text with resolved variables (using example values)
 */
export function VariablePreview({ text, className = '' }) {
    const previewText = useMemo(() => {
        let preview = text;

        // Replace all variables with their example values
        Object.values(VARIABLE_CATEGORIES).forEach(category => {
            Object.entries(category.variables).forEach(([varKey, varData]) => {
                const pattern = new RegExp(`\\{\\{${varKey.replace('.', '\\.')}(?:\\|[^}]+)?\\}\\}`, 'gi');
                preview = preview.replace(pattern, varData.example);
            });
        });

        return preview;
    }, [text]);

    if (!text) return null;

    return (
        <div className={`bg-gray-50 rounded-lg p-3 border border-gray-200 ${className}`}>
            <div className="flex items-center gap-2 text-xs text-gray-500 mb-2">
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Preview (with example data)
            </div>
            <div className="text-sm text-gray-700 whitespace-pre-wrap font-mono">
                {previewText}
            </div>
        </div>
    );
}

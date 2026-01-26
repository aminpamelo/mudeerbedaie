/**
 * Funnel Automation Types
 * These are specific triggers and actions for sales funnel automations
 */

// Funnel-specific Trigger Types
export const FUNNEL_TRIGGER_TYPES = {
    // Session Events
    SESSION_STARTED: 'session_started',
    PAGE_VIEW: 'page_view',

    // Cart Events
    CART_CREATED: 'cart_created',
    CART_UPDATED: 'cart_updated',
    CART_ABANDONMENT: 'cart_abandonment',

    // Purchase Events
    PURCHASE_COMPLETED: 'purchase_completed',
    PURCHASE_FAILED: 'purchase_failed',

    // Opt-in Events
    OPTIN_SUBMITTED: 'optin_submitted',

    // Upsell Events
    UPSELL_ACCEPTED: 'upsell_accepted',
    UPSELL_DECLINED: 'upsell_declined',
    DOWNSELL_ACCEPTED: 'downsell_accepted',
    DOWNSELL_DECLINED: 'downsell_declined',

    // Order Bump Events
    ORDER_BUMP_ACCEPTED: 'order_bump_accepted',
    ORDER_BUMP_DECLINED: 'order_bump_declined',

    // Time-based
    TIME_DELAY: 'time_delay',
};

// Action Types (shared with CRM workflow)
export const FUNNEL_ACTION_TYPES = {
    // Communication
    SEND_EMAIL: 'send_email',
    SEND_WHATSAPP: 'send_whatsapp',
    SEND_SMS: 'send_sms',

    // Contact Management
    ADD_TAG: 'add_tag',
    REMOVE_TAG: 'remove_tag',
    UPDATE_FIELD: 'update_field',
    ADD_SCORE: 'add_score',

    // Funnel-specific Actions
    REDIRECT_TO_STEP: 'redirect_to_step',
    SHOW_POPUP: 'show_popup',
    APPLY_COUPON: 'apply_coupon',

    // External
    WEBHOOK: 'webhook',
};

// Condition Operators
export const CONDITION_OPERATORS = {
    EQUALS: 'equals',
    NOT_EQUALS: 'not_equals',
    CONTAINS: 'contains',
    NOT_CONTAINS: 'not_contains',
    GREATER_THAN: 'greater_than',
    LESS_THAN: 'less_than',
    IS_SET: 'is_set',
    IS_NOT_SET: 'is_not_set',
};

// Delay Units
export const DELAY_UNITS = {
    MINUTES: 'minutes',
    HOURS: 'hours',
    DAYS: 'days',
};

// Automation Status
export const AUTOMATION_STATUS = {
    DRAFT: 'draft',
    ACTIVE: 'active',
    PAUSED: 'paused',
};

// Trigger Configurations
export const FUNNEL_TRIGGER_CONFIGS = {
    [FUNNEL_TRIGGER_TYPES.SESSION_STARTED]: {
        label: 'Session Started',
        description: 'Triggers when a visitor starts a new funnel session',
        icon: '🚀',
        color: '#10B981',
        category: 'session',
    },
    [FUNNEL_TRIGGER_TYPES.PAGE_VIEW]: {
        label: 'Page View',
        description: 'Triggers when a specific funnel step is viewed',
        icon: '👁️',
        color: '#3B82F6',
        category: 'session',
        config: { step_id: null },
    },
    [FUNNEL_TRIGGER_TYPES.CART_CREATED]: {
        label: 'Cart Created',
        description: 'Triggers when a cart is created',
        icon: '🛒',
        color: '#8B5CF6',
        category: 'cart',
    },
    [FUNNEL_TRIGGER_TYPES.CART_UPDATED]: {
        label: 'Cart Updated',
        description: 'Triggers when cart contents change',
        icon: '📝',
        color: '#6366F1',
        category: 'cart',
    },
    [FUNNEL_TRIGGER_TYPES.CART_ABANDONMENT]: {
        label: 'Cart Abandonment',
        description: 'Triggers when a cart is abandoned for a specified time',
        icon: '🛒❌',
        color: '#EF4444',
        category: 'cart',
        config: { delay_minutes: 30 },
    },
    [FUNNEL_TRIGGER_TYPES.PURCHASE_COMPLETED]: {
        label: 'Purchase Completed',
        description: 'Triggers when a purchase is successfully completed',
        icon: '✅',
        color: '#10B981',
        category: 'purchase',
    },
    [FUNNEL_TRIGGER_TYPES.PURCHASE_FAILED]: {
        label: 'Purchase Failed',
        description: 'Triggers when a purchase attempt fails',
        icon: '❌',
        color: '#EF4444',
        category: 'purchase',
    },
    [FUNNEL_TRIGGER_TYPES.OPTIN_SUBMITTED]: {
        label: 'Opt-in Submitted',
        description: 'Triggers when a visitor submits an opt-in form',
        icon: '📧',
        color: '#3B82F6',
        category: 'optin',
    },
    [FUNNEL_TRIGGER_TYPES.UPSELL_ACCEPTED]: {
        label: 'Upsell Accepted',
        description: 'Triggers when an upsell offer is accepted',
        icon: '💰✅',
        color: '#10B981',
        category: 'upsell',
        config: { step_id: null },
    },
    [FUNNEL_TRIGGER_TYPES.UPSELL_DECLINED]: {
        label: 'Upsell Declined',
        description: 'Triggers when an upsell offer is declined',
        icon: '💰❌',
        color: '#F59E0B',
        category: 'upsell',
        config: { step_id: null },
    },
    [FUNNEL_TRIGGER_TYPES.DOWNSELL_ACCEPTED]: {
        label: 'Downsell Accepted',
        description: 'Triggers when a downsell offer is accepted',
        icon: '🏷️✅',
        color: '#10B981',
        category: 'downsell',
        config: { step_id: null },
    },
    [FUNNEL_TRIGGER_TYPES.DOWNSELL_DECLINED]: {
        label: 'Downsell Declined',
        description: 'Triggers when a downsell offer is declined',
        icon: '🏷️❌',
        color: '#F59E0B',
        category: 'downsell',
        config: { step_id: null },
    },
    [FUNNEL_TRIGGER_TYPES.ORDER_BUMP_ACCEPTED]: {
        label: 'Order Bump Accepted',
        description: 'Triggers when an order bump is accepted',
        icon: '📦✅',
        color: '#10B981',
        category: 'order_bump',
    },
    [FUNNEL_TRIGGER_TYPES.ORDER_BUMP_DECLINED]: {
        label: 'Order Bump Declined',
        description: 'Triggers when an order bump is declined/unchecked',
        icon: '📦❌',
        color: '#F59E0B',
        category: 'order_bump',
    },
    [FUNNEL_TRIGGER_TYPES.TIME_DELAY]: {
        label: 'Time Delay',
        description: 'Wait for a specified amount of time',
        icon: '⏰',
        color: '#F97316',
        category: 'time',
        config: { delay: 1, unit: 'hours' },
    },
};

// Action Configurations
export const FUNNEL_ACTION_CONFIGS = {
    [FUNNEL_ACTION_TYPES.SEND_EMAIL]: {
        label: 'Send Email',
        description: 'Send an email to the visitor',
        icon: '📧',
        color: '#3B82F6',
        category: 'communication',
        config: { subject: '', content: '', template_id: null },
    },
    [FUNNEL_ACTION_TYPES.SEND_WHATSAPP]: {
        label: 'Send WhatsApp',
        description: 'Send a WhatsApp message',
        icon: '💬',
        color: '#25D366',
        category: 'communication',
        config: { message: '', template_id: null },
    },
    [FUNNEL_ACTION_TYPES.SEND_SMS]: {
        label: 'Send SMS',
        description: 'Send an SMS message',
        icon: '📱',
        color: '#6366F1',
        category: 'communication',
        config: { message: '' },
    },
    [FUNNEL_ACTION_TYPES.ADD_TAG]: {
        label: 'Add Tag',
        description: 'Add a tag to the contact',
        icon: '🏷️',
        color: '#8B5CF6',
        category: 'contact',
        config: { tag_id: null },
    },
    [FUNNEL_ACTION_TYPES.REMOVE_TAG]: {
        label: 'Remove Tag',
        description: 'Remove a tag from the contact',
        icon: '🏷️❌',
        color: '#EF4444',
        category: 'contact',
        config: { tag_id: null },
    },
    [FUNNEL_ACTION_TYPES.UPDATE_FIELD]: {
        label: 'Update Field',
        description: 'Update a contact field value',
        icon: '✏️',
        color: '#6366F1',
        category: 'contact',
        config: { field: '', value: '' },
    },
    [FUNNEL_ACTION_TYPES.ADD_SCORE]: {
        label: 'Add Score',
        description: 'Add or subtract lead score points',
        icon: '📊',
        color: '#F59E0B',
        category: 'contact',
        config: { points: 0, reason: '' },
    },
    [FUNNEL_ACTION_TYPES.REDIRECT_TO_STEP]: {
        label: 'Redirect to Step',
        description: 'Redirect visitor to a different funnel step',
        icon: '↪️',
        color: '#8B5CF6',
        category: 'funnel',
        config: { step_id: null },
    },
    [FUNNEL_ACTION_TYPES.SHOW_POPUP]: {
        label: 'Show Popup',
        description: 'Display a popup message',
        icon: '💬',
        color: '#6366F1',
        category: 'funnel',
        config: { title: '', message: '', type: 'info' },
    },
    [FUNNEL_ACTION_TYPES.APPLY_COUPON]: {
        label: 'Apply Coupon',
        description: 'Automatically apply a coupon code',
        icon: '🎟️',
        color: '#10B981',
        category: 'funnel',
        config: { coupon_id: null },
    },
    [FUNNEL_ACTION_TYPES.WEBHOOK]: {
        label: 'Webhook',
        description: 'Send data to an external URL',
        icon: '🌐',
        color: '#6B7280',
        category: 'external',
        config: { url: '', method: 'POST', include_session_data: true },
    },
};

export default {
    FUNNEL_TRIGGER_TYPES,
    FUNNEL_ACTION_TYPES,
    CONDITION_OPERATORS,
    DELAY_UNITS,
    AUTOMATION_STATUS,
    FUNNEL_TRIGGER_CONFIGS,
    FUNNEL_ACTION_CONFIGS,
};

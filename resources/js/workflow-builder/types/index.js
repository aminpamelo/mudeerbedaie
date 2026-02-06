/**
 * Workflow Builder Types
 */

// Node types
export const NODE_TYPES = {
    TRIGGER: 'trigger',
    ACTION: 'action',
    CONDITION: 'condition',
    DELAY: 'delay',
};

// Trigger types
export const TRIGGER_TYPES = {
    // Student/Contact Events
    STUDENT_CREATED: 'student_created',
    STUDENT_UPDATED: 'student_updated',
    TAG_ADDED: 'tag_added',
    TAG_REMOVED: 'tag_removed',

    // Order Events
    ORDER_CREATED: 'order_created',
    ORDER_PAID: 'order_paid',
    ORDER_CANCELLED: 'order_cancelled',
    ORDER_SHIPPED: 'order_shipped',
    ORDER_DELIVERED: 'order_delivered',

    // Enrollment Events
    ENROLLMENT_CREATED: 'enrollment_created',
    ENROLLMENT_COMPLETED: 'enrollment_completed',
    ENROLLMENT_CANCELLED: 'enrollment_cancelled',
    SUBSCRIPTION_CANCELLED: 'subscription_cancelled',
    SUBSCRIPTION_PAST_DUE: 'subscription_past_due',

    // Attendance Events
    ATTENDANCE_MARKED: 'attendance_marked',
    ATTENDANCE_PRESENT: 'attendance_present',
    ATTENDANCE_ABSENT: 'attendance_absent',
    ATTENDANCE_LATE: 'attendance_late',
    ATTENDANCE_EXCUSED: 'attendance_excused',

    // Communication Events
    EMAIL_OPENED: 'email_opened',
    EMAIL_CLICKED: 'email_clicked',
    WHATSAPP_REPLIED: 'whatsapp_replied',

    // Time-based
    DATE_TRIGGER: 'date_trigger',
    RECURRING: 'recurring',

    // Manual
    MANUAL: 'manual',
};

// Action types
export const ACTION_TYPES = {
    // Communication
    SEND_EMAIL: 'send_email',
    SEND_WHATSAPP: 'send_whatsapp',
    SEND_SMS: 'send_sms',
    SEND_NOTIFICATION: 'send_notification',

    // Contact Management
    ADD_TAG: 'add_tag',
    REMOVE_TAG: 'remove_tag',
    UPDATE_FIELD: 'update_field',
    ADD_SCORE: 'add_score',

    // Flow Control
    ADD_TO_WORKFLOW: 'add_to_workflow',
    REMOVE_FROM_WORKFLOW: 'remove_from_workflow',

    // External
    WEBHOOK: 'webhook',

    // Internal
    CREATE_TASK: 'create_task',
    SEND_INTERNAL_NOTIFICATION: 'send_internal_notification',
};

// Condition operators
export const CONDITION_OPERATORS = {
    EQUALS: 'equals',
    NOT_EQUALS: 'not_equals',
    CONTAINS: 'contains',
    NOT_CONTAINS: 'not_contains',
    GREATER_THAN: 'greater_than',
    LESS_THAN: 'less_than',
    IS_SET: 'is_set',
    IS_NOT_SET: 'is_not_set',
    IN_LIST: 'in_list',
    NOT_IN_LIST: 'not_in_list',
};

// Delay units
export const DELAY_UNITS = {
    MINUTES: 'minutes',
    HOURS: 'hours',
    DAYS: 'days',
    WEEKS: 'weeks',
};

// Workflow status
export const WORKFLOW_STATUS = {
    DRAFT: 'draft',
    ACTIVE: 'active',
    PAUSED: 'paused',
    ARCHIVED: 'archived',
};

// Node configurations
export const NODE_CONFIGS = {
    // Student/Contact Triggers
    [TRIGGER_TYPES.STUDENT_CREATED]: {
        label: 'Student Created',
        description: 'Triggers when a new student is created',
        icon: 'user-plus',
        color: '#10B981',
        category: 'student',
    },
    [TRIGGER_TYPES.STUDENT_UPDATED]: {
        label: 'Student Updated',
        description: 'Triggers when a student record is updated',
        icon: 'user-check',
        color: '#10B981',
        category: 'student',
    },
    [TRIGGER_TYPES.TAG_ADDED]: {
        label: 'Tag Added',
        description: 'Triggers when a tag is added to a student',
        icon: 'tag',
        color: '#10B981',
        category: 'student',
        config: { tag_id: null },
    },
    [TRIGGER_TYPES.TAG_REMOVED]: {
        label: 'Tag Removed',
        description: 'Triggers when a tag is removed from a student',
        icon: 'x-circle',
        color: '#EF4444',
        category: 'student',
        config: { tag_id: null },
    },

    // Order Triggers
    [TRIGGER_TYPES.ORDER_CREATED]: {
        label: 'Order Created',
        description: 'Triggers when a new order is created',
        icon: 'shopping-cart',
        color: '#6366F1',
        category: 'order',
    },
    [TRIGGER_TYPES.ORDER_PAID]: {
        label: 'Order Paid',
        description: 'Triggers when an order is paid',
        icon: 'credit-card',
        color: '#10B981',
        category: 'order',
    },
    [TRIGGER_TYPES.ORDER_CANCELLED]: {
        label: 'Order Cancelled',
        description: 'Triggers when an order is cancelled',
        icon: 'x-circle',
        color: '#EF4444',
        category: 'order',
    },
    [TRIGGER_TYPES.ORDER_SHIPPED]: {
        label: 'Order Shipped',
        description: 'Triggers when an order is shipped',
        icon: 'truck',
        color: '#3B82F6',
        category: 'order',
    },
    [TRIGGER_TYPES.ORDER_DELIVERED]: {
        label: 'Order Delivered',
        description: 'Triggers when an order is delivered',
        icon: 'check-circle',
        color: '#10B981',
        category: 'order',
    },

    // Enrollment Triggers
    [TRIGGER_TYPES.ENROLLMENT_CREATED]: {
        label: 'Enrollment Created',
        description: 'Triggers when a student enrolls in a course',
        icon: 'book-open',
        color: '#8B5CF6',
        category: 'enrollment',
        config: { course_id: null },
    },
    [TRIGGER_TYPES.ENROLLMENT_COMPLETED]: {
        label: 'Enrollment Completed',
        description: 'Triggers when a student completes a course',
        icon: 'award',
        color: '#10B981',
        category: 'enrollment',
        config: { course_id: null },
    },
    [TRIGGER_TYPES.ENROLLMENT_CANCELLED]: {
        label: 'Enrollment Cancelled',
        description: 'Triggers when an enrollment is cancelled',
        icon: 'user-x',
        color: '#EF4444',
        category: 'enrollment',
        config: { course_id: null },
    },
    [TRIGGER_TYPES.SUBSCRIPTION_CANCELLED]: {
        label: 'Subscription Cancelled',
        description: 'Triggers when a subscription is cancelled',
        icon: 'credit-card',
        color: '#EF4444',
        category: 'enrollment',
    },
    [TRIGGER_TYPES.SUBSCRIPTION_PAST_DUE]: {
        label: 'Subscription Past Due',
        description: 'Triggers when a subscription payment is past due',
        icon: 'alert-triangle',
        color: '#F59E0B',
        category: 'enrollment',
    },

    // Attendance Triggers
    [TRIGGER_TYPES.ATTENDANCE_MARKED]: {
        label: 'Attendance Marked',
        description: 'Triggers when any attendance is recorded',
        icon: 'clipboard-check',
        color: '#6366F1',
        category: 'attendance',
        config: { class_id: null },
    },
    [TRIGGER_TYPES.ATTENDANCE_PRESENT]: {
        label: 'Marked Present',
        description: 'Triggers when a student is marked present',
        icon: 'check-circle',
        color: '#10B981',
        category: 'attendance',
        config: { class_id: null },
    },
    [TRIGGER_TYPES.ATTENDANCE_ABSENT]: {
        label: 'Marked Absent',
        description: 'Triggers when a student is marked absent',
        icon: 'x-circle',
        color: '#EF4444',
        category: 'attendance',
        config: { class_id: null },
    },
    [TRIGGER_TYPES.ATTENDANCE_LATE]: {
        label: 'Marked Late',
        description: 'Triggers when a student is marked late',
        icon: 'clock',
        color: '#F59E0B',
        category: 'attendance',
        config: { class_id: null },
    },
    [TRIGGER_TYPES.ATTENDANCE_EXCUSED]: {
        label: 'Marked Excused',
        description: 'Triggers when a student is marked excused',
        icon: 'shield',
        color: '#3B82F6',
        category: 'attendance',
        config: { class_id: null },
    },

    // Time-based Triggers
    [TRIGGER_TYPES.DATE_TRIGGER]: {
        label: 'Date Trigger',
        description: 'Triggers on a specific date',
        icon: 'calendar',
        color: '#8B5CF6',
        category: 'time',
        config: { date: null, time: null },
    },
    [TRIGGER_TYPES.RECURRING]: {
        label: 'Recurring',
        description: 'Triggers on a recurring schedule',
        icon: 'repeat',
        color: '#8B5CF6',
        category: 'time',
        config: { frequency: 'daily', time: null },
    },
    [TRIGGER_TYPES.MANUAL]: {
        label: 'Manual Trigger',
        description: 'Manually trigger for selected students',
        icon: 'play',
        color: '#6B7280',
        category: 'manual',
    },

    // Actions
    [ACTION_TYPES.SEND_EMAIL]: {
        label: 'Send Email',
        description: 'Send an email to the student',
        icon: 'mail',
        color: '#3B82F6',
        category: 'communication',
        config: { template_id: null, subject: '', content: '' },
    },
    [ACTION_TYPES.SEND_WHATSAPP]: {
        label: 'Send WhatsApp',
        description: 'Send a WhatsApp message',
        icon: 'message-circle',
        color: '#25D366',
        category: 'communication',
        config: { template_id: null, message: '' },
    },
    [ACTION_TYPES.SEND_NOTIFICATION]: {
        label: 'Send Notification',
        description: 'Send an internal notification to admins',
        icon: 'bell',
        color: '#F59E0B',
        category: 'communication',
        config: { title: '', message: '', notify_admins: true },
    },
    [ACTION_TYPES.ADD_TAG]: {
        label: 'Add Tag',
        description: 'Add a tag to the student',
        icon: 'tag',
        color: '#8B5CF6',
        category: 'student',
        config: { tag_id: null },
    },
    [ACTION_TYPES.REMOVE_TAG]: {
        label: 'Remove Tag',
        description: 'Remove a tag from the student',
        icon: 'x-circle',
        color: '#EF4444',
        category: 'student',
        config: { tag_id: null },
    },
    [ACTION_TYPES.UPDATE_FIELD]: {
        label: 'Update Field',
        description: 'Update a student field value',
        icon: 'edit',
        color: '#6366F1',
        category: 'student',
        config: { field: '', value: '' },
    },
    [ACTION_TYPES.ADD_SCORE]: {
        label: 'Add Score',
        description: 'Add or subtract lead score points',
        icon: 'trending-up',
        color: '#F59E0B',
        category: 'student',
        config: { points: 0, category: 'engagement' },
    },
    [ACTION_TYPES.WEBHOOK]: {
        label: 'Webhook',
        description: 'Send data to an external URL',
        icon: 'globe',
        color: '#6B7280',
        category: 'external',
        config: { url: '', method: 'POST', headers: {} },
    },
};

export default {
    NODE_TYPES,
    TRIGGER_TYPES,
    ACTION_TYPES,
    CONDITION_OPERATORS,
    DELAY_UNITS,
    WORKFLOW_STATUS,
    NODE_CONFIGS,
};

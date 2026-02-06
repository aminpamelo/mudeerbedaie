import React, { useState } from 'react';
import { TRIGGER_TYPES, ACTION_TYPES, NODE_CONFIGS } from '../types';

const NodeItem = ({ type, nodeType, data, icon, label, color }) => {
    const onDragStart = (event) => {
        event.dataTransfer.setData('application/reactflow/type', nodeType);
        event.dataTransfer.setData('application/reactflow/data', JSON.stringify({
            ...data,
            label,
        }));
        event.dataTransfer.effectAllowed = 'move';
    };

    return (
        <div
            draggable
            onDragStart={onDragStart}
            className={`
                flex items-center gap-2 p-2.5 rounded-lg border border-gray-200 bg-white
                cursor-grab hover:border-gray-300 hover:shadow-sm transition-all
            `}
        >
            <div
                className="w-7 h-7 rounded-full flex items-center justify-center text-base"
                style={{ backgroundColor: `${color}20` }}
            >
                {icon}
            </div>
            <div className="flex-1 min-w-0">
                <div className="text-xs font-medium text-gray-900 truncate">{label}</div>
            </div>
        </div>
    );
};

const CollapsibleSection = ({ title, children, defaultOpen = true }) => {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className="mb-4">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center justify-between w-full text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 hover:text-gray-700"
            >
                <span>{title}</span>
                <span className="text-gray-400">{isOpen ? '−' : '+'}</span>
            </button>
            {isOpen && <div className="space-y-1.5">{children}</div>}
        </div>
    );
};

export default function NodePalette() {
    // Student/Contact Triggers
    const studentTriggers = [
        { type: TRIGGER_TYPES.STUDENT_CREATED, icon: '👤', label: 'Student Created', color: '#10B981' },
        { type: TRIGGER_TYPES.STUDENT_UPDATED, icon: '✏️', label: 'Student Updated', color: '#10B981' },
        { type: TRIGGER_TYPES.TAG_ADDED, icon: '🏷️', label: 'Tag Added', color: '#10B981' },
        { type: TRIGGER_TYPES.TAG_REMOVED, icon: '🚫', label: 'Tag Removed', color: '#EF4444' },
    ];

    // Order Triggers
    const orderTriggers = [
        { type: TRIGGER_TYPES.ORDER_CREATED, icon: '🛒', label: 'Order Created', color: '#6366F1' },
        { type: TRIGGER_TYPES.ORDER_PAID, icon: '💳', label: 'Order Paid', color: '#10B981' },
        { type: TRIGGER_TYPES.ORDER_CANCELLED, icon: '❌', label: 'Order Cancelled', color: '#EF4444' },
        { type: TRIGGER_TYPES.ORDER_SHIPPED, icon: '🚚', label: 'Order Shipped', color: '#3B82F6' },
        { type: TRIGGER_TYPES.ORDER_DELIVERED, icon: '✅', label: 'Order Delivered', color: '#10B981' },
    ];

    // Enrollment Triggers
    const enrollmentTriggers = [
        { type: TRIGGER_TYPES.ENROLLMENT_CREATED, icon: '📚', label: 'Enrolled in Course', color: '#8B5CF6' },
        { type: TRIGGER_TYPES.ENROLLMENT_COMPLETED, icon: '🎓', label: 'Course Completed', color: '#10B981' },
        { type: TRIGGER_TYPES.ENROLLMENT_CANCELLED, icon: '🚪', label: 'Enrollment Cancelled', color: '#EF4444' },
        { type: TRIGGER_TYPES.SUBSCRIPTION_CANCELLED, icon: '💔', label: 'Subscription Cancelled', color: '#EF4444' },
        { type: TRIGGER_TYPES.SUBSCRIPTION_PAST_DUE, icon: '⚠️', label: 'Payment Past Due', color: '#F59E0B' },
    ];

    // Attendance Triggers
    const attendanceTriggers = [
        { type: TRIGGER_TYPES.ATTENDANCE_MARKED, icon: '📋', label: 'Any Attendance', color: '#6366F1' },
        { type: TRIGGER_TYPES.ATTENDANCE_PRESENT, icon: '✅', label: 'Marked Present', color: '#10B981' },
        { type: TRIGGER_TYPES.ATTENDANCE_ABSENT, icon: '❌', label: 'Marked Absent', color: '#EF4444' },
        { type: TRIGGER_TYPES.ATTENDANCE_LATE, icon: '⏰', label: 'Marked Late', color: '#F59E0B' },
        { type: TRIGGER_TYPES.ATTENDANCE_EXCUSED, icon: '🛡️', label: 'Marked Excused', color: '#3B82F6' },
    ];

    // Communication Triggers
    const communicationTriggers = [
        { type: TRIGGER_TYPES.EMAIL_OPENED, icon: '📧', label: 'Email Opened', color: '#10B981' },
        { type: TRIGGER_TYPES.EMAIL_CLICKED, icon: '🖱️', label: 'Email Clicked', color: '#3B82F6' },
        { type: TRIGGER_TYPES.WHATSAPP_REPLIED, icon: '💬', label: 'WhatsApp Replied', color: '#25D366' },
    ];

    // Other Triggers
    const otherTriggers = [
        { type: TRIGGER_TYPES.DATE_TRIGGER, icon: '📅', label: 'On Date', color: '#8B5CF6' },
        { type: TRIGGER_TYPES.RECURRING, icon: '🔄', label: 'Recurring', color: '#8B5CF6' },
        { type: TRIGGER_TYPES.MANUAL, icon: '▶️', label: 'Manual Trigger', color: '#6B7280' },
    ];

    // Communication Actions
    const communicationActions = [
        { type: ACTION_TYPES.SEND_EMAIL, icon: '📧', label: 'Send Email', color: '#3B82F6' },
        { type: ACTION_TYPES.SEND_WHATSAPP, icon: '💬', label: 'Send WhatsApp', color: '#25D366' },
        { type: ACTION_TYPES.SEND_NOTIFICATION, icon: '🔔', label: 'Send Notification', color: '#F59E0B' },
    ];

    // Student Actions
    const studentActions = [
        { type: ACTION_TYPES.ADD_TAG, icon: '🏷️', label: 'Add Tag', color: '#8B5CF6' },
        { type: ACTION_TYPES.REMOVE_TAG, icon: '🚫', label: 'Remove Tag', color: '#EF4444' },
        { type: ACTION_TYPES.UPDATE_FIELD, icon: '✏️', label: 'Update Field', color: '#6366F1' },
        { type: ACTION_TYPES.ADD_SCORE, icon: '📊', label: 'Add Score', color: '#F59E0B' },
    ];

    // External Actions
    const externalActions = [
        { type: ACTION_TYPES.WEBHOOK, icon: '🌐', label: 'Webhook', color: '#6B7280' },
    ];

    const renderTriggers = (triggers) => (
        triggers.map((trigger) => (
            <NodeItem
                key={trigger.type}
                type={trigger.type}
                nodeType="trigger"
                data={{ triggerType: trigger.type }}
                icon={trigger.icon}
                label={trigger.label}
                color={trigger.color}
            />
        ))
    );

    const renderActions = (actions) => (
        actions.map((action) => (
            <NodeItem
                key={action.type}
                type={action.type}
                nodeType="action"
                data={{ actionType: action.type }}
                icon={action.icon}
                label={action.label}
                color={action.color}
            />
        ))
    );

    return (
        <div className="w-64 bg-gray-50 border-r border-gray-200 p-4 overflow-y-auto">
            <div className="mb-4">
                <h2 className="text-sm font-bold text-gray-700 mb-1">Workflow Builder</h2>
                <p className="text-xs text-gray-500">Drag and drop to build</p>
            </div>

            <div className="border-t border-gray-200 pt-4">
                <h3 className="text-xs font-bold text-green-600 uppercase tracking-wider mb-3">
                    Triggers
                </h3>

                <CollapsibleSection title="Student Events">
                    {renderTriggers(studentTriggers)}
                </CollapsibleSection>

                <CollapsibleSection title="Order Events">
                    {renderTriggers(orderTriggers)}
                </CollapsibleSection>

                <CollapsibleSection title="Enrollment">
                    {renderTriggers(enrollmentTriggers)}
                </CollapsibleSection>

                <CollapsibleSection title="Attendance">
                    {renderTriggers(attendanceTriggers)}
                </CollapsibleSection>

                <CollapsibleSection title="Communication" defaultOpen={false}>
                    {renderTriggers(communicationTriggers)}
                </CollapsibleSection>

                <CollapsibleSection title="Other" defaultOpen={false}>
                    {renderTriggers(otherTriggers)}
                </CollapsibleSection>
            </div>

            <div className="border-t border-gray-200 pt-4">
                <h3 className="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3">
                    Actions
                </h3>

                <CollapsibleSection title="Communication">
                    {renderActions(communicationActions)}
                </CollapsibleSection>

                <CollapsibleSection title="Student">
                    {renderActions(studentActions)}
                </CollapsibleSection>

                <CollapsibleSection title="External" defaultOpen={false}>
                    {renderActions(externalActions)}
                </CollapsibleSection>
            </div>

            <div className="border-t border-gray-200 pt-4">
                <h3 className="text-xs font-bold text-amber-600 uppercase tracking-wider mb-3">
                    Flow Control
                </h3>
                <div className="space-y-1.5">
                    <NodeItem
                        type="condition"
                        nodeType="condition"
                        data={{}}
                        icon="🔀"
                        label="If/Else Condition"
                        color="#F59E0B"
                    />
                    <NodeItem
                        type="delay"
                        nodeType="delay"
                        data={{ delay: 1, unit: 'days' }}
                        icon="⏱️"
                        label="Delay / Wait"
                        color="#F97316"
                    />
                </div>
            </div>
        </div>
    );
}

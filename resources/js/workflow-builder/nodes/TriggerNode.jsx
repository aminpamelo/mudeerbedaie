import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';
import NodeUnlinkButton from '../components/NodeUnlinkButton';

const triggerIcons = {
    contact_created: '👤',
    contact_updated: '✏️',
    tag_added: '🏷️',
    tag_removed: '🏷️',
    order_created: '🛒',
    order_paid: '💳',
    order_cancelled: '❌',
    enrollment_created: '📚',
    class_attended: '✅',
    class_absent: '❓',
    email_opened: '📧',
    email_clicked: '🔗',
    whatsapp_replied: '💬',
    date_trigger: '📅',
    recurring: '🔄',
    manual: '👆',
};

const triggerLabels = {
    contact_created: 'Contact Created',
    contact_updated: 'Contact Updated',
    tag_added: 'Tag Added',
    tag_removed: 'Tag Removed',
    order_created: 'Order Created',
    order_paid: 'Order Paid',
    order_cancelled: 'Order Cancelled',
    enrollment_created: 'Enrollment Created',
    class_attended: 'Class Attended',
    class_absent: 'Class Absent',
    email_opened: 'Email Opened',
    email_clicked: 'Email Clicked',
    whatsapp_replied: 'WhatsApp Replied',
    date_trigger: 'Date Trigger',
    recurring: 'Recurring',
    manual: 'Manual Trigger',
};

function TriggerNode({ id, data, selected }) {
    const triggerType = data?.triggerType || 'manual';
    const label = data?.label || triggerLabels[triggerType] || 'Select Trigger';
    const icon = triggerIcons[triggerType] || '⚡';

    return (
        <div
            className={`group relative px-4 py-3 rounded-lg border-2 bg-white shadow-md min-w-[200px] ${
                selected ? 'border-green-500 ring-2 ring-green-200' : 'border-green-300'
            }`}
        >
            <NodeUnlinkButton nodeId={id} />
            <div className="flex items-center gap-2 mb-2">
                <div className="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-lg">
                    {icon}
                </div>
                <div className="flex-1">
                    <div className="text-xs text-green-600 font-semibold uppercase">Trigger</div>
                    <div className="text-sm font-medium text-gray-900">{label}</div>
                </div>
            </div>

            {data?.description && (
                <div className="text-xs text-gray-500 mt-1">{data.description}</div>
            )}

            <Handle
                type="source"
                position={Position.Bottom}
                className="w-3 h-3 !bg-green-500 border-2 border-white"
            />
        </div>
    );
}

export default memo(TriggerNode);

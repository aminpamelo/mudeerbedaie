import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

const actionIcons = {
    send_email: '📧',
    send_whatsapp: '💬',
    send_sms: '📱',
    send_notification: '🔔',
    add_tag: '🏷️',
    remove_tag: '🚫',
    update_field: '📝',
    add_score: '📊',
    add_to_workflow: '➡️',
    remove_from_workflow: '⬅️',
    webhook: '🌐',
    create_task: '✅',
    send_internal_notification: '📢',
};

const actionLabels = {
    send_email: 'Send Email',
    send_whatsapp: 'Send WhatsApp',
    send_sms: 'Send SMS',
    send_notification: 'Send Notification',
    add_tag: 'Add Tag',
    remove_tag: 'Remove Tag',
    update_field: 'Update Field',
    add_score: 'Add Score',
    add_to_workflow: 'Add to Workflow',
    remove_from_workflow: 'Remove from Workflow',
    webhook: 'Webhook',
    create_task: 'Create Task',
    send_internal_notification: 'Internal Notification',
};

const actionColors = {
    send_email: { bg: 'bg-blue-100', border: 'border-blue-300', text: 'text-blue-600' },
    send_whatsapp: { bg: 'bg-green-100', border: 'border-green-300', text: 'text-green-600' },
    send_sms: { bg: 'bg-purple-100', border: 'border-purple-300', text: 'text-purple-600' },
    add_tag: { bg: 'bg-indigo-100', border: 'border-indigo-300', text: 'text-indigo-600' },
    remove_tag: { bg: 'bg-red-100', border: 'border-red-300', text: 'text-red-600' },
    add_score: { bg: 'bg-yellow-100', border: 'border-yellow-300', text: 'text-yellow-600' },
    default: { bg: 'bg-gray-100', border: 'border-gray-300', text: 'text-gray-600' },
};

function ActionNode({ data, selected }) {
    const actionType = data?.actionType || '';
    const label = data?.label || actionLabels[actionType] || 'Select Action';
    const icon = actionIcons[actionType] || '⚙️';
    const colors = actionColors[actionType] || actionColors.default;

    return (
        <div
            className={`px-4 py-3 rounded-lg border-2 bg-white shadow-md min-w-[200px] ${
                selected ? `${colors.border} ring-2 ring-blue-200` : colors.border
            }`}
        >
            <Handle
                type="target"
                position={Position.Top}
                className="w-3 h-3 !bg-gray-400 border-2 border-white"
            />

            <div className="flex items-center gap-2 mb-2">
                <div className={`w-8 h-8 rounded-full ${colors.bg} flex items-center justify-center text-lg`}>
                    {icon}
                </div>
                <div className="flex-1">
                    <div className={`text-xs ${colors.text} font-semibold uppercase`}>Action</div>
                    <div className="text-sm font-medium text-gray-900">{label}</div>
                </div>
            </div>

            {data?.description && (
                <div className="text-xs text-gray-500 mt-1">{data.description}</div>
            )}

            {data?.config?.template_id && (
                <div className="mt-2 px-2 py-1 bg-gray-50 rounded text-xs text-gray-600">
                    Template: #{data.config.template_id}
                </div>
            )}

            {data?.config?.tag_id && (
                <div className="mt-2 px-2 py-1 bg-gray-50 rounded text-xs text-gray-600">
                    Tag: #{data.config.tag_id}
                </div>
            )}

            <Handle
                type="source"
                position={Position.Bottom}
                className="w-3 h-3 !bg-blue-500 border-2 border-white"
            />
        </div>
    );
}

export default memo(ActionNode);

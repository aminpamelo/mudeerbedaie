import React, { useState, useEffect } from 'react';
import { useWorkflowStore } from '../stores/workflowStore';
import { ACTION_TYPES, TRIGGER_TYPES, DELAY_UNITS, CONDITION_OPERATORS } from '../types';
import api from '../services/api';

const TriggerConfig = ({ node, onUpdate }) => {
    const triggerType = node.data.triggerType;
    const [tags, setTags] = useState([]);
    const [courses, setCourses] = useState([]);
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            try {
                const [tagsRes, coursesRes, classesRes] = await Promise.all([
                    api.tag.getAll().catch(() => ({ data: [] })),
                    api.course.getAll().catch(() => ({ data: [] })),
                    api.class.getAll().catch(() => ({ data: [] })),
                ]);
                setTags(tagsRes.data || []);
                setCourses(coursesRes.data || []);
                setClasses(classesRes.data || []);
            } catch (error) {
                console.error('Failed to fetch data:', error);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, []);

    const handleChange = (field, value) => {
        onUpdate({
            ...node.data,
            config: { ...node.data.config, [field]: value },
        });
    };

    const needsTagSelector = [TRIGGER_TYPES.TAG_ADDED, TRIGGER_TYPES.TAG_REMOVED].includes(triggerType);
    const needsCourseSelector = [
        TRIGGER_TYPES.ENROLLMENT_CREATED,
        TRIGGER_TYPES.ENROLLMENT_COMPLETED,
        TRIGGER_TYPES.ENROLLMENT_CANCELLED,
    ].includes(triggerType);
    const needsClassSelector = [
        TRIGGER_TYPES.ATTENDANCE_MARKED,
        TRIGGER_TYPES.ATTENDANCE_PRESENT,
        TRIGGER_TYPES.ATTENDANCE_ABSENT,
        TRIGGER_TYPES.ATTENDANCE_LATE,
        TRIGGER_TYPES.ATTENDANCE_EXCUSED,
    ].includes(triggerType);

    return (
        <div className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Trigger Type
                </label>
                <select
                    value={triggerType || ''}
                    onChange={(e) => onUpdate({ ...node.data, triggerType: e.target.value, config: {} })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                >
                    <option value="">Select trigger...</option>
                    <optgroup label="Student Events">
                        <option value={TRIGGER_TYPES.STUDENT_CREATED}>Student Created</option>
                        <option value={TRIGGER_TYPES.STUDENT_UPDATED}>Student Updated</option>
                        <option value={TRIGGER_TYPES.TAG_ADDED}>Tag Added</option>
                        <option value={TRIGGER_TYPES.TAG_REMOVED}>Tag Removed</option>
                    </optgroup>
                    <optgroup label="Order Events">
                        <option value={TRIGGER_TYPES.ORDER_CREATED}>Order Created</option>
                        <option value={TRIGGER_TYPES.ORDER_PAID}>Order Paid</option>
                        <option value={TRIGGER_TYPES.ORDER_CANCELLED}>Order Cancelled</option>
                        <option value={TRIGGER_TYPES.ORDER_SHIPPED}>Order Shipped</option>
                        <option value={TRIGGER_TYPES.ORDER_DELIVERED}>Order Delivered</option>
                    </optgroup>
                    <optgroup label="Enrollment Events">
                        <option value={TRIGGER_TYPES.ENROLLMENT_CREATED}>Enrolled in Course</option>
                        <option value={TRIGGER_TYPES.ENROLLMENT_COMPLETED}>Course Completed</option>
                        <option value={TRIGGER_TYPES.ENROLLMENT_CANCELLED}>Enrollment Cancelled</option>
                        <option value={TRIGGER_TYPES.SUBSCRIPTION_CANCELLED}>Subscription Cancelled</option>
                        <option value={TRIGGER_TYPES.SUBSCRIPTION_PAST_DUE}>Payment Past Due</option>
                    </optgroup>
                    <optgroup label="Attendance Events">
                        <option value={TRIGGER_TYPES.ATTENDANCE_MARKED}>Any Attendance</option>
                        <option value={TRIGGER_TYPES.ATTENDANCE_PRESENT}>Marked Present</option>
                        <option value={TRIGGER_TYPES.ATTENDANCE_ABSENT}>Marked Absent</option>
                        <option value={TRIGGER_TYPES.ATTENDANCE_LATE}>Marked Late</option>
                        <option value={TRIGGER_TYPES.ATTENDANCE_EXCUSED}>Marked Excused</option>
                    </optgroup>
                    <optgroup label="Communication Events">
                        <option value={TRIGGER_TYPES.EMAIL_OPENED}>Email Opened</option>
                        <option value={TRIGGER_TYPES.EMAIL_CLICKED}>Email Clicked</option>
                        <option value={TRIGGER_TYPES.WHATSAPP_REPLIED}>WhatsApp Replied</option>
                    </optgroup>
                    <optgroup label="Other">
                        <option value={TRIGGER_TYPES.DATE_TRIGGER}>On Specific Date</option>
                        <option value={TRIGGER_TYPES.RECURRING}>Recurring Schedule</option>
                        <option value={TRIGGER_TYPES.MANUAL}>Manual Trigger</option>
                    </optgroup>
                </select>
            </div>

            {needsTagSelector && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Select Tag (Optional)
                    </label>
                    <select
                        value={node.data.config?.tag_id || ''}
                        onChange={(e) => handleChange('tag_id', e.target.value ? parseInt(e.target.value) : null)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        disabled={loading}
                    >
                        <option value="">Any tag</option>
                        {tags.map((tag) => (
                            <option key={tag.id} value={tag.id}>{tag.name}</option>
                        ))}
                    </select>
                    <p className="text-xs text-gray-500 mt-1">
                        Leave empty to trigger for any tag
                    </p>
                </div>
            )}

            {needsCourseSelector && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Select Course (Optional)
                    </label>
                    <select
                        value={node.data.config?.course_id || ''}
                        onChange={(e) => handleChange('course_id', e.target.value ? parseInt(e.target.value) : null)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        disabled={loading}
                    >
                        <option value="">Any course</option>
                        {courses.map((course) => (
                            <option key={course.id} value={course.id}>{course.name}</option>
                        ))}
                    </select>
                    <p className="text-xs text-gray-500 mt-1">
                        Leave empty to trigger for any course
                    </p>
                </div>
            )}

            {needsClassSelector && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Select Class (Optional)
                    </label>
                    <select
                        value={node.data.config?.class_id || ''}
                        onChange={(e) => handleChange('class_id', e.target.value ? parseInt(e.target.value) : null)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        disabled={loading}
                    >
                        <option value="">Any class</option>
                        {classes.map((cls) => (
                            <option key={cls.id} value={cls.id}>{cls.title}</option>
                        ))}
                    </select>
                    <p className="text-xs text-gray-500 mt-1">
                        Leave empty to trigger for any class
                    </p>
                </div>
            )}

            {triggerType === TRIGGER_TYPES.DATE_TRIGGER && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Date
                        </label>
                        <input
                            type="date"
                            value={node.data.config?.date || ''}
                            onChange={(e) => handleChange('date', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Time
                        </label>
                        <input
                            type="time"
                            value={node.data.config?.time || '09:00'}
                            onChange={(e) => handleChange('time', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                </div>
            )}

            {triggerType === TRIGGER_TYPES.RECURRING && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Frequency
                        </label>
                        <select
                            value={node.data.config?.frequency || 'daily'}
                            onChange={(e) => handleChange('frequency', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            At Time
                        </label>
                        <input
                            type="time"
                            value={node.data.config?.time || '09:00'}
                            onChange={(e) => handleChange('time', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                </div>
            )}
        </div>
    );
};

const ActionConfig = ({ node, onUpdate }) => {
    const actionType = node.data.actionType;
    const [tags, setTags] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const fetchTags = async () => {
            setLoading(true);
            try {
                const response = await api.tag.getAll().catch(() => ({ data: [] }));
                setTags(response.data || []);
            } catch (error) {
                console.error('Failed to fetch tags:', error);
            } finally {
                setLoading(false);
            }
        };
        fetchTags();
    }, []);

    const handleChange = (field, value) => {
        onUpdate({
            ...node.data,
            config: { ...node.data.config, [field]: value },
        });
    };

    return (
        <div className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Action Type
                </label>
                <select
                    value={actionType || ''}
                    onChange={(e) => onUpdate({ ...node.data, actionType: e.target.value, config: {} })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                >
                    <option value="">Select action...</option>
                    <optgroup label="Communication">
                        <option value={ACTION_TYPES.SEND_EMAIL}>Send Email</option>
                        <option value={ACTION_TYPES.SEND_WHATSAPP}>Send WhatsApp</option>
                        <option value={ACTION_TYPES.SEND_NOTIFICATION}>Send Notification</option>
                    </optgroup>
                    <optgroup label="Student Management">
                        <option value={ACTION_TYPES.ADD_TAG}>Add Tag</option>
                        <option value={ACTION_TYPES.REMOVE_TAG}>Remove Tag</option>
                        <option value={ACTION_TYPES.UPDATE_FIELD}>Update Field</option>
                        <option value={ACTION_TYPES.ADD_SCORE}>Add Score</option>
                    </optgroup>
                    <optgroup label="External">
                        <option value={ACTION_TYPES.WEBHOOK}>Webhook</option>
                    </optgroup>
                </select>
            </div>

            {actionType === ACTION_TYPES.SEND_EMAIL && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Subject Line
                        </label>
                        <input
                            type="text"
                            placeholder="Email subject"
                            value={node.data.config?.subject || ''}
                            onChange={(e) => handleChange('subject', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Content
                        </label>
                        <textarea
                            rows="4"
                            placeholder="Email content... Use {{name}}, {{email}} for personalization"
                            value={node.data.config?.content || ''}
                            onChange={(e) => handleChange('content', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                </div>
            )}

            {actionType === ACTION_TYPES.SEND_WHATSAPP && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Message
                    </label>
                    <textarea
                        rows="4"
                        placeholder="WhatsApp message... Use {{name}}, {{phone}} for personalization"
                        value={node.data.config?.message || ''}
                        onChange={(e) => handleChange('message', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    />
                </div>
            )}

            {actionType === ACTION_TYPES.SEND_NOTIFICATION && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Title
                        </label>
                        <input
                            type="text"
                            placeholder="Notification title"
                            value={node.data.config?.title || ''}
                            onChange={(e) => handleChange('title', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Message
                        </label>
                        <textarea
                            rows="3"
                            placeholder="Notification message..."
                            value={node.data.config?.message || ''}
                            onChange={(e) => handleChange('message', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="notify_admins"
                            checked={node.data.config?.notify_admins ?? true}
                            onChange={(e) => handleChange('notify_admins', e.target.checked)}
                            className="rounded border-gray-300"
                        />
                        <label htmlFor="notify_admins" className="text-sm text-gray-700">
                            Notify all admins
                        </label>
                    </div>
                </div>
            )}

            {(actionType === ACTION_TYPES.ADD_TAG || actionType === ACTION_TYPES.REMOVE_TAG) && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Select Tag
                    </label>
                    <select
                        value={node.data.config?.tag_id || ''}
                        onChange={(e) => handleChange('tag_id', e.target.value ? parseInt(e.target.value) : null)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        disabled={loading}
                    >
                        <option value="">Select tag...</option>
                        {tags.map((tag) => (
                            <option key={tag.id} value={tag.id}>{tag.name}</option>
                        ))}
                    </select>
                </div>
            )}

            {actionType === ACTION_TYPES.UPDATE_FIELD && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Field
                        </label>
                        <select
                            value={node.data.config?.field || ''}
                            onChange={(e) => handleChange('field', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        >
                            <option value="">Select field...</option>
                            <option value="status">Status</option>
                            <option value="phone">Phone</option>
                            <option value="city">City</option>
                            <option value="state">State</option>
                            <option value="country">Country</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            New Value
                        </label>
                        <input
                            type="text"
                            placeholder="New value"
                            value={node.data.config?.value || ''}
                            onChange={(e) => handleChange('value', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                </div>
            )}

            {actionType === ACTION_TYPES.ADD_SCORE && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Points
                        </label>
                        <input
                            type="number"
                            placeholder="Points to add (or negative to subtract)"
                            value={node.data.config?.points || 0}
                            onChange={(e) => handleChange('points', parseInt(e.target.value) || 0)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Reason
                        </label>
                        <input
                            type="text"
                            placeholder="e.g., Workflow automation"
                            value={node.data.config?.reason || ''}
                            onChange={(e) => handleChange('reason', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                </div>
            )}

            {actionType === ACTION_TYPES.WEBHOOK && (
                <div className="space-y-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Webhook URL
                        </label>
                        <input
                            type="url"
                            placeholder="https://..."
                            value={node.data.config?.url || ''}
                            onChange={(e) => handleChange('url', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Method
                        </label>
                        <select
                            value={node.data.config?.method || 'POST'}
                            onChange={(e) => handleChange('method', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        >
                            <option value="POST">POST</option>
                            <option value="GET">GET</option>
                            <option value="PUT">PUT</option>
                            <option value="PATCH">PATCH</option>
                        </select>
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="include_student_data"
                            checked={node.data.config?.include_student_data ?? true}
                            onChange={(e) => handleChange('include_student_data', e.target.checked)}
                            className="rounded border-gray-300"
                        />
                        <label htmlFor="include_student_data" className="text-sm text-gray-700">
                            Include student data
                        </label>
                    </div>
                </div>
            )}
        </div>
    );
};

const ConditionConfig = ({ node, onUpdate }) => {
    const handleChange = (field, value) => {
        onUpdate({ ...node.data, [field]: value });
    };

    return (
        <div className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Field to Check
                </label>
                <select
                    value={node.data.field || ''}
                    onChange={(e) => handleChange('field', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                >
                    <option value="">Select field...</option>
                    <optgroup label="Student Fields">
                        <option value="email">Email</option>
                        <option value="name">Name</option>
                        <option value="phone">Phone</option>
                        <option value="status">Status</option>
                        <option value="city">City</option>
                        <option value="state">State</option>
                        <option value="country">Country</option>
                    </optgroup>
                    <optgroup label="Engagement">
                        <option value="leadScore.total_score">Lead Score</option>
                        <option value="orders_count">Order Count</option>
                    </optgroup>
                    <optgroup label="Tags">
                        <option value="has_tag">Has Tag</option>
                    </optgroup>
                </select>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Operator
                </label>
                <select
                    value={node.data.operator || ''}
                    onChange={(e) => handleChange('operator', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                >
                    <option value="">Select operator...</option>
                    <option value={CONDITION_OPERATORS.EQUALS}>Equals</option>
                    <option value={CONDITION_OPERATORS.NOT_EQUALS}>Not Equals</option>
                    <option value={CONDITION_OPERATORS.CONTAINS}>Contains</option>
                    <option value={CONDITION_OPERATORS.GREATER_THAN}>Greater Than</option>
                    <option value={CONDITION_OPERATORS.LESS_THAN}>Less Than</option>
                    <option value={CONDITION_OPERATORS.IS_SET}>Is Set</option>
                    <option value={CONDITION_OPERATORS.IS_NOT_SET}>Is Not Set</option>
                </select>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Value
                </label>
                <input
                    type="text"
                    placeholder="Value to compare"
                    value={node.data.value || ''}
                    onChange={(e) => handleChange('value', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                />
            </div>
        </div>
    );
};

const DelayConfig = ({ node, onUpdate }) => {
    const handleChange = (field, value) => {
        onUpdate({ ...node.data, [field]: value });
    };

    return (
        <div className="space-y-4">
            <div className="flex gap-2">
                <div className="flex-1">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Wait
                    </label>
                    <input
                        type="number"
                        min="1"
                        value={node.data.delay || 1}
                        onChange={(e) => handleChange('delay', parseInt(e.target.value) || 1)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    />
                </div>
                <div className="flex-1">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Unit
                    </label>
                    <select
                        value={node.data.unit || DELAY_UNITS.DAYS}
                        onChange={(e) => handleChange('unit', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    >
                        <option value={DELAY_UNITS.MINUTES}>Minutes</option>
                        <option value={DELAY_UNITS.HOURS}>Hours</option>
                        <option value={DELAY_UNITS.DAYS}>Days</option>
                        <option value={DELAY_UNITS.WEEKS}>Weeks</option>
                    </select>
                </div>
            </div>
        </div>
    );
};

export default function NodeConfigPanel() {
    const { selectedNode, updateNode, removeNode } = useWorkflowStore();

    if (!selectedNode) {
        return (
            <div className="w-80 bg-white border-l border-gray-200 p-4">
                <div className="text-center text-gray-500 py-8">
                    <div className="text-4xl mb-2">👆</div>
                    <p className="text-sm">Select a node to configure it</p>
                </div>
            </div>
        );
    }

    const handleUpdate = (newData) => {
        updateNode(selectedNode.id, newData);
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this node?')) {
            removeNode(selectedNode.id);
        }
    };

    const renderConfig = () => {
        switch (selectedNode.type) {
            case 'trigger':
                return <TriggerConfig node={selectedNode} onUpdate={handleUpdate} />;
            case 'action':
                return <ActionConfig node={selectedNode} onUpdate={handleUpdate} />;
            case 'condition':
                return <ConditionConfig node={selectedNode} onUpdate={handleUpdate} />;
            case 'delay':
                return <DelayConfig node={selectedNode} onUpdate={handleUpdate} />;
            default:
                return <p className="text-sm text-gray-500">Unknown node type</p>;
        }
    };

    return (
        <div className="w-80 bg-white border-l border-gray-200 p-4 overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900 capitalize">
                    {selectedNode.type} Settings
                </h3>
                <button
                    onClick={handleDelete}
                    className="text-red-500 hover:text-red-700 text-sm"
                >
                    Delete
                </button>
            </div>

            <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Label
                </label>
                <input
                    type="text"
                    value={selectedNode.data.label || ''}
                    onChange={(e) => handleUpdate({ ...selectedNode.data, label: e.target.value })}
                    placeholder="Node label"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                />
            </div>

            <hr className="my-4" />

            {renderConfig()}
        </div>
    );
}

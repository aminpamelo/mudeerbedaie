import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

const unitLabels = {
    minutes: 'minute(s)',
    hours: 'hour(s)',
    days: 'day(s)',
    weeks: 'week(s)',
};

function DelayNode({ data, selected }) {
    const getDelayText = () => {
        if (!data?.delay || !data?.unit) return 'Set delay';
        return `Wait ${data.delay} ${unitLabels[data.unit] || data.unit}`;
    };

    const label = data?.label || 'Wait';

    return (
        <div
            className={`px-4 py-3 rounded-lg border-2 bg-white shadow-md min-w-[180px] ${
                selected ? 'border-orange-500 ring-2 ring-orange-200' : 'border-orange-300'
            }`}
        >
            <Handle
                type="target"
                position={Position.Top}
                className="w-3 h-3 !bg-gray-400 border-2 border-white"
            />

            <div className="flex items-center gap-2 mb-1">
                <div className="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-lg">
                    ⏱️
                </div>
                <div className="flex-1">
                    <div className="text-xs text-orange-600 font-semibold uppercase">Delay</div>
                    <div className="text-sm font-medium text-gray-900">{label}</div>
                </div>
            </div>

            <div className="mt-2 px-2 py-1 bg-orange-50 rounded text-xs text-orange-700 text-center">
                {getDelayText()}
            </div>

            <Handle
                type="source"
                position={Position.Bottom}
                className="w-3 h-3 !bg-orange-500 border-2 border-white"
            />
        </div>
    );
}

export default memo(DelayNode);

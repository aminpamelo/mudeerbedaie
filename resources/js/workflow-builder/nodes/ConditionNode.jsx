import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

function ConditionNode({ data, selected }) {
    const label = data?.label || 'If/Else';

    return (
        <div
            className={`px-4 py-3 rounded-lg border-2 bg-white shadow-md min-w-[200px] ${
                selected ? 'border-amber-500 ring-2 ring-amber-200' : 'border-amber-300'
            }`}
        >
            <Handle
                type="target"
                position={Position.Top}
                className="w-3 h-3 !bg-gray-400 border-2 border-white"
            />

            <div className="flex items-center gap-2 mb-2">
                <div className="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-lg">
                    🔀
                </div>
                <div className="flex-1">
                    <div className="text-xs text-amber-600 font-semibold uppercase">Condition</div>
                    <div className="text-sm font-medium text-gray-900">{label}</div>
                </div>
            </div>

            {data?.field && data?.operator && (
                <div className="mt-2 px-2 py-1 bg-amber-50 rounded text-xs text-amber-700">
                    {data.field} {data.operator} {data.value}
                </div>
            )}

            <div className="flex justify-between mt-3 text-xs">
                <div className="flex items-center gap-1">
                    <div className="w-2 h-2 rounded-full bg-green-500"></div>
                    <span className="text-green-600">Yes</span>
                </div>
                <div className="flex items-center gap-1">
                    <span className="text-red-600">No</span>
                    <div className="w-2 h-2 rounded-full bg-red-500"></div>
                </div>
            </div>

            <Handle
                type="source"
                position={Position.Bottom}
                id="yes"
                className="w-3 h-3 !bg-green-500 border-2 border-white"
                style={{ left: '30%' }}
            />
            <Handle
                type="source"
                position={Position.Bottom}
                id="no"
                className="w-3 h-3 !bg-red-500 border-2 border-white"
                style={{ left: '70%' }}
            />
        </div>
    );
}

export default memo(ConditionNode);

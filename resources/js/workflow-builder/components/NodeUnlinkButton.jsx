import React from 'react';
import { useWorkflowStore } from '../stores/workflowStore';

export default function NodeUnlinkButton({ nodeId }) {
    const removeNode = useWorkflowStore((state) => state.removeNode);

    const handleClick = (event) => {
        event.stopPropagation();
        event.preventDefault();
        removeNode(nodeId);
    };

    const handleMouseDown = (event) => {
        event.stopPropagation();
    };

    return (
        <button
            type="button"
            onClick={handleClick}
            onMouseDown={handleMouseDown}
            aria-label="Remove node"
            title="Remove node"
            className="workflow-node-unlink-btn nodrag"
        >
            <svg
                width="11"
                height="11"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    );
}

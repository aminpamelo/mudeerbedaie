import React, { useCallback, useRef } from 'react';
import {
    ReactFlow,
    Background,
    Controls,
    MiniMap,
} from '@xyflow/react';

import { nodeTypes } from '../nodes';
import { useWorkflowStore } from '../stores/workflowStore';

export default function WorkflowCanvas({ onNodeClick }) {
    const reactFlowWrapper = useRef(null);
    const {
        nodes,
        edges,
        onNodesChange,
        onEdgesChange,
        onConnect,
        setSelectedNode,
    } = useWorkflowStore();

    const handleNodeClick = useCallback((event, node) => {
        setSelectedNode(node);
        if (onNodeClick) {
            onNodeClick(node);
        }
    }, [setSelectedNode, onNodeClick]);

    const handlePaneClick = useCallback(() => {
        setSelectedNode(null);
    }, [setSelectedNode]);

    const onDragOver = useCallback((event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }, []);

    const onDrop = useCallback(
        (event) => {
            event.preventDefault();

            const type = event.dataTransfer.getData('application/reactflow/type');
            const nodeData = JSON.parse(event.dataTransfer.getData('application/reactflow/data') || '{}');

            if (!type) return;

            const position = {
                x: event.clientX - reactFlowWrapper.current.getBoundingClientRect().left,
                y: event.clientY - reactFlowWrapper.current.getBoundingClientRect().top,
            };

            const newNode = {
                id: `${type}_${Date.now()}`,
                type,
                position,
                data: nodeData,
            };

            useWorkflowStore.getState().addNode(newNode);
        },
        []
    );

    return (
        <div ref={reactFlowWrapper} className="h-full w-full">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                onNodeClick={handleNodeClick}
                onPaneClick={handlePaneClick}
                onDragOver={onDragOver}
                onDrop={onDrop}
                nodeTypes={nodeTypes}
                fitView
                snapToGrid
                snapGrid={[15, 15]}
                defaultEdgeOptions={{
                    type: 'smoothstep',
                    animated: true,
                }}
            >
                <Background color="#e5e7eb" gap={15} />
                <Controls />
                <MiniMap
                    nodeColor={(node) => {
                        switch (node.type) {
                            case 'trigger':
                                return '#10B981';
                            case 'action':
                                return '#3B82F6';
                            case 'condition':
                                return '#F59E0B';
                            case 'delay':
                                return '#F97316';
                            default:
                                return '#6B7280';
                        }
                    }}
                    maskColor="rgba(0, 0, 0, 0.1)"
                />
            </ReactFlow>
        </div>
    );
}

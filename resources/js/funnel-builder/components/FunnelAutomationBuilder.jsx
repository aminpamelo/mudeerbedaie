/**
 * Funnel Automation Builder
 * Visual workflow builder using React Flow for funnel automations
 */

import React, { useState, useCallback, useRef, useMemo, useEffect } from 'react';
import {
    ReactFlow,
    ReactFlowProvider,
    Background,
    Controls,
    MiniMap,
    addEdge,
    useNodesState,
    useEdgesState,
    useReactFlow,
    MarkerType,
    Handle,
    Position,
    getBezierPath,
    BaseEdge,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

// Custom animated edge component
const AnimatedEdge = ({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    style = {},
    markerEnd,
    selected,
}) => {
    const [edgePath] = getBezierPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
    });

    return (
        <>
            <BaseEdge
                path={edgePath}
                markerEnd={markerEnd}
                style={{
                    ...style,
                    strokeWidth: selected ? 3 : 2,
                    stroke: selected ? '#3b82f6' : '#94a3b8',
                }}
            />
            {/* Animated dot along the edge */}
            <circle r="4" fill="#3b82f6" className="animate-pulse">
                <animateMotion dur="2s" repeatCount="indefinite" path={edgePath} />
            </circle>
        </>
    );
};

// Edge types configuration
const edgeTypes = {
    animated: AnimatedEdge,
};
import { automationApi } from '../services/api';
import {
    FUNNEL_TRIGGER_TYPES,
    FUNNEL_TRIGGER_CONFIGS,
    FUNNEL_ACTION_TYPES,
    FUNNEL_ACTION_CONFIGS,
    DELAY_UNITS,
} from '../types/funnel-automation-types';
import VariablePicker, { TextareaWithVariables, VariablePreview } from './VariablePicker';

// Handle styles
const handleStyle = {
    width: 12,
    height: 12,
    border: '2px solid white',
    boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
};

const sourceHandleStyle = {
    ...handleStyle,
    background: '#10b981',
    bottom: -6,
};

const targetHandleStyle = {
    ...handleStyle,
    background: '#3b82f6',
    top: -6,
};

// Custom Node Components with Handles
const TriggerNode = ({ data, selected, isConnectable }) => (
    <div className={`relative px-4 py-4 rounded-xl border-2 bg-gradient-to-br from-green-50 to-white shadow-lg min-w-[220px] transition-all duration-200 ${
        selected ? 'border-green-500 ring-4 ring-green-100 shadow-green-200 scale-105' : 'border-green-300 hover:border-green-400 hover:shadow-xl'
    }`}>
        <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-xl shadow-md text-white">
                {data.icon || '✓'}
            </div>
            <div className="flex-1">
                <div className="text-xs text-green-600 font-bold uppercase tracking-wide">Trigger</div>
                <div className="text-sm font-semibold text-gray-900">{data.label || 'Select Trigger'}</div>
            </div>
        </div>
        {data.description && (
            <div className="text-xs text-gray-500 mt-2 pl-13 border-t border-green-100 pt-2">{data.description}</div>
        )}
        {/* Source Handle - Bottom (triggers can only output) */}
        <Handle
            type="source"
            position={Position.Bottom}
            id="source"
            isConnectable={isConnectable}
            style={sourceHandleStyle}
            className="!bg-green-500 hover:!bg-green-600 hover:scale-125 transition-transform"
        />
    </div>
);

const ActionNode = ({ data, selected, isConnectable }) => (
    <div className={`relative px-4 py-4 rounded-xl border-2 bg-gradient-to-br from-blue-50 to-white shadow-lg min-w-[220px] transition-all duration-200 ${
        selected ? 'border-blue-500 ring-4 ring-blue-100 shadow-blue-200 scale-105' : 'border-blue-300 hover:border-blue-400 hover:shadow-xl'
    }`}>
        {/* Target Handle - Top (actions receive input) */}
        <Handle
            type="target"
            position={Position.Top}
            id="target"
            isConnectable={isConnectable}
            style={targetHandleStyle}
            className="!bg-blue-500 hover:!bg-blue-600 hover:scale-125 transition-transform"
        />
        <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-xl shadow-md text-white">
                {data.icon || '💬'}
            </div>
            <div className="flex-1">
                <div className="text-xs text-blue-600 font-bold uppercase tracking-wide">Action</div>
                <div className="text-sm font-semibold text-gray-900">{data.label || 'Select Action'}</div>
            </div>
        </div>
        {data.description && (
            <div className="text-xs text-gray-500 mt-2 border-t border-blue-100 pt-2">{data.description}</div>
        )}
        {/* Source Handle - Bottom (actions can chain to other actions) */}
        <Handle
            type="source"
            position={Position.Bottom}
            id="source"
            isConnectable={isConnectable}
            style={sourceHandleStyle}
            className="!bg-green-500 hover:!bg-green-600 hover:scale-125 transition-transform"
        />
    </div>
);

const DelayNode = ({ data, selected, isConnectable }) => (
    <div className={`relative px-4 py-4 rounded-xl border-2 bg-gradient-to-br from-orange-50 to-white shadow-lg min-w-[200px] transition-all duration-200 ${
        selected ? 'border-orange-500 ring-4 ring-orange-100 shadow-orange-200 scale-105' : 'border-orange-300 hover:border-orange-400 hover:shadow-xl'
    }`}>
        {/* Target Handle - Top */}
        <Handle
            type="target"
            position={Position.Top}
            id="target"
            isConnectable={isConnectable}
            style={{ ...targetHandleStyle, background: '#f97316' }}
            className="!bg-orange-500 hover:!bg-orange-600 hover:scale-125 transition-transform"
        />
        <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-xl shadow-md">
                ⏰
            </div>
            <div className="flex-1">
                <div className="text-xs text-orange-600 font-bold uppercase tracking-wide">Delay</div>
                <div className="text-sm font-semibold text-gray-900">
                    Wait {data.delay || 1} {data.unit || 'hours'}
                </div>
            </div>
        </div>
        {/* Progress indicator */}
        <div className="mt-2 h-1.5 bg-orange-100 rounded-full overflow-hidden">
            <div className="h-full bg-gradient-to-r from-orange-400 to-orange-600 w-1/3 rounded-full animate-pulse"></div>
        </div>
        {/* Source Handle - Bottom */}
        <Handle
            type="source"
            position={Position.Bottom}
            id="source"
            isConnectable={isConnectable}
            style={sourceHandleStyle}
            className="!bg-green-500 hover:!bg-green-600 hover:scale-125 transition-transform"
        />
    </div>
);

const ConditionNode = ({ data, selected, isConnectable }) => (
    <div className={`relative px-4 py-4 rounded-xl border-2 bg-gradient-to-br from-amber-50 to-white shadow-lg min-w-[220px] transition-all duration-200 ${
        selected ? 'border-amber-500 ring-4 ring-amber-100 shadow-amber-200 scale-105' : 'border-amber-300 hover:border-amber-400 hover:shadow-xl'
    }`}>
        {/* Target Handle - Top */}
        <Handle
            type="target"
            position={Position.Top}
            id="target"
            isConnectable={isConnectable}
            style={{ ...targetHandleStyle, background: '#f59e0b' }}
            className="!bg-amber-500 hover:!bg-amber-600 hover:scale-125 transition-transform"
        />
        <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-xl shadow-md">
                🔀
            </div>
            <div className="flex-1">
                <div className="text-xs text-amber-600 font-bold uppercase tracking-wide">Condition</div>
                <div className="text-sm font-semibold text-gray-900">{data.label || 'If/Else'}</div>
            </div>
        </div>
        {data.field && (
            <div className="text-xs text-gray-600 mt-2 bg-amber-50 rounded-lg px-2 py-1 border border-amber-200">
                <span className="font-medium">{data.field}</span> {data.operator} <span className="font-medium">{data.value}</span>
            </div>
        )}
        {/* Two Source Handles - Yes (Left) and No (Right) */}
        <div className="flex justify-between mt-3 text-xs">
            <span className="text-green-600 font-medium flex items-center gap-1">
                <span className="w-2 h-2 rounded-full bg-green-500"></span> Yes
            </span>
            <span className="text-red-600 font-medium flex items-center gap-1">
                No <span className="w-2 h-2 rounded-full bg-red-500"></span>
            </span>
        </div>
        <Handle
            type="source"
            position={Position.Bottom}
            id="yes"
            isConnectable={isConnectable}
            style={{ ...sourceHandleStyle, left: '30%', background: '#22c55e' }}
            className="!bg-green-500 hover:!bg-green-600 hover:scale-125 transition-transform"
        />
        <Handle
            type="source"
            position={Position.Bottom}
            id="no"
            isConnectable={isConnectable}
            style={{ ...sourceHandleStyle, left: '70%', background: '#ef4444' }}
            className="!bg-red-500 hover:!bg-red-600 hover:scale-125 transition-transform"
        />
    </div>
);

// Node types configuration
const nodeTypes = {
    trigger: TriggerNode,
    action: ActionNode,
    delay: DelayNode,
    condition: ConditionNode,
};

// Default edge options with better styling
const defaultEdgeOptions = {
    type: 'smoothstep',
    markerEnd: {
        type: MarkerType.ArrowClosed,
        color: '#64748b',
        width: 20,
        height: 20,
    },
    style: {
        strokeWidth: 2,
        stroke: '#64748b',
    },
    animated: true,
};

// Connection validation - prevent invalid connections
const isValidConnection = (connection, nodes) => {
    const sourceNode = nodes.find(n => n.id === connection.source);
    const targetNode = nodes.find(n => n.id === connection.target);

    if (!sourceNode || !targetNode) return false;

    // Trigger nodes cannot be targets
    if (targetNode.type === 'trigger') return false;

    // Cannot connect a node to itself
    if (connection.source === connection.target) return false;

    return true;
};

export default function FunnelAutomationBuilder({ funnelUuid, automation, steps = [], onClose, showToast }) {
    return (
        <ReactFlowProvider>
            <BuilderContent
                funnelUuid={funnelUuid}
                automation={automation}
                steps={steps}
                onClose={onClose}
                showToast={showToast}
            />
        </ReactFlowProvider>
    );
}

function BuilderContent({ funnelUuid, automation, steps, onClose, showToast }) {
    const reactFlowWrapper = useRef(null);
    const [reactFlowInstance, setReactFlowInstance] = useState(null);
    const [name, setName] = useState(automation?.name || 'New Automation');
    const [saving, setSaving] = useState(false);
    const [selectedNode, setSelectedNode] = useState(null);
    const [showNodePalette, setShowNodePalette] = useState(true);
    const [isDirty, setIsDirty] = useState(false);
    const [connectionFeedback, setConnectionFeedback] = useState(null);

    // Initialize nodes and edges from automation canvas data
    const initialNodes = useMemo(() => {
        if (automation?.canvas_data?.nodes) {
            return automation.canvas_data.nodes;
        }
        // Create default trigger node
        const triggerConfig = FUNNEL_TRIGGER_CONFIGS[automation?.trigger_type] || {};
        return [{
            id: 'trigger-1',
            type: 'trigger',
            position: { x: 250, y: 50 },
            data: {
                label: triggerConfig.label || 'Trigger',
                triggerType: automation?.trigger_type || 'purchase_completed',
                icon: triggerConfig.icon || '⚡',
                description: triggerConfig.description,
                config: automation?.trigger_config || {},
            },
        }];
    }, [automation]);

    const initialEdges = useMemo(() => {
        if (automation?.canvas_data?.edges) {
            return automation.canvas_data.edges;
        }
        return [];
    }, [automation]);

    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);

    // Handle connections with validation
    const onConnect = useCallback(
        (params) => {
            if (!isValidConnection(params, nodes)) {
                setConnectionFeedback({ type: 'error', message: 'Invalid connection!' });
                setTimeout(() => setConnectionFeedback(null), 2000);
                return;
            }

            // Determine edge color based on source handle
            let edgeStyle = { ...defaultEdgeOptions.style };
            if (params.sourceHandle === 'yes') {
                edgeStyle.stroke = '#22c55e';
            } else if (params.sourceHandle === 'no') {
                edgeStyle.stroke = '#ef4444';
            }

            setEdges((eds) => addEdge({
                ...params,
                ...defaultEdgeOptions,
                style: edgeStyle,
                markerEnd: {
                    ...defaultEdgeOptions.markerEnd,
                    color: edgeStyle.stroke,
                },
            }, eds));

            setIsDirty(true);
            setConnectionFeedback({ type: 'success', message: 'Connected!' });
            setTimeout(() => setConnectionFeedback(null), 1500);
        },
        [setEdges, nodes]
    );

    // Handle connection start (for visual feedback)
    const onConnectStart = useCallback((event, { nodeId, handleType }) => {
        setConnectionFeedback({ type: 'info', message: 'Drag to another node to connect...' });
    }, []);

    const onConnectEnd = useCallback(() => {
        setTimeout(() => setConnectionFeedback(null), 500);
    }, []);

    // Handle node selection
    const onNodeClick = useCallback((event, node) => {
        setSelectedNode(node);
    }, []);

    // Handle background click (deselect)
    const onPaneClick = useCallback(() => {
        setSelectedNode(null);
    }, []);

    // Add new node with smart positioning
    const addNode = useCallback((type, config) => {
        // Find the lowest node position and place new node below it
        const lowestY = nodes.reduce((max, node) => Math.max(max, node.position.y), 0);
        const centerX = 300;

        const newNode = {
            id: `${type}-${Date.now()}`,
            type,
            position: {
                x: centerX + (Math.random() * 100 - 50),
                y: lowestY + 150,
            },
            data: {
                label: config.label,
                icon: config.icon,
                description: config.description,
                [`${type}Type`]: config.type,
                config: config.config || {},
                ...config,
            },
        };
        setNodes((nds) => [...nds, newNode]);
        setIsDirty(true);

        // Show feedback
        showToast?.(`Added ${config.label}`, 'success');
    }, [nodes, setNodes, showToast]);

    // Delete selected node
    const deleteSelectedNode = useCallback(() => {
        if (!selectedNode) return;
        if (selectedNode.type === 'trigger') {
            showToast?.('Cannot delete the trigger node', 'error');
            return;
        }
        setNodes((nds) => nds.filter((n) => n.id !== selectedNode.id));
        setEdges((eds) => eds.filter((e) => e.source !== selectedNode.id && e.target !== selectedNode.id));
        setSelectedNode(null);
        setIsDirty(true);
        showToast?.('Node deleted', 'success');
    }, [selectedNode, setNodes, setEdges, showToast]);

    // Update selected node
    const updateSelectedNode = useCallback((updates) => {
        if (!selectedNode) return;
        setNodes((nds) =>
            nds.map((n) =>
                n.id === selectedNode.id
                    ? { ...n, data: { ...n.data, ...updates } }
                    : n
            )
        );
        setSelectedNode((prev) => prev ? { ...prev, data: { ...prev.data, ...updates } } : null);
        setIsDirty(true);
    }, [selectedNode, setNodes]);

    // Save automation
    const handleSave = async () => {
        // Validation: Check if there are actions connected to the trigger
        const triggerNode = nodes.find(n => n.type === 'trigger');
        const connectedEdges = edges.filter(e => e.source === triggerNode?.id);

        if (connectedEdges.length === 0 && nodes.length > 1) {
            showToast?.('Please connect the trigger to at least one action', 'warning');
            return;
        }

        setSaving(true);
        try {
            const canvasData = {
                nodes,
                edges,
            };

            if (automation?.id) {
                await automationApi.update(funnelUuid, automation.id, {
                    name,
                    canvas_data: canvasData,
                });
            } else {
                await automationApi.create(funnelUuid, {
                    name,
                    trigger_type: nodes.find(n => n.type === 'trigger')?.data?.triggerType || 'purchase_completed',
                    canvas_data: canvasData,
                });
            }

            setIsDirty(false);
            showToast?.('Automation saved successfully', 'success');
            onClose();
        } catch (err) {
            console.error('Failed to save:', err);
            showToast?.('Failed to save automation', 'error');
        } finally {
            setSaving(false);
        }
    };

    // Warn before closing with unsaved changes
    const handleClose = useCallback(() => {
        if (isDirty) {
            if (window.confirm('You have unsaved changes. Are you sure you want to leave?')) {
                onClose();
            }
        } else {
            onClose();
        }
    }, [isDirty, onClose]);

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                    deleteSelectedNode();
                }
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [deleteSelectedNode]);

    return (
        <div className="fixed inset-0 bg-gray-100 z-50 flex flex-col">
            {/* Connection Feedback Toast */}
            {connectionFeedback && (
                <div className={`fixed top-20 left-1/2 transform -translate-x-1/2 z-[60] px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 animate-bounce ${
                    connectionFeedback.type === 'success' ? 'bg-green-500 text-white' :
                    connectionFeedback.type === 'error' ? 'bg-red-500 text-white' :
                    'bg-blue-500 text-white'
                }`}>
                    {connectionFeedback.type === 'success' && (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                    {connectionFeedback.type === 'error' && (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    )}
                    {connectionFeedback.type === 'info' && (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    )}
                    <span className="font-medium">{connectionFeedback.message}</span>
                </div>
            )}

            {/* Header */}
            <div className="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-4">
                    <button
                        onClick={handleClose}
                        className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <div className="flex items-center gap-2">
                        <input
                            type="text"
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                setIsDirty(true);
                            }}
                            className="text-lg font-semibold text-gray-900 border-0 border-b-2 border-transparent hover:border-gray-300 focus:border-blue-500 focus:ring-0 bg-transparent px-1"
                        />
                        {isDirty && (
                            <span className="px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 rounded-full">
                                Unsaved
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {/* Node count indicator */}
                    <div className="hidden sm:flex items-center gap-2 text-sm text-gray-500">
                        <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-green-500"></span>
                            {nodes.filter(n => n.type === 'trigger').length} trigger
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-blue-500"></span>
                            {nodes.filter(n => n.type === 'action').length} actions
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-gray-400"></span>
                            {edges.length} connections
                        </span>
                    </div>

                    <div className="w-px h-6 bg-gray-200"></div>

                    <button
                        onClick={() => setShowNodePalette(!showNodePalette)}
                        className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                            showNodePalette ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        {showNodePalette ? 'Hide Palette' : 'Show Palette'}
                    </button>
                    <button
                        onClick={handleClose}
                        className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg font-medium transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSave}
                        disabled={saving}
                        className={`px-4 py-2 rounded-lg font-medium transition-all disabled:opacity-50 flex items-center gap-2 ${
                            isDirty
                                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg'
                                : 'bg-gray-200 text-gray-600'
                        }`}
                    >
                        {saving ? (
                            <>
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </>
                        ) : 'Save Automation'}
                    </button>
                </div>
            </div>

            {/* Main Content */}
            <div className="flex-1 flex overflow-hidden">
                {/* Node Palette */}
                {showNodePalette && (
                    <NodePalette onAddNode={addNode} steps={steps} />
                )}

                {/* Canvas */}
                <div className="flex-1 relative" ref={reactFlowWrapper}>
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        onNodesChange={(changes) => {
                            onNodesChange(changes);
                            // Track position changes as dirty
                            if (changes.some(c => c.type === 'position' && c.dragging === false)) {
                                setIsDirty(true);
                            }
                        }}
                        onEdgesChange={onEdgesChange}
                        onConnect={onConnect}
                        onConnectStart={onConnectStart}
                        onConnectEnd={onConnectEnd}
                        onInit={setReactFlowInstance}
                        onNodeClick={onNodeClick}
                        onPaneClick={onPaneClick}
                        nodeTypes={nodeTypes}
                        edgeTypes={edgeTypes}
                        defaultEdgeOptions={defaultEdgeOptions}
                        fitView
                        fitViewOptions={{ padding: 0.2 }}
                        snapToGrid
                        snapGrid={[15, 15]}
                        connectionLineStyle={{
                            strokeWidth: 3,
                            stroke: '#3b82f6',
                            strokeDasharray: '5,5',
                        }}
                        connectionLineType="smoothstep"
                        deleteKeyCode={['Backspace', 'Delete']}
                        selectionKeyCode={['Shift']}
                        multiSelectionKeyCode={['Meta', 'Control']}
                        proOptions={{ hideAttribution: true }}
                    >
                        <Background
                            color="#cbd5e1"
                            gap={20}
                            size={1}
                            variant="dots"
                        />
                        <Controls
                            position="bottom-left"
                            showInteractive={false}
                            className="!bg-white !border !border-gray-200 !rounded-lg !shadow-md"
                        />
                        <MiniMap
                            position="bottom-right"
                            nodeColor={(node) => {
                                switch (node.type) {
                                    case 'trigger': return '#10b981';
                                    case 'action': return '#3b82f6';
                                    case 'delay': return '#f97316';
                                    case 'condition': return '#f59e0b';
                                    default: return '#6b7280';
                                }
                            }}
                            maskColor="rgba(0, 0, 0, 0.1)"
                            className="!bg-white !border !border-gray-200 !rounded-lg !shadow-md"
                        />
                    </ReactFlow>

                    {/* Keyboard shortcuts helper */}
                    <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-white/90 backdrop-blur-sm border border-gray-200 rounded-lg px-4 py-2 shadow-sm">
                        <div className="flex items-center gap-4 text-xs text-gray-500">
                            <span><kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-gray-700 font-mono">Del</kbd> Delete node</span>
                            <span><kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-gray-700 font-mono">Drag</kbd> from handle to connect</span>
                            <span><kbd className="px-1.5 py-0.5 bg-gray-100 rounded text-gray-700 font-mono">Scroll</kbd> to zoom</span>
                        </div>
                    </div>
                </div>

                {/* Node Config Panel */}
                {selectedNode && (
                    <NodeConfigPanel
                        node={selectedNode}
                        nodes={nodes}
                        steps={steps}
                        onUpdate={updateSelectedNode}
                        onDelete={deleteSelectedNode}
                        onClose={() => setSelectedNode(null)}
                    />
                )}
            </div>
        </div>
    );
}

// Node Palette Component
function NodePalette({ onAddNode, steps }) {
    const [activeCategory, setActiveCategory] = useState('actions');
    const [searchTerm, setSearchTerm] = useState('');

    const categories = {
        actions: {
            label: 'Actions',
            icon: '⚡',
            color: 'blue',
            items: [
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.SEND_EMAIL, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.SEND_EMAIL] },
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.SEND_WHATSAPP, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.SEND_WHATSAPP] },
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.ADD_TAG, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.ADD_TAG] },
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.REMOVE_TAG, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.REMOVE_TAG] },
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.ADD_SCORE, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.ADD_SCORE] },
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.UPDATE_FIELD, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.UPDATE_FIELD] },
                { type: 'action', actionType: FUNNEL_ACTION_TYPES.WEBHOOK, ...FUNNEL_ACTION_CONFIGS[FUNNEL_ACTION_TYPES.WEBHOOK] },
            ],
        },
        flow: {
            label: 'Flow Control',
            icon: '🔄',
            color: 'amber',
            items: [
                { type: 'delay', label: 'Wait/Delay', icon: '⏰', description: 'Wait for a specified time before continuing' },
                { type: 'condition', label: 'Condition', icon: '🔀', description: 'Branch based on conditions (If/Else)' },
            ],
        },
    };

    // Filter items based on search
    const getFilteredItems = () => {
        const items = categories[activeCategory]?.items || [];
        if (!searchTerm) return items;
        return items.filter(item =>
            item.label?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            item.description?.toLowerCase().includes(searchTerm.toLowerCase())
        );
    };

    const filteredItems = getFilteredItems();

    const getNodeTypeColor = (type) => {
        switch (type) {
            case 'action': return 'from-blue-50 to-blue-100 border-blue-200 hover:border-blue-400';
            case 'delay': return 'from-orange-50 to-orange-100 border-orange-200 hover:border-orange-400';
            case 'condition': return 'from-amber-50 to-amber-100 border-amber-200 hover:border-amber-400';
            default: return 'from-gray-50 to-gray-100 border-gray-200 hover:border-gray-400';
        }
    };

    const getIconBgColor = (type) => {
        switch (type) {
            case 'action': return 'bg-gradient-to-br from-blue-400 to-blue-600';
            case 'delay': return 'bg-gradient-to-br from-orange-400 to-orange-600';
            case 'condition': return 'bg-gradient-to-br from-amber-400 to-amber-600';
            default: return 'bg-gradient-to-br from-gray-400 to-gray-600';
        }
    };

    return (
        <div className="w-72 bg-white border-r border-gray-200 overflow-hidden flex flex-col shadow-lg">
            {/* Header */}
            <div className="p-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <svg className="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Nodes
                </h3>

                {/* Search */}
                <div className="mt-3 relative">
                    <input
                        type="text"
                        placeholder="Search nodes..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                    <svg className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {/* Category Tabs */}
            <div className="flex border-b border-gray-200">
                {Object.entries(categories).map(([key, cat]) => (
                    <button
                        key={key}
                        onClick={() => setActiveCategory(key)}
                        className={`flex-1 px-4 py-3 text-sm font-medium transition-all relative ${
                            activeCategory === key
                                ? 'text-blue-600 bg-blue-50/50'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        <span className="flex items-center justify-center gap-1.5">
                            <span>{cat.icon}</span>
                            <span>{cat.label}</span>
                        </span>
                        {activeCategory === key && (
                            <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600"></div>
                        )}
                    </button>
                ))}
            </div>

            {/* Items */}
            <div className="flex-1 overflow-y-auto p-3 space-y-2">
                {filteredItems.length === 0 ? (
                    <div className="text-center py-8 text-gray-500">
                        <svg className="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-sm">No nodes found</p>
                        <p className="text-xs text-gray-400 mt-1">Try a different search term</p>
                    </div>
                ) : (
                    filteredItems.map((item, index) => (
                        <button
                            key={index}
                            onClick={() => onAddNode(item.type, item)}
                            className={`w-full p-3 bg-gradient-to-r ${getNodeTypeColor(item.type)} border rounded-xl text-left transition-all hover:shadow-md hover:scale-[1.02] active:scale-[0.98] group`}
                        >
                            <div className="flex items-start gap-3">
                                <div className={`w-10 h-10 rounded-lg ${getIconBgColor(item.type)} flex items-center justify-center text-lg shadow-sm group-hover:scale-110 transition-transform`}>
                                    {item.icon}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-gray-900 group-hover:text-gray-700">{item.label}</p>
                                    <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{item.description}</p>
                                </div>
                                <svg className="w-5 h-5 text-gray-300 group-hover:text-gray-500 group-hover:translate-x-1 transition-all flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                            </div>
                        </button>
                    ))
                )}
            </div>

            {/* Help Section */}
            <div className="p-3 border-t border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                <div className="flex items-start gap-2">
                    <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p className="text-xs font-medium text-blue-800">How to connect nodes</p>
                        <p className="text-xs text-blue-600 mt-0.5">
                            Click to add a node, then drag from the <span className="font-semibold">green handle</span> at the bottom to the <span className="font-semibold">blue handle</span> at the top of another node.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Node Config Panel Component
function NodeConfigPanel({ node, nodes, steps, onUpdate, onDelete, onClose }) {
    const nodeType = node.type;
    const data = node.data || {};

    // Get trigger type from the trigger node for variable picker
    const triggerNode = nodes?.find(n => n.type === 'trigger');
    const triggerType = triggerNode?.data?.triggerType || 'purchase_completed';

    const getTypeColor = () => {
        switch (nodeType) {
            case 'trigger': return 'from-green-500 to-green-600 border-green-400';
            case 'action': return 'from-blue-500 to-blue-600 border-blue-400';
            case 'delay': return 'from-orange-500 to-orange-600 border-orange-400';
            case 'condition': return 'from-amber-500 to-amber-600 border-amber-400';
            default: return 'from-gray-500 to-gray-600 border-gray-400';
        }
    };

    const getTypeBgColor = () => {
        switch (nodeType) {
            case 'trigger': return 'bg-green-50';
            case 'action': return 'bg-blue-50';
            case 'delay': return 'bg-orange-50';
            case 'condition': return 'bg-amber-50';
            default: return 'bg-gray-50';
        }
    };

    return (
        <div className="w-80 bg-white border-l border-gray-200 overflow-y-auto shadow-xl flex flex-col">
            {/* Header with gradient */}
            <div className={`p-4 ${getTypeBgColor()} border-b`}>
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Configure
                    </h3>
                    <button
                        onClick={onClose}
                        className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-white/50 rounded-lg transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Node Type Badge */}
                <div className="flex items-center gap-3 p-3 bg-white rounded-xl shadow-sm border">
                    <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${getTypeColor()} flex items-center justify-center text-2xl shadow-md`}>
                        {data.icon}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs font-bold uppercase text-gray-400 tracking-wider">{nodeType}</p>
                        <p className="text-base font-semibold text-gray-900 truncate">{data.label}</p>
                    </div>
                </div>
            </div>

            {/* Config Fields */}
            <div className="flex-1 overflow-y-auto p-4">
                <div className="space-y-4">
                    {/* Label */}
                    <div className="group">
                        <label className="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            Label
                        </label>
                        <input
                            type="text"
                            value={data.label || ''}
                            onChange={(e) => onUpdate({ label: e.target.value })}
                            className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all hover:border-gray-300"
                        />
                    </div>

                    {/* Description */}
                    <div className="group">
                        <label className="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            Description
                        </label>
                        <input
                            type="text"
                            value={data.description || ''}
                            onChange={(e) => onUpdate({ description: e.target.value })}
                            className="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all hover:border-gray-300"
                            placeholder="Optional description"
                        />
                    </div>

                    {/* Delay Node Config */}
                    {nodeType === 'delay' && (
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Wait Time
                                </label>
                                <input
                                    type="number"
                                    value={data.delay || 1}
                                    onChange={(e) => onUpdate({ delay: parseInt(e.target.value) || 1 })}
                                    min="1"
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Unit
                                </label>
                                <select
                                    value={data.unit || 'hours'}
                                    onChange={(e) => onUpdate({ unit: e.target.value })}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="minutes">Minutes</option>
                                    <option value="hours">Hours</option>
                                    <option value="days">Days</option>
                                </select>
                            </div>
                        </div>
                    )}

                    {/* Email Action Config */}
                    {nodeType === 'action' && data.actionType === FUNNEL_ACTION_TYPES.SEND_EMAIL && (
                        <>
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-xs font-medium text-gray-700">
                                        Subject
                                    </label>
                                    <VariablePicker
                                        triggerType={triggerType}
                                        buttonText="Insert"
                                        buttonClassName="text-xs py-0.5 px-2"
                                        onSelect={(tag) => {
                                            const newSubject = (data.config?.subject || '') + tag;
                                            onUpdate({ config: { ...data.config, subject: newSubject } });
                                        }}
                                    />
                                </div>
                                <input
                                    type="text"
                                    value={data.config?.subject || ''}
                                    onChange={(e) => onUpdate({ config: { ...data.config, subject: e.target.value } })}
                                    className="w-full px-3 py-2 text-sm font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Email subject with {{contact.name}}"
                                />
                            </div>
                            <div>
                                <TextareaWithVariables
                                    label="Content"
                                    value={data.config?.content || ''}
                                    onChange={(value) => onUpdate({ config: { ...data.config, content: value } })}
                                    triggerType={triggerType}
                                    rows={6}
                                    placeholder="Hi {{contact.first_name}},&#10;&#10;Thank you for your order #{{order.number}}!&#10;&#10;Total: {{order.total}}"
                                    helpText="Use merge tags to personalize your email"
                                />
                            </div>
                            {/* Preview */}
                            {data.config?.content && (
                                <VariablePreview
                                    text={data.config.content}
                                    className="mt-2"
                                />
                            )}
                        </>
                    )}

                    {/* WhatsApp Action Config */}
                    {nodeType === 'action' && data.actionType === FUNNEL_ACTION_TYPES.SEND_WHATSAPP && (
                        <div className="space-y-3">
                            <TextareaWithVariables
                                label="Message"
                                value={data.config?.message || ''}
                                onChange={(value) => onUpdate({ config: { ...data.config, message: value } })}
                                triggerType={triggerType}
                                rows={6}
                                placeholder="Hi {{contact.name|default:&quot;there&quot;}}!&#10;&#10;Thank you for your purchase!&#10;&#10;Order #: {{order.number}}&#10;Total: {{order.total}}&#10;&#10;{{order.items_list}}"
                                helpText="Use merge tags to personalize your message"
                            />
                            {/* Phone Number Field Info */}
                            <div className="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <div className="flex items-start gap-2">
                                    <svg className="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div className="text-xs text-blue-700">
                                        <p className="font-medium mb-1">Phone Number</p>
                                        <p>The message will be sent to <code className="bg-blue-100 px-1 rounded">{'{{contact.phone}}'}</code> automatically from the order/contact data.</p>
                                    </div>
                                </div>
                            </div>
                            {/* Preview */}
                            {data.config?.message && (
                                <VariablePreview
                                    text={data.config.message}
                                    className="mt-2"
                                />
                            )}
                        </div>
                    )}

                    {/* Webhook Config */}
                    {nodeType === 'action' && data.actionType === FUNNEL_ACTION_TYPES.WEBHOOK && (
                        <>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Webhook URL
                                </label>
                                <input
                                    type="url"
                                    value={data.config?.url || ''}
                                    onChange={(e) => onUpdate({ config: { ...data.config, url: e.target.value } })}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="https://..."
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Method
                                </label>
                                <select
                                    value={data.config?.method || 'POST'}
                                    onChange={(e) => onUpdate({ config: { ...data.config, method: e.target.value } })}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                </select>
                            </div>
                        </>
                    )}

                    {/* Condition Node Config */}
                    {nodeType === 'condition' && (
                        <>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Field
                                </label>
                                <select
                                    value={data.field || ''}
                                    onChange={(e) => onUpdate({ field: e.target.value })}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="">Select field...</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone</option>
                                    <option value="order_total">Order Total</option>
                                    <option value="has_purchased">Has Purchased</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Operator
                                </label>
                                <select
                                    value={data.operator || 'equals'}
                                    onChange={(e) => onUpdate({ operator: e.target.value })}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="equals">Equals</option>
                                    <option value="not_equals">Not Equals</option>
                                    <option value="contains">Contains</option>
                                    <option value="greater_than">Greater Than</option>
                                    <option value="less_than">Less Than</option>
                                    <option value="is_set">Is Set</option>
                                    <option value="is_not_set">Is Not Set</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Value
                                </label>
                                <input
                                    type="text"
                                    value={data.value || ''}
                                    onChange={(e) => onUpdate({ value: e.target.value })}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Value to compare"
                                />
                            </div>
                        </>
                    )}

                    {/* Trigger Step Selection */}
                    {nodeType === 'trigger' && data.triggerType === FUNNEL_TRIGGER_TYPES.PAGE_VIEW && steps.length > 0 && (
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Funnel Step
                            </label>
                            <select
                                value={data.config?.step_id || ''}
                                onChange={(e) => onUpdate({ config: { ...data.config, step_id: e.target.value } })}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Any step</option>
                                {steps.map((step) => (
                                    <option key={step.id} value={step.id}>
                                        {step.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>
            </div>

            {/* Delete Button - Fixed at bottom */}
            {nodeType !== 'trigger' && (
                <div className="p-4 border-t border-gray-100 bg-gray-50">
                    <button
                        onClick={onDelete}
                        className="w-full px-4 py-2.5 text-red-600 hover:text-white hover:bg-red-500 bg-red-50 border border-red-200 hover:border-red-500 rounded-lg font-medium flex items-center justify-center gap-2 transition-all"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Node
                    </button>
                    <p className="text-xs text-gray-400 text-center mt-2">Or press <kbd className="px-1.5 py-0.5 bg-gray-200 rounded text-gray-600 font-mono">Delete</kbd> key</p>
                </div>
            )}

            {/* Trigger-specific message */}
            {nodeType === 'trigger' && (
                <div className="p-4 border-t border-gray-100 bg-green-50">
                    <div className="flex items-start gap-2">
                        <svg className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p className="text-xs text-green-700">
                            This is the trigger node. Every automation starts with a trigger that defines when the automation runs.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}

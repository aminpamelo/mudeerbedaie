import { create } from 'zustand';
import { addEdge, applyNodeChanges, applyEdgeChanges } from '@xyflow/react';

const initialState = {
    workflow: null,
    nodes: [],
    edges: [],
    selectedNode: null,
    isDirty: false,
    isLoading: false,
    error: null,
};

export const useWorkflowStore = create((set, get) => ({
    ...initialState,

    // Workflow actions
    setWorkflow: (workflow) => set({
        workflow,
        nodes: workflow?.canvas_data?.nodes || [],
        edges: workflow?.canvas_data?.edges || [],
        isDirty: false,
    }),

    clearWorkflow: () => set(initialState),

    updateWorkflowSettings: (settings) => set((state) => ({
        workflow: { ...state.workflow, ...settings },
        isDirty: true,
    })),

    // Node actions
    setNodes: (nodes) => set({ nodes, isDirty: true }),

    onNodesChange: (changes) => {
        set({
            nodes: applyNodeChanges(changes, get().nodes),
            isDirty: true,
        });
    },

    addNode: (node) => {
        const newNode = {
            ...node,
            id: node.id || `node_${Date.now()}`,
            position: node.position || { x: 250, y: 100 },
        };
        set((state) => ({
            nodes: [...state.nodes, newNode],
            isDirty: true,
        }));
        return newNode;
    },

    updateNode: (nodeId, data) => {
        set((state) => ({
            nodes: state.nodes.map((node) =>
                node.id === nodeId ? { ...node, data: { ...node.data, ...data } } : node
            ),
            isDirty: true,
        }));
    },

    removeNode: (nodeId) => {
        set((state) => ({
            nodes: state.nodes.filter((node) => node.id !== nodeId),
            edges: state.edges.filter(
                (edge) => edge.source !== nodeId && edge.target !== nodeId
            ),
            selectedNode: state.selectedNode?.id === nodeId ? null : state.selectedNode,
            isDirty: true,
        }));
    },

    // Edge actions
    setEdges: (edges) => set({ edges, isDirty: true }),

    onEdgesChange: (changes) => {
        set({
            edges: applyEdgeChanges(changes, get().edges),
            isDirty: true,
        });
    },

    onConnect: (connection) => {
        set({
            edges: addEdge({
                ...connection,
                id: `edge_${Date.now()}`,
                type: 'smoothstep',
                animated: true,
            }, get().edges),
            isDirty: true,
        });
    },

    removeEdge: (edgeId) => {
        set((state) => ({
            edges: state.edges.filter((edge) => edge.id !== edgeId),
            isDirty: true,
        }));
    },

    // Selection
    setSelectedNode: (node) => set({ selectedNode: node }),

    clearSelection: () => set({ selectedNode: null }),

    // UI State
    setLoading: (isLoading) => set({ isLoading }),

    setError: (error) => set({ error }),

    clearError: () => set({ error: null }),

    markAsSaved: () => set({ isDirty: false }),

    // Canvas data export
    getCanvasData: () => {
        const { nodes, edges } = get();
        return { nodes, edges };
    },

    // Validate workflow
    validateWorkflow: () => {
        const { nodes, edges } = get();
        const errors = [];

        // Check for at least one trigger
        const triggerNodes = nodes.filter((n) => n.type === 'trigger');
        if (triggerNodes.length === 0) {
            errors.push('Workflow must have at least one trigger');
        }

        // Check for disconnected nodes
        const connectedNodeIds = new Set();
        edges.forEach((edge) => {
            connectedNodeIds.add(edge.source);
            connectedNodeIds.add(edge.target);
        });

        const disconnectedNodes = nodes.filter(
            (node) => node.type !== 'trigger' && !connectedNodeIds.has(node.id)
        );

        if (disconnectedNodes.length > 0) {
            errors.push('Some nodes are not connected to the workflow');
        }

        return {
            isValid: errors.length === 0,
            errors,
        };
    },
}));

export default useWorkflowStore;

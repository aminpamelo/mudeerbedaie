import React, { useEffect, useRef, useState } from 'react';
import toast from 'react-hot-toast';
import WorkflowCanvas from './WorkflowCanvas';
import NodePalette from './NodePalette';
import NodeConfigPanel from './NodeConfigPanel';
import { useWorkflowStore } from '../stores/workflowStore';
import { ReactFlowProvider } from '@xyflow/react';

export default function WorkflowBuilder({ workflow, onSave, onPublish, onBack, onDelete }) {
    const {
        setWorkflow,
        getCanvasData,
        validateWorkflow,
        isDirty,
        isLoading,
        setLoading,
        markAsSaved,
    } = useWorkflowStore();

    const [workflowName, setWorkflowName] = useState(workflow?.name || 'Untitled Workflow');
    const [workflowStatus, setWorkflowStatus] = useState(workflow?.status || 'draft');
    const [deleteArmed, setDeleteArmed] = useState(false);
    const deleteTimerRef = useRef(null);

    useEffect(() => {
        return () => {
            if (deleteTimerRef.current) {
                clearTimeout(deleteTimerRef.current);
            }
        };
    }, []);

    const handleDeleteClick = async () => {
        if (!onDelete) return;

        if (!deleteArmed) {
            setDeleteArmed(true);
            deleteTimerRef.current = setTimeout(() => {
                setDeleteArmed(false);
            }, 3000);
            return;
        }

        if (deleteTimerRef.current) {
            clearTimeout(deleteTimerRef.current);
        }
        setDeleteArmed(false);
        await onDelete();
    };

    useEffect(() => {
        if (workflow) {
            setWorkflow(workflow);
            setWorkflowName(workflow.name);
            setWorkflowStatus(workflow.status);
        }
    }, [workflow]);

    const handleSave = async () => {
        const canvasData = getCanvasData();
        setLoading(true);

        try {
            await onSave({
                name: workflowName,
                canvas_data: canvasData,
            });
            markAsSaved();
        } catch (error) {
            console.error('Failed to save workflow:', error);
        } finally {
            setLoading(false);
        }
    };

    const handlePublish = async () => {
        const validation = validateWorkflow();
        if (!validation.isValid) {
            validation.errors.forEach((error) => toast.error(error));
            return;
        }

        if (isDirty) {
            await handleSave();
        }

        setLoading(true);
        try {
            await onPublish();
            setWorkflowStatus('active');
        } catch (error) {
            console.error('Failed to publish workflow:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <ReactFlowProvider>
            <div className="h-screen flex flex-col bg-gray-100">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={onBack}
                            className="text-gray-500 hover:text-gray-700"
                            title="Back to workflows"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <input
                            type="text"
                            value={workflowName}
                            onChange={(e) => setWorkflowName(e.target.value)}
                            className="text-lg font-semibold text-gray-900 border-none focus:ring-0 bg-transparent px-0"
                            placeholder="Workflow name"
                        />
                        <span className={`
                            px-2 py-1 text-xs font-medium rounded-full
                            ${workflowStatus === 'active' ? 'bg-green-100 text-green-800' : ''}
                            ${workflowStatus === 'draft' ? 'bg-gray-100 text-gray-800' : ''}
                            ${workflowStatus === 'paused' ? 'bg-yellow-100 text-yellow-800' : ''}
                        `}>
                            {workflowStatus}
                        </span>
                        {isDirty && (
                            <span className="text-xs text-gray-500">(unsaved changes)</span>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {onDelete && (workflow?.uuid) && (
                            <button
                                type="button"
                                onClick={handleDeleteClick}
                                disabled={isLoading}
                                aria-label={deleteArmed ? 'Confirm delete workflow' : 'Delete workflow'}
                                title={deleteArmed ? 'Click again to confirm' : 'Delete workflow'}
                                className={`inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border transition-colors disabled:opacity-50 ${
                                    deleteArmed
                                        ? 'bg-red-500 text-white border-red-500 hover:bg-red-600'
                                        : 'bg-white text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300'
                                }`}
                            >
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                                {deleteArmed ? 'Confirm delete' : 'Delete'}
                            </button>
                        )}
                        <div className="h-6 w-px bg-gray-200 mx-1" />
                        <button
                            onClick={handleSave}
                            disabled={isLoading}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                        >
                            {isLoading ? 'Saving...' : 'Save'}
                        </button>
                        <button
                            onClick={handlePublish}
                            disabled={isLoading || workflowStatus === 'active'}
                            className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50"
                        >
                            {workflowStatus === 'active' ? 'Published' : 'Publish'}
                        </button>
                    </div>
                </header>

                {/* Main Content */}
                <div className="flex-1 flex overflow-hidden">
                    <NodePalette />
                    <div className="flex-1">
                        <WorkflowCanvas />
                    </div>
                    <NodeConfigPanel />
                </div>
            </div>
        </ReactFlowProvider>
    );
}

import React, { useState, useEffect } from 'react';
import toast, { Toaster } from 'react-hot-toast';
import WorkflowBuilder from './components/WorkflowBuilder';
import { workflowApi } from './services/api';

// Get config from window
const getConfig = () => window.workflowBuilderConfig || {};
const getWorkflowsUrl = () => getConfig().workflowsUrl || '/workflows';
const getAppUrl = () => getConfig().appUrl || '';

export default function App({ workflowUuid }) {
    const [workflow, setWorkflow] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (workflowUuid) {
            loadWorkflow();
        } else {
            setLoading(false);
        }
    }, [workflowUuid]);

    const loadWorkflow = async () => {
        try {
            setLoading(true);
            const response = await workflowApi.get(workflowUuid);
            setWorkflow(response.data);
        } catch (err) {
            setError('Failed to load workflow');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async (data) => {
        try {
            if (workflow?.uuid || workflowUuid) {
                const uuid = workflow?.uuid || workflowUuid;
                const response = await workflowApi.update(uuid, data);
                setWorkflow(response.data);
                toast.success('Workflow saved successfully');
                return response.data;
            } else {
                const response = await workflowApi.create(data);
                setWorkflow(response.data);
                // Update URL with new workflow UUID
                window.history.replaceState(
                    {},
                    '',
                    `${getAppUrl()}/workflow-builder/${response.data.uuid}`
                );
                toast.success('Workflow created successfully');
                return response.data;
            }
        } catch (err) {
            console.error('Failed to save workflow:', err);
            toast.error('Failed to save workflow');
            throw err;
        }
    };

    const handlePublish = async () => {
        try {
            const response = await workflowApi.publish(workflow.uuid);
            setWorkflow(response.data);
            toast.success('Workflow published successfully');
            return response.data;
        } catch (err) {
            console.error('Failed to publish workflow:', err);
            const errorMessage = err.response?.data?.message || 'Failed to publish workflow';
            toast.error(errorMessage);
            throw err;
        }
    };

    const handleBack = () => {
        window.location.href = getWorkflowsUrl();
    };

    if (loading) {
        return (
            <div className="h-screen flex items-center justify-center bg-gray-100">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                    <p className="mt-4 text-gray-600">Loading workflow...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="h-screen flex items-center justify-center bg-gray-100">
                <div className="text-center">
                    <div className="text-red-500 text-6xl mb-4">!</div>
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Error</h2>
                    <p className="text-gray-600">{error}</p>
                    <a
                        href={getWorkflowsUrl()}
                        className="mt-4 inline-block px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    >
                        Back to Workflows
                    </a>
                </div>
            </div>
        );
    }

    return (
        <>
            <Toaster
                position="top-right"
                toastOptions={{
                    duration: 4000,
                    style: {
                        background: '#333',
                        color: '#fff',
                    },
                    success: {
                        iconTheme: {
                            primary: '#10b981',
                            secondary: '#fff',
                        },
                    },
                    error: {
                        iconTheme: {
                            primary: '#ef4444',
                            secondary: '#fff',
                        },
                    },
                }}
            />
            <WorkflowBuilder
                workflow={workflow}
                onSave={handleSave}
                onPublish={handlePublish}
                onBack={handleBack}
            />
        </>
    );
}

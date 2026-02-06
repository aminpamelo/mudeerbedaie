/**
 * Funnel Builder App
 * Main application component for the React SPA funnel builder
 */

import React, { useState, useEffect } from 'react';
import FunnelList from './components/FunnelList';
import FunnelDetail from './components/FunnelDetail';
import FunnelEditor from './components/FunnelEditor';
import { stepApi } from './services/api';

// View states
const VIEWS = {
    LIST: 'list',
    DETAIL: 'detail',
    EDITOR: 'editor',
};

export default function App() {
    const [currentView, setCurrentView] = useState(VIEWS.LIST);
    const [selectedFunnel, setSelectedFunnel] = useState(null);
    const [selectedStep, setSelectedStep] = useState(null);
    const [initialContent, setInitialContent] = useState(null);

    // Handle browser back/forward navigation
    useEffect(() => {
        const handlePopState = async () => {
            const path = window.location.pathname;
            const searchParams = new URLSearchParams(window.location.search);
            const funnelFromQuery = searchParams.get('funnel');

            // Check for funnel query parameter first (from admin page "Edit" links)
            if (funnelFromQuery && path === '/funnel-builder') {
                setSelectedFunnel({ uuid: funnelFromQuery });
                setCurrentView(VIEWS.DETAIL);
                // Clean up URL by removing query parameter
                window.history.replaceState({}, '', `/funnel-builder/${funnelFromQuery}`);
                return;
            }

            if (path.includes('/edit/')) {
                // Editor view
                const matches = path.match(/\/funnel-builder\/([^/]+)\/edit\/(\d+)/);
                if (matches) {
                    const funnelUuid = matches[1];
                    const stepId = parseInt(matches[2]);
                    setSelectedFunnel({ uuid: funnelUuid });
                    setSelectedStep({ id: stepId });

                    // Load initial content when navigating directly to editor URL
                    try {
                        const response = await stepApi.getContent(funnelUuid, stepId);
                        setInitialContent(response.data?.content || { content: [], root: {} });
                    } catch (err) {
                        console.error('Failed to load step content:', err);
                        setInitialContent({ content: [], root: {} });
                    }

                    setCurrentView(VIEWS.EDITOR);
                }
            } else if (path.includes('/funnel-builder/') && path !== '/funnel-builder') {
                // Detail view
                const uuid = path.split('/funnel-builder/')[1];
                setSelectedFunnel({ uuid });
                setCurrentView(VIEWS.DETAIL);
            } else {
                // List view
                setCurrentView(VIEWS.LIST);
                setSelectedFunnel(null);
                setSelectedStep(null);
            }
        };

        window.addEventListener('popstate', handlePopState);

        // Initialize from current URL
        handlePopState();

        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    // Navigate to funnel detail
    const handleSelectFunnel = (funnel) => {
        setSelectedFunnel(funnel);
        setCurrentView(VIEWS.DETAIL);
        window.history.pushState({}, '', `/funnel-builder/${funnel.uuid}`);
    };

    // Navigate back to list
    const handleBackToList = () => {
        setSelectedFunnel(null);
        setSelectedStep(null);
        setCurrentView(VIEWS.LIST);
        window.history.pushState({}, '', '/funnel-builder');
    };

    // Navigate to step editor
    const handleEditStep = async (step) => {
        setSelectedStep(step);
        setCurrentView(VIEWS.EDITOR);

        // Load initial content if available
        try {
            const response = await stepApi.getContent(selectedFunnel.uuid, step.id);
            setInitialContent(response.data?.content || { content: [], root: {} });
        } catch (err) {
            console.error('Failed to load step content:', err);
            setInitialContent({ content: [], root: {} });
        }

        window.history.pushState({}, '', `/funnel-builder/${selectedFunnel.uuid}/edit/${step.id}`);
    };

    // Navigate back to funnel detail from editor
    const handleBackToDetail = () => {
        setSelectedStep(null);
        setInitialContent(null);
        setCurrentView(VIEWS.DETAIL);
        window.history.pushState({}, '', `/funnel-builder/${selectedFunnel.uuid}`);
    };

    // Handle content save
    const handleContentSave = (data) => {
        console.log('Content saved:', data);
    };

    // Render current view
    const renderView = () => {
        switch (currentView) {
            case VIEWS.EDITOR:
                return (
                    <FunnelEditor
                        funnelUuid={selectedFunnel?.uuid}
                        stepId={selectedStep?.id}
                        initialContent={initialContent}
                        onSave={handleContentSave}
                        onBack={handleBackToDetail}
                    />
                );

            case VIEWS.DETAIL:
                return (
                    <FunnelDetail
                        funnelUuid={selectedFunnel?.uuid}
                        onBack={handleBackToList}
                        onEditStep={handleEditStep}
                    />
                );

            case VIEWS.LIST:
            default:
                return (
                    <FunnelList
                        onSelectFunnel={handleSelectFunnel}
                        onCreateFunnel={handleSelectFunnel}
                    />
                );
        }
    };

    return (
        <div className="funnel-builder-app min-h-screen bg-gray-50">
            {currentView === VIEWS.LIST && (
                <header className="bg-white border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <a href="/admin/funnels" className="text-gray-500 hover:text-gray-700">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </a>
                                <h1 className="text-xl font-bold text-gray-900">Funnel Builder</h1>
                            </div>
                            <div className="flex items-center gap-4">
                                <a
                                    href="/docs/funnel-builder"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gray-500 hover:text-gray-700 text-sm"
                                >
                                    Documentation
                                </a>
                            </div>
                        </div>
                    </div>
                </header>
            )}

            <main className={currentView === VIEWS.LIST ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8' : ''}>
                {renderView()}
            </main>
        </div>
    );
}

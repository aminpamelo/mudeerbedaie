/**
 * Funnel Builder App
 * Main application component for the React SPA funnel builder
 */

import React, { useState, useEffect } from 'react';
import FunnelList from './components/FunnelList';
import FunnelDetail from './components/FunnelDetail';
import FunnelEditor from './components/FunnelEditor';
import PixelLibrary from './components/PixelLibrary';
import StudioShell from './components/StudioShell';
import StudioProducts from './components/StudioProducts';
import StudioOrders from './components/StudioOrders';
import StudioReports from './components/StudioReports';
import StudioFacebookAds from './components/StudioFacebookAds';
import { stepApi } from './services/api';

// View states
const VIEWS = {
    LIST: 'list',
    DETAIL: 'detail',
    EDITOR: 'editor',
    PIXELS: 'pixels',
    PRODUCTS: 'products',
    ORDERS: 'orders',
    REPORTS: 'reports',
    FACEBOOK_ADS: 'facebook_ads',
};

// Static SPA pages (must be matched before the /funnel-builder/{uuid} branch)
const STATIC_PATHS = {
    '/funnel-builder/pixel-library': VIEWS.PIXELS,
    '/funnel-builder/products': VIEWS.PRODUCTS,
    '/funnel-builder/orders': VIEWS.ORDERS,
    '/funnel-builder/reports': VIEWS.REPORTS,
    '/funnel-builder/facebook-ads': VIEWS.FACEBOOK_ADS,
};

export default function App() {
    const [currentView, setCurrentView] = useState(VIEWS.LIST);
    const [selectedFunnel, setSelectedFunnel] = useState(null);
    const [selectedStep, setSelectedStep] = useState(null);
    const [initialContent, setInitialContent] = useState(null);
    // Loaded funnel details (name etc.) reported up by FunnelDetail so the
    // shell breadcrumb/pin work even on direct URL loads.
    const [funnelMeta, setFunnelMeta] = useState(null);

    // "Fighter context" = the builder was entered from the Fighter portal — a
    // real fighter (by role), or an admin who opened it via /fighter (carrying
    // ?from=fighter). In this context /fighter IS the funnel list, so every
    // "back" returns there and the builder's own list is never shown. Computed
    // once from the entry URL so internal navigation keeps the context.
    const [fighterContext] = useState(() => {
        if (window.funnelBuilderConfig?.user?.role === 'fighter') return true;
        return new URLSearchParams(window.location.search).get('from') === 'fighter';
    });

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

            if (STATIC_PATHS[path]) {
                // Static studio pages — must be matched before the generic
                // /funnel-builder/{uuid} detail branch below.
                setCurrentView(STATIC_PATHS[path]);
                setSelectedFunnel(null);
                setSelectedStep(null);
            } else if (path.includes('/edit/')) {
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
                // List view — but fighters have no builder list; send them home.
                if (fighterContext) {
                    window.location.href = '/fighter';
                    return;
                }
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

    // Navigate back out of a funnel. Fighters go home to /fighter (their list);
    // admins/employees go to the builder's internal funnel list.
    const handleBackToList = () => {
        if (fighterContext) {
            window.location.href = '/fighter';
            return;
        }
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

    // Navigate to a studio view (sidebar nav). 'list' goes through
    // handleBackToList so fighters are sent home correctly.
    const handleNavigate = (view) => {
        if (view === VIEWS.LIST) {
            handleBackToList();
            return;
        }
        const path = Object.keys(STATIC_PATHS).find((p) => STATIC_PATHS[p] === view);
        if (!path) return;
        setSelectedFunnel(null);
        setSelectedStep(null);
        setCurrentView(view);
        window.history.pushState({}, '', path);
    };

    // Render current view
    const renderView = () => {
        switch (currentView) {
            case VIEWS.PIXELS:
                return <PixelLibrary onBack={handleBackToList} onSelectFunnel={handleSelectFunnel} />;
            case VIEWS.PRODUCTS:
                return <StudioProducts onSelectFunnel={handleSelectFunnel} />;
            case VIEWS.ORDERS:
                return <StudioOrders onSelectFunnel={handleSelectFunnel} />;
            case VIEWS.REPORTS:
                return <StudioReports onSelectFunnel={handleSelectFunnel} />;
            case VIEWS.FACEBOOK_ADS:
                return <StudioFacebookAds />;
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
                        onFunnelLoaded={setFunnelMeta}
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

    // The Puck editor edits light public pages, so it keeps its full-screen
    // light layout; every other view lives inside the persistent Studio Shell.
    if (currentView === VIEWS.EDITOR) {
        return (
            <div className="funnel-builder-app min-h-screen bg-zinc-50 dark:bg-zinc-950">
                {renderView()}
            </div>
        );
    }

    return (
        <div className="funnel-builder-app fs-studio h-screen overflow-hidden">
            <StudioShell
                currentView={currentView}
                funnelUuid={currentView === VIEWS.DETAIL ? selectedFunnel?.uuid : null}
                funnelName={currentView === VIEWS.DETAIL ? (funnelMeta?.uuid === selectedFunnel?.uuid ? funnelMeta?.name : selectedFunnel?.name) : null}
                fighterContext={fighterContext}
                onNavigate={handleNavigate}
                onSelectFunnel={handleSelectFunnel}
            >
                <div className={currentView === VIEWS.LIST ? 'mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8' : ''}>
                    {renderView()}
                </div>
            </StudioShell>
        </div>
    );
}

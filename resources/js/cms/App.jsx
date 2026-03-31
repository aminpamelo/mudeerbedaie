import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import CmsLayout from './layouts/CmsLayout';
import Dashboard from './pages/Dashboard';
import ContentList from './pages/ContentList';
import ContentCreate from './pages/ContentCreate';
import ContentDetail from './pages/ContentDetail';
import ContentEdit from './pages/ContentEdit';
import KanbanBoard from './pages/KanbanBoard';
import ContentCalendar from './pages/ContentCalendar';
import MarkedPosts from './pages/MarkedPosts';
import AdsList from './pages/AdsList';
import AdCampaignDetail from './pages/AdCampaignDetail';
import PerformanceReport from './pages/PerformanceReport';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60 * 5,
            retry: 1,
        },
    },
});

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <BrowserRouter basename="/cms">
                <Routes>
                    <Route element={<CmsLayout />}>
                        <Route index element={<Dashboard />} />
                        <Route path="contents" element={<ContentList />} />
                        <Route path="contents/create" element={<ContentCreate />} />
                        <Route path="contents/:id" element={<ContentDetail />} />
                        <Route path="contents/:id/edit" element={<ContentEdit />} />
                        <Route path="kanban" element={<KanbanBoard />} />
                        <Route path="calendar" element={<ContentCalendar />} />
                        <Route path="ads/marked" element={<MarkedPosts />} />
                        <Route path="ads" element={<AdsList />} />
                        <Route path="ads/:id" element={<AdCampaignDetail />} />
                        <Route path="reports/performance" element={<PerformanceReport />} />
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Route>
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    );
}

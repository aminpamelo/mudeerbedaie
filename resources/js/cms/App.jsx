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
                        {/* More routes added in later tasks */}
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Route>
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    );
}

import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import CmsLayout from './layouts/CmsLayout';
import Dashboard from './pages/Dashboard';
import ContentList from './pages/ContentList';

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
                        {/* More routes added in later tasks */}
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Route>
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    );
}

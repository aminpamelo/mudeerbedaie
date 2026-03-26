import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

// Admin layout & pages
import HrLayout from './layouts/HrLayout';
import Dashboard from './pages/Dashboard';
import EmployeeList from './pages/EmployeeList';
import EmployeeCreate from './pages/EmployeeCreate';
import EmployeeShow from './pages/EmployeeShow';
import EmployeeEdit from './pages/EmployeeEdit';
import Departments from './pages/Departments';
import Positions from './pages/Positions';

// Employee self-service layout & page
import EmployeeAppLayout from './layouts/EmployeeAppLayout';
import MyProfile from './pages/MyProfile';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60 * 5,
            retry: 1,
        },
    },
});

function getIsAdmin() {
    const user = window.hrConfig?.user;
    return user?.role === 'admin';
}

function AdminRoutes() {
    return (
        <Route element={<HrLayout />}>
            <Route index element={<Dashboard />} />
            <Route path="employees" element={<EmployeeList />} />
            <Route path="employees/create" element={<EmployeeCreate />} />
            <Route path="employees/:id" element={<EmployeeShow />} />
            <Route path="employees/:id/edit" element={<EmployeeEdit />} />
            <Route path="departments" element={<Departments />} />
            <Route path="positions" element={<Positions />} />
        </Route>
    );
}

function EmployeeRoutes() {
    return (
        <Route element={<EmployeeAppLayout />}>
            <Route index element={<MyProfile />} />
        </Route>
    );
}

export default function App() {
    const isAdmin = getIsAdmin();

    return (
        <QueryClientProvider client={queryClient}>
            <BrowserRouter basename="/hr">
                <Routes>
                    {isAdmin ? AdminRoutes() : EmployeeRoutes()}
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    );
}

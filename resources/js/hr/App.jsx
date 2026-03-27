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

// Admin - Attendance
import AttendanceDashboard from './pages/attendance/AttendanceDashboard';
import AttendanceRecords from './pages/attendance/AttendanceRecords';
import WorkSchedules from './pages/attendance/WorkSchedules';
import ScheduleAssignments from './pages/attendance/ScheduleAssignments';
import OvertimeManagement from './pages/attendance/OvertimeManagement';
import HolidayCalendar from './pages/attendance/HolidayCalendar';
import AttendanceAnalytics from './pages/attendance/AttendanceAnalytics';
import DepartmentApprovers from './pages/attendance/DepartmentApprovers';

// Admin - Leave
import LeaveDashboard from './pages/leave/LeaveDashboard';
import LeaveRequests from './pages/leave/LeaveRequests';
import LeaveCalendar from './pages/leave/LeaveCalendar';
import LeaveBalances from './pages/leave/LeaveBalances';
import LeaveTypes from './pages/leave/LeaveTypes';
import LeaveEntitlements from './pages/leave/LeaveEntitlements';
import LeaveApprovers from './pages/leave/LeaveApprovers';

// Shared pages
import ClockInOut from './pages/ClockInOut';

// Employee self-service layout & pages
import EmployeeAppLayout from './layouts/EmployeeAppLayout';
import MyProfile from './pages/MyProfile';
import MyAttendance from './pages/my/MyAttendance';
import MyOvertime from './pages/my/MyOvertime';
import MyLeave from './pages/my/MyLeave';
import ApplyLeave from './pages/my/ApplyLeave';

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

            {/* Attendance */}
            <Route path="attendance" element={<AttendanceDashboard />} />
            <Route path="attendance/records" element={<AttendanceRecords />} />
            <Route path="attendance/schedules" element={<WorkSchedules />} />
            <Route path="attendance/assignments" element={<ScheduleAssignments />} />
            <Route path="attendance/overtime" element={<OvertimeManagement />} />
            <Route path="attendance/holidays" element={<HolidayCalendar />} />
            <Route path="attendance/analytics" element={<AttendanceAnalytics />} />
            <Route path="attendance/approvers" element={<DepartmentApprovers />} />

            {/* Leave */}
            <Route path="leave" element={<LeaveDashboard />} />
            <Route path="leave/requests" element={<LeaveRequests />} />
            <Route path="leave/calendar" element={<LeaveCalendar />} />
            <Route path="leave/balances" element={<LeaveBalances />} />
            <Route path="leave/types" element={<LeaveTypes />} />
            <Route path="leave/entitlements" element={<LeaveEntitlements />} />
            <Route path="leave/approvers" element={<LeaveApprovers />} />

            {/* Shared */}
            <Route path="clock" element={<ClockInOut />} />
        </Route>
    );
}

function EmployeeRoutes() {
    return (
        <Route element={<EmployeeAppLayout />}>
            <Route index element={<MyProfile />} />
            <Route path="clock" element={<ClockInOut />} />
            <Route path="my/attendance" element={<MyAttendance />} />
            <Route path="my/overtime" element={<MyOvertime />} />
            <Route path="my/leave" element={<MyLeave />} />
            <Route path="my/leave/apply" element={<ApplyLeave />} />
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

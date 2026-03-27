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

// Admin - Payroll
import PayrollDashboard from './pages/payroll/PayrollDashboard';
import PayrollRun from './pages/payroll/PayrollRun';
import PayrollHistory from './pages/payroll/PayrollHistory';
import SalaryComponents from './pages/payroll/SalaryComponents';
import EmployeeSalaries from './pages/payroll/EmployeeSalaries';
import TaxProfiles from './pages/payroll/TaxProfiles';
import StatutoryRates from './pages/payroll/StatutoryRates';
import PayrollReports from './pages/payroll/PayrollReports';
import PayrollSettings from './pages/payroll/PayrollSettings';
import EaForms from './pages/payroll/EaForms';

// Admin - Claims
import ClaimsDashboard from './pages/claims/ClaimsDashboard';
import ClaimRequests from './pages/claims/ClaimRequests';
import ClaimTypes from './pages/claims/ClaimTypes';
import ClaimApprovers from './pages/claims/ClaimApprovers';
import ClaimsReports from './pages/claims/ClaimsReports';

// Admin - Benefits
import BenefitsManagement from './pages/benefits/BenefitsManagement';
import BenefitTypes from './pages/benefits/BenefitTypes';

// Admin - Assets
import AssetDashboard from './pages/assets/AssetDashboard';
import AssetList from './pages/assets/AssetList';
import AssetCategories from './pages/assets/AssetCategories';
import AssetAssignments from './pages/assets/AssetAssignments';

// Shared pages
import ClockInOut from './pages/ClockInOut';

// Employee self-service layout & pages
import EmployeeAppLayout from './layouts/EmployeeAppLayout';
import MyProfile from './pages/MyProfile';
import MyAttendance from './pages/my/MyAttendance';
import MyOvertime from './pages/my/MyOvertime';
import MyLeave from './pages/my/MyLeave';
import ApplyLeave from './pages/my/ApplyLeave';
import MyPayslips from './pages/my/MyPayslips';
import MyClaims from './pages/my/MyClaims';
import MyAssets from './pages/my/MyAssets';

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

            {/* Payroll */}
            <Route path="payroll" element={<PayrollDashboard />} />
            <Route path="payroll/run/:id" element={<PayrollRun />} />
            <Route path="payroll/history" element={<PayrollHistory />} />
            <Route path="payroll/components" element={<SalaryComponents />} />
            <Route path="payroll/salaries" element={<EmployeeSalaries />} />
            <Route path="payroll/tax-profiles" element={<TaxProfiles />} />
            <Route path="payroll/statutory-rates" element={<StatutoryRates />} />
            <Route path="payroll/reports" element={<PayrollReports />} />
            <Route path="payroll/settings" element={<PayrollSettings />} />
            <Route path="payroll/ea-forms" element={<EaForms />} />

            {/* Claims */}
            <Route path="claims" element={<ClaimsDashboard />} />
            <Route path="claims/requests" element={<ClaimRequests />} />
            <Route path="claims/types" element={<ClaimTypes />} />
            <Route path="claims/approvers" element={<ClaimApprovers />} />
            <Route path="claims/reports" element={<ClaimsReports />} />

            {/* Benefits */}
            <Route path="benefits" element={<BenefitsManagement />} />
            <Route path="benefits/types" element={<BenefitTypes />} />

            {/* Assets */}
            <Route path="assets" element={<AssetDashboard />} />
            <Route path="assets/inventory" element={<AssetList />} />
            <Route path="assets/categories" element={<AssetCategories />} />
            <Route path="assets/assignments" element={<AssetAssignments />} />

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
            <Route path="my/payslips" element={<MyPayslips />} />
            <Route path="my/claims" element={<MyClaims />} />
            <Route path="my/assets" element={<MyAssets />} />
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

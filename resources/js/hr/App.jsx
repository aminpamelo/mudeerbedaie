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
import OrgChart from './pages/OrgChart';

// Admin - Attendance
import AttendanceDashboard from './pages/attendance/AttendanceDashboard';
import AttendanceRecords from './pages/attendance/AttendanceRecords';
import WorkSchedules from './pages/attendance/WorkSchedules';
import ScheduleAssignments from './pages/attendance/ScheduleAssignments';
import OvertimeManagement from './pages/attendance/OvertimeManagement';
import HolidayCalendar from './pages/attendance/HolidayCalendar';
import AttendanceAnalytics from './pages/attendance/AttendanceAnalytics';
import DepartmentApprovers from './pages/attendance/DepartmentApprovers';
import AttendanceSettings from './pages/attendance/AttendanceSettings';

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

// Admin - Recruitment & Onboarding
import RecruitmentDashboard from './pages/recruitment/RecruitmentDashboard';
import JobPostings from './pages/recruitment/JobPostings';
import JobPostingDetail from './pages/recruitment/JobPostingDetail';
import Applicants from './pages/recruitment/Applicants';
import ApplicantDetail from './pages/recruitment/ApplicantDetail';
import Interviews from './pages/recruitment/Interviews';
import OnboardingDashboard from './pages/recruitment/OnboardingDashboard';
import OnboardingTemplates from './pages/recruitment/OnboardingTemplates';

// Admin - Performance Management
import PerformanceDashboard from './pages/performance/PerformanceDashboard';
import ReviewCycles from './pages/performance/ReviewCycles';
import ReviewCycleDetail from './pages/performance/ReviewCycleDetail';
import KpiTemplates from './pages/performance/KpiTemplates';
import ReviewDetail from './pages/performance/ReviewDetail';
import PipManagement from './pages/performance/PipManagement';
import PipDetail from './pages/performance/PipDetail';
import RatingScaleConfig from './pages/performance/RatingScaleConfig';

// Admin - Meetings (MOM)
import MeetingList from './pages/meetings/MeetingList';
import MeetingCreate from './pages/meetings/MeetingCreate';
import MeetingDetail from './pages/meetings/MeetingDetail';
import MeetingEdit from './pages/meetings/MeetingEdit';
import MeetingRecord from './pages/meetings/MeetingRecord';
import MeetingSeriesList from './pages/meetings/MeetingSeriesList';
import TaskDashboard from './pages/meetings/TaskDashboard';

// Admin - Disciplinary & Offboarding
import DisciplinaryDashboard from './pages/disciplinary/DisciplinaryDashboard';
import DisciplinaryRecords from './pages/disciplinary/DisciplinaryRecords';
import DisciplinaryDetail from './pages/disciplinary/DisciplinaryDetail';
import CreateDisciplinaryAction from './pages/disciplinary/CreateDisciplinaryAction';
import LetterTemplates from './pages/disciplinary/LetterTemplates';
import ResignationRequests from './pages/offboarding/ResignationRequests';
import ResignationDetail from './pages/offboarding/ResignationDetail';
import ExitChecklists from './pages/offboarding/ExitChecklists';
import ExitInterviews from './pages/offboarding/ExitInterviews';
import FinalSettlements from './pages/offboarding/FinalSettlements';
import SettlementDetail from './pages/offboarding/SettlementDetail';

// Admin - Training & Development
import TrainingDashboard from './pages/training/TrainingDashboard';
import TrainingPrograms from './pages/training/TrainingPrograms';
import TrainingDetail from './pages/training/TrainingDetail';
import Certifications from './pages/training/Certifications';
import EmployeeCertifications from './pages/training/EmployeeCertifications';
import TrainingBudgets from './pages/training/TrainingBudgets';
import TrainingReports from './pages/training/TrainingReports';

// Shared pages
import ClockInOut from './pages/ClockInOut';
import Notifications from './pages/Notifications';

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
import MyMeetings from './pages/my/MyMeetings';
import MyTasks from './pages/my/MyTasks';
import MyReviews from './pages/my/MyReviews';
import MyReviewDetail from './pages/my/MyReviewDetail';
import MyPip from './pages/my/MyPip';
import MyOnboarding from './pages/my/MyOnboarding';
import MyDisciplinary from './pages/my/MyDisciplinary';
import MyResignation from './pages/my/MyResignation';
import MyTraining from './pages/my/MyTraining';

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
            <Route path="org-chart" element={<OrgChart />} />

            {/* Attendance */}
            <Route path="attendance" element={<AttendanceDashboard />} />
            <Route path="attendance/records" element={<AttendanceRecords />} />
            <Route path="attendance/schedules" element={<WorkSchedules />} />
            <Route path="attendance/assignments" element={<ScheduleAssignments />} />
            <Route path="attendance/overtime" element={<OvertimeManagement />} />
            <Route path="attendance/holidays" element={<HolidayCalendar />} />
            <Route path="attendance/analytics" element={<AttendanceAnalytics />} />
            <Route path="attendance/approvers" element={<DepartmentApprovers />} />
            <Route path="attendance/settings" element={<AttendanceSettings />} />

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

            {/* Recruitment & Onboarding */}
            <Route path="recruitment" element={<RecruitmentDashboard />} />
            <Route path="recruitment/postings" element={<JobPostings />} />
            <Route path="recruitment/postings/:id" element={<JobPostingDetail />} />
            <Route path="recruitment/applicants" element={<Applicants />} />
            <Route path="recruitment/applicants/:id" element={<ApplicantDetail />} />
            <Route path="recruitment/interviews" element={<Interviews />} />
            <Route path="onboarding" element={<OnboardingDashboard />} />
            <Route path="onboarding/templates" element={<OnboardingTemplates />} />

            {/* Performance Management */}
            <Route path="performance" element={<PerformanceDashboard />} />
            <Route path="performance/cycles" element={<ReviewCycles />} />
            <Route path="performance/cycles/:id" element={<ReviewCycleDetail />} />
            <Route path="performance/kpis" element={<KpiTemplates />} />
            <Route path="performance/reviews/:id" element={<ReviewDetail />} />
            <Route path="performance/pips" element={<PipManagement />} />
            <Route path="performance/pips/:id" element={<PipDetail />} />
            <Route path="performance/rating-scales" element={<RatingScaleConfig />} />

            {/* Meetings (MOM) */}
            <Route path="meetings" element={<MeetingList />} />
            <Route path="meetings/create" element={<MeetingCreate />} />
            <Route path="meetings/:id" element={<MeetingDetail />} />
            <Route path="meetings/:id/edit" element={<MeetingEdit />} />
            <Route path="meetings/:id/record" element={<MeetingRecord />} />
            <Route path="meetings/series" element={<MeetingSeriesList />} />
            <Route path="meetings/tasks" element={<TaskDashboard />} />

            {/* Disciplinary */}
            <Route path="disciplinary" element={<DisciplinaryDashboard />} />
            <Route path="disciplinary/records" element={<DisciplinaryRecords />} />
            <Route path="disciplinary/actions/create" element={<CreateDisciplinaryAction />} />
            <Route path="disciplinary/actions/:id" element={<DisciplinaryDetail />} />
            <Route path="disciplinary/letter-templates" element={<LetterTemplates />} />

            {/* Offboarding */}
            <Route path="offboarding/resignations" element={<ResignationRequests />} />
            <Route path="offboarding/resignations/:id" element={<ResignationDetail />} />
            <Route path="offboarding/checklists" element={<ExitChecklists />} />
            <Route path="offboarding/exit-interviews" element={<ExitInterviews />} />
            <Route path="offboarding/settlements" element={<FinalSettlements />} />
            <Route path="offboarding/settlements/:id" element={<SettlementDetail />} />

            {/* Training & Development */}
            <Route path="training" element={<TrainingDashboard />} />
            <Route path="training/programs" element={<TrainingPrograms />} />
            <Route path="training/programs/:id" element={<TrainingDetail />} />
            <Route path="training/certifications" element={<Certifications />} />
            <Route path="training/employee-certifications" element={<EmployeeCertifications />} />
            <Route path="training/budgets" element={<TrainingBudgets />} />
            <Route path="training/reports" element={<TrainingReports />} />

            {/* Shared */}
            <Route path="clock" element={<ClockInOut />} />
            <Route path="notifications" element={<Notifications />} />
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
            <Route path="my/meetings" element={<MyMeetings />} />
            <Route path="my/tasks" element={<MyTasks />} />
            <Route path="my/reviews" element={<MyReviews />} />
            <Route path="my/reviews/:id" element={<MyReviewDetail />} />
            <Route path="my/pip" element={<MyPip />} />
            <Route path="my/onboarding" element={<MyOnboarding />} />
            <Route path="my/disciplinary" element={<MyDisciplinary />} />
            <Route path="my/resignation" element={<MyResignation />} />
            <Route path="my/training" element={<MyTraining />} />
            <Route path="notifications" element={<Notifications />} />
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

import { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from './components/ErrorBoundary';
import { ToastProvider } from './components/Toast';
import { RouteFallback } from './components/RouteFallback';

// Layouts — kept eager so the shell renders instantly while routes lazy-load.
import HrLayout from './layouts/HrLayout';
import EmployeeAppLayout from './layouts/EmployeeAppLayout';

// ─── Admin pages (lazy) ──────────────────────────────────────────────────────
const Dashboard = lazy(() => import('./pages/Dashboard'));
const EmployeeList = lazy(() => import('./pages/EmployeeList'));
const EmployeeCreate = lazy(() => import('./pages/EmployeeCreate'));
const EmployeeShow = lazy(() => import('./pages/EmployeeShow'));
const EmployeeEdit = lazy(() => import('./pages/EmployeeEdit'));
const Departments = lazy(() => import('./pages/Departments'));
const Positions = lazy(() => import('./pages/Positions'));
const OrgChart = lazy(() => import('./pages/OrgChart'));

// Admin - Attendance
const AttendanceDashboard = lazy(() => import('./pages/attendance/AttendanceDashboard'));
const AttendanceRecords = lazy(() => import('./pages/attendance/AttendanceRecords'));
const AttendanceMonthlyView = lazy(() => import('./pages/attendance/AttendanceMonthlyView'));
const WorkSchedules = lazy(() => import('./pages/attendance/WorkSchedules'));
const ScheduleAssignments = lazy(() => import('./pages/attendance/ScheduleAssignments'));
const OvertimeManagement = lazy(() => import('./pages/attendance/OvertimeManagement'));
const HolidayCalendar = lazy(() => import('./pages/attendance/HolidayCalendar'));
const AttendanceAnalytics = lazy(() => import('./pages/attendance/AttendanceAnalytics'));
const DepartmentApprovers = lazy(() => import('./pages/attendance/DepartmentApprovers'));
const AttendanceSettings = lazy(() => import('./pages/attendance/AttendanceSettings'));

// Admin - Leave
const LeaveDashboard = lazy(() => import('./pages/leave/LeaveDashboard'));
const LeaveRequests = lazy(() => import('./pages/leave/LeaveRequests'));
const LeaveCalendar = lazy(() => import('./pages/leave/LeaveCalendar'));
const LeaveBalances = lazy(() => import('./pages/leave/LeaveBalances'));
const LeaveTypes = lazy(() => import('./pages/leave/LeaveTypes'));
const LeaveEntitlements = lazy(() => import('./pages/leave/LeaveEntitlements'));
const LeaveApprovers = lazy(() => import('./pages/leave/LeaveApprovers'));

// Admin - Payroll
const PayrollDashboard = lazy(() => import('./pages/payroll/PayrollDashboard'));
const PayrollRun = lazy(() => import('./pages/payroll/PayrollRun'));
const PayrollHistory = lazy(() => import('./pages/payroll/PayrollHistory'));
const SalaryComponents = lazy(() => import('./pages/payroll/SalaryComponents'));
const EmployeeSalaries = lazy(() => import('./pages/payroll/EmployeeSalaries'));
const TaxProfiles = lazy(() => import('./pages/payroll/TaxProfiles'));
const StatutoryRates = lazy(() => import('./pages/payroll/StatutoryRates'));
const PayrollReports = lazy(() => import('./pages/payroll/PayrollReports'));
const PayrollSettings = lazy(() => import('./pages/payroll/PayrollSettings'));
const EaForms = lazy(() => import('./pages/payroll/EaForms'));

// Admin - Claims
const ClaimsDashboard = lazy(() => import('./pages/claims/ClaimsDashboard'));
const ClaimRequests = lazy(() => import('./pages/claims/ClaimRequests'));
const ClaimTypes = lazy(() => import('./pages/claims/ClaimTypes'));
const ClaimApprovers = lazy(() => import('./pages/claims/ClaimApprovers'));
const ClaimsReports = lazy(() => import('./pages/claims/ClaimsReports'));

// Admin - Benefits
const BenefitsManagement = lazy(() => import('./pages/benefits/BenefitsManagement'));
const BenefitTypes = lazy(() => import('./pages/benefits/BenefitTypes'));

// Admin - Assets
const AssetDashboard = lazy(() => import('./pages/assets/AssetDashboard'));
const AssetList = lazy(() => import('./pages/assets/AssetList'));
const AssetCategories = lazy(() => import('./pages/assets/AssetCategories'));
const AssetAssignments = lazy(() => import('./pages/assets/AssetAssignments'));

// Admin - Recruitment & Onboarding
const RecruitmentDashboard = lazy(() => import('./pages/recruitment/RecruitmentDashboard'));
const JobPostings = lazy(() => import('./pages/recruitment/JobPostings'));
const JobPostingDetail = lazy(() => import('./pages/recruitment/JobPostingDetail'));
const Applicants = lazy(() => import('./pages/recruitment/Applicants'));
const ApplicantDetail = lazy(() => import('./pages/recruitment/ApplicantDetail'));
const Interviews = lazy(() => import('./pages/recruitment/Interviews'));
const OnboardingDashboard = lazy(() => import('./pages/recruitment/OnboardingDashboard'));
const OnboardingTemplates = lazy(() => import('./pages/recruitment/OnboardingTemplates'));

// Admin - Performance Management
const PerformanceDashboard = lazy(() => import('./pages/performance/PerformanceDashboard'));
const ReviewCycles = lazy(() => import('./pages/performance/ReviewCycles'));
const ReviewCycleDetail = lazy(() => import('./pages/performance/ReviewCycleDetail'));
const KpiTemplates = lazy(() => import('./pages/performance/KpiTemplates'));
const ReviewDetail = lazy(() => import('./pages/performance/ReviewDetail'));
const PipManagement = lazy(() => import('./pages/performance/PipManagement'));
const PipDetail = lazy(() => import('./pages/performance/PipDetail'));
const RatingScaleConfig = lazy(() => import('./pages/performance/RatingScaleConfig'));

// Admin - Meetings (MOM)
const MeetingList = lazy(() => import('./pages/meetings/MeetingList'));
const MeetingCreate = lazy(() => import('./pages/meetings/MeetingCreate'));
const MeetingDetail = lazy(() => import('./pages/meetings/MeetingDetail'));
const MeetingEdit = lazy(() => import('./pages/meetings/MeetingEdit'));
const MeetingRecord = lazy(() => import('./pages/meetings/MeetingRecord'));
const MeetingSeriesList = lazy(() => import('./pages/meetings/MeetingSeriesList'));
const TaskDashboard = lazy(() => import('./pages/meetings/TaskDashboard'));

// Admin - Disciplinary & Offboarding
const DisciplinaryDashboard = lazy(() => import('./pages/disciplinary/DisciplinaryDashboard'));
const DisciplinaryRecords = lazy(() => import('./pages/disciplinary/DisciplinaryRecords'));
const DisciplinaryDetail = lazy(() => import('./pages/disciplinary/DisciplinaryDetail'));
const CreateDisciplinaryAction = lazy(() => import('./pages/disciplinary/CreateDisciplinaryAction'));
const LetterTemplates = lazy(() => import('./pages/disciplinary/LetterTemplates'));
const ResignationRequests = lazy(() => import('./pages/offboarding/ResignationRequests'));
const ResignationDetail = lazy(() => import('./pages/offboarding/ResignationDetail'));
const ExitChecklists = lazy(() => import('./pages/offboarding/ExitChecklists'));
const ExitInterviews = lazy(() => import('./pages/offboarding/ExitInterviews'));
const FinalSettlements = lazy(() => import('./pages/offboarding/FinalSettlements'));
const SettlementDetail = lazy(() => import('./pages/offboarding/SettlementDetail'));

// Admin - Training & Development
const TrainingDashboard = lazy(() => import('./pages/training/TrainingDashboard'));
const TrainingPrograms = lazy(() => import('./pages/training/TrainingPrograms'));
const TrainingDetail = lazy(() => import('./pages/training/TrainingDetail'));
const Certifications = lazy(() => import('./pages/training/Certifications'));
const EmployeeCertifications = lazy(() => import('./pages/training/EmployeeCertifications'));
const TrainingBudgets = lazy(() => import('./pages/training/TrainingBudgets'));
const TrainingReports = lazy(() => import('./pages/training/TrainingReports'));

// Admin - Settings
const PwaSettings = lazy(() => import('./pages/settings/PwaSettings'));

// Shared
const ClockInOut = lazy(() => import('./pages/ClockInOut'));
const Notifications = lazy(() => import('./pages/Notifications'));

// Employee self-service pages
const MyProfile = lazy(() => import('./pages/MyProfile'));
const MyAttendance = lazy(() => import('./pages/my/MyAttendance'));
const MyOvertime = lazy(() => import('./pages/my/MyOvertime'));
const MyLeave = lazy(() => import('./pages/my/MyLeave'));
const ApplyLeave = lazy(() => import('./pages/my/ApplyLeave'));
const MyPayslips = lazy(() => import('./pages/my/MyPayslips'));
const MyClaims = lazy(() => import('./pages/my/MyClaims'));
const MyAssets = lazy(() => import('./pages/my/MyAssets'));
const MyMeetings = lazy(() => import('./pages/my/MyMeetings'));
const MyTasks = lazy(() => import('./pages/my/MyTasks'));
const MyReviews = lazy(() => import('./pages/my/MyReviews'));
const MyReviewDetail = lazy(() => import('./pages/my/MyReviewDetail'));
const MyPip = lazy(() => import('./pages/my/MyPip'));
const MyOnboarding = lazy(() => import('./pages/my/MyOnboarding'));
const MyDisciplinary = lazy(() => import('./pages/my/MyDisciplinary'));
const MyResignation = lazy(() => import('./pages/my/MyResignation'));
const MyTraining = lazy(() => import('./pages/my/MyTraining'));
const MyApprovals = lazy(() => import('./pages/my/MyApprovals'));
const MyApprovalsOvertime = lazy(() => import('./pages/my/MyApprovalsOvertime'));
const MyApprovalsLeave = lazy(() => import('./pages/my/MyApprovalsLeave'));
const MyApprovalsClaims = lazy(() => import('./pages/my/MyApprovalsClaims'));
const ExitPermissions = lazy(() => import('./pages/exitpermissions/ExitPermissions'));
const ExitPermissionNotifiers = lazy(() => import('./pages/exitpermissions/ExitPermissionNotifiers'));
const MyExitPermissions = lazy(() => import('./pages/my/MyExitPermissions'));
const ApplyExitPermission = lazy(() => import('./pages/my/ApplyExitPermission'));
const MyApprovalsExitPermissions = lazy(() => import('./pages/my/MyApprovalsExitPermissions'));

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
            <Route path="attendance/monthly" element={<AttendanceMonthlyView />} />
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

            {/* Exit Permissions */}
            <Route path="exit-permissions" element={<ExitPermissions />} />
            <Route path="exit-permissions/notifiers" element={<ExitPermissionNotifiers />} />

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

            {/* Settings */}
            <Route path="settings/pwa" element={<PwaSettings />} />

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
            <Route path="my/exit-permissions" element={<MyExitPermissions />} />
            <Route path="my/exit-permissions/apply" element={<ApplyExitPermission />} />
            <Route path="my/approvals" element={<MyApprovals />} />
            <Route path="my/approvals/overtime" element={<MyApprovalsOvertime />} />
            <Route path="my/approvals/leave" element={<MyApprovalsLeave />} />
            <Route path="my/approvals/claims" element={<MyApprovalsClaims />} />
            <Route path="my/approvals/exit-permissions" element={<MyApprovalsExitPermissions />} />
            <Route path="notifications" element={<Notifications />} />
        </Route>
    );
}

export default function App() {
    const isAdmin = getIsAdmin();

    return (
        <QueryClientProvider client={queryClient}>
            <ToastProvider>
                <BrowserRouter basename="/hr">
                    <ErrorBoundary>
                        <Suspense fallback={<RouteFallback />}>
                            <Routes>
                                {isAdmin ? AdminRoutes() : EmployeeRoutes()}
                                <Route path="*" element={<Navigate to="/" replace />} />
                            </Routes>
                        </Suspense>
                    </ErrorBoundary>
                </BrowserRouter>
            </ToastProvider>
        </QueryClientProvider>
    );
}

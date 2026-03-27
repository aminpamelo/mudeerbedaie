import axios from 'axios';

const config = window.hrConfig || {};

const api = axios.create({
    baseURL: config.apiBaseUrl || '/api/hr',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
        if (error.response?.status === 419) {
            window.location.reload();
        }
        return Promise.reject(error);
    }
);

// ========== Dashboard ==========
export const fetchDashboardStats = () => api.get('/dashboard/stats').then(r => r.data);
export const fetchRecentActivity = () => api.get('/dashboard/recent-activity').then(r => r.data);
export const fetchHeadcountByDepartment = () => api.get('/dashboard/headcount-by-department').then(r => r.data);

// ========== Employees ==========
export const fetchEmployees = (params) => api.get('/employees', { params }).then(r => r.data);
export const fetchEmployee = (id) => api.get(`/employees/${id}`).then(r => r.data);
export const createEmployee = (data) => api.post('/employees', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const updateEmployee = (id, data) => api.put(`/employees/${id}`, data).then(r => r.data);
export const updateEmployeeStatus = (id, data) => api.patch(`/employees/${id}/status`, data).then(r => r.data);
export const deleteEmployee = (id) => api.delete(`/employees/${id}`).then(r => r.data);
export const fetchNextEmployeeId = () => api.get('/employees/next-id').then(r => r.data);
export const exportEmployees = (params) => api.get('/employees/export', { params, responseType: 'blob' }).then(r => r.data);

// ========== Employee Sub-resources ==========
export const fetchEmployeeHistory = (id) => api.get(`/employees/${id}/history`).then(r => r.data);
export const fetchEmployeeDocuments = (id) => api.get(`/employees/${id}/documents`).then(r => r.data);
export const uploadEmployeeDocument = (id, data) => api.post(`/employees/${id}/documents`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const deleteEmployeeDocument = (employeeId, docId) => api.delete(`/employees/${employeeId}/documents/${docId}`).then(r => r.data);
export const fetchEmergencyContacts = (id) => api.get(`/employees/${id}/emergency-contacts`).then(r => r.data);
export const createEmergencyContact = (id, data) => api.post(`/employees/${id}/emergency-contacts`, data).then(r => r.data);
export const updateEmergencyContact = (employeeId, contactId, data) => api.put(`/employees/${employeeId}/emergency-contacts/${contactId}`, data).then(r => r.data);
export const deleteEmergencyContact = (employeeId, contactId) => api.delete(`/employees/${employeeId}/emergency-contacts/${contactId}`).then(r => r.data);

// ========== Departments ==========
export const fetchDepartments = (params) => api.get('/departments', { params }).then(r => r.data);
export const fetchDepartment = (id) => api.get(`/departments/${id}`).then(r => r.data);
export const createDepartment = (data) => api.post('/departments', data).then(r => r.data);
export const updateDepartment = (id, data) => api.put(`/departments/${id}`, data).then(r => r.data);
export const deleteDepartment = (id) => api.delete(`/departments/${id}`).then(r => r.data);
export const fetchDepartmentTree = () => api.get('/departments/tree').then(r => r.data);
export const fetchDepartmentEmployees = (id) => api.get(`/departments/${id}/employees`).then(r => r.data);

// ========== Positions ==========
export const fetchPositions = (params) => api.get('/positions', { params }).then(r => r.data);
export const fetchPosition = (id) => api.get(`/positions/${id}`).then(r => r.data);
export const createPosition = (data) => api.post('/positions', data).then(r => r.data);
export const updatePosition = (id, data) => api.put(`/positions/${id}`, data).then(r => r.data);
export const deletePosition = (id) => api.delete(`/positions/${id}`).then(r => r.data);

// ========== Leave Dashboard ==========
export const fetchLeaveDashboardStats = () => api.get('/leave/dashboard/stats').then(r => r.data);
export const fetchPendingLeaveRequests = (params) => api.get('/leave/dashboard/pending', { params }).then(r => r.data);
export const fetchLeaveDistribution = (params) => api.get('/leave/dashboard/distribution', { params }).then(r => r.data);

// ========== Leave Requests ==========
export const fetchLeaveRequests = (params) => api.get('/leave/requests', { params }).then(r => r.data);
export const fetchLeaveRequest = (id) => api.get(`/leave/requests/${id}`).then(r => r.data);
export const approveLeaveRequest = (id, data) => api.patch(`/leave/requests/${id}/approve`, data).then(r => r.data);
export const rejectLeaveRequest = (id, data) => api.patch(`/leave/requests/${id}/reject`, data).then(r => r.data);
export const exportLeaveRequests = (params) => api.get('/leave/requests/export', { params, responseType: 'blob' }).then(r => r.data);

// ========== Leave Calendar ==========
export const fetchLeaveCalendar = (params) => api.get('/leave/calendar', { params }).then(r => r.data);
export const fetchLeaveOverlaps = (params) => api.get('/leave/calendar/overlaps', { params }).then(r => r.data);

// ========== Leave Balances ==========
export const fetchLeaveBalances = (params) => api.get('/leave/balances', { params }).then(r => r.data);
export const fetchEmployeeLeaveBalance = (id, params) => api.get(`/leave/balances/${id}`, { params }).then(r => r.data);
export const initializeLeaveBalances = (data) => api.post('/leave/balances/initialize', data).then(r => r.data);
export const adjustLeaveBalance = (id, data) => api.post(`/leave/balances/${id}/adjust`, data).then(r => r.data);
export const exportLeaveBalances = (params) => api.get('/leave/balances/export', { params, responseType: 'blob' }).then(r => r.data);

// ========== Leave Types ==========
export const fetchLeaveTypes = (params) => api.get('/leave/types', { params }).then(r => r.data);
export const createLeaveType = (data) => api.post('/leave/types', data).then(r => r.data);
export const updateLeaveType = (id, data) => api.put(`/leave/types/${id}`, data).then(r => r.data);
export const deleteLeaveType = (id) => api.delete(`/leave/types/${id}`).then(r => r.data);

// ========== Leave Entitlements ==========
export const fetchLeaveEntitlements = (params) => api.get('/leave/entitlements', { params }).then(r => r.data);
export const createLeaveEntitlement = (data) => api.post('/leave/entitlements', data).then(r => r.data);
export const updateLeaveEntitlement = (id, data) => api.put(`/leave/entitlements/${id}`, data).then(r => r.data);
export const deleteLeaveEntitlement = (id) => api.delete(`/leave/entitlements/${id}`).then(r => r.data);
export const recalculateEntitlements = () => api.post('/leave/entitlements/recalculate').then(r => r.data);


// ========== Work Schedules ==========
export const fetchSchedules = (params) => api.get('/schedules', { params }).then(r => r.data);
export const fetchSchedule = (id) => api.get(`/schedules/${id}`).then(r => r.data);
export const createSchedule = (data) => api.post('/schedules', data).then(r => r.data);
export const updateSchedule = (id, data) => api.put(`/schedules/${id}`, data).then(r => r.data);
export const deleteSchedule = (id) => api.delete(`/schedules/${id}`).then(r => r.data);
export const fetchScheduleEmployees = (id) => api.get(`/schedules/${id}/employees`).then(r => r.data);

// ========== Employee Schedules ==========
export const fetchEmployeeSchedules = (params) => api.get('/employee-schedules', { params }).then(r => r.data);
export const assignEmployeeSchedule = (data) => api.post('/employee-schedules', data).then(r => r.data);
export const updateEmployeeSchedule = (id, data) => api.put(`/employee-schedules/${id}`, data).then(r => r.data);
export const deleteEmployeeSchedule = (id) => api.delete(`/employee-schedules/${id}`).then(r => r.data);

// ========== Attendance ==========
export const fetchAttendance = (params) => api.get('/attendance', { params }).then(r => r.data);
export const fetchTodayAttendance = () => api.get('/attendance/today').then(r => r.data);
export const fetchAttendanceLog = (id) => api.get(`/attendance/${id}`).then(r => r.data);
export const updateAttendanceLog = (id, data) => api.put(`/attendance/${id}`, data).then(r => r.data);
export const exportAttendance = (params) => api.get('/attendance/export', { params, responseType: 'blob' }).then(r => r.data);

// ========== Attendance Analytics ==========
export const fetchAnalyticsOverview = () => api.get('/attendance/analytics/overview').then(r => r.data);
export const fetchAnalyticsTrends = (params) => api.get('/attendance/analytics/trends', { params }).then(r => r.data);
export const fetchAnalyticsDepartment = (params) => api.get('/attendance/analytics/department', { params }).then(r => r.data);
export const fetchAnalyticsPunctuality = (params) => api.get('/attendance/analytics/punctuality', { params }).then(r => r.data);
export const fetchAnalyticsOvertime = (params) => api.get('/attendance/analytics/overtime', { params }).then(r => r.data);

// ========== Overtime ==========
export const fetchOvertimeRequests = (params) => api.get('/overtime', { params }).then(r => r.data);
export const fetchOvertimeRequest = (id) => api.get(`/overtime/${id}`).then(r => r.data);
export const approveOvertime = (id) => api.patch(`/overtime/${id}/approve`).then(r => r.data);
export const rejectOvertime = (id, data) => api.patch(`/overtime/${id}/reject`, data).then(r => r.data);
export const completeOvertime = (id, data) => api.patch(`/overtime/${id}/complete`, data).then(r => r.data);

// ========== Holidays ==========
export const fetchHolidays = (params) => api.get('/holidays', { params }).then(r => r.data);
export const createHoliday = (data) => api.post('/holidays', data).then(r => r.data);
export const updateHoliday = (id, data) => api.put(`/holidays/${id}`, data).then(r => r.data);
export const deleteHoliday = (id) => api.delete(`/holidays/${id}`).then(r => r.data);
export const bulkImportHolidays = (data) => api.post('/holidays/bulk-import', data).then(r => r.data);

// ========== Department Approvers ==========
export const fetchDepartmentApprovers = (params) => api.get('/department-approvers', { params }).then(r => r.data);
export const createDepartmentApprover = (data) => api.post('/department-approvers', data).then(r => r.data);
export const updateDepartmentApprover = (id, data) => api.put(`/department-approvers/${id}`, data).then(r => r.data);
export const deleteDepartmentApprover = (id) => api.delete(`/department-approvers/${id}`).then(r => r.data);

// ========== Penalties ==========
export const fetchPenalties = (params) => api.get('/penalties', { params }).then(r => r.data);
export const fetchFlaggedEmployees = () => api.get('/penalties/flagged').then(r => r.data);
export const fetchPenaltySummary = (params) => api.get('/penalties/summary', { params }).then(r => r.data);

// ========== My Profile (Self-Service) ==========
export const fetchMyProfile = () => api.get('/me').then(r => r.data);
export const updateMyProfile = (data) => api.put('/me', data).then(r => r.data);
export const fetchMyDocuments = () => api.get('/me/documents').then(r => r.data);
export const uploadMyDocument = (data) => api.post('/me/documents', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const fetchMyEmergencyContacts = () => api.get('/me/emergency-contacts').then(r => r.data);
export const createMyEmergencyContact = (data) => api.post('/me/emergency-contacts', data).then(r => r.data);
export const updateMyEmergencyContact = (id, data) => api.put(`/me/emergency-contacts/${id}`, data).then(r => r.data);
export const deleteMyEmergencyContact = (id) => api.delete(`/me/emergency-contacts/${id}`).then(r => r.data);

// ========== My Attendance (Self-Service) ==========
export const fetchMyAttendance = (params) => api.get('/me/attendance', { params }).then(r => r.data);
export const clockIn = (data) => api.post('/me/attendance/clock-in', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const clockOut = (data) => api.post('/me/attendance/clock-out', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const fetchMyTodayAttendance = () => api.get('/me/attendance/today').then(r => r.data);
export const fetchMyAttendanceSummary = (params) => api.get('/me/attendance/summary', { params }).then(r => r.data);
export const fetchMyOvertime = (params) => api.get('/me/overtime', { params }).then(r => r.data);
export const submitMyOvertime = (data) => api.post('/me/overtime', data).then(r => r.data);
export const fetchMyOvertimeBalance = () => api.get('/me/overtime/balance').then(r => r.data);
export const cancelMyOvertime = (id) => api.delete(`/me/overtime/${id}`).then(r => r.data);

// ========== My Leave (Self-Service) ==========
export const fetchMyLeaveBalances = () => api.get('/me/leave/balances').then(r => r.data);
export const fetchMyLeaveRequests = (params) => api.get('/me/leave/requests', { params }).then(r => r.data);
export const applyForLeave = (data) => api.post('/me/leave/requests', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const cancelMyLeave = (id) => api.delete(`/me/leave/requests/${id}`).then(r => r.data);
export const calculateLeaveDays = (params) => api.get('/me/leave/calculate-days', { params }).then(r => r.data);

// ========== Payroll Dashboard ==========
export const fetchPayrollDashboardStats = () => api.get('/payroll/dashboard/stats').then(r => r.data);
export const fetchPayrollTrend = () => api.get('/payroll/dashboard/trend').then(r => r.data);
export const fetchStatutoryBreakdown = () => api.get('/payroll/dashboard/statutory-breakdown').then(r => r.data);

// ========== Payroll Runs ==========
export const fetchPayrollRuns = (params) => api.get('/payroll/runs', { params }).then(r => r.data);
export const fetchPayrollRun = (id) => api.get(`/payroll/runs/${id}`).then(r => r.data);
export const createPayrollRun = (data) => api.post('/payroll/runs', data).then(r => r.data);
export const deletePayrollRun = (id) => api.delete(`/payroll/runs/${id}`).then(r => r.data);
export const calculatePayroll = (id) => api.post(`/payroll/runs/${id}/calculate`).then(r => r.data);
export const calculatePayrollEmployee = (runId, empId) => api.post(`/payroll/runs/${runId}/calculate/${empId}`).then(r => r.data);
export const submitPayrollReview = (id) => api.patch(`/payroll/runs/${id}/submit-review`).then(r => r.data);
export const approvePayroll = (id) => api.patch(`/payroll/runs/${id}/approve`).then(r => r.data);
export const returnPayrollDraft = (id) => api.patch(`/payroll/runs/${id}/return-draft`).then(r => r.data);
export const finalizePayroll = (id) => api.patch(`/payroll/runs/${id}/finalize`).then(r => r.data);

// ========== Payroll Items ==========
export const addPayrollItem = (runId, data) => api.post(`/payroll/runs/${runId}/items`, data).then(r => r.data);
export const updatePayrollItem = (runId, itemId, data) => api.put(`/payroll/runs/${runId}/items/${itemId}`, data).then(r => r.data);
export const deletePayrollItem = (runId, itemId) => api.delete(`/payroll/runs/${runId}/items/${itemId}`).then(r => r.data);

// ========== Salary Components ==========
export const fetchSalaryComponents = (params) => api.get('/payroll/components', { params }).then(r => r.data);
export const createSalaryComponent = (data) => api.post('/payroll/components', data).then(r => r.data);
export const updateSalaryComponent = (id, data) => api.put(`/payroll/components/${id}`, data).then(r => r.data);
export const deleteSalaryComponent = (id) => api.delete(`/payroll/components/${id}`).then(r => r.data);

// ========== Employee Salaries ==========
export const fetchEmployeeSalaries = (params) => api.get('/payroll/salaries', { params }).then(r => r.data);
export const fetchEmployeeSalary = (employeeId) => api.get(`/payroll/salaries/${employeeId}`).then(r => r.data);
export const createEmployeeSalary = (data) => api.post('/payroll/salaries', data).then(r => r.data);
export const updateEmployeeSalary = (id, data) => api.put(`/payroll/salaries/${id}`, data).then(r => r.data);
export const fetchSalaryRevisions = (employeeId) => api.get(`/payroll/salaries/${employeeId}/revisions`).then(r => r.data);
export const bulkSalaryRevision = (data) => api.post('/payroll/salaries/bulk-revision', data).then(r => r.data);

// ========== Tax Profiles ==========
export const fetchTaxProfiles = (params) => api.get('/payroll/tax-profiles', { params }).then(r => r.data);
export const fetchTaxProfile = (employeeId) => api.get(`/payroll/tax-profiles/${employeeId}`).then(r => r.data);
export const updateTaxProfile = (employeeId, data) => api.put(`/payroll/tax-profiles/${employeeId}`, data).then(r => r.data);

// ========== Statutory Rates ==========
export const fetchStatutoryRates = (params) => api.get('/payroll/statutory-rates', { params }).then(r => r.data);
export const updateStatutoryRate = (id, data) => api.put(`/payroll/statutory-rates/${id}`, data).then(r => r.data);
export const bulkUpdateStatutoryRates = (data) => api.post('/payroll/statutory-rates/bulk-update', data).then(r => r.data);

// ========== Payslips ==========
export const fetchPayslips = (params) => api.get('/payroll/payslips', { params }).then(r => r.data);
export const fetchPayslip = (id) => api.get(`/payroll/payslips/${id}`).then(r => r.data);
export const downloadPayslipPdf = (id) => api.get(`/payroll/payslips/${id}/pdf`, { responseType: 'blob' }).then(r => r.data);
export const downloadBulkPayslipsPdf = (runId) => api.get(`/payroll/payslips/bulk-pdf/${runId}`, { responseType: 'blob' }).then(r => r.data);

// ========== Payroll Reports ==========
export const fetchPayrollMonthlySummary = (params) => api.get('/payroll/reports/monthly-summary', { params }).then(r => r.data);
export const fetchPayrollStatutoryReport = (params) => api.get('/payroll/reports/statutory', { params }).then(r => r.data);
export const fetchPayrollBankPayment = (params) => api.get('/payroll/reports/bank-payment', { params }).then(r => r.data);
export const fetchPayrollYtd = (params) => api.get('/payroll/reports/ytd', { params }).then(r => r.data);
export const downloadEaForm = (employeeId) => api.get(`/payroll/reports/ea-form/${employeeId}`, { responseType: 'blob' }).then(r => r.data);
export const downloadEaForms = (year) => api.get(`/payroll/reports/ea-forms/${year}`, { responseType: 'blob' }).then(r => r.data);

// ========== Payroll Settings ==========
export const fetchPayrollSettings = () => api.get('/payroll/settings').then(r => r.data);
export const updatePayrollSettings = (data) => api.put('/payroll/settings', data).then(r => r.data);

// ========== My Payslips (Self-Service) ==========
export const fetchMyPayslips = (params) => api.get('/me/payslips', { params }).then(r => r.data);
export const fetchMyPayslip = (id) => api.get(`/me/payslips/${id}`).then(r => r.data);
export const downloadMyPayslipPdf = (id) => api.get(`/me/payslips/${id}/pdf`, { responseType: 'blob' }).then(r => r.data);
export const fetchMyPayslipYtd = () => api.get('/me/payslips/ytd').then(r => r.data);

// ========== Claims Dashboard ==========
export const fetchClaimsDashboardStats = () => api.get('/claims/dashboard/stats').then(r => r.data);
export const fetchPendingClaimRequests = (params) => api.get('/claims/dashboard/pending', { params }).then(r => r.data);
export const fetchClaimsDistribution = (params) => api.get('/claims/dashboard/distribution', { params }).then(r => r.data);

// ========== Claim Types ==========
export const fetchClaimTypes = (params) => api.get('/claims/types', { params }).then(r => r.data);
export const createClaimType = (data) => api.post('/claims/types', data).then(r => r.data);
export const updateClaimType = (id, data) => api.put(`/claims/types/${id}`, data).then(r => r.data);
export const deleteClaimType = (id) => api.delete(`/claims/types/${id}`).then(r => r.data);

// ========== Claim Requests ==========
export const fetchClaimRequests = (params) => api.get('/claims/requests', { params }).then(r => r.data);
export const fetchClaimRequest = (id) => api.get(`/claims/requests/${id}`).then(r => r.data);
export const approveClaimRequest = (id, data) => api.patch(`/claims/requests/${id}/approve`, data).then(r => r.data);
export const rejectClaimRequest = (id, data) => api.patch(`/claims/requests/${id}/reject`, data).then(r => r.data);
export const markClaimPaid = (id, data) => api.patch(`/claims/requests/${id}/pay`, data).then(r => r.data);
export const exportClaimRequests = (params) => api.get('/claims/requests/export', { params, responseType: 'blob' }).then(r => r.data);

// ========== Claim Approvers ==========
export const fetchClaimApprovers = (params) => api.get('/claims/approvers', { params }).then(r => r.data);
export const createClaimApprover = (data) => api.post('/claims/approvers', data).then(r => r.data);
export const deleteClaimApprover = (id) => api.delete(`/claims/approvers/${id}`).then(r => r.data);

// ========== Claims Reports ==========
export const fetchClaimsReport = (params) => api.get('/claims/reports', { params }).then(r => r.data);
export const exportClaimsReport = (params) => api.get('/claims/reports/export', { params, responseType: 'blob' }).then(r => r.data);

// ========== My Claims (Self-Service) ==========
export const fetchMyClaims = (params) => api.get('/me/claims', { params }).then(r => r.data);
export const fetchMyClaimLimits = () => api.get('/me/claims/limits').then(r => r.data);
export const createMyClaim = (data) => api.post('/me/claims', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const submitMyClaim = (id) => api.post(`/me/claims/${id}/submit`).then(r => r.data);
export const deleteMyClaim = (id) => api.delete(`/me/claims/${id}`).then(r => r.data);

// ========== Benefits ==========
export const fetchBenefitTypes = (params) => api.get('/benefits/types', { params }).then(r => r.data);
export const createBenefitType = (data) => api.post('/benefits/types', data).then(r => r.data);
export const updateBenefitType = (id, data) => api.put(`/benefits/types/${id}`, data).then(r => r.data);
export const deleteBenefitType = (id) => api.delete(`/benefits/types/${id}`).then(r => r.data);
export const fetchEmployeeBenefits = (params) => api.get('/benefits', { params }).then(r => r.data);
export const createEmployeeBenefit = (data) => api.post('/benefits', data).then(r => r.data);
export const updateEmployeeBenefit = (id, data) => api.put(`/benefits/${id}`, data).then(r => r.data);
export const deleteEmployeeBenefit = (id) => api.delete(`/benefits/${id}`).then(r => r.data);

// ========== Assets ==========
export const fetchAssetCategories = (params) => api.get('/assets/categories', { params }).then(r => r.data);
export const createAssetCategory = (data) => api.post('/assets/categories', data).then(r => r.data);
export const updateAssetCategory = (id, data) => api.put(`/assets/categories/${id}`, data).then(r => r.data);
export const deleteAssetCategory = (id) => api.delete(`/assets/categories/${id}`).then(r => r.data);
export const fetchAssets = (params) => api.get('/assets', { params }).then(r => r.data);
export const fetchAsset = (id) => api.get(`/assets/${id}`).then(r => r.data);
export const createAsset = (data) => api.post('/assets', data).then(r => r.data);
export const updateAsset = (id, data) => api.put(`/assets/${id}`, data).then(r => r.data);
export const deleteAsset = (id) => api.delete(`/assets/${id}`).then(r => r.data);
export const fetchAssetAssignments = (params) => api.get('/assets/assignments', { params }).then(r => r.data);
export const createAssetAssignment = (data) => api.post('/assets/assignments', data).then(r => r.data);
export const returnAsset = (id, data) => api.put(`/assets/assignments/${id}/return`, data).then(r => r.data);

// ========== My Assets (Self-Service) ==========
export const fetchMyAssets = () => api.get('/me/assets').then(r => r.data);

export default api;

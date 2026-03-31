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
export const fetchUnlinkedUsers = (params) => api.get('/employees/unlinked-users', { params }).then(r => r.data);
export const exportEmployees = (params) => api.get('/employees/export', { params, responseType: 'blob' }).then(r => r.data);
export const uploadProfilePhoto = (id, data) => api.post(`/employees/${id}/photo`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const removeProfilePhoto = (id) => api.delete(`/employees/${id}/photo`).then(r => r.data);

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

// ========== Organization Chart ==========
export const fetchOrgChart = () => api.get('/org-chart').then(r => r.data);

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
export const fetchPositionEmployees = (id) => api.get(`/positions/${id}/employees`).then(r => r.data);
export const assignPositionEmployees = (id, employeeIds) => api.post(`/positions/${id}/assign-employees`, { employee_ids: employeeIds }).then(r => r.data);
export const removePositionEmployee = (positionId, employeeId) => api.delete(`/positions/${positionId}/employees/${employeeId}`).then(r => r.data);

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

// ========== HR Settings ==========
export const fetchOfficeLocation = () => api.get('/settings/office-location').then(r => r.data);
export const updateOfficeLocation = (data) => api.put('/settings/office-location', data).then(r => r.data);

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
export const approveClaimRequest = (id, data) => api.post(`/claims/requests/${id}/approve`, data).then(r => r.data);
export const rejectClaimRequest = (id, data) => api.post(`/claims/requests/${id}/reject`, data).then(r => r.data);
export const markClaimPaid = (id, data) => api.post(`/claims/requests/${id}/mark-paid`, data).then(r => r.data);
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

// ========== Recruitment Dashboard ==========
export const fetchRecruitmentDashboard = () => api.get('/recruitment/dashboard').then(r => r.data);

// ========== Job Postings ==========
export const fetchJobPostings = (params) => api.get('/recruitment/postings', { params }).then(r => r.data);
export const fetchJobPosting = (id) => api.get(`/recruitment/postings/${id}`).then(r => r.data);
export const createJobPosting = (data) => api.post('/recruitment/postings', data).then(r => r.data);
export const updateJobPosting = (id, data) => api.put(`/recruitment/postings/${id}`, data).then(r => r.data);
export const deleteJobPosting = (id) => api.delete(`/recruitment/postings/${id}`).then(r => r.data);
export const publishJobPosting = (id) => api.patch(`/recruitment/postings/${id}/publish`).then(r => r.data);
export const closeJobPosting = (id) => api.patch(`/recruitment/postings/${id}/close`).then(r => r.data);

// ========== Applicants ==========
export const fetchApplicants = (params) => api.get('/recruitment/applicants', { params }).then(r => r.data);
export const fetchApplicant = (id) => api.get(`/recruitment/applicants/${id}`).then(r => r.data);
export const createApplicant = (data) => api.post('/recruitment/applicants', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const updateApplicant = (id, data) => api.put(`/recruitment/applicants/${id}`, data).then(r => r.data);
export const moveApplicantStage = (id, data) => api.patch(`/recruitment/applicants/${id}/stage`, data).then(r => r.data);
export const hireApplicant = (id, data) => api.post(`/recruitment/applicants/${id}/hire`, data).then(r => r.data);

// ========== Interviews ==========
export const fetchInterviews = (params) => api.get('/recruitment/interviews', { params }).then(r => r.data);
export const createInterview = (data) => api.post('/recruitment/interviews', data).then(r => r.data);
export const updateInterview = (id, data) => api.put(`/recruitment/interviews/${id}`, data).then(r => r.data);
export const deleteInterview = (id) => api.delete(`/recruitment/interviews/${id}`).then(r => r.data);
export const submitInterviewFeedback = (id, data) => api.put(`/recruitment/interviews/${id}/feedback`, data).then(r => r.data);

// ========== Offer Letters ==========
export const createOfferLetter = (data) => api.post('/recruitment/offers', data).then(r => r.data);
export const fetchOfferLetter = (id) => api.get(`/recruitment/offers/${id}`).then(r => r.data);
export const updateOfferLetter = (id, data) => api.put(`/recruitment/offers/${id}`, data).then(r => r.data);
export const sendOfferLetter = (id) => api.post(`/recruitment/offers/${id}/send`).then(r => r.data);
export const respondOfferLetter = (id, data) => api.patch(`/recruitment/offers/${id}/respond`, data).then(r => r.data);

// ========== Onboarding ==========
export const fetchOnboardingDashboard = () => api.get('/onboarding/dashboard').then(r => r.data);
export const assignOnboarding = (employeeId, data) => api.post(`/onboarding/assign/${employeeId}`, data).then(r => r.data);
export const fetchOnboardingTasks = (employeeId) => api.get(`/onboarding/tasks/${employeeId}`).then(r => r.data);
export const updateOnboardingTask = (taskId, data) => api.patch(`/onboarding/tasks/${taskId}`, data).then(r => r.data);

// ========== Onboarding Templates ==========
export const fetchOnboardingTemplates = () => api.get('/onboarding/templates').then(r => r.data);
export const createOnboardingTemplate = (data) => api.post('/onboarding/templates', data).then(r => r.data);
export const updateOnboardingTemplate = (id, data) => api.put(`/onboarding/templates/${id}`, data).then(r => r.data);
export const deleteOnboardingTemplate = (id) => api.delete(`/onboarding/templates/${id}`).then(r => r.data);

// ========== Performance Dashboard ==========
export const fetchPerformanceDashboard = () => api.get('/performance/dashboard').then(r => r.data);

// ========== Review Cycles ==========
export const fetchReviewCycles = (params) => api.get('/performance/cycles', { params }).then(r => r.data);
export const fetchReviewCycle = (id) => api.get(`/performance/cycles/${id}`).then(r => r.data);
export const createReviewCycle = (data) => api.post('/performance/cycles', data).then(r => r.data);
export const updateReviewCycle = (id, data) => api.put(`/performance/cycles/${id}`, data).then(r => r.data);
export const deleteReviewCycle = (id) => api.delete(`/performance/cycles/${id}`).then(r => r.data);
export const activateReviewCycle = (id) => api.patch(`/performance/cycles/${id}/activate`).then(r => r.data);
export const completeReviewCycle = (id) => api.patch(`/performance/cycles/${id}/complete`).then(r => r.data);

// ========== KPI Templates ==========
export const fetchKpiTemplates = (params) => api.get('/performance/kpis', { params }).then(r => r.data);
export const createKpiTemplate = (data) => api.post('/performance/kpis', data).then(r => r.data);
export const updateKpiTemplate = (id, data) => api.put(`/performance/kpis/${id}`, data).then(r => r.data);
export const deleteKpiTemplate = (id) => api.delete(`/performance/kpis/${id}`).then(r => r.data);

// ========== Performance Reviews ==========
export const fetchPerformanceReviews = (params) => api.get('/performance/reviews', { params }).then(r => r.data);
export const fetchPerformanceReview = (id) => api.get(`/performance/reviews/${id}`).then(r => r.data);
export const addReviewKpi = (reviewId, data) => api.post(`/performance/reviews/${reviewId}/kpis`, data).then(r => r.data);
export const submitSelfAssessment = (reviewId, data) => api.put(`/performance/reviews/${reviewId}/self-assessment`, data).then(r => r.data);
export const submitManagerReview = (reviewId, data) => api.put(`/performance/reviews/${reviewId}/manager-review`, data).then(r => r.data);
export const completeReview = (reviewId) => api.patch(`/performance/reviews/${reviewId}/complete`).then(r => r.data);
export const acknowledgeReview = (reviewId) => api.patch(`/performance/reviews/${reviewId}/acknowledge`).then(r => r.data);

// ========== PIPs ==========
export const fetchPips = (params) => api.get('/performance/pips', { params }).then(r => r.data);
export const fetchPip = (id) => api.get(`/performance/pips/${id}`).then(r => r.data);
export const createPip = (data) => api.post('/performance/pips', data).then(r => r.data);
export const updatePip = (id, data) => api.put(`/performance/pips/${id}`, data).then(r => r.data);
export const extendPip = (id, data) => api.patch(`/performance/pips/${id}/extend`, data).then(r => r.data);
export const completePip = (id, data) => api.patch(`/performance/pips/${id}/complete`, data).then(r => r.data);
export const addPipGoal = (pipId, data) => api.post(`/performance/pips/${pipId}/goals`, data).then(r => r.data);
export const updatePipGoal = (pipId, goalId, data) => api.put(`/performance/pips/${pipId}/goals/${goalId}`, data).then(r => r.data);

// ========== Rating Scales ==========
export const fetchRatingScales = () => api.get('/performance/rating-scales').then(r => r.data);
export const updateRatingScales = (data) => api.put('/performance/rating-scales', data).then(r => r.data);

// ========== My Reviews (Employee Self-Service) ==========
export const fetchMyReviews = () => api.get('/me/reviews').then(r => r.data);
export const fetchMyReview = (id) => api.get(`/me/reviews/${id}`).then(r => r.data);
export const submitMySelfAssessment = (reviewId, data) => api.put(`/me/reviews/${reviewId}/self-assessment`, data).then(r => r.data);
export const fetchMyPip = () => api.get('/me/pip').then(r => r.data);

// ========== My Onboarding (Employee Self-Service) ==========
export const fetchMyOnboarding = () => api.get('/me/onboarding').then(r => r.data);

// ========== Meetings ==========
export const fetchMeetings = (params) => api.get('/meetings', { params }).then(r => r.data);
export const fetchMeeting = (id) => api.get(`/meetings/${id}`).then(r => r.data);
export const createMeeting = (data) => api.post('/meetings', data).then(r => r.data);
export const updateMeeting = (id, data) => api.put(`/meetings/${id}`, data).then(r => r.data);
export const deleteMeeting = (id) => api.delete(`/meetings/${id}`).then(r => r.data);
export const updateMeetingStatus = (id, data) => api.patch(`/meetings/${id}/status`, data).then(r => r.data);

// ========== Meeting Series ==========
export const fetchMeetingSeries = () => api.get('/meetings/series').then(r => r.data);
export const createMeetingSeries = (data) => api.post('/meetings/series', data).then(r => r.data);
export const fetchMeetingSeriesDetail = (id) => api.get(`/meetings/series/${id}`).then(r => r.data);

// ========== Meeting Attendees ==========
export const addMeetingAttendees = (meetingId, data) => api.post(`/meetings/${meetingId}/attendees`, data).then(r => r.data);
export const removeMeetingAttendee = (meetingId, employeeId) => api.delete(`/meetings/${meetingId}/attendees/${employeeId}`).then(r => r.data);
export const updateAttendeeStatus = (meetingId, employeeId, data) => api.patch(`/meetings/${meetingId}/attendees/${employeeId}`, data).then(r => r.data);

// ========== Meeting Agenda ==========
export const addAgendaItem = (meetingId, data) => api.post(`/meetings/${meetingId}/agenda-items`, data).then(r => r.data);
export const updateAgendaItem = (meetingId, itemId, data) => api.put(`/meetings/${meetingId}/agenda-items/${itemId}`, data).then(r => r.data);
export const deleteAgendaItem = (meetingId, itemId) => api.delete(`/meetings/${meetingId}/agenda-items/${itemId}`).then(r => r.data);
export const reorderAgendaItems = (meetingId, data) => api.patch(`/meetings/${meetingId}/agenda-items/reorder`, data).then(r => r.data);

// ========== Meeting Decisions ==========
export const addDecision = (meetingId, data) => api.post(`/meetings/${meetingId}/decisions`, data).then(r => r.data);
export const updateDecision = (meetingId, decId, data) => api.put(`/meetings/${meetingId}/decisions/${decId}`, data).then(r => r.data);
export const deleteDecision = (meetingId, decId) => api.delete(`/meetings/${meetingId}/decisions/${decId}`).then(r => r.data);

// ========== Meeting Attachments ==========
export const uploadMeetingAttachment = (meetingId, formData) => api.post(`/meetings/${meetingId}/attachments`, formData, { headers: { 'Content-Type': undefined } }).then(r => r.data);
export const deleteMeetingAttachment = (meetingId, attId) => api.delete(`/meetings/${meetingId}/attachments/${attId}`).then(r => r.data);

// ========== Meeting Recording & AI ==========
export const uploadRecording = (meetingId, formData) => api.post(`/meetings/${meetingId}/recordings`, formData, { headers: { 'Content-Type': undefined } }).then(r => r.data);
export const deleteRecording = (meetingId, recId) => api.delete(`/meetings/${meetingId}/recordings/${recId}`).then(r => r.data);
export const triggerTranscription = (meetingId, recId) => api.post(`/meetings/${meetingId}/recordings/${recId}/transcribe`).then(r => r.data);
export const fetchTranscript = (meetingId) => api.get(`/meetings/${meetingId}/transcript`).then(r => r.data);
export const triggerAiAnalysis = (meetingId) => api.post(`/meetings/${meetingId}/ai-analyze`).then(r => r.data);
export const fetchAiSummary = (meetingId) => api.get(`/meetings/${meetingId}/ai-summary`).then(r => r.data);
export const approveAiTasks = (meetingId, data) => api.post(`/meetings/${meetingId}/ai-summary/approve-tasks`, data).then(r => r.data);

// ========== Tasks ==========
export const fetchMeetingTasks = (params) => api.get('/tasks', { params }).then(r => r.data);
export const fetchMeetingTask = (id) => api.get(`/tasks/${id}`).then(r => r.data);
export const createMeetingTask = (meetingId, data) => api.post(`/meetings/${meetingId}/tasks`, data).then(r => r.data);
export const updateMeetingTaskItem = (id, data) => api.put(`/tasks/${id}`, data).then(r => r.data);
export const updateTaskStatus = (id, data) => api.patch(`/tasks/${id}/status`, data).then(r => r.data);
export const deleteMeetingTask = (id) => api.delete(`/tasks/${id}`).then(r => r.data);
export const createSubtask = (taskId, data) => api.post(`/tasks/${taskId}/subtasks`, data).then(r => r.data);
export const addTaskComment = (taskId, data) => api.post(`/tasks/${taskId}/comments`, data).then(r => r.data);
export const uploadTaskAttachment = (taskId, formData) => api.post(`/tasks/${taskId}/attachments`, formData, { headers: { 'Content-Type': undefined } }).then(r => r.data);

// ========== My Meetings & Tasks ==========
export const fetchMyMeetings = (params) => api.get('/my/meetings', { params }).then(r => r.data);
export const fetchMyMeetingTasks = (params) => api.get('/my/tasks', { params }).then(r => r.data);

// ========== Disciplinary Dashboard ==========
export const fetchDisciplinaryDashboard = () => api.get('/disciplinary/dashboard').then(r => r.data);

// ========== Disciplinary Actions ==========
export const fetchDisciplinaryActions = (params) => api.get('/disciplinary/actions', { params }).then(r => r.data);
export const fetchDisciplinaryAction = (id) => api.get(`/disciplinary/actions/${id}`).then(r => r.data);
export const createDisciplinaryAction = (data) => api.post('/disciplinary/actions', data).then(r => r.data);
export const updateDisciplinaryAction = (id, data) => api.put(`/disciplinary/actions/${id}`, data).then(r => r.data);
export const issueDisciplinaryAction = (id) => api.patch(`/disciplinary/actions/${id}/issue`).then(r => r.data);
export const closeDisciplinaryAction = (id) => api.patch(`/disciplinary/actions/${id}/close`).then(r => r.data);
export const downloadDisciplinaryPdf = (id) => api.get(`/disciplinary/actions/${id}/pdf`, { responseType: 'blob' }).then(r => r.data);
export const fetchEmployeeDisciplinaryHistory = (employeeId) => api.get(`/disciplinary/employee/${employeeId}`).then(r => r.data);

// ========== Disciplinary Inquiries ==========
export const createDisciplinaryInquiry = (data) => api.post('/disciplinary/inquiries', data).then(r => r.data);
export const fetchDisciplinaryInquiry = (id) => api.get(`/disciplinary/inquiries/${id}`).then(r => r.data);
export const updateDisciplinaryInquiry = (id, data) => api.put(`/disciplinary/inquiries/${id}`, data).then(r => r.data);
export const completeDisciplinaryInquiry = (id, data) => api.patch(`/disciplinary/inquiries/${id}/complete`, data).then(r => r.data);

// ========== Resignations ==========
export const fetchResignations = (params) => api.get('/offboarding/resignations', { params }).then(r => r.data);
export const createResignation = (data) => api.post('/offboarding/resignations', data).then(r => r.data);
export const fetchResignation = (id) => api.get(`/offboarding/resignations/${id}`).then(r => r.data);
export const approveResignation = (id, data) => api.patch(`/offboarding/resignations/${id}/approve`, data).then(r => r.data);
export const rejectResignation = (id, data) => api.patch(`/offboarding/resignations/${id}/reject`, data).then(r => r.data);
export const completeResignation = (id) => api.patch(`/offboarding/resignations/${id}/complete`).then(r => r.data);

// ========== Exit Checklists ==========
export const fetchExitChecklists = (params) => api.get('/offboarding/checklists', { params }).then(r => r.data);
export const createExitChecklist = (employeeId) => api.post(`/offboarding/checklists/${employeeId}`).then(r => r.data);
export const fetchExitChecklist = (id) => api.get(`/offboarding/checklists/${id}`).then(r => r.data);
export const updateExitChecklistItem = (checklistId, itemId, data) => api.patch(`/offboarding/checklists/${checklistId}/items/${itemId}`, data).then(r => r.data);

// ========== Exit Interviews ==========
export const fetchExitInterviews = (params) => api.get('/offboarding/exit-interviews', { params }).then(r => r.data);
export const createExitInterview = (data) => api.post('/offboarding/exit-interviews', data).then(r => r.data);
export const fetchExitInterview = (id) => api.get(`/offboarding/exit-interviews/${id}`).then(r => r.data);
export const updateExitInterview = (id, data) => api.put(`/offboarding/exit-interviews/${id}`, data).then(r => r.data);
export const fetchExitInterviewAnalytics = () => api.get('/offboarding/exit-interviews/analytics').then(r => r.data);

// ========== Final Settlements ==========
export const fetchFinalSettlements = (params) => api.get('/offboarding/settlements', { params }).then(r => r.data);
export const calculateFinalSettlement = (employeeId, data) => api.post(`/offboarding/settlements/${employeeId}/calculate`, data).then(r => r.data);
export const fetchFinalSettlement = (id) => api.get(`/offboarding/settlements/${id}`).then(r => r.data);
export const updateFinalSettlement = (id, data) => api.put(`/offboarding/settlements/${id}`, data).then(r => r.data);
export const approveFinalSettlement = (id) => api.patch(`/offboarding/settlements/${id}/approve`).then(r => r.data);
export const markSettlementPaid = (id) => api.patch(`/offboarding/settlements/${id}/paid`).then(r => r.data);
export const downloadSettlementPdf = (id) => api.get(`/offboarding/settlements/${id}/pdf`, { responseType: 'blob' }).then(r => r.data);

// ========== Letter Templates ==========
export const fetchLetterTemplates = (params) => api.get('/letter-templates', { params }).then(r => r.data);
export const createLetterTemplate = (data) => api.post('/letter-templates', data).then(r => r.data);
export const updateLetterTemplate = (id, data) => api.put(`/letter-templates/${id}`, data).then(r => r.data);
export const deleteLetterTemplate = (id) => api.delete(`/letter-templates/${id}`).then(r => r.data);

// ========== Training Dashboard ==========
export const fetchTrainingDashboard = () => api.get('/training/dashboard').then(r => r.data);

// ========== Training Programs ==========
export const fetchTrainingPrograms = (params) => api.get('/training/programs', { params }).then(r => r.data);
export const fetchTrainingProgram = (id) => api.get(`/training/programs/${id}`).then(r => r.data);
export const createTrainingProgram = (data) => api.post('/training/programs', data).then(r => r.data);
export const updateTrainingProgram = (id, data) => api.put(`/training/programs/${id}`, data).then(r => r.data);
export const deleteTrainingProgram = (id) => api.delete(`/training/programs/${id}`).then(r => r.data);
export const completeTrainingProgram = (id) => api.patch(`/training/programs/${id}/complete`).then(r => r.data);

// ========== Training Enrollments ==========
export const fetchTrainingEnrollments = (params) => api.get('/training/enrollments', { params }).then(r => r.data);
export const enrollEmployees = (programId, data) => api.post(`/training/programs/${programId}/enroll`, data).then(r => r.data);
export const updateTrainingEnrollment = (id, data) => api.patch(`/training/enrollments/${id}`, data).then(r => r.data);
export const deleteTrainingEnrollment = (id) => api.delete(`/training/enrollments/${id}`).then(r => r.data);
export const submitEnrollmentFeedback = (id, data) => api.put(`/training/enrollments/${id}/feedback`, data).then(r => r.data);

// ========== Training Costs ==========
export const fetchTrainingCosts = (programId) => api.get(`/training/programs/${programId}/costs`).then(r => r.data);
export const createTrainingCost = (programId, data) => api.post(`/training/programs/${programId}/costs`, data).then(r => r.data);
export const updateTrainingCost = (id, data) => api.put(`/training/costs/${id}`, data).then(r => r.data);
export const deleteTrainingCost = (id) => api.delete(`/training/costs/${id}`).then(r => r.data);

// ========== Certifications ==========
export const fetchCertifications = (params) => api.get('/training/certifications', { params }).then(r => r.data);
export const createCertification = (data) => api.post('/training/certifications', data).then(r => r.data);
export const updateCertification = (id, data) => api.put(`/training/certifications/${id}`, data).then(r => r.data);
export const deleteCertification = (id) => api.delete(`/training/certifications/${id}`).then(r => r.data);

// ========== Employee Certifications ==========
export const fetchEmployeeCertifications = (params) => api.get('/training/employee-certifications', { params }).then(r => r.data);
export const createEmployeeCertification = (data) => api.post('/training/employee-certifications', data).then(r => r.data);
export const updateEmployeeCertification = (id, data) => api.put(`/training/employee-certifications/${id}`, data).then(r => r.data);
export const deleteEmployeeCertification = (id) => api.delete(`/training/employee-certifications/${id}`).then(r => r.data);
export const fetchExpiringCertifications = (params) => api.get('/training/employee-certifications/expiring', { params }).then(r => r.data);

// ========== Training Budgets ==========
export const fetchTrainingBudgets = (params) => api.get('/training/budgets', { params }).then(r => r.data);
export const createTrainingBudget = (data) => api.post('/training/budgets', data).then(r => r.data);
export const updateTrainingBudget = (id, data) => api.put(`/training/budgets/${id}`, data).then(r => r.data);

// ========== Training Reports ==========
export const fetchTrainingReports = (params) => api.get('/training/reports', { params }).then(r => r.data);

// ========== My Disciplinary (Employee Self-Service) ==========
export const fetchMyDisciplinary = () => api.get('/me/disciplinary').then(r => r.data);
export const respondToDisciplinary = (id, data) => api.post(`/me/disciplinary/${id}/respond`, data).then(r => r.data);

// ========== My Resignation (Employee Self-Service) ==========
export const submitMyResignation = (data) => api.post('/me/resignation', data).then(r => r.data);
export const fetchMyResignation = () => api.get('/me/resignation').then(r => r.data);

// ========== My Training (Employee Self-Service) ==========
export const fetchMyTraining = () => api.get('/me/training').then(r => r.data);
export const submitMyTrainingFeedback = (enrollmentId, data) => api.put(`/me/training/${enrollmentId}/feedback`, data).then(r => r.data);

export default api;

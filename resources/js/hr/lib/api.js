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

export default api;

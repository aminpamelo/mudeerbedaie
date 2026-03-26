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

export default api;

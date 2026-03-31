import axios from 'axios';

const config = window.cmsConfig || {};

const api = axios.create({
    baseURL: config.apiBaseUrl || '/api/cms',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': config.csrfToken || '',
        'Accept': 'application/json',
    },
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

export function fetchDashboardStats() {
    return api.get('/dashboard/stats').then((r) => r.data);
}

export function fetchTopPosts() {
    return api.get('/dashboard/top-posts').then((r) => r.data);
}

// ─── Contents ────────────────────────────────────────────────────────────────

export function fetchContents(params) {
    return api.get('/contents', { params }).then((r) => r.data);
}

export function fetchContent(id) {
    return api.get(`/contents/${id}`).then((r) => r.data);
}

export function createContent(data) {
    return api.post('/contents', data).then((r) => r.data);
}

export function updateContent(id, data) {
    return api.put(`/contents/${id}`, data).then((r) => r.data);
}

export function deleteContent(id) {
    return api.delete(`/contents/${id}`).then((r) => r.data);
}

export function updateContentStage(id, data) {
    return api.patch(`/contents/${id}/stage`, data).then((r) => r.data);
}

export function addContentStats(id, data) {
    return api.post(`/contents/${id}/stats`, data).then((r) => r.data);
}

export function markContentForAds(id) {
    return api.patch(`/contents/${id}/mark-for-ads`).then((r) => r.data);
}

export function fetchKanban() {
    return api.get('/contents/kanban').then((r) => r.data);
}

export function fetchCalendar(params) {
    return api.get('/contents/calendar', { params }).then((r) => r.data);
}

// ─── Stage Assignees ─────────────────────────────────────────────────────────

export function addStageAssignee(contentId, stage, data) {
    return api.post(`/contents/${contentId}/stages/${stage}/assignees`, data).then((r) => r.data);
}

export function removeStageAssignee(contentId, stage, employeeId) {
    return api.delete(`/contents/${contentId}/stages/${stage}/assignees/${employeeId}`).then((r) => r.data);
}

export function updateStageDueDate(contentId, stage, data) {
    return api.patch(`/contents/${contentId}/stages/${stage}/due-date`, data).then((r) => r.data);
}

// ─── Ad Campaigns ────────────────────────────────────────────────────────────

export function fetchAdCampaigns(params) {
    return api.get('/ad-campaigns', { params }).then((r) => r.data);
}

export function fetchAdCampaign(id) {
    return api.get(`/ad-campaigns/${id}`).then((r) => r.data);
}

export function createAdCampaign(data) {
    return api.post('/ad-campaigns', data).then((r) => r.data);
}

export function updateAdCampaign(id, data) {
    return api.put(`/ad-campaigns/${id}`, data).then((r) => r.data);
}

export function addAdStats(id, data) {
    return api.post(`/ad-campaigns/${id}/stats`, data).then((r) => r.data);
}

// ─── Performance Report ─────────────────────────────────────────────────────

export function fetchPerformanceReport(params) {
    return api.get('/performance-report', { params }).then((r) => r.data);
}

// ─── Employees (for assignee picker - uses HR API) ──────────────────────────

export function fetchEmployees(params) {
    return axios.get('/api/hr/employees', {
        params,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': config.csrfToken || '',
            'Accept': 'application/json',
        },
    }).then((r) => r.data);
}

export default api;

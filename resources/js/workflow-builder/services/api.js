import axios from 'axios';

// Get API base URL from window config or use default
const getApiBase = () => window.workflowBuilderConfig?.apiBaseUrl || '/api/workflows';
const getCrmApiBase = () => '/api/crm';

// Get CSRF token from meta tag or window config
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || window.workflowBuilderConfig?.csrfToken
        || '';
};

// Create axios instance with proper defaults
const api = axios.create({
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Add request interceptor to always include fresh CSRF token
api.interceptors.request.use((config) => {
    const token = getCsrfToken();
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

export const workflowApi = {
    // Get all workflows
    async getAll(params = {}) {
        const response = await api.get(getApiBase(), { params });
        return response.data;
    },

    // Get single workflow
    async get(uuid) {
        const response = await api.get(`${getApiBase()}/${uuid}`);
        return response.data;
    },

    // Create workflow
    async create(data) {
        const response = await api.post(getApiBase(), data);
        return response.data;
    },

    // Update workflow
    async update(uuid, data) {
        const response = await api.put(`${getApiBase()}/${uuid}`, data);
        return response.data;
    },

    // Delete workflow
    async delete(uuid) {
        const response = await api.delete(`${getApiBase()}/${uuid}`);
        return response.data;
    },

    // Publish workflow
    async publish(uuid) {
        const response = await api.post(`${getApiBase()}/${uuid}/publish`);
        return response.data;
    },

    // Pause workflow
    async pause(uuid) {
        const response = await api.post(`${getApiBase()}/${uuid}/pause`);
        return response.data;
    },

    // Get workflow statistics
    async getStats(uuid) {
        const response = await api.get(`${getApiBase()}/${uuid}/stats`);
        return response.data;
    },
};

export const tagApi = {
    // Get all tags
    async getAll() {
        const response = await api.get(`${getCrmApiBase()}/tags`);
        return response.data;
    },

    // Create tag
    async create(data) {
        const response = await api.post(`${getCrmApiBase()}/tags`, data);
        return response.data;
    },
};

export const templateApi = {
    // Get all templates
    async getAll(channel = null) {
        const params = channel ? { channel } : {};
        const response = await api.get(`${getCrmApiBase()}/templates`, { params });
        return response.data;
    },
};

export const courseApi = {
    // Get all courses
    async getAll() {
        const response = await api.get(`${getCrmApiBase()}/courses`);
        return response.data;
    },
};

export const classApi = {
    // Get all classes
    async getAll() {
        const response = await api.get(`${getCrmApiBase()}/classes`);
        return response.data;
    },
};

// Export the raw axios instance for direct API calls
export { api };

export default {
    workflow: workflowApi,
    tag: tagApi,
    template: templateApi,
    course: courseApi,
    class: classApi,
};

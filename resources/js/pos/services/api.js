const getApiBase = () => window.posConfig?.apiBaseUrl || '/api/pos';
const getCsrfToken = () => window.posConfig?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;

async function request(endpoint, options = {}) {
    const url = `${getApiBase()}${endpoint}`;
    const config = {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
        ...options,
    };

    const response = await fetch(url, config);

    if (!response.ok) {
        const error = await response.json().catch(() => ({ message: 'Request failed' }));
        throw new Error(error.message || `HTTP ${response.status}`);
    }

    return response.json();
}

export const productApi = {
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/products?${query}`);
    },
};

export const packageApi = {
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/packages?${query}`);
    },
};

export const courseApi = {
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/courses?${query}`);
    },
    getClasses: (courseId) => request(`/classes/${courseId}`),
};

export const customerApi = {
    search: (search) => request(`/customers?search=${encodeURIComponent(search)}`),
};

export const saleApi = {
    create: (data) => request('/sales', {
        method: 'POST',
        body: JSON.stringify(data),
    }),
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/sales?${query}`);
    },
    get: (id) => request(`/sales/${id}`),
    updateStatus: (id, status) => request(`/sales/${id}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status }),
    }),
    delete: (id) => request(`/sales/${id}`, {
        method: 'DELETE',
    }),
};

export const dashboardApi = {
    stats: () => request('/dashboard'),
};

export const reportApi = {
    monthly: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/reports/monthly?${query}`);
    },
    daily: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/reports/daily?${query}`);
    },
};

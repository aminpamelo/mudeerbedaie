const getApiBase = () => window.posConfig?.apiBaseUrl || '/api/pos';
const getCsrfToken = () => window.posConfig?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;

async function request(endpoint, options = {}) {
    const url = `${getApiBase()}${endpoint}`;
    const isFormData = options.body instanceof FormData;
    const defaultHeaders = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (!isFormData) {
        defaultHeaders['Content-Type'] = 'application/json';
    }

    const config = {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
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
    create: (data, receiptFile = null) => {
        if (receiptFile) {
            const formData = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (key === 'items' && Array.isArray(value)) {
                    value.forEach((item, index) => {
                        Object.entries(item).forEach(([itemKey, itemValue]) => {
                            if (itemValue !== null && itemValue !== undefined) {
                                formData.append(`items[${index}][${itemKey}]`, itemValue);
                            }
                        });
                    });
                } else if (value !== null && value !== undefined) {
                    formData.append(key, value);
                }
            });
            formData.append('receipt_attachment', receiptFile);

            return request('/sales', {
                method: 'POST',
                body: formData,
            });
        }

        return request('/sales', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/sales?${query}`);
    },
    get: (id) => request(`/sales/${id}`),
    updateStatus: (id, status) => request(`/sales/${id}/status`, {
        method: 'PUT',
        body: JSON.stringify({ status }),
    }),
    updateDetails: (id, data) => request(`/sales/${id}/details`, {
        method: 'PUT',
        body: JSON.stringify(data),
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

/**
 * Funnel Builder API Service
 */

// Get API base URL from config or default
const getApiBase = () => {
    return window.funnelBuilderConfig?.apiBaseUrl || '/api/v1';
};

// Get CSRF token
const getCsrfToken = () => {
    return window.funnelBuilderConfig?.csrfToken ||
           document.querySelector('meta[name="csrf-token"]')?.content;
};

/**
 * Make an API request
 */
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
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || `HTTP error! status: ${response.status}`);
    }

    return response.json();
}

/**
 * Funnel API
 */
export const funnelApi = {
    // List all funnels
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/funnels${query ? `?${query}` : ''}`);
    },

    // Get single funnel
    get: (uuid) => request(`/funnels/${uuid}`),

    // Create funnel
    create: (data) => request('/funnels', {
        method: 'POST',
        body: JSON.stringify(data),
    }),

    // Update funnel
    update: (uuid, data) => request(`/funnels/${uuid}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Delete funnel
    delete: (uuid) => request(`/funnels/${uuid}`, {
        method: 'DELETE',
    }),

    // Duplicate funnel
    duplicate: (uuid) => request(`/funnels/${uuid}/duplicate`, {
        method: 'POST',
    }),

    // Publish funnel
    publish: (uuid) => request(`/funnels/${uuid}/publish`, {
        method: 'POST',
    }),

    // Unpublish funnel
    unpublish: (uuid) => request(`/funnels/${uuid}/unpublish`, {
        method: 'POST',
    }),
};

/**
 * Funnel Step API
 */
export const stepApi = {
    // List steps for a funnel
    list: (funnelUuid) => request(`/funnels/${funnelUuid}/steps`),

    // Get single step
    get: (funnelUuid, stepId) => request(`/funnels/${funnelUuid}/steps/${stepId}`),

    // Create step
    create: (funnelUuid, data) => request(`/funnels/${funnelUuid}/steps`, {
        method: 'POST',
        body: JSON.stringify(data),
    }),

    // Update step
    update: (funnelUuid, stepId, data) => request(`/funnels/${funnelUuid}/steps/${stepId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Delete step
    delete: (funnelUuid, stepId) => request(`/funnels/${funnelUuid}/steps/${stepId}`, {
        method: 'DELETE',
    }),

    // Reorder steps
    reorder: (funnelUuid, steps) => request(`/funnels/${funnelUuid}/steps/reorder`, {
        method: 'POST',
        body: JSON.stringify({ steps }),
    }),

    // Get step content
    getContent: (funnelUuid, stepId) => request(`/funnels/${funnelUuid}/steps/${stepId}/content`),

    // Save step content (Puck data)
    saveContent: (funnelUuid, stepId, content) => request(`/funnels/${funnelUuid}/steps/${stepId}/content`, {
        method: 'PUT',
        body: JSON.stringify({ content }),
    }),

    // Publish step content
    publishContent: (funnelUuid, stepId) => request(`/funnels/${funnelUuid}/steps/${stepId}/content/publish`, {
        method: 'POST',
    }),

    // Duplicate step
    duplicate: (funnelUuid, stepId) => request(`/funnels/${funnelUuid}/steps/${stepId}/duplicate`, {
        method: 'POST',
    }),
};

/**
 * Funnel Product API
 */
export const productApi = {
    // List all products in funnel (across all steps)
    listAll: (funnelUuid) => request(`/funnels/${funnelUuid}/products`),

    // Add product to step
    create: (funnelUuid, stepId, data) => request(`/funnels/${funnelUuid}/steps/${stepId}/products`, {
        method: 'POST',
        body: JSON.stringify(data),
    }),

    // Update product
    update: (funnelUuid, stepId, productId, data) => request(`/funnels/${funnelUuid}/steps/${stepId}/products/${productId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Delete product
    delete: (funnelUuid, stepId, productId) => request(`/funnels/${funnelUuid}/steps/${stepId}/products/${productId}`, {
        method: 'DELETE',
    }),

    // Reorder products within step
    reorder: (funnelUuid, stepId, products) => request(`/funnels/${funnelUuid}/steps/${stepId}/products/reorder`, {
        method: 'POST',
        body: JSON.stringify({ products }),
    }),

    // Search available products
    search: (query) => request(`/products/search?q=${encodeURIComponent(query)}`),

    // Search available courses
    searchCourses: (query) => request(`/courses/search?q=${encodeURIComponent(query)}`),

    // Search available packages
    searchPackages: (query) => request(`/packages/search?q=${encodeURIComponent(query)}`),
};

/**
 * Order Bump API
 */
export const orderBumpApi = {
    // List order bumps for a step
    list: (funnelUuid, stepId) => request(`/funnels/${funnelUuid}/steps/${stepId}/order-bumps`),

    // Add order bump to step
    create: (funnelUuid, stepId, data) => request(`/funnels/${funnelUuid}/steps/${stepId}/order-bumps`, {
        method: 'POST',
        body: JSON.stringify(data),
    }),

    // Update order bump
    update: (funnelUuid, stepId, bumpId, data) => request(`/funnels/${funnelUuid}/steps/${stepId}/order-bumps/${bumpId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Delete order bump
    delete: (funnelUuid, stepId, bumpId) => request(`/funnels/${funnelUuid}/steps/${stepId}/order-bumps/${bumpId}`, {
        method: 'DELETE',
    }),
};

/**
 * Analytics API
 */
export const analyticsApi = {
    // Get funnel analytics
    getFunnelStats: (funnelUuid, period = '7d') =>
        request(`/funnels/${funnelUuid}/analytics?period=${period}`),

    // Get step analytics
    getStepStats: (funnelUuid, stepId, period = '7d') =>
        request(`/funnels/${funnelUuid}/steps/${stepId}/analytics?period=${period}`),
};

/**
 * Orders API
 */
export const ordersApi = {
    // Get orders for a funnel with pagination and filters
    list: (funnelUuid, params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/funnels/${funnelUuid}/orders${query ? `?${query}` : ''}`);
    },

    // Get order statistics
    stats: (funnelUuid) => request(`/funnels/${funnelUuid}/orders/stats`),

    // Get abandoned carts for a funnel
    abandonedCarts: (funnelUuid, params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/funnels/${funnelUuid}/carts${query ? `?${query}` : ''}`);
    },
};

/**
 * Automation API
 */
export const automationApi = {
    // List all automations for a funnel
    list: (funnelUuid) => request(`/funnels/${funnelUuid}/automations`),

    // Get single automation
    get: (funnelUuid, automationId) => request(`/funnels/${funnelUuid}/automations/${automationId}`),

    // Create automation
    create: (funnelUuid, data) => request(`/funnels/${funnelUuid}/automations`, {
        method: 'POST',
        body: JSON.stringify(data),
    }),

    // Update automation
    update: (funnelUuid, automationId, data) => request(`/funnels/${funnelUuid}/automations/${automationId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Delete automation
    delete: (funnelUuid, automationId) => request(`/funnels/${funnelUuid}/automations/${automationId}`, {
        method: 'DELETE',
    }),

    // Toggle automation active status
    toggle: (funnelUuid, automationId) => request(`/funnels/${funnelUuid}/automations/${automationId}/toggle`, {
        method: 'POST',
    }),

    // Duplicate automation
    duplicate: (funnelUuid, automationId) => request(`/funnels/${funnelUuid}/automations/${automationId}/duplicate`, {
        method: 'POST',
    }),

    // Get automation logs
    logs: (funnelUuid, automationId, page = 1) =>
        request(`/funnels/${funnelUuid}/automations/${automationId}/logs?page=${page}`),
};

/**
 * Templates API
 */
export const templateApi = {
    // List templates
    list: (category = null) => {
        const query = category ? `?category=${category}` : '';
        return request(`/templates${query}`);
    },

    // Get template
    get: (id) => request(`/templates/${id}`),

    // Create funnel from template
    createFromTemplate: (templateId, name) => request('/funnels/from-template', {
        method: 'POST',
        body: JSON.stringify({ template_id: templateId, name }),
    }),
};

/**
 * Media API
 */
export const mediaApi = {
    // List media
    list: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/media${query ? `?${query}` : ''}`);
    },

    // Upload image
    upload: async (file, funnelUuid = null, altText = null) => {
        const formData = new FormData();
        formData.append('file', file);
        if (funnelUuid) {
            formData.append('funnel_uuid', funnelUuid);
        }
        if (altText) {
            formData.append('alt_text', altText);
        }

        const response = await fetch(`${getApiBase()}/media`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || 'Upload failed');
        }

        return response.json();
    },

    // Update media (alt text)
    update: (id, data) => request(`/media/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // Delete media
    delete: (id) => request(`/media/${id}`, {
        method: 'DELETE',
    }),

    // Bulk delete
    bulkDelete: (ids) => request('/media/bulk-delete', {
        method: 'POST',
        body: JSON.stringify({ ids }),
    }),
};

/**
 * Affiliate API
 */
export const affiliateApi = {
    // Get affiliate settings for a funnel
    settings: (funnelUuid) => request(`/funnels/${funnelUuid}/affiliate-settings`),

    // Update affiliate settings
    updateSettings: (funnelUuid, data) => request(`/funnels/${funnelUuid}/affiliate-settings`, {
        method: 'PUT',
        body: JSON.stringify(data),
    }),

    // List affiliates for a funnel
    affiliates: (funnelUuid) => request(`/funnels/${funnelUuid}/affiliates`),

    // Get affiliate stats
    affiliateStats: (funnelUuid, affiliateId) => request(`/funnels/${funnelUuid}/affiliates/${affiliateId}/stats`),

    // List commissions
    commissions: (funnelUuid, params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`/funnels/${funnelUuid}/commissions${query ? `?${query}` : ''}`);
    },

    // Approve commission
    approveCommission: (funnelUuid, commissionId) => request(`/funnels/${funnelUuid}/commissions/${commissionId}/approve`, {
        method: 'POST',
    }),

    // Reject commission
    rejectCommission: (funnelUuid, commissionId, notes = '') => request(`/funnels/${funnelUuid}/commissions/${commissionId}/reject`, {
        method: 'POST',
        body: JSON.stringify({ notes }),
    }),

    // Bulk approve commissions
    bulkApprove: (funnelUuid) => request(`/funnels/${funnelUuid}/commissions/bulk-approve`, {
        method: 'POST',
    }),
};

export default {
    funnel: funnelApi,
    step: stepApi,
    product: productApi,
    orderBump: orderBumpApi,
    analytics: analyticsApi,
    orders: ordersApi,
    automation: automationApi,
    template: templateApi,
    media: mediaApi,
    affiliate: affiliateApi,
};

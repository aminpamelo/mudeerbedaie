const getConfig = () => window.affiliateConfig || {};

const apiFetch = async (endpoint, options = {}) => {
    const config = getConfig();
    const url = `${config.apiBaseUrl || '/affiliate-api'}${endpoint}`;

    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': config.csrfToken || '',
        ...options.headers,
    };

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    });

    if (response.status === 401) {
        throw { status: 401, message: 'Unauthenticated' };
    }

    const data = await response.json();

    if (!response.ok) {
        throw { status: response.status, message: data.message || 'Request failed', errors: data.errors };
    }

    return data;
};

const api = {
    login(phone) {
        return apiFetch('/login', {
            method: 'POST',
            body: JSON.stringify({ phone }),
        });
    },

    register(name, phone, email) {
        return apiFetch('/register', {
            method: 'POST',
            body: JSON.stringify({ name, phone, email: email || undefined }),
        });
    },

    logout() {
        return apiFetch('/logout', {
            method: 'POST',
        });
    },

    getMe() {
        return apiFetch('/me');
    },

    getDashboard() {
        return apiFetch('/dashboard');
    },

    getJoinedFunnels() {
        return apiFetch('/funnels');
    },

    discoverFunnels() {
        return apiFetch('/funnels/discover');
    },

    joinFunnel(funnelId) {
        return apiFetch(`/funnels/${funnelId}/join`, {
            method: 'POST',
        });
    },

    getFunnelStats(funnelId) {
        return apiFetch(`/funnels/${funnelId}/stats`);
    },

    getLeaderboard() {
        return apiFetch('/leaderboard');
    },
};

export default api;

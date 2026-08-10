export async function workspaceJson(url, options = {}) {
    const res = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...options.headers,
        },
        ...options,
    });
    if (!res.ok) throw new Error(`${res.status}: ${res.statusText}`);
    return res.json();
}

export async function workspaceSend(url, { method = 'POST', body } = {}) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || `${res.status}: ${res.statusText}`);
    }
    return res.json();
}

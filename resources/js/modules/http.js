const token = () => document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Small fetch wrapper for JSON endpoints. Throws an Error carrying the
 * Laravel validation `errors` bag on 422 responses.
 */
export async function requestJson(url, { method = 'GET', body = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token() };
    let payload = body;

    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || 'Something went wrong. Please try again.');
        error.status = response.status;
        error.errors = data.errors || {};
        throw error;
    }

    return data;
}

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

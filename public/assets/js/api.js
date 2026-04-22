/**
 * TRUSTLINK - JavaScript API Client
 * Version: 2.0 | Render-Ready | April 2026
 * 
 * Description: Centralized API client for all backend communication
 * Changes for Render:
 * - Removed hardcoded '/trustfiles' prefix
 * - Uses relative '/api' endpoints (backend must respond to /api/*)
 * - Works on same-origin deployments (frontend + backend together)
 * - For separate frontend/backend, set window.API_BASE_URL
 */

const API = (function() {
    // ✅ Render fix: no more '/trustfiles' – use empty base (relative URLs)
    // If your backend is on a different domain, set window.API_BASE_URL = 'https://backend.onrender.com'
    const BASE_URL = window.API_BASE_URL || '';
    
    console.log('API Base URL:', BASE_URL || '(relative)');

    async function request(endpoint, options = {}) {
        // ✅ Build URL: BASE_URL + /api + endpoint
        const url = `${BASE_URL}/api${endpoint}`;
        console.log("MAIN API URL:", url);

        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            credentials: 'same-origin',
            ...options
        };

        if (options.body && typeof options.body === 'object') {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            console.log('API Response:', data);

            if (!response.ok) {
                throw {
                    status: response.status,
                    message: data.message || 'An error occurred',
                    errors: data.errors
                };
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /**
     * GET request
     * @param {string} endpoint - API endpoint
     * @param {Object} params - Query parameters
     * @returns {Promise}
     */
    async function get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return request(url, { method: 'GET' });
    }

    /**
     * POST request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @returns {Promise}
     */
    async function post(endpoint, data = {}) {
        return request(endpoint, { method: 'POST', body: data });
    }

    /**
     * PUT request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @returns {Promise}
     */
    async function put(endpoint, data = {}) {
        return request(endpoint, { method: 'PUT', body: data });
    }

    /**
     * DELETE request
     * @param {string} endpoint - API endpoint
     * @returns {Promise}
     */
    async function del(endpoint) {
        return request(endpoint, { method: 'DELETE' });
    }

    /**
     * FormData POST (for file uploads)
     * @param {string} endpoint - API endpoint
     * @param {FormData} formData - Form data
     * @returns {Promise}
     */
    async function upload(endpoint, formData) {
        const url = `${BASE_URL}/api${endpoint}`;
        console.log("UPLOAD API URL:", url);

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const text = await response.text();
            console.log("RAW RESPONSE:", text);

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error("Server returned invalid JSON:\n" + text);
            }

            if (!response.ok) {
                throw {
                    status: response.status,
                    message: data.message || 'Upload failed',
                    errors: data.errors
                };
            }

            return data;
        } catch (error) {
            console.error('Upload Error:', error);
            throw error;
        }
    }

    return {
        get,
        post,
        put,
        delete: del,
        upload
    };
})();

// ========== HELPER FUNCTIONS (unchanged, kept for completeness) ==========

function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatCurrency(amount) {
    return `KES ${Number(amount).toLocaleString()}`;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return `${diff} seconds ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)} minutes ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
    if (diff < 2592000) return `${Math.floor(diff / 604800)} weeks ago`;
    return `${Math.floor(diff / 2592000)} months ago`;
}
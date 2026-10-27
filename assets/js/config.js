/**
 * Infinity Blog - Configuration
 */

// Dynamically compute the API base path based on current location
const computeDefaultApiBase = () => {
    const origin = window.location.origin;
    const pathname = window.location.pathname;

    // If served via standard HTTP/HTTPS
    if (origin && origin !== 'null' && origin !== 'file://') {
        const lastSlash = pathname.lastIndexOf('/');
        const basePath = lastSlash >= 0 ? pathname.substring(0, lastSlash + 1) : '/';
        return `${origin}${basePath}api/v1`.replace(/\/+api\/v1$/, '/api/v1');
    }

    return '/api/v1';
};

export const CONFIG = {
    API_BASE: window.INFINITY_BLOG_API_BASE || computeDefaultApiBase(),
    APP_NAME: 'Infinity Blog',
    VERSION: '1.0.0',
};

/**
 * Infinity Blog - Configuration
 */

// Dynamically compute the API base path based on current location
const computeDefaultApiBase = () => {
    const origin = window.location.origin;
    const pathname = window.location.pathname;
    
    // If hosted inside /infinity-blog/ subdirectory
    if (pathname.includes('/infinity-blog')) {
        return `${origin}/infinity-blog/api/v1`;
    }
    return `${origin}/api/v1`;
};

export const CONFIG = {
    API_BASE: window.INFINITY_BLOG_API_BASE || computeDefaultApiBase(),
    APP_NAME: 'Infinity Blog',
    VERSION: '1.0.0',
};

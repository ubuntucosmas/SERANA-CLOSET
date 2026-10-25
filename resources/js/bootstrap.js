import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Send this session's CSRF token ourselves. Reading it from the XSRF-TOKEN
// cookie alone fails when another site on a parent domain (e.g. a second app
// on *.ddev.site) sets its own XSRF-TOKEN: the browser sends that one first and
// every form answers "Your session has expired". Laravel checks X-CSRF-TOKEN
// before the cookie header. Fresh tokens arrive with each Inertia page.
export function useCsrfToken(token) {
    if (!token) return;
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
}
useCsrfToken(document.querySelector('meta[name="csrf-token"]')?.content);

// ══════════════════════════════════════════════════════════════════
//  Massar — HTTP client setup
//  Location: resources/js/bootstrap.js
//
//  axios for the rare request that is not an Inertia visit (e.g. a
//  live search). Sends the XSRF cookie Laravel expects.
// ══════════════════════════════════════════════════════════════════

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

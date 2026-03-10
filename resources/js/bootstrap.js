import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add a response interceptor
window.axios.interceptors.response.use(
    function (response) {
        return response;
    },
    function (error) {
        if (error.response && error.response.status === 401) {
            // Redirect to login page
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

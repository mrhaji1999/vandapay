import axios from 'axios';

const rawBaseUrl = import.meta.env.VITE_API_BASE_URL || '';
const normalizedBaseUrl = rawBaseUrl.endsWith('/') && rawBaseUrl !== '/' ? rawBaseUrl.slice(0, -1) : rawBaseUrl;

const apiClient = axios.create({
    baseURL: normalizedBaseUrl,
});

apiClient.interceptors.request.use((config) => {
    if (typeof window !== 'undefined') {
        const token = window.localStorage.getItem('token');
        if (token && !config.headers?.Authorization) {
            config.headers = {
                ...config.headers,
                Authorization: `Bearer ${token}`,
            };
        }
    }
    return config;
});

export default apiClient;

import axios from 'axios';
import toast from 'react-hot-toast';
import { getToken, logout } from '../store/auth';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;

if (!apiBaseUrl) {
  console.warn('VITE_API_BASE_URL is not defined. API calls will fail.');
}

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json'
  }
});

apiClient.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers = config.headers ?? {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error)) {
      const status = error.response?.status;
      if (status === 401) {
        logout();
        window.location.href = '/login';
      } else if (!error.response) {
        toast.error('Network error. Please check your connection.');
      } else {
        const message =
          (error.response.data as { message?: string })?.message || 'Request failed';
        toast.error(message);
      }
    }
    return Promise.reject(error);
  }
);

export type ApiResponse<T> = {
  data: T;
};

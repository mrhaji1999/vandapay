import axios from 'axios';
import toast from 'react-hot-toast';
import { getToken, logout } from '../store/auth';

const configuredBaseUrl = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.trim();
const fallbackBaseUrl =
  typeof window !== 'undefined'
    ? `${window.location.origin.replace(/\/$/, '')}/wp-json/cwm/v1`
    : undefined;

const apiBaseUrl = (configuredBaseUrl && configuredBaseUrl.length > 0
  ? configuredBaseUrl
  : fallbackBaseUrl) as string | undefined;

if (!configuredBaseUrl) {
  console.info(
    'VITE_API_BASE_URL تنظیم نشده است. از نشانی هم‌ریشه برای برقراری ارتباط با افزونه استفاده می‌شود.'
  );
}

if (!apiBaseUrl) {
  console.error('نشانی پایه‌ی سرویس یافت نشد و درخواست‌ها با خطا مواجه خواهند شد.');
}

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json'
  }
});

apiClient.interceptors.request.use((config) => {
  if (!apiBaseUrl) {
    toast.error('تنظیمات اتصال به سرویس یافت نشد. لطفاً با مدیر سامانه تماس بگیرید.');
    return Promise.reject(new Error('API base URL is not configured.'));
  }
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
        toast.error('خطا در برقراری ارتباط با سرور. اتصال اینترنت خود را بررسی کنید.');
      } else {
        const message =
          (error.response.data as { message?: string })?.message || 'درخواست با خطا مواجه شد.';
        toast.error(message);
      }
    }
    return Promise.reject(error);
  }
);

export type ApiResponse<T> = {
  data: T;
};

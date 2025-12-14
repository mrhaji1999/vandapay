import axios from 'axios';
import toast from 'react-hot-toast';
import { getToken, logout } from '../store/auth';

const configuredBaseUrl = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.trim();

const deriveFallbackBaseUrl = (): string | undefined => {
  if (typeof window === 'undefined') {
    return undefined;
  }

  const origin = window.location.origin.replace(/\/$/, '');
  const { hostname, protocol } = window.location;

  if (/^panel\./i.test(hostname)) {
    const candidateHost = hostname.replace(/^panel\./i, 'mr.');
    return `${protocol}//${candidateHost}/wp-json/cwm/v1`;
  }

  return `${origin}/wp-json/cwm/v1`;
};

const fallbackBaseUrl = deriveFallbackBaseUrl();

const apiBaseUrl = (configuredBaseUrl && configuredBaseUrl.length > 0
  ? configuredBaseUrl
  : fallbackBaseUrl) as string | undefined;

if (!configuredBaseUrl) {
  console.info(
    'VITE_API_BASE_URL تنظیم نشده است. در حال تلاش برای استفاده از نشانی پیش‌فرض بر اساس دامنهٔ فعلی.'
  );
  if (fallbackBaseUrl) {
    console.info(`نشانی پایهٔ انتخاب‌شده: ${fallbackBaseUrl}`);
  }
}

if (!apiBaseUrl) {
  console.error('نشانی پایه‌ی سرویس یافت نشد و درخواست‌ها با خطا مواجه خواهند شد.');
}

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json'
  },
  withCredentials: true
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
  // Remove Content-Type header for FormData to let browser set it with boundary
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type'];
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
        toast.error('خطا در برقراری ارتباط با سرور یا مشکل CORS. لطفاً تنظیمات مبدا مجاز را بررسی کنید.');
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

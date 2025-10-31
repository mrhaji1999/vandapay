import axios from 'axios';

const normalizeBaseUrl = (url, fallbackPath) => {
  if (!url) {
    if (typeof window !== 'undefined') {
      return `${window.location.origin}${fallbackPath}`;
    }
    return fallbackPath;
  }
  const trimmed = url.endsWith('/') ? url.slice(0, -1) : url;
  return trimmed;
};

const API_BASE_URL = normalizeBaseUrl(import.meta.env.VITE_API_BASE_URL, '/wp-json/cwm/v1');
const AUTH_BASE_URL = normalizeBaseUrl(import.meta.env.VITE_AUTH_BASE_URL, '/wp-json/jwt-auth/v1');

const apiClient = axios.create({
  baseURL: API_BASE_URL,
});

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('vandapay_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const login = async (credentials) => {
  const { data } = await axios.post(`${AUTH_BASE_URL}/token`, credentials);
  return {
    token: data.token,
    user: {
      id: data.user_id,
      name: data.user_display_name,
      role: data.user_role || data.role || data.roles?.[0] || null,
      roles: data.roles,
    },
  };
};

export const getCurrentUser = async () => {
  const { data } = await apiClient.get('/me');
  return data;
};

export const getCompanyEmployees = async (companyId, query = {}) => {
  const { data } = await apiClient.get(`/companies/${companyId}/employees`, { params: query });
  return data;
};

export const bulkChargeEmployees = async (payload) => {
  // payload expected to include FormData with CSV
  const { data } = await apiClient.post('/wallet/charge-bulk', payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
};

export const searchEmployeeByNationalId = async (nationalId) => {
  const { data } = await apiClient.get('/employees/search', { params: { national_id: nationalId } });
  return data;
};

export const createMerchantPaymentRequest = async (payload) => {
  const { data } = await apiClient.post('/merchant/payment-requests', payload);
  return data;
};

export const getEmployeePendingRequests = async () => {
  const { data } = await apiClient.get('/employee/payment-requests', { params: { status: 'pending' } });
  return data;
};

export const confirmEmployeePaymentRequest = async (id) => {
  // TODO: implement in WordPress plugin
  const { data } = await apiClient.post(`/employee/payment-requests/${id}/confirm`);
  return data;
};

export const verifyEmployeePaymentOtp = async (id, code) => {
  // TODO: implement in WordPress plugin
  const { data } = await apiClient.post(`/employee/payment-requests/${id}/verify-otp`, { code });
  return data;
};

export const getMerchantWallet = async () => {
  const { data } = await apiClient.get('/merchant/wallet');
  return data;
};

export const createMerchantPayoutRequest = async (payload) => {
  const { data } = await apiClient.post('/merchant/payout-request', payload);
  return data;
};

export const getMerchantPayments = async (params = {}) => {
  const { data } = await apiClient.get('/merchant/payment-requests', { params });
  return data;
};

export const getMerchantBankAccounts = async () => {
  // TODO: implement in WordPress plugin
  const { data } = await apiClient.get('/merchant/bank-accounts');
  return data;
};

export const createMerchantBankAccount = async (payload) => {
  // TODO: implement in WordPress plugin
  const { data } = await apiClient.post('/merchant/bank-accounts', payload);
  return data;
};

export const getEmployeeTransactions = async () => {
  const { data } = await apiClient.get('/employee/transactions');
  return data;
};

export const getCompanyReports = async (companyId, params = {}) => {
  const { data } = await apiClient.get(`/companies/${companyId}/reports`, { params });
  return data;
};

import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { jwtDecode } from 'jwt-decode';
import { apiClient } from '../api/client';
import type { UserProfile } from '../types/user';

export type Role = 'administrator' | 'company' | 'merchant' | 'employee';

type AuthState = {
  token: string | null;
  user: UserProfile | null;
  isAuthenticated: boolean;
  setToken: (token: string | null) => void;
  setUser: (user: UserProfile | null) => void;
  fetchProfile: () => Promise<UserProfile | null>;
  logout: () => void;
};

const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      isAuthenticated: false,
      setToken: (token) => {
        set({ token, isAuthenticated: Boolean(token) });
      },
      setUser: (user) => set({ user }),
      fetchProfile: async () => {
        try {
          const response = await apiClient.get<UserProfile>('/profile');
          set({ user: response.data, isAuthenticated: true });
          return response.data;
        } catch (error) {
          console.warn('Failed to fetch profile', error);
          return null;
        }
      },
      logout: () => {
        set({ token: null, user: null, isAuthenticated: false });
      }
    }),
    {
      name: 'cwm-auth-store'
    }
  )
);

export const useAuth = useAuthStore;

export const getToken = () => useAuthStore.getState().token;

export const logout = () => useAuthStore.getState().logout();

type JwtPayload = {
  role?: Role;
  roles?: Role[];
  data?: {
    user?: {
      role?: Role;
      roles?: Role[];
    };
  };
};

export const decodeRole = (token: string): Role | null => {
  try {
    const payload = jwtDecode<JwtPayload>(token);
    if (payload.role) return payload.role;
    if (payload.roles && payload.roles.length > 0) return payload.roles[0];

    const nestedRole = payload.data?.user?.role;
    if (nestedRole) return nestedRole;

    const nestedRoles = payload.data?.user?.roles;
    if (nestedRoles && nestedRoles.length > 0) return nestedRoles[0];

    return null;
  } catch (error) {
    console.warn('Unable to decode token role', error);
    return null;
  }
};

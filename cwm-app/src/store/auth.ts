import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { jwtDecode } from 'jwt-decode';
import { apiClient } from '../api/client';
import type { UserProfile } from '../types/user';

export type Role = 'administrator' | 'company' | 'merchant' | 'employee';

type ProfileResponse = {
  status?: string;
  data?: {
    id?: number;
    username?: string;
    email?: string;
    name?: string;
    display_name?: string;
    role?: Role;
    roles?: Role[];
    capabilities?: string[] | Record<string, boolean>;
  };
};

const isRole = (value: unknown): value is Role =>
  value === 'administrator' || value === 'company' || value === 'merchant' || value === 'employee';

const normalizeCapabilities = (
  raw: string[] | Record<string, boolean> | undefined
): string[] | undefined => {
  if (!raw) return undefined;
  if (Array.isArray(raw)) {
    return raw;
  }

  return Object.entries(raw)
    .filter(([, allowed]) => Boolean(allowed))
    .map(([capability]) => capability);
};

const normalizeProfile = (payload?: ProfileResponse['data']): UserProfile | null => {
  if (!payload || typeof payload.id !== 'number') {
    return null;
  }

  const roles = Array.isArray(payload.roles)
    ? (payload.roles.filter(isRole) as Role[])
    : undefined;
  const primaryRole = payload.role && isRole(payload.role) ? payload.role : roles?.[0];

  return {
    id: payload.id,
    username: payload.username ?? payload.email ?? undefined,
    email: payload.email ?? undefined,
    name: payload.name ?? payload.display_name ?? payload.username ?? payload.email ?? undefined,
    role: primaryRole,
    roles: roles ?? (primaryRole ? [primaryRole] : undefined),
    capabilities: normalizeCapabilities(payload.capabilities),
  };
};

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
      setUser: (user) =>
        set((state) => ({
          user,
          isAuthenticated: user ? true : state.isAuthenticated && Boolean(state.token),
        })),
      fetchProfile: async () => {
        try {
          const response = await apiClient.get<ProfileResponse>('/profile');
          const normalized = normalizeProfile(response.data?.data);

          if (normalized) {
            set({ user: normalized, isAuthenticated: true });
            return normalized;
          }

          return null;
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

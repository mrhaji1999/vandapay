import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { login as loginRequest, getCurrentUser } from '../services/api.js';

const AuthContext = createContext();

const TOKEN_STORAGE_KEY = 'vandapay_token';
const USER_STORAGE_KEY = 'vandapay_user';

const normalizeUser = (rawUser) => {
  if (!rawUser) return null;

  const normalizedRole = rawUser.role || rawUser.roles?.[0] || rawUser.primary_role || null;

  return {
    id: rawUser.id ?? rawUser.user_id ?? null,
    name: rawUser.name ?? rawUser.display_name ?? rawUser.user_display_name ?? '',
    role: normalizedRole,
    ...rawUser,
  };
};

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => {
    if (typeof window === 'undefined') {
      return null;
    }

    return localStorage.getItem(TOKEN_STORAGE_KEY);
  });
  const [user, setUser] = useState(() => {
    if (typeof window === 'undefined') {
      return null;
    }

    try {
      const stored = localStorage.getItem(USER_STORAGE_KEY);
      return stored ? JSON.parse(stored) : null;
    } catch (error) {
      console.warn('Failed to parse stored user profile', error);
      return null;
    }
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadCurrentUser() {
      if (!token) {
        setLoading(false);
        return;
      }

      try {
        const profile = await getCurrentUser();
        const normalized = normalizeUser(profile);
        if (normalized) {
          setUser(normalized);
        }
      } catch (error) {
        const status = error?.response?.status;

        if (status === 401 || status === 403) {
          console.error('Failed to load user', error);
          logout();
        } else {
          console.warn('Continuing with cached user profile', error);
        }
      } finally {
        setLoading(false);
      }
    }

    loadCurrentUser();
  }, [token]);

  const login = async (credentials) => {
    const { token: jwt, user: loginUser } = await loginRequest(credentials);
    localStorage.setItem(TOKEN_STORAGE_KEY, jwt);
    setToken(jwt);

    let profile = normalizeUser(loginUser);

    try {
      const freshProfile = await getCurrentUser();
      profile = normalizeUser({ ...profile, ...freshProfile }) ?? profile;
    } catch (error) {
      if (!profile) {
        localStorage.removeItem(TOKEN_STORAGE_KEY);
        setToken(null);
        throw error;
      }
      console.warn('Falling back to login payload for user profile', error);
    }

    setUser(profile);
    return profile;
  };

  const logout = () => {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    localStorage.removeItem(USER_STORAGE_KEY);
    setToken(null);
    setUser(null);
  };

  useEffect(() => {
    if (!user) {
      localStorage.removeItem(USER_STORAGE_KEY);
      return;
    }

    try {
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user));
    } catch (error) {
      console.warn('Failed to persist user profile', error);
    }
  }, [user]);

  const value = useMemo(
    () => ({
      token,
      user,
      loading,
      login,
      logout,
      setUser,
      setToken,
    }),
    [token, user, loading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return useContext(AuthContext);
}

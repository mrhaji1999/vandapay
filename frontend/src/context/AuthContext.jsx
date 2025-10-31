import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { login as loginRequest, getCurrentUser } from '../services/api.js';

const AuthContext = createContext();

const TOKEN_STORAGE_KEY = 'vandapay_token';

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
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_STORAGE_KEY));
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadCurrentUser() {
      if (!token) {
        setLoading(false);
        return;
      }

      try {
        const profile = await getCurrentUser();
        setUser(normalizeUser(profile));
      } catch (error) {
        console.error('Failed to load user', error);
        logout();
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
    setToken(null);
    setUser(null);
  };

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

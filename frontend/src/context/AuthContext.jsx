import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { login as loginRequest, getCurrentUser } from '../services/api.js';

const AuthContext = createContext();

const TOKEN_STORAGE_KEY = 'vandapay_token';

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
        setUser(profile);
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
    const { token: jwt, user: profile } = await loginRequest(credentials);
    localStorage.setItem(TOKEN_STORAGE_KEY, jwt);
    setToken(jwt);
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

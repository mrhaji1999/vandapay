import React, { createContext, useState, useContext, useEffect, useCallback } from 'react';
import axios from 'axios';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(localStorage.getItem('token'));
    const [isLoading, setIsLoading] = useState(!!token);

    const logout = useCallback(() => {
        localStorage.removeItem('token');
        setToken(null);
        setUser(null);
        setIsLoading(false);
    }, []);

    const fetchProfile = useCallback(async (activeToken) => {
        if (!activeToken) {
            setUser(null);
            setIsLoading(false);
            return false;
        }

        try {
            setIsLoading(true);
            const response = await axios.get('/wp-json/cwm/v1/profile', {
                headers: { Authorization: `Bearer ${activeToken}` },
            });
            setUser(response.data.data);
            return true;
        } catch (error) {
            console.error('Failed to fetch profile:', error);
            logout();
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [logout]);

    const login = async (username, password) => {
        try {
            setIsLoading(true);
            const response = await axios.post('/wp-json/cwm/v1/token', {
                username,
                password,
            });
            localStorage.setItem('token', response.data.token);
            setToken(response.data.token);
            const profileLoaded = await fetchProfile(response.data.token);
            return profileLoaded;
        } catch (error) {
            console.error('Login failed:', error);
            setIsLoading(false);
            return false;
        }
    };

    useEffect(() => {
        if (token) {
            fetchProfile(token);
        } else {
            setUser(null);
        }
    }, [token, fetchProfile]);

    return (
        <AuthContext.Provider value={{ token, user, login, logout, isLoading }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext); // eslint-disable-line react-refresh/only-export-components

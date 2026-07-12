import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import * as authApi from '../api/auth';
import {setTokenGetter} from '../api/client';
import {clearToken, getToken, setToken} from '../api/tokenStorage';
import {ApiError} from '../api/client';
import type {User} from '../types/api';

interface AuthContextValue {
  token: string | null;
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  mustChangePassword: boolean;
  login: (phone: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  changePassword: (current: string, newPassword: string) => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({children}: {children: React.ReactNode}) {
  const [token, setTokenState] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setTokenGetter(() => token);
  }, [token]);

  const refreshUser = useCallback(async () => {
    const me = await authApi.getMe();
    setUser(me);
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const stored = await getToken();
        if (!stored) {
          return;
        }
        setTokenState(stored);
        const me = await authApi.getMe();
        setUser(me);
      } catch (e) {
        if (e instanceof ApiError && e.code === 401) {
          await clearToken();
          setTokenState(null);
          setUser(null);
        }
      } finally {
        setIsLoading(false);
      }
    })();
  }, []);

  const login = useCallback(async (phone: string, password: string) => {
    const result = await authApi.login(phone, password);
    await setToken(result.token);
    setTokenState(result.token);
    setUser(result.user);
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      await clearToken();
      setTokenState(null);
      setUser(null);
    }
  }, []);

  const changePassword = useCallback(
    async (current: string, newPassword: string) => {
      await authApi.changePassword(current, newPassword);
      await refreshUser();
    },
    [refreshUser],
  );

  const value = useMemo(
    () => ({
      token,
      user,
      isLoading,
      isAuthenticated: token !== null && user !== null,
      mustChangePassword: user?.must_change_password ?? false,
      login,
      logout,
      refreshUser,
      changePassword,
    }),
    [token, user, isLoading, login, logout, refreshUser, changePassword],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}

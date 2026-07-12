import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { useNavigate } from 'react-router-dom';
import { authApi } from '../api/auth';
import {
  clearToken,
  getToken,
  setOnMustChangePassword,
  setOnUnauthorized,
  setToken,
} from '../api/client';
import type { AuthUser } from '../types/employee';

interface AuthContextValue {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  login: (token: string, user: AuthUser) => void;
  logout: () => Promise<void>;
  refreshUser: () => Promise<AuthUser>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate();
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setTokenState] = useState<string | null>(getToken());
  const [loading, setLoading] = useState(true);

  const login = useCallback((newToken: string, newUser: AuthUser) => {
    setToken(newToken);
    setTokenState(newToken);
    setUser(newUser);
  }, []);

  const logout = useCallback(async () => {
    try {
      if (getToken()) {
        await authApi.logout();
      }
    } finally {
      clearToken();
      setTokenState(null);
      setUser(null);
      navigate('/login');
    }
  }, [navigate]);

  const refreshUser = useCallback(async () => {
    const me = await authApi.me();
    setUser(me);
    return me;
  }, []);

  useEffect(() => {
    setOnUnauthorized(() => {
      setTokenState(null);
      setUser(null);
      navigate('/login');
    });
    setOnMustChangePassword(() => {
      navigate('/change-password');
    });
  }, [navigate]);

  useEffect(() => {
    const init = async () => {
      const stored = getToken();
      if (!stored) {
        setLoading(false);
        return;
      }
      try {
        const me = await authApi.me();
        setUser(me);
        setTokenState(stored);
      } catch {
        clearToken();
        setTokenState(null);
        setUser(null);
      } finally {
        setLoading(false);
      }
    };
    void init();
  }, []);

  const value = useMemo(
    () => ({ user, token, loading, login, logout, refreshUser }),
    [user, token, loading, login, logout, refreshUser],
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

export function isAdminRole(role: string): boolean {
  return role === 'admin' || role === 'super_admin';
}

# M11 管理后台员工管理 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 React 管理后台登录、首次改密、员工列表 CRUD（分页搜索 + Modal），对接 M03 Auth 与 Employee API。

**Architecture:** 方案 1 — Ant Design 5 + React Router 6 + fetch + AuthContext；`api/client.ts` 统一 Bearer 与错误处理；`ProtectedRoute` 三态守卫（未登录 / 需改密 / 非 admin）；员工页 Table + Modal 对接 `/admin/employees`。

**Tech Stack:** React **18.3.1** · TypeScript **~5.6** · Vite **5.x** · Ant Design **5.x** · React Router **6.x**

## Global Constraints

- React **18.3.1**（禁止 17 / 18.0~18.2 / 19）
- TypeScript **~5.6** / Vite **5.x**（禁止 Vite 4）
- Node **20 LTS**（≥18，见根目录 `.nvmrc`）
- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- Frontend **不直连数据库**，只调 Backend API
- 页面放 `frontend/src/pages/`，API 放 `frontend/src/api/`，组件放 `frontend/src/components/`
- Token 存 `localStorage` key `king_shop_token`
- M11 **无前端自动化测试**，以手工验收清单为完成门槛
- 完成门槛：`npm run build` 无 TS 错误 + 手工验收清单全通过

---

### Task 1: 依赖安装、类型定义与 API Client

**Files:**
- Modify: `frontend/package.json`
- Create: `frontend/src/types/api.ts`
- Create: `frontend/src/types/employee.ts`
- Create: `frontend/src/api/client.ts`

**Interfaces:**
- Produces: `ApiResponse<T>`, `PaginatedMeta`, `ApiError` class
- Produces: `request<T>(path, options?)` — 自动附加 Bearer、解析 JSON、抛 ApiError
- Produces: `getToken()`, `setToken()`, `clearToken()`
- Produces: `Employee`, `Role`, `Status`, `CreateEmployeePayload`, `UpdateEmployeePayload`

- [ ] **Step 1: Install dependencies**

```bash
cd frontend
npm install antd react-router-dom @ant-design/icons
```

- [ ] **Step 2: Create types**

```typescript
// frontend/src/types/api.ts
export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

export interface PaginatedMeta {
  total: number;
  page: number;
  per_page: number;
}

export interface PaginatedResult<T> {
  items: T[];
  meta: PaginatedMeta;
}
```

```typescript
// frontend/src/types/employee.ts
export type Role = 'employee' | 'admin' | 'super_admin';
export type Status = 'active' | 'disabled';

export interface Employee {
  id: number;
  name: string;
  phone: string;
  employee_no: string | null;
  department: string | null;
  role: Role;
  status: Status;
  avatar: string | null;
  must_change_password: boolean;
}

export interface AuthUser extends Employee {}

export interface LoginResult {
  token: string;
  user: AuthUser;
  must_change_password: boolean;
}

export interface CreateEmployeePayload {
  name: string;
  phone: string;
  employee_no?: string;
  department?: string;
  role?: Role;
}

export interface UpdateEmployeePayload {
  name: string;
  employee_no?: string;
  department?: string;
  role: Role;
  status: Status;
  reset_password?: boolean;
}

export interface EmployeeListParams {
  keyword?: string;
  page?: number;
  per_page?: number;
}
```

- [ ] **Step 3: Implement API client**

```typescript
// frontend/src/api/client.ts
import type { ApiResponse } from '../types/api';

const TOKEN_KEY = 'king_shop_token';

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: number,
    message: string,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

type OnUnauthorized = () => void;
let onUnauthorized: OnUnauthorized | null = null;

export function setOnUnauthorized(handler: OnUnauthorized): void {
  onUnauthorized = handler;
}

const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1';

export async function request<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers as Record<string, string> | undefined),
  };

  const token = getToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path}`, { ...options, headers });
  } catch {
    throw new ApiError(0, 0, '网络异常，请重试');
  }

  let body: ApiResponse<T> | null = null;
  try {
    body = (await response.json()) as ApiResponse<T>;
  } catch {
    throw new ApiError(response.status, response.status, '响应解析失败');
  }

  if (response.status === 401) {
    clearToken();
    onUnauthorized?.();
    throw new ApiError(401, body?.code ?? 401, body?.message ?? '未授权');
  }

  if (!response.ok || body.code !== 0) {
    const errors = (body as ApiResponse<unknown> & { errors?: Record<string, string[]> }).errors;
    throw new ApiError(
      response.status,
      body.code,
      body.message ?? '请求失败',
      errors,
    );
  }

  return body.data;
}
```

- [ ] **Step 4: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS（可能仅有 App.tsx 占位，无类型错误）

---

### Task 2: Auth API 与 AuthContext

**Files:**
- Create: `frontend/src/api/auth.ts`
- Create: `frontend/src/contexts/AuthContext.tsx`

**Interfaces:**
- Consumes: `request`, `setToken`, `clearToken`, `setOnUnauthorized` from `api/client.ts`
- Produces: `authApi.login(phone, password): Promise<LoginResult>`
- Produces: `authApi.me(): Promise<AuthUser>`
- Produces: `authApi.changePassword(current, newPassword, confirm): Promise<void>`
- Produces: `authApi.logout(): Promise<void>`
- Produces: `useAuth()` → `{ user, token, loading, login, logout, refreshUser }`
- Produces: `AuthProvider` component

- [ ] **Step 1: Create auth API**

```typescript
// frontend/src/api/auth.ts
import { request } from './client';
import type { AuthUser, LoginResult } from '../types/employee';

export const authApi = {
  login(phone: string, password: string): Promise<LoginResult> {
    return request<LoginResult>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ phone, password }),
    });
  },

  me(): Promise<AuthUser> {
    return request<AuthUser>('/auth/me');
  },

  changePassword(
    currentPassword: string,
    newPassword: string,
    newPasswordConfirmation: string,
  ): Promise<void> {
    return request<void>('/auth/password', {
      method: 'PUT',
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation,
      }),
    });
  },

  logout(): Promise<void> {
    return request<void>('/auth/logout', { method: 'POST' });
  },
};
```

- [ ] **Step 2: Implement AuthContext**

```typescript
// frontend/src/contexts/AuthContext.tsx
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
```

- [ ] **Step 3: Verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

---

### Task 3: 路由守卫、App 入口与全局样式

**Files:**
- Create: `frontend/src/components/ProtectedRoute.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/main.tsx`
- Modify: `frontend/src/index.css`

**Interfaces:**
- Consumes: `useAuth()`, `isAdminRole()` from AuthContext
- Produces: `ProtectedRoute` — 处理 loading / 未登录 / 需改密 / 非 admin
- Produces: `App` — BrowserRouter + ConfigProvider(zhCN) + Routes

- [ ] **Step 1: ProtectedRoute**

```typescript
// frontend/src/components/ProtectedRoute.tsx
import { Navigate, useLocation } from 'react-router-dom';
import { Spin, Result, Button } from 'antd';
import { useAuth, isAdminRole } from '../contexts/AuthContext';

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user, token, loading, logout } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', paddingTop: 120 }}>
        <Spin size="large" />
      </div>
    );
  }

  if (!token || !user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (user.must_change_password && location.pathname !== '/change-password') {
    return <Navigate to="/change-password" replace />;
  }

  if (!isAdminRole(user.role)) {
    return (
      <Result
        status="403"
        title="无权访问"
        subTitle="您的账号没有管理后台权限"
        extra={
          <Button type="primary" onClick={() => void logout()}>
            退出登录
          </Button>
        }
      />
    );
  }

  return <>{children}</>;
}
```

- [ ] **Step 2: Update App.tsx with router shell**

```typescript
// frontend/src/App.tsx
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { ConfigProvider } from 'antd';
import zhCN from 'antd/locale/zh_CN';
import { AuthProvider } from './contexts/AuthContext';
import { ProtectedRoute } from './components/ProtectedRoute';
import LoginPage from './pages/LoginPage';
import ChangePasswordPage from './pages/ChangePasswordPage';
import AdminLayout from './components/AdminLayout';
import EmployeeListPage from './pages/employees/EmployeeListPage';

export default function App() {
  return (
    <ConfigProvider locale={zhCN}>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              path="/change-password"
              element={
                <ProtectedRoute>
                  <ChangePasswordPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <AdminLayout />
                </ProtectedRoute>
              }
            >
              <Route index element={<Navigate to="/employees" replace />} />
              <Route path="employees" element={<EmployeeListPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </ConfigProvider>
  );
}
```

- [ ] **Step 3: Simplify index.css**

```css
/* frontend/src/index.css */
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

#root {
  min-height: 100vh;
}
```

- [ ] **Step 4: Create placeholder pages (minimal stubs for build)**

先创建三个占位文件使 `App.tsx` 可编译，Task 4/5/6 会替换为完整实现：

```typescript
// frontend/src/pages/LoginPage.tsx
export default function LoginPage() {
  return null;
}
```

```typescript
// frontend/src/pages/ChangePasswordPage.tsx
export default function ChangePasswordPage() {
  return null;
}
```

```typescript
// frontend/src/components/AdminLayout.tsx
import { Outlet } from 'react-router-dom';
export default function AdminLayout() {
  return <Outlet />;
}
```

```typescript
// frontend/src/pages/employees/EmployeeListPage.tsx
export default function EmployeeListPage() {
  return null;
}
```

- [ ] **Step 5: Remove App.css import from main if present; verify build**

```bash
cd frontend && npm run build
```

Expected: PASS

---

### Task 4: 登录页与改密页

**Files:**
- Modify: `frontend/src/pages/LoginPage.tsx`
- Modify: `frontend/src/pages/ChangePasswordPage.tsx`

**Interfaces:**
- Consumes: `authApi.login`, `useAuth().login`, `useAuth().user`
- Consumes: `authApi.changePassword`, `useAuth().refreshUser`
- Consumes: `ApiError` from `api/client.ts`

- [ ] **Step 1: Implement LoginPage**

```typescript
// frontend/src/pages/LoginPage.tsx
import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { authApi } from '../api/auth';
import { ApiError } from '../api/client';
import { useAuth, isAdminRole } from '../contexts/AuthContext';

export default function LoginPage() {
  const navigate = useNavigate();
  const { login, user, token, loading } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!loading && token && user) {
    if (user.must_change_password) {
      return <Navigate to="/change-password" replace />;
    }
    if (isAdminRole(user.role)) {
      return <Navigate to="/employees" replace />;
    }
  }

  const onFinish = async (values: { phone: string; password: string }) => {
    setSubmitting(true);
    setError(null);
    try {
      const result = await authApi.login(values.phone, values.password);
      login(result.token, result.user);
      if (result.must_change_password) {
        navigate('/change-password');
      } else if (isAdminRole(result.user.role)) {
        navigate('/employees');
      }
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.message);
      } else {
        setError('登录失败，请重试');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: '#f0f2f5',
      }}
    >
      <Card style={{ width: 400 }}>
        <Typography.Title level={3} style={{ textAlign: 'center', marginBottom: 24 }}>
          King Shop 管理后台
        </Typography.Title>
        {error && (
          <Alert type="error" message={error} style={{ marginBottom: 16 }} showIcon />
        )}
        <Form layout="vertical" onFinish={onFinish}>
          <Form.Item
            label="手机号"
            name="phone"
            rules={[
              { required: true, message: '请输入手机号' },
              { pattern: /^1\d{10}$/, message: '请输入 11 位手机号' },
            ]}
          >
            <Input placeholder="手机号" size="large" />
          </Form.Item>
          <Form.Item
            label="密码"
            name="password"
            rules={[{ required: true, message: '请输入密码' }]}
          >
            <Input.Password placeholder="密码" size="large" />
          </Form.Item>
          <Button type="primary" htmlType="submit" block size="large" loading={submitting}>
            登录
          </Button>
        </Form>
      </Card>
    </div>
  );
}
```

- [ ] **Step 2: Implement ChangePasswordPage**

```typescript
// frontend/src/pages/ChangePasswordPage.tsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Alert, Button, Card, Form, Input, Typography, message } from 'antd';
import { authApi } from '../api/auth';
import { ApiError } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

export default function ChangePasswordPage() {
  const navigate = useNavigate();
  const { refreshUser } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onFinish = async (values: {
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
  }) => {
    setSubmitting(true);
    setError(null);
    try {
      await authApi.changePassword(
        values.current_password,
        values.new_password,
        values.new_password_confirmation,
      );
      await refreshUser();
      message.success('密码修改成功');
      navigate('/employees');
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.message);
      } else {
        setError('修改失败，请重试');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: '#f0f2f5',
      }}
    >
      <Card style={{ width: 400 }}>
        <Typography.Title level={4} style={{ textAlign: 'center', marginBottom: 8 }}>
          修改密码
        </Typography.Title>
        <Typography.Paragraph type="secondary" style={{ textAlign: 'center', marginBottom: 24 }}>
          首次登录须修改密码后才能使用管理功能
        </Typography.Paragraph>
        {error && (
          <Alert type="error" message={error} style={{ marginBottom: 16 }} showIcon />
        )}
        <Form layout="vertical" onFinish={onFinish}>
          <Form.Item
            label="当前密码"
            name="current_password"
            rules={[{ required: true, message: '请输入当前密码' }]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label="新密码"
            name="new_password"
            rules={[
              { required: true, message: '请输入新密码' },
              { min: 6, message: '密码至少 6 位' },
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label="确认新密码"
            name="new_password_confirmation"
            dependencies={['new_password']}
            rules={[
              { required: true, message: '请确认新密码' },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  if (!value || getFieldValue('new_password') === value) {
                    return Promise.resolve();
                  }
                  return Promise.reject(new Error('两次密码不一致'));
                },
              }),
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Button type="primary" htmlType="submit" block loading={submitting}>
            确认修改
          </Button>
        </Form>
      </Card>
    </div>
  );
}
```

- [ ] **Step 3: Manual smoke test**

```bash
./scripts/dev-up.sh
cd frontend && npm run dev
```

打开 `http://localhost:5173/login`，用 `13800000000` / `admin123` 登录，应跳转 `/employees`（列表页可能空白，正常）。

---

### Task 5: AdminLayout

**Files:**
- Modify: `frontend/src/components/AdminLayout.tsx`

**Interfaces:**
- Consumes: `useAuth().user`, `useAuth().logout`
- Produces: Sider 菜单「员工管理」+ Header 用户名/角色 + Outlet

- [ ] **Step 1: Implement AdminLayout**

```typescript
// frontend/src/components/AdminLayout.tsx
import { useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Layout, Menu, Button, Space, Tag, Typography } from 'antd';
import { TeamOutlined, LogoutOutlined, MenuFoldOutlined, MenuUnfoldOutlined } from '@ant-design/icons';
import { useAuth } from '../contexts/AuthContext';

const { Header, Sider, Content } = Layout;

const roleLabels: Record<string, string> = {
  employee: '员工',
  admin: '管理员',
  super_admin: '超级管理员',
};

const roleColors: Record<string, string> = {
  employee: 'blue',
  admin: 'orange',
  super_admin: 'red',
};

export default function AdminLayout() {
  const [collapsed, setCollapsed] = useState(false);
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const selectedKey = location.pathname.startsWith('/employees') ? 'employees' : '';

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider collapsible collapsed={collapsed} trigger={null} theme="light">
        <div
          style={{
            height: 64,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontWeight: 600,
            fontSize: collapsed ? 14 : 16,
          }}
        >
          {collapsed ? 'KS' : 'King Shop'}
        </div>
        <Menu
          mode="inline"
          selectedKeys={[selectedKey]}
          items={[
            {
              key: 'employees',
              icon: <TeamOutlined />,
              label: '员工管理',
              onClick: () => navigate('/employees'),
            },
          ]}
        />
      </Sider>
      <Layout>
        <Header
          style={{
            padding: '0 24px',
            background: '#fff',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            borderBottom: '1px solid #f0f0f0',
          }}
        >
          <Button
            type="text"
            icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
            onClick={() => setCollapsed(!collapsed)}
          />
          <Space>
            <Typography.Text>{user?.name}</Typography.Text>
            {user && (
              <Tag color={roleColors[user.role]}>{roleLabels[user.role]}</Tag>
            )}
            <Button
              type="link"
              icon={<LogoutOutlined />}
              onClick={() => void logout()}
            >
              退出
            </Button>
          </Space>
        </Header>
        <Content style={{ margin: 24 }}>
          <Outlet />
        </Content>
      </Layout>
    </Layout>
  );
}
```

- [ ] **Step 2: Manual verify**

登录后应看到侧边栏「员工管理」、顶栏用户名与退出按钮。

---

### Task 6: 员工 API、Form Modal 与列表页

**Files:**
- Create: `frontend/src/api/employees.ts`
- Create: `frontend/src/components/EmployeeFormModal.tsx`
- Modify: `frontend/src/pages/employees/EmployeeListPage.tsx`

**Interfaces:**
- Consumes: `request<T>` from `api/client.ts`
- Produces: `employeesApi.list/create/update/disable`
- Produces: `EmployeeFormModal` props `{ open, mode, employee?, onClose, onSuccess }`

- [ ] **Step 1: employees API**

```typescript
// frontend/src/api/employees.ts
import { request } from './client';
import type { PaginatedResult } from '../types/api';
import type {
  CreateEmployeePayload,
  Employee,
  EmployeeListParams,
  UpdateEmployeePayload,
} from '../types/employee';

function toQuery(params: EmployeeListParams): string {
  const q = new URLSearchParams();
  if (params.keyword) q.set('keyword', params.keyword);
  if (params.page) q.set('page', String(params.page));
  if (params.per_page) q.set('per_page', String(params.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

export const employeesApi = {
  list(params: EmployeeListParams = {}): Promise<PaginatedResult<Employee>> {
    return request<PaginatedResult<Employee>>(`/admin/employees${toQuery(params)}`);
  },

  create(payload: CreateEmployeePayload): Promise<Employee> {
    return request<Employee>('/admin/employees', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id: number, payload: UpdateEmployeePayload): Promise<Employee> {
    return request<Employee>(`/admin/employees/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  disable(id: number): Promise<Employee> {
    return request<Employee>(`/admin/employees/${id}`, { method: 'DELETE' });
  },
};
```

- [ ] **Step 2: EmployeeFormModal**

```typescript
// frontend/src/components/EmployeeFormModal.tsx
import { useEffect } from 'react';
import { Form, Input, Modal, Select, Switch, message } from 'antd';
import { employeesApi } from '../api/employees';
import { ApiError } from '../api/client';
import type { Employee, Role } from '../types/employee';
import { useAuth } from '../contexts/AuthContext';

interface Props {
  open: boolean;
  mode: 'create' | 'edit';
  employee?: Employee | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function EmployeeFormModal({
  open,
  mode,
  employee,
  onClose,
  onSuccess,
}: Props) {
  const [form] = Form.useForm();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = currentUser?.role === 'super_admin';
  const isSelf = mode === 'edit' && employee?.id === currentUser?.id;

  useEffect(() => {
    if (!open) return;
    if (mode === 'edit' && employee) {
      form.setFieldsValue({
        name: employee.name,
        phone: employee.phone,
        employee_no: employee.employee_no ?? '',
        department: employee.department ?? '',
        role: employee.role,
        status: employee.status,
        reset_password: false,
      });
    } else {
      form.resetFields();
      form.setFieldsValue({ role: 'employee' });
    }
  }, [open, mode, employee, form]);

  const roleOptions: { value: Role; label: string }[] = isSuperAdmin
    ? [
        { value: 'employee', label: '员工' },
        { value: 'admin', label: '管理员' },
        { value: 'super_admin', label: '超级管理员' },
      ]
    : [{ value: 'employee', label: '员工' }];

  const handleSubmit = async () => {
    const values = await form.validateFields();
    try {
      if (mode === 'create') {
        await employeesApi.create({
          name: values.name,
          phone: values.phone,
          employee_no: values.employee_no || undefined,
          department: values.department || undefined,
          role: values.role,
        });
        message.success('创建成功，默认密码为 123456');
      } else if (employee) {
        await employeesApi.update(employee.id, {
          name: values.name,
          employee_no: values.employee_no || undefined,
          department: values.department || undefined,
          role: values.role,
          status: values.status,
          reset_password: values.reset_password || false,
        });
        message.success('保存成功');
      }
      onSuccess();
      onClose();
    } catch (e) {
      if (e instanceof ApiError && e.errors) {
        const fields = Object.entries(e.errors).map(([name, msgs]) => ({
          name,
          errors: msgs,
        }));
        form.setFields(fields);
      } else if (e instanceof ApiError) {
        message.error(e.message);
      } else {
        message.error('操作失败');
      }
    }
  };

  return (
    <Modal
      title={mode === 'create' ? '新增员工' : '编辑员工'}
      open={open}
      onCancel={onClose}
      onOk={() => void handleSubmit()}
      destroyOnClose
      width={480}
    >
      <Form form={form} layout="vertical">
        <Form.Item
          label="姓名"
          name="name"
          rules={[{ required: true, message: '请输入姓名' }]}
        >
          <Input />
        </Form.Item>
        {mode === 'create' && (
          <Form.Item
            label="手机号"
            name="phone"
            rules={[
              { required: true, message: '请输入手机号' },
              { pattern: /^1\d{10}$/, message: '请输入 11 位手机号' },
            ]}
          >
            <Input />
          </Form.Item>
        )}
        <Form.Item label="工号" name="employee_no">
          <Input />
        </Form.Item>
        <Form.Item label="部门" name="department">
          <Input />
        </Form.Item>
        <Form.Item label="角色" name="role" rules={[{ required: true }]}>
          <Select options={roleOptions} disabled={isSelf} />
        </Form.Item>
        {mode === 'edit' && (
          <>
            <Form.Item label="状态" name="status" rules={[{ required: true }]}>
              <Select
                disabled={isSelf}
                options={[
                  { value: 'active', label: '正常' },
                  { value: 'disabled', label: '已禁用' },
                ]}
              />
            </Form.Item>
            <Form.Item label="重置密码" name="reset_password" valuePropName="checked">
              <Switch />
            </Form.Item>
          </>
        )}
      </Form>
    </Modal>
  );
}
```

- [ ] **Step 3: EmployeeListPage**

```typescript
// frontend/src/pages/employees/EmployeeListPage.tsx
import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Input,
  Popconfirm,
  Space,
  Table,
  Tag,
  message,
  type TablePaginationConfig,
} from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { employeesApi } from '../../api/employees';
import { ApiError } from '../../api/client';
import EmployeeFormModal from '../../components/EmployeeFormModal';
import { useAuth } from '../../contexts/AuthContext';
import type { Employee } from '../../types/employee';

const roleLabels: Record<string, string> = {
  employee: '员工',
  admin: '管理员',
  super_admin: '超级管理员',
};

const roleColors: Record<string, string> = {
  employee: 'blue',
  admin: 'orange',
  super_admin: 'red',
};

export default function EmployeeListPage() {
  const { user: currentUser } = useAuth();
  const [items, setItems] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [modalOpen, setModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await employeesApi.list({ keyword, page, per_page: perPage });
      setItems(result.items);
      setTotal(result.meta.total);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setLoading(false);
    }
  }, [keyword, page, perPage]);

  useEffect(() => {
    void fetchList();
  }, [fetchList]);

  const handleSearch = (value: string) => {
    setKeyword(value);
    setPage(1);
  };

  const openCreate = () => {
    setModalMode('create');
    setEditingEmployee(null);
    setModalOpen(true);
  };

  const openEdit = (record: Employee) => {
    setModalMode('edit');
    setEditingEmployee(record);
    setModalOpen(true);
  };

  const handleResetPassword = async (record: Employee) => {
    try {
      await employeesApi.update(record.id, {
        name: record.name,
        employee_no: record.employee_no ?? undefined,
        department: record.department ?? undefined,
        role: record.role,
        status: record.status,
        reset_password: true,
      });
      message.success('密码已重置为 123456');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    }
  };

  const handleDisable = async (record: Employee) => {
    try {
      await employeesApi.disable(record.id);
      message.success('已禁用');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    }
  };

  const columns = [
    { title: '姓名', dataIndex: 'name', key: 'name' },
    { title: '手机号', dataIndex: 'phone', key: 'phone' },
    {
      title: '工号',
      dataIndex: 'employee_no',
      key: 'employee_no',
      render: (v: string | null) => v ?? '-',
    },
    {
      title: '部门',
      dataIndex: 'department',
      key: 'department',
      render: (v: string | null) => v ?? '-',
    },
    {
      title: '角色',
      dataIndex: 'role',
      key: 'role',
      render: (role: string) => (
        <Tag color={roleColors[role]}>{roleLabels[role]}</Tag>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'default'}>
          {status === 'active' ? '正常' : '已禁用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      render: (_: unknown, record: Employee) => {
        const isSelf = record.id === currentUser?.id;
        return (
          <Space>
            <Button type="link" size="small" onClick={() => openEdit(record)}>
              编辑
            </Button>
            <Popconfirm
              title="确认重置为默认密码 123456？"
              onConfirm={() => void handleResetPassword(record)}
            >
              <Button type="link" size="small">
                重置密码
              </Button>
            </Popconfirm>
            {record.status === 'active' && !isSelf && (
              <Popconfirm
                title="确认禁用该员工？"
                onConfirm={() => void handleDisable(record)}
              >
                <Button type="link" size="small" danger>
                  禁用
                </Button>
              </Popconfirm>
            )}
          </Space>
        );
      },
    },
  ];

  const pagination: TablePaginationConfig = {
    current: page,
    pageSize: perPage,
    total,
    showSizeChanger: false,
    onChange: (p) => setPage(p),
  };

  return (
    <>
      <Space style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
        <Input.Search
          placeholder="搜索姓名 / 手机号 / 工号"
          allowClear
          onSearch={handleSearch}
          style={{ width: 280 }}
        />
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
          新增员工
        </Button>
      </Space>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={pagination}
      />
      <EmployeeFormModal
        open={modalOpen}
        mode={modalMode}
        employee={editingEmployee}
        onClose={() => setModalOpen(false)}
        onSuccess={() => void fetchList()}
      />
    </>
  );
}
```

- [ ] **Step 4: Delete obsolete App.css if unused**

移除 `App.tsx` 中对 `App.css` 的引用（若仍存在），可删除 `frontend/src/App.css`。

- [ ] **Step 5: Final build**

```bash
cd frontend && npm run build
```

Expected: PASS，无 TypeScript 错误

---

### Task 7: 联调与手工验收

**Files:** 无新增

- [ ] **Step 1: 启动环境**

```bash
./scripts/dev-up.sh
cd frontend && npm run dev
```

- [ ] **Step 2: 执行验收清单**

| # | 步骤 | 预期 |
|---|------|------|
| 1 | `13800000000` / `admin123` 登录 | 进入员工列表 |
| 2 | 新增员工（手机号未占用） | 成功，提示默认密码 123456 |
| 3 | keyword 搜索刚创建的员工 | 列表过滤正确 |
| 4 | 编辑员工部门 | 保存成功 |
| 5 | 重置密码 | 成功提示 |
| 6 | 禁用员工 | 状态变灰；该账号无法登录 |
| 7 | 用 employee 角色 token 访问（或创建 employee 尝试登录后台） | 无权访问页 |
| 8 | 登出后刷新 | 跳转登录页 |
| 9 | admin 账号（非 super_admin）创建员工 | 角色下拉仅 employee |
| 10 | 当前用户行 | 无「禁用」按钮 |

- [ ] **Step 3: 记录验收**

在 `docs/superpowers/records/2026-07-12-M11-admin-employees.md` 记录验收结果（可选，完成时创建）。

---

## Plan Self-Review

| Spec 章节 | 对应 Task |
|-----------|-----------|
| §2 设计决策 | Task 1–6 全局约束 |
| §3.1 鉴权流程 | Task 2, 3, 4 |
| §3.2 目录结构 | Task 1–6 文件路径 |
| §4 API 对接 | Task 1, 2, 6 |
| §5 页面设计 | Task 4, 5, 6 |
| §6 权限矩阵 | Task 6 Modal + List 按钮隐藏 |
| §7 错误处理 | Task 1 ApiError + 各页 message |
| §9 验收标准 | Task 7 |

无 TBD / 占位符；类型名前后一致。

# M08 App 登录与商品浏览 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 RN 员工端登录、首次改密、分类 Tab + 商品列表/详情浏览，Token AsyncStorage 持久化，对接 M03 Auth 与 M04 App Catalog API。

**Architecture:** 方案 1 — `@react-navigation/native-stack` 三态 RootNavigator（Auth / ChangePassword / Main）；`AuthContext` 管理 token 与 user；`api/client.ts` 统一 fetch + Bearer；各 Screen 本地 state 拉取商品数据，HomeScreen 支持下拉刷新与分页。

**Tech Stack:** React Native **0.76.9** · React **18.3.1** · TypeScript **5.0.4** · Jest 29 · AsyncStorage · React Navigation 6

## Global Constraints

- React **18.3.1**（禁止 17 / 18.0~18.2 / 19）
- React Native **0.76.9**（禁止 < 0.76）
- TypeScript **5.0.4**
- API 前缀 `/api/v1/`，响应 `{ "code": 0, "message": "ok", "data": {} }`
- App **不直连数据库**，只调 Backend API
- 屏幕放 `app/src/screens/`，导航放 `app/src/navigation/`，API 放 `app/src/api/`
- Android 优先验收；本地联调 `http://10.0.2.2:8000/api/v1`（Android 模拟器）
- TDD：纯函数与 API client 先写 Jest 失败测试再实现
- 完成门槛：`cd app && npm test` 全绿 + Android 手工验收清单

---

### Task 1: 依赖安装、配置、类型与 formatPrice

**Files:**
- Modify: `app/package.json`（新增 navigation / async-storage 依赖）
- Create: `app/src/config/api.ts`
- Create: `app/src/types/api.ts`
- Create: `app/src/utils/formatPrice.ts`
- Create: `app/__tests__/utils/formatPrice.test.ts`
- Modify: `app/android/app/src/main/AndroidManifest.xml`（cleartext traffic）

**Interfaces:**
- Produces: `API_BASE_URL: string` — Android `http://10.0.2.2:8000/api/v1`
- Produces: `formatPrice(cents: number): string` — 如 `1500` → `'¥15.00'`
- Produces: types `User`, `Category`, `Product`, `PaginatedProducts`, `ApiResponse<T>`

- [ ] **Step 1: Install dependencies**

```bash
cd app
npm install @react-navigation/native @react-navigation/native-stack \
  react-native-screens react-native-safe-area-context \
  @react-native-async-storage/async-storage
```

- [ ] **Step 2: Write failing formatPrice test**

```typescript
// app/__tests__/utils/formatPrice.test.ts
import {formatPrice} from '../../src/utils/formatPrice';

describe('formatPrice', () => {
  it('formats cents to yuan with two decimals', () => {
    expect(formatPrice(1500)).toBe('¥15.00');
  });

  it('formats zero', () => {
    expect(formatPrice(0)).toBe('¥0.00');
  });

  it('formats fractional yuan cents', () => {
    expect(formatPrice(99)).toBe('¥0.99');
  });
});
```

- [ ] **Step 3: Run test — expect FAIL**

```bash
cd app && npm test -- --testPathPattern=formatPrice
```

Expected: FAIL — module not found

- [ ] **Step 4: Implement formatPrice + types + config**

```typescript
// app/src/utils/formatPrice.ts
export function formatPrice(cents: number): string {
  return `¥${(cents / 100).toFixed(2)}`;
}
```

```typescript
// app/src/types/api.ts
export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

export interface User {
  id: number;
  name: string;
  phone: string;
  employee_no: string | null;
  department: string | null;
  role: string;
  status: string;
  avatar: string | null;
  must_change_password: boolean;
}

export interface LoginResult {
  token: string;
  user: User;
  must_change_password: boolean;
}

export interface Category {
  id: number;
  name: string;
  sort: number;
}

export interface Product {
  id: number;
  name: string;
  description: string | null;
  price: number;
  image_url: string | null;
  category_id: number;
  category_name?: string;
  status: string;
}

export interface PaginatedProducts {
  items: Product[];
  meta: {total: number; page: number; per_page: number};
}
```

```typescript
// app/src/config/api.ts
import {Platform} from 'react-native';

const DEV_HOST = Platform.OS === 'android' ? '10.0.2.2' : 'localhost';

/** 真机调试时改为电脑局域网 IP，如 192.168.1.100 */
export const API_BASE_URL = `http://${DEV_HOST}:8000/api/v1`;
```

- [ ] **Step 5: Enable Android cleartext HTTP**

在 `app/android/app/src/main/AndroidManifest.xml` 的 `<application>` 标签增加：

```xml
android:usesCleartextTraffic="true"
```

- [ ] **Step 6: Run test — expect PASS**

```bash
cd app && npm test -- --testPathPattern=formatPrice
```

- [ ] **Step 7: Commit**

```bash
git add app/package.json app/package-lock.json \
  app/src/config/api.ts app/src/types/api.ts app/src/utils/formatPrice.ts \
  app/__tests__/utils/formatPrice.test.ts \
  app/android/app/src/main/AndroidManifest.xml
git commit -m "feat(M08): add app config, types, and formatPrice utility"
```

---

### Task 2: API Client 与 Token Storage

**Files:**
- Create: `app/src/api/client.ts`
- Create: `app/src/api/tokenStorage.ts`
- Create: `app/__tests__/api/client.test.ts`
- Create: `app/__tests__/api/tokenStorage.test.ts`

**Interfaces:**
- Produces: `class ApiError extends Error { code: number }`
- Produces: `setTokenGetter(fn: () => string | null): void`
- Produces: `apiRequest<T>(path: string, options?: RequestInit): Promise<T>`
- Produces: `TOKEN_KEY = '@king_shop/token'`
- Produces: `getToken(): Promise<string | null>`, `setToken(token: string): Promise<void>`, `clearToken(): Promise<void>`

- [ ] **Step 1: Write failing client test**

```typescript
// app/__tests__/api/client.test.ts
import {apiRequest, ApiError, setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();

beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => null);
});

it('returns data when code is 0', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: {id: 1}}),
  });

  const result = await apiRequest<{id: number}>('/health');
  expect(result).toEqual({id: 1});
});

it('throws ApiError when code is not 0', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 403,
    json: async () => ({code: 40301, message: '请先修改密码', data: null}),
  });

  await expect(apiRequest('/products')).rejects.toMatchObject({
    code: 40301,
    message: '请先修改密码',
  });
});

it('throws ApiError with code 401 on HTTP 401', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: false,
    status: 401,
    json: async () => ({code: 401, message: 'Unauthenticated', data: null}),
  });

  await expect(apiRequest('/auth/me')).rejects.toMatchObject({code: 401});
});

it('sends Authorization header when token exists', async () => {
  setTokenGetter(() => 'test-token');
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: {}}),
  });

  await apiRequest('/auth/me');

  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/me'),
    expect.objectContaining({
      headers: expect.objectContaining({
        Authorization: 'Bearer test-token',
      }),
    }),
  );
});
```

- [ ] **Step 2: Write failing tokenStorage test**

```typescript
// app/__tests__/api/tokenStorage.test.ts
import AsyncStorage from '@react-native-async-storage/async-storage';
import {getToken, setToken, clearToken, TOKEN_KEY} from '../../src/api/tokenStorage';

jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

it('stores and retrieves token', async () => {
  await setToken('abc');
  expect(await getToken()).toBe('abc');
  expect(AsyncStorage.getItem).toHaveBeenCalledWith(TOKEN_KEY);
});

it('clears token', async () => {
  await setToken('abc');
  await clearToken();
  expect(await getToken()).toBeNull();
});
```

- [ ] **Step 3: Run tests — expect FAIL**

```bash
cd app && npm test -- --testPathPattern='api/(client|tokenStorage)'
```

- [ ] **Step 4: Implement client + tokenStorage**

```typescript
// app/src/api/tokenStorage.ts
import AsyncStorage from '@react-native-async-storage/async-storage';

export const TOKEN_KEY = '@king_shop/token';

export async function getToken(): Promise<string | null> {
  return AsyncStorage.getItem(TOKEN_KEY);
}

export async function setToken(token: string): Promise<void> {
  await AsyncStorage.setItem(TOKEN_KEY, token);
}

export async function clearToken(): Promise<void> {
  await AsyncStorage.removeItem(TOKEN_KEY);
}
```

```typescript
// app/src/api/client.ts
import {API_BASE_URL} from '../config/api';
import type {ApiResponse} from '../types/api';

export class ApiError extends Error {
  constructor(
    public code: number,
    message: string,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

let tokenGetter: () => string | null = () => null;

export function setTokenGetter(fn: () => string | null): void {
  tokenGetter = fn;
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = tokenGetter();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
  });

  let body: ApiResponse<T>;
  try {
    body = await response.json();
  } catch {
    throw new ApiError(response.status, '网络响应解析失败');
  }

  if (!response.ok && body.code === undefined) {
    throw new ApiError(response.status, body.message ?? '请求失败');
  }

  if (body.code !== 0) {
    throw new ApiError(body.code, body.message);
  }

  return body.data;
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
cd app && npm test -- --testPathPattern='api/(client|tokenStorage)'
```

- [ ] **Step 6: Commit**

```bash
git add app/src/api/client.ts app/src/api/tokenStorage.ts \
  app/__tests__/api/client.test.ts app/__tests__/api/tokenStorage.test.ts
git commit -m "feat(M08): add API client and token storage"
```

---

### Task 3: Auth API 与 AuthContext

**Files:**
- Create: `app/src/api/auth.ts`
- Create: `app/src/context/AuthContext.tsx`
- Create: `app/__tests__/api/auth.test.ts`

**Interfaces:**
- Produces: `login(phone: string, password: string): Promise<LoginResult>`
- Produces: `getMe(): Promise<User>`
- Produces: `changePassword(current: string, newPassword: string): Promise<void>`
- Produces: `logout(): Promise<void>`
- Produces: `AuthProvider` + `useAuth()` → `{ token, user, isLoading, isAuthenticated, mustChangePassword, login, logout, refreshUser, changePassword }`

- [ ] **Step 1: Write failing auth API test**

```typescript
// app/__tests__/api/auth.test.ts
import {login, getMe, changePassword} from '../../src/api/auth';
import {setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();

beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => 'tok');
});

it('login posts credentials and returns result', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {
        token: '1|abc',
        user: {id: 1, name: '张三', phone: '13800000001', must_change_password: true},
        must_change_password: true,
      },
    }),
  });

  const result = await login('13800000001', '123456');
  expect(result.token).toBe('1|abc');
  expect(result.must_change_password).toBe(true);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/login'),
    expect.objectContaining({method: 'POST'}),
  );
});

it('changePassword sends PUT body', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: null}),
  });

  await changePassword('123456', 'newpass1');
  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/password'),
    expect.objectContaining({
      method: 'PUT',
      body: JSON.stringify({
        current_password: '123456',
        new_password: 'newpass1',
        new_password_confirmation: 'newpass1',
      }),
    }),
  );
});
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
cd app && npm test -- --testPathPattern=auth.test
```

- [ ] **Step 3: Implement auth.ts**

```typescript
// app/src/api/auth.ts
import {apiRequest} from './client';
import type {LoginResult, User} from '../types/api';

export function login(phone: string, password: string): Promise<LoginResult> {
  return apiRequest<LoginResult>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({phone, password}),
  });
}

export function getMe(): Promise<User> {
  return apiRequest<User>('/auth/me');
}

export function changePassword(
  currentPassword: string,
  newPassword: string,
): Promise<void> {
  return apiRequest<void>('/auth/password', {
    method: 'PUT',
    body: JSON.stringify({
      current_password: currentPassword,
      new_password: newPassword,
      new_password_confirmation: newPassword,
    }),
  });
}

export function logout(): Promise<void> {
  return apiRequest<void>('/auth/logout', {method: 'POST'});
}
```

- [ ] **Step 4: Implement AuthContext.tsx**

```typescript
// app/src/context/AuthContext.tsx
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
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
cd app && npm test -- --testPathPattern=auth.test
```

- [ ] **Step 6: Commit**

```bash
git add app/src/api/auth.ts app/src/context/AuthContext.tsx app/__tests__/api/auth.test.ts
git commit -m "feat(M08): add auth API and AuthContext"
```

---

### Task 4: 导航 RootNavigator

**Files:**
- Create: `app/src/navigation/types.ts`
- Create: `app/src/navigation/RootNavigator.tsx`

**Interfaces:**
- Produces: `RootStackParamList` — `{ ProductDetail: { productId: number } }`
- Produces: `RootNavigator` — 根据 `isLoading` / `isAuthenticated` / `mustChangePassword` 切换 Stack

- [ ] **Step 1: Create navigation types**

```typescript
// app/src/navigation/types.ts
export type MainStackParamList = {
  Home: undefined;
  ProductDetail: {productId: number};
};

export type AuthStackParamList = {
  Login: undefined;
};

export type ChangePasswordStackParamList = {
  ChangePassword: undefined;
};
```

- [ ] **Step 2: Implement RootNavigator**

```typescript
// app/src/navigation/RootNavigator.tsx
import React from 'react';
import {ActivityIndicator, View} from 'react-native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAuth} from '../context/AuthContext';
import LoginScreen from '../screens/LoginScreen';
import ChangePasswordScreen from '../screens/ChangePasswordScreen';
import HomeScreen from '../screens/HomeScreen';
import ProductDetailScreen from '../screens/ProductDetailScreen';
import type {
  AuthStackParamList,
  ChangePasswordStackParamList,
  MainStackParamList,
} from './types';

const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const ChangePasswordStack =
  createNativeStackNavigator<ChangePasswordStackParamList>();
const MainStack = createNativeStackNavigator<MainStackParamList>();

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{headerShown: false}}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
    </AuthStack.Navigator>
  );
}

function ChangePasswordNavigator() {
  return (
    <ChangePasswordStack.Navigator>
      <ChangePasswordStack.Screen
        name="ChangePassword"
        component={ChangePasswordScreen}
        options={{title: '修改密码'}}
      />
    </ChangePasswordStack.Navigator>
  );
}

function MainNavigator() {
  return (
    <MainStack.Navigator>
      <MainStack.Screen
        name="Home"
        component={HomeScreen}
        options={{title: '商品'}}
      />
      <MainStack.Screen
        name="ProductDetail"
        component={ProductDetailScreen}
        options={{title: '商品详情'}}
      />
    </MainStack.Navigator>
  );
}

export default function RootNavigator() {
  const {isLoading, isAuthenticated, mustChangePassword} = useAuth();

  if (isLoading) {
    return (
      <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (!isAuthenticated) {
    return <AuthNavigator />;
  }

  if (mustChangePassword) {
    return <ChangePasswordNavigator />;
  }

  return <MainNavigator />;
}
```

- [ ] **Step 3: Create placeholder screens**（空组件避免编译错误，Task 5–7 填充）

```typescript
// app/src/screens/LoginScreen.tsx — placeholder
import React from 'react';
import {Text, View} from 'react-native';
export default function LoginScreen() {
  return <View><Text>Login</Text></View>;
}
```

同理创建 `ChangePasswordScreen.tsx`、`HomeScreen.tsx`、`ProductDetailScreen.tsx` 占位。

- [ ] **Step 4: Wire App.tsx**

```typescript
// app/App.tsx
import React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {AuthProvider} from './src/context/AuthContext';
import RootNavigator from './src/navigation/RootNavigator';

export default function App() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <NavigationContainer>
          <RootNavigator />
        </NavigationContainer>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
```

- [ ] **Step 5: Verify app builds**

```bash
cd app && npx tsc --noEmit
```

- [ ] **Step 6: Commit**

```bash
git add app/src/navigation/ app/src/screens/ app/App.tsx
git commit -m "feat(M08): add navigation shell and wire App entry"
```

---

### Task 5: LoginScreen 与 ChangePasswordScreen

**Files:**
- Modify: `app/src/screens/LoginScreen.tsx`
- Modify: `app/src/screens/ChangePasswordScreen.tsx`

**Interfaces:**
- Consumes: `useAuth().login`, `useAuth().changePassword`
- Handles: `ApiError` code 401 / 403 on login; validation errors on change password

- [ ] **Step 1: Implement LoginScreen**

- 手机号 `TextInput`（`keyboardType="phone-pad"`，`maxLength={11}`）
- 密码 `TextInput`（`secureTextEntry`）
- 登录按钮 → `login(phone, password)`；loading 态
- catch `ApiError`：401 →「手机号或密码错误」；403 →「账号已禁用」；其他 → `message`

- [ ] **Step 2: Implement ChangePasswordScreen**

- 三个密码输入框
- 本地校验：新密码 ≥ 6 位、两次一致
- 提交 → `changePassword(current, newPassword)`；成功后 AuthContext 自动 `refreshUser`，Navigator 切 MainStack
- 422 时展示后端 validation message

- [ ] **Step 3: Manual smoke — Login screen renders**

```bash
cd app && npm start
# 另开终端
cd app && npm run android
```

- [ ] **Step 4: Commit**

```bash
git add app/src/screens/LoginScreen.tsx app/src/screens/ChangePasswordScreen.tsx
git commit -m "feat(M08): add login and change password screens"
```

---

### Task 6: Catalog API 与共享组件

**Files:**
- Create: `app/src/api/catalog.ts`
- Create: `app/src/components/CategoryTabs.tsx`
- Create: `app/src/components/ProductListItem.tsx`
- Create: `app/src/components/EmptyState.tsx`
- Create: `app/src/components/LoadingView.tsx`
- Create: `app/__tests__/api/catalog.test.ts`

**Interfaces:**
- Produces: `fetchCategories(): Promise<Category[]>`
- Produces: `fetchProducts(params: {categoryId?: number; page?: number; perPage?: number}): Promise<PaginatedProducts>`
- Produces: `fetchProduct(id: number): Promise<Product>`

- [ ] **Step 1: Write failing catalog test**

```typescript
// app/__tests__/api/catalog.test.ts
import {fetchCategories, fetchProducts} from '../../src/api/catalog';
import {setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();
beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => 'tok');
});

it('fetchProducts appends category_id query', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {items: [], meta: {total: 0, page: 1, per_page: 20}},
    }),
  });

  await fetchProducts({categoryId: 2, page: 1});
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/products\?.*category_id=2/),
    expect.any(Object),
  );
});

it('fetchProducts omits category_id when null', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {items: [], meta: {total: 0, page: 1, per_page: 20}},
    }),
  });

  await fetchProducts({});
  expect(fetch).toHaveBeenCalledWith(
    expect.not.stringContaining('category_id'),
    expect.any(Object),
  );
});
```

- [ ] **Step 2: Implement catalog.ts**

```typescript
// app/src/api/catalog.ts
import {apiRequest} from './client';
import type {Category, PaginatedProducts, Product} from '../types/api';

export function fetchCategories(): Promise<Category[]> {
  return apiRequest<Category[]>('/categories');
}

export function fetchProducts(params: {
  categoryId?: number | null;
  page?: number;
  perPage?: number;
}): Promise<PaginatedProducts> {
  const search = new URLSearchParams();
  if (params.categoryId != null) {
    search.set('category_id', String(params.categoryId));
  }
  search.set('page', String(params.page ?? 1));
  search.set('per_page', String(params.perPage ?? 20));
  const qs = search.toString();
  return apiRequest<PaginatedProducts>(`/products?${qs}`);
}

export function fetchProduct(id: number): Promise<Product> {
  return apiRequest<Product>(`/products/${id}`);
}
```

- [ ] **Step 3: Implement components**

**CategoryTabs：** props `{ categories: Category[]; selectedId: number | null; onSelect: (id: number | null) => void }` — 首项「全部」`id=null`。

**ProductListItem：** props `{ product: Product; onPress: () => void }` — 左图 80×80，右文名称/描述/价格。

**EmptyState：** props `{ message: string }`

**LoadingView：** 居中 `ActivityIndicator`

- [ ] **Step 4: Run tests — expect PASS**

```bash
cd app && npm test -- --testPathPattern=catalog.test
```

- [ ] **Step 5: Commit**

```bash
git add app/src/api/catalog.ts app/src/components/ app/__tests__/api/catalog.test.ts
git commit -m "feat(M08): add catalog API and list components"
```

---

### Task 7: HomeScreen

**Files:**
- Modify: `app/src/screens/HomeScreen.tsx`

**Interfaces:**
- Consumes: `fetchCategories`, `fetchProducts`, `CategoryTabs`, `ProductListItem`, `EmptyState`, `LoadingView`
- Consumes: `useNavigation<NativeStackNavigationProp<MainStackParamList>>()`
- Handles: `ApiError` 40301 → 由 AuthContext/Navigator 处理（若发生则 refreshUser）

- [ ] **Step 1: Implement HomeScreen state machine**

- `categories`, `selectedCategoryId`（默认 `null` = 全部）
- `products`, `page`, `total`, `isRefreshing`, `isLoadingMore`, `isInitialLoading`, `error`
- `loadCategories()` on mount
- `loadProducts(reset: boolean)` — reset 时 page=1；append 时 page+1
- `RefreshControl` onRefresh → reset
- `FlatList` `onEndReached` → 当 `products.length < total` 时 loadMore
- `ListEmptyComponent` → EmptyState（非 loading 时）
- `ListFooterComponent` → loading more indicator

- [ ] **Step 2: Category tab change → reset list**

- [ ] **Step 3: Item press → `navigation.navigate('ProductDetail', { productId: item.id })`**

- [ ] **Step 4: Commit**

```bash
git add app/src/screens/HomeScreen.tsx
git commit -m "feat(M08): add home screen with category tabs and product list"
```

---

### Task 8: ProductDetailScreen 与最终集成

**Files:**
- Modify: `app/src/screens/ProductDetailScreen.tsx`

**Interfaces:**
- Consumes: `route.params.productId`, `fetchProduct`
- Shows: 404 ApiError →「商品不存在或已下架」

- [ ] **Step 1: Implement ProductDetailScreen**

- `useEffect` 加载 `fetchProduct(productId)`
- Loading / Error / Success 三态
- 大图 `Image` `resizeMode="cover"`
- 名称、描述、价格

- [ ] **Step 2: Run full Jest suite**

```bash
cd app && npm test
```

Expected: ALL PASS

- [ ] **Step 3: End-to-end manual verification（需 M04 App API 就绪）**

```bash
# 终端 1
./scripts/dev-up.sh

# 终端 2 — 确保 M04 已完成并有 seed 商品
./scripts/docker-test.sh --filter=ProductCatalogApiTest

# 终端 3
cd app && npm start

# 终端 4
cd app && npm run android
```

**手工验收清单：**
1. 用测试员工（`must_change_password=true`，默认密码 `123456`）登录 → 进入改密页
2. 改密成功 → 进入首页，「全部」Tab 有商品
3. 切换分类 Tab → 列表更新
4. 下拉刷新 → 列表重载
5. 点击商品 → 详情页图片/名称/价格正确
6. 下架商品不在列表；点旧 ID 详情显示不存在

- [ ] **Step 4: Commit**

```bash
git add app/src/screens/ProductDetailScreen.tsx
git commit -m "feat(M08): add product detail screen and complete browse flow"
```

---

## Plan Self-Review

**Spec coverage:**

| Spec 要求 | Task |
|---|---|
| 方案 1 Navigation + AuthContext + fetch | Task 2–4 |
| ChangePasswordScreen 强制改密 | Task 4–5 |
| LoginScreen | Task 5 |
| HomeScreen 分类 Tab + 列表 | Task 6–7 |
| ProductDetailScreen | Task 8 |
| AsyncStorage Token | Task 2 |
| api.ts 配置 10.0.2.2 | Task 1 |
| 「全部」Tab | Task 6–7 |
| 单列左图右文 | Task 6 |
| formatPrice | Task 1 |
| Jest 单元测试 | Task 1–3, 6 |
| Android cleartext | Task 1 |
| 不含购买按钮 | Task 8（无购买 UI） |

**Placeholder scan:** 无 TBD/TODO。Task 5 Screen 实现步骤为行为描述（UI 代码在实现时按 Spec 6 节编写，属 RN 视图层）。

**Type consistency:** `Category.id`, `Product.id`, `productId` param, `PaginatedProducts.meta` 全链路一致。

**Dependency note:** Task 8 手工验收依赖 M04 Task 7（App Catalog API）；M08 代码可先行，E2E 验收在 M04 合并后进行。

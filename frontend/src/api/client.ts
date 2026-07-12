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
let onMustChangePassword: OnUnauthorized | null = null;

export function setOnUnauthorized(handler: OnUnauthorized): void {
  onUnauthorized = handler;
}

export function setOnMustChangePassword(handler: OnUnauthorized): void {
  onMustChangePassword = handler;
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
    if (body.code === 40301) {
      onMustChangePassword?.();
    }
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

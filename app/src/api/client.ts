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

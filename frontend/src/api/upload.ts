import {
  ApiError,
  clearToken,
  getToken,
} from './client';
import type { ApiResponse } from '../types/api';
import type { UploadResult } from '../types/product';

const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1';

type OnUnauthorized = () => void;
let onUnauthorized: OnUnauthorized | null = null;
let onMustChangePassword: OnUnauthorized | null = null;

export function setUploadOnUnauthorized(handler: OnUnauthorized): void {
  onUnauthorized = handler;
}

export function setUploadOnMustChangePassword(handler: OnUnauthorized): void {
  onMustChangePassword = handler;
}

export async function uploadImage(file: File): Promise<UploadResult> {
  const formData = new FormData();
  formData.append('file', file);

  const headers: Record<string, string> = { Accept: 'application/json' };
  const token = getToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let response: Response;
  try {
    response = await fetch(`${baseUrl}/admin/upload`, {
      method: 'POST',
      headers,
      body: formData,
    });
  } catch {
    throw new ApiError(0, 0, '网络异常，请重试');
  }

  let body: ApiResponse<UploadResult> | null = null;
  try {
    body = (await response.json()) as ApiResponse<UploadResult>;
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
      body.message ?? '上传失败',
      errors,
    );
  }

  return body.data;
}

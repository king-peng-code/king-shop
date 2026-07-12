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

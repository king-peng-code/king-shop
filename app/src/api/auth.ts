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

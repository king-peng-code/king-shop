import { request } from './client';
import type { PaginatedResult } from '../types/api';
import type { ExternalUser, UpdateExternalUserPayload } from '../types/externalUser';

export const externalUsersApi = {
  list(keyword = '', page = 1, perPage = 20): Promise<PaginatedResult<ExternalUser>> {
    const q = new URLSearchParams();
    if (keyword) q.set('keyword', keyword);
    q.set('page', String(page));
    q.set('per_page', String(perPage));
    return request<PaginatedResult<ExternalUser>>(`/admin/external-users?${q.toString()}`);
  },

  update(id: number, payload: UpdateExternalUserPayload): Promise<void> {
    return request<void>(`/admin/external-users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
};

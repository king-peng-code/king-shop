import { request } from './client';
import type { ConfigListResult, ConfigUpdatePayload } from '../types/config';

export const configsApi = {
  get(): Promise<ConfigListResult> {
    return request<ConfigListResult>('/admin/configs');
  },

  update(configs: ConfigUpdatePayload[]): Promise<ConfigListResult> {
    return request<ConfigListResult>('/admin/configs', {
      method: 'PUT',
      body: JSON.stringify({ configs }),
    });
  },
};

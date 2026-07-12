import { request } from './client';
import type { DashboardStats } from '../types/dashboard';

export const dashboardApi = {
  getStats(): Promise<DashboardStats> {
    return request<DashboardStats>('/admin/dashboard/stats');
  },
};

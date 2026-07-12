import { request } from './client';
import type { EmployeeStatsItem, ProxyPayerStatsItem } from '../types/statistics';

export const statisticsApi = {
  getEmployeeStats(): Promise<EmployeeStatsItem[]> {
    return request<EmployeeStatsItem[]>('/admin/stats/employees');
  },

  getProxyPayerStats(): Promise<ProxyPayerStatsItem[]> {
    return request<ProxyPayerStatsItem[]>('/admin/stats/proxy-payers');
  },
};

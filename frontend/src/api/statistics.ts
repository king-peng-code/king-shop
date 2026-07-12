import { request } from './client';
import type { EmployeeStatsItem, ProxyPayerStatsItem } from '../types/statistics';

export const statisticsApi = {
  getEmployeeStats(keyword?: string): Promise<EmployeeStatsItem[]> {
    const q = keyword ? `?keyword=${encodeURIComponent(keyword)}` : '';
    return request<EmployeeStatsItem[]>(`/admin/stats/employees${q}`);
  },

  getProxyPayerStats(keyword?: string): Promise<ProxyPayerStatsItem[]> {
    const q = keyword ? `?keyword=${encodeURIComponent(keyword)}` : '';
    return request<ProxyPayerStatsItem[]>(`/admin/stats/proxy-payers${q}`);
  },
};

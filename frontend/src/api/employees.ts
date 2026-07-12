import { request } from './client';
import type { PaginatedResult } from '../types/api';
import type {
  CreateEmployeePayload,
  Employee,
  EmployeeListParams,
  UpdateEmployeePayload,
} from '../types/employee';

function toQuery(params: EmployeeListParams): string {
  const q = new URLSearchParams();
  if (params.keyword) q.set('keyword', params.keyword);
  if (params.page) q.set('page', String(params.page));
  if (params.per_page) q.set('per_page', String(params.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

export const employeesApi = {
  list(params: EmployeeListParams = {}): Promise<PaginatedResult<Employee>> {
    return request<PaginatedResult<Employee>>(`/admin/employees${toQuery(params)}`);
  },

  create(payload: CreateEmployeePayload): Promise<Employee> {
    return request<Employee>('/admin/employees', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id: number, payload: UpdateEmployeePayload): Promise<Employee> {
    return request<Employee>(`/admin/employees/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  disable(id: number): Promise<Employee> {
    return request<Employee>(`/admin/employees/${id}`, { method: 'DELETE' });
  },
};

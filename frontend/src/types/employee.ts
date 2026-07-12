export type Role = 'employee' | 'admin' | 'super_admin';
export type Status = 'active' | 'disabled';

export interface Employee {
  id: number;
  name: string;
  phone: string;
  role: Role;
  status: Status;
  avatar: string | null;
  must_change_password: boolean;
}

export interface AuthUser extends Employee {}

export interface LoginResult {
  token: string;
  user: AuthUser;
  must_change_password: boolean;
}

export interface CreateEmployeePayload {
  name: string;
  phone: string;
  role?: Role;
}

export interface UpdateEmployeePayload {
  name: string;
  role: Role;
  status: Status;
  reset_password?: boolean;
}

export interface EmployeeListParams {
  keyword?: string;
  page?: number;
  per_page?: number;
}

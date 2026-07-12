export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

export interface User {
  id: number;
  name: string;
  phone: string;
  role: string;
  status: string;
  avatar: string | null;
  must_change_password: boolean;
}

export interface LoginResult {
  token: string;
  user: User;
  must_change_password: boolean;
}

export interface Category {
  id: number;
  name: string;
  sort: number;
}

export interface Product {
  id: number;
  name: string;
  description: string | null;
  price: number;
  image_url: string | null;
  category_id: number;
  category_name?: string;
  status: string;
}

export interface PaginatedProducts {
  items: Product[];
  meta: {total: number; page: number; per_page: number};
}

import { request } from './client';
import type { PaginatedResult } from '../types/api';
import type {
  CreateProductPayload,
  Product,
  ProductListParams,
  UpdateProductPayload,
} from '../types/product';

function toQuery(params: ProductListParams): string {
  const q = new URLSearchParams();
  if (params.category_id) q.set('category_id', String(params.category_id));
  if (params.status) q.set('status', params.status);
  if (params.keyword) q.set('keyword', params.keyword);
  if (params.page) q.set('page', String(params.page));
  if (params.per_page) q.set('per_page', String(params.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

export const productsApi = {
  list(params: ProductListParams = {}): Promise<PaginatedResult<Product>> {
    return request<PaginatedResult<Product>>(`/admin/products${toQuery(params)}`);
  },

  create(payload: CreateProductPayload): Promise<Product> {
    return request<Product>('/admin/products', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id: number, payload: UpdateProductPayload): Promise<Product> {
    return request<Product>(`/admin/products/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
};

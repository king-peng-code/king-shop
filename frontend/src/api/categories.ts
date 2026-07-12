import { request } from './client';
import type {
  Category,
  CategoryListResult,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from '../types/category';

export const categoriesApi = {
  list(): Promise<CategoryListResult> {
    return request<CategoryListResult>('/admin/categories');
  },

  create(payload: CreateCategoryPayload): Promise<Category> {
    return request<Category>('/admin/categories', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  update(id: number, payload: UpdateCategoryPayload): Promise<Category> {
    return request<Category>(`/admin/categories/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  delete(id: number): Promise<void> {
    return request<void>(`/admin/categories/${id}`, { method: 'DELETE' });
  },
};

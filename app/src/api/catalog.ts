import {apiRequest} from './client';
import type {Category, PaginatedProducts, Product} from '../types/api';

export function fetchCategories(): Promise<Category[]> {
  return apiRequest<Category[]>('/categories');
}

export function fetchProducts(params: {
  categoryId?: number | null;
  page?: number;
  perPage?: number;
}): Promise<PaginatedProducts> {
  const search = new URLSearchParams();
  if (params.categoryId != null) {
    search.set('category_id', String(params.categoryId));
  }
  search.set('page', String(params.page ?? 1));
  search.set('per_page', String(params.perPage ?? 20));
  const qs = search.toString();
  return apiRequest<PaginatedProducts>(`/products?${qs}`);
}

export function fetchProduct(id: number): Promise<Product> {
  return apiRequest<Product>(`/products/${id}`);
}

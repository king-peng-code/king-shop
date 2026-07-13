import {apiRequest} from './client';
import {buildQueryString} from '../utils/queryString';
import {fixImageUrl} from '../utils/imageUrl';
import type {Category, PaginatedProducts, Product} from '../types/api';

function fixProduct(p: Product): Product {
  return {...p, image_url: fixImageUrl(p.image_url)};
}

export async function fetchCategories(): Promise<Category[]> {
  const data = await apiRequest<{items: Category[]}>('/categories');
  return data.items;
}

export async function fetchProducts(params: {
  categoryId?: number | null;
  page?: number;
  perPage?: number;
}): Promise<PaginatedProducts> {
  const qs = buildQueryString({
    category_id: params.categoryId,
    page: params.page ?? 1,
    per_page: params.perPage ?? 20,
  });
  const data = await apiRequest<PaginatedProducts>(`/products?${qs}`);
  return {...data, items: data.items.map(fixProduct)};
}

export async function fetchProduct(id: number): Promise<Product> {
  const data = await apiRequest<Product>(`/products/${id}`);
  return fixProduct(data);
}

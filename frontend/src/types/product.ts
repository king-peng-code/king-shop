export type ProductStatus = 'on_sale' | 'off_sale';

export interface Product {
  id: number;
  category_id: number;
  category_name: string;
  name: string;
  description: string | null;
  price: number;
  upload_id: number | null;
  image_path: string | null;
  image_url: string | null;
  status: ProductStatus;
  sort: number;
}

export interface CreateProductPayload {
  category_id: number;
  name: string;
  description?: string | null;
  price: number;
  upload_id?: number | null;
  status?: ProductStatus;
  sort?: number;
}

export interface UpdateProductPayload {
  category_id: number;
  name: string;
  description?: string | null;
  price: number;
  upload_id?: number | null;
  status: ProductStatus;
  sort: number;
}

export interface ProductListParams {
  category_id?: number;
  status?: ProductStatus;
  keyword?: string;
  page?: number;
  per_page?: number;
}

export interface UploadResult {
  id: number;
  url: string;
  path: string;
  filename: string;
  size: number;
}

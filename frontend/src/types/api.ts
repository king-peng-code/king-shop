export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

export interface PaginatedMeta {
  total: number;
  page: number;
  per_page: number;
}

export interface PaginatedResult<T> {
  items: T[];
  meta: PaginatedMeta;
}

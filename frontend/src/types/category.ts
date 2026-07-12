export type CategoryStatus = 'active' | 'disabled';

export interface Category {
  id: number;
  name: string;
  sort: number;
  status: CategoryStatus;
}

export interface CreateCategoryPayload {
  name: string;
  sort?: number;
  status?: CategoryStatus;
}

export interface UpdateCategoryPayload {
  name: string;
  sort: number;
  status: CategoryStatus;
}

export interface CategoryListResult {
  items: Category[];
}

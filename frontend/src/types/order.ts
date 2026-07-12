export type OrderStatus =
  | 'pending_payment'
  | 'paid'
  | 'preparing'
  | 'ready'
  | 'completed'
  | 'cancelled';

export type PaymentMethod = 'self' | 'proxy';

export interface OrderUser {
  id: number;
  name: string;
  phone: string;
  department?: string | null;
}

export interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_image: string | null;
  price: number;
  quantity: number;
  subtotal: number;
}

export interface Order {
  id: number;
  order_no: string;
  user: OrderUser;
  total_amount: number;
  status: OrderStatus;
  payment_method: PaymentMethod;
  paid_by_user: OrderUser | null;
  paid_at: string | null;
  remark: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
  created_at: string;
  items?: OrderItem[];
}

export interface OrderListParams {
  status?: OrderStatus;
  user_id?: number;
  date_from?: string;
  date_to?: string;
  keyword?: string;
  page?: number;
  per_page?: number;
}

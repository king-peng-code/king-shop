import type {PayChannel, PaymentOutcome} from '../types/order';

export type MainStackParamList = {
  Home: undefined;
  ProductDetail: {productId: number};
  Checkout: {productId: number; quantity: number};
  Payment: {orderId: number; channel: PayChannel};
  ProxyShare: {orderId: number};
  PaymentResult: {orderId: number; outcome: PaymentOutcome};
};

export type AuthStackParamList = {
  Login: undefined;
};

export type ChangePasswordStackParamList = {
  ChangePassword: undefined;
};

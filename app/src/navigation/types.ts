import type {NavigatorScreenParams} from '@react-navigation/native';
import type {PayChannel, PaymentOutcome} from '../types/order';

export type ShopStackParamList = {
  Home: undefined;
  ProductDetail: {productId: number};
  Checkout: {productId: number; quantity: number};
  Payment: {orderId: number; channel: PayChannel};
  ProxyShare: {orderId: number};
  PaymentResult: {orderId: number; outcome: PaymentOutcome};
};

export type OrdersStackParamList = {
  OrdersList: undefined;
  OrderDetail: {orderId: number};
};

export type ProfileStackParamList = {
  Profile: undefined;
  ChangePassword: undefined;
};

export type MainTabParamList = {
  ShopTab: NavigatorScreenParams<ShopStackParamList>;
  OrdersTab: NavigatorScreenParams<OrdersStackParamList>;
  ProfileTab: NavigatorScreenParams<ProfileStackParamList>;
};

export type AuthStackParamList = {
  Login: undefined;
};

export type ChangePasswordStackParamList = {
  ChangePassword: undefined;
};

/** @deprecated use ShopStackParamList */
export type MainStackParamList = ShopStackParamList;

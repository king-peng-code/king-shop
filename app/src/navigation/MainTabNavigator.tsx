import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import HomeScreen from '../screens/HomeScreen';
import ProductDetailScreen from '../screens/ProductDetailScreen';
import CheckoutScreen from '../screens/CheckoutScreen';
import PaymentScreen from '../screens/PaymentScreen';
import ProxyShareScreen from '../screens/ProxyShareScreen';
import PaymentResultScreen from '../screens/PaymentResultScreen';
import OrdersScreen from '../screens/OrdersScreen';
import OrderDetailScreen from '../screens/OrderDetailScreen';
import ProfileScreen from '../screens/ProfileScreen';
import ChangePasswordScreen from '../screens/ChangePasswordScreen';
import type {
  MainTabParamList,
  OrdersStackParamList,
  ProfileStackParamList,
  ShopStackParamList,
} from './types';

const Tab = createBottomTabNavigator<MainTabParamList>();
const ShopStack = createNativeStackNavigator<ShopStackParamList>();
const OrdersStack = createNativeStackNavigator<OrdersStackParamList>();
const ProfileStack = createNativeStackNavigator<ProfileStackParamList>();

function ShopNavigator() {
  return (
    <ShopStack.Navigator>
      <ShopStack.Screen
        name="Home"
        component={HomeScreen}
        options={{title: '商品'}}
      />
      <ShopStack.Screen
        name="ProductDetail"
        component={ProductDetailScreen}
        options={{title: '商品详情'}}
      />
      <ShopStack.Screen
        name="Checkout"
        component={CheckoutScreen}
        options={{title: '确认订单'}}
      />
      <ShopStack.Screen
        name="Payment"
        component={PaymentScreen}
        options={{title: '支付', headerBackVisible: false}}
      />
      <ShopStack.Screen
        name="ProxyShare"
        component={ProxyShareScreen}
        options={{title: '找人代付', headerBackVisible: false}}
      />
      <ShopStack.Screen
        name="PaymentResult"
        component={PaymentResultScreen}
        options={{title: '支付结果', headerBackVisible: false}}
      />
    </ShopStack.Navigator>
  );
}

function OrdersNavigator() {
  return (
    <OrdersStack.Navigator>
      <OrdersStack.Screen
        name="OrdersList"
        component={OrdersScreen}
        options={{title: '订单'}}
      />
      <OrdersStack.Screen
        name="OrderDetail"
        component={OrderDetailScreen}
        options={{title: '订单详情'}}
      />
    </OrdersStack.Navigator>
  );
}

function ProfileNavigator() {
  return (
    <ProfileStack.Navigator>
      <ProfileStack.Screen
        name="Profile"
        component={ProfileScreen}
        options={{title: '我的'}}
      />
      <ProfileStack.Screen
        name="ChangePassword"
        component={ChangePasswordScreen}
        options={{title: '修改密码'}}
      />
    </ProfileStack.Navigator>
  );
}

export default function MainTabNavigator() {
  return (
    <Tab.Navigator screenOptions={{headerShown: false}}>
      <Tab.Screen
        name="ShopTab"
        component={ShopNavigator}
        options={{title: '商品'}}
      />
      <Tab.Screen
        name="OrdersTab"
        component={OrdersNavigator}
        options={{title: '订单'}}
      />
      <Tab.Screen
        name="ProfileTab"
        component={ProfileNavigator}
        options={{title: '我的'}}
      />
    </Tab.Navigator>
  );
}

import React from 'react';
import {ActivityIndicator, View} from 'react-native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAuth} from '../context/AuthContext';
import LoginScreen from '../screens/LoginScreen';
import ChangePasswordScreen from '../screens/ChangePasswordScreen';
import HomeScreen from '../screens/HomeScreen';
import ProductDetailScreen from '../screens/ProductDetailScreen';
import CheckoutScreen from '../screens/CheckoutScreen';
import PaymentScreen from '../screens/PaymentScreen';
import ProxyShareScreen from '../screens/ProxyShareScreen';
import PaymentResultScreen from '../screens/PaymentResultScreen';
import type {
  AuthStackParamList,
  ChangePasswordStackParamList,
  MainStackParamList,
} from './types';

const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const ChangePasswordStack =
  createNativeStackNavigator<ChangePasswordStackParamList>();
const MainStack = createNativeStackNavigator<MainStackParamList>();

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{headerShown: false}}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
    </AuthStack.Navigator>
  );
}

function ChangePasswordNavigator() {
  return (
    <ChangePasswordStack.Navigator>
      <ChangePasswordStack.Screen
        name="ChangePassword"
        component={ChangePasswordScreen}
        options={{title: '修改密码'}}
      />
    </ChangePasswordStack.Navigator>
  );
}

function MainNavigator() {
  return (
    <MainStack.Navigator>
      <MainStack.Screen
        name="Home"
        component={HomeScreen}
        options={{title: '商品'}}
      />
      <MainStack.Screen
        name="ProductDetail"
        component={ProductDetailScreen}
        options={{title: '商品详情'}}
      />
      <MainStack.Screen
        name="Checkout"
        component={CheckoutScreen}
        options={{title: '确认订单'}}
      />
      <MainStack.Screen
        name="Payment"
        component={PaymentScreen}
        options={{title: '支付', headerBackVisible: false}}
      />
      <MainStack.Screen
        name="ProxyShare"
        component={ProxyShareScreen}
        options={{title: '找人代付', headerBackVisible: false}}
      />
      <MainStack.Screen
        name="PaymentResult"
        component={PaymentResultScreen}
        options={{title: '支付结果', headerBackVisible: false}}
      />
    </MainStack.Navigator>
  );
}

export default function RootNavigator() {
  const {isLoading, isAuthenticated, mustChangePassword} = useAuth();

  if (isLoading) {
    return (
      <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (!isAuthenticated) {
    return <AuthNavigator />;
  }

  if (mustChangePassword) {
    return <ChangePasswordNavigator />;
  }

  return <MainNavigator />;
}

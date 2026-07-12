import React from 'react';
import {ActivityIndicator, View} from 'react-native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAuth} from '../context/AuthContext';
import LoginScreen from '../screens/LoginScreen';
import ChangePasswordScreen from '../screens/ChangePasswordScreen';
import MainTabNavigator from './MainTabNavigator';
import type {
  AuthStackParamList,
  ChangePasswordStackParamList,
} from './types';

const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const ChangePasswordStack =
  createNativeStackNavigator<ChangePasswordStackParamList>();

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

  return <MainTabNavigator />;
}

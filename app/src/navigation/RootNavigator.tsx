import React from 'react';
import {ActivityIndicator, StyleSheet, View} from 'react-native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAuth} from '../context/AuthContext';
import LoginScreen from '../screens/LoginScreen';
import ChangePasswordScreen from '../screens/ChangePasswordScreen';
import MainTabNavigator from './MainTabNavigator';
import type {
  AuthStackParamList,
  ChangePasswordStackParamList,
} from './types';

type RootStackParamList = {
  Loading: undefined;
  Auth: undefined;
  ChangePassword: undefined;
  Main: undefined;
};

const RootStack = createNativeStackNavigator<RootStackParamList>();

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

function LoadingScreen() {
  return (
    <View style={styles.loading}>
      <ActivityIndicator size="large" />
    </View>
  );
}

export default function RootNavigator() {
  const {isLoading, isAuthenticated, mustChangePassword} = useAuth();

  return (
    <RootStack.Navigator screenOptions={{headerShown: false}}>
      {isLoading ? (
        <RootStack.Screen name="Loading" component={LoadingScreen} />
      ) : !isAuthenticated ? (
        <RootStack.Screen name="Auth" component={AuthNavigator} />
      ) : mustChangePassword ? (
        <RootStack.Screen name="ChangePassword" component={ChangePasswordNavigator} />
      ) : (
        <RootStack.Screen name="Main" component={MainTabNavigator} />
      )}
    </RootStack.Navigator>
  );
}

const styles = StyleSheet.create({
  loading: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});

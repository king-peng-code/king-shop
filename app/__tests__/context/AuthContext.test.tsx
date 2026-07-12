import React from 'react';
import renderer, {act} from 'react-test-renderer';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {AuthProvider, useAuth} from '../../src/context/AuthContext';
import {TOKEN_KEY} from '../../src/api/tokenStorage';
import type {User} from '../../src/types/api';

jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

global.fetch = jest.fn();

const mockUser: User = {
  id: 1,
  name: 'Test User',
  phone: '13800138000',
  employee_no: 'E001',
  department: 'Sales',
  role: 'employee',
  status: 'active',
  avatar: null,
  must_change_password: false,
};

function TestConsumer() {
  const {isLoading, isAuthenticated} = useAuth();
  return (
    <>
      {isLoading ? 'loading' : isAuthenticated ? 'authenticated' : 'guest'}
    </>
  );
}

beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  AsyncStorage.clear();
});

it('sends Authorization header when restoring session on cold start', async () => {
  await AsyncStorage.setItem(TOKEN_KEY, 'stored-token');

  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: mockUser}),
  });

  let tree: renderer.ReactTestRenderer;
  await act(async () => {
    tree = renderer.create(
      <AuthProvider>
        <TestConsumer />
      </AuthProvider>,
    );
  });

  await act(async () => {
    await Promise.resolve();
  });

  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/me'),
    expect.objectContaining({
      headers: expect.objectContaining({
        Authorization: 'Bearer stored-token',
      }),
    }),
  );

  expect(tree!.toJSON()).toBe('authenticated');
});

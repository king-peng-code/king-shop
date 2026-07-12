import AsyncStorage from '@react-native-async-storage/async-storage';
import {getToken, setToken, clearToken, TOKEN_KEY} from '../../src/api/tokenStorage';

jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

it('stores and retrieves token', async () => {
  await setToken('abc');
  expect(await getToken()).toBe('abc');
  expect(AsyncStorage.getItem).toHaveBeenCalledWith(TOKEN_KEY);
});

it('clears token', async () => {
  await setToken('abc');
  await clearToken();
  expect(await getToken()).toBeNull();
});

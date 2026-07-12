/**
 * @format
 */

import 'react-native';
import React from 'react';
import App from '../App';

// Note: import explicitly to use the types shipped with jest.
import {it} from '@jest/globals';

// Note: test renderer must be required after react-native.
import renderer from 'react-test-renderer';

jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

jest.mock('../src/navigation/RootNavigator', () => {
  const ReactMock = require('react');
  const {Text} = require('react-native');
  return function MockRootNavigator() {
    return ReactMock.createElement(Text, null, 'RootNavigator');
  };
});

global.fetch = jest.fn();

it('renders correctly', () => {
  renderer.create(<App />);
});

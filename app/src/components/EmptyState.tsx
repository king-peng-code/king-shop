import React from 'react';
import {StyleSheet, Text, View} from 'react-native';

interface EmptyStateProps {
  message: string;
}

export default function EmptyState({message}: EmptyStateProps) {
  return (
    <View style={styles.container}>
      <Text style={styles.message}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    paddingVertical: 48,
    paddingHorizontal: 24,
  },
  message: {
    fontSize: 15,
    color: '#999',
  },
});

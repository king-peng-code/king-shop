import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';

interface QuantityStepperProps {
  value: number;
  min?: number;
  max?: number;
  onChange: (value: number) => void;
}

export default function QuantityStepper({
  value,
  min = 1,
  max = 99,
  onChange,
}: QuantityStepperProps) {
  const decrease = () => {
    if (value > min) {
      onChange(value - 1);
    }
  };

  const increase = () => {
    if (value < max) {
      onChange(value + 1);
    }
  };

  return (
    <View style={styles.container}>
      <Pressable
        style={[styles.button, value <= min && styles.buttonDisabled]}
        onPress={decrease}
        disabled={value <= min}>
        <Text style={styles.buttonText}>−</Text>
      </Pressable>
      <Text style={styles.value}>{value}</Text>
      <Pressable
        style={[styles.button, value >= max && styles.buttonDisabled]}
        onPress={increase}
        disabled={value >= max}>
        <Text style={styles.buttonText}>+</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  button: {
    width: 36,
    height: 36,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ddd',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
  },
  buttonDisabled: {
    opacity: 0.4,
  },
  buttonText: {
    fontSize: 20,
    color: '#333',
    lineHeight: 22,
  },
  value: {
    minWidth: 40,
    textAlign: 'center',
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
});

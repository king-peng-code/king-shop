import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import type {PayChannel} from '../types/order';

export interface ChannelOption {
  value: PayChannel;
  label: string;
}

interface PaymentChannelPickerProps {
  options: ChannelOption[];
  value: PayChannel;
  onChange: (value: PayChannel) => void;
}

export default function PaymentChannelPicker({
  options,
  value,
  onChange,
}: PaymentChannelPickerProps) {
  return (
    <View style={styles.container}>
      {options.map(option => {
        const selected = option.value === value;
        return (
          <Pressable
            key={option.value}
            style={[styles.option, selected && styles.optionSelected]}
            onPress={() => onChange(option.value)}>
            <View style={[styles.radio, selected && styles.radioSelected]}>
              {selected ? <View style={styles.radioDot} /> : null}
            </View>
            <Text style={[styles.label, selected && styles.labelSelected]}>
              {option.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 8,
  },
  option: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#eee',
    backgroundColor: '#fff',
  },
  optionSelected: {
    borderColor: '#1976d2',
    backgroundColor: '#e3f2fd',
  },
  radio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: '#bbb',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  radioSelected: {
    borderColor: '#1976d2',
  },
  radioDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#1976d2',
  },
  label: {
    fontSize: 15,
    color: '#333',
  },
  labelSelected: {
    fontWeight: '600',
    color: '#1976d2',
  },
});

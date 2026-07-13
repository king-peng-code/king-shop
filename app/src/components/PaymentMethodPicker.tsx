import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import type {PaymentMethod} from '../types/order';

interface PaymentMethodPickerProps {
  value: PaymentMethod;
  onChange: (value: PaymentMethod) => void;
}

const OPTIONS: Array<{value: PaymentMethod; label: string; hint: string}> = [
  {value: 'self', label: '自己付', hint: '使用支付宝或微信完成支付'},
  {value: 'proxy', label: '找人代付', hint: '生成链接分享给同事帮忙付款'},
];

export default function PaymentMethodPicker({
  value,
  onChange,
}: PaymentMethodPickerProps) {
  return (
    <View style={styles.container}>
      {OPTIONS.map(option => {
        const selected = option.value === value;
        return (
          <Pressable
            key={option.value}
            style={[styles.option, selected && styles.optionSelected]}
            onPress={() => onChange(option.value)}>
            <View style={[styles.radio, selected && styles.radioSelected]}>
              {selected ? <View style={styles.radioDot} /> : null}
            </View>
            <View style={styles.textWrap}>
              <Text style={[styles.label, selected && styles.labelSelected]}>
                {option.label}
              </Text>
              <Text style={styles.hint}>{option.hint}</Text>
            </View>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    gap: 8,
  },
  option: {
    flex: 1,
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
  textWrap: {
    flex: 1,
    marginLeft: 8,
  },
  radio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: '#bbb',
    alignItems: 'center',
    justifyContent: 'center',
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
  hint: {
    marginTop: 2,
    fontSize: 11,
    color: '#888',
  },
});

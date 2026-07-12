import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import type {Order} from '../types/order';
import {formatPrice} from '../utils/formatPrice';
import {
  getOrderStatusColor,
  getOrderStatusLabel,
} from '../utils/orderStatus';

interface OrderListItemProps {
  order: Order;
  onPress: () => void;
}

function formatOrderTime(iso: string): string {
  const date = new Date(iso);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${month}-${day} ${hours}:${minutes}`;
}

export default function OrderListItem({order, onPress}: OrderListItemProps) {
  const productName =
    order.items && order.items.length > 0
      ? order.items[0].product_name
      : '订单商品';
  const statusColor = getOrderStatusColor(order.status);
  const statusLabel = getOrderStatusLabel(order.status, order.cancel_reason);

  return (
    <Pressable style={styles.container} onPress={onPress}>
      <View style={styles.header}>
        <Text style={styles.orderNo} numberOfLines={1}>
          {order.order_no}
        </Text>
        <View style={[styles.statusBadge, {backgroundColor: statusColor}]}>
          <Text style={styles.statusText}>{statusLabel}</Text>
        </View>
      </View>
      <Text style={styles.productName} numberOfLines={1}>
        {productName}
      </Text>
      <View style={styles.footer}>
        <Text style={styles.amount}>{formatPrice(order.total_amount)}</Text>
        <Text style={styles.time}>{formatOrderTime(order.created_at)}</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: {
    marginHorizontal: 12,
    marginVertical: 6,
    padding: 12,
    borderRadius: 8,
    backgroundColor: '#fff',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: '#e8e8e8',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  orderNo: {
    flex: 1,
    fontSize: 14,
    color: '#666',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 12,
    color: '#fff',
    fontWeight: '600',
  },
  productName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginTop: 8,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 8,
  },
  amount: {
    fontSize: 15,
    fontWeight: '600',
    color: '#e53935',
  },
  time: {
    fontSize: 13,
    color: '#999',
  },
});

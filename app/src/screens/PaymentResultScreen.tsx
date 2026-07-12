import React, {useEffect, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation, useRoute} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {RouteProp} from '@react-navigation/native';
import {getOrder} from '../api/orders';
import LoadingView from '../components/LoadingView';
import type {ShopStackParamList} from '../navigation/types';
import type {Order, PaymentOutcome} from '../types/order';
import {formatPrice} from '../utils/formatPrice';

type PaymentResultRouteProp = RouteProp<ShopStackParamList, 'PaymentResult'>;
type PaymentResultNavProp = NativeStackNavigationProp<
  ShopStackParamList,
  'PaymentResult'
>;

const OUTCOME_COPY: Record<
  PaymentOutcome,
  {emoji: string; title: string; subtitle: string}
> = {
  success: {
    emoji: '✅',
    title: '支付成功',
    subtitle: '订单已支付，请等待取餐通知',
  },
  failed: {
    emoji: '❌',
    title: '支付未完成',
    subtitle: '支付已取消或失败，可返回重新下单',
  },
  pending: {
    emoji: '⏳',
    title: '支付处理中',
    subtitle: '请稍后在订单中心查看支付结果',
  },
};

export default function PaymentResultScreen() {
  const route = useRoute<PaymentResultRouteProp>();
  const navigation = useNavigation<PaymentResultNavProp>();
  const {orderId, outcome} = route.params;

  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      try {
        const data = await getOrder(orderId);
        if (!cancelled) {
          setOrder(data);
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    };

    void load();
  }, [orderId]);

  const copy = OUTCOME_COPY[outcome];

  if (isLoading) {
    return <LoadingView />;
  }

  return (
    <View style={styles.container}>
      <Text style={styles.emoji}>{copy.emoji}</Text>
      <Text style={styles.title}>{copy.title}</Text>
      <Text style={styles.subtitle}>{copy.subtitle}</Text>
      {order ? (
        <View style={styles.card}>
          <Text style={styles.orderNo}>订单号 {order.order_no}</Text>
          <Text style={styles.amount}>{formatPrice(order.total_amount)}</Text>
        </View>
      ) : null}
      <View style={styles.actions}>
        {outcome === 'success' || outcome === 'pending' ? (
          <Pressable
            style={styles.primaryButton}
            onPress={() =>
              navigation.getParent()?.navigate('OrdersTab', {
                screen: 'OrderDetail',
                params: {orderId},
              })
            }>
            <Text style={styles.primaryButtonText}>查看订单</Text>
          </Pressable>
        ) : null}
        <Pressable
          style={styles.secondaryButton}
          onPress={() => navigation.popToTop()}>
          <Text style={styles.secondaryButtonText}>返回首页</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  emoji: {
    fontSize: 48,
  },
  title: {
    marginTop: 16,
    fontSize: 22,
    fontWeight: '700',
    color: '#333',
  },
  subtitle: {
    marginTop: 8,
    fontSize: 14,
    color: '#888',
    textAlign: 'center',
    lineHeight: 20,
  },
  card: {
    marginTop: 24,
    padding: 16,
    borderRadius: 12,
    backgroundColor: '#f5f5f5',
    width: '100%',
    alignItems: 'center',
  },
  orderNo: {
    fontSize: 14,
    color: '#666',
  },
  amount: {
    marginTop: 8,
    fontSize: 20,
    fontWeight: '600',
    color: '#e53935',
  },
  actions: {
    marginTop: 32,
    width: '100%',
    gap: 10,
  },
  primaryButton: {
    backgroundColor: '#1976d2',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  secondaryButton: {
    backgroundColor: '#fff',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ddd',
  },
  primaryButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  secondaryButtonText: {
    color: '#666',
    fontSize: 16,
    fontWeight: '600',
  },
});

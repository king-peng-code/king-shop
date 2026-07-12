import React, {useCallback, useEffect, useRef, useState} from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {WebView} from 'react-native-webview';
import {useNavigation, useRoute} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {RouteProp} from '@react-navigation/native';
import {getOrder, payOrder} from '../api/orders';
import {ApiError} from '../api/client';
import LoadingView from '../components/LoadingView';
import {useAuth} from '../context/AuthContext';
import type {MainStackParamList} from '../navigation/types';
import {
  isAlipayParams,
  isFakeParams,
  isWechatParams,
  launchFakePay,
  launchWechatPay,
} from '../services/paymentLauncher';
import type {Order, PayChannel, PaymentOutcome} from '../types/order';
import {formatPrice} from '../utils/formatPrice';
import {pollOrderStatus} from '../utils/pollOrderStatus';

type PaymentRouteProp = RouteProp<MainStackParamList, 'Payment'>;
type PaymentNavProp = NativeStackNavigationProp<MainStackParamList, 'Payment'>;

const CHANNEL_LABELS: Record<PayChannel, string> = {
  fake: '模拟支付',
  alipay_sandbox: '支付宝',
  wechat: '微信支付',
};

export default function PaymentScreen() {
  const route = useRoute<PaymentRouteProp>();
  const navigation = useNavigation<PaymentNavProp>();
  const {orderId, channel} = route.params;
  const {refreshUser} = useAuth();

  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isPaying, setIsPaying] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [alipayUrl, setAlipayUrl] = useState<string | null>(null);
  const [showFakeConfirm, setShowFakeConfirm] = useState(false);
  const startedRef = useRef(false);

  const goToResult = useCallback(
    (outcome: PaymentOutcome) => {
      navigation.replace('PaymentResult', {orderId, outcome});
    },
    [navigation, orderId],
  );

  const handleApiError = useCallback(
    async (e: unknown) => {
      if (e instanceof ApiError && e.code === 40301) {
        await refreshUser();
        return;
      }
      if (e instanceof ApiError) {
        setError(e.message);
        return;
      }
      if (e instanceof Error) {
        setError(e.message);
        return;
      }
      setError('支付失败，请稍后重试');
    },
    [refreshUser],
  );

  const confirmPaid = useCallback(async () => {
    const result = await pollOrderStatus(orderId);
    goToResult(result === 'paid' ? 'success' : 'pending');
  }, [goToResult, orderId]);

  const runPayment = useCallback(async () => {
    setIsPaying(true);
    setError(null);

    try {
      const payResult = await payOrder(orderId, channel);
      const {pay_params: payParams} = payResult;

      if (isFakeParams(payParams)) {
        setShowFakeConfirm(true);
        return;
      }

      if (isAlipayParams(payParams)) {
        setAlipayUrl(payParams.pay_url);
        return;
      }

      if (isWechatParams(payParams)) {
        const wechatResult = await launchWechatPay(payParams.prepay);
        if (wechatResult === 'cancelled') {
          goToResult('failed');
          return;
        }
        if (wechatResult === 'failed') {
          setError('微信支付失败，请重试');
          return;
        }
        await confirmPaid();
      }
    } catch (e) {
      await handleApiError(e);
    } finally {
      setIsPaying(false);
    }
  }, [channel, confirmPaid, goToResult, handleApiError, orderId]);

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      try {
        const data = await getOrder(orderId);
        if (!cancelled) {
          setOrder(data);
        }
      } catch (e) {
        if (!cancelled) {
          await handleApiError(e);
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    };

    void load();
  }, [orderId, handleApiError]);

  useEffect(() => {
    if (isLoading || startedRef.current) {
      return;
    }
    startedRef.current = true;
    void runPayment();
  }, [isLoading, runPayment]);

  const handleFakeConfirm = async () => {
    setShowFakeConfirm(false);
    setIsPaying(true);
    setError(null);

    try {
      const payResult = await payOrder(orderId, 'fake');
      if (isFakeParams(payResult.pay_params)) {
        await launchFakePay(payResult.pay_params.out_trade_no);
        await confirmPaid();
      }
    } catch (e) {
      await handleApiError(e);
    } finally {
      setIsPaying(false);
    }
  };

  const handleAlipayClose = async () => {
    setAlipayUrl(null);
    setIsPaying(true);
    try {
      await confirmPaid();
    } catch (e) {
      await handleApiError(e);
    } finally {
      setIsPaying(false);
    }
  };

  if (isLoading) {
    return <LoadingView />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <Text style={styles.title}>正在支付</Text>
        {order ? (
          <>
            <Text style={styles.orderNo}>订单号 {order.order_no}</Text>
            <Text style={styles.amount}>{formatPrice(order.total_amount)}</Text>
          </>
        ) : null}
        <Text style={styles.channel}>{CHANNEL_LABELS[channel]}</Text>
        {isPaying ? <ActivityIndicator style={styles.spinner} size="large" /> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}
        {error ? (
          <Pressable style={styles.retryButton} onPress={() => void runPayment()}>
            <Text style={styles.retryText}>重试支付</Text>
          </Pressable>
        ) : null}
      </View>

      <Modal visible={showFakeConfirm} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>模拟支付</Text>
            <Text style={styles.modalBody}>开发环境：确认模拟支付成功？</Text>
            <View style={styles.modalActions}>
              <Pressable
                style={styles.modalCancel}
                onPress={() => {
                  setShowFakeConfirm(false);
                  goToResult('failed');
                }}>
                <Text style={styles.modalCancelText}>取消</Text>
              </Pressable>
              <Pressable
                style={styles.modalConfirm}
                onPress={() => void handleFakeConfirm()}>
                <Text style={styles.modalConfirmText}>确认支付</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={alipayUrl !== null} animationType="slide">
        <View style={styles.webviewContainer}>
          <View style={styles.webviewHeader}>
            <Pressable onPress={() => void handleAlipayClose()}>
              <Text style={styles.webviewClose}>关闭并确认</Text>
            </Pressable>
          </View>
          {alipayUrl ? (
            <WebView
              source={{uri: alipayUrl}}
              startInLoadingState
              renderLoading={() => (
                <ActivityIndicator style={styles.webviewLoading} size="large" />
              )}
            />
          ) : null}
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    justifyContent: 'center',
    padding: 24,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 24,
    alignItems: 'center',
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333',
  },
  orderNo: {
    marginTop: 12,
    fontSize: 14,
    color: '#666',
  },
  amount: {
    marginTop: 8,
    fontSize: 28,
    fontWeight: '700',
    color: '#e53935',
  },
  channel: {
    marginTop: 12,
    fontSize: 14,
    color: '#888',
  },
  spinner: {
    marginTop: 24,
  },
  error: {
    marginTop: 16,
    fontSize: 14,
    color: '#e53935',
    textAlign: 'center',
  },
  retryButton: {
    marginTop: 16,
    paddingVertical: 10,
    paddingHorizontal: 20,
    backgroundColor: '#1976d2',
    borderRadius: 8,
  },
  retryText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '600',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    padding: 24,
  },
  modalCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
  },
  modalTitle: {
    fontSize: 17,
    fontWeight: '600',
    color: '#333',
  },
  modalBody: {
    marginTop: 8,
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginTop: 20,
    gap: 12,
  },
  modalCancel: {
    paddingVertical: 8,
    paddingHorizontal: 12,
  },
  modalCancelText: {
    color: '#666',
    fontSize: 15,
  },
  modalConfirm: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    backgroundColor: '#1976d2',
    borderRadius: 6,
  },
  modalConfirmText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '600',
  },
  webviewContainer: {
    flex: 1,
    backgroundColor: '#fff',
  },
  webviewHeader: {
    paddingTop: 48,
    paddingHorizontal: 16,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  webviewClose: {
    fontSize: 16,
    color: '#1976d2',
    fontWeight: '600',
  },
  webviewLoading: {
    flex: 1,
    justifyContent: 'center',
  },
});

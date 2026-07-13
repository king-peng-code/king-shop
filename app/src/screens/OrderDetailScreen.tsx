import React, {useCallback, useEffect, useRef, useState} from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {
  useFocusEffect,
  useNavigation,
  useRoute,
} from '@react-navigation/native';
import type {CompositeNavigationProp} from '@react-navigation/native';
import type {BottomTabNavigationProp} from '@react-navigation/bottom-tabs';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {RouteProp} from '@react-navigation/native';
import {cancelOrder, getOrder} from '../api/orders';
import {ApiError} from '../api/client';
import LoadingView from '../components/LoadingView';
import PaymentChannelPicker, {type ChannelOption} from '../components/PaymentChannelPicker';
import {useAuth} from '../context/AuthContext';
import type {MainTabParamList, OrdersStackParamList} from '../navigation/types';
import type {Order, PayChannel} from '../types/order';
import {formatPrice} from '../utils/formatPrice';
import {
  getOrderStatusColor,
  getOrderStatusLabel,
} from '../utils/orderStatus';
import {selfPayChannels} from '../utils/payChannels';

type OrderDetailRouteProp = RouteProp<OrdersStackParamList, 'OrderDetail'>;
type OrderDetailNavProp = CompositeNavigationProp<
  NativeStackNavigationProp<OrdersStackParamList, 'OrderDetail'>,
  BottomTabNavigationProp<MainTabParamList>
>;

const PAYMENT_METHOD_LABELS = {
  self: '自己付',
  proxy: '找人代付',
} as const;

function formatOrderTime(iso: string): string {
  const date = new Date(iso);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${minutes}`;
}

export default function OrderDetailScreen() {
  const route = useRoute<OrderDetailRouteProp>();
  const navigation = useNavigation<OrderDetailNavProp>();
  const {orderId} = route.params;
  const {refreshUser} = useAuth();

  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isActionLoading, setIsActionLoading] = useState(false);
  const [showPayModal, setShowPayModal] = useState(false);
  const [channel, setChannel] = useState<PayChannel | null>(null);
  const [channelOptions, setChannelOptions] = useState<ChannelOption[]>([]);

  const hasFocusedRef = useRef(false);

  // Load available payment channels on mount
  useEffect(() => {
    void (async () => {
      const channels = await selfPayChannels();
      setChannelOptions(channels);
      if (channels.length > 0) {
        setChannel(channels[0].value);
      }
    })();
  }, []);

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
      setError('加载失败，请稍后重试');
    },
    [refreshUser],
  );

  const loadOrder = useCallback(async () => {
    try {
      setError(null);
      const data = await getOrder(orderId);
      setOrder(data);
    } catch (e) {
      await handleApiError(e);
    } finally {
      setIsLoading(false);
    }
  }, [orderId, handleApiError]);

  useEffect(() => {
    setIsLoading(true);
    setOrder(null);
    void loadOrder();
  }, [loadOrder]);

  useFocusEffect(
    useCallback(() => {
      if (!hasFocusedRef.current) {
        hasFocusedRef.current = true;
        return;
      }
      void loadOrder();
    }, [loadOrder]),
  );

  const handleCancel = () => {
    Alert.alert('确认取消', '确定要取消此订单吗？', [
      {text: '再想想', style: 'cancel'},
      {
        text: '确认取消',
        style: 'destructive',
        onPress: () => {
          void (async () => {
            setIsActionLoading(true);
            try {
              const updated = await cancelOrder(orderId);
              setOrder(updated);
            } catch (e) {
              await handleApiError(e);
            } finally {
              setIsActionLoading(false);
            }
          })();
        },
      },
    ]);
  };

  const handlePayConfirm = () => {
    if (!channel) {
      return;
    }
    setShowPayModal(false);
    navigation.getParent()?.navigate('ShopTab', {
      screen: 'Payment',
      params: {orderId, channel},
    });
  };

  const statusColor = order ? getOrderStatusColor(order.status) : '#999';
  const statusLabel = order
    ? getOrderStatusLabel(order.status, order.cancel_reason)
    : '';
  const showPendingActions = order?.status === 'pending_payment';
  const showSelfPayButton =
    showPendingActions && order?.payment_method === 'self';

  return (
    <View style={styles.container}>
      {isLoading ? (
        <LoadingView />
      ) : !order && error ? (
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : !order ? (
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>订单不存在</Text>
        </View>
      ) : (
        <>
          <ScrollView contentContainerStyle={styles.content}>
            <View style={styles.headerCard}>
              <Text style={styles.orderNo}>{order.order_no}</Text>
              <View style={[styles.statusBadge, {backgroundColor: statusColor}]}>
                <Text style={styles.statusText}>{statusLabel}</Text>
              </View>
            </View>

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>商品明细</Text>
              {(order.items ?? []).map(item => (
                <View key={item.id} style={styles.itemRow}>
                  {item.image_url ?? item.product_image ? (
                    <Image
                      source={{uri: item.image_url ?? item.product_image ?? undefined}}
                      style={styles.itemImage}
                    />
                  ) : (
                    <View style={[styles.itemImage, styles.placeholder]} />
                  )}
                  <View style={styles.itemInfo}>
                    <Text style={styles.itemName}>{item.product_name}</Text>
                    <Text style={styles.itemMeta}>
                      {formatPrice(item.price)} × {item.quantity}
                    </Text>
                  </View>
                  <Text style={styles.itemSubtotal}>
                    {formatPrice(item.subtotal)}
                  </Text>
                </View>
              ))}
              <View style={styles.totalRow}>
                <Text style={styles.totalLabel}>合计</Text>
                <Text style={styles.totalAmount}>
                  {formatPrice(order.total_amount)}
                </Text>
              </View>
            </View>

            {order.remark ? (
              <View style={styles.section}>
                <Text style={styles.sectionTitle}>备注</Text>
                <Text style={styles.remarkText}>{order.remark}</Text>
              </View>
            ) : null}

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>订单信息</Text>
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>支付方式</Text>
                <Text style={styles.infoValue}>
                  {PAYMENT_METHOD_LABELS[order.payment_method]}
                </Text>
              </View>
              {order.paid_by_payer ? (
                <View style={styles.infoRow}>
                  <Text style={styles.infoLabel}>代付人</Text>
                  <Text style={styles.infoValue}>{order.paid_by_payer.name}</Text>
                </View>
              ) : null}
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>下单时间</Text>
                <Text style={styles.infoValue}>
                  {formatOrderTime(order.created_at)}
                </Text>
              </View>
              {order.paid_at ? (
                <View style={styles.infoRow}>
                  <Text style={styles.infoLabel}>支付时间</Text>
                  <Text style={styles.infoValue}>
                    {formatOrderTime(order.paid_at)}
                  </Text>
                </View>
              ) : null}
            </View>

            {error ? <Text style={styles.inlineError}>{error}</Text> : null}
          </ScrollView>

          {showPendingActions ? (
            <View style={styles.footer}>
              {showSelfPayButton ? (
                <Pressable
                  style={[styles.primaryButton, isActionLoading && styles.disabled]}
                  onPress={() => setShowPayModal(true)}
                  disabled={isActionLoading}>
                  {isActionLoading ? (
                    <ActivityIndicator color="#fff" />
                  ) : (
                    <Text style={styles.primaryButtonText}>去支付</Text>
                  )}
                </Pressable>
              ) : (
                <Pressable
                  style={styles.primaryButton}
                  onPress={() =>
                    navigation.getParent()?.navigate('ShopTab', {
                      screen: 'ProxyShare',
                      params: {orderId: order.id},
                    })
                  }>
                  <Text style={styles.primaryButtonText}>分享代付链接</Text>
                </Pressable>
              )}
              <Pressable
                style={[styles.secondaryButton, isActionLoading && styles.disabled]}
                onPress={handleCancel}
                disabled={isActionLoading}>
                <Text style={styles.secondaryButtonText}>取消订单</Text>
              </Pressable>
            </View>
          ) : null}

          <Modal visible={showPayModal} transparent animationType="slide">
            <View style={styles.modalBackdrop}>
              <View style={styles.modalCard}>
                <Text style={styles.modalTitle}>选择支付渠道</Text>
                {channelOptions.length === 0 ? (
                  <Text style={styles.noChannelText}>暂无可用的支付方式</Text>
                ) : (
                  <PaymentChannelPicker
                options={channelOptions}
                value={channel ?? channelOptions[0]?.value}
                onChange={setChannel}
              />
                )}
                <View style={styles.modalActions}>
                  <Pressable
                    style={styles.modalCancel}
                    onPress={() => setShowPayModal(false)}>
                    <Text style={styles.modalCancelText}>取消</Text>
                  </Pressable>
                  <Pressable
                    style={[
                      styles.modalConfirm,
                      (!channel || channelOptions.length === 0) && styles.disabled,
                    ]}
                    disabled={!channel || channelOptions.length === 0}
                    onPress={handlePayConfirm}>
                    <Text style={styles.modalConfirmText}>确认支付</Text>
                  </Pressable>
                </View>
              </View>
            </View>
          </Modal>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  content: {
    padding: 16,
    paddingBottom: 24,
  },
  headerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
  },
  orderNo: {
    flex: 1,
    fontSize: 15,
    color: '#666',
    marginRight: 8,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 13,
    color: '#fff',
    fontWeight: '600',
  },
  section: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: '#333',
    marginBottom: 12,
  },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  itemImage: {
    width: 56,
    height: 56,
    borderRadius: 8,
  },
  placeholder: {
    backgroundColor: '#e0e0e0',
  },
  itemInfo: {
    flex: 1,
    marginLeft: 12,
  },
  itemName: {
    fontSize: 15,
    fontWeight: '500',
    color: '#333',
  },
  itemMeta: {
    fontSize: 13,
    color: '#888',
    marginTop: 4,
  },
  itemSubtotal: {
    fontSize: 15,
    fontWeight: '600',
    color: '#333',
    marginLeft: 8,
  },
  totalRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#eee',
    paddingTop: 12,
    marginTop: 4,
  },
  totalLabel: {
    fontSize: 15,
    fontWeight: '600',
    color: '#333',
  },
  totalAmount: {
    fontSize: 18,
    fontWeight: '700',
    color: '#e53935',
  },
  remarkText: {
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  infoLabel: {
    fontSize: 14,
    color: '#888',
  },
  infoValue: {
    fontSize: 14,
    color: '#333',
  },
  inlineError: {
    color: '#e53935',
    fontSize: 14,
    textAlign: 'center',
    marginTop: 8,
  },
  footer: {
    padding: 16,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#eee',
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
  disabled: {
    opacity: 0.7,
  },
  errorContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
  },
  errorText: {
    fontSize: 15,
    color: '#999',
    textAlign: 'center',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'flex-end',
  },
  modalCard: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: 20,
    paddingBottom: 32,
  },
  modalTitle: {
    fontSize: 17,
    fontWeight: '600',
    color: '#333',
    marginBottom: 16,
  },
  noChannelText: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
    paddingVertical: 16,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 20,
  },
  modalCancel: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ddd',
    alignItems: 'center',
  },
  modalCancelText: {
    fontSize: 16,
    color: '#666',
  },
  modalConfirm: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 8,
    backgroundColor: '#1976d2',
    alignItems: 'center',
  },
  modalConfirmText: {
    fontSize: 16,
    color: '#fff',
    fontWeight: '600',
  },
});

import React, {useCallback, useEffect, useState} from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import {useNavigation, useRoute} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {RouteProp} from '@react-navigation/native';
import {fetchProduct} from '../api/catalog';
import {createOrder} from '../api/orders';
import {ApiError} from '../api/client';
import PaymentChannelPicker from '../components/PaymentChannelPicker';
import type {ChannelOption} from '../components/PaymentChannelPicker';
import PaymentMethodPicker from '../components/PaymentMethodPicker';
import LoadingView from '../components/LoadingView';
import {useAuth} from '../context/AuthContext';
import type {ShopStackParamList} from '../navigation/types';
import type {PayChannel, PaymentMethod} from '../types/order';
import type {Product} from '../types/api';
import {formatPrice} from '../utils/formatPrice';
import {selfPayChannels} from '../utils/payChannels';

type CheckoutRouteProp = RouteProp<ShopStackParamList, 'Checkout'>;
type CheckoutNavProp = NativeStackNavigationProp<ShopStackParamList, 'Checkout'>;

export default function CheckoutScreen() {
  const route = useRoute<CheckoutRouteProp>();
  const navigation = useNavigation<CheckoutNavProp>();
  const {productId, quantity} = route.params;
  const {refreshUser} = useAuth();

  const [product, setProduct] = useState<Product | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [remark, setRemark] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('self');
  const [channel, setChannel] = useState<PayChannel | null>(null);
  const [channelOptions, setChannelOptions] = useState<ChannelOption[]>([]);

  useEffect(() => {
    void (async () => {
      try {
        const channels = await selfPayChannels();
        setChannelOptions(channels);
        if (channels.length > 0) {
          setChannel(channels[0].value);
        }
      } catch {
        // 保持空列表，UI 显示「暂无可用的支付方式」
      }
    })();
  }, []);
  const totalAmount = product ? product.price * quantity : 0;

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

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const data = await fetchProduct(productId);
        if (!cancelled) {
          setProduct(data);
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

    return () => {
      cancelled = true;
    };
  }, [productId, handleApiError]);

  const handleSubmit = async () => {
    if (!product || isSubmitting) {
      return;
    }

    if (paymentMethod === 'self' && !channel) {
      setError('支付渠道加载中，请稍候');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      const order = await createOrder({
        items: [{product_id: product.id, quantity}],
        payment_method: paymentMethod,
        remark: remark.trim() || undefined,
      });

      if (paymentMethod === 'proxy') {
        navigation.replace('ProxyShare', {orderId: order.id});
      } else {
        navigation.replace('Payment', {orderId: order.id, channel});
      }
    } catch (e) {
      await handleApiError(e);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading) {
    return <LoadingView />;
  }

  if (error && !product) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  if (!product) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorText}>商品不存在或已下架</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.card}>
          {product.image_url ? (
            <Image source={{uri: product.image_url}} style={styles.image} />
          ) : (
            <View style={[styles.image, styles.placeholder]} />
          )}
          <View style={styles.productInfo}>
            <Text style={styles.name}>{product.name}</Text>
            <Text style={styles.meta}>
              {formatPrice(product.price)} × {quantity}
            </Text>
            <Text style={styles.total}>合计 {formatPrice(totalAmount)}</Text>
          </View>
        </View>

        <Text style={styles.sectionTitle}>备注（可选）</Text>
        <TextInput
          style={styles.remarkInput}
          value={remark}
          onChangeText={setRemark}
          placeholder="如：少糖、打包"
          multiline
          maxLength={500}
        />

        <Text style={styles.sectionTitle}>付款方式</Text>
        <PaymentMethodPicker value={paymentMethod} onChange={setPaymentMethod} />

        {paymentMethod === 'self' ? (
          <>
            <Text style={[styles.sectionTitle, styles.sectionGap]}>
              支付渠道
            </Text>
            {channelOptions.length === 0 ? (
              <Text style={styles.noChannelText}>暂无可用的支付方式</Text>
            ) : (
              <PaymentChannelPicker
                options={channelOptions}
                value={channel ?? channelOptions[0]?.value}
                onChange={setChannel}
              />
            )}
          </>
        ) : null}

        {error ? <Text style={styles.inlineError}>{error}</Text> : null}
      </ScrollView>

      <View style={styles.footer}>
        <Pressable
          style={[styles.submitButton, isSubmitting && styles.submitDisabled]}
          onPress={() => void handleSubmit()}
          disabled={isSubmitting}>
          {isSubmitting ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.submitText}>
              提交订单 · {formatPrice(totalAmount)}
            </Text>
          )}
        </Pressable>
      </View>
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
  card: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 12,
    marginBottom: 16,
  },
  image: {
    width: 72,
    height: 72,
    borderRadius: 8,
  },
  placeholder: {
    backgroundColor: '#e0e0e0',
  },
  productInfo: {
    flex: 1,
    marginLeft: 12,
    justifyContent: 'center',
  },
  name: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  meta: {
    fontSize: 14,
    color: '#666',
    marginTop: 6,
  },
  total: {
    fontSize: 16,
    fontWeight: '600',
    color: '#e53935',
    marginTop: 8,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  sectionGap: {
    marginTop: 16,
  },
  remarkInput: {
    backgroundColor: '#fff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#eee',
    padding: 12,
    minHeight: 72,
    fontSize: 15,
    textAlignVertical: 'top',
    marginBottom: 16,
  },
  inlineError: {
    marginTop: 12,
    color: '#e53935',
    fontSize: 14,
  },
  footer: {
    padding: 16,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#eee',
  },
  submitButton: {
    backgroundColor: '#1976d2',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  submitDisabled: {
    opacity: 0.7,
  },
  noChannelText: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
    paddingVertical: 16,
  },
  submitText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
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
});

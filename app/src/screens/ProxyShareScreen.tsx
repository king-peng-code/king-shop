import React, {useCallback, useEffect, useState} from 'react';
import {
  Pressable,
  Share,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import Clipboard from '@react-native-clipboard/clipboard';
import {useNavigation, useRoute} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {RouteProp} from '@react-navigation/native';
import {generateProxyPayLink, getOrder} from '../api/orders';
import {ApiError} from '../api/client';
import LoadingView from '../components/LoadingView';
import {useAuth} from '../context/AuthContext';
import type {ShopStackParamList} from '../navigation/types';
import type {Order} from '../types/order';
import {formatPrice} from '../utils/formatPrice';

type ProxyShareRouteProp = RouteProp<ShopStackParamList, 'ProxyShare'>;
type ProxyShareNavProp = NativeStackNavigationProp<
  ShopStackParamList,
  'ProxyShare'
>;

function formatExpiresAt(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleString('zh-CN', {hour12: false});
}

export default function ProxyShareScreen() {
  const route = useRoute<ProxyShareRouteProp>();
  const navigation = useNavigation<ProxyShareNavProp>();
  const {orderId} = route.params;
  const {refreshUser} = useAuth();

  const [order, setOrder] = useState<Order | null>(null);
  const [shareUrl, setShareUrl] = useState<string | null>(null);
  const [shareTitle, setShareTitle] = useState('帮我付一下');
  const [shareMessage, setShareMessage] = useState<string | null>(null);
  const [shareCopyText, setShareCopyText] = useState<string | null>(null);
  const [expiresAt, setExpiresAt] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [copyHint, setCopyHint] = useState<string | null>(null);

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
        const [orderData, linkData] = await Promise.all([
          getOrder(orderId),
          generateProxyPayLink(orderId),
        ]);
        if (!cancelled) {
          setOrder(orderData);
          setShareUrl(linkData.url);
          setShareTitle(linkData.share_title);
          setShareMessage(linkData.share_message);
          setShareCopyText(linkData.share_copy_text);
          setExpiresAt(linkData.expires_at);
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

  const handleShare = async () => {
    if (!shareUrl || !order) {
      return;
    }

    try {
      const title = shareTitle;
      const message = shareMessage ?? shareUrl;

      await Share.share({
        title,
        message,
        url: shareUrl,
      });
    } catch {
      setError('分享失败，请复制链接手动发送');
    }
  };

  const handleCopy = () => {
    if (!shareUrl) {
      return;
    }
    const copyText = shareCopyText ?? shareUrl;
    Clipboard.setString(copyText);
    setCopyHint('链接已复制');
    setTimeout(() => setCopyHint(null), 2000);
  };

  if (isLoading) {
    return <LoadingView />;
  }

  if (error && !shareUrl) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <Text style={styles.title}>找人代付</Text>
        {order ? (
          <>
            <Text style={styles.orderNo}>订单号 {order.order_no}</Text>
            <Text style={styles.amount}>{formatPrice(order.total_amount)}</Text>
          </>
        ) : null}
        {expiresAt ? (
          <Text style={styles.expires}>链接有效期至 {formatExpiresAt(expiresAt)}</Text>
        ) : null}
        {error ? <Text style={styles.inlineError}>{error}</Text> : null}
        {copyHint ? <Text style={styles.copyHint}>{copyHint}</Text> : null}
      </View>

      <Pressable style={styles.primaryButton} onPress={() => void handleShare()}>
        <Text style={styles.primaryText}>分享给同事</Text>
      </Pressable>
      <Pressable style={styles.secondaryButton} onPress={handleCopy}>
        <Text style={styles.secondaryText}>复制链接</Text>
      </Pressable>
      <Pressable
        style={styles.secondaryButton}
        onPress={() => navigation.popToTop()}>
        <Text style={styles.secondaryText}>返回首页</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    padding: 24,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    marginBottom: 24,
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
    fontSize: 24,
    fontWeight: '700',
    color: '#e53935',
  },
  expires: {
    marginTop: 12,
    fontSize: 13,
    color: '#888',
  },
  url: {
    marginTop: 16,
    fontSize: 13,
    color: '#1976d2',
    lineHeight: 20,
  },
  inlineError: {
    marginTop: 12,
    color: '#e53935',
    fontSize: 14,
  },
  copyHint: {
    marginTop: 8,
    color: '#2e7d32',
    fontSize: 13,
  },
  primaryButton: {
    backgroundColor: '#1976d2',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginBottom: 12,
  },
  primaryText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  secondaryButton: {
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#ddd',
    backgroundColor: '#fff',
  },
  secondaryText: {
    color: '#333',
    fontSize: 15,
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

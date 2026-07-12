import React, {useCallback, useEffect, useRef, useState} from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {useFocusEffect, useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {listOrders, type PaginatedOrders} from '../api/orders';
import {ApiError} from '../api/client';
import EmptyState from '../components/EmptyState';
import LoadingView from '../components/LoadingView';
import OrderListItem from '../components/OrderListItem';
import OrderStatusTabs, {
  type OrderStatusTabKey,
} from '../components/OrderStatusTabs';
import {useAuth} from '../context/AuthContext';
import type {OrdersStackParamList} from '../navigation/types';
import type {Order} from '../types/order';

type TabKey = OrderStatusTabKey;

async function fetchOrdersForTab(
  tab: TabKey,
  page: number,
): Promise<PaginatedOrders> {
  if (tab === 'in_progress') {
    const [paid, preparing] = await Promise.all([
      listOrders({status: 'paid', page, per_page: 20}),
      listOrders({status: 'preparing', page, per_page: 20}),
    ]);
    const items = [...paid.items, ...preparing.items].sort(
      (a, b) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    );
    return {
      items,
      meta: {
        total: paid.meta.total + preparing.meta.total,
        page,
        per_page: 20,
      },
    };
  }
  const status = tab === 'all' ? undefined : tab;
  return listOrders({status, page, per_page: 20});
}

export default function OrdersScreen() {
  const navigation =
    useNavigation<NativeStackNavigationProp<OrdersStackParamList>>();
  const {refreshUser} = useAuth();

  const [selectedTab, setSelectedTab] = useState<TabKey>('all');
  const [orders, setOrders] = useState<Order[]>([]);
  const [total, setTotal] = useState(0);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isInitialLoading, setIsInitialLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const pageRef = useRef(1);
  const totalRef = useRef(0);
  const ordersLengthRef = useRef(0);
  const loadingMoreRef = useRef(false);
  const refreshingRef = useRef(false);
  const hasFocusedRef = useRef(false);

  useEffect(() => {
    ordersLengthRef.current = orders.length;
  }, [orders.length]);

  const handleApiError = useCallback(
    async (e: unknown) => {
      if (e instanceof ApiError && e.code === 40301) {
        await refreshUser();
        return;
      }
      if (e instanceof ApiError) {
        setError(e.message);
      } else if (e instanceof Error) {
        setError(e.message);
      } else {
        setError('加载失败，请稍后重试');
      }
    },
    [refreshUser],
  );

  const loadOrders = useCallback(
    async (reset: boolean, tab: TabKey) => {
      if (!reset) {
        if (loadingMoreRef.current || refreshingRef.current) {
          return;
        }
        if (
          ordersLengthRef.current >= totalRef.current &&
          totalRef.current > 0
        ) {
          return;
        }
      }

      const nextPage = reset ? 1 : pageRef.current + 1;

      if (reset) {
        refreshingRef.current = true;
        setIsRefreshing(true);
      } else {
        loadingMoreRef.current = true;
        setIsLoadingMore(true);
      }

      try {
        setError(null);
        const result = await fetchOrdersForTab(tab, nextPage);

        setOrders(prev =>
          reset ? result.items : [...prev, ...result.items],
        );
        pageRef.current = nextPage;
        totalRef.current = result.meta.total;
        setTotal(result.meta.total);
      } catch (e) {
        await handleApiError(e);
      } finally {
        if (reset) {
          refreshingRef.current = false;
          setIsRefreshing(false);
        } else {
          loadingMoreRef.current = false;
          setIsLoadingMore(false);
        }
        setIsInitialLoading(false);
      }
    },
    [handleApiError],
  );

  useEffect(() => {
    pageRef.current = 1;
    totalRef.current = 0;
    ordersLengthRef.current = 0;
    setOrders([]);
    setTotal(0);
    setIsInitialLoading(true);
    void loadOrders(true, selectedTab);
  }, [selectedTab, loadOrders]);

  useFocusEffect(
    useCallback(() => {
      if (!hasFocusedRef.current) {
        hasFocusedRef.current = true;
        return;
      }
      void loadOrders(true, selectedTab);
    }, [loadOrders, selectedTab]),
  );

  const handleTabSelect = (tab: TabKey) => {
    setSelectedTab(tab);
  };

  const handleRefresh = () => {
    void loadOrders(true, selectedTab);
  };

  const handleLoadMore = () => {
    if (orders.length < total && !isLoadingMore && !isRefreshing) {
      void loadOrders(false, selectedTab);
    }
  };

  const handleOrderPress = (orderId: number) => {
    navigation.navigate('OrderDetail', {orderId});
  };

  if (isInitialLoading && orders.length === 0) {
    return (
      <View style={styles.container}>
        <OrderStatusTabs
          selectedTab={selectedTab}
          onSelect={handleTabSelect}
        />
        <LoadingView />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <OrderStatusTabs selectedTab={selectedTab} onSelect={handleTabSelect} />
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <FlatList
        data={orders}
        keyExtractor={item => String(item.id)}
        renderItem={({item}) => (
          <OrderListItem
            order={item}
            onPress={() => handleOrderPress(item.id)}
          />
        )}
        refreshControl={
          <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} />
        }
        onEndReached={handleLoadMore}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          !isRefreshing ? <EmptyState message="暂无订单" /> : null
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footer}>
              <ActivityIndicator />
            </View>
          ) : null
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  error: {
    color: '#d32f2f',
    fontSize: 14,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  footer: {
    paddingVertical: 16,
    alignItems: 'center',
  },
});

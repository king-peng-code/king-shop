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
import {fetchCategories, fetchProducts} from '../api/catalog';
import {ApiError} from '../api/client';
import CategorySidebar from '../components/CategorySidebar';
import EmptyState from '../components/EmptyState';
import LoadingView from '../components/LoadingView';
import ProductListItem from '../components/ProductListItem';
import {useAuth} from '../context/AuthContext';
import type {ShopStackParamList} from '../navigation/types';
import type {Category, Product} from '../types/api';

const SIDEBAR_WIDTH = 88;

export default function HomeScreen() {
  const navigation =
    useNavigation<NativeStackNavigationProp<ShopStackParamList>>();
  const {refreshUser} = useAuth();

  const [categories, setCategories] = useState<Category[]>([]);
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(
    null,
  );
  const [products, setProducts] = useState<Product[]>([]);
  const [total, setTotal] = useState(0);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isInitialLoading, setIsInitialLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const pageRef = useRef(1);
  const totalRef = useRef(0);
  const productsLengthRef = useRef(0);
  const loadingMoreRef = useRef(false);
  const refreshingRef = useRef(false);
  const hasFocusedRef = useRef(false);
  const isMountedRef = useRef(true);

  useEffect(() => {
    productsLengthRef.current = products.length;
  }, [products.length]);

  useEffect(() => {
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  const handleApiError = useCallback(
    async (e: unknown) => {
      if (!isMountedRef.current) {
        return;
      }
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

  const loadCategories = useCallback(async () => {
    try {
      const data = await fetchCategories();
      if (isMountedRef.current) {
        setCategories(data);
      }
    } catch (e) {
      await handleApiError(e);
    }
  }, [handleApiError]);

  const loadProducts = useCallback(
    async (reset: boolean, categoryId: number | null) => {
      if (!reset) {
        if (loadingMoreRef.current || refreshingRef.current) {
          return;
        }
        if (
          productsLengthRef.current >= totalRef.current &&
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
        const result = await fetchProducts({
          categoryId,
          page: nextPage,
          perPage: 20,
        });

        if (!isMountedRef.current) {
          return;
        }

        setProducts(prev =>
          reset ? result.items : [...prev, ...result.items],
        );
        pageRef.current = nextPage;
        totalRef.current = result.meta.total;
        setTotal(result.meta.total);
      } catch (e) {
        if (isMountedRef.current) {
          await handleApiError(e);
        }
      } finally {
        if (!isMountedRef.current) {
          return;
        }
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
    void loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    pageRef.current = 1;
    totalRef.current = 0;
    productsLengthRef.current = 0;
    setProducts([]);
    setTotal(0);
    setIsInitialLoading(true);
    void loadProducts(true, selectedCategoryId);
  }, [selectedCategoryId, loadProducts]);

  useFocusEffect(
    useCallback(() => {
      if (!hasFocusedRef.current) {
        hasFocusedRef.current = true;
        return;
      }
      const timer = setTimeout(() => {
        void loadCategories();
        void loadProducts(true, selectedCategoryId);
      }, 350);
      return () => clearTimeout(timer);
    }, [loadCategories, loadProducts, selectedCategoryId]),
  );

  const handleCategorySelect = (id: number | null) => {
    setSelectedCategoryId(id);
  };

  const handleRefresh = () => {
    void loadCategories();
    void loadProducts(true, selectedCategoryId);
  };

  const handleLoadMore = () => {
    if (products.length < total && !isLoadingMore && !isRefreshing) {
      void loadProducts(false, selectedCategoryId);
    }
  };

  const handleProductPress = (productId: number) => {
    navigation.navigate('ProductDetail', {productId});
  };

  return (
    <View style={styles.container}>
      <View style={styles.sidebar}>
        <CategorySidebar
          categories={categories}
          selectedId={selectedCategoryId}
          onSelect={handleCategorySelect}
        />
      </View>
      <View style={styles.divider} />
      <View style={styles.main}>
        {error ? <Text style={styles.error}>{error}</Text> : null}
        {isInitialLoading && products.length === 0 ? (
          <LoadingView />
        ) : products.length === 0 ? (
          <View style={styles.emptyContainer}>
            <EmptyState message="暂无商品" />
          </View>
        ) : (
          <FlatList
            data={products}
            keyExtractor={item => String(item.id)}
            renderItem={({item}) => (
              <ProductListItem
                product={item}
                onPress={() => handleProductPress(item.id)}
              />
            )}
            contentContainerStyle={styles.listContent}
            refreshControl={
              <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} />
            }
            onEndReached={handleLoadMore}
            onEndReachedThreshold={0.3}
            ListFooterComponent={
              isLoadingMore ? (
                <View style={styles.footer}>
                  <ActivityIndicator />
                </View>
              ) : null
            }
          />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    flexDirection: 'row',
    backgroundColor: '#fff',
  },
  sidebar: {
    width: SIDEBAR_WIDTH,
    backgroundColor: '#fafafa',
  },
  divider: {
    width: StyleSheet.hairlineWidth,
    backgroundColor: '#e0e0e0',
  },
  main: {
    flex: 1,
    backgroundColor: '#fff',
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
  listContent: {
    flexGrow: 1,
    paddingTop: 8,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});

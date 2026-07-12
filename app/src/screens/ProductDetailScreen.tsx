import React, {useCallback, useEffect, useState} from 'react';
import {
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {useNavigation, useRoute} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {RouteProp} from '@react-navigation/native';
import {fetchProduct} from '../api/catalog';
import {ApiError} from '../api/client';
import QuantityStepper from '../components/QuantityStepper';
import LoadingView from '../components/LoadingView';
import {useAuth} from '../context/AuthContext';
import type {ShopStackParamList} from '../navigation/types';
import type {Product} from '../types/api';
import {formatPrice} from '../utils/formatPrice';

type ProductDetailRouteProp = RouteProp<ShopStackParamList, 'ProductDetail'>;
type ProductDetailNavProp = NativeStackNavigationProp<
  ShopStackParamList,
  'ProductDetail'
>;

function productErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.code === 404) {
      return '商品不存在或已下架';
    }
    return error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return '加载失败，请稍后重试';
}

export default function ProductDetailScreen() {
  const route = useRoute<ProductDetailRouteProp>();
  const navigation = useNavigation<ProductDetailNavProp>();
  const {productId} = route.params;
  const {refreshUser} = useAuth();

  const [product, setProduct] = useState<Product | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const handleApiError = useCallback(
    async (e: unknown) => {
      if (e instanceof ApiError && e.code === 40301) {
        await refreshUser();
        return;
      }
      setError(productErrorMessage(e));
    },
    [refreshUser],
  );

  useEffect(() => {
    let cancelled = false;

    const loadProduct = async () => {
      setIsLoading(true);
      setError(null);
      setProduct(null);

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

    void loadProduct();

    return () => {
      cancelled = true;
    };
  }, [productId, handleApiError]);

  if (isLoading) {
    return <LoadingView />;
  }

  if (error || !product) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorText}>
          {error ?? '商品不存在或已下架'}
        </Text>
      </View>
    );
  }

  const canBuy = product.status === 'on_sale';

  return (
    <View style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        {product.image_url ? (
          <Image
            source={{uri: product.image_url}}
            style={styles.image}
            resizeMode="cover"
          />
        ) : (
          <View style={[styles.image, styles.placeholder]} />
        )}
        <View style={styles.details}>
          <Text style={styles.name}>{product.name}</Text>
          {product.description ? (
            <Text style={styles.description}>{product.description}</Text>
          ) : null}
          <Text style={styles.price}>{formatPrice(product.price)}</Text>
        </View>
      </ScrollView>

      <View style={styles.footer}>
        <QuantityStepper value={quantity} onChange={setQuantity} />
        <Pressable
          style={[styles.buyButton, !canBuy && styles.buyButtonDisabled]}
          disabled={!canBuy}
          onPress={() =>
            navigation.navigate('Checkout', {productId: product.id, quantity})
          }>
          <Text style={styles.buyButtonText}>
            {canBuy ? '立即购买' : '暂不可购'}
          </Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    paddingBottom: 24,
  },
  image: {
    width: '100%',
    aspectRatio: 1,
  },
  placeholder: {
    backgroundColor: '#e0e0e0',
  },
  details: {
    padding: 16,
  },
  name: {
    fontSize: 20,
    fontWeight: '600',
    color: '#333',
  },
  description: {
    fontSize: 15,
    color: '#666',
    marginTop: 12,
    lineHeight: 22,
  },
  price: {
    fontSize: 22,
    fontWeight: '600',
    color: '#e53935',
    marginTop: 16,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#eee',
    backgroundColor: '#fff',
  },
  buyButton: {
    flex: 1,
    marginLeft: 16,
    backgroundColor: '#1976d2',
    borderRadius: 8,
    paddingVertical: 12,
    alignItems: 'center',
  },
  buyButtonDisabled: {
    backgroundColor: '#bbb',
  },
  buyButtonText: {
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

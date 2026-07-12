import React from 'react';
import {
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import type {Product} from '../types/api';
import {formatPrice} from '../utils/formatPrice';

interface ProductListItemProps {
  product: Product;
  onPress: () => void;
}

export default function ProductListItem({
  product,
  onPress,
}: ProductListItemProps) {
  return (
    <Pressable style={styles.container} onPress={onPress}>
      {product.image_url ? (
        <Image source={{uri: product.image_url}} style={styles.image} />
      ) : (
        <View style={[styles.image, styles.placeholder]} />
      )}
      <View style={styles.content}>
        <Text style={styles.name} numberOfLines={1}>
          {product.name}
        </Text>
        {product.description ? (
          <Text style={styles.description} numberOfLines={2}>
            {product.description}
          </Text>
        ) : null}
        <Text style={styles.price}>{formatPrice(product.price)}</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    padding: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e8e8e8',
  },
  image: {
    width: 80,
    height: 80,
    borderRadius: 4,
  },
  placeholder: {
    backgroundColor: '#e0e0e0',
  },
  content: {
    flex: 1,
    marginLeft: 12,
    justifyContent: 'center',
  },
  name: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  description: {
    fontSize: 13,
    color: '#666',
    marginTop: 4,
  },
  price: {
    fontSize: 15,
    fontWeight: '600',
    color: '#e53935',
    marginTop: 6,
  },
});

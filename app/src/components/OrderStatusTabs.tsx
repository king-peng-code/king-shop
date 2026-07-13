import React from 'react';
import {Pressable, ScrollView, StyleSheet, Text, View} from 'react-native';

export type OrderStatusTabKey =
  | 'all'
  | 'pending_payment'
  | 'paid'
  | 'cancelled';

interface OrderStatusTabsProps {
  selectedTab: OrderStatusTabKey;
  onSelect: (tab: OrderStatusTabKey) => void;
}

const TABS: {key: OrderStatusTabKey; label: string}[] = [
  {key: 'all', label: '全部'},
  {key: 'pending_payment', label: '待支付'},
  {key: 'paid', label: '已支付'},
  {key: 'cancelled', label: '已取消'},
];

export default function OrderStatusTabs({
  selectedTab,
  onSelect,
}: OrderStatusTabsProps) {
  return (
    <View style={styles.wrapper}>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.container}>
        {TABS.map(tab => {
          const isSelected = tab.key === selectedTab;
          return (
            <Pressable
              key={tab.key}
              style={[styles.tab, isSelected && styles.tabSelected]}
              onPress={() => onSelect(tab.key)}>
              <Text
                style={[styles.tabText, isSelected && styles.tabTextSelected]}>
                {tab.label}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    height: 40,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e8e8e8',
  },
  container: {
    paddingHorizontal: 8,
  },
  tab: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabSelected: {
    borderBottomColor: '#1677ff',
  },
  tabText: {
    fontSize: 14,
    color: '#666',
  },
  tabTextSelected: {
    color: '#1677ff',
    fontWeight: '700',
  },
});

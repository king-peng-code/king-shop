import React from 'react';
import {Pressable, ScrollView, StyleSheet, Text} from 'react-native';

export type OrderStatusTabKey =
  | 'all'
  | 'pending_payment'
  | 'in_progress'
  | 'ready'
  | 'completed';

interface OrderStatusTabsProps {
  selectedTab: OrderStatusTabKey;
  onSelect: (tab: OrderStatusTabKey) => void;
}

const TABS: {key: OrderStatusTabKey; label: string}[] = [
  {key: 'all', label: '全部'},
  {key: 'pending_payment', label: '待支付'},
  {key: 'in_progress', label: '进行中'},
  {key: 'ready', label: '可取餐'},
  {key: 'completed', label: '已完成'},
];

export default function OrderStatusTabs({
  selectedTab,
  onSelect,
}: OrderStatusTabsProps) {
  return (
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
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e8e8e8',
  },
  tab: {
    paddingHorizontal: 16,
    paddingVertical: 12,
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

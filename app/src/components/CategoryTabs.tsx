import React from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
} from 'react-native';
import type {Category} from '../types/api';

interface CategoryTabsProps {
  categories: Category[];
  selectedId: number | null;
  onSelect: (id: number | null) => void;
}

export default function CategoryTabs({
  categories,
  selectedId,
  onSelect,
}: CategoryTabsProps) {
  const tabs: {id: number | null; name: string}[] = [
    {id: null, name: '全部'},
    ...categories.map(c => ({id: c.id, name: c.name})),
  ];

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.container}>
      {tabs.map(tab => {
        const isSelected = tab.id === selectedId;
        return (
          <Pressable
            key={tab.id ?? 'all'}
            style={[styles.tab, isSelected && styles.tabSelected]}
            onPress={() => onSelect(tab.id)}>
            <Text style={[styles.tabText, isSelected && styles.tabTextSelected]}>
              {tab.name}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    gap: 8,
  },
  tab: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 16,
    backgroundColor: '#f0f0f0',
  },
  tabSelected: {
    backgroundColor: '#1677ff',
  },
  tabText: {
    fontSize: 14,
    color: '#333',
  },
  tabTextSelected: {
    color: '#fff',
    fontWeight: '600',
  },
});

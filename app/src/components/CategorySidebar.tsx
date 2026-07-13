import React from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
} from 'react-native';
import type {Category} from '../types/api';

interface CategorySidebarProps {
  categories: Category[];
  selectedId: number | null;
  onSelect: (id: number | null) => void;
}

export default function CategorySidebar({
  categories,
  selectedId,
  onSelect,
}: CategorySidebarProps) {
  const tabs: {id: number | null; name: string}[] = [
    {id: null, name: '全部'},
    ...categories.map(c => ({id: c.id, name: c.name})),
  ];

  return (
    <ScrollView
      showsVerticalScrollIndicator={false}
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
    paddingVertical: 4,
  },
  tab: {
    paddingHorizontal: 12,
    paddingVertical: 14,
    borderLeftWidth: 3,
    borderLeftColor: 'transparent',
    backgroundColor: '#fafafa',
  },
  tabSelected: {
    backgroundColor: '#e6f0ff',
    borderLeftColor: '#1677ff',
  },
  tabText: {
    fontSize: 14,
    color: '#333',
    textAlign: 'center',
  },
  tabTextSelected: {
    color: '#1677ff',
    fontWeight: '600',
  },
});

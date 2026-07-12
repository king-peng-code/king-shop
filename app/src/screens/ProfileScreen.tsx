import React, {useCallback} from 'react';
import {
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../context/AuthContext';
import type {ProfileStackParamList} from '../navigation/types';

function nameInitial(name: string): string {
  const trimmed = name.trim();
  return trimmed ? trimmed.charAt(0) : '?';
}

export default function ProfileScreen() {
  const navigation =
    useNavigation<NativeStackNavigationProp<ProfileStackParamList>>();
  const {user, logout} = useAuth();

  const handleChangePassword = useCallback(() => {
    navigation.navigate('ChangePassword');
  }, [navigation]);

  const handleLogout = useCallback(() => {
    Alert.alert('退出登录', '确定要退出当前账号吗？', [
      {text: '取消', style: 'cancel'},
      {
        text: '退出',
        style: 'destructive',
        onPress: () => {
          void logout();
        },
      },
    ]);
  }, [logout]);

  if (!user) {
    return null;
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{nameInitial(user.name)}</Text>
        </View>

        <View style={styles.infoRows}>
          <View style={styles.infoRow}>
            <Text style={styles.label}>姓名</Text>
            <Text style={styles.value}>{user.name}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.label}>部门</Text>
            <Text style={styles.value}>{user.department ?? '未设置'}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.label}>手机号</Text>
            <Text style={styles.value}>{user.phone}</Text>
          </View>
          {user.employee_no ? (
            <View style={styles.infoRow}>
              <Text style={styles.label}>工号</Text>
              <Text style={styles.value}>{user.employee_no}</Text>
            </View>
          ) : null}
        </View>
      </View>

      <View style={styles.actions}>
        <Pressable style={styles.actionButton} onPress={handleChangePassword}>
          <Text style={styles.actionButtonText}>修改密码</Text>
        </Pressable>
        <Pressable
          style={[styles.actionButton, styles.logoutButton]}
          onPress={handleLogout}>
          <Text style={[styles.actionButtonText, styles.logoutButtonText]}>
            退出登录
          </Text>
        </Pressable>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  content: {
    padding: 16,
    paddingBottom: 32,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 24,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: '#1976d2',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  avatarText: {
    fontSize: 28,
    fontWeight: '600',
    color: '#fff',
  },
  infoRows: {
    width: '100%',
    gap: 12,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 4,
  },
  label: {
    fontSize: 14,
    color: '#666',
  },
  value: {
    fontSize: 15,
    fontWeight: '500',
    color: '#1a1a1a',
    flexShrink: 1,
    textAlign: 'right',
    marginLeft: 16,
  },
  actions: {
    marginTop: 24,
    gap: 12,
  },
  actionButton: {
    backgroundColor: '#fff',
    borderRadius: 12,
    paddingVertical: 16,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  actionButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1976d2',
  },
  logoutButton: {
    backgroundColor: '#fff',
  },
  logoutButtonText: {
    color: '#d32f2f',
  },
});

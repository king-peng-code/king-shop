import { useCallback, useEffect, useState } from 'react';
import { Alert, Spin, Tabs, Typography } from 'antd';
import { configsApi } from '../../api/configs';
import { ConfigGroupForm } from '../../components/ConfigGroupForm';
import { useAuth } from '../../contexts/AuthContext';
import type { ConfigGroup } from '../../types/config';

export function SettingsPage() {
  const { user } = useAuth();
  const [groups, setGroups] = useState<ConfigGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadConfigs = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await configsApi.get();
      setGroups(result.groups);
    } catch {
      setError('加载配置失败，请重试');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadConfigs();
  }, [loadConfigs]);

  if (loading) {
    return <Spin size="large" style={{ display: 'block', margin: '80px auto' }} />;
  }

  if (error) {
    return <Alert type="error" message={error} showIcon />;
  }

  if (!user) {
    return null;
  }

  const tabItems = groups.map((group) => ({
    key: group.name,
    label: group.label,
    children: (
      <ConfigGroupForm
        group={group}
        userRole={user.role}
        onSaved={(updatedGroups) => setGroups(updatedGroups)}
      />
    ),
  }));

  return (
    <div>
      <Typography.Title level={4} style={{ marginBottom: 16 }}>
        系统配置
      </Typography.Title>
      <Tabs items={tabItems} />
    </div>
  );
}

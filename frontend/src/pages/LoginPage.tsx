import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { authApi } from '../api/auth';
import { ApiError } from '../api/client';
import { useAuth, isAdminRole } from '../contexts/AuthContext';

export default function LoginPage() {
  const navigate = useNavigate();
  const { login, user, token, loading } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!loading && token && user) {
    if (user.must_change_password) {
      return <Navigate to="/change-password" replace />;
    }
    if (isAdminRole(user.role)) {
      return <Navigate to="/employees" replace />;
    }
  }

  const onFinish = async (values: { phone: string; password: string }) => {
    setSubmitting(true);
    setError(null);
    try {
      const result = await authApi.login(values.phone, values.password);
      login(result.token, result.user);
      if (result.must_change_password) {
        navigate('/change-password');
      } else if (isAdminRole(result.user.role)) {
        navigate('/employees');
      }
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.message);
      } else {
        setError('登录失败，请重试');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: '#f0f2f5',
      }}
    >
      <Card style={{ width: 400 }}>
        <Typography.Title level={3} style={{ textAlign: 'center', marginBottom: 24 }}>
          King Shop 管理后台
        </Typography.Title>
        {error && (
          <Alert type="error" message={error} style={{ marginBottom: 16 }} showIcon />
        )}
        <Form layout="vertical" onFinish={onFinish}>
          <Form.Item
            label="手机号"
            name="phone"
            rules={[
              { required: true, message: '请输入手机号' },
              { pattern: /^1\d{10}$/, message: '请输入 11 位手机号' },
            ]}
          >
            <Input placeholder="手机号" size="large" />
          </Form.Item>
          <Form.Item
            label="密码"
            name="password"
            rules={[{ required: true, message: '请输入密码' }]}
          >
            <Input.Password placeholder="密码" size="large" />
          </Form.Item>
          <Button type="primary" htmlType="submit" block size="large" loading={submitting}>
            登录
          </Button>
        </Form>
      </Card>
    </div>
  );
}

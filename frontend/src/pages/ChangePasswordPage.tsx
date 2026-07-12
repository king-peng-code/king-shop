import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Alert, Button, Card, Form, Input, Typography, message } from 'antd';
import { authApi } from '../api/auth';
import { ApiError } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

export default function ChangePasswordPage() {
  const navigate = useNavigate();
  const { refreshUser } = useAuth();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onFinish = async (values: {
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
  }) => {
    setSubmitting(true);
    setError(null);
    try {
      await authApi.changePassword(
        values.current_password,
        values.new_password,
        values.new_password_confirmation,
      );
      await refreshUser();
      message.success('密码修改成功');
      navigate('/employees');
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.message);
      } else {
        setError('修改失败，请重试');
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
        <Typography.Title level={4} style={{ textAlign: 'center', marginBottom: 8 }}>
          修改密码
        </Typography.Title>
        <Typography.Paragraph type="secondary" style={{ textAlign: 'center', marginBottom: 24 }}>
          首次登录须修改密码后才能使用管理功能
        </Typography.Paragraph>
        {error && (
          <Alert type="error" message={error} style={{ marginBottom: 16 }} showIcon />
        )}
        <Form layout="vertical" onFinish={onFinish}>
          <Form.Item
            label="当前密码"
            name="current_password"
            rules={[{ required: true, message: '请输入当前密码' }]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label="新密码"
            name="new_password"
            rules={[
              { required: true, message: '请输入新密码' },
              { min: 6, message: '密码至少 6 位' },
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label="确认新密码"
            name="new_password_confirmation"
            dependencies={['new_password']}
            rules={[
              { required: true, message: '请确认新密码' },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  if (!value || getFieldValue('new_password') === value) {
                    return Promise.resolve();
                  }
                  return Promise.reject(new Error('两次密码不一致'));
                },
              }),
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Button type="primary" htmlType="submit" block loading={submitting}>
            确认修改
          </Button>
        </Form>
      </Card>
    </div>
  );
}

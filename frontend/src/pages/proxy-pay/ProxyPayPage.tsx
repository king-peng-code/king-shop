import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Button, Card, Descriptions, Input, Space, Typography, message } from 'antd';
import { proxyPayApi } from '../../api/proxyPay';
import { ApiError, getToken, setToken } from '../../api/client';
import { authApi } from '../../api/auth';
import { fenToYuan } from '../../utils/price';

export default function ProxyPayPage() {
  const { token = '' } = useParams();
  const navigate = useNavigate();
  const [preview, setPreview] = useState<Awaited<ReturnType<typeof proxyPayApi.preview>> | null>(null);
  const [loading, setLoading] = useState(true);
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [paying, setPaying] = useState(false);
  const [loggedIn, setLoggedIn] = useState(Boolean(getToken()));

  useEffect(() => {
    void proxyPayApi
      .preview(token)
      .then(setPreview)
      .catch((e) => {
        if (e instanceof ApiError) {
          message.error(e.message);
        }
      })
      .finally(() => setLoading(false));
  }, [token]);

  const handleLogin = async () => {
    try {
      const result = await authApi.login(phone, password);
      setToken(result.token);
      setLoggedIn(true);
      message.success('登录成功');
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    }
  };

  const handlePay = async () => {
    setPaying(true);
    try {
      const result = await proxyPayApi.pay(token, { channel: 'fake' });
      await fetch(`${import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api/v1'}/payments/notify/wechat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          trade_status: 'TRADE_SUCCESS',
          out_trade_no: result.payment.out_trade_no,
          trade_no: 'FAKE_H5',
        }),
      });
      message.success('代付成功');
      void proxyPayApi.preview(token).then(setPreview);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setPaying(false);
    }
  };

  if (loading) {
    return <Card loading style={{ maxWidth: 480, margin: '40px auto' }} />;
  }

  if (!preview) {
    return (
      <Card style={{ maxWidth: 480, margin: '40px auto' }}>
        <Typography.Text type="danger">代付链接无效或已过期</Typography.Text>
      </Card>
    );
  }

  return (
    <Card title="帮人代付" style={{ maxWidth: 480, margin: '40px auto' }}>
      <Descriptions column={1} size="small">
        <Descriptions.Item label="订单号">{preview.order_no}</Descriptions.Item>
        <Descriptions.Item label="下单人">{preview.buyer_name ?? '-'}</Descriptions.Item>
        <Descriptions.Item label="金额">¥{fenToYuan(preview.total_amount)}</Descriptions.Item>
        <Descriptions.Item label="状态">{preview.status}</Descriptions.Item>
        <Descriptions.Item label="有效期">{new Date(preview.expires_at).toLocaleString()}</Descriptions.Item>
      </Descriptions>

      {!loggedIn ? (
        <Space direction="vertical" style={{ width: '100%', marginTop: 24 }}>
          <Typography.Text>请先登录后再代付</Typography.Text>
          <Input placeholder="手机号" value={phone} onChange={(e) => setPhone(e.target.value)} />
          <Input.Password placeholder="密码" value={password} onChange={(e) => setPassword(e.target.value)} />
          <Button type="primary" block onClick={() => void handleLogin()}>
            登录
          </Button>
          <Button type="link" block onClick={() => navigate('/login')}>
            前往登录页
          </Button>
        </Space>
      ) : (
        <Button
          type="primary"
          block
          style={{ marginTop: 24 }}
          loading={paying}
          disabled={!preview.payable}
          onClick={() => void handlePay()}
        >
          {preview.payable ? '确认代付' : '订单不可支付'}
        </Button>
      )}
    </Card>
  );
}

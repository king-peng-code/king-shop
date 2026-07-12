import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Button, Card, Descriptions, Typography, message } from 'antd';
import { proxyPayApi } from '../../api/proxyPay';
import { ApiError } from '../../api/client';
import { fenToYuan } from '../../utils/price';

const FAKE_PAYER_KEY = 'proxy_pay_fake_external_id';

function getOrCreateFakeExternalId(): string {
  let id = localStorage.getItem(FAKE_PAYER_KEY);
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem(FAKE_PAYER_KEY, id);
  }
  return id;
}

export default function ProxyPayPage() {
  const { token = '' } = useParams();
  const [preview, setPreview] = useState<Awaited<ReturnType<typeof proxyPayApi.preview>> | null>(null);
  const [loading, setLoading] = useState(true);
  const [paying, setPaying] = useState(false);

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

  const handlePay = async () => {
    setPaying(true);
    try {
      const external_id = getOrCreateFakeExternalId();
      const result = await proxyPayApi.pay(token, {
        channel: 'fake',
        provider: 'fake',
        external_id,
        payer_name: 'H5代付人',
      });
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
    </Card>
  );
}

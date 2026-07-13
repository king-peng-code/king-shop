import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Button, Card, Descriptions, Typography, message } from 'antd';
import { proxyPayApi } from '../../api/proxyPay';
import { ApiError } from '../../api/client';
import { fenToYuan } from '../../utils/price';

const FAKE_PAYER_KEY = 'proxy_pay_fake_external_id';

const STATUS_LABELS: Record<string, string> = {
  pending_payment: '待支付',
  paid: '已支付',
  cancelled: '已取消',
};

function getStatusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status;
}

function getOrCreateFakeExternalId(): string {
  let id = localStorage.getItem(FAKE_PAYER_KEY);
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem(FAKE_PAYER_KEY, id);
  }
  return id;
}

function formatCountdown(ms: number): string {
  if (ms <= 0) return '已过期';
  const totalSeconds = Math.floor(ms / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

export default function ProxyPayPage() {
  const { token = '' } = useParams();
  const [preview, setPreview] = useState<Awaited<ReturnType<typeof proxyPayApi.preview>> | null>(null);
  const [loading, setLoading] = useState(true);
  const [paying, setPaying] = useState(false);
  const [countdown, setCountdown] = useState<number>(0);

  useEffect(() => {
    void proxyPayApi
      .preview(token)
      .then((data) => {
        setPreview(data);
        const expires = new Date(data.expires_at).getTime();
        const now = Date.now();
        setCountdown(Math.max(0, expires - now));
      })
      .catch((e) => {
        if (e instanceof ApiError) {
          message.error(e.message);
        }
      })
      .finally(() => setLoading(false));
  }, [token]);

  useEffect(() => {
    if (!preview || !preview.payable) return;

    const timer = setInterval(() => {
      setCountdown((prev) => {
        const next = prev - 1000;
        if (next <= 0) {
          clearInterval(timer);
          // Re-fetch preview to update status
          void proxyPayApi.preview(token).then(setPreview).catch(() => {});
          return 0;
        }
        return next;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [preview, token]);

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

  const countdownLabel = countdown > 0
    ? `待支付剩余时间 ${formatCountdown(countdown)}`
    : '已过期';
  const statusLabel = getStatusLabel(preview.status);

  return (
    <Card style={{ maxWidth: 480, margin: '40px auto' }}>
      <Descriptions column={1} size="small">
        <Descriptions.Item label="下单人">{preview.buyer_name ?? '-'}</Descriptions.Item>
        <Descriptions.Item label="商品">{preview.items_summary || '-'}</Descriptions.Item>
        <Descriptions.Item label="金额">
          <span style={{ fontSize: 20, fontWeight: 600, color: '#e53935' }}>
            ¥{fenToYuan(preview.total_amount)}
          </span>
        </Descriptions.Item>
        <Descriptions.Item label="状态">{statusLabel}</Descriptions.Item>
        <Descriptions.Item label={countdown > 0 ? '待支付剩余时间' : '有效期'}>
          {countdown > 0 ? (
            <span style={{ color: countdown <= 60000 ? '#e53935' : '#333', fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>
              {formatCountdown(countdown)}
            </span>
          ) : (
            new Date(preview.expires_at).toLocaleString()
          )}
        </Descriptions.Item>
      </Descriptions>

      <Button
        type="primary"
        block
        size="large"
        style={{ marginTop: 24, height: 44 }}
        loading={paying}
        disabled={!preview.payable}
        onClick={() => void handlePay()}
      >
        {preview.payable ? '立即支付' : '订单不可支付'}
      </Button>
    </Card>
  );
}

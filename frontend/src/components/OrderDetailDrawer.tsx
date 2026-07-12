import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Descriptions,
  Drawer,
  Image,
  Popconfirm,
  Space,
  Spin,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import dayjs from 'dayjs';
import { ordersApi } from '../api/orders';
import { ApiError } from '../api/client';
import type { Order } from '../types/order';
import { fenToYuan } from '../utils/price';
import { resolveMediaUrl } from '../utils/mediaUrl';
import {
  formatOrderStatusLabel,
  getOrderStatusColor,
} from '../utils/orderStatus';

interface OrderDetailDrawerProps {
  open: boolean;
  orderId: number | null;
  onClose: () => void;
  onUpdated: () => void;
}

export default function OrderDetailDrawer({
  open,
  orderId,
  onClose,
  onUpdated,
}: OrderDetailDrawerProps) {
  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);

  const loadOrder = useCallback(async () => {
    if (!orderId) return;
    setLoading(true);
    try {
      const data = await ordersApi.get(orderId);
      setOrder(data);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
      onClose();
    } finally {
      setLoading(false);
    }
  }, [orderId, onClose]);

  useEffect(() => {
    if (open && orderId) {
      void loadOrder();
    } else {
      setOrder(null);
    }
  }, [open, orderId, loadOrder]);

  const runAction = async (action: () => Promise<Order>, successMsg: string) => {
    setActionLoading(true);
    try {
      const updated = await action();
      setOrder(updated);
      message.success(successMsg);
      onUpdated();
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setActionLoading(false);
    }
  };

  const renderActions = () => {
    if (!order || order.status !== 'pending_payment') {
      return null;
    }

    return (
      <Popconfirm
        title="确认取消该订单？"
        onConfirm={() =>
          void runAction(() => ordersApi.cancel(order.id), '订单已取消')
        }
      >
        <Button danger loading={actionLoading}>
          取消订单
        </Button>
      </Popconfirm>
    );
  };

  return (
    <Drawer
      title={order ? `订单 ${order.order_no}` : '订单详情'}
      width={640}
      open={open}
      onClose={onClose}
      footer={
        <Space>{renderActions()}</Space>
      }
    >
      {loading ? (
        <Spin />
      ) : order ? (
        <Space direction="vertical" size="large" style={{ width: '100%' }}>
          <Descriptions column={1} size="small" title="基本信息">
            <Descriptions.Item label="状态">
              <Tag color={getOrderStatusColor(order.status)}>
                {formatOrderStatusLabel(order.status, order.cancel_reason)}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="下单时间">
              {dayjs(order.created_at).format('YYYY-MM-DD HH:mm:ss')}
            </Descriptions.Item>
            {order.remark && (
              <Descriptions.Item label="备注">{order.remark}</Descriptions.Item>
            )}
            {order.cancelled_at && (
              <Descriptions.Item label="取消时间">
                {dayjs(order.cancelled_at).format('YYYY-MM-DD HH:mm:ss')}
              </Descriptions.Item>
            )}
            {order.cancel_reason && (
              <Descriptions.Item label="取消原因">
                {order.cancel_reason}
              </Descriptions.Item>
            )}
          </Descriptions>

          <Descriptions column={1} size="small" title="员工信息">
            <Descriptions.Item label="姓名">{order.user.name}</Descriptions.Item>
            <Descriptions.Item label="手机号">{order.user.phone}</Descriptions.Item>
            {order.user.department && (
              <Descriptions.Item label="部门">
                {order.user.department}
              </Descriptions.Item>
            )}
          </Descriptions>

          <Descriptions column={1} size="small" title="支付信息">
            <Descriptions.Item label="支付方式">
              {order.payment_method === 'self' ? '自付' : '代付'}
            </Descriptions.Item>
            <Descriptions.Item label="支付时间">
              {order.paid_at
                ? dayjs(order.paid_at).format('YYYY-MM-DD HH:mm:ss')
                : '-'}
            </Descriptions.Item>
            {order.payment_method === 'proxy' && (
              <Descriptions.Item label="代付人">
                {order.paid_by_user?.name ?? '-'}
              </Descriptions.Item>
            )}
            <Descriptions.Item label="订单金额">
              <Typography.Text strong>¥{fenToYuan(order.total_amount)}</Typography.Text>
            </Descriptions.Item>
          </Descriptions>

          <div>
            <Typography.Title level={5}>商品明细</Typography.Title>
            <Table
              rowKey="id"
              size="small"
              pagination={false}
              dataSource={order.items ?? []}
              columns={[
                {
                  title: '商品',
                  key: 'product',
                  render: (_, record) => (
                    <Space>
                      {record.product_image && (
                        <Image
                          src={resolveMediaUrl(record.product_image) ?? undefined}
                          width={48}
                          height={48}
                          style={{ objectFit: 'cover' }}
                        />
                      )}
                      <span>{record.product_name}</span>
                    </Space>
                  ),
                },
                {
                  title: '单价',
                  dataIndex: 'price',
                  render: (v: number) => `¥${fenToYuan(v)}`,
                },
                { title: '数量', dataIndex: 'quantity' },
                {
                  title: '小计',
                  dataIndex: 'subtotal',
                  render: (v: number) => `¥${fenToYuan(v)}`,
                },
              ]}
            />
          </div>
        </Space>
      ) : null}
    </Drawer>
  );
}

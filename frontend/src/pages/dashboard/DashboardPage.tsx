import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  Col,
  Empty,
  Row,
  Spin,
  Statistic,
  Table,
  message,
} from 'antd';
import {
  Bar,
  BarChart,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import dayjs from 'dayjs';
import { dashboardApi } from '../../api/dashboard';
import { ApiError } from '../../api/client';
import type { DashboardStats } from '../../types/dashboard';
import type { OrderStatus } from '../../types/order';
import { fenToYuan } from '../../utils/price';

const STATUS_COLORS: Record<OrderStatus, string> = {
  pending_payment: '#fa8c16',
  paid: '#1677ff',
  cancelled: '#ff4d4f',
};

function getStatusColor(status: string): string {
  return STATUS_COLORS[status as OrderStatus] ?? '#d9d9d9';
}

export default function DashboardPage() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<DashboardStats | null>(null);

  const fetchStats = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const stats = await dashboardApi.getStats();
      setData(stats);
    } catch (e) {
      const msg = e instanceof ApiError ? e.message : '加载统计数据失败';
      setError(msg);
      message.error(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void fetchStats();
  }, [fetchStats]);

  if (loading && !data) {
    return (
      <div style={{ textAlign: 'center', padding: 80 }}>
        <Spin size="large" />
      </div>
    );
  }

  if (error && !data) {
    return (
      <div style={{ textAlign: 'center', padding: 80 }}>
        <p style={{ marginBottom: 16, color: '#ff4d4f' }}>{error}</p>
        <Button type="primary" onClick={() => void fetchStats()}>
          重试
        </Button>
      </div>
    );
  }

  if (!data) {
    return <Empty description="暂无统计数据" />;
  }

  const { summary, status_distribution, hot_products_by_quantity, hot_products_by_sales, week_daily_sales } =
    data;

  const chartData = week_daily_sales.map((item) => ({
    ...item,
    dateLabel: dayjs(item.date).format('MM-DD'),
  }));

  return (
    <Spin spinning={loading}>
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="今日订单" value={summary.today.order_count} suffix="单" />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="今日销售额"
              value={fenToYuan(summary.today.sales_amount)}
              prefix="¥"
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic title="本周订单" value={summary.week.order_count} suffix="单" />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card>
            <Statistic
              title="本周销售额"
              value={fenToYuan(summary.week.sales_amount)}
              prefix="¥"
            />
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col xs={24} lg={14}>
          <Card title="本周每日销售额">
            {chartData.length === 0 ? (
              <Empty description="暂无销售数据" />
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={chartData}>
                  <XAxis dataKey="dateLabel" />
                  <YAxis tickFormatter={(v: number) => fenToYuan(v)} />
                  <Tooltip
                    formatter={(value: number) => [`¥${fenToYuan(value)}`, '销售额']}
                    labelFormatter={(label) => `日期: ${label}`}
                  />
                  <Bar dataKey="sales_amount" name="销售额" fill="#1677ff" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </Card>
        </Col>
        <Col xs={24} lg={10}>
          <Card title="订单状态分布">
            {status_distribution.length === 0 ? (
              <Empty description="暂无订单数据" />
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={status_distribution}
                    nameKey="label"
                    dataKey="count"
                    cx="50%"
                    cy="50%"
                    outerRadius={90}
                    label={({ label, percent }) =>
                      `${label} ${(percent * 100).toFixed(0)}%`
                    }
                  >
                    {status_distribution.map((entry) => (
                      <Cell key={entry.status} fill={getStatusColor(entry.status)} />
                    ))}
                  </Pie>
                  <Tooltip />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col xs={24} lg={12}>
          <Card title="热门商品销量榜">
            {hot_products_by_quantity.length === 0 ? (
              <Empty description="暂无热门商品" />
            ) : (
              <Table
                rowKey="product_id"
                pagination={false}
                dataSource={hot_products_by_quantity}
                columns={[
                  {
                    title: '排名',
                    width: 64,
                    render: (_, __, index) => index + 1,
                  },
                  { title: '商品名', dataIndex: 'product_name' },
                  { title: '销量', dataIndex: 'quantity' },
                ]}
              />
            )}
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title="热门商品销售额榜">
            {hot_products_by_sales.length === 0 ? (
              <Empty description="暂无热门商品" />
            ) : (
              <Table
                rowKey="product_id"
                pagination={false}
                dataSource={hot_products_by_sales}
                columns={[
                  {
                    title: '排名',
                    width: 64,
                    render: (_, __, index) => index + 1,
                  },
                  { title: '商品名', dataIndex: 'product_name' },
                  {
                    title: '销售额',
                    dataIndex: 'sales_amount',
                    render: (v: number) => `¥${fenToYuan(v)}`,
                  },
                ]}
              />
            )}
          </Card>
        </Col>
      </Row>
    </Spin>
  );
}

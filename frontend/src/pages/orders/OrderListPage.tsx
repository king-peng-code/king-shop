import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Button,
  DatePicker,
  Input,
  Select,
  Space,
  Table,
  Tag,
  message,
  type TablePaginationConfig,
} from 'antd';
import dayjs, { type Dayjs } from 'dayjs';
import { ordersApi } from '../../api/orders';
import { employeesApi } from '../../api/employees';
import { ApiError } from '../../api/client';
import OrderDetailDrawer from '../../components/OrderDetailDrawer';
import type { Employee } from '../../types/employee';
import type { Order, OrderStatus } from '../../types/order';
import { fenToYuan } from '../../utils/price';

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending_payment: '待支付',
  paid: '已支付',
  preparing: '备餐中',
  ready: '可取餐',
  completed: '已完成',
  cancelled: '已取消',
};

const STATUS_COLORS: Record<OrderStatus, string> = {
  pending_payment: 'orange',
  paid: 'blue',
  preparing: 'purple',
  ready: 'green',
  completed: 'default',
  cancelled: 'red',
};

const STATUS_OPTIONS = (Object.keys(STATUS_LABELS) as OrderStatus[]).map(
  (value) => ({
    value,
    label: STATUS_LABELS[value],
  }),
);

export default function OrderListPage() {
  const [items, setItems] = useState<Order[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState<OrderStatus | undefined>();
  const [userId, setUserId] = useState<number | undefined>();
  const [dateRange, setDateRange] = useState<
    [Dayjs | null, Dayjs | null] | null
  >(null);
  const [keyword, setKeyword] = useState('');
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selectedOrderId, setSelectedOrderId] = useState<number | null>(null);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    void employeesApi
      .list({ per_page: 100 })
      .then((res) => setEmployees(res.items))
      .catch((e) => {
        if (e instanceof ApiError) {
          message.error(e.message);
        }
      });
  }, []);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await ordersApi.list({
        status,
        user_id: userId,
        date_from: dateRange?.[0]?.format('YYYY-MM-DD'),
        date_to: dateRange?.[1]?.format('YYYY-MM-DD'),
        keyword,
        page,
        per_page: perPage,
      });
      setItems(result.items);
      setTotal(result.meta.total);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setLoading(false);
    }
  }, [status, userId, dateRange, keyword, page, perPage]);

  useEffect(() => {
    void fetchList();
  }, [fetchList]);

  useEffect(() => {
    return () => {
      if (searchDebounceRef.current) {
        clearTimeout(searchDebounceRef.current);
      }
    };
  }, []);

  const scheduleSearch = (value: string) => {
    if (searchDebounceRef.current) {
      clearTimeout(searchDebounceRef.current);
    }
    searchDebounceRef.current = setTimeout(() => {
      setKeyword(value.trim());
      setPage(1);
    }, 300);
  };

  const openDetail = (orderId: number) => {
    setSelectedOrderId(orderId);
    setDrawerOpen(true);
  };

  const closeDetail = () => {
    setDrawerOpen(false);
    setSelectedOrderId(null);
  };

  const columns = [
    { title: '订单号', dataIndex: 'order_no', key: 'order_no' },
    {
      title: '员工',
      key: 'user',
      render: (_: unknown, record: Order) => record.user.name,
    },
    {
      title: '金额',
      dataIndex: 'total_amount',
      key: 'total_amount',
      render: (amount: number) => `¥${fenToYuan(amount)}`,
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      render: (s: OrderStatus) => (
        <Tag color={STATUS_COLORS[s]}>{STATUS_LABELS[s]}</Tag>
      ),
    },
    {
      title: '支付方式',
      dataIndex: 'payment_method',
      key: 'payment_method',
      render: (method: string) => (method === 'self' ? '自付' : '代付'),
    },
    {
      title: '代付人',
      key: 'paid_by_user',
      render: (_: unknown, record: Order) =>
        record.payment_method === 'proxy'
          ? (record.paid_by_user?.name ?? '-')
          : '-',
    },
    {
      title: '下单时间',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: '操作',
      key: 'actions',
      render: (_: unknown, record: Order) => (
        <Button
          type="link"
          size="small"
          onClick={(e) => {
            e.stopPropagation();
            openDetail(record.id);
          }}
        >
          详情
        </Button>
      ),
    },
  ];

  const pagination: TablePaginationConfig = {
    current: page,
    pageSize: perPage,
    total,
    showSizeChanger: false,
    onChange: (p) => setPage(p),
  };

  return (
    <>
      <Space style={{ marginBottom: 16, width: '100%' }} wrap>
        <Select
          placeholder="全部状态"
          allowClear
          style={{ width: 140 }}
          value={status}
          onChange={(v) => {
            setStatus(v);
            setPage(1);
          }}
          options={STATUS_OPTIONS}
        />
        <DatePicker.RangePicker
          value={dateRange}
          onChange={(dates) => {
            setDateRange(dates);
            setPage(1);
          }}
        />
        <Select
          placeholder="全部员工"
          allowClear
          showSearch
          optionFilterProp="label"
          style={{ width: 160 }}
          value={userId}
          onChange={(v) => {
            setUserId(v);
            setPage(1);
          }}
          options={employees.map((e) => ({
            value: e.id,
            label: e.name,
          }))}
        />
        <Input.Search
          placeholder="搜索订单号 / 员工"
          allowClear
          onSearch={(v) => {
            setKeyword(v.trim());
            setPage(1);
          }}
          onChange={(e) => scheduleSearch(e.target.value)}
          style={{ width: 240 }}
        />
      </Space>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={pagination}
        onRow={(record) => ({
          onClick: () => openDetail(record.id),
          style: { cursor: 'pointer' },
        })}
      />
      <OrderDetailDrawer
        open={drawerOpen}
        orderId={selectedOrderId}
        onClose={closeDetail}
        onUpdated={() => void fetchList()}
      />
    </>
  );
}

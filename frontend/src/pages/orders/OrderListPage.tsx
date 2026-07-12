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
import dayjs from 'dayjs';
import { employeesApi } from '../../api/employees';
import { ordersApi } from '../../api/orders';
import { ApiError } from '../../api/client';
import OrderDetailDrawer from '../../components/OrderDetailDrawer';
import type { Employee } from '../../types/employee';
import type { Order, OrderListParams, OrderStatus } from '../../types/order';
import { fenToYuan } from '../../utils/price';
import {
  formatOrderStatusLabel,
  getOrderStatusColor,
} from '../../utils/orderStatus';

const STATUS_OPTIONS: { value: OrderStatus | ''; label: string }[] = [
  { value: '', label: '全部状态' },
  { value: 'pending_payment', label: '待支付' },
  { value: 'paid', label: '已支付' },
  { value: 'cancelled', label: '已取消' },
];

export default function OrderListPage() {
  const [items, setItems] = useState<Order[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState<OrderStatus | ''>('');
  const [userId, setUserId] = useState<number | undefined>();
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null] | null>(
    null,
  );
  const [keyword, setKeyword] = useState('');
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selectedOrderId, setSelectedOrderId] = useState<number | null>(null);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    void employeesApi.list({ per_page: 100 }).then((res) => setEmployees(res.items));
  }, []);

  const listParams = useCallback((): OrderListParams => {
    const params: OrderListParams = { page, per_page: perPage };
    if (status) params.status = status;
    if (userId) params.user_id = userId;
    if (dateRange?.[0]) params.date_from = dateRange[0].format('YYYY-MM-DD');
    if (dateRange?.[1]) params.date_to = dateRange[1].format('YYYY-MM-DD');
    if (keyword) params.keyword = keyword;
    return params;
  }, [status, userId, dateRange, keyword, page, perPage]);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await ordersApi.list(listParams());
      setItems(result.items);
      setTotal(result.meta.total);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setLoading(false);
    }
  }, [listParams]);

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

  const handleTableChange = (pagination: TablePaginationConfig) => {
    setPage(pagination.current ?? 1);
  };

  return (
    <div>
      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 140 }}
          value={status}
          options={STATUS_OPTIONS}
          onChange={(v) => {
            setStatus(v);
            setPage(1);
          }}
        />
        <DatePicker.RangePicker
          value={dateRange}
          onChange={(dates) => {
            setDateRange(dates);
            setPage(1);
          }}
        />
        <Select
          allowClear
          showSearch
          placeholder="选择员工"
          style={{ width: 180 }}
          optionFilterProp="label"
          value={userId}
          options={employees.map((e) => ({ value: e.id, label: e.name }))}
          onChange={(v) => {
            setUserId(v);
            setPage(1);
          }}
        />
        <Input.Search
          placeholder="订单号 / 姓名 / 手机号"
          allowClear
          style={{ width: 240 }}
          onChange={(e) => scheduleSearch(e.target.value)}
          onSearch={(v) => {
            setKeyword(v.trim());
            setPage(1);
          }}
        />
      </Space>

      <Table
        rowKey="id"
        loading={loading}
        dataSource={items}
        pagination={{ current: page, pageSize: perPage, total }}
        onChange={handleTableChange}
        columns={[
          { title: '订单号', dataIndex: 'order_no' },
          { title: '员工', dataIndex: ['user', 'name'] },
          {
            title: '金额',
            dataIndex: 'total_amount',
            render: (v: number) => `¥${fenToYuan(v)}`,
          },
          {
            title: '状态',
            dataIndex: 'status',
            render: (s: OrderStatus, record: Order) => (
              <Tag color={getOrderStatusColor(s)}>
                {formatOrderStatusLabel(s, record.cancel_reason)}
              </Tag>
            ),
          },
          {
            title: '支付方式',
            dataIndex: 'payment_method',
            render: (m: string) => (m === 'self' ? '自付' : '代付'),
          },
          {
            title: '代付人',
            render: (_, record) => record.paid_by_user?.name ?? '-',
          },
          {
            title: '下单时间',
            dataIndex: 'created_at',
            render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm'),
          },
          {
            title: '操作',
            render: (_, record) => (
              <Button type="link" onClick={() => openDetail(record.id)}>
                详情
              </Button>
            ),
          },
        ]}
      />

      <OrderDetailDrawer
        open={drawerOpen}
        orderId={selectedOrderId}
        onClose={() => {
          setDrawerOpen(false);
          setSelectedOrderId(null);
        }}
        onUpdated={() => void fetchList()}
      />
    </div>
  );
}

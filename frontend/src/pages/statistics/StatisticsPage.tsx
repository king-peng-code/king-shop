import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  Empty,
  Input,
  Space,
  Spin,
  Table,
  Tabs,
  message,
  type TabsProps,
} from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import { statisticsApi } from '../../api/statistics';
import { ApiError } from '../../api/client';
import type { EmployeeStatsItem, ProxyPayerStatsItem } from '../../types/statistics';
import { fenToYuan } from '../../utils/price';

export default function StatisticsPage() {
  const navigate = useNavigate();

  const [employeeLoading, setEmployeeLoading] = useState(false);
  const [employeeData, setEmployeeData] = useState<EmployeeStatsItem[]>([]);
  const [employeeKeyword, setEmployeeKeyword] = useState('');
  const [proxyPayerLoading, setProxyPayerLoading] = useState(false);
  const [proxyPayerData, setProxyPayerData] = useState<ProxyPayerStatsItem[]>([]);
  const [proxyPayerKeyword, setProxyPayerKeyword] = useState('');

  const employeeSearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const proxyPayerSearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const employeeFetched = useRef(false);
  const proxyPayerFetched = useRef(false);

  const fetchEmployeeStats = useCallback(async (keyword?: string) => {
    setEmployeeLoading(true);
    try {
      const data = await statisticsApi.getEmployeeStats(keyword);
      setEmployeeData(data);
      employeeFetched.current = true;
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setEmployeeLoading(false);
    }
  }, []);

  const fetchProxyPayerStats = useCallback(async (keyword?: string) => {
    setProxyPayerLoading(true);
    try {
      const data = await statisticsApi.getProxyPayerStats(keyword);
      setProxyPayerData(data);
      proxyPayerFetched.current = true;
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setProxyPayerLoading(false);
    }
  }, []);

  const handleEmployeeSearch = (value: string) => {
    setEmployeeKeyword(value);
    if (employeeSearchTimer.current) clearTimeout(employeeSearchTimer.current);
    employeeSearchTimer.current = setTimeout(() => {
      void fetchEmployeeStats(value || undefined);
    }, 300);
  };

  const handleProxyPayerSearch = (value: string) => {
    setProxyPayerKeyword(value);
    if (proxyPayerSearchTimer.current) clearTimeout(proxyPayerSearchTimer.current);
    proxyPayerSearchTimer.current = setTimeout(() => {
      void fetchProxyPayerStats(value || undefined);
    }, 300);
  };

  const employeeColumns = [
    {
      title: '排名',
      width: 64,
      key: 'rank',
      render: (_: unknown, __: unknown, index: number) => index + 1,
    },
    {
      title: '姓名',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: EmployeeStatsItem) => (
        <a onClick={() => navigate(`/orders?user_id=${record.user_id}`)}>
          {name}
        </a>
      ),
    },
    { title: '手机号', dataIndex: 'phone', key: 'phone' },
    {
      title: '订单数',
      dataIndex: 'order_count',
      key: 'order_count',
      render: (v: number) => `${v} 单`,
    },
    {
      title: '订单总金额',
      dataIndex: 'total_amount',
      key: 'total_amount',
      render: (v: number) => `¥${fenToYuan(v)}`,
    },
  ];

  const proxyPayerColumns = [
    {
      title: '排名',
      width: 64,
      key: 'rank',
      render: (_: unknown, __: unknown, index: number) => index + 1,
    },
    {
      title: '姓名',
      dataIndex: 'name',
      key: 'name',
      render: (name: string | null, record: ProxyPayerStatsItem) => (
        <a onClick={() => navigate(`/orders?paid_by_external_user_id=${record.external_user_id}`)}>
          {name ?? '-'}
        </a>
      ),
    },
    { title: '手机号', dataIndex: 'phone', key: 'phone', render: (v: string | null) => v ?? '-' },
    { title: '代付渠道', dataIndex: 'provider', key: 'provider' },
    {
      title: '代付订单数',
      dataIndex: 'order_count',
      key: 'order_count',
      render: (v: number) => `${v} 单`,
    },
    {
      title: '代付总金额',
      dataIndex: 'total_amount',
      key: 'total_amount',
      render: (v: number) => `¥${fenToYuan(v)}`,
    },
  ];

  const handleTabChange = (key: string) => {
    if (key === 'employees' && !employeeFetched.current) {
      void fetchEmployeeStats();
    } else if (key === 'proxy-payers' && !proxyPayerFetched.current) {
      void fetchProxyPayerStats();
    }
  };

  // Fetch default tab on mount
  useEffect(() => {
    void fetchEmployeeStats();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const tabItems: TabsProps['items'] = [
    {
      key: 'employees',
      label: '员工统计',
      children: (
        <Card>
          <Space style={{ marginBottom: 16 }}>
            <Input
              prefix={<SearchOutlined />}
              placeholder="搜索姓名 / 手机号"
              allowClear
              value={employeeKeyword}
              onChange={(e) => handleEmployeeSearch(e.target.value)}
              style={{ width: 280 }}
            />
          </Space>
          <Spin spinning={employeeLoading}>
            {employeeData.length === 0 && !employeeLoading ? (
              <Empty description="暂无员工订单数据" />
            ) : (
              <Table
                rowKey="user_id"
                columns={employeeColumns}
                dataSource={employeeData}
                pagination={employeeData.length > 20 ? { pageSize: 20 } : false}
              />
            )}
          </Spin>
        </Card>
      ),
    },
    {
      key: 'proxy-payers',
      label: '代付人员统计',
      children: (
        <Card>
          <Space style={{ marginBottom: 16 }}>
            <Input
              prefix={<SearchOutlined />}
              placeholder="搜索姓名 / 手机号"
              allowClear
              value={proxyPayerKeyword}
              onChange={(e) => handleProxyPayerSearch(e.target.value)}
              style={{ width: 280 }}
            />
          </Space>
          <Spin spinning={proxyPayerLoading}>
            {proxyPayerData.length === 0 && !proxyPayerLoading ? (
              <Empty description="暂无代付数据" />
            ) : (
              <Table
                rowKey="external_user_id"
                columns={proxyPayerColumns}
                dataSource={proxyPayerData}
                pagination={proxyPayerData.length > 20 ? { pageSize: 20 } : false}
              />
            )}
          </Spin>
        </Card>
      ),
    },
  ];

  return (
    <div>
      <h2 style={{ marginBottom: 16 }}>统计报表</h2>
      <Tabs defaultActiveKey="employees" items={tabItems} onChange={handleTabChange} />
    </div>
  );
}

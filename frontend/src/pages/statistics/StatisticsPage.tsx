import { useCallback, useRef, useState } from 'react';
import {
  Card,
  Empty,
  Spin,
  Table,
  Tabs,
  message,
  type TabsProps,
} from 'antd';
import { statisticsApi } from '../../api/statistics';
import { ApiError } from '../../api/client';
import type { EmployeeStatsItem, ProxyPayerStatsItem } from '../../types/statistics';
import { fenToYuan } from '../../utils/price';

export default function StatisticsPage() {
  const [employeeLoading, setEmployeeLoading] = useState(false);
  const [employeeData, setEmployeeData] = useState<EmployeeStatsItem[]>([]);
  const [proxyPayerLoading, setProxyPayerLoading] = useState(false);
  const [proxyPayerData, setProxyPayerData] = useState<ProxyPayerStatsItem[]>([]);

  const employeeFetched = useRef(false);
  const proxyPayerFetched = useRef(false);

  const fetchEmployeeStats = useCallback(async () => {
    if (employeeFetched.current) return;
    setEmployeeLoading(true);
    try {
      const data = await statisticsApi.getEmployeeStats();
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

  const fetchProxyPayerStats = useCallback(async () => {
    if (proxyPayerFetched.current) return;
    setProxyPayerLoading(true);
    try {
      const data = await statisticsApi.getProxyPayerStats();
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

  const employeeColumns = [
    {
      title: '排名',
      width: 64,
      key: 'rank',
      render: (_: unknown, __: unknown, index: number) => index + 1,
    },
    { title: '姓名', dataIndex: 'name', key: 'name' },
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
    { title: '姓名', dataIndex: 'name', key: 'name', render: (v: string | null) => v ?? '-' },
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
    if (key === 'employees') {
      void fetchEmployeeStats();
    } else if (key === 'proxy-payers') {
      void fetchProxyPayerStats();
    }
  };

  const tabItems: TabsProps['items'] = [
    {
      key: 'employees',
      label: '员工统计',
      children: (
        <Card>
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

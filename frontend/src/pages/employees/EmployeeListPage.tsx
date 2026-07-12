import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Button,
  Input,
  Popconfirm,
  Space,
  Table,
  Tag,
  message,
  type TablePaginationConfig,
} from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { employeesApi } from '../../api/employees';
import { ApiError } from '../../api/client';
import EmployeeFormModal from '../../components/EmployeeFormModal';
import { useAuth } from '../../contexts/AuthContext';
import type { Employee } from '../../types/employee';

const roleLabels: Record<string, string> = {
  employee: '员工',
  admin: '管理员',
  super_admin: '超级管理员',
};

const roleColors: Record<string, string> = {
  employee: 'blue',
  admin: 'orange',
  super_admin: 'red',
};

export default function EmployeeListPage() {
  const { user: currentUser } = useAuth();
  const [items, setItems] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [modalOpen, setModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await employeesApi.list({ keyword, page, per_page: perPage });
      setItems(result.items);
      setTotal(result.meta.total);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setLoading(false);
    }
  }, [keyword, page, perPage]);

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
      handleSearch(value);
    }, 300);
  };

  const handleSearch = (value: string) => {
    setKeyword(value.trim());
    setPage(1);
  };

  const canManageRecord = (record: Employee): boolean => {
    if (currentUser?.role === 'super_admin') {
      return true;
    }
    return record.role === 'employee';
  };

  const openCreate = () => {
    setModalMode('create');
    setEditingEmployee(null);
    setModalOpen(true);
  };

  const openEdit = (record: Employee) => {
    setModalMode('edit');
    setEditingEmployee(record);
    setModalOpen(true);
  };

  const handleResetPassword = async (record: Employee) => {
    try {
      await employeesApi.update(record.id, {
        name: record.name,
        role: record.role,
        status: record.status,
        reset_password: true,
      });
      message.success('密码已重置为 123456');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    }
  };

  const handleDisable = async (record: Employee) => {
    try {
      await employeesApi.disable(record.id);
      message.success('已禁用');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    }
  };

  const columns = [
    { title: '姓名', dataIndex: 'name', key: 'name' },
    { title: '手机号', dataIndex: 'phone', key: 'phone' },
    {
      title: '角色',
      dataIndex: 'role',
      key: 'role',
      render: (role: string) => (
        <Tag color={roleColors[role]}>{roleLabels[role]}</Tag>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'default'}>
          {status === 'active' ? '正常' : '已禁用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      render: (_: unknown, record: Employee) => {
        const isSelf = record.id === currentUser?.id;
        const manageable = canManageRecord(record);
        return (
          <Space>
            {manageable && (
              <Button type="link" size="small" onClick={() => openEdit(record)}>
                编辑
              </Button>
            )}
            {manageable && (
              <Popconfirm
                title="确认重置为默认密码 123456？"
                onConfirm={() => void handleResetPassword(record)}
              >
                <Button type="link" size="small">
                  重置密码
                </Button>
              </Popconfirm>
            )}
            {record.status === 'active' && !isSelf && (
              <Popconfirm
                title="确认禁用该员工？"
                onConfirm={() => void handleDisable(record)}
              >
                <Button type="link" size="small" danger>
                  禁用
                </Button>
              </Popconfirm>
            )}
          </Space>
        );
      },
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
      <Space style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
        <Input.Search
          placeholder="搜索姓名 / 手机号"
          allowClear
          onSearch={handleSearch}
          onChange={(e) => scheduleSearch(e.target.value)}
          style={{ width: 280 }}
        />
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
          新增员工
        </Button>
      </Space>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={pagination}
      />
      <EmployeeFormModal
        open={modalOpen}
        mode={modalMode}
        employee={editingEmployee}
        onClose={() => setModalOpen(false)}
        onSuccess={() => void fetchList()}
      />
    </>
  );
}

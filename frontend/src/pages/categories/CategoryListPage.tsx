import { useCallback, useEffect, useState } from 'react';
import { Button, Popconfirm, Space, Table, Tag, message } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { categoriesApi } from '../../api/categories';
import { ApiError } from '../../api/client';
import CategoryFormModal from '../../components/CategoryFormModal';
import type { Category, CategoryStatus } from '../../types/category';

export default function CategoryListPage() {
  const [items, setItems] = useState<Category[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await categoriesApi.list();
      setItems(result.items);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void fetchList();
  }, [fetchList]);

  const openCreate = () => {
    setModalMode('create');
    setEditingCategory(null);
    setModalOpen(true);
  };

  const openEdit = (record: Category) => {
    setModalMode('edit');
    setEditingCategory(record);
    setModalOpen(true);
  };

  const toggleStatus = async (record: Category, newStatus: CategoryStatus) => {
    try {
      await categoriesApi.update(record.id, {
        name: record.name,
        sort: record.sort,
        status: newStatus,
      });
      message.success(newStatus === 'active' ? '已启用' : '已禁用');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    }
  };

  const handleDelete = async (record: Category) => {
    try {
      await categoriesApi.delete(record.id);
      message.success('删除成功');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    }
  };

  const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: '名称', dataIndex: 'name', key: 'name' },
    { title: '排序', dataIndex: 'sort', key: 'sort', width: 100 },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'default'}>
          {status === 'active' ? '启用' : '禁用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      width: 240,
      render: (_: unknown, record: Category) => (
        <Space>
          <Button type="link" size="small" onClick={() => openEdit(record)}>
            编辑
          </Button>
          {record.status === 'active' ? (
            <Popconfirm
              title="确认禁用该分类？"
              description="禁用后 App 端该分类及下属商品不可见"
              onConfirm={() => void toggleStatus(record, 'disabled')}
            >
              <Button type="link" size="small" danger>
                禁用
              </Button>
            </Popconfirm>
          ) : (
            <Popconfirm
              title="确认启用该分类？"
              onConfirm={() => void toggleStatus(record, 'active')}
            >
              <Button type="link" size="small">
                启用
              </Button>
            </Popconfirm>
          )}
          <Popconfirm
            title="确认删除该分类？"
            description="仅无商品的分类可删除"
            onConfirm={() => void handleDelete(record)}
          >
            <Button type="link" size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <Space style={{ marginBottom: 16, width: '100%', justifyContent: 'flex-end' }}>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
          新增分类
        </Button>
      </Space>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={false}
      />
      <CategoryFormModal
        open={modalOpen}
        mode={modalMode}
        category={editingCategory}
        onClose={() => setModalOpen(false)}
        onSuccess={() => void fetchList()}
      />
    </>
  );
}

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Button,
  Image,
  Input,
  Popconfirm,
  Select,
  Space,
  Table,
  Tag,
  message,
  type TablePaginationConfig,
} from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { categoriesApi } from '../../api/categories';
import { productsApi } from '../../api/products';
import { ApiError } from '../../api/client';
import ProductFormModal from '../../components/ProductFormModal';
import type { Category } from '../../types/category';
import type { Product, ProductStatus } from '../../types/product';
import { fenToYuan } from '../../utils/price';
import { resolveMediaUrl } from '../../utils/mediaUrl';

export default function ProductListPage() {
  const [items, setItems] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [categoryId, setCategoryId] = useState<number | undefined>();
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [modalOpen, setModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    void categoriesApi.list().then((res) => setCategories(res.items));
  }, []);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await productsApi.list({
        keyword,
        category_id: categoryId,
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
  }, [keyword, categoryId, page, perPage]);

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

  const openCreate = () => {
    setModalMode('create');
    setEditingProduct(null);
    setModalOpen(true);
  };

  const openEdit = (record: Product) => {
    setModalMode('edit');
    setEditingProduct(record);
    setModalOpen(true);
  };

  const toggleStatus = async (record: Product, newStatus: ProductStatus) => {
    try {
      await productsApi.update(record.id, {
        category_id: record.category_id,
        name: record.name,
        description: record.description,
        price: record.price,
        status: newStatus,
        sort: record.sort,
      });
      message.success(newStatus === 'on_sale' ? '已启用' : '已禁用');
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      }
    }
  };

  const columns = [
    {
      title: '封面',
      dataIndex: 'image_url',
      key: 'image_url',
      width: 80,
      render: (url: string | null) => {
        const src = resolveMediaUrl(url);
        return src ? (
          <Image src={src} width={48} height={48} style={{ objectFit: 'cover' }} />
        ) : (
          <span style={{ color: '#999' }}>无图</span>
        );
      },
    },
    { title: '名称', dataIndex: 'name', key: 'name' },
    { title: '分类', dataIndex: 'category_name', key: 'category_name', width: 120 },
    {
      title: '价格',
      dataIndex: 'price',
      key: 'price',
      width: 100,
      render: (price: number) => `¥${fenToYuan(price)}`,
    },
    { title: '排序', dataIndex: 'sort', key: 'sort', width: 80 },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 100,
      render: (s: ProductStatus) => (
        <Tag color={s === 'on_sale' ? 'green' : 'default'}>
          {s === 'on_sale' ? '启用' : '禁用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      width: 180,
      render: (_: unknown, record: Product) => (
        <Space>
          <Button type="link" size="small" onClick={() => openEdit(record)}>
            编辑
          </Button>
          {record.status === 'on_sale' ? (
            <Popconfirm
              title="确认禁用该商品？"
              onConfirm={() => void toggleStatus(record, 'off_sale')}
            >
              <Button type="link" size="small" danger>
                禁用
              </Button>
            </Popconfirm>
          ) : (
            <Popconfirm
              title="确认启用该商品？"
              onConfirm={() => void toggleStatus(record, 'on_sale')}
            >
              <Button type="link" size="small">
                启用
              </Button>
            </Popconfirm>
          )}
        </Space>
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
      <Space style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }} wrap>
        <Space wrap>
          <Select
            placeholder="全部分类"
            allowClear
            style={{ width: 160 }}
            value={categoryId}
            onChange={(v) => {
              setCategoryId(v);
              setPage(1);
            }}
            options={categories.map((c) => ({ value: c.id, label: c.name }))}
          />
          <Input.Search
            placeholder="搜索商品名称"
            allowClear
            onSearch={(v) => {
              setKeyword(v.trim());
              setPage(1);
            }}
            onChange={(e) => scheduleSearch(e.target.value)}
            style={{ width: 220 }}
          />
        </Space>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
          新增商品
        </Button>
      </Space>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={pagination}
      />
      <ProductFormModal
        open={modalOpen}
        mode={modalMode}
        product={editingProduct}
        onClose={() => setModalOpen(false)}
        onSuccess={() => void fetchList()}
      />
    </>
  );
}

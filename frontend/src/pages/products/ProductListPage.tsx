import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Button,
  Image,
  Input,
  Modal,
  Popconfirm,
  Select,
  Space,
  Table,
  Tag,
  Typography,
  message,
  type TablePaginationConfig,
} from 'antd';
import { AppstoreOutlined, PlusOutlined, SettingOutlined } from '@ant-design/icons';
import { categoriesApi } from '../../api/categories';
import { productsApi } from '../../api/products';
import { ApiError } from '../../api/client';
import CategoryFormModal from '../../components/CategoryFormModal';
import ProductFormModal from '../../components/ProductFormModal';
import type { Category, CategoryStatus } from '../../types/category';
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

  // Category management inside product page
  const [catManagerOpen, setCatManagerOpen] = useState(false);
  const [catItems, setCatItems] = useState<Category[]>([]);
  const [catLoading, setCatLoading] = useState(false);
  const [catFormOpen, setCatFormOpen] = useState(false);
  const [catFormMode, setCatFormMode] = useState<'create' | 'edit'>('create');
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);

  const fetchCategories = useCallback(async () => {
    setCatLoading(true);
    try {
      const result = await categoriesApi.list();
      setCatItems(result.items);
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    } finally {
      setCatLoading(false);
    }
  }, []);

  const openCatManager = () => {
    void fetchCategories();
    setCatManagerOpen(true);
  };

  useEffect(() => {
    void categoriesApi.list().then((res) => setCategories(res.items));
  }, []);

  // Refresh category options when manager modal closes
  useEffect(() => {
    if (!catManagerOpen) {
      void categoriesApi.list().then((res) => setCategories(res.items));
    }
  }, [catManagerOpen]);

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

  // Category operations
  const openCatCreate = () => {
    setCatFormMode('create');
    setEditingCategory(null);
    setCatFormOpen(true);
  };

  const openCatEdit = (record: Category) => {
    setCatFormMode('edit');
    setEditingCategory(record);
    setCatFormOpen(true);
  };

  const toggleCatStatus = async (record: Category, newStatus: CategoryStatus) => {
    try {
      await categoriesApi.update(record.id, {
        name: record.name,
        sort: record.sort,
        status: newStatus,
      });
      message.success(newStatus === 'active' ? '已启用' : '已禁用');
      void fetchCategories();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    }
  };

  const handleCatDelete = async (record: Category) => {
    try {
      await categoriesApi.delete(record.id);
      message.success('删除成功');
      void fetchCategories();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
    }
  };

  const catColumns = [
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
          <Button type="link" size="small" onClick={() => openCatEdit(record)}>
            编辑
          </Button>
          {record.status === 'active' ? (
            <Popconfirm
              title="确认禁用该分类？"
              description="禁用后 App 端该分类及下属商品不可见"
              onConfirm={() => void toggleCatStatus(record, 'disabled')}
            >
              <Button type="link" size="small" danger>
                禁用
              </Button>
            </Popconfirm>
          ) : (
            <Popconfirm
              title="确认启用该分类？"
              onConfirm={() => void toggleCatStatus(record, 'active')}
            >
              <Button type="link" size="small">
                启用
              </Button>
            </Popconfirm>
          )}
          <Popconfirm
            title="确认删除该分类？"
            description="仅无商品的分类可删除"
            onConfirm={() => void handleCatDelete(record)}
          >
            <Button type="link" size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

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
        <Space>
          <Button icon={<SettingOutlined />} onClick={openCatManager}>
            分类管理
          </Button>
          <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
            新增商品
          </Button>
        </Space>
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

      <Modal
        title={
          <Space>
            <AppstoreOutlined />
            <span>分类管理</span>
          </Space>
        }
        open={catManagerOpen}
        onCancel={() => setCatManagerOpen(false)}
        footer={null}
        destroyOnClose
        width={680}
      >
        <Space style={{ marginBottom: 16, width: '100%', justifyContent: 'flex-end' }}>
          <Button type="primary" icon={<PlusOutlined />} onClick={openCatCreate}>
            新增分类
          </Button>
        </Space>
        <Table
          rowKey="id"
          columns={catColumns}
          dataSource={catItems}
          loading={catLoading}
          pagination={false}
        />
        <CategoryFormModal
          open={catFormOpen}
          mode={catFormMode}
          category={editingCategory}
          onClose={() => setCatFormOpen(false)}
          onSuccess={() => void fetchCategories()}
        />
      </Modal>
    </>
  );
}

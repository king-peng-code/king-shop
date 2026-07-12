import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Button,
  Form,
  Input,
  Modal,
  Select,
  Space,
  Table,
  Tag,
  Typography,
  message,
  type TablePaginationConfig,
} from 'antd';
import { PlusOutlined, SearchOutlined, SettingOutlined } from '@ant-design/icons';
import { externalUsersApi } from '../../api/externalUsers';
import { configsApi } from '../../api/configs';
import { ApiError } from '../../api/client';
import type { ExternalUser } from '../../types/externalUser';

const providerLabels: Record<string, string> = {
  alipay: '支付宝',
  wechat: '微信',
  fake: '模拟',
};

const providerColors: Record<string, string> = {
  alipay: 'blue',
  wechat: 'green',
  fake: 'default',
};

function parseTagPresets(configString: string): string[] {
  return configString
    .split(',')
    .map((t) => t.trim())
    .filter(Boolean);
}

function formatTagPresets(tags: string[]): string {
  return tags.join(',');
}

export default function ExternalUserListPage() {
  const [items, setItems] = useState<ExternalUser[]>([]);
  const [loading, setLoading] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [tagPresets, setTagPresets] = useState<string[]>([]);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Edit modal
  const [modalOpen, setModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<ExternalUser | null>(null);
  const [form] = Form.useForm();

  // Tag management modal
  const [tagManagerOpen, setTagManagerOpen] = useState(false);
  const [editingTags, setEditingTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState('');

  const loadTagPresets = useCallback(async () => {
    try {
      const result = await configsApi.get();
      for (const group of result.groups) {
        if (group.name !== 'external_user') continue;
        for (const item of group.items) {
          if (item.key === 'tag_presets') {
            setTagPresets(parseTagPresets(item.value));
          }
        }
      }
    } catch {
      // silent
    }
  }, []);

  useEffect(() => {
    void loadTagPresets();
  }, [loadTagPresets]);

  const fetchList = useCallback(async () => {
    setLoading(true);
    try {
      const result = await externalUsersApi.list(keyword, page, perPage);
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
      setKeyword(value.trim());
      setPage(1);
    }, 300);
  };

  const openEdit = (record: ExternalUser) => {
    setEditingUser(record);
    form.setFieldsValue({
      name: record.name ?? '',
      phone: record.phone ?? '',
      tags: record.tags,
    });
    setModalOpen(true);
  };

  const handleUpdate = async () => {
    if (!editingUser) return;
    const values = await form.validateFields();
    try {
      await externalUsersApi.update(editingUser.id, {
        name: values.name || null,
        phone: values.phone || null,
        tags: values.tags ?? [],
      });
      message.success('保存成功');
      setModalOpen(false);
      void fetchList();
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
      else message.error('操作失败');
    }
  };

  // Tag management
  const openTagManager = async () => {
    await loadTagPresets();
    setEditingTags([...tagPresets]);
    setTagInput('');
    setTagManagerOpen(true);
  };

  const addTag = () => {
    const trimmed = tagInput.trim();
    if (!trimmed) return;
    if (editingTags.includes(trimmed)) {
      message.warning('标签已存在');
      return;
    }
    setEditingTags([...editingTags, trimmed]);
    setTagInput('');
  };

  const removeTag = (tag: string) => {
    setEditingTags(editingTags.filter((t) => t !== tag));
  };

  const saveTags = async () => {
    try {
      await configsApi.update([
        { group: 'external_user', key: 'tag_presets', value: formatTagPresets(editingTags) },
      ]);
      message.success('标签已保存');
      setTagPresets(editingTags);
      setTagManagerOpen(false);
    } catch (e) {
      if (e instanceof ApiError) message.error(e.message);
      else message.error('保存失败');
    }
  };

  const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    {
      title: '姓名',
      dataIndex: 'name',
      key: 'name',
      render: (v: string | null) => v ?? '-',
    },
    {
      title: '手机号',
      dataIndex: 'phone',
      key: 'phone',
      render: (v: string | null) => v ?? '-',
    },
    {
      title: '代付渠道',
      dataIndex: 'provider',
      key: 'provider',
      render: (v: string) => (
        <Tag color={providerColors[v]}>{providerLabels[v] ?? v}</Tag>
      ),
    },
    {
      title: '标签',
      dataIndex: 'tags',
      key: 'tags',
      render: (tags: string[]) =>
        tags.length > 0
          ? tags.map((t) => <Tag key={t}>{t}</Tag>)
          : <span style={{ color: '#999' }}>-</span>,
    },
    {
      title: '操作',
      key: 'actions',
      render: (_: unknown, record: ExternalUser) => (
        <Button type="link" size="small" onClick={() => openEdit(record)}>
          编辑
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

  const tagPresetOptions = tagPresets.map((t) => ({ value: t, label: t }));

  return (
    <>
      <Space style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
        <Input.Search
          placeholder="搜索姓名 / 手机号"
          allowClear
          prefix={<SearchOutlined />}
          onChange={(e) => scheduleSearch(e.target.value)}
          onSearch={(v) => {
            setKeyword(v.trim());
            setPage(1);
          }}
          style={{ width: 280 }}
        />
        <Button icon={<SettingOutlined />} onClick={() => void openTagManager()}>
          标签类别管理
        </Button>
      </Space>

      <Table
        rowKey="id"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={pagination}
      />

      <Modal
        title="编辑代付人"
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => void handleUpdate()}
        destroyOnClose
        width={500}
      >
        <Form form={form} layout="vertical">
          <Form.Item label="姓名" name="name">
            <Input placeholder="输入姓名" />
          </Form.Item>
          <Form.Item
            label="手机号"
            name="phone"
            rules={[
              {
                pattern: /^1\d{10}$/,
                message: '请输入 11 位手机号',
              },
            ]}
          >
            <Input placeholder="输入手机号" />
          </Form.Item>
          <Form.Item label="标签" name="tags">
            <Select
              mode="tags"
              placeholder="输入标签后回车"
              options={tagPresetOptions}
            />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="标签类别管理"
        open={tagManagerOpen}
        onCancel={() => setTagManagerOpen(false)}
        onOk={() => void saveTags()}
        destroyOnClose
        width={500}
      >
        <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
          管理所有代付人的默认标签，编辑时可直接从预设中选择。
        </Typography.Text>

        <Space.Compact style={{ width: '100%', marginBottom: 16 }}>
          <Input
            value={tagInput}
            onChange={(e) => setTagInput(e.target.value)}
            onPressEnter={() => addTag()}
            placeholder="输入新标签名称后回车"
          />
          <Button type="primary" icon={<PlusOutlined />} onClick={() => addTag()}>
            添加
          </Button>
        </Space.Compact>

        <div style={{ minHeight: 100 }}>
          {editingTags.length === 0 ? (
            <Typography.Text type="secondary">暂无标签，请在上方添加</Typography.Text>
          ) : (
            editingTags.map((t) => (
              <Tag
                key={t}
                closable
                onClose={() => removeTag(t)}
                style={{ marginBottom: 8, fontSize: 14, padding: '2px 8px' }}
              >
                {t}
              </Tag>
            ))
          )}
        </div>
      </Modal>
    </>
  );
}

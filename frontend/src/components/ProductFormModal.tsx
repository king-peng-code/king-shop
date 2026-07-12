import { useEffect, useState } from 'react';
import { Form, Input, InputNumber, Modal, Select, message } from 'antd';
import { categoriesApi } from '../api/categories';
import { productsApi } from '../api/products';
import { ApiError } from '../api/client';
import ImageUpload, { type ImageUploadValue } from './ImageUpload';
import type { Category } from '../types/category';
import type { Product } from '../types/product';
import { fenToYuan, yuanToFen } from '../utils/price';
import { resolveMediaUrl } from '../utils/mediaUrl';

interface Props {
  open: boolean;
  mode: 'create' | 'edit';
  product?: Product | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function ProductFormModal({
  open,
  mode,
  product,
  onClose,
  onSuccess,
}: Props) {
  const [form] = Form.useForm();
  const [categories, setCategories] = useState<Category[]>([]);
  const [imageChanged, setImageChanged] = useState(false);

  useEffect(() => {
    if (!open) return;
    void categoriesApi.list().then((res) => setCategories(res.items));
  }, [open]);

  useEffect(() => {
    if (!open) return;
    setImageChanged(false);
    if (mode === 'edit' && product) {
      form.setFieldsValue({
        category_id: product.category_id,
        name: product.name,
        description: product.description ?? '',
        price: Number(fenToYuan(product.price)),
        sort: product.sort,
        cover: product.image_url
          ? {
              uploadId: product.upload_id,
              previewUrl: resolveMediaUrl(product.image_url),
            }
          : null,
      });
    } else {
      form.resetFields();
      form.setFieldsValue({ sort: 0, cover: null });
    }
  }, [open, mode, product, form]);

  const handleSubmit = async () => {
    const values = await form.validateFields();
    const cover = values.cover as ImageUploadValue | null | undefined;

    try {
      if (mode === 'create') {
        const payload: Parameters<typeof productsApi.create>[0] = {
          category_id: values.category_id,
          name: values.name,
          description: values.description || null,
          price: yuanToFen(values.price),
          sort: values.sort,
          status: 'on_sale',
        };
        if (cover?.uploadId) {
          payload.upload_id = cover.uploadId;
        }
        await productsApi.create(payload);
        message.success('创建成功');
      } else if (product) {
        const payload: Parameters<typeof productsApi.update>[1] = {
          category_id: values.category_id,
          name: values.name,
          description: values.description || null,
          price: yuanToFen(values.price),
          status: product.status,
          sort: values.sort,
        };
        if (imageChanged && cover?.uploadId) {
          payload.upload_id = cover.uploadId;
        }
        await productsApi.update(product.id, payload);
        message.success('保存成功');
      }
      onSuccess();
      onClose();
    } catch (e) {
      if (e instanceof ApiError && e.errors) {
        const fields = Object.entries(e.errors).map(([name, msgs]) => ({
          name,
          errors: msgs,
        }));
        form.setFields(fields);
      } else if (e instanceof ApiError) {
        message.error(e.message);
      } else {
        message.error('操作失败');
      }
    }
  };

  return (
    <Modal
      title={mode === 'create' ? '新增商品' : '编辑商品'}
      open={open}
      onCancel={onClose}
      onOk={() => void handleSubmit()}
      destroyOnClose
      width={560}
    >
      <Form form={form} layout="vertical">
        <Form.Item
          label="分类"
          name="category_id"
          rules={[{ required: true, message: '请选择分类' }]}
        >
          <Select
            options={categories.map((c) => ({
              value: c.id,
              label: c.status === 'disabled' ? `${c.name}（已禁用）` : c.name,
            }))}
            placeholder="选择分类"
          />
        </Form.Item>
        <Form.Item
          label="名称"
          name="name"
          rules={[{ required: true, message: '请输入商品名称' }]}
        >
          <Input maxLength={200} />
        </Form.Item>
        <Form.Item label="描述" name="description">
          <Input.TextArea rows={3} />
        </Form.Item>
        <Form.Item
          label="价格（元）"
          name="price"
          rules={[
            { required: true, message: '请输入价格' },
            { type: 'number', min: 0.01, message: '价格必须大于 0' },
          ]}
        >
          <InputNumber min={0.01} precision={2} style={{ width: '100%' }} />
        </Form.Item>
        <Form.Item label="封面" name="cover">
          <ImageUpload onImageChange={() => setImageChanged(true)} />
        </Form.Item>
        <Form.Item
          label="排序"
          name="sort"
          rules={[{ required: true }]}
          extra="数字越小越靠前"
        >
          <InputNumber min={0} style={{ width: '100%' }} />
        </Form.Item>
      </Form>
    </Modal>
  );
}

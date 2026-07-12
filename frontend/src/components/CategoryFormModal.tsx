import { useEffect } from 'react';
import { Form, Input, InputNumber, Modal, message } from 'antd';
import { categoriesApi } from '../api/categories';
import { ApiError } from '../api/client';
import type { Category } from '../types/category';

interface Props {
  open: boolean;
  mode: 'create' | 'edit';
  category?: Category | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function CategoryFormModal({
  open,
  mode,
  category,
  onClose,
  onSuccess,
}: Props) {
  const [form] = Form.useForm();

  useEffect(() => {
    if (!open) return;
    if (mode === 'edit' && category) {
      form.setFieldsValue({
        name: category.name,
        sort: category.sort,
      });
    } else {
      form.resetFields();
      form.setFieldsValue({ sort: 0 });
    }
  }, [open, mode, category, form]);

  const handleSubmit = async () => {
    const values = await form.validateFields();
    try {
      if (mode === 'create') {
        await categoriesApi.create({
          name: values.name,
          sort: values.sort,
          status: 'active',
        });
        message.success('创建成功');
      } else if (category) {
        await categoriesApi.update(category.id, {
          name: values.name,
          sort: values.sort,
          status: category.status,
        });
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
      title={mode === 'create' ? '新增分类' : '编辑分类'}
      open={open}
      onCancel={onClose}
      onOk={() => void handleSubmit()}
      destroyOnClose
      width={480}
    >
      <Form form={form} layout="vertical">
        <Form.Item
          label="名称"
          name="name"
          rules={[{ required: true, message: '请输入分类名称' }]}
        >
          <Input maxLength={100} />
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

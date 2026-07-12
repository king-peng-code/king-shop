import { useMemo } from 'react';
import { Button, Form, Input, InputNumber, Select, message } from 'antd';
import { configsApi } from '../api/configs';
import { ApiError } from '../api/client';
import { getFieldMeta, getFieldExtra, isFieldVisible, sortConfigItems } from '../config/configFieldMeta';
import type { ConfigGroup } from '../types/config';
import type { Role } from '../types/employee';

interface ConfigGroupFormProps {
  group: ConfigGroup;
  userRole: Role;
  onSaved: (groups: ConfigGroup[]) => void;
}

function buildInitialValues(group: ConfigGroup): Record<string, string> {
  const values: Record<string, string> = {};
  for (const item of group.items) {
    values[item.key] = item.value;
  }
  return values;
}

export function ConfigGroupForm({
  group,
  userRole,
  onSaved,
}: ConfigGroupFormProps) {
  const [form] = Form.useForm<Record<string, string>>();
  const initialValues = useMemo(() => buildInitialValues(group), [group]);

  const watchedValues = Form.useWatch([], form) ?? initialValues;

  const visibilityContext = useMemo(() => {
    const ctx: Record<string, string> = { ...initialValues, ...watchedValues };
    for (const [k, v] of Object.entries(ctx)) {
      ctx[`${group.name}.${k}`] = v;
    }
    return ctx;
  }, [group.name, initialValues, watchedValues]);

  const visibleItems = useMemo(() => {
    const filtered = group.items.filter((item) =>
      isFieldVisible(group.name, item.key, visibilityContext),
    );
    return sortConfigItems(group.name, filtered);
  }, [group.items, group.name, visibilityContext]);

  const handleSubmit = async (values: Record<string, unknown>) => {
    const editableItems = visibleItems.filter((item) => {
      if (item.is_readonly) {
        return false;
      }
      if (item.is_sensitive && userRole !== 'super_admin') {
        return false;
      }
      return true;
    });

    const configs = editableItems.map((item) => ({
      group: group.name,
      key: item.key,
      value:
        item.key in values
          ? String(values[item.key] ?? '')
          : String(item.value ?? ''),
    }));

    try {
      const result = await configsApi.update(configs);
      message.success('保存成功');
      const updated = result.groups.find((g) => g.name === group.name);
      if (updated) {
        form.setFieldsValue(buildInitialValues(updated));
      }
      onSaved(result.groups);
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.code === 403) {
          message.error('无权修改敏感配置');
          return;
        }
        if (error.status === 422 && error.errors) {
          const first = Object.values(error.errors)[0]?.[0];
          message.error(first ?? '校验失败');
          return;
        }
        message.error(error.message);
        return;
      }
      message.error('网络异常，请重试');
    }
  };

  const renderField = (item: (typeof group.items)[number]) => {
    const meta = getFieldMeta(group.name, item.key, item.is_sensitive);
    const readOnlySensitive =
      item.is_sensitive && userRole !== 'super_admin';
    const readOnly = item.is_readonly === true || readOnlySensitive;

    const label = item.description ?? item.key;

    if (readOnly) {
      return <Input disabled value={item.is_sensitive ? '****' : item.value} />;
    }

    switch (meta.type) {
      case 'select':
        return (
          <Select
            options={meta.options}
            placeholder={`请选择${label}`}
          />
        );
      case 'number':
        return (
          <InputNumber
            min={meta.min}
            max={meta.max}
            style={{ width: '100%' }}
          />
        );
      case 'textarea':
        return <Input.TextArea rows={meta.rows ?? 4} />;
      case 'password':
        return <Input.Password placeholder={`请输入${label}`} />;
      default:
        return <Input placeholder={`请输入${label}`} />;
    }
  };

  return (
    <Form
      form={form}
      layout="vertical"
      initialValues={initialValues}
      onFinish={handleSubmit}
    >
      {visibleItems.map((item) => (
        <Form.Item
          key={item.key}
          name={item.key}
          label={item.description ?? item.key}
          extra={getFieldExtra(group.name, item.key)}
          rules={[
            {
              required: !item.is_sensitive && !item.is_readonly,
              message: '此项不能为空',
            },
          ]}
        >
          {renderField(item)}
        </Form.Item>
      ))}

      <Form.Item>
        <Button type="primary" htmlType="submit">
          保存
        </Button>
      </Form.Item>
    </Form>
  );
}

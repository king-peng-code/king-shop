import { useMemo } from 'react';
import { Button, Form, Input, InputNumber, Select, Switch, message } from 'antd';
import { configsApi } from '../api/configs';
import { ApiError } from '../api/client';
import {
  getFieldMeta,
  getFieldExtra,
  getPaymentSubGroups,
  isFieldVisible,
  sortConfigItems,
} from '../config/configFieldMeta';
import type { ConfigGroup } from '../types/config';
import type { Role } from '../types/employee';

interface ConfigGroupFormProps {
  group: ConfigGroup;
  userRole: Role;
  onSaved: (groups: ConfigGroup[]) => void;
}

function buildInitialValues(group: ConfigGroup): Record<string, string | boolean> {
  const values: Record<string, string | boolean> = {};
  for (const item of group.items) {
    const meta = getFieldMeta(group.name, item.key, item.is_sensitive);
    if (meta.type === 'switch') {
      values[item.key] = item.value === '1';
    } else {
      values[item.key] = item.value;
    }
  }
  return values;
}

export function ConfigGroupForm({
  group,
  userRole,
  onSaved,
}: ConfigGroupFormProps) {
  const [form] = Form.useForm<Record<string, string | boolean>>();
  const initialValues = useMemo(() => buildInitialValues(group), [group]);

  const watchedValues = (Form.useWatch([], form) ?? initialValues) as Record<string, string | boolean>;

  const visibilityContext = useMemo(() => {
    const ctx: Record<string, string> = {};
    const merged = { ...initialValues, ...watchedValues };
    for (const [k, v] of Object.entries(merged)) {
      const strVal = typeof v === 'boolean' ? (v ? '1' : '0') : String(v ?? '');
      ctx[k] = strVal;
      ctx[`${group.name}.${k}`] = strVal;
    }
    return ctx;
  }, [group.name, initialValues, watchedValues]);

  const visibleItems = useMemo(() => {
    const filtered = group.items.filter((item) =>
      isFieldVisible(group.name, item.key, visibilityContext),
    );
    return sortConfigItems(group.name, filtered);
  }, [group.items, group.name, visibilityContext]);

  const subGroups =
    group.name === 'payment' ? getPaymentSubGroups(visibleItems) : undefined;

  const handleSubmit = async (values: Record<string, string | boolean>) => {
    const editableItems = visibleItems.filter((item) => {
      if (item.is_readonly) {
        return false;
      }
      if (item.is_sensitive && userRole !== 'super_admin') {
        return false;
      }
      return true;
    });

    const configs = editableItems.map((item) => {
      const rawValue = item.key in values ? values[item.key] : item.value;
      const value =
        rawValue === undefined || rawValue === null
          ? ''
          : typeof rawValue === 'boolean'
            ? rawValue
              ? '1'
              : '0'
            : String(rawValue);
      return {
        group: group.name,
        key: item.key,
        value,
      };
    });

    try {
      const result = await configsApi.update(configs);
      message.success('保存成功');
      const updatedGroup = result.groups.find((g) => g.name === group.name);
      if (updatedGroup) {
        form.setFieldsValue(buildInitialValues(updatedGroup));
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
      case 'switch':
        return <Switch checked={item.value === '1'} />;
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
      {subGroups ? (
        // Render payment config in sub-groups with visual separation
        <>
          {subGroups.map((subGroup) => (
            <div key={subGroup.label} style={{ marginBottom: 24 }}>
              <h4
                style={{
                  fontSize: 14,
                  fontWeight: 600,
                  color: '#333',
                  marginBottom: 12,
                  paddingBottom: 8,
                  borderBottom: '1px solid #f0f0f0',
                }}
              >
                {subGroup.label}
              </h4>
              {subGroup.items.map((item) => (
                <Form.Item
                  key={item.key}
                  name={item.key}
                  label={item.description ?? item.key}
                  extra={getFieldExtra(group.name, item.key)}
                  valuePropName={
                    getFieldMeta(group.name, item.key, item.is_sensitive).type === 'switch'
                      ? 'checked'
                      : 'value'
                  }
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
            </div>
          ))}
        </>
      ) : (
        // Normal flat rendering for non-payment groups
        <>
          {visibleItems.map((item) => (
            <Form.Item
              key={item.key}
              name={item.key}
              label={item.description ?? item.key}
              extra={getFieldExtra(group.name, item.key)}
              valuePropName={
                getFieldMeta(group.name, item.key, item.is_sensitive).type === 'switch'
                  ? 'checked'
                  : 'value'
              }
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
        </>
      )}

      <Form.Item>
        <Button type="primary" htmlType="submit">
          保存
        </Button>
      </Form.Item>
    </Form>
  );
}

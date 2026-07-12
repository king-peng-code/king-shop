import { useEffect } from 'react';
import { Form, Input, Modal, Select, Switch, message } from 'antd';
import { employeesApi } from '../api/employees';
import { ApiError } from '../api/client';
import type { Employee, Role } from '../types/employee';
import { useAuth } from '../contexts/AuthContext';

interface Props {
  open: boolean;
  mode: 'create' | 'edit';
  employee?: Employee | null;
  onClose: () => void;
  onSuccess: () => void;
}

export default function EmployeeFormModal({
  open,
  mode,
  employee,
  onClose,
  onSuccess,
}: Props) {
  const [form] = Form.useForm();
  const { user: currentUser } = useAuth();
  const isSuperAdmin = currentUser?.role === 'super_admin';
  const isSelf = mode === 'edit' && employee?.id === currentUser?.id;

  useEffect(() => {
    if (!open) return;
    if (mode === 'edit' && employee) {
      form.setFieldsValue({
        name: employee.name,
        phone: employee.phone,
        employee_no: employee.employee_no ?? '',
        department: employee.department ?? '',
        role: employee.role,
        status: employee.status,
        reset_password: false,
      });
    } else {
      form.resetFields();
      form.setFieldsValue({ role: 'employee' });
    }
  }, [open, mode, employee, form]);

  const roleOptions: { value: Role; label: string }[] = isSuperAdmin
    ? [
        { value: 'employee', label: '员工' },
        { value: 'admin', label: '管理员' },
        { value: 'super_admin', label: '超级管理员' },
      ]
    : [{ value: 'employee', label: '员工' }];

  const handleSubmit = async () => {
    const values = await form.validateFields();
    try {
      if (mode === 'create') {
        await employeesApi.create({
          name: values.name,
          phone: values.phone,
          employee_no: values.employee_no || undefined,
          department: values.department || undefined,
          role: values.role,
        });
        message.success('创建成功，默认密码为 123456');
      } else if (employee) {
        await employeesApi.update(employee.id, {
          name: values.name,
          employee_no: values.employee_no || undefined,
          department: values.department || undefined,
          role: values.role,
          status: values.status,
          reset_password: values.reset_password || false,
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
      title={mode === 'create' ? '新增员工' : '编辑员工'}
      open={open}
      onCancel={onClose}
      onOk={() => void handleSubmit()}
      destroyOnClose
      width={480}
    >
      <Form form={form} layout="vertical">
        <Form.Item
          label="姓名"
          name="name"
          rules={[{ required: true, message: '请输入姓名' }]}
        >
          <Input />
        </Form.Item>
        {mode === 'create' && (
          <Form.Item
            label="手机号"
            name="phone"
            rules={[
              { required: true, message: '请输入手机号' },
              { pattern: /^1\d{10}$/, message: '请输入 11 位手机号' },
            ]}
          >
            <Input />
          </Form.Item>
        )}
        <Form.Item label="工号" name="employee_no">
          <Input />
        </Form.Item>
        <Form.Item label="部门" name="department">
          <Input />
        </Form.Item>
        <Form.Item label="角色" name="role" rules={[{ required: true }]}>
          <Select options={roleOptions} disabled={isSelf} />
        </Form.Item>
        {mode === 'edit' && (
          <>
            <Form.Item label="状态" name="status" rules={[{ required: true }]}>
              <Select
                disabled={isSelf}
                options={[
                  { value: 'active', label: '正常' },
                  { value: 'disabled', label: '已禁用' },
                ]}
              />
            </Form.Item>
            <Form.Item label="重置密码" name="reset_password" valuePropName="checked">
              <Switch />
            </Form.Item>
          </>
        )}
      </Form>
    </Modal>
  );
}

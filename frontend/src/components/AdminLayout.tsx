import { useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Layout, Menu, Button, Space, Tag, Typography } from 'antd';
import { TeamOutlined, LogoutOutlined, MenuFoldOutlined, MenuUnfoldOutlined } from '@ant-design/icons';
import { useAuth } from '../contexts/AuthContext';

const { Header, Sider, Content } = Layout;

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

export default function AdminLayout() {
  const [collapsed, setCollapsed] = useState(false);
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const selectedKey = location.pathname.startsWith('/employees') ? 'employees' : '';

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider collapsible collapsed={collapsed} trigger={null} theme="light">
        <div
          style={{
            height: 64,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontWeight: 600,
            fontSize: collapsed ? 14 : 16,
          }}
        >
          {collapsed ? 'KS' : 'King Shop'}
        </div>
        <Menu
          mode="inline"
          selectedKeys={[selectedKey]}
          items={[
            {
              key: 'employees',
              icon: <TeamOutlined />,
              label: '员工管理',
              onClick: () => navigate('/employees'),
            },
          ]}
        />
      </Sider>
      <Layout>
        <Header
          style={{
            padding: '0 24px',
            background: '#fff',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            borderBottom: '1px solid #f0f0f0',
          }}
        >
          <Button
            type="text"
            icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
            onClick={() => setCollapsed(!collapsed)}
          />
          <Space>
            <Typography.Text>{user?.name}</Typography.Text>
            {user && (
              <Tag color={roleColors[user.role]}>{roleLabels[user.role]}</Tag>
            )}
            <Button
              type="link"
              icon={<LogoutOutlined />}
              onClick={() => void logout()}
            >
              退出
            </Button>
          </Space>
        </Header>
        <Content style={{ margin: 24 }}>
          <Outlet />
        </Content>
      </Layout>
    </Layout>
  );
}

import { Navigate, useLocation } from 'react-router-dom';
import { Spin, Result, Button } from 'antd';
import { useAuth, isAdminRole } from '../contexts/AuthContext';

export function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user, token, loading, logout } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', paddingTop: 120 }}>
        <Spin size="large" />
      </div>
    );
  }

  if (!token || !user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (user.must_change_password && location.pathname !== '/change-password') {
    return <Navigate to="/change-password" replace />;
  }

  if (!isAdminRole(user.role)) {
    return (
      <Result
        status="403"
        title="无权访问"
        subTitle="您的账号没有管理后台权限"
        extra={
          <Button type="primary" onClick={() => void logout()}>
            退出登录
          </Button>
        }
      />
    );
  }

  return <>{children}</>;
}

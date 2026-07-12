import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { ConfigProvider } from 'antd';
import zhCN from 'antd/locale/zh_CN';
import { AuthProvider } from './contexts/AuthContext';
import { ProtectedRoute } from './components/ProtectedRoute';
import LoginPage from './pages/LoginPage';
import ChangePasswordPage from './pages/ChangePasswordPage';
import AdminLayout from './components/AdminLayout';
import EmployeeListPage from './pages/employees/EmployeeListPage';
import CategoryListPage from './pages/categories/CategoryListPage';
import ProductListPage from './pages/products/ProductListPage';
import OrderListPage from './pages/orders/OrderListPage';
import { SettingsPage } from './pages/settings/SettingsPage';

export default function App() {
  return (
    <ConfigProvider locale={zhCN}>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              path="/change-password"
              element={
                <ProtectedRoute>
                  <ChangePasswordPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <AdminLayout />
                </ProtectedRoute>
              }
            >
              <Route index element={<Navigate to="/employees" replace />} />
              <Route path="employees" element={<EmployeeListPage />} />
              <Route path="categories" element={<CategoryListPage />} />
              <Route path="products" element={<ProductListPage />} />
              <Route path="orders" element={<OrderListPage />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </ConfigProvider>
  );
}

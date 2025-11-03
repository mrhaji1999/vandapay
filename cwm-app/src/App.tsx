import { Navigate, Route, Routes } from 'react-router-dom';
import { useEffect } from 'react';
import { useAuth } from './store/auth';
import { LoginPage } from './pages/LoginPage';
import { RegisterCompanyPage } from './pages/RegisterCompanyPage';
import { RegisterMerchantPage } from './pages/RegisterMerchantPage';
import { ProtectedRoute } from './components/layout/ProtectedRoute';
import { AdminDashboard } from './pages/admin/AdminDashboard';
import { AdminTransactionsPage } from './pages/admin/AdminTransactionsPage';
import { AdminCategoriesPage } from './pages/admin/AdminCategoriesPage';
import { AdminMerchantsPage } from './pages/admin/AdminMerchantsPage';
import { CompanyDashboard } from './pages/company/CompanyDashboard';
import { MerchantDashboard } from './pages/merchant/MerchantDashboard';
import { EmployeeDashboard } from './pages/employee/EmployeeDashboard';

const RoleRedirect = () => {
  const { user } = useAuth();
  const role = user?.role || user?.roles?.[0];
  if (!role) {
    return <Navigate to="/login" replace />;
  }
  return <Navigate to={`/${role}`} replace />;
};

const App = () => {
  const { token, user, fetchProfile } = useAuth();

  useEffect(() => {
    if (token && !user) {
      void fetchProfile();
    }
  }, [fetchProfile, token, user]);

  return (
    <Routes>
      <Route path="/" element={<RoleRedirect />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register/company" element={<RegisterCompanyPage />} />
      <Route path="/register/merchant" element={<RegisterMerchantPage />} />

      <Route element={<ProtectedRoute roles={['administrator']} />}>
        <Route path="/administrator" element={<Navigate to="/admin" replace />} />
        <Route path="/admin" element={<AdminDashboard />} />
        <Route path="/admin/merchants" element={<AdminMerchantsPage />} />
        <Route path="/admin/categories" element={<AdminCategoriesPage />} />
        <Route path="/admin/transactions" element={<AdminTransactionsPage />} />
      </Route>

      <Route element={<ProtectedRoute roles={['company']} />}>
        <Route path="/company" element={<CompanyDashboard />} />
      </Route>

      <Route element={<ProtectedRoute roles={['merchant']} />}>
        <Route path="/merchant" element={<MerchantDashboard />} />
      </Route>

      <Route element={<ProtectedRoute roles={['employee']} />}>
        <Route path="/employee" element={<EmployeeDashboard />} />
      </Route>

      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
};

export default App;

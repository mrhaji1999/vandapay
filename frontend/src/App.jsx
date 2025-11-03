import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './context/AuthContext.jsx';
import LoginPage from './pages/LoginPage.jsx';
import CompanyDashboard from './pages/CompanyDashboard.jsx';
import MerchantDashboard from './pages/MerchantDashboard.jsx';
import EmployeeDashboard from './pages/EmployeeDashboard.tsx';
import EmployeeTransactions from './pages/EmployeeTransactions.tsx';
import DashboardLayout from './components/layout/DashboardLayout.jsx';

function ProtectedRoute({ children, allowedRoles }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="page-loader">
        <div className="spinner" aria-hidden />
        <span>در حال بارگذاری...</span>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    return (
      <DashboardLayout>
        <div className="empty-state">
          <h2>دسترسی غیر مجاز</h2>
          <p>شما دسترسی لازم برای مشاهده این بخش را ندارید.</p>
        </div>
      </DashboardLayout>
    );
  }

  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/dashboard/company"
        element={
          <ProtectedRoute allowedRoles={["company"]}>
            <DashboardLayout pageTitle="داشبورد شرکت">
              <CompanyDashboard />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/merchant"
        element={
          <ProtectedRoute allowedRoles={["merchant"]}>
            <DashboardLayout pageTitle="داشبورد پذیرنده">
              <MerchantDashboard />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/employee"
        element={
          <ProtectedRoute allowedRoles={["employee"]}>
            <DashboardLayout pageTitle="داشبورد کارمند">
              <EmployeeDashboard />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/employee/transactions"
        element={
          <ProtectedRoute allowedRoles={["employee"]}>
            <DashboardLayout pageTitle="سوابق خرید کارمند">
              <EmployeeTransactions />
            </DashboardLayout>
          </ProtectedRoute>
        }
      />
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}

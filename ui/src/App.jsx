import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, Link } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import LoginPage from './pages/LoginPage';
import CompanyPanel from './pages/CompanyPanel';
import MerchantPanel from './pages/MerchantPanel';
import EmployeePanel from './pages/EmployeePanel';
import ProtectedRoute from './components/ProtectedRoute';

// A placeholder for a dashboard component that would handle role-based redirection.
const Dashboard = () => {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-screen">
                <p className="text-lg font-medium">Loading your dashboard…</p>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    const roles = user.roles || [];
    const isAdmin = roles.includes('administrator');

    const panelOptions = [
        { role: 'company', path: '/company', label: 'پنل شرکت' },
        { role: 'merchant', path: '/merchant', label: 'پنل پذیرنده' },
        { role: 'employee', path: '/employee', label: 'پنل کارمند' },
    ].filter(({ role }) => roles.includes(role) || isAdmin);

    if (panelOptions.length === 0) {
        return (
            <div className="flex items-center justify-center h-screen">
                <p className="text-lg font-medium">هیچ پنل فعالی برای نقش شما تعریف نشده است.</p>
            </div>
        );
    }

    if (panelOptions.length === 1) {
        return <Navigate to={panelOptions[0].path} replace />;
    }

    return (
        <div className="flex items-center justify-center min-h-screen bg-gray-100">
            <div className="w-full max-w-xl p-8 space-y-6 bg-white rounded-lg shadow">
                <h1 className="text-2xl font-bold text-center">یک پنل را انتخاب کنید</h1>
                <p className="text-center text-gray-600">
                    براساس نقش‌های شما می‌توانید هر یک از پنل‌های زیر را مشاهده کنید.
                </p>
                <div className="grid gap-4 md:grid-cols-2">
                    {panelOptions.map(({ path, label }) => (
                        <Link
                            key={path}
                            to={path}
                            className="block p-4 text-center font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
                        >
                            {label}
                        </Link>
                    ))}
                </div>
            </div>
        </div>
    );
};

function App() {
    return (
        <AuthProvider>
            <Router>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route
                        path="/dashboard"
                        element={
                            <ProtectedRoute>
                                <Dashboard />
                            </ProtectedRoute>
                        }
                    />
                    <Route
                        path="/company"
                        element={
                            <ProtectedRoute allowedRoles={['company', 'administrator']}>
                                <CompanyPanel />
                            </ProtectedRoute>
                        }
                    />
                     <Route
                        path="/merchant"
                        element={
                            <ProtectedRoute allowedRoles={['merchant', 'administrator']}>
                                <MerchantPanel />
                            </ProtectedRoute>
                        }
                    />
                     <Route
                        path="/employee"
                        element={
                            <ProtectedRoute allowedRoles={['employee', 'administrator']}>
                                <EmployeePanel />
                            </ProtectedRoute>
                        }
                    />
                    <Route path="*" element={<Navigate to="/login" />} />
                </Routes>
            </Router>
        </AuthProvider>
    );
}

export default App;

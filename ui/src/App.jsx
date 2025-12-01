import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, Link } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import LoginPage from './pages/LoginPage';
import CompanyPanel from './pages/CompanyPanel';
import MerchantPanel from './pages/MerchantPanel';
import EmployeePanel from './pages/EmployeePanel';
import ProtectedRoute from './components/ProtectedRoute';
import PanelLayout from './components/PanelLayout';
import Button from './components/Button';

// A placeholder for a dashboard component that would handle role-based redirection.
const Dashboard = () => {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-[#050816] text-slate-200">
                <div className="space-y-3 text-center">
                    <span className="mx-auto block h-12 w-12 animate-spin rounded-full border-2 border-slate-700 border-t-sky-400" />
                    <p className="text-sm tracking-wide text-slate-400">در حال بارگذاری اطلاعات شما…</p>
                </div>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    const roles = user.roles || [];
    const isAdmin = roles.includes('administrator');

    const panelOptions = [
        {
            role: 'company',
            path: '/company',
            label: 'پنل شرکت',
            description: 'مدیریت کارکنان، بارگذاری فایل‌های CSV و تخصیص شارژ گروهی به کیف پول‌ها',
        },
        {
            role: 'merchant',
            path: '/merchant',
            label: 'پنل پذیرنده',
            description: 'جست‌وجوی لحظه‌ای کارمند، ثبت درخواست پرداخت و مدیریت تسویه‌ حساب‌های پذیرنده',
        },
        {
            role: 'employee',
            path: '/employee',
            label: 'پنل مشتری',
            description: 'بررسی درخواست‌های پرداخت در انتظار تایید، تایید رمز یکبار مصرف و رصد تراکنش‌ها',
        },
    ].filter(({ role }) => roles.includes(role) || isAdmin);

    if (panelOptions.length === 0) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-[#050816] text-slate-100">
                <div className="max-w-md rounded-3xl border border-white/10 bg-white/5 p-8 text-center shadow-[0_25px_55px_-35px_rgba(15,23,42,0.9)]">
                    <p className="text-lg font-semibold">هیچ پنل فعالی برای نقش شما تعریف نشده است.</p>
                    <p className="mt-3 text-sm text-slate-300">لطفاً با مدیر سیستم تماس بگیرید تا سطح دسترسی متناسب را برای شما فعال کند.</p>
                </div>
            </div>
        );
    }

    if (panelOptions.length === 1) {
        return <Navigate to={panelOptions[0].path} replace />;
    }

    return (
        <PanelLayout
            title="کدام فضای کاری را مدیریت می‌کنید؟"
            description="دسترسی شما به چندین سطح کیف‌پول فعال شده است. یکی از فضاهای کاری زیر را انتخاب کنید تا به سرعت وارد ابزارهای اختصاصی همان نقش شوید."
        >
            <div className="grid gap-6 sm:grid-cols-2">
                {panelOptions.map(({ path, label, description }) => (
                    <Link
                        key={path}
                        to={path}
                        className="group flex h-full flex-col justify-between rounded-3xl border border-white/10 bg-white/5 p-6 text-left shadow-[0_25px_55px_-35px_rgba(15,23,42,0.9)] transition hover:border-sky-400/50 hover:bg-white/10"
                    >
                        <div className="space-y-3">
                            <p className="text-sm uppercase tracking-[0.3em] text-slate-400">{label}</p>
                            <p className="text-base text-slate-200">{description}</p>
                        </div>
                        <Button variant="ghost" className="mt-6 justify-start px-0 text-sky-300 group-hover:text-white">
                            ورود به {label}
                        </Button>
                    </Link>
                ))}
            </div>
        </PanelLayout>
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

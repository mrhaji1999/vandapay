import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

export default function LoginPage() {
  const navigate = useNavigate();
  const { login, user, loading, logout } = useAuth();
  const [credentials, setCredentials] = useState({ username: '', password: '' });
  const [selectedRole, setSelectedRole] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const roleOptions = [
    { value: 'company', label: 'شرکت' },
    { value: 'merchant', label: 'پذیرنده' },
    { value: 'employee', label: 'کارمند' },
  ];

  if (loading) {
    return (
      <div className="page-loader">
        <div className="spinner" aria-hidden />
        <span>در حال بررسی حساب...</span>
      </div>
    );
  }

  if (user) {
    const redirectMap = {
      company: '/dashboard/company',
      merchant: '/dashboard/merchant',
      employee: '/dashboard/employee',
    };
    return <Navigate to={redirectMap[user.role] || '/dashboard/company'} replace />;
  }

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');

    if (!selectedRole) {
      setError('لطفاً نوع حساب خود را انتخاب کنید.');
      return;
    }

    setSubmitting(true);

    try {
      const user = await login(credentials);
      if (selectedRole && user?.role && user.role !== selectedRole) {
        setError('نوع حساب انتخاب‌شده با نقش کاربری شما مطابقت ندارد.');
        logout();
        return;
      }
      const redirectMap = {
        company: '/dashboard/company',
        merchant: '/dashboard/merchant',
        employee: '/dashboard/employee',
      };
      navigate(redirectMap[user.role] || '/dashboard/company');
    } catch (err) {
      if (err?.response?.data?.message) {
        setError(err.response.data.message);
      } else if (err?.message === 'Network Error') {
        setError('ارتباط با سرور برقرار نشد. لطفاً تنظیمات دسترسی و CORS را بررسی کنید.');
      } else {
        setError('ورود ناموفق بود. لطفا اطلاعات را بررسی کنید.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="login-page">
      <form className="login-card" onSubmit={handleSubmit}>
        <h1>ورود به سامانه وندا پی</h1>
        <p>نام کاربری و رمز عبور وردپرس خود را وارد کنید.</p>
        <div className="role-selector" role="radiogroup" aria-label="انتخاب نوع حساب">
          <span className="role-selector__label">نوع حساب</span>
          <div className="role-selector__options">
            {roleOptions.map((role) => (
              <label
                key={role.value}
                className={`role-selector__option${selectedRole === role.value ? ' role-selector__option--active' : ''}`}
              >
                <input
                  type="radio"
                  name="accountRole"
                  value={role.value}
                  checked={selectedRole === role.value}
                  onChange={() => setSelectedRole(role.value)}
                />
                <span>{role.label}</span>
              </label>
            ))}
          </div>
          <p className="role-selector__hint">ابتدا نوع حساب خود را مشخص کنید تا به داشبورد مربوط هدایت شوید.</p>
        </div>
        <label>
          <span>نام کاربری</span>
          <input
            value={credentials.username}
            onChange={(event) => setCredentials((prev) => ({ ...prev, username: event.target.value }))}
          />
        </label>
        <label>
          <span>رمز عبور</span>
          <input
            type="password"
            value={credentials.password}
            onChange={(event) => setCredentials((prev) => ({ ...prev, password: event.target.value }))}
          />
        </label>
        {error && <div className="login-error">{error}</div>}
        <button className="primary" type="submit" disabled={submitting}>
          {submitting ? 'در حال ورود...' : 'ورود'}
        </button>
      </form>
    </div>
  );
}

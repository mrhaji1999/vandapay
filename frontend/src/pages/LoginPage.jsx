import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

export default function LoginPage() {
  const navigate = useNavigate();
  const { login, user, loading } = useAuth();
  const [credentials, setCredentials] = useState({ username: '', password: '' });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

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
    setSubmitting(true);

    try {
      const user = await login(credentials);
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

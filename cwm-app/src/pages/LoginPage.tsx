import axios from 'axios';
import { FormEvent, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import toast from 'react-hot-toast';
import { AuthLayout } from '../components/layout/AuthLayout';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Button } from '../components/ui/button';
import { apiClient } from '../api/client';
import { decodeRole, useAuth } from '../store/auth';

export const LoginPage = () => {
  const navigate = useNavigate();
  const { setToken, fetchProfile, setUser } = useAuth();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const username = String(form.get('username') ?? '');
    const password = String(form.get('password') ?? '');
    setLoading(true);

    try {
      const response = await apiClient.post<{ token: string }>('/token', { username, password });
      const token = response.data.token;
      setToken(token);
      const profile = await fetchProfile();
      const resolvedRole = decodeRole(token) ?? profile?.role ?? profile?.roles?.[0] ?? null;

      if (!profile && resolvedRole) {
        setUser({
          id: 0,
          username,
          email: '',
          role: resolvedRole,
          roles: [resolvedRole]
        });
      }

      if (!resolvedRole) {
        toast.error('نقش کاربر در توکن یافت نشد. لطفاً تنظیمات افزونه یا دسترسی‌های کاربر را بررسی کنید.');
        return;
      }

      toast.success('ورود با موفقیت انجام شد');
      navigate(`/${resolvedRole}`, { replace: true });
    } catch (error) {
      console.error(error);
      if (axios.isAxiosError(error)) {
        const status = error.response?.status;
        if (!error.response) {
          toast.error('امکان برقراری ارتباط با سرور وجود ندارد. لطفاً تنظیمات CORS را بررسی کنید.');
        } else if (status && status >= 500) {
          toast.error('سرور با خطای داخلی مواجه شد. لطفاً دقایقی دیگر تلاش کنید.');
        } else {
          toast.error('نام کاربری یا رمز عبور نامعتبر است.');
        }
      } else {
        toast.error('ورود با خطا مواجه شد.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      <form className="space-y-4" onSubmit={handleSubmit}>
        <div className="space-y-2">
          <Label htmlFor="username">نام کاربری</Label>
          <Input
            id="username"
            name="username"
            placeholder="نام کاربری خود را وارد کنید"
            required
            autoComplete="username"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="password">رمز عبور</Label>
          <Input
            id="password"
            name="password"
            type="password"
            placeholder="رمز عبور خود را وارد کنید"
            required
            autoComplete="current-password"
          />
        </div>
        <Button className="w-full" type="submit" disabled={loading}>
          {loading ? 'در حال ورود…' : 'ورود'}
        </Button>
        <div className="text-center text-sm">
          <p>
            حساب کاربری ندارید؟{' '}
            <Link className="text-primary underline" to="/register/company">
              ثبت‌نام شرکت
            </Link>{' '}
            کنید یا{' '}
            <Link className="text-primary underline" to="/register/merchant">
              ثبت‌نام پذیرنده
            </Link>{' '}
            انجام دهید.
          </p>
        </div>
      </form>
    </AuthLayout>
  );
};

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

      if (profile) {
        if (resolvedRole && resolvedRole !== profile.role) {
          setUser({
            ...profile,
            role: resolvedRole,
            roles: Array.from(new Set([resolvedRole, ...(profile.roles ?? [])]))
          });
        }
      } else if (resolvedRole) {
        setUser({
          id: 0,
          username,
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
      <form className="space-y-6" onSubmit={handleSubmit}>
        <div className="space-y-2">
          <Label htmlFor="username" className="text-sm font-semibold text-foreground">نام کاربری</Label>
          <Input
            id="username"
            name="username"
            placeholder="نام کاربری خود را وارد کنید"
            required
            autoComplete="username"
            className="h-12 text-base border-2 focus:border-primary transition-colors"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="password" className="text-sm font-semibold text-foreground">رمز عبور</Label>
          <Input
            id="password"
            name="password"
            type="password"
            placeholder="رمز عبور خود را وارد کنید"
            required
            autoComplete="current-password"
            className="h-12 text-base border-2 focus:border-primary transition-colors"
          />
        </div>
        <Button 
          className="w-full h-12 text-base font-semibold bg-gradient-primary hover:opacity-90 shadow-lg shadow-primary/20 transition-all" 
          type="submit" 
          disabled={loading}
        >
          {loading ? 'در حال ورود…' : 'ورود به سامانه'}
        </Button>
        <div className="text-center text-sm space-y-2 pt-4 border-t border-border">
          <p className="text-muted-foreground">
            حساب کاربری ندارید؟
          </p>
          <div className="flex gap-4 justify-center">
            <Link 
              className="text-primary font-semibold hover:underline transition-colors" 
              to="/register/company"
            >
              ثبت‌نام شرکت
            </Link>
            <span className="text-muted-foreground">|</span>
            <Link 
              className="text-primary font-semibold hover:underline transition-colors" 
              to="/register/merchant"
            >
              ثبت‌نام پذیرنده
            </Link>
          </div>
        </div>
      </form>
    </AuthLayout>
  );
};

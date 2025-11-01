import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { AuthLayout } from '../components/layout/AuthLayout';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Button } from '../components/ui/button';
import { apiClient } from '../api/client';

const FIELDS: { name: string; label: string; type?: string }[] = [
  { name: 'full_name', label: 'نام و نام خانوادگی' },
  { name: 'store_name', label: 'نام فروشگاه' },
  { name: 'store_address', label: 'آدرس فروشگاه' },
  { name: 'phone', label: 'تلفن ثابت' },
  { name: 'email', label: 'ایمیل', type: 'email' },
  { name: 'mobile', label: 'شماره همراه' },
  { name: 'password', label: 'رمز عبور', type: 'password' }
];

export const RegisterMerchantPage = () => {
  const navigate = useNavigate();
  const [formState, setFormState] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload: Record<string, string> = {};
    const persisted: Record<string, string> = {};
    for (const [key, value] of form.entries()) {
      const stringValue = String(value);
      payload[key] = stringValue;
      persisted[key] = stringValue;
    }
    setFormState(persisted);
    setLoading(true);
    try {
      await apiClient.post('/public/merchant/register', payload);
      toast.success('ثبت‌نام با موفقیت انجام شد. لطفاً وارد شوید.');
      navigate('/login', { replace: true });
    } catch (error) {
      const status = (error as any)?.response?.status;
      if (status === 500 || status === 404) {
        toast.error('خدمت ثبت‌نام موقتاً در دسترس نیست.');
      } else {
        const message = (error as any)?.response?.data?.message;
        toast.error(message ?? 'ثبت‌نام با خطا مواجه شد. لطفاً دوباره تلاش کنید.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      <form className="space-y-4" onSubmit={handleSubmit}>
        {FIELDS.map((field) => (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name}>{field.label}</Label>
            <Input
              id={field.name}
              name={field.name}
              type={field.type ?? 'text'}
              required
              defaultValue={formState[field.name] ?? ''}
            />
          </div>
        ))}
        <Button type="submit" className="w-full" disabled={loading}>
          {loading ? 'در حال ارسال…' : 'ارسال'}
        </Button>
      </form>
    </AuthLayout>
  );
};

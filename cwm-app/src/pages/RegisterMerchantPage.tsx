import { FormEvent, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { AuthLayout } from '../components/layout/AuthLayout';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Button } from '../components/ui/button';
import { apiClient } from '../api/client';
import { unwrapWordPressList } from '../api/wordpress';

const FIELDS: { name: string; label: string; type?: string }[] = [
  { name: 'full_name', label: 'نام و نام خانوادگی' },
  { name: 'store_name', label: 'نام فروشگاه' },
  { name: 'store_address', label: 'آدرس فروشگاه' },
  { name: 'phone', label: 'تلفن ثابت' },
  { name: 'email', label: 'ایمیل', type: 'email' },
  { name: 'mobile', label: 'شماره همراه' },
  { name: 'password', label: 'رمز عبور', type: 'password' }
];

interface Category {
  id: number;
  name: string;
  slug: string;
}

export const RegisterMerchantPage = () => {
  const navigate = useNavigate();
  const [formState, setFormState] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);
  const [selectedCategories, setSelectedCategories] = useState<number[]>([]);

  useEffect(() => {
    let isMounted = true;
    const fetchCategories = async () => {
      setCategoriesLoading(true);
      try {
        const response = await apiClient.get('/categories');
        const data = unwrapWordPressList<Record<string, unknown>>(response.data);
        if (isMounted) {
          setCategories(
            data.map((category) => ({
              id: Number(category.id ?? 0),
              name: String(category.name ?? ''),
              slug: String(category.slug ?? '')
            }))
          );
        }
      } catch (error) {
        console.error(error);
        if (isMounted) {
          toast.error('امکان دریافت دسته‌بندی‌ها وجود ندارد. لطفاً بعداً تلاش کنید.');
        }
      } finally {
        if (isMounted) {
          setCategoriesLoading(false);
        }
      }
    };

    void fetchCategories();

    return () => {
      isMounted = false;
    };
  }, []);

  const toggleCategory = (categoryId: number) => {
    setSelectedCategories((prev) =>
      prev.includes(categoryId) ? prev.filter((id) => id !== categoryId) : [...prev, categoryId]
    );
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (categories.length === 0) {
      toast.error('هیچ دسته‌بندی برای ثبت‌نام موجود نیست. لطفاً با مدیر سامانه تماس بگیرید.');
      return;
    }

    if (selectedCategories.length === 0) {
      toast.error('لطفاً حداقل یک دسته‌بندی را انتخاب کنید.');
      return;
    }

    const form = new FormData(event.currentTarget);
    const payload: Record<string, unknown> = {};
    const persisted: Record<string, string> = {};
    for (const [key, value] of form.entries()) {
      const stringValue = String(value);
      if (key === 'category_ids[]') {
        continue;
      }
      payload[key] = stringValue;
      persisted[key] = stringValue;
    }
    payload['category_ids'] = selectedCategories;
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
      <form className="space-y-5" onSubmit={handleSubmit}>
        {FIELDS.map((field) => (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name} className="text-sm font-semibold text-foreground">{field.label}</Label>
            <Input
              id={field.name}
              name={field.name}
              type={field.type ?? 'text'}
              required
              defaultValue={formState[field.name] ?? ''}
              className="h-12 text-base border-2 focus:border-primary transition-colors"
            />
          </div>
        ))}
        <div className="space-y-2">
          <Label className="text-sm font-semibold text-foreground">دسته‌بندی‌های پذیرنده</Label>
          <div className="space-y-3 rounded-xl border-2 border-border bg-secondary/50 p-4">
            {categoriesLoading ? (
              <p className="text-sm text-muted-foreground text-center py-4">در حال بارگذاری دسته‌بندی‌ها…</p>
            ) : categories.length > 0 ? (
              categories.map((category) => (
                <label 
                  key={category.id} 
                  className="flex items-center justify-between gap-3 p-3 rounded-lg hover:bg-card transition-colors cursor-pointer border border-border"
                >
                  <span className="text-sm font-medium text-foreground">{category.name}</span>
                  <input
                    type="checkbox"
                    name="category_ids[]"
                    value={category.id}
                    checked={selectedCategories.includes(category.id)}
                    onChange={() => toggleCategory(category.id)}
                    className="h-5 w-5 rounded border-2 border-primary text-primary focus:ring-2 focus:ring-primary/20 cursor-pointer"
                  />
                </label>
              ))
            ) : (
              <p className="text-sm text-muted-foreground text-center py-4">دسته‌بندی فعالی ثبت نشده است.</p>
            )}
          </div>
        </div>
        <Button 
          type="submit" 
          className="w-full h-12 text-base font-semibold bg-gradient-primary hover:opacity-90 shadow-lg shadow-primary/20 transition-all mt-6" 
          disabled={loading}
        >
          {loading ? 'در حال ارسال…' : 'ثبت درخواست'}
        </Button>
      </form>
    </AuthLayout>
  );
};

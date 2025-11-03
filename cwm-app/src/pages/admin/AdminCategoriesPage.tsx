import { FormEvent, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { apiClient } from '../../api/client';
import { unwrapWordPressList } from '../../api/wordpress';

interface CategoryRow {
  id: number;
  name: string;
  slug: string;
}

export const AdminCategoriesPage = () => {
  const queryClient = useQueryClient();
  const [name, setName] = useState('');

  const { data: categories = [], isLoading } = useQuery({
    queryKey: ['admin', 'categories'],
    queryFn: async () => {
      const response = await apiClient.get('/categories');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((category) => ({
        id: Number(category.id ?? 0),
        name: String(category.name ?? ''),
        slug: String(category.slug ?? '')
      }));
    }
  });

  const createMutation = useMutation({
    mutationFn: async (payload: { name: string }) => {
      await apiClient.post('/categories', payload);
    },
    onSuccess: () => {
      toast.success('دسته‌بندی جدید ثبت شد.');
      setName('');
      queryClient.invalidateQueries({ queryKey: ['admin', 'categories'] });
      queryClient.invalidateQueries({ queryKey: ['company', 'categories'] });
      queryClient.invalidateQueries({ queryKey: ['merchant', 'categories'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ثبت دسته‌بندی با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!name.trim()) {
      toast.error('نام دسته‌بندی را وارد کنید.');
      return;
    }
    createMutation.mutate({ name: name.trim() });
  };

  const tableRows = useMemo(() => {
    return categories.map((category: CategoryRow) => (
      <TableRow key={category.id}>
        <TableCell className="text-right">{category.id}</TableCell>
        <TableCell className="text-right">{category.name}</TableCell>
        <TableCell className="text-right font-mono text-xs text-muted-foreground">{category.slug}</TableCell>
      </TableRow>
    ));
  }, [categories]);

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-1">
          <CardHeader>
            <CardTitle>افزودن دسته‌بندی جدید</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={handleSubmit}>
              <div className="space-y-2">
                <label className="block text-sm font-medium text-right" htmlFor="category_name">
                  عنوان دسته‌بندی
                </label>
                <Input
                  id="category_name"
                  name="category_name"
                  placeholder="مثلاً دندانپزشکی"
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                />
              </div>
              <Button type="submit" className="w-full" disabled={createMutation.isPending}>
                {createMutation.isPending ? 'در حال ثبت…' : 'ذخیره دسته‌بندی'}
              </Button>
            </form>
            <p className="mt-4 text-xs leading-6 text-muted-foreground">
              پس از ایجاد دسته‌بندی، پذیرنده‌ها می‌توانند از طریق پنل خود آن را فعال کنند و شرکت‌ها قادر به تعیین سقف برای
              کارکنان خواهند بود.
            </p>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>لیست دسته‌بندی‌های تعریف‌شده</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>
            ) : categories.length === 0 ? (
              <p className="text-sm text-muted-foreground">هنوز دسته‌بندی‌ای ثبت نشده است.</p>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="text-right">شناسه</TableHead>
                      <TableHead className="text-right">عنوان</TableHead>
                      <TableHead className="text-right">نامک</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>{tableRows}</TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      </section>
    </DashboardLayout>
  );
};

export default AdminCategoriesPage;

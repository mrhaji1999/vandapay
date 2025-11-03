import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { apiClient } from '../../api/client';
import { useAuth } from '../../store/auth';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';

interface CompanyEmployee {
  id: number;
  name: string;
  national_id: string;
  balance: number;
  category_limits: CategoryLimit[];
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface CategoryLimit {
  category_id: number;
  category_name: string;
  limit: number;
  spent: number;
  remaining: number;
}

interface EmployeeLimitsResponse {
  employee_id: number;
  company_id: number;
  categories: CategoryLimit[];
}

interface CompanyProfile {
  id?: number;
  name?: string;
  display_name?: string;
  email?: string;
  company_name?: string;
  status_message?: string;
}

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
}

export const CompanyDashboard = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [editingEmployee, setEditingEmployee] = useState<CompanyEmployee | null>(null);
  const [limitForm, setLimitForm] = useState<Record<number, string>>({});

  const { data: profile } = useQuery({
    queryKey: ['company', 'profile'],
    queryFn: async () => {
      const response = await apiClient.get('/profile');
      return unwrapWordPressObject<CompanyProfile>(response.data);
    }
  });

  const { data: employees = [] } = useQuery({
    queryKey: ['company', 'employees', user?.email],
    queryFn: async () => {
      if (!user?.email) return [] as CompanyEmployee[];
      const response = await apiClient.get(`/admin/companies?email=${encodeURIComponent(user.email)}`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((employee) => ({
        id: Number(employee.id ?? 0),
        name: String(employee.name ?? ''),
        national_id: String(employee.national_id ?? ''),
        balance: Number(employee.balance ?? 0),
        category_limits: Array.isArray(employee.category_limits)
          ? (employee.category_limits as Record<string, unknown>[]).map((item) => ({
              category_id: Number(item.category_id ?? 0),
              category_name: String(item.category_name ?? ''),
              limit: Number(item.limit ?? 0),
              spent: Number(item.spent ?? 0),
              remaining: Number(item.remaining ?? 0)
            }))
          : []
      }));
    },
    enabled: Boolean(user?.email)
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['company', 'transactions'],
    queryFn: async () => {
      const response = await apiClient.get('/transactions/history');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((transaction) => ({
        id: Number(transaction.id ?? 0),
        type: String(transaction.type ?? ''),
        amount: Number(transaction.amount ?? 0),
        created_at: String(transaction.created_at ?? ''),
        description: transaction.description ? String(transaction.description) : undefined
      }));
    }
  });

  const { data: categories = [] } = useQuery({
    queryKey: ['company', 'categories'],
    queryFn: async () => {
      const response = await apiClient.get('/categories');
      const data = unwrapWordPressList<Record<string, unknown>>(response.data);
      return data.map((category) => ({
        id: Number(category.id ?? 0),
        name: String(category.name ?? ''),
        slug: String(category.slug ?? '')
      }));
    }
  });

  const { data: employeeLimits } = useQuery({
    queryKey: ['company', 'employee-limits', editingEmployee?.id],
    queryFn: async () => {
      if (!editingEmployee) return null;
      const response = await apiClient.get(`/company/employees/${editingEmployee.id}/limits`);
      const data = unwrapWordPressObject<EmployeeLimitsResponse>(response.data);
      if (!data) return null;
      return {
        employee_id: Number(data.employee_id ?? editingEmployee.id),
        categories: (data.categories ?? []).map((category) => ({
          category_id: Number(category.category_id ?? 0),
          category_name: String(category.category_name ?? ''),
          limit: Number(category.limit ?? 0),
          spent: Number(category.spent ?? 0),
          remaining: Number(category.remaining ?? 0)
        }))
      };
    },
    enabled: Boolean(editingEmployee)
  });

  useEffect(() => {
    if (employeeLimits) {
      const defaults: Record<number, string> = {};
      employeeLimits.categories.forEach((category) => {
        defaults[category.category_id] = category.limit.toString();
      });
      setLimitForm(defaults);
    } else if (!editingEmployee) {
      setLimitForm({});
    }
  }, [employeeLimits, editingEmployee]);

  const updateLimitsMutation = useMutation({
    mutationFn: async (payload: { employeeId: number; limits: { category_id: number; limit: number }[] }) => {
      await apiClient.post(`/company/employees/${payload.employeeId}/limits`, { limits: payload.limits });
    },
    onSuccess: () => {
      toast.success('سقف‌های دسته‌بندی با موفقیت به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['company', 'employees', user?.email] });
      if (editingEmployee) {
        queryClient.invalidateQueries({ queryKey: ['company', 'employee-limits', editingEmployee.id] });
      }
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی سقف‌ها با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const statusMessage = useMemo(() => {
    return (
      profile?.status_message ??
      'شرکت شما در انتظار تأیید مدیر است. پس از تأیید از طریق سامانه اطلاع‌رسانی می‌شود.'
    );
  }, [profile]);

  const formCategories = employeeLimits?.categories ?? categories.map((category) => ({
    category_id: category.id,
    category_name: category.name,
    limit: 0,
    spent: 0,
    remaining: 0
  }));

  const handleLimitChange = (categoryId: number, value: string) => {
    setLimitForm((prev) => ({
      ...prev,
      [categoryId]: value
    }));
  };

  const handleLimitSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!editingEmployee) return;

    const limits = formCategories.map((category) => ({
      category_id: category.category_id,
      limit: Number(limitForm[category.category_id] ?? 0)
    }));

    updateLimitsMutation.mutate({ employeeId: editingEmployee.id, limits });
  };

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>وضعیت شرکت</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">{statusMessage}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>اطلاعات حساب</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm">
              <span className="font-medium">شرکت:</span> {profile?.company_name ?? user?.name ?? '—'}
            </p>
            <p className="text-sm">
              <span className="font-medium">ایمیل:</span> {user?.email ?? '—'}
            </p>
          </CardContent>
        </Card>
      </section>

      {editingEmployee && (
        <section className="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>سقف استفاده برای {editingEmployee.name}</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-4" onSubmit={handleLimitSubmit}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {formCategories.map((category) => (
                    <div key={category.category_id} className="space-y-2 rounded-md border border-muted-foreground/20 p-3">
                      <Label htmlFor={`limit-${category.category_id}`}>{category.category_name}</Label>
                      <Input
                        id={`limit-${category.category_id}`}
                        type="number"
                        min={0}
                        step={1000}
                        value={limitForm[category.category_id] ?? ''}
                        onChange={(event) => handleLimitChange(category.category_id, event.target.value)}
                      />
                      <p className="text-xs text-muted-foreground">
                        مصرف‌شده: <span className="font-medium">{category.spent}</span> | باقی‌مانده:{' '}
                        <span className="font-medium">{category.remaining}</span>
                      </p>
                    </div>
                  ))}
                </div>
                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                  <Button type="button" variant="outline" onClick={() => setEditingEmployee(null)}>
                    انصراف
                  </Button>
                  <Button type="submit" disabled={updateLimitsMutation.isPending}>
                    {updateLimitsMutation.isPending ? 'در حال ذخیره…' : 'ذخیره سقف‌ها'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </section>
      )}

      <section className="mt-6">
        <h2 className="text-xl font-semibold">کارکنان</h2>
        <Table className="mt-4">
          <TableHeader>
            <TableRow>
              <TableHead>شناسه</TableHead>
              <TableHead>نام</TableHead>
              <TableHead>کد ملی</TableHead>
              <TableHead>موجودی</TableHead>
              <TableHead className="text-right">عملیات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {employees.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">
                  هنوز کارمندی برای شرکت شما ثبت نشده است.
                </TableCell>
              </TableRow>
            ) : (
              employees.map((employee) => (
                <TableRow key={employee.id}>
                  <TableCell>{employee.id}</TableCell>
                  <TableCell>{employee.name}</TableCell>
                  <TableCell>{employee.national_id}</TableCell>
                  <TableCell>{employee.balance}</TableCell>
                  <TableCell className="text-right">
                    <Button size="sm" variant="outline" onClick={() => setEditingEmployee(employee)}>
                      ویرایش سقف‌ها
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </section>

      <section className="mt-6">
        <h2 className="text-xl font-semibold">تراکنش‌ها</h2>
        <Table className="mt-4">
          <TableHeader>
            <TableRow>
              <TableHead>شناسه</TableHead>
              <TableHead>نوع</TableHead>
              <TableHead>مبلغ</TableHead>
              <TableHead>تاریخ</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {transactions.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  تراکنشی برای نمایش وجود ندارد.
                </TableCell>
              </TableRow>
            ) : (
              transactions.map((transaction) => (
                <TableRow key={transaction.id}>
                  <TableCell>{transaction.id}</TableCell>
                  <TableCell>{transaction.type}</TableCell>
                  <TableCell>{transaction.amount}</TableCell>
                  <TableCell>{transaction.created_at}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </section>
    </DashboardLayout>
  );
};

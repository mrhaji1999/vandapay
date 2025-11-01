import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { apiClient } from '../../api/client';
import { useAuth } from '../../store/auth';

interface CompanyEmployee {
  id: number;
  name: string;
  national_id: string;
  balance: number;
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

  const { data: profile } = useQuery({
    queryKey: ['company', 'profile'],
    queryFn: async () => (await apiClient.get('/profile')).data
  });

  const { data: employees = [] } = useQuery({
    queryKey: ['company', 'employees', user?.email],
    queryFn: async () => {
      if (!user?.email) return [] as CompanyEmployee[];
      const response = await apiClient.get<CompanyEmployee[]>(`/admin/companies?email=${encodeURIComponent(user.email)}`);
      return response.data;
    },
    enabled: Boolean(user?.email)
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['company', 'transactions'],
    queryFn: async () => (await apiClient.get<Transaction[]>('/transactions/history')).data
  });

  const statusMessage = useMemo(() => {
    return (
      profile?.status_message ??
      'شرکت شما در انتظار تأیید مدیر است. پس از تأیید از طریق سامانه اطلاع‌رسانی می‌شود.'
    );
  }, [profile]);

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

      <section>
        <h2 className="text-xl font-semibold">کارکنان</h2>
        <Table className="mt-4">
          <TableHeader>
            <TableRow>
              <TableHead>شناسه</TableHead>
              <TableHead>نام</TableHead>
              <TableHead>کد ملی</TableHead>
              <TableHead>موجودی</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {employees.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
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
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </section>

      <section>
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

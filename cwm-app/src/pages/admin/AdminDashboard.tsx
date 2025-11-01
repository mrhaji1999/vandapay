import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ResponsiveContainer, LineChart, Line, CartesianGrid, XAxis, YAxis, Tooltip } from 'recharts';
import { apiClient } from '../../api/client';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { DataTable, type Column } from '../../components/common/DataTable';
import { Button } from '../../components/ui/button';
import { DateRangePicker } from '../../components/common/DateRangePicker';
import { PayoutStatusBadge } from '../../components/common/PayoutStatusBadge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { exportToCsv } from '../../lib/csv';
import { useAuth } from '../../store/auth';
import { hasCapability } from '../../types/user';

interface StatsResponse {
  total_companies: number;
  total_merchants: number;
  total_payouts_pending: number;
  total_transactions: number;
  chart?: { label: string; value: number }[];
}

interface Company {
  id: number;
  title: string;
  status: string;
  meta?: {
    _cwm_company_type?: string;
    company_email?: string;
  };
}

interface Employee {
  id: number;
  name: string;
  balance: number;
  national_id?: string;
}

interface Merchant {
  id: number;
  name: string;
  wallet_balance: number;
  pending_payouts: number;
}

interface Payout {
  id: number;
  company?: string;
  merchant?: string;
  amount: number;
  status: string;
  created_at?: string;
}

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
}

export const AdminDashboard = () => {
  const { user } = useAuth();
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [dateFilter, setDateFilter] = useState<{ from?: string; to?: string }>({});

  const { data: stats } = useQuery({
    queryKey: ['admin', 'stats'],
    queryFn: async () => (await apiClient.get<StatsResponse>('/admin/stats')).data
  });

  const { data: companies = [] } = useQuery({
    queryKey: ['admin', 'companies'],
    queryFn: async () => (await apiClient.get<Company[]>('/admin/companies')).data
  });

  const { data: merchants = [] } = useQuery({
    queryKey: ['admin', 'merchants'],
    queryFn: async () => (await apiClient.get<Merchant[]>('/admin/merchants')).data
  });

  const { data: payouts = [] } = useQuery({
    queryKey: ['admin', 'payouts'],
    queryFn: async () => (await apiClient.get<Payout[]>('/admin/payouts')).data
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['admin', 'transactions', dateFilter],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (dateFilter.from) params.set('from', dateFilter.from);
      if (dateFilter.to) params.set('to', dateFilter.to);
      const response = await apiClient.get<Transaction[]>(`/admin/transactions?${params.toString()}`);
      return response.data;
    }
  });

  const { data: employees = [] } = useQuery({
    queryKey: ['admin', 'companies', selectedCompany?.id, 'employees'],
    queryFn: async () => {
      if (!selectedCompany) return [] as Employee[];
      const response = await apiClient.get<Employee[]>(`/admin/companies/${selectedCompany.id}/employees`);
      return response.data;
    },
    enabled: Boolean(selectedCompany?.id)
  });

  const companyColumns: Column<Company>[] = [
    { key: 'id', header: 'شناسه' },
    { key: 'title', header: 'عنوان / نام' },
    { key: 'status', header: 'وضعیت' },
    {
      key: 'type',
      header: 'نوع شرکت',
      render: (company) => company.meta?._cwm_company_type ?? '—'
    },
    {
      key: 'email',
      header: 'ایمیل شرکت',
      render: (company) => company.meta?.company_email ?? '—'
    },
    {
      key: 'actions',
      header: 'عملیات',
      render: (company) => (
        <Button size="sm" variant="outline" onClick={() => setSelectedCompany(company)}>
          مشاهده کارکنان
        </Button>
      )
    }
  ];

  const merchantColumns: Column<Merchant>[] = [
    { key: 'name', header: 'پذیرنده' },
    { key: 'wallet_balance', header: 'موجودی کیف پول' },
    { key: 'pending_payouts', header: 'تسویه‌های در انتظار' }
  ];

  const payoutColumns: Column<Payout>[] = [
    { key: 'id', header: 'شناسه' },
    { key: 'merchant', header: 'پذیرنده' },
    { key: 'company', header: 'شرکت' },
    { key: 'amount', header: 'مبلغ' },
    {
      key: 'status',
      header: 'وضعیت',
      render: (payout) => <PayoutStatusBadge status={payout.status} />
    }
  ];

  const transactionColumns: Column<Transaction>[] = [
    { key: 'id', header: 'شناسه' },
    { key: 'type', header: 'نوع' },
    { key: 'amount', header: 'مبلغ' },
    { key: 'created_at', header: 'تاریخ' },
    { key: 'description', header: 'توضیحات' }
  ];

  if (!hasCapability(user, 'manage_wallets')) {
    return (
      <DashboardLayout>
        <Card>
          <CardHeader>
            <CardTitle>دسترسی غیرمجاز</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">حساب کاربری شما مجوز مشاهده داشبورد مدیریت را ندارد.</p>
          </CardContent>
        </Card>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader>
            <CardTitle>تعداد کل شرکت‌ها</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_companies ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>تعداد کل پذیرندگان</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_merchants ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>تسویه‌های در انتظار</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_payouts_pending ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>مجموع تراکنش‌ها</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_transactions ?? 0}</CardContent>
        </Card>
      </section>

      <section>
        <h2 className="text-xl font-semibold">شرکت‌ها</h2>
        <DataTable data={companies} columns={companyColumns} searchPlaceholder="جست‌وجوی شرکت‌ها" />
      </section>

      {stats?.chart && stats.chart.length > 0 && (
        <section className="rounded-lg border bg-white p-6">
          <h2 className="text-xl font-semibold">نمای کلی فعالیت</h2>
          <p className="text-sm text-muted-foreground">شاخص‌های عملکرد استخراج‌شده از مسیر /admin/stats.</p>
          <div className="mt-4 h-72">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={stats.chart}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="label" />
                <YAxis />
                <Tooltip />
                <Line type="monotone" dataKey="value" stroke="#1f2937" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </section>
      )}

      {selectedCompany && (
        <section className="rounded-lg border bg-white p-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold">کارکنان {selectedCompany.title}</h3>
              <p className="text-sm text-muted-foreground">جزئیات موجودی کیف پول هر کارمند</p>
            </div>
            <Button variant="ghost" onClick={() => setSelectedCompany(null)}>
              بستن
            </Button>
          </div>
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
              {employees.map((employee) => (
                <TableRow key={employee.id}>
                  <TableCell>{employee.id}</TableCell>
                  <TableCell>{employee.name}</TableCell>
                  <TableCell>{employee.national_id ?? '—'}</TableCell>
                  <TableCell>{employee.balance}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </section>
      )}

      <section>
        <h2 className="text-xl font-semibold">پذیرندگان</h2>
        <DataTable data={merchants} columns={merchantColumns} searchPlaceholder="جست‌وجوی پذیرندگان" />
      </section>

      <section>
        <h2 className="text-xl font-semibold">درخواست‌های تسویه</h2>
        <DataTable data={payouts} columns={payoutColumns} searchPlaceholder="جست‌وجوی درخواست‌های تسویه" />
      </section>

      <section className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="text-xl font-semibold">تراکنش‌ها</h2>
            <p className="text-sm text-muted-foreground">فیلتر بر اساس تاریخ و دریافت خروجی CSV.</p>
          </div>
          <Button onClick={() => exportToCsv('transactions.csv', transactions)} variant="outline">
            دریافت CSV
          </Button>
        </div>
        <DateRangePicker onChange={setDateFilter} />
        <DataTable data={transactions} columns={transactionColumns} searchPlaceholder="جست‌وجوی تراکنش‌ها" />
      </section>
    </DashboardLayout>
  );
};

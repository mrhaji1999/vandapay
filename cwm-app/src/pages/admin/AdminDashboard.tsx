import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import toast from 'react-hot-toast';

interface NormalizedStats {
  total_companies: number;
  total_merchants: number;
  total_payouts_pending: number;
  total_transactions: number;
  total_balance?: number;
  total_wallets?: number;
  chart?: { label: string; value: number }[];
}

interface Company {
  id: number;
  title: string;
  status: string;
  company_type?: string;
  email?: string;
  phone?: string;
}

interface Employee {
  id: number;
  name: string;
  balance: number;
  national_id?: string;
  email?: string;
  phone?: string;
}

interface EmployeeImportSummary {
  processed: number;
  created: number;
  updated: number;
  balances_adjusted: number;
  errors: { row: number; message: string }[];
}

interface Merchant {
  id: number;
  name: string;
  balance: number;
  pending_payouts: number;
}

interface Payout {
  id: number;
  merchant_id?: number;
  bank_account?: string;
  amount: number;
  status: string;
  created_at?: string;
}

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  sender_id?: number;
  receiver_id?: number;
  status?: string;
}

export const AdminDashboard = () => {
  const { user } = useAuth();
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [dateFilter, setDateFilter] = useState<{ from?: string; to?: string }>({});
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [importSummary, setImportSummary] = useState<EmployeeImportSummary | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const queryClient = useQueryClient();

  const { data: stats } = useQuery({
    queryKey: ['admin', 'stats'],
    queryFn: async () => {
      const response = await apiClient.get('/admin/stats');
      const raw = unwrapWordPressObject<Record<string, unknown>>(response.data);

      if (!raw) {
        return undefined;
      }

      const totalMerchants = (raw.total_merchants ?? raw.total_wallets) as number | undefined;
      const pendingPayouts = (raw.total_payouts_pending ?? raw.pending_payout_sum) as number | undefined;
      const totalTransactions = raw.total_transactions as number | undefined;

      const chartData = Array.isArray((raw as Record<string, unknown>).chart)
        ? ((raw as Record<string, unknown>).chart as { label: string; value: number }[])
        : undefined;

      const normalized: NormalizedStats = {
        total_companies: Number(raw.total_companies ?? 0),
        total_merchants: typeof totalMerchants === 'number' ? totalMerchants : Number(raw.total_merchants ?? 0),
        total_payouts_pending: typeof pendingPayouts === 'number' ? pendingPayouts : Number(raw.total_payouts_pending ?? 0),
        total_transactions: typeof totalTransactions === 'number' ? totalTransactions : Number(raw.total_transactions ?? 0),
        total_balance: raw.total_balance !== undefined ? Number(raw.total_balance) : undefined,
        total_wallets: raw.total_wallets !== undefined ? Number(raw.total_wallets) : undefined,
        chart: chartData
      };

      return normalized;
    }
  });

  const { data: companies = [] } = useQuery({
    queryKey: ['admin', 'companies'],
    queryFn: async () => {
      const response = await apiClient.get('/admin/companies');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((company) => ({
        id: Number(company.id ?? 0),
        title: String(company.title ?? ''),
        status: String(company.status ?? ''),
        company_type: company.company_type ? String(company.company_type) : undefined,
        email: company.email ? String(company.email) : undefined,
        phone: company.phone ? String(company.phone) : undefined
      }));
    }
  });

  const { data: merchants = [] } = useQuery({
    queryKey: ['admin', 'merchants'],
    queryFn: async () => {
      const response = await apiClient.get('/admin/merchants');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((merchant) => ({
        id: Number(merchant.id ?? 0),
        name: String(merchant.name ?? ''),
        balance: Number(merchant.balance ?? merchant.wallet_balance ?? 0),
        pending_payouts: Number(merchant.pending_payouts ?? 0)
      }));
    }
  });

  const { data: payouts = [] } = useQuery({
    queryKey: ['admin', 'payouts'],
    queryFn: async () => {
      const response = await apiClient.get('/admin/payouts');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((payout) => ({
        id: Number(payout.id ?? 0),
        merchant_id: payout.merchant_id ? Number(payout.merchant_id) : undefined,
        bank_account: payout.bank_account ? String(payout.bank_account) : undefined,
        amount: Number(payout.amount ?? 0),
        status: String(payout.status ?? ''),
        created_at: payout.created_at ? String(payout.created_at) : undefined
      }));
    }
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['admin', 'transactions', dateFilter],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (dateFilter.from) params.set('from', dateFilter.from);
      if (dateFilter.to) params.set('to', dateFilter.to);
      const response = await apiClient.get(`/admin/transactions?${params.toString()}`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((transaction) => ({
        id: Number(transaction.id ?? 0),
        type: String(transaction.type ?? ''),
        amount: Number(transaction.amount ?? 0),
        created_at: String(transaction.created_at ?? ''),
        sender_id: transaction.sender_id ? Number(transaction.sender_id) : undefined,
        receiver_id: transaction.receiver_id ? Number(transaction.receiver_id) : undefined,
        status: transaction.status ? String(transaction.status) : undefined
      }));
    }
  });

  const { data: employees = [] } = useQuery({
    queryKey: ['admin', 'companies', selectedCompany?.id, 'employees'],
    queryFn: async () => {
      if (!selectedCompany) return [] as Employee[];
      const response = await apiClient.get(`/admin/companies/${selectedCompany.id}/employees`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((employee) => ({
        id: Number(employee.id ?? 0),
        name: String(employee.name ?? ''),
        balance: Number(employee.balance ?? 0),
        national_id: employee.national_id ? String(employee.national_id) : undefined,
        email: employee.email ? String(employee.email) : undefined,
        phone: employee.phone ? String(employee.phone) : undefined
      }));
    },
    enabled: Boolean(selectedCompany?.id)
  });

  const employeeImportMutation = useMutation({
    mutationFn: async ({ companyId, file }: { companyId: number; file: File }) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await apiClient.post(`/admin/companies/${companyId}/employees/import`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      return response.data as EmployeeImportSummary;
    },
    onSuccess: (data, variables) => {
      setImportSummary({
        processed: data.processed,
        created: data.created,
        updated: data.updated,
        balances_adjusted: data.balances_adjusted,
        errors: Array.isArray(data.errors) ? data.errors : []
      });
      toast.success('فایل کارکنان با موفقیت پردازش شد.');
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      setSelectedFile(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'companies', variables.companyId, 'employees'] });
    },
    onError: () => {
      toast.error('بارگذاری CSV کارکنان با خطا مواجه شد.');
    }
  });

  const companyColumns: Column<Company>[] = [
    { key: 'id', header: 'شناسه' },
    { key: 'title', header: 'عنوان / نام' },
    { key: 'status', header: 'وضعیت' },
    {
      key: 'company_type',
      header: 'نوع شرکت',
      render: (company) => company.company_type ?? '—'
    },
    {
      key: 'email',
      header: 'ایمیل شرکت',
      render: (company) => company.email ?? '—'
    },
    {
      key: 'actions',
      header: 'عملیات',
      render: (company) => (
        <Button
          size="sm"
          variant="outline"
          onClick={() => {
            setSelectedCompany(company);
            setImportSummary(null);
            setSelectedFile(null);
            if (fileInputRef.current) {
              fileInputRef.current.value = '';
            }
          }}
        >
          مشاهده کارکنان
        </Button>
      )
    }
  ];

  const merchantColumns: Column<Merchant>[] = [
    { key: 'name', header: 'پذیرنده' },
    {
      key: 'balance',
      header: 'موجودی کیف پول',
      render: (merchant) => merchant.balance.toLocaleString()
    },
    { key: 'pending_payouts', header: 'تسویه‌های در انتظار' }
  ];

  const payoutColumns: Column<Payout>[] = [
    { key: 'id', header: 'شناسه' },
    {
      key: 'merchant_id',
      header: 'شناسه پذیرنده',
      render: (payout) => payout.merchant_id ?? '—'
    },
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
    {
      key: 'status',
      header: 'وضعیت',
      render: (transaction) => transaction.status ?? '—'
    }
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
            <Button
              variant="ghost"
              onClick={() => {
                setSelectedCompany(null);
                setImportSummary(null);
                setSelectedFile(null);
                if (fileInputRef.current) {
                  fileInputRef.current.value = '';
                }
              }}
            >
              بستن
            </Button>
          </div>
          <form
            className="mt-4 space-y-4 rounded-md border border-dashed border-slate-200 p-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (!selectedCompany) {
                toast.error('ابتدا یک شرکت را انتخاب کنید.');
                return;
              }
              if (!selectedFile) {
                toast.error('لطفاً فایل CSV کارکنان را انتخاب کنید.');
                return;
              }
              employeeImportMutation.mutate({ companyId: selectedCompany.id, file: selectedFile });
            }}
          >
            <div className="space-y-2 text-right">
              <Label htmlFor="employee-csv">آپلود فایل CSV کارکنان</Label>
              <Input
                id="employee-csv"
                ref={fileInputRef}
                type="file"
                accept=".csv,text/csv"
                onChange={(event) => {
                  const file = event.target.files?.[0] ?? null;
                  setSelectedFile(file ?? null);
                }}
                disabled={employeeImportMutation.isPending}
              />
              <p className="text-xs text-muted-foreground">
                ستون‌های پشتیبانی‌شده: name, email, national_id, mobile, balance. ردیف اول باید عنوان ستون‌ها باشد.
              </p>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-4">
              <Button type="submit" disabled={employeeImportMutation.isPending}>
                {employeeImportMutation.isPending ? 'در حال پردازش...' : 'بارگذاری CSV'}
              </Button>
              {importSummary && (
                <div className="text-xs text-muted-foreground">
                  <span>ردیف‌ها: {importSummary.processed}</span>
                  <span className="mx-2">•</span>
                  <span>ایجاد شده: {importSummary.created}</span>
                  <span className="mx-2">•</span>
                  <span>به‌روزرسانی: {importSummary.updated}</span>
                  <span className="mx-2">•</span>
                  <span>تغییر موجودی: {importSummary.balances_adjusted}</span>
                </div>
              )}
            </div>
            {importSummary?.errors.length ? (
              <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-6 text-amber-900">
                <p className="font-semibold">برخی ردیف‌ها با خطا مواجه شدند:</p>
                <ul className="mt-1 list-disc space-y-1 pr-4">
                  {importSummary.errors.map((error) => (
                    <li key={`${error.row}-${error.message}`}>
                      ردیف {error.row}: {error.message}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </form>
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
              {employees.length === 0 && (
                <TableRow>
                  <TableCell colSpan={4} className="py-6 text-center text-sm text-muted-foreground">
                    برای این شرکت کارمندی ثبت نشده است.
                  </TableCell>
                </TableRow>
              )}
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

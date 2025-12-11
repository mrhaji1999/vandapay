import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ResponsiveContainer, LineChart, Line, BarChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend } from 'recharts';
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

interface OrderReports {
  total_online_orders: number;
  total_online_sales: number;
  total_in_person_sales: number;
  orders_by_status: Array<{ status: string; count: number }>;
  sales_by_merchant: Array<{
    merchant_id: number;
    store_name: string;
    order_count: number;
    total_sales: number;
  }>;
  sales_by_category: Array<{
    category_id: number;
    category_name: string;
    order_count: number;
    total_sales: number;
  }>;
}

interface ProductReports {
  total_products: number;
  products_by_category: Array<{
    category_id: number;
    category_name: string;
    product_count: number;
  }>;
  top_products: Array<{
    product_id: number;
    product_name: string;
    merchant_id: number;
    store_name: string;
    total_sold: number;
    total_revenue: number;
  }>;
}

interface Company {
  id: number;
  title: string;
  status: string;
  company_type?: string;
  email?: string;
  phone?: string;
  economic_code?: string;
  national_id?: string;
  user_id?: number;
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
  email?: string;
  store_name?: string;
  store_address?: string;
  phone?: string;
  mobile?: string;
}

interface AdminProduct {
  id: number;
  name: string;
  price: number;
  stock_quantity: number;
  status: string;
  merchant_id?: number;
  merchant_name?: string;
  merchant_email?: string;
  online_purchase_enabled?: boolean;
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
  const [bulkAmount, setBulkAmount] = useState<string>('');
  const [importSummary, setImportSummary] = useState<EmployeeImportSummary | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const queryClient = useQueryClient();
  const [companyForm, setCompanyForm] = useState({
    company_type: 'legal',
    company_name: '',
    company_email: '',
    company_phone: '',
    economic_code: '',
    national_id: '',
    password: ''
  });
  const [companyEditId, setCompanyEditId] = useState<number | null>(null);
  const [companyEditForm, setCompanyEditForm] = useState({
    title: '',
    email: '',
    phone: '',
    company_type: 'legal',
    economic_code: '',
    national_id: ''
  });
  const [merchantEditId, setMerchantEditId] = useState<number | null>(null);
  const [merchantForm, setMerchantForm] = useState({
    full_name: '',
    email: '',
    store_name: '',
    store_address: '',
    phone: '',
    mobile: ''
  });
  const [selectedProduct, setSelectedProduct] = useState<AdminProduct | null>(null);
  const [productForm, setProductForm] = useState({
    name: '',
    price: '',
    stock_quantity: '',
    status: 'active',
    online_purchase_enabled: false
  });
  const [userDeleteId, setUserDeleteId] = useState('');

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
        phone: company.phone ? String(company.phone) : undefined,
        economic_code: company.economic_code ? String(company.economic_code) : undefined,
        national_id: company.national_id ? String(company.national_id) : undefined,
        user_id: company.user_id ? Number(company.user_id) : undefined
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
        pending_payouts: Number(merchant.pending_payouts ?? 0),
        email: merchant.email ? String(merchant.email) : undefined,
        store_name: merchant.store_name ? String(merchant.store_name) : undefined,
        store_address: merchant.store_address ? String(merchant.store_address) : undefined,
        phone: merchant.phone ? String(merchant.phone) : undefined,
        mobile: merchant.mobile ? String(merchant.mobile) : undefined
      }));
    }
  });

  const { data: products = [] } = useQuery({
    queryKey: ['admin', 'products'],
    queryFn: async () => {
      const response = await apiClient.get('/admin/products');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((product) => ({
        id: Number(product.id ?? 0),
        name: String(product.name ?? ''),
        price: Number(product.price ?? 0),
        stock_quantity: Number(product.stock_quantity ?? 0),
        status: product.status ? String(product.status) : 'active',
        merchant_id: product.merchant_id ? Number(product.merchant_id) : undefined,
        merchant_name: product.merchant_name ? String(product.merchant_name) : undefined,
        merchant_email: product.merchant_email ? String(product.merchant_email) : undefined,
        online_purchase_enabled: Boolean(product.online_purchase_enabled)
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
    mutationFn: async ({ companyId, file, amount }: { companyId: number; file: File; amount?: string }) => {
      const formData = new FormData();
      formData.append('file', file);
      if (amount && amount.trim() !== '') {
        formData.append('amount', amount.trim());
      }
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

  const createCompanyMutation = useMutation({
    mutationFn: async () => {
      await apiClient.post('/admin/companies', companyForm);
    },
    onSuccess: () => {
      toast.success('شرکت جدید با موفقیت ثبت شد.');
      setCompanyForm({
        company_type: 'legal',
        company_name: '',
        company_email: '',
        company_phone: '',
        economic_code: '',
        national_id: '',
        password: ''
      });
      queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] });
    },
    onError: () => toast.error('ثبت شرکت جدید با خطا مواجه شد.')
  });

  const updateCompanyMutation = useMutation({
    mutationFn: async ({ companyId, payload }: { companyId: number; payload: Record<string, unknown> }) => {
      await apiClient.put(`/admin/companies/${companyId}`, payload);
    },
    onSuccess: () => {
      toast.success('اطلاعات شرکت به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] });
    },
    onError: () => toast.error('به‌روزرسانی شرکت با خطا مواجه شد.')
  });

  const deleteCompanyMutation = useMutation({
    mutationFn: async (companyId: number) => {
      await apiClient.delete(`/admin/companies/${companyId}`);
    },
    onSuccess: () => {
      toast.success('شرکت حذف شد.');
      setCompanyEditId(null);
      setCompanyEditForm({
        title: '',
        email: '',
        phone: '',
        company_type: 'legal',
        economic_code: '',
        national_id: ''
      });
      queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] });
    },
    onError: () => toast.error('حذف شرکت با خطا مواجه شد.')
  });

  const updateMerchantMutation = useMutation({
    mutationFn: async ({ merchantId, payload }: { merchantId: number; payload: Record<string, unknown> }) => {
      await apiClient.put(`/admin/merchants/${merchantId}`, payload);
    },
    onSuccess: () => {
      toast.success('اطلاعات پذیرنده به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['admin', 'merchants'] });
    },
    onError: () => toast.error('به‌روزرسانی پذیرنده با خطا مواجه شد.')
  });

  const deleteMerchantMutation = useMutation({
    mutationFn: async (merchantId: number) => {
      await apiClient.delete(`/admin/merchants/${merchantId}`);
    },
    onSuccess: () => {
      toast.success('پذیرنده حذف شد.');
      setMerchantEditId(null);
      setMerchantForm({
        full_name: '',
        email: '',
        store_name: '',
        store_address: '',
        phone: '',
        mobile: ''
      });
      queryClient.invalidateQueries({ queryKey: ['admin', 'merchants'] });
    },
    onError: () => toast.error('حذف پذیرنده با خطا مواجه شد.')
  });

  const updateProductMutation = useMutation({
    mutationFn: async ({ productId, payload }: { productId: number; payload: Record<string, unknown> }) => {
      await apiClient.put(`/admin/products/${productId}`, payload);
    },
    onSuccess: () => {
      toast.success('محصول به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['admin', 'products'] });
    },
    onError: () => toast.error('به‌روزرسانی محصول با خطا مواجه شد.')
  });

  const deleteProductMutation = useMutation({
    mutationFn: async (productId: number) => {
      await apiClient.delete(`/admin/products/${productId}`);
    },
    onSuccess: () => {
      toast.success('محصول حذف شد.');
      setSelectedProduct(null);
      queryClient.invalidateQueries({ queryKey: ['admin', 'products'] });
    },
    onError: () => toast.error('حذف محصول با خطا مواجه شد.')
  });

  const deleteUserMutation = useMutation({
    mutationFn: async (userId: number) => {
      await apiClient.delete(`/admin/users/${userId}`);
    },
    onSuccess: () => {
      toast.success('کاربر حذف شد.');
      setUserDeleteId('');
      queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'merchants'] });
    },
    onError: () => toast.error('حذف کاربر با خطا مواجه شد.')
  });

  const handleCompanySelection = (companyId: number | null) => {
    setCompanyEditId(companyId);
    if (!companyId) {
      setCompanyEditForm({
        title: '',
        email: '',
        phone: '',
        company_type: 'legal',
        economic_code: '',
        national_id: ''
      });
      return;
    }
    const company = companies.find((item) => item.id === companyId);
    if (company) {
      setCompanyEditForm({
        title: company.title,
        email: company.email ?? '',
        phone: company.phone ?? '',
        company_type: company.company_type ?? 'legal',
        economic_code: company.economic_code ?? '',
        national_id: company.national_id ?? ''
      });
    }
  };

  const handleMerchantSelection = (merchantId: number | null) => {
    setMerchantEditId(merchantId);
    if (!merchantId) {
      setMerchantForm({
        full_name: '',
        email: '',
        store_name: '',
        store_address: '',
        phone: '',
        mobile: ''
      });
      return;
    }
    const merchant = merchants.find((item) => item.id === merchantId);
    if (merchant) {
      setMerchantForm({
        full_name: merchant.name,
        email: merchant.email ?? '',
        store_name: merchant.store_name ?? '',
        store_address: merchant.store_address ?? '',
        phone: merchant.phone ?? '',
        mobile: merchant.mobile ?? ''
      });
    }
  };

  const handleProductSelection = (product: AdminProduct | null) => {
    setSelectedProduct(product);
    if (!product) {
      setProductForm({
        name: '',
        price: '',
        stock_quantity: '',
        status: 'active',
        online_purchase_enabled: false
      });
      return;
    }
    setProductForm({
      name: product.name,
      price: product.price.toString(),
      stock_quantity: product.stock_quantity.toString(),
      status: product.status ?? 'active',
      online_purchase_enabled: Boolean(product.online_purchase_enabled)
    });
  };

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
        <div className="flex flex-wrap gap-2">
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
          <Button size="sm" variant="secondary" onClick={() => handleCompanySelection(company.id)}>
            ویرایش
          </Button>
        </div>
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
    { key: 'pending_payouts', header: 'تسویه‌های در انتظار' },
    {
      key: 'actions',
      header: 'عملیات',
      render: (merchant) => (
        <Button size="sm" variant="outline" onClick={() => handleMerchantSelection(merchant.id)}>
          ویرایش
        </Button>
      )
    }
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
      <section className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        <Card className="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100/50 border-blue-200">
          <div className="absolute top-0 left-0 w-32 h-32 bg-blue-200/20 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
          <CardHeader className="relative">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-foreground/70">تعداد کل شرکت‌ها</CardTitle>
              <span className="text-2xl">🏢</span>
            </div>
          </CardHeader>
          <CardContent className="relative">
            <div className="text-4xl font-bold text-foreground">{stats?.total_companies ?? 0}</div>
            <p className="mt-2 text-xs text-muted-foreground">شرکت‌های فعال</p>
          </CardContent>
        </Card>
        <Card className="relative overflow-hidden bg-gradient-to-br from-purple-50 to-purple-100/50 border-purple-200">
          <div className="absolute top-0 left-0 w-32 h-32 bg-purple-200/20 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
          <CardHeader className="relative">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-foreground/70">تعداد کل پذیرندگان</CardTitle>
              <span className="text-2xl">🏪</span>
            </div>
          </CardHeader>
          <CardContent className="relative">
            <div className="text-4xl font-bold text-foreground">{stats?.total_merchants ?? 0}</div>
            <p className="mt-2 text-xs text-muted-foreground">پذیرندگان ثبت‌شده</p>
          </CardContent>
        </Card>
        <Card className="relative overflow-hidden bg-gradient-to-br from-amber-50 to-amber-100/50 border-amber-200">
          <div className="absolute top-0 left-0 w-32 h-32 bg-amber-200/20 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
          <CardHeader className="relative">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-foreground/70">تسویه‌های در انتظار</CardTitle>
              <span className="text-2xl">⏳</span>
            </div>
          </CardHeader>
          <CardContent className="relative">
            <div className="text-4xl font-bold text-foreground">{stats?.total_payouts_pending ?? 0}</div>
            <p className="mt-2 text-xs text-muted-foreground">در انتظار پردازش</p>
          </CardContent>
        </Card>
        <Card className="relative overflow-hidden bg-gradient-to-br from-green-50 to-green-100/50 border-green-200">
          <div className="absolute top-0 left-0 w-32 h-32 bg-green-200/20 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
          <CardHeader className="relative">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-foreground/70">مجموع تراکنش‌ها</CardTitle>
              <span className="text-2xl">💳</span>
            </div>
          </CardHeader>
          <CardContent className="relative">
            <div className="text-4xl font-bold text-foreground">{stats?.total_transactions ?? 0}</div>
            <p className="mt-2 text-xs text-muted-foreground">کل تراکنش‌ها</p>
          </CardContent>
        </Card>
      </section>

      {/* Company management: create & edit */}
      <section className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>ثبت شرکت جدید</CardTitle>
          </CardHeader>
          <CardContent>
            <form
              className="space-y-4"
              onSubmit={(event) => {
                event.preventDefault();
                createCompanyMutation.mutate();
              }}
            >
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="company-name">نام شرکت</Label>
                  <Input
                    id="company-name"
                    required
                    value={companyForm.company_name}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, company_name: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-type">نوع شرکت</Label>
                  <select
                    id="company-type"
                    className="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm"
                    value={companyForm.company_type}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, company_type: e.target.value }))}
                  >
                    <option value="legal">حقوقی</option>
                    <option value="real">حقیقی</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-email">ایمیل</Label>
                  <Input
                    id="company-email"
                    type="email"
                    required
                    value={companyForm.company_email}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, company_email: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-phone">تلفن</Label>
                  <Input
                    id="company-phone"
                    required
                    value={companyForm.company_phone}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, company_phone: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="economic-code">کد اقتصادی (اختیاری)</Label>
                  <Input
                    id="economic-code"
                    value={companyForm.economic_code}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, economic_code: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-national-id">شناسه/کدملی (اختیاری)</Label>
                  <Input
                    id="company-national-id"
                    value={companyForm.national_id}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, national_id: e.target.value }))}
                  />
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="company-password">رمز عبور ورود به پنل شرکت</Label>
                  <Input
                    id="company-password"
                    type="text"
                    placeholder="در صورت خالی بودن، به‌صورت تصادفی ساخته می‌شود."
                    value={companyForm.password}
                    onChange={(e) => setCompanyForm((prev) => ({ ...prev, password: e.target.value }))}
                  />
                </div>
              </div>
              <div className="flex justify-end">
                <Button type="submit" disabled={createCompanyMutation.isPending}>
                  {createCompanyMutation.isPending ? 'در حال ثبت...' : 'ثبت شرکت'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>ویرایش / حذف شرکت</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit-company-select">انتخاب شرکت</Label>
              <select
                id="edit-company-select"
                className="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm"
                value={companyEditId ?? ''}
                onChange={(e) => handleCompanySelection(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">— انتخاب کنید —</option>
                {companies.map((company) => (
                  <option key={company.id} value={company.id}>
                    {company.title}
                  </option>
                ))}
              </select>
            </div>

            {companyEditId && (
              <form
                className="space-y-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  updateCompanyMutation.mutate({
                    companyId: companyEditId,
                    payload: {
                      title: companyEditForm.title,
                      email: companyEditForm.email,
                      phone: companyEditForm.phone,
                      company_type: companyEditForm.company_type,
                      economic_code: companyEditForm.economic_code,
                      national_id: companyEditForm.national_id
                    }
                  });
                }}
              >
                <div className="grid gap-3 md:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>نام / عنوان</Label>
                    <Input
                      value={companyEditForm.title}
                      onChange={(e) => setCompanyEditForm((prev) => ({ ...prev, title: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>نوع شرکت</Label>
                    <select
                      className="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm"
                      value={companyEditForm.company_type}
                      onChange={(e) => setCompanyEditForm((prev) => ({ ...prev, company_type: e.target.value }))}
                    >
                      <option value="legal">حقوقی</option>
                      <option value="real">حقیقی</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>ایمیل</Label>
                    <Input
                      type="email"
                      value={companyEditForm.email}
                      onChange={(e) => setCompanyEditForm((prev) => ({ ...prev, email: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>تلفن</Label>
                    <Input
                      value={companyEditForm.phone}
                      onChange={(e) => setCompanyEditForm((prev) => ({ ...prev, phone: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>کد اقتصادی</Label>
                    <Input
                      value={companyEditForm.economic_code}
                      onChange={(e) => setCompanyEditForm((prev) => ({ ...prev, economic_code: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>شناسه / کدملی</Label>
                    <Input
                      value={companyEditForm.national_id}
                      onChange={(e) => setCompanyEditForm((prev) => ({ ...prev, national_id: e.target.value }))}
                    />
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  <Button type="submit" size="sm" disabled={updateCompanyMutation.isPending}>
                    ذخیره تغییرات
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    disabled={deleteCompanyMutation.isPending}
                    onClick={() => companyEditId && deleteCompanyMutation.mutate(companyEditId)}
                  >
                    حذف شرکت
                  </Button>
                </div>
              </form>
            )}
          </CardContent>
        </Card>
      </section>

      <section>
        <div className="mb-4">
          <h2 className="text-2xl font-bold text-foreground">شرکت‌ها</h2>
          <p className="text-sm text-muted-foreground mt-1">مدیریت و مشاهده شرکت‌های ثبت‌شده</p>
        </div>
        <DataTable data={companies} columns={companyColumns} searchPlaceholder="جست‌وجوی شرکت‌ها" />
      </section>

      {stats?.chart && stats.chart.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>نمای کلی فعالیت</CardTitle>
          <p className="text-sm text-muted-foreground">شاخص‌های عملکرد استخراج‌شده از مسیر /admin/stats.</p>
          </CardHeader>
          <CardContent>
            <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={stats.chart}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                  <XAxis
                    dataKey="label"
                    stroke="#6b7280"
                    style={{ fontSize: '12px' }}
                  />
                  <YAxis
                    stroke="#6b7280"
                    style={{ fontSize: '12px' }}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: 'white',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px',
                      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                    }}
                  />
                  <Line
                    type="monotone"
                    dataKey="value"
                    stroke="hsl(221, 83%, 53%)"
                    strokeWidth={3}
                    dot={{ fill: 'hsl(221, 83%, 53%)', r: 4 }}
                    activeDot={{ r: 6 }}
                  />
              </LineChart>
            </ResponsiveContainer>
          </div>
          </CardContent>
        </Card>
      )}

      {selectedCompany && (
        <Card>
          <CardHeader>
          <div className="flex items-center justify-between">
            <div>
                <CardTitle>کارکنان {selectedCompany.title}</CardTitle>
                <p className="text-sm text-muted-foreground mt-1">جزئیات موجودی کیف پول هر کارمند</p>
            </div>
            <Button
              variant="ghost"
                size="sm"
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
          </CardHeader>
          <CardContent>
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
              employeeImportMutation.mutate({ companyId: selectedCompany.id, file: selectedFile, amount: bulkAmount });
            }}
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2 text-right">
                <Label htmlFor="bulk-amount">مبلغ شارژ برای هر کارمند (اختیاری)</Label>
                <Input
                  id="bulk-amount"
                  inputMode="decimal"
                  placeholder="مثلاً 1000000"
                  value={bulkAmount}
                  onChange={(e) => setBulkAmount(e.target.value)}
                  disabled={employeeImportMutation.isPending}
                />
                <p className="text-xs text-muted-foreground">
                  در صورت تنظیم، به کیف پول همه کارکنان همین مقدار افزوده میD0شود.
                </p>
              </div>
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
          </CardContent>
        </Card>
      )}

      <section>
        <div className="mb-4">
          <h2 className="text-2xl font-bold text-foreground">پذیرندگان</h2>
          <p className="text-sm text-muted-foreground mt-1">مدیریت پذیرندگان و موجودی کیف پول</p>
        </div>
        <DataTable data={merchants} columns={merchantColumns} searchPlaceholder="جست‌وجوی پذیرندگان" />
      </section>

      {/* Merchant and products management */}
      <section className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>ویرایش پذیرنده</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit-merchant-select">انتخاب پذیرنده</Label>
              <select
                id="edit-merchant-select"
                className="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm"
                value={merchantEditId ?? ''}
                onChange={(e) => handleMerchantSelection(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">— انتخاب کنید —</option>
                {merchants.map((merchant) => (
                  <option key={merchant.id} value={merchant.id}>
                    {merchant.name}
                  </option>
                ))}
              </select>
            </div>

            {merchantEditId && (
              <form
                className="space-y-3"
                onSubmit={(event) => {
                  event.preventDefault();
                  updateMerchantMutation.mutate({
                    merchantId: merchantEditId,
                    payload: {
                      full_name: merchantForm.full_name,
                      email: merchantForm.email,
                      store_name: merchantForm.store_name,
                      store_address: merchantForm.store_address,
                      phone: merchantForm.phone,
                      mobile: merchantForm.mobile
                    }
                  });
                }}
              >
                <div className="grid gap-3 md:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>نام کامل</Label>
                    <Input
                      value={merchantForm.full_name}
                      onChange={(e) => setMerchantForm((prev) => ({ ...prev, full_name: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>ایمیل</Label>
                    <Input
                      type="email"
                      value={merchantForm.email}
                      onChange={(e) => setMerchantForm((prev) => ({ ...prev, email: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>نام فروشگاه</Label>
                    <Input
                      value={merchantForm.store_name}
                      onChange={(e) => setMerchantForm((prev) => ({ ...prev, store_name: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>آدرس فروشگاه</Label>
                    <Input
                      value={merchantForm.store_address}
                      onChange={(e) => setMerchantForm((prev) => ({ ...prev, store_address: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>تلفن</Label>
                    <Input
                      value={merchantForm.phone}
                      onChange={(e) => setMerchantForm((prev) => ({ ...prev, phone: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>موبایل</Label>
                    <Input
                      value={merchantForm.mobile}
                      onChange={(e) => setMerchantForm((prev) => ({ ...prev, mobile: e.target.value }))}
                    />
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  <Button type="submit" size="sm" disabled={updateMerchantMutation.isPending}>
                    ذخیره تغییرات
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    disabled={deleteMerchantMutation.isPending}
                    onClick={() => merchantEditId && deleteMerchantMutation.mutate(merchantEditId)}
                  >
                    حذف پذیرنده
                  </Button>
                </div>
              </form>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>حذف کاربر بر اساس شناسه</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-sm text-muted-foreground">
              شناسه کاربری (ID) هر حساب را می‌توانید از جدول‌ها یا از داخل وردپرس مشاهده کنید. با دقت استفاده کنید؛ این
              عملیات قابل بازگشت نیست.
            </p>
            <div className="flex items-center gap-2">
              <Input
                placeholder="مثلاً 123"
                value={userDeleteId}
                onChange={(e) => setUserDeleteId(e.target.value)}
              />
              <Button
                type="button"
                variant="destructive"
                disabled={!userDeleteId || deleteUserMutation.isPending}
                onClick={() => {
                  const id = Number(userDeleteId);
                  if (!Number.isFinite(id) || id <= 0) {
                    toast.error('شناسه نامعتبر است.');
                    return;
                  }
                  deleteUserMutation.mutate(id);
                }}
              >
                حذف کاربر
              </Button>
            </div>
          </CardContent>
        </Card>
      </section>

      {/* Products overview for admin */}
      <section className="mt-8 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold text-foreground">محصولات همه پذیرندگان</h2>
            <p className="text-sm text-muted-foreground mt-1">ویرایش یا حذف محصولات توسط مدیر سامانه</p>
          </div>
        </div>
        <DataTable
          data={products}
          columns={[
            { key: 'name', header: 'محصول' },
            { key: 'merchant_name', header: 'پذیرنده' },
            {
              key: 'price',
              header: 'قیمت',
              render: (product) => product.price.toLocaleString()
            },
            { key: 'stock_quantity', header: 'موجودی' },
            {
              key: 'online_purchase_enabled',
              header: 'خرید آنلاین',
              render: (product) => (product.online_purchase_enabled ? 'فعال' : 'غیرفعال')
            },
            {
              key: 'actions',
              header: 'عملیات',
              render: (product) => (
                <Button size="sm" variant="outline" onClick={() => handleProductSelection(product)}>
                  ویرایش
                </Button>
              )
            }
          ]}
          searchPlaceholder="جست‌وجوی محصولات"
        />

        {selectedProduct && (
          <Card>
            <CardHeader>
              <CardTitle>ویرایش محصول: {selectedProduct.name}</CardTitle>
            </CardHeader>
            <CardContent>
              <form
                className="grid gap-4 md:grid-cols-2"
                onSubmit={(event) => {
                  event.preventDefault();
                  if (!selectedProduct) return;
                  updateProductMutation.mutate({
                    productId: selectedProduct.id,
                    payload: {
                      name: productForm.name,
                      price: Number(productForm.price || 0),
                      stock_quantity: Number(productForm.stock_quantity || 0),
                      status: productForm.status,
                      online_purchase_enabled: productForm.online_purchase_enabled
                    }
                  });
                }}
              >
                <div className="space-y-1.5">
                  <Label>نام محصول</Label>
                  <Input
                    value={productForm.name}
                    onChange={(e) => setProductForm((prev) => ({ ...prev, name: e.target.value }))}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>قیمت</Label>
                  <Input
                    type="number"
                    value={productForm.price}
                    onChange={(e) => setProductForm((prev) => ({ ...prev, price: e.target.value }))}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>موجودی</Label>
                  <Input
                    type="number"
                    value={productForm.stock_quantity}
                    onChange={(e) => setProductForm((prev) => ({ ...prev, stock_quantity: e.target.value }))}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>وضعیت</Label>
                  <select
                    className="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm"
                    value={productForm.status}
                    onChange={(e) => setProductForm((prev) => ({ ...prev, status: e.target.value }))}
                  >
                    <option value="active">فعال</option>
                    <option value="inactive">غیرفعال</option>
                  </select>
                </div>
                <div className="space-y-1.5 md:col-span-2">
                  <label className="inline-flex items-center gap-2 text-sm text-foreground">
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded border border-[#E5E7EB]"
                      checked={productForm.online_purchase_enabled}
                      onChange={(e) =>
                        setProductForm((prev) => ({ ...prev, online_purchase_enabled: e.target.checked }))
                      }
                    />
                    <span>فعال بودن برای خرید آنلاین</span>
                  </label>
                </div>
                <div className="flex flex-wrap items-center gap-3 md:col-span-2">
                  <Button type="submit" size="sm" disabled={updateProductMutation.isPending}>
                    ذخیره تغییرات
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    disabled={deleteProductMutation.isPending}
                    onClick={() => selectedProduct && deleteProductMutation.mutate(selectedProduct.id)}
                  >
                    حذف محصول
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => handleProductSelection(null)}
                  >
                    بستن
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}
      </section>

      <section>
        <div className="mb-4">
          <h2 className="text-2xl font-bold text-foreground">درخواست‌های تسویه</h2>
          <p className="text-sm text-muted-foreground mt-1">پیگیری و مدیریت درخواست‌های تسویه</p>
        </div>
        <DataTable data={payouts} columns={payoutColumns} searchPlaceholder="جست‌وجوی درخواست‌های تسویه" />
      </section>

      <section className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold text-foreground">تراکنش‌ها</h2>
            <p className="text-sm text-muted-foreground mt-1">فیلتر بر اساس تاریخ و دریافت خروجی CSV.</p>
          </div>
          <Button onClick={() => exportToCsv('transactions.csv', transactions)} variant="outline">
            دریافت CSV
          </Button>
        </div>
        <DateRangePicker onChange={setDateFilter} />
        <DataTable data={transactions} columns={transactionColumns} searchPlaceholder="جست‌وجوی تراکنش‌ها" />
      </section>

      {/* Reports Section */}
      <ReportsSection />
    </DashboardLayout>
  );
};

// Reports Component
const ReportsSection = () => {
  const defaultOrderReports: OrderReports = {
    total_online_orders: 0,
    total_online_sales: 0,
    total_in_person_sales: 0,
    orders_by_status: [],
    sales_by_merchant: [],
    sales_by_category: []
  };

  const defaultProductReports: ProductReports = {
    total_products: 0,
    products_by_category: [],
    top_products: []
  };

  const { data: orderReports = defaultOrderReports } = useQuery<OrderReports>({
    queryKey: ['admin', 'reports', 'orders'],
    queryFn: async (): Promise<OrderReports> => {
      const response = await apiClient.get('/admin/reports/orders');
      const data = unwrapWordPressObject<OrderReports>(response.data);
      return data ?? defaultOrderReports;
    }
  });

  const { data: productReports = defaultProductReports } = useQuery<ProductReports>({
    queryKey: ['admin', 'reports', 'products'],
    queryFn: async (): Promise<ProductReports> => {
      const response = await apiClient.get('/admin/reports/products');
      const data = unwrapWordPressObject<ProductReports>(response.data);
      return data ?? defaultProductReports;
    }
  });

  return (
    <div className="space-y-6">
      <section>
        <div className="mb-4">
          <h2 className="text-2xl font-bold text-foreground">گزارشات فروش</h2>
          <p className="text-sm text-muted-foreground mt-1">گزارشات کامل فروش آنلاین و حضوری</p>
        </div>
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
          <Card className="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100/50 border-blue-200">
            <CardHeader className="relative">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm font-medium text-foreground/70">فروش آنلاین</CardTitle>
                <span className="text-2xl">🛒</span>
              </div>
            </CardHeader>
            <CardContent className="relative">
              <div className="text-4xl font-bold text-foreground">
                {orderReports.total_online_sales.toLocaleString()}
              </div>
              <p className="mt-2 text-xs text-muted-foreground">تومان</p>
            </CardContent>
          </Card>
          <Card className="relative overflow-hidden bg-gradient-to-br from-green-50 to-green-100/50 border-green-200">
            <CardHeader className="relative">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm font-medium text-foreground/70">فروش حضوری</CardTitle>
                <span className="text-2xl">💳</span>
              </div>
            </CardHeader>
            <CardContent className="relative">
              <div className="text-4xl font-bold text-foreground">
                {orderReports.total_in_person_sales.toLocaleString()}
              </div>
              <p className="mt-2 text-xs text-muted-foreground">تومان</p>
            </CardContent>
          </Card>
          <Card className="relative overflow-hidden bg-gradient-to-br from-purple-50 to-purple-100/50 border-purple-200">
            <CardHeader className="relative">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm font-medium text-foreground/70">تعداد سفارشات آنلاین</CardTitle>
                <span className="text-2xl">📦</span>
              </div>
            </CardHeader>
            <CardContent className="relative">
              <div className="text-4xl font-bold text-foreground">
                {orderReports.total_online_orders}
              </div>
              <p className="mt-2 text-xs text-muted-foreground">سفارش</p>
            </CardContent>
          </Card>
        </div>
      </section>

      {orderReports.sales_by_merchant && orderReports.sales_by_merchant.length > 0 && (
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">فروش بر اساس فروشگاه</h2>
          <Card>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>فروشگاه</TableHead>
                    <TableHead>تعداد سفارش</TableHead>
                    <TableHead>مبلغ کل</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {orderReports.sales_by_merchant.map((merchant) => (
                    <TableRow key={merchant.merchant_id}>
                      <TableCell>{merchant.store_name || `فروشگاه ${merchant.merchant_id}`}</TableCell>
                      <TableCell>{merchant.order_count}</TableCell>
                      <TableCell>{merchant.total_sales.toLocaleString()} تومان</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </section>
      )}

      {orderReports.sales_by_category && orderReports.sales_by_category.length > 0 && (
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">فروش بر اساس دسته‌بندی محصولات</h2>
          <Card>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>دسته‌بندی</TableHead>
                    <TableHead>تعداد سفارش</TableHead>
                    <TableHead>مبلغ کل</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {orderReports.sales_by_category.map((category) => (
                    <TableRow key={category.category_id}>
                      <TableCell>{category.category_name}</TableCell>
                      <TableCell>{category.order_count}</TableCell>
                      <TableCell>{category.total_sales.toLocaleString()} تومان</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </section>
      )}

      {productReports && (
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">گزارشات محصولات</h2>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>محصولات بر اساس دسته‌بندی</CardTitle>
              </CardHeader>
              <CardContent>
                {productReports.products_by_category && productReports.products_by_category.length > 0 ? (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>دسته‌بندی</TableHead>
                        <TableHead>تعداد محصول</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {productReports.products_by_category.map((cat) => (
                        <TableRow key={cat.category_id}>
                          <TableCell>{cat.category_name}</TableCell>
                          <TableCell>{cat.product_count}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                ) : (
                  <p className="text-muted-foreground">داده‌ای موجود نیست</p>
                )}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>پرفروش‌ترین محصولات</CardTitle>
              </CardHeader>
              <CardContent>
                {productReports.top_products && productReports.top_products.length > 0 ? (
                  <div className="space-y-2">
                    {productReports.top_products.slice(0, 5).map((product) => (
                      <div key={product.product_id} className="flex justify-between border-b pb-2">
                        <div>
                          <p className="font-medium">{product.product_name}</p>
                          <p className="text-xs text-muted-foreground">{product.store_name}</p>
                        </div>
                        <div className="text-left">
                          <p className="font-semibold">{product.total_revenue.toLocaleString()} تومان</p>
                          <p className="text-xs text-muted-foreground">{product.total_sold} عدد</p>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground">داده‌ای موجود نیست</p>
                )}
              </CardContent>
            </Card>
          </div>
        </section>
      )}
    </div>
  );
};

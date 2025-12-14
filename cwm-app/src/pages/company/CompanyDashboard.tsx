import { useEffect, useMemo, useState, useRef } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { ResponsiveContainer, LineChart, Line, BarChart, Bar, CartesianGrid, XAxis, YAxis, Tooltip, Legend, PieChart, Pie, Cell, AreaChart, Area } from 'recharts';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { CreditCard as CreditCardComponent } from '../../components/common/CreditCard';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Select } from '../../components/ui/select';
import { apiClient } from '../../api/client';
import { useAuth } from '../../store/auth';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';
import { cn } from '../../utils/cn';
import {
  LayoutDashboard,
  Wallet,
  Upload,
  Lock,
  Users,
  Store,
  ShoppingCart,
  TrendingUp,
  User
} from 'lucide-react';

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

interface EmployeeReport {
  employee_id: number;
  employee_name: string;
  employee_email: string;
  online_orders: number;
  online_spent: number;
  in_person_transactions: number;
  in_person_spent: number;
  total_spent: number;
}

interface CompanyReports {
  employees: EmployeeReport[];
}

interface CompanyCredit {
  credit_amount: number;
  wallet_balance: number;
  available: number;
}

interface CategoryCap {
  category_id: number;
  category_name: string;
  slug: string;
  cap?: number;
  limit_type?: string;
  limit_value?: number;
}

interface TopMerchant {
  merchant_id: number;
  store_name: string;
  order_count: number;
  total_sales: number;
}

type TabType = 'overview' | 'credit' | 'allocate-credit' | 'category-limits' | 'employee-reports' | 'merchant-reports' | 'online-access';

const tabs = [
  { id: 'overview' as TabType, label: 'نمای کلی', icon: LayoutDashboard },
  { id: 'credit' as TabType, label: 'اعتبار شرکت', icon: Wallet },
  { id: 'allocate-credit' as TabType, label: 'تخصیص اعتبار', icon: Upload },
  { id: 'category-limits' as TabType, label: 'محدودیت دسته‌بندی', icon: Lock },
  { id: 'employee-reports' as TabType, label: 'گزارشات کارمندان', icon: Users },
  { id: 'merchant-reports' as TabType, label: 'گزارشات فروشگاه‌ها', icon: Store },
  { id: 'online-access' as TabType, label: 'دسترسی خرید آنلاین', icon: ShoppingCart }
];

export const CompanyDashboard = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [editingEmployee, setEditingEmployee] = useState<CompanyEmployee | null>(null);
  const [limitForm, setLimitForm] = useState<Record<number, string>>({});
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const [bulkAmount, setBulkAmount] = useState<string>('');
  const [importSummary, setImportSummary] = useState<{ processed: number; created: number; updated: number; balances_adjusted: number; errors: { row: number; message: string }[] } | null>(null);
  const [categoryCaps, setCategoryCaps] = useState<Record<number, { limit_type: string; limit_value: string }>>({});

  const { data: profile } = useQuery({
    queryKey: ['company', 'profile'],
    queryFn: async () => {
      const response = await apiClient.get('/profile');
      return unwrapWordPressObject<CompanyProfile>(response.data);
    }
  });

  const { data: credit } = useQuery<CompanyCredit>({
    queryKey: ['company', 'credit'],
    queryFn: async () => {
      const response = await apiClient.get('/company/credit');
      const data = unwrapWordPressObject<{ data?: CompanyCredit } & CompanyCredit>(response.data);
      const creditData = data?.data ?? data;
      return {
        credit_amount: Number(creditData?.credit_amount ?? 0),
        wallet_balance: Number(creditData?.wallet_balance ?? 0),
        available: Number(creditData?.available ?? 0)
      };
    },
    enabled: activeTab === 'credit' || activeTab === 'overview'
  });

  const { data: employees = [] } = useQuery({
    queryKey: ['company', 'employees', user?.email],
    queryFn: async () => {
      if (!user?.email) return [] as CompanyEmployee[];
      const response = await apiClient.get('/company/employees');
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

  const { data: companyCategoryCaps = [] } = useQuery<CategoryCap[]>({
    queryKey: ['company', 'category-caps'],
    queryFn: async () => {
      const response = await apiClient.get('/company/category-caps');
      const data = unwrapWordPressObject<{ data?: { caps?: CategoryCap[] }; caps?: CategoryCap[] }>(response.data);
      const caps = data?.data?.caps ?? data?.caps ?? [];
      return caps.map((cap: any) => ({
        category_id: Number(cap.category_id ?? 0),
        category_name: String(cap.category_name ?? ''),
        slug: String(cap.slug ?? ''),
        cap: cap.cap ? Number(cap.cap) : undefined,
        limit_type: cap.limit_type ?? 'amount',
        limit_value: cap.limit_value ? Number(cap.limit_value) : undefined
      }));
    },
    enabled: activeTab === 'category-limits'
  });

  const { data: employeeReports = [] } = useQuery<EmployeeReport[]>({
    queryKey: ['company', 'reports', 'employees'],
    queryFn: async () => {
      const response = await apiClient.get('/company/reports/employees');
      const data = unwrapWordPressObject<{ data?: CompanyReports; employees?: EmployeeReport[] }>(response.data);
      return data?.data?.employees ?? data?.employees ?? [];
    },
    enabled: activeTab === 'employee-reports'
  });

  const { data: topMerchants = [] } = useQuery<TopMerchant[]>({
    queryKey: ['company', 'reports', 'top-merchants'],
    queryFn: async () => {
      const response = await apiClient.get('/company/reports/top-merchants');
      const data = unwrapWordPressObject<{ data?: { merchants?: TopMerchant[] }; merchants?: TopMerchant[] }>(response.data);
      return data?.data?.merchants ?? data?.merchants ?? [];
    },
    enabled: activeTab === 'merchant-reports'
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

  useEffect(() => {
    const caps: Record<number, { limit_type: string; limit_value: string }> = {};
    companyCategoryCaps.forEach((cap) => {
      caps[cap.category_id] = {
        limit_type: cap.limit_type ?? 'amount',
        limit_value: cap.limit_value !== null && cap.limit_value !== undefined 
          ? cap.limit_value.toString() 
          : (cap.cap !== null && cap.cap !== undefined ? cap.cap.toString() : '')
      };
    });
    // Also initialize empty state for categories that don't have caps yet
    categories.forEach((category) => {
      if (!caps[category.id]) {
        caps[category.id] = { limit_type: 'amount', limit_value: '' };
      }
    });
    setCategoryCaps(caps);
  }, [companyCategoryCaps, categories]);

  const allocateCreditMutation = useMutation({
    mutationFn: async ({ file, amount }: { file: File; amount?: string }) => {
      const formData = new FormData();
      formData.append('file', file);
      if (amount && amount.trim()) {
        formData.append('amount', amount.trim());
      }
      const response = await apiClient.post('/company/employees/import', formData, {
        headers: { 
          'Content-Type': 'multipart/form-data'
        }
      });
      return response.data;
    },
    onSuccess: (data) => {
      setImportSummary({
        processed: data.processed ?? 0,
        created: data.created ?? 0,
        updated: data.updated ?? 0,
        balances_adjusted: data.balances_adjusted ?? 0,
        errors: Array.isArray(data.errors) ? data.errors : []
      });
      toast.success(`فایل کارکنان با موفقیت پردازش شد. پردازش شده: ${data.processed ?? 0}, ایجاد: ${data.created ?? 0}, به‌روزرسانی: ${data.updated ?? 0}`);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      setSelectedFile(null);
      setBulkAmount('');
      queryClient.invalidateQueries({ queryKey: ['company', 'employees', user?.email] });
      queryClient.invalidateQueries({ queryKey: ['company', 'credit'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'بارگذاری CSV کارکنان با خطا مواجه شد.';
      toast.error(message);
      setImportSummary(null);
    }
  });

  const updateCategoryCapsMutation = useMutation({
    mutationFn: async (caps: Array<{ category_id: number; limit_type: string; limit_value: number }>) => {
      await apiClient.post('/company/category-caps', { caps });
    },
    onSuccess: () => {
      toast.success('محدودیت‌های دسته‌بندی به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['company', 'category-caps'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی محدودیت‌ها با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const updateEmployeeOnlineAccessMutation = useMutation({
    mutationFn: async ({ employeeId, hasAccess }: { employeeId: number; hasAccess: boolean }) => {
      await apiClient.post(`/company/employees/${employeeId}/online-access`, { has_access: hasAccess });
    },
    onSuccess: () => {
      toast.success('دسترسی خرید آنلاین به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['company', 'employees', user?.email] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی دسترسی با خطا مواجه شد.';
      toast.error(message);
    }
  });

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

  const handleCategoryCapChange = (categoryId: number, field: 'limit_type' | 'limit_value', value: string) => {
    setCategoryCaps((prev) => {
      const current = prev[categoryId] || { limit_type: 'amount', limit_value: '' };
      const updated = {
        ...prev,
        [categoryId]: {
          ...current,
          [field]: value
        }
      };
      console.log(`Category cap changed - Category ${categoryId}, Field: ${field}, Value: ${value}`, updated[categoryId]);
      return updated;
    });
  };

  const handleCategoryCapsSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const caps = Object.entries(categoryCaps)
      .filter(([_, cap]) => {
        // Filter out empty or invalid entries
        const hasLimitValue = cap.limit_value && cap.limit_value.trim() !== '';
        const hasValidNumber = Number(cap.limit_value) > 0;
        const hasLimitType = cap.limit_type && cap.limit_type.trim() !== '';
        return hasLimitValue && hasValidNumber && hasLimitType;
      })
      .map(([categoryId, cap]) => {
        // Ensure limit_type is always set and valid
        let limitType = (cap.limit_type && cap.limit_type.trim() !== '') 
          ? cap.limit_type.trim() 
          : 'amount';
        
        // Validate limit_type
        if (limitType !== 'amount' && limitType !== 'percentage') {
          console.warn(`Invalid limit_type "${limitType}" for category ${categoryId}, defaulting to "amount"`);
          limitType = 'amount';
        }
        
        const limitValue = Number(cap.limit_value);
        
        if (isNaN(limitValue) || limitValue <= 0) {
          return null;
        }
        
        const result = {
          category_id: Number(categoryId),
          limit_type: limitType,
          limit_value: limitValue
        };
        
        console.log(`Mapped cap for category ${categoryId}:`, result);
        return result;
      })
      .filter((cap): cap is { category_id: number; limit_type: string; limit_value: number } => cap !== null);

    if (caps.length === 0) {
      toast.error('لطفاً حداقل یک محدودیت معتبر وارد کنید.');
      return;
    }

    console.log('Submitting category caps:', caps);
    console.log('Category caps state before submit:', categoryCaps);
    
    // Double check that all caps have valid limit_type - STRICT validation
    const validatedCaps = caps.map(cap => {
      // Ensure limit_type is always a valid string
      let limitType = String(cap.limit_type || '').trim().toLowerCase();
      
      // If empty or invalid, default to 'amount'
      if (limitType !== 'amount' && limitType !== 'percentage') {
        console.warn(`Invalid limit_type for category ${cap.category_id}: "${cap.limit_type}" (normalized: "${limitType}"), defaulting to "amount"`);
        limitType = 'amount';
      }
      
      // Ensure all fields are properly typed
      const validated = {
        category_id: Number(cap.category_id),
        limit_type: limitType,
        limit_value: Number(cap.limit_value)
      };
      
      console.log(`Final validated cap for category ${validated.category_id}:`, validated);
      return validated;
    });
    
    console.log('Final validated caps array:', JSON.stringify(validatedCaps, null, 2));
    updateCategoryCapsMutation.mutate(validatedCaps);
  };

  const handleAllocateCredit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!selectedFile) {
      toast.error('لطفاً فایل CSV کارکنان را انتخاب کنید.');
      return;
    }
    allocateCreditMutation.mutate({ file: selectedFile, amount: bulkAmount });
  };

  return (
    <DashboardLayout
      sidebarTabs={tabs}
      activeTab={activeTab}
      onTabChange={(tabId) => setActiveTab(tabId as TabType)}
    >
      {/* Overview Tab */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          {/* Credit Card */}
          <section className="mb-6">
            <div className="w-full md:w-1/3">
              <CreditCardComponent
                balance={credit?.wallet_balance ?? credit?.credit_amount ?? 0}
                cardHolderName={profile?.company_name || profile?.display_name || user?.name || 'شرکت'}
                phoneNumber={undefined}
              />
            </div>
          </section>

          {/* Stats Cards - Figma Style */}
          <section className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <Card className="border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-yellow-100">
                    <Wallet className="h-7 w-7 text-yellow-600" />
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm font-medium text-gray-600">موجودی کیف پول</p>
                  <p className="text-3xl font-bold text-gray-900">{credit?.wallet_balance?.toLocaleString() ?? 0}</p>
                  <p className="text-xs text-gray-500">تومان</p>
                </div>
              </CardContent>
            </Card>
            <Card className="border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-100">
                    <TrendingUp className="h-7 w-7 text-blue-600" />
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm font-medium text-gray-600">اعتبار کل</p>
                  <p className="text-3xl font-bold text-gray-900">{credit?.credit_amount?.toLocaleString() ?? 0}</p>
                  <p className="text-xs text-gray-500">تومان</p>
                </div>
              </CardContent>
            </Card>
            <Card className="border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-pink-100">
                    <Users className="h-7 w-7 text-pink-600" />
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm font-medium text-gray-600">تعداد کارمندان</p>
                  <p className="text-3xl font-bold text-gray-900">{employees.length}</p>
                  <p className="text-xs text-gray-500">کارمند فعال</p>
                </div>
              </CardContent>
            </Card>
            <Card className="border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-teal-100">
                    <ShoppingCart className="h-7 w-7 text-teal-600" />
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm font-medium text-gray-600">تراکنش‌ها</p>
                  <p className="text-3xl font-bold text-gray-900">{transactions.length}</p>
                  <p className="text-xs text-gray-500">تراکنش انجام شده</p>
                </div>
              </CardContent>
            </Card>
          </section>

          {/* Charts Section - Figma Style */}
          <section className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Weekly Activity Chart */}
            <Card className="border border-gray-200 bg-white shadow-sm">
              <CardHeader className="border-b border-gray-200 pb-4">
                <CardTitle className="text-lg font-semibold text-gray-900">فعالیت هفتگی</CardTitle>
                <p className="text-sm text-gray-500 mt-1">نمودار تراکنش‌های هفتگی</p>
              </CardHeader>
              <CardContent className="pt-6">
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={[
                    { name: 'شنبه', value: transactions.filter(t => new Date(t.created_at).getDay() === 6).length },
                    { name: 'یکشنبه', value: transactions.filter(t => new Date(t.created_at).getDay() === 0).length },
                    { name: 'دوشنبه', value: transactions.filter(t => new Date(t.created_at).getDay() === 1).length },
                    { name: 'سه‌شنبه', value: transactions.filter(t => new Date(t.created_at).getDay() === 2).length },
                    { name: 'چهارشنبه', value: transactions.filter(t => new Date(t.created_at).getDay() === 3).length },
                    { name: 'پنج‌شنبه', value: transactions.filter(t => new Date(t.created_at).getDay() === 4).length },
                    { name: 'جمعه', value: transactions.filter(t => new Date(t.created_at).getDay() === 5).length }
                  ]}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                    <XAxis dataKey="name" stroke="#6b7280" style={{ fontSize: '12px' }} />
                    <YAxis stroke="#6b7280" style={{ fontSize: '12px' }} />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: '#fff',
                        border: '1px solid #e5e7eb',
                        borderRadius: '8px',
                        boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                      }}
                    />
                    <Bar dataKey="value" fill="#3b82f6" radius={[8, 8, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            {/* Employee Balance Distribution */}
            <Card className="border border-gray-200 bg-white shadow-sm">
              <CardHeader className="border-b border-gray-200 pb-4">
                <CardTitle className="text-lg font-semibold text-gray-900">توزیع اعتبار کارمندان</CardTitle>
                <p className="text-sm text-gray-500 mt-1">بر اساس موجودی کارمندان</p>
              </CardHeader>
              <CardContent className="pt-6">
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={employees.slice(0, 5).map(emp => ({ name: emp.name, value: emp.balance }))}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                      outerRadius={100}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      {employees.slice(0, 5).map((entry, index) => {
                        const colors = ['#fbbf24', '#3b82f6', '#ec4899', '#14b8a6', '#8b5cf6'];
                        return <Cell key={`cell-${index}`} fill={colors[index % colors.length]} />;
                      })}
                    </Pie>
                    <Tooltip
                      contentStyle={{
                        backgroundColor: '#fff',
                        border: '1px solid #e5e7eb',
                        borderRadius: '8px'
                      }}
                    />
                  </PieChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </section>

          {/* Account Info Section */}
          <section className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <Card className="border border-gray-200 bg-white shadow-sm">
              <CardHeader className="border-b border-gray-200 pb-4">
                <CardTitle className="text-lg font-semibold text-gray-900">اطلاعات حساب</CardTitle>
              </CardHeader>
              <CardContent className="pt-6 space-y-3">
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-gray-600">شرکت:</span>
                  <span className="text-sm text-gray-900">{profile?.company_name ?? user?.name ?? '—'}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-gray-600">ایمیل:</span>
                  <span className="text-sm text-gray-900">{user?.email ?? '—'}</span>
                </div>
              </CardContent>
            </Card>
            <Card className="border border-gray-200 bg-white shadow-sm">
              <CardHeader className="border-b border-gray-200 pb-4">
                <CardTitle className="text-lg font-semibold text-gray-900">وضعیت شرکت</CardTitle>
              </CardHeader>
              <CardContent className="pt-6">
                <p className="text-sm text-gray-700 leading-relaxed">{statusMessage}</p>
              </CardContent>
            </Card>
          </section>
        </div>
      )}

      {/* Credit Tab */}
      {activeTab === 'credit' && (
        <div className="space-y-6">
          <Card className="border-0 shadow-elevated">
            <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10">
              <CardTitle className="text-xl">اعتبار شرکت</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card className="border-0 bg-gradient-to-br from-blue-500 to-blue-600 shadow-lg shadow-blue-500/20">
                  <CardHeader className="border-0">
                    <CardTitle className="text-sm font-medium text-white/90">اعتبار اولیه</CardTitle>
                  </CardHeader>
                  <CardContent className="border-0">
                    <p className="text-3xl font-bold text-white">{credit?.credit_amount?.toLocaleString() ?? 0}</p>
                    <p className="text-sm text-white/80">تومان</p>
                  </CardContent>
                </Card>
                <Card className="border-0 bg-gradient-to-br from-green-500 to-green-600 shadow-lg shadow-green-500/20">
                  <CardHeader className="border-0">
                    <CardTitle className="text-sm font-medium text-white/90">موجودی کیف پول</CardTitle>
                  </CardHeader>
                  <CardContent className="border-0">
                    <p className="text-3xl font-bold text-white">{credit?.wallet_balance?.toLocaleString() ?? 0}</p>
                    <p className="text-sm text-white/80">تومان</p>
                  </CardContent>
                </Card>
                <Card className="border-0 bg-gradient-to-br from-purple-500 to-purple-600 shadow-lg shadow-purple-500/20">
                  <CardHeader className="border-0">
                    <CardTitle className="text-sm font-medium text-white/90">اعتبار قابل استفاده</CardTitle>
                  </CardHeader>
                  <CardContent className="border-0">
                    <p className="text-3xl font-bold text-white">{credit?.available?.toLocaleString() ?? 0}</p>
                    <p className="text-sm text-white/80">تومان</p>
                  </CardContent>
                </Card>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Allocate Credit Tab */}
      {activeTab === 'allocate-credit' && (
        <div className="space-y-6">
          <Card className="border border-gray-200 bg-white shadow-sm">
            <CardHeader className="border-b border-gray-200 pb-4">
              <CardTitle className="text-lg font-semibold text-gray-900">تخصیص اعتبار به کارمندان</CardTitle>
              <p className="text-sm text-gray-500 mt-1">فایل CSV را آپلود کنید تا اعتبار به کارمندان تخصیص داده شود</p>
            </CardHeader>
            <CardContent className="pt-6">
              <form
                className="mt-4 space-y-4 rounded-md border border-dashed border-slate-200 p-4"
                onSubmit={handleAllocateCredit}
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
                      disabled={allocateCreditMutation.isPending}
                    />
                    <p className="text-xs text-muted-foreground">
                      در صورت تنظیم، به کیف پول همه کارکنان همین مقدار افزوده می‌شود.
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
                      disabled={allocateCreditMutation.isPending}
                    />
                    <p className="text-xs text-muted-foreground">
                      ستون‌های پشتیبانی‌شده: name, email, national_id, mobile, balance. ردیف اول باید عنوان ستون‌ها باشد.
                    </p>
                  </div>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4">
                  <Button type="submit" disabled={allocateCreditMutation.isPending || !selectedFile}>
                    {allocateCreditMutation.isPending ? 'در حال پردازش...' : 'بارگذاری CSV'}
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
                {importSummary?.errors && importSummary.errors.length > 0 && (
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
                )}
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
                      <TableCell>{employee.balance?.toLocaleString() ?? 0} تومان</TableCell>
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
        </div>
      )}

      {/* Category Limits Tab */}
      {activeTab === 'category-limits' && (
        <div className="space-y-6">
          <Card className="border-0 shadow-elevated">
            <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10">
              <CardTitle className="text-xl">محدودیت دسته‌بندی‌ها</CardTitle>
              <p className="text-sm text-muted-foreground mt-1">محدودیت استفاده کارمندان از اعتبار در هر دسته‌بندی را تنظیم کنید</p>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleCategoryCapsSubmit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {categories.map((category) => {
                    const cap = categoryCaps[category.id] ?? { limit_type: 'amount', limit_value: '' };
                    return (
                      <Card key={category.id} className="border border-border p-4">
                        <div className="space-y-3">
                          <Label className="font-semibold">{category.name}</Label>
                          <div className="space-y-2">
                            <Label htmlFor={`limit-type-${category.id}`} className="text-xs">نوع محدودیت</Label>
                            <Select
                              id={`limit-type-${category.id}`}
                              value={cap.limit_type || 'amount'}
                              onChange={(e) => {
                                const newValue = e.target.value || 'amount';
                                console.log(`Select changed for category ${category.id}: ${newValue}`);
                                handleCategoryCapChange(category.id, 'limit_type', newValue);
                              }}
                            >
                              <option value="amount">مبلغ (تومان)</option>
                              <option value="percentage">درصد از اعتبار</option>
                            </Select>
                          </div>
                          <div className="space-y-2">
                            <Label htmlFor={`limit-value-${category.id}`} className="text-xs">
                              {cap.limit_type === 'percentage' ? 'درصد' : 'مبلغ (تومان)'}
                            </Label>
                            <Input
                              id={`limit-value-${category.id}`}
                              type="number"
                              min="0"
                              step={cap.limit_type === 'percentage' ? '1' : '1000'}
                              value={cap.limit_value}
                              onChange={(e) => handleCategoryCapChange(category.id, 'limit_value', e.target.value)}
                              placeholder={cap.limit_type === 'percentage' ? 'مثلاً 50' : 'مثلاً 1000000'}
                            />
                          </div>
                        </div>
                      </Card>
                    );
                  })}
                </div>
                <div className="flex justify-end">
                  <Button type="submit" disabled={updateCategoryCapsMutation.isPending}>
                    {updateCategoryCapsMutation.isPending ? 'در حال ذخیره...' : 'ذخیره محدودیت‌ها'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Employee Reports Tab */}
      {activeTab === 'employee-reports' && (
        <div className="space-y-6">
          <Card className="border-0 shadow-elevated">
            <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10">
              <CardTitle className="text-xl">گزارشات کارمندان</CardTitle>
              <p className="text-sm text-muted-foreground mt-1">گزارشات خرید آنلاین و حضوری کارمندان</p>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>نام کارمند</TableHead>
                    <TableHead>ایمیل</TableHead>
                    <TableHead>سفارشات آنلاین</TableHead>
                    <TableHead>خرید آنلاین</TableHead>
                    <TableHead>تراکنش‌های حضوری</TableHead>
                    <TableHead>خرید حضوری</TableHead>
                    <TableHead>جمع کل</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {employeeReports.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-muted-foreground py-8">
                        گزارشی موجود نیست.
                      </TableCell>
                    </TableRow>
                  ) : (
                    employeeReports.map((employee) => (
                      <TableRow key={employee.employee_id}>
                        <TableCell>{employee.employee_name}</TableCell>
                        <TableCell>{employee.employee_email}</TableCell>
                        <TableCell>{employee.online_orders}</TableCell>
                        <TableCell>{employee.online_spent.toLocaleString()} تومان</TableCell>
                        <TableCell>{employee.in_person_transactions}</TableCell>
                        <TableCell>{employee.in_person_spent.toLocaleString()} تومان</TableCell>
                        <TableCell className="font-bold">{employee.total_spent.toLocaleString()} تومان</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Merchant Reports Tab */}
      {activeTab === 'merchant-reports' && (
        <div className="space-y-6">
          <Card className="border-0 shadow-elevated">
            <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10">
              <CardTitle className="text-xl">گزارشات فروشگاه‌ها</CardTitle>
              <p className="text-sm text-muted-foreground mt-1">فروشگاه‌هایی که بیشترین فروش را داشته‌اند</p>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>نام فروشگاه</TableHead>
                    <TableHead>تعداد سفارش</TableHead>
                    <TableHead>مبلغ کل فروش</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {topMerchants.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={3} className="text-center text-muted-foreground py-8">
                        گزارشی موجود نیست.
                      </TableCell>
                    </TableRow>
                  ) : (
                    topMerchants.map((merchant) => (
                      <TableRow key={merchant.merchant_id}>
                        <TableCell className="font-medium">{merchant.store_name || `فروشگاه ${merchant.merchant_id}`}</TableCell>
                        <TableCell>{merchant.order_count}</TableCell>
                        <TableCell className="font-bold">{merchant.total_sales.toLocaleString()} تومان</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Online Access Tab */}
      {activeTab === 'online-access' && (
        <div className="space-y-6">
          <Card className="border-0 shadow-elevated">
            <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10">
              <CardTitle className="text-xl">مدیریت دسترسی خرید آنلاین</CardTitle>
              <p className="text-sm text-muted-foreground mt-1">کنترل دسترسی کارمندان به خرید آنلاین از پذیرنده‌ها</p>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>نام کارمند</TableHead>
                    <TableHead>کد ملی</TableHead>
                    <TableHead>موجودی</TableHead>
                    <TableHead>دسترسی خرید آنلاین</TableHead>
                    <TableHead>عملیات</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {employees.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                        کارمندی ثبت نشده است.
                      </TableCell>
                    </TableRow>
                  ) : (
                    employees.map((employee) => (
                      <EmployeeOnlineAccessRow
                        key={employee.id}
                        employee={employee}
                        onUpdate={(hasAccess) =>
                          updateEmployeeOnlineAccessMutation.mutate({ employeeId: employee.id, hasAccess })
                        }
                        isUpdating={updateEmployeeOnlineAccessMutation.isPending}
                      />
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      )}
    </DashboardLayout>
  );
};

// Employee Online Access Row Component
const EmployeeOnlineAccessRow = ({
  employee,
  onUpdate,
  isUpdating
}: {
  employee: CompanyEmployee;
  onUpdate: (hasAccess: boolean) => void;
  isUpdating: boolean;
}) => {
  const [hasAccess, setHasAccess] = useState<boolean | null>(null);

  const { data: accessData } = useQuery<{ has_access: boolean }>({
    queryKey: ['company', 'employee-online-access', employee.id],
    queryFn: async () => {
      const response = await apiClient.get(`/company/employees/${employee.id}/online-access`);
      const data = unwrapWordPressObject<{ data?: { has_access: boolean }; has_access?: boolean }>(response.data);
      return {
        has_access: data?.data?.has_access ?? data?.has_access ?? false
      };
    }
  });

  useEffect(() => {
    if (accessData) {
      setHasAccess(accessData.has_access);
    }
  }, [accessData]);

  return (
    <TableRow>
      <TableCell>{employee.name}</TableCell>
      <TableCell>{employee.national_id}</TableCell>
      <TableCell>{employee.balance.toLocaleString()} تومان</TableCell>
      <TableCell>
        {hasAccess === null ? (
          <span className="text-muted-foreground">—</span>
        ) : hasAccess ? (
          <span className="text-green-600 font-semibold">✓ فعال</span>
        ) : (
          <span className="text-gray-400">✗ غیرفعال</span>
        )}
      </TableCell>
      <TableCell>
        <div className="flex gap-2">
          <Button
            size="sm"
            variant={hasAccess ? 'outline' : 'default'}
            onClick={() => onUpdate(!hasAccess)}
            disabled={isUpdating || hasAccess === null}
          >
            {hasAccess ? 'غیرفعال کردن' : 'فعال کردن'}
          </Button>
        </div>
      </TableCell>
    </TableRow>
  );
};

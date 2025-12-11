import { useState, useEffect, useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Select } from '../../components/ui/select';
import { Label } from '../../components/ui/label';
import { apiClient } from '../../api/client';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';
import { formatDateTime } from '../../utils/format';
import { cn } from '../../utils/cn';
import * as Dialog from '@radix-ui/react-dialog';
import { iranProvinces, getCitiesByProvinceId } from '../../lib/iran-cities';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
  status?: string;
}

interface CategoryBalance {
  category_id: number;
  category_name: string;
  limit: number;
  spent: number;
  remaining: number;
}

interface CategoryBalanceResponse {
  wallet_balance: number;
  categories: CategoryBalance[];
}

interface Merchant {
  id: number;
  name: string;
  store_name: string;
  store_description?: string;
  store_image?: string;
  store_address?: string;
  phone?: string;
  email?: string;
  province_id?: number;
  city_id?: number;
  province_name?: string;
  city_name?: string;
}

interface Product {
  id: number;
  name: string;
  description?: string;
  image?: string;
  price?: number;
  merchant_id?: number;
  merchant_name?: string;
  store_name?: string;
  product_category_id?: number;
  category_name?: string;
  stock_quantity?: number;
  online_purchase_enabled?: boolean;
}

interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  product_name: string;
  price: number;
  subtotal: number;
  image?: string;
  stock_quantity: number;
  merchant_id: number;
  store_name: string;
  product_category_id?: number;
  category_name?: string;
}

interface Order {
  id: number;
  order_number: string;
  employee_id: number;
  merchant_id: number;
  store_name?: string;
  total_amount: number;
  customer_name: string;
  customer_family: string;
  customer_address: string;
  customer_mobile: string;
  customer_postal_code: string;
  tracking_code?: string;
  status: string;
  payment_status: string;
  created_at: string;
  updated_at: string;
  items: OrderItem[];
}

interface OrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_price: number;
  quantity: number;
  subtotal: number;
}

type TabType = 'dashboard' | 'stores' | 'cart' | 'orders' | 'checkout';

export const EmployeeDashboard = () => {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<TabType>('dashboard');
  const [selectedStore, setSelectedStore] = useState<Merchant | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [darkMode, setDarkMode] = useState(false);
  const [selectedProvinceFilter, setSelectedProvinceFilter] = useState<number | ''>('');
  const [selectedCityFilter, setSelectedCityFilter] = useState<number | ''>('');
  const [productSearchQuery, setProductSearchQuery] = useState('');
  const availableCitiesFilter = useMemo(() => {
    if (!selectedProvinceFilter) return [];
    return getCitiesByProvinceId(Number(selectedProvinceFilter));
  }, [selectedProvinceFilter]);

  // Cart and Order states
  const [checkoutForm, setCheckoutForm] = useState({
    customer_name: '',
    customer_family: '',
    customer_address: '',
    customer_mobile: '',
    customer_postal_code: ''
  });

  useEffect(() => {
    const isDark = localStorage.getItem('darkMode') === 'true' || window.matchMedia('(prefers-color-scheme: dark)').matches;
    setDarkMode(isDark);
    if (isDark) {
      document.documentElement.classList.add('dark');
    }
  }, []);

  const toggleDarkMode = () => {
    const newMode = !darkMode;
    setDarkMode(newMode);
    localStorage.setItem('darkMode', String(newMode));
    if (newMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  };

  const { data: categoryBalances } = useQuery({
    queryKey: ['employee', 'category-balances'],
    queryFn: async () => {
      const response = await apiClient.get('/employee/category-balances');
      const data = unwrapWordPressObject<CategoryBalanceResponse>(response.data);
      return {
        walletBalance: Number(data?.wallet_balance ?? 0),
        categories: (data?.categories ?? []).map((category) => ({
          category_id: Number(category.category_id ?? 0),
          category_name: String(category.category_name ?? ''),
          limit: Number(category.limit ?? 0),
          spent: Number(category.spent ?? 0),
          remaining: Number(category.remaining ?? 0)
        }))
      };
    }
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['employee', 'transactions'],
    queryFn: async () => {
      const response = await apiClient.get('/transactions/history');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((transaction) => ({
        id: Number(transaction.id ?? 0),
        type: String(transaction.type ?? ''),
        amount: Number(transaction.amount ?? 0),
        created_at: String(transaction.created_at ?? ''),
        description: transaction.description ? String(transaction.description) : undefined,
        status: transaction.status ? String(transaction.status) : undefined
      }));
    }
  });

  const { data: merchants = [] } = useQuery<Merchant[]>({
    queryKey: ['employee', 'merchants', selectedProvinceFilter, selectedCityFilter],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (selectedProvinceFilter) params.set('province_id', String(selectedProvinceFilter));
      if (selectedCityFilter) params.set('city_id', String(selectedCityFilter));
      const response = await apiClient.get(`/employee/merchants?${params.toString()}`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((merchant) => ({
        id: Number(merchant.id ?? 0),
        name: String(merchant.name ?? ''),
        store_name: String(merchant.store_name ?? ''),
        store_description: merchant.store_description ? String(merchant.store_description) : undefined,
        store_image: merchant.store_image ? String(merchant.store_image) : undefined,
        store_address: merchant.store_address ? String(merchant.store_address) : undefined,
        phone: merchant.phone ? String(merchant.phone) : undefined,
        email: merchant.email ? String(merchant.email) : undefined,
        province_id: merchant.province_id ? Number(merchant.province_id) : undefined,
        city_id: merchant.city_id ? Number(merchant.city_id) : undefined,
        province_name: merchant.province_name ? String(merchant.province_name) : undefined,
        city_name: merchant.city_name ? String(merchant.city_name) : undefined
      }));
    },
    enabled: activeTab === 'stores'
  });

  const { data: allProducts = [] } = useQuery<Array<Product & { merchant_id: number; merchant_name: string; store_name: string }>>({
    queryKey: ['employee', 'all-products', productSearchQuery],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (productSearchQuery) params.set('search', productSearchQuery);
      const response = await apiClient.get(`/employee/products/search?${params.toString()}`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((product) => ({
        id: Number(product.id ?? 0),
        name: String(product.name ?? ''),
        description: product.description ? String(product.description) : undefined,
        image: product.image ? String(product.image) : undefined,
        price: product.price ? Number(product.price) : undefined,
        merchant_id: Number(product.merchant_id ?? 0),
        merchant_name: String(product.merchant_name ?? ''),
        store_name: String(product.store_name ?? '')
      }));
    },
    enabled: activeTab === 'stores' && productSearchQuery.length > 0
  });

  const { data: storeProducts = [] } = useQuery<Product[]>({
    queryKey: ['employee', 'store-products', selectedStore?.id],
    queryFn: async () => {
      if (!selectedStore) return [];
      const response = await apiClient.get(`/employee/merchants/${selectedStore.id}/products`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((product) => ({
        id: Number(product.id ?? 0),
        name: String(product.name ?? ''),
        description: product.description ? String(product.description) : undefined,
        image: product.image ? String(product.image) : undefined,
        price: product.price ? Number(product.price) : undefined
      }));
    },
    enabled: Boolean(selectedStore)
  });

  // Online products query
  const { data: onlineProducts = [] } = useQuery<Product[]>({
    queryKey: ['employee', 'products', 'online'],
    queryFn: async () => {
      const response = await apiClient.get('/employee/products/online');
      return unwrapWordPressList<Product>(response.data);
    },
    enabled: activeTab === 'stores'
  });

  // Cart query
  const { data: cartItems = [] } = useQuery<CartItem[]>({
    queryKey: ['employee', 'cart'],
    queryFn: async () => {
      const response = await apiClient.get('/cart');
      return unwrapWordPressList<CartItem>(response.data);
    },
    enabled: activeTab === 'cart'
  });

  // Orders query
  const { data: orders = [] } = useQuery<Order[]>({
    queryKey: ['employee', 'orders'],
    queryFn: async () => {
      const response = await apiClient.get('/employee/orders');
      return unwrapWordPressList<Order>(response.data);
    },
    enabled: activeTab === 'orders'
  });

  const filteredProducts = storeProducts.filter((product) =>
    product.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  // Cart Mutations
  const addToCartMutation = useMutation({
    mutationFn: async ({ product_id, quantity }: { product_id: number; quantity: number }) => {
      await apiClient.post('/cart/add', { product_id, quantity });
    },
    onSuccess: () => {
      toast.success('محصول به سبد خرید اضافه شد.');
      queryClient.invalidateQueries({ queryKey: ['employee', 'cart'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'افزودن به سبد خرید با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const removeFromCartMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/cart/remove/${id}`);
    },
    onSuccess: () => {
      toast.success('محصول از سبد خرید حذف شد.');
      queryClient.invalidateQueries({ queryKey: ['employee', 'cart'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'حذف از سبد خرید با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const updateCartItemMutation = useMutation({
    mutationFn: async ({ id, quantity }: { id: number; quantity: number }) => {
      await apiClient.put(`/cart/update/${id}`, { quantity });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['employee', 'cart'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی سبد خرید با خطا مواجه شد.';
      toast.error(message);
    }
  });

  // Order Mutation
  const createOrderMutation = useMutation({
    mutationFn: async (payload: typeof checkoutForm) => {
      const response = await apiClient.post('/orders', payload);
      return response.data;
    },
    onSuccess: () => {
      toast.success('سفارش با موفقیت ثبت شد.');
      queryClient.invalidateQueries({ queryKey: ['employee', 'orders'] });
      queryClient.invalidateQueries({ queryKey: ['employee', 'cart'] });
      queryClient.invalidateQueries({ queryKey: ['employee', 'category-balances'] });
      setCheckoutForm({
        customer_name: '',
        customer_family: '',
        customer_address: '',
        customer_mobile: '',
        customer_postal_code: ''
      });
      setActiveTab('orders');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ثبت سفارش با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const tabs = [
    { id: 'dashboard' as TabType, label: 'داشبورد', icon: '📊' },
    { id: 'stores' as TabType, label: 'فروشگاه‌ها', icon: '🏪' },
    { id: 'cart' as TabType, label: 'سبد خرید', icon: '🛒' },
    { id: 'orders' as TabType, label: 'سفارشات من', icon: '📦' }
  ];

  return (
    <DashboardLayout>
      <div className={cn('min-h-screen transition-colors', darkMode ? 'dark bg-slate-900' : 'bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50')}>
        {/* Header with Dark Mode Toggle */}
        <div className="mb-6 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 p-4 text-white shadow-lg">
              <p className="text-sm font-medium">موجودی کیف پول</p>
              <p className="text-3xl font-bold">{categoryBalances?.walletBalance ?? 0}</p>
            </div>
          </div>
          <Button
            variant="outline"
            onClick={toggleDarkMode}
            className="rounded-full"
          >
            {darkMode ? '☀️' : '🌙'}
          </Button>
        </div>

        {/* Tabs */}
        <div className="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 dark:border-slate-700">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => {
                setActiveTab(tab.id);
                if (tab.id === 'dashboard') {
                  setSelectedStore(null);
                }
              }}
              className={cn(
                'flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 font-semibold transition-colors',
                activeTab === tab.id
                  ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400'
                  : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
              )}
            >
              <span>{tab.icon}</span>
              <span>{tab.label}</span>
            </button>
          ))}
        </div>

        {/* Dashboard Tab */}
        {activeTab === 'dashboard' && (
          <div className="space-y-6">
            <section className="mt-6 space-y-4">
              <h2 className="text-xl font-bold">سقف استفاده از دسته‌بندی‌ها</h2>
              {categoryBalances && categoryBalances.categories.length > 0 ? (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {categoryBalances.categories.map((category) => (
                    <Card key={category.category_id} className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
                      <CardHeader>
                        <CardTitle className="text-base font-medium">{category.category_name}</CardTitle>
                      </CardHeader>
                      <CardContent className="space-y-1 text-sm">
                        <p>
                          سقف تعریف‌شده: <span className="font-semibold">{category.limit}</span>
                        </p>
                        <p>
                          مصرف‌شده: <span className={cn('font-semibold', darkMode ? 'text-rose-400' : 'text-rose-600')}>{category.spent}</span>
                        </p>
                        <p>
                          باقی‌مانده: <span className={cn('font-semibold', darkMode ? 'text-emerald-400' : 'text-emerald-700')}>{category.remaining}</span>
                        </p>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              ) : (
                <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
                  <CardContent className="py-6 text-sm text-muted-foreground">
                    هنوز سقفی برای استفاده از دسته‌بندی‌ها تعیین نشده است.
                  </CardContent>
                </Card>
              )}
            </section>

            <section>
              <h2 className="mb-4 text-xl font-bold">تاریخچه تراکنش‌ها</h2>
              <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
                <CardContent className="p-0">
                  <Table>
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
                            <TableCell>{formatDateTime(transaction.created_at)}</TableCell>
                          </TableRow>
                        ))
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </section>
          </div>
        )}

        {/* Stores Tab */}
        {activeTab === 'stores' && (
          <div className="space-y-6">
            {/* Product Search */}
            <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
              <CardHeader>
                <CardTitle className="text-xl font-bold">جستجوی محصول در تمام فروشگاه‌ها</CardTitle>
              </CardHeader>
              <CardContent>
                <Input
                  placeholder="جستجوی محصول..."
                  value={productSearchQuery}
                  onChange={(e) => setProductSearchQuery(e.target.value)}
                  className="w-full"
                />
                {allProducts.length > 0 && (
                  <div className="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {allProducts.map((product: Product & { merchant_id: number; merchant_name: string; store_name: string }) => (
                      <Card
                        key={`${product.merchant_id}-${product.id}`}
                        className={cn('cursor-pointer transition-all hover:shadow-xl', darkMode ? 'bg-slate-700 hover:bg-slate-600' : 'bg-slate-50 hover:shadow-lg')}
                        onClick={() => setSelectedProduct(product)}
                      >
                        <CardContent className="p-4">
                          {product.image && (
                            <img
                              src={product.image}
                              alt={product.name}
                              className="mb-3 h-32 w-full rounded-lg object-cover"
                            />
                          )}
                          <h4 className="font-bold">{product.name}</h4>
                          <p className={cn('mt-1 text-sm', darkMode ? 'text-slate-400' : 'text-slate-600')}>
                            فروشگاه: {product.store_name}
                          </p>
                          {product.price && (
                            <p className={cn('mt-2 font-semibold', darkMode ? 'text-emerald-400' : 'text-emerald-600')}>
                              {product.price.toLocaleString()} تومان
                            </p>
                          )}
                        </CardContent>
                      </Card>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Filters */}
            <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
              <CardHeader>
                <CardTitle className="text-xl font-bold">فیلتر فروشگاه‌ها</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="province-filter">استان</Label>
                    <Select
                      id="province-filter"
                      value={selectedProvinceFilter}
                      onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                        const provinceId = e.target.value ? Number(e.target.value) : '';
                        setSelectedProvinceFilter(provinceId);
                        setSelectedCityFilter('');
                      }}
                    >
                      <option value="">همه استان‌ها</option>
                      {iranProvinces.map((province) => (
                        <option key={province.id} value={province.id}>
                          {province.name}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="city-filter">شهر</Label>
                    <Select
                      id="city-filter"
                      value={selectedCityFilter}
                      onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                        setSelectedCityFilter(e.target.value ? Number(e.target.value) : '');
                      }}
                      disabled={!selectedProvinceFilter}
                    >
                      <option value="">همه شهرها</option>
                      {availableCitiesFilter.map((city) => (
                        <option key={city.id} value={city.id}>
                          {city.name}
                        </option>
                      ))}
                    </Select>
                  </div>
                </div>
              </CardContent>
            </Card>

            {!selectedStore ? (
              <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {merchants.map((merchant) => (
                  <Card
                    key={merchant.id}
                    className={cn('cursor-pointer transition-all hover:shadow-xl', darkMode ? 'bg-slate-800 hover:bg-slate-700' : 'bg-white hover:shadow-lg')}
                    onClick={() => setSelectedStore(merchant)}
                  >
                    <CardContent className="p-6">
                      <div className="flex items-start gap-4">
                        {merchant.store_image ? (
                          <img
                            src={merchant.store_image}
                            alt={merchant.store_name}
                            className="h-20 w-20 rounded-lg object-cover"
                          />
                        ) : (
                          <div className={cn('flex h-20 w-20 items-center justify-center rounded-lg text-3xl', darkMode ? 'bg-slate-700' : 'bg-slate-200')}>
                            🏪
                          </div>
                        )}
                        <div className="flex-1">
                          <h3 className="text-lg font-bold">{merchant.store_name}</h3>
                          {merchant.store_description && (
                            <p className={cn('mt-2 line-clamp-2 text-sm', darkMode ? 'text-slate-400' : 'text-slate-600')}>
                              {merchant.store_description}
                            </p>
                          )}
                        </div>
                      </div>
                      <Button className="mt-4 w-full" variant="outline">
                        مشاهده محصولات
                      </Button>
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : (
              <div className="space-y-6">
                {/* Store Header */}
                <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
                  <CardContent className="p-6">
                    <Button
                      variant="ghost"
                      onClick={() => setSelectedStore(null)}
                      className="mb-4"
                    >
                      ← بازگشت به لیست فروشگاه‌ها
                    </Button>
                    <div className="flex flex-col gap-6 md:flex-row">
                      {selectedStore.store_image ? (
                        <img
                          src={selectedStore.store_image}
                          alt={selectedStore.store_name}
                          className="h-48 w-full rounded-lg object-cover md:w-48"
                        />
                      ) : (
                        <div className={cn('flex h-48 w-full items-center justify-center rounded-lg text-6xl md:w-48', darkMode ? 'bg-slate-700' : 'bg-slate-200')}>
                          🏪
                        </div>
                      )}
                      <div className="flex-1">
                        <h2 className="text-2xl font-bold">{selectedStore.store_name}</h2>
                        {selectedStore.store_description && (
                          <p className={cn('mt-2', darkMode ? 'text-slate-300' : 'text-slate-600')}>
                            {selectedStore.store_description}
                          </p>
                        )}
                        {selectedStore.store_address && (
                          <p className={cn('mt-2 text-sm', darkMode ? 'text-slate-400' : 'text-slate-500')}>
                            📍 {selectedStore.store_address}
                          </p>
                        )}
                        {selectedStore.phone && (
                          <p className={cn('mt-2 text-sm', darkMode ? 'text-slate-400' : 'text-slate-500')}>
                            📞 {selectedStore.phone}
                          </p>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>

                {/* Product Search */}
                <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
                  <CardContent className="p-6">
                    <Input
                      placeholder="جستجوی محصول..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="w-full"
                    />
                  </CardContent>
                </Card>

                {/* Products Grid */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                  {filteredProducts.length === 0 ? (
                    <Card className={cn('col-span-full shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
                      <CardContent className="py-12 text-center text-muted-foreground">
                        {searchQuery ? 'محصولی با این نام یافت نشد.' : 'هنوز محصولی در این فروشگاه ثبت نشده است.'}
                      </CardContent>
                    </Card>
                  ) : (
                    filteredProducts.map((product) => (
                      <Card
                        key={product.id}
                        className={cn('cursor-pointer transition-all hover:shadow-xl', darkMode ? 'bg-slate-800 hover:bg-slate-700' : 'bg-white hover:shadow-lg')}
                        onClick={() => setSelectedProduct(product)}
                      >
                        <CardContent className="p-6">
                          {product.image ? (
                            <img
                              src={product.image}
                              alt={product.name}
                              className="mb-4 h-48 w-full rounded-lg object-cover"
                            />
                          ) : (
                            <div className={cn('mb-4 flex h-48 w-full items-center justify-center rounded-lg text-4xl', darkMode ? 'bg-slate-700' : 'bg-slate-200')}>
                              📦
                            </div>
                          )}
                          <h3 className="text-lg font-bold">{product.name}</h3>
                          {product.price && (
                            <p className={cn('mt-2 text-lg font-semibold', darkMode ? 'text-emerald-400' : 'text-emerald-600')}>
                              {product.price.toLocaleString()} تومان
                            </p>
                          )}
                          {product.online_purchase_enabled && (
                            <Button
                              className="mt-4 w-full"
                              onClick={(e) => {
                                e.stopPropagation();
                                addToCartMutation.mutate({ product_id: product.id, quantity: 1 });
                              }}
                              disabled={addToCartMutation.isPending}
                            >
                              افزودن به سبد خرید
                            </Button>
                          )}
                        </CardContent>
                      </Card>
                    ))
                  )}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Cart Tab */}
        {activeTab === 'cart' && (
          <div className="space-y-6">
            <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
              <CardHeader>
                <CardTitle className="text-xl font-bold">سبد خرید</CardTitle>
              </CardHeader>
              <CardContent>
                {cartItems.length === 0 ? (
                  <p className="text-center text-muted-foreground py-8">سبد خرید شما خالی است.</p>
                ) : (
                  <div className="space-y-4">
                    {cartItems.map((item) => (
                      <div
                        key={item.id}
                        className={cn('flex items-center gap-4 rounded-lg border p-4', darkMode ? 'border-slate-700' : 'border-slate-200')}
                      >
                        {item.image && (
                          <img src={item.image} alt={item.product_name} className="h-20 w-20 rounded-lg object-cover" />
                        )}
                        <div className="flex-1">
                          <h4 className="font-semibold">{item.product_name}</h4>
                          <p className="text-sm text-muted-foreground">{item.store_name}</p>
                          <p className="mt-1 font-semibold">{item.price.toLocaleString()} تومان</p>
                        </div>
                        <div className="flex items-center gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              if (item.quantity > 1) {
                                updateCartItemMutation.mutate({ id: item.id, quantity: item.quantity - 1 });
                              }
                            }}
                            disabled={item.quantity <= 1}
                          >
                            -
                          </Button>
                          <span className="w-12 text-center">{item.quantity}</span>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              if (item.quantity < item.stock_quantity) {
                                updateCartItemMutation.mutate({ id: item.id, quantity: item.quantity + 1 });
                              }
                            }}
                            disabled={item.quantity >= item.stock_quantity}
                          >
                            +
                          </Button>
                        </div>
                        <div className="text-left">
                          <p className="font-bold">{item.subtotal.toLocaleString()} تومان</p>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => removeFromCartMutation.mutate(item.id)}
                            className="mt-2"
                          >
                            حذف
                          </Button>
                        </div>
                      </div>
                    ))}
                    <div className="border-t pt-4">
                      <div className="flex justify-between text-lg font-bold">
                        <span>جمع کل:</span>
                        <span>
                          {cartItems.reduce((sum, item) => sum + item.subtotal, 0).toLocaleString()} تومان
                        </span>
                      </div>
                      <Button
                        className="mt-4 w-full"
                        onClick={() => {
                          if (cartItems.length > 0) {
                            setActiveTab('checkout');
                          }
                        }}
                        disabled={cartItems.length === 0}
                      >
                        تسویه حساب
                      </Button>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}

        {/* Checkout Tab (shown when clicking checkout from cart) */}
        {activeTab === 'checkout' && cartItems.length > 0 && (
          <div className="space-y-6">
            <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
              <CardHeader>
                <CardTitle className="text-xl font-bold">تسویه حساب</CardTitle>
              </CardHeader>
              <CardContent>
                <form
                  className="space-y-4"
                  onSubmit={(e) => {
                    e.preventDefault();
                    createOrderMutation.mutate(checkoutForm);
                  }}
                >
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="customer_name">نام</Label>
                      <Input
                        id="customer_name"
                        required
                        value={checkoutForm.customer_name}
                        onChange={(e) => setCheckoutForm({ ...checkoutForm, customer_name: e.target.value })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="customer_family">نام خانوادگی</Label>
                      <Input
                        id="customer_family"
                        required
                        value={checkoutForm.customer_family}
                        onChange={(e) => setCheckoutForm({ ...checkoutForm, customer_family: e.target.value })}
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="customer_address">آدرس</Label>
                    <textarea
                      id="customer_address"
                      required
                      rows={3}
                      className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={checkoutForm.customer_address}
                      onChange={(e) => setCheckoutForm({ ...checkoutForm, customer_address: e.target.value })}
                    />
                  </div>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="customer_mobile">شماره موبایل</Label>
                      <Input
                        id="customer_mobile"
                        required
                        value={checkoutForm.customer_mobile}
                        onChange={(e) => setCheckoutForm({ ...checkoutForm, customer_mobile: e.target.value })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="customer_postal_code">کد پستی</Label>
                      <Input
                        id="customer_postal_code"
                        required
                        value={checkoutForm.customer_postal_code}
                        onChange={(e) => setCheckoutForm({ ...checkoutForm, customer_postal_code: e.target.value })}
                      />
                    </div>
                  </div>
                  <div className="rounded-lg border p-4">
                    <h3 className="font-semibold mb-2">خلاصه سفارش:</h3>
                    {cartItems.map((item) => (
                      <div key={item.id} className="flex justify-between text-sm">
                        <span>{item.product_name} × {item.quantity}</span>
                        <span>{item.subtotal.toLocaleString()} تومان</span>
                      </div>
                    ))}
                    <div className="mt-2 flex justify-between border-t pt-2 font-bold">
                      <span>جمع کل:</span>
                      <span>{cartItems.reduce((sum, item) => sum + item.subtotal, 0).toLocaleString()} تومان</span>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setActiveTab('cart')}
                    >
                      بازگشت به سبد خرید
                    </Button>
                    <Button
                      type="submit"
                      className="flex-1"
                      disabled={createOrderMutation.isPending}
                    >
                      {createOrderMutation.isPending ? 'در حال ثبت سفارش...' : 'ثبت سفارش'}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Orders Tab */}
        {activeTab === 'orders' && (
          <div className="space-y-6">
            <Card className={cn('shadow-lg', darkMode ? 'bg-slate-800' : 'bg-white')}>
              <CardHeader>
                <CardTitle className="text-xl font-bold">سفارشات من</CardTitle>
              </CardHeader>
              <CardContent>
                {orders.length === 0 ? (
                  <p className="text-center text-muted-foreground py-8">سفارشی ثبت نشده است.</p>
                ) : (
                  <div className="space-y-4">
                    {orders.map((order) => (
                      <Card key={order.id} className={cn(darkMode ? 'bg-slate-700' : 'bg-slate-50')}>
                        <CardContent className="p-4">
                          <div className="flex items-start justify-between">
                            <div className="flex-1">
                              <div className="flex items-center gap-2">
                                <h4 className="font-bold">شماره سفارش: {order.order_number}</h4>
                                <span
                                  className={cn(
                                    'rounded-full px-2 py-1 text-xs font-semibold',
                                    order.status === 'delivered'
                                      ? 'bg-green-100 text-green-800'
                                      : order.status === 'shipped'
                                        ? 'bg-blue-100 text-blue-800'
                                        : order.status === 'processing'
                                          ? 'bg-yellow-100 text-yellow-800'
                                          : 'bg-gray-100 text-gray-800'
                                  )}
                                >
                                  {order.status === 'pending'
                                    ? 'در انتظار'
                                    : order.status === 'processing'
                                      ? 'در حال پردازش'
                                      : order.status === 'shipped'
                                        ? 'ارسال شده'
                                        : order.status === 'delivered'
                                          ? 'تحویل داده شده'
                                          : order.status}
                                </span>
                              </div>
                              <p className="mt-2 text-sm text-muted-foreground">
                                فروشگاه: {order.store_name || '—'}
                              </p>
                              <p className="text-sm">مبلغ کل: {order.total_amount.toLocaleString()} تومان</p>
                              {order.tracking_code && (
                                <p className="text-sm">کد پیگیری: {order.tracking_code}</p>
                              )}
                              <p className="text-xs text-muted-foreground mt-2">
                                تاریخ: {new Date(order.created_at).toLocaleDateString('fa-IR')}
                              </p>
                            </div>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => setSelectedProduct(order as any)}
                            >
                              جزئیات
                            </Button>
                          </div>
                        </CardContent>
                      </Card>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}

        {/* Product Detail Modal */}
        <Dialog.Root open={Boolean(selectedProduct)} onOpenChange={() => setSelectedProduct(null)}>
          <Dialog.Portal>
            <Dialog.Overlay className="fixed inset-0 z-40 bg-black/40" />
            <Dialog.Content
              className={cn(
                'fixed left-1/2 top-1/2 z-50 w-[90vw] max-w-2xl -translate-x-1/2 -translate-y-1/2 rounded-lg border p-6 shadow-lg',
                darkMode ? 'bg-slate-800' : 'bg-white'
              )}
            >
              {selectedProduct && (
                <>
                  <Dialog.Title className="text-2xl font-bold">{selectedProduct.name}</Dialog.Title>
                  {selectedProduct.image && (
                    <img
                      src={selectedProduct.image}
                      alt={selectedProduct.name}
                      className="my-4 h-64 w-full rounded-lg object-cover"
                    />
                  )}
                  {selectedProduct.description && (
                    <Dialog.Description className={cn('mt-4 text-base', darkMode ? 'text-slate-300' : 'text-slate-600')}>
                      {selectedProduct.description}
                    </Dialog.Description>
                  )}
                  {selectedProduct.price && (
                    <p className={cn('mt-4 text-2xl font-bold', darkMode ? 'text-emerald-400' : 'text-emerald-600')}>
                      {selectedProduct.price.toLocaleString()} تومان
                    </p>
                  )}
                  <div className="mt-6 flex justify-end">
                    <Dialog.Close asChild>
                      <Button variant="outline">بستن</Button>
                    </Dialog.Close>
                  </div>
                </>
              )}
            </Dialog.Content>
          </Dialog.Portal>
        </Dialog.Root>
      </div>
    </DashboardLayout>
  );
};

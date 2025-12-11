import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Button } from '../../components/ui/button';
import { Select } from '../../components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { PayoutStatusBadge } from '../../components/common/PayoutStatusBadge';
import { OTPModal } from '../../components/common/OTPModal';
import { apiClient } from '../../api/client';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';
import { cn } from '../../utils/cn';
import { iranProvinces, getCitiesByProvinceId, type Province, type City } from '../../lib/iran-cities';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
  status?: string;
}

interface PayoutStatus {
  id: number;
  amount: number;
  status: string;
  created_at: string;
}

interface BalancePayload {
  balance?: number;
  new_balance?: number;
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface MerchantCategories {
  assigned: Category[];
  available: Category[];
}

interface PreviewPayload {
  employee_national_id: string;
  category_id: number;
}

interface PreviewResponse {
  employee_id: number;
  employee_name: string;
  category_id: number;
  limit_defined: boolean;
  limit: number;
  spent: number;
  remaining: number;
  wallet_balance: number;
  available_amount: number;
}

interface PaymentPayload {
  employee_national_id: string;
  category_id: number;
  amount: number;
}

interface MerchantProfile {
  store_name?: string;
  store_address?: string;
  phone?: string;
  mobile?: string;
  email?: string;
  store_description?: string;
  store_slogan?: string;
  store_image?: string;
  store_images?: string[];
  province_id?: number;
  city_id?: number;
  products?: Array<{
    id: number;
    name: string;
    description?: string;
    image?: string;
    price?: number;
  }>;
}

interface RevenueData {
  date: string;
  amount: number;
}

interface RevenueStats {
  daily: RevenueData[];
  monthly: RevenueData[];
  yearly: RevenueData[];
}

interface ProductCategory {
  id: number;
  name: string;
  slug: string;
  description?: string;
}

interface Product {
  id: number;
  merchant_id: number;
  product_category_id?: number;
  category_name?: string;
  category_slug?: string;
  name: string;
  description?: string;
  price: number;
  image?: string;
  stock_quantity: number;
  online_purchase_enabled: boolean;
  status: string;
  created_at?: string;
  updated_at?: string;
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

type TabType = 'payment' | 'products' | 'orders' | 'profile' | 'transactions' | 'payouts' | 'analytics';

export const MerchantDashboard = () => {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<TabType>('payment');
  const [nationalId, setNationalId] = useState('');
  const [amountValue, setAmountValue] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [previewInfo, setPreviewInfo] = useState<PreviewResponse | null>(null);
  const [categorySelection, setCategorySelection] = useState<number[]>([]);
  const [activeRequest, setActiveRequest] = useState<{ id: number; amount: number; employeeName?: string } | null>(null);
  const [otpOpen, setOtpOpen] = useState(false);

  // Merchant profile state
  const [profile, setProfile] = useState<MerchantProfile>({});
  const [profileImage, setProfileImage] = useState<File | null>(null);
  const [profileImages, setProfileImages] = useState<File[]>([]);
  const [products, setProducts] = useState<Array<{ name: string; description: string; image?: File; price?: number }>>([]);
  const [selectedProvinceId, setSelectedProvinceId] = useState<number | ''>('');
  const [selectedCityId, setSelectedCityId] = useState<number | ''>('');
  const availableCities = useMemo(() => {
    if (!selectedProvinceId) return [];
    return getCitiesByProvinceId(Number(selectedProvinceId));
  }, [selectedProvinceId]);

  // Products management state
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [productForm, setProductForm] = useState({
    name: '',
    description: '',
    price: '',
    product_category_id: '',
    stock_quantity: '',
    online_purchase_enabled: false,
    image: null as File | null
  });

  // Orders state
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [orderStatus, setOrderStatus] = useState('');
  const [trackingCode, setTrackingCode] = useState('');


  useEffect(() => {
    setPreviewInfo(null);
  }, [nationalId, selectedCategory]);

  const { data: balance } = useQuery({
    queryKey: ['merchant', 'balance'],
    queryFn: async () => {
      const response = await apiClient.get('/wallet/balance');
      const data = unwrapWordPressObject<BalancePayload>(response.data);
      if (!data) {
        return { balance: 0 };
      }

      return {
        balance: Number(data.balance ?? data.new_balance ?? 0)
      };
    }
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['merchant', 'transactions'],
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

  const { data: payoutStatus = [] } = useQuery({
    queryKey: ['merchant', 'payout-status'],
    queryFn: async () => {
      const response = await apiClient.get('/payout/status');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((payout) => ({
        id: Number(payout.id ?? 0),
        amount: Number(payout.amount ?? 0),
        status: String(payout.status ?? ''),
        created_at: String(payout.created_at ?? '')
      }));
    }
  });

  const { data: categoriesData } = useQuery({
    queryKey: ['merchant', 'categories'],
    queryFn: async () => {
      const response = await apiClient.get('/merchant/categories');
      const data = unwrapWordPressObject<MerchantCategories>(response.data);
      return {
        assigned: (data?.assigned ?? []).map((item) => ({
          id: Number(item.id ?? 0),
          name: String(item.name ?? ''),
          slug: String(item.slug ?? '')
        })),
        available: (data?.available ?? []).map((item) => ({
          id: Number(item.id ?? 0),
          name: String(item.name ?? ''),
          slug: String(item.slug ?? '')
        }))
      } satisfies MerchantCategories;
    }
  });

  const assignedCategories = categoriesData?.assigned ?? [];
  const availableCategories = categoriesData?.available ?? [];

  useEffect(() => {
    setCategorySelection(assignedCategories.map((category) => category.id));
  }, [assignedCategories]);

  const { data: merchantProfile } = useQuery<MerchantProfile | null>({
    queryKey: ['merchant', 'profile'],
    queryFn: async () => {
      const response = await apiClient.get('/merchant/profile');
      const data = unwrapWordPressObject<MerchantProfile>(response.data);
      return data ?? null;
    },
    enabled: activeTab === 'profile'
  });

  useEffect(() => {
    if (merchantProfile && activeTab === 'profile') {
      setProfile(merchantProfile);
      if (merchantProfile.province_id) {
        setSelectedProvinceId(merchantProfile.province_id);
      }
      if (merchantProfile.city_id) {
        setSelectedCityId(merchantProfile.city_id);
      }
    }
  }, [merchantProfile, activeTab]);

  const { data: revenueStats } = useQuery<RevenueStats>({
    queryKey: ['merchant', 'revenue-stats'],
    queryFn: async () => {
      const response = await apiClient.get('/merchant/revenue-stats');
      const data = unwrapWordPressObject<RevenueStats>(response.data);
      return {
        daily: (data?.daily ?? []).map((item) => ({
          date: String(item.date ?? ''),
          amount: Number(item.amount ?? 0)
        })),
        monthly: (data?.monthly ?? []).map((item) => ({
          date: String(item.date ?? ''),
          amount: Number(item.amount ?? 0)
        })),
        yearly: (data?.yearly ?? []).map((item) => ({
          date: String(item.date ?? ''),
          amount: Number(item.amount ?? 0)
        }))
      };
    },
    enabled: activeTab === 'analytics'
  });

  // Product Categories Query
  const { data: productCategories = [] } = useQuery<ProductCategory[]>({
    queryKey: ['product-categories'],
    queryFn: async () => {
      const response = await apiClient.get('/product-categories');
      return unwrapWordPressList<ProductCategory>(response.data);
    }
  });

  // Products Query
  const { data: merchantProducts = [] } = useQuery<Product[]>({
    queryKey: ['merchant', 'products'],
    queryFn: async () => {
      const response = await apiClient.get('/merchant/products');
      return unwrapWordPressList<Product>(response.data);
    },
    enabled: activeTab === 'products'
  });

  // Orders Query
  const { data: merchantOrders = [] } = useQuery<Order[]>({
    queryKey: ['merchant', 'orders'],
    queryFn: async () => {
      const response = await apiClient.get('/merchant/orders');
      return unwrapWordPressList<Order>(response.data);
    },
    enabled: activeTab === 'orders'
  });

  const previewMutation = useMutation({
    mutationFn: async (payload: PreviewPayload) => {
      const response = await apiClient.post('/payment/preview', payload);
      const data = unwrapWordPressObject<PreviewResponse>(response.data);
      if (!data) {
        throw new Error('PREVIEW_FAILED');
      }
      return data;
    },
    onSuccess: (data) => {
      setPreviewInfo(data);
      toast.success('سقف قابل استفاده به‌روزرسانی شد.');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'استعلام امکان‌پذیر نبود.';
      toast.error(message);
    }
  });

  const paymentMutation = useMutation({
    mutationFn: async (payload: PaymentPayload) => {
      const response = await apiClient.post('/payment/request', payload);
      return unwrapWordPressObject<{ request_id: number; remaining?: number; wallet_balance?: number }>(response.data);
    },
    onSuccess: (data, variables) => {
      toast.success('کد تأیید برای مشتری ارسال شد.');
      setActiveRequest({
        id: Number(data?.request_id ?? 0),
        amount: variables.amount,
        employeeName: previewInfo?.employee_name ?? undefined
      });
      setOtpOpen(true);
      queryClient.invalidateQueries({ queryKey: ['merchant', 'transactions'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ثبت درخواست پرداخت با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const updateCategoriesMutation = useMutation({
    mutationFn: async (payload: { category_ids: number[] }) => {
      await apiClient.post('/merchant/categories', payload);
    },
    onSuccess: () => {
      toast.success('دسته‌بندی‌های پذیرنده به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'categories'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی دسته‌بندی‌ها ممکن نشد.';
      toast.error(message);
    }
  });

  const confirmMutation = useMutation({
    mutationFn: async (payload: { request_id: number; otp_code: string }) => {
      await apiClient.post('/payment/confirm', payload);
    },
    onSuccess: () => {
      toast.success('پرداخت با موفقیت انجام شد.');
      setOtpOpen(false);
      setActiveRequest(null);
      setPreviewInfo(null);
      setAmountValue('');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'transactions'] });
      queryClient.invalidateQueries({ queryKey: ['merchant', 'balance'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'تأیید پرداخت با خطا روبه‌رو شد.';
      toast.error(message);
    }
  });

  const payoutMutation = useMutation({
    mutationFn: async (payload: { amount: number; bank_account: string }) => {
      await apiClient.post('/payout/request', payload);
    },
    onSuccess: () => {
      toast.success('درخواست تسویه ثبت شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'payout-status'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ثبت درخواست تسویه با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const profileMutation = useMutation({
    mutationFn: async (formData: FormData) => {
      const response = await apiClient.post('/merchant/profile', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      return unwrapWordPressObject<MerchantProfile>(response.data);
    },
    onSuccess: (data: MerchantProfile | null) => {
      toast.success('اطلاعات فروشگاه با موفقیت به‌روزرسانی شد.');
      if (data) {
        setProfile(data);
      }
    },
    onError: (error: unknown) => {
      const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'به‌روزرسانی اطلاعات فروشگاه با خطا مواجه شد.';
      toast.error(message);
    }
  });

  // Product Mutations
  const createProductCategoryMutation = useMutation({
    mutationFn: async (payload: { name: string; description?: string }) => {
      const response = await apiClient.post('/product-categories', payload);
      return unwrapWordPressObject<ProductCategory>(response.data);
    },
    onSuccess: () => {
      toast.success('دسته‌بندی محصول با موفقیت ایجاد شد.');
      queryClient.invalidateQueries({ queryKey: ['product-categories'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ایجاد دسته‌بندی با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const createProductMutation = useMutation({
    mutationFn: async (formData: FormData) => {
      const response = await apiClient.post('/merchant/products', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      return response.data;
    },
    onSuccess: () => {
      toast.success('محصول با موفقیت ایجاد شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'products'] });
      setProductForm({
        name: '',
        description: '',
        price: '',
        product_category_id: '',
        stock_quantity: '',
        online_purchase_enabled: false,
        image: null
      });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ایجاد محصول با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const updateProductMutation = useMutation({
    mutationFn: async ({ id, formData }: { id: number; formData: FormData }) => {
      const response = await apiClient.put(`/merchant/products/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      return response.data;
    },
    onSuccess: () => {
      toast.success('محصول با موفقیت به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'products'] });
      setEditingProduct(null);
      setProductForm({
        name: '',
        description: '',
        price: '',
        product_category_id: '',
        stock_quantity: '',
        online_purchase_enabled: false,
        image: null
      });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی محصول با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const deleteProductMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/merchant/products/${id}`);
    },
    onSuccess: () => {
      toast.success('محصول با موفقیت حذف شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'products'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'حذف محصول با خطا مواجه شد.';
      toast.error(message);
    }
  });

  // Order Mutations
  const updateOrderStatusMutation = useMutation({
    mutationFn: async ({ id, status, tracking_code }: { id: number; status?: string; tracking_code?: string }) => {
      const payload: any = {};
      if (status) payload.status = status;
      if (tracking_code !== undefined) payload.tracking_code = tracking_code;
      await apiClient.put(`/orders/${id}`, payload);
    },
    onSuccess: () => {
      toast.success('وضعیت سفارش به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'orders'] });
      setSelectedOrder(null);
      setOrderStatus('');
      setTrackingCode('');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی وضعیت سفارش با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const handlePreview = () => {
    if (!nationalId || !selectedCategory) {
      toast.error('کد ملی و دسته‌بندی را مشخص کنید.');
      return;
    }

    previewMutation.mutate({
      employee_national_id: nationalId,
      category_id: Number(selectedCategory)
    });
  };

  const handlePaymentRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!selectedCategory) {
      toast.error('لطفاً دسته‌بندی پذیرنده را انتخاب کنید.');
      return;
    }

    const amount = Number(amountValue);
    if (!nationalId || amount <= 0) {
      toast.error('اطلاعات مشتری و مبلغ باید وارد شود.');
      return;
    }

    await paymentMutation.mutateAsync({
      employee_national_id: nationalId,
      category_id: Number(selectedCategory),
      amount
    });
  };

  const handlePayoutRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const amount = Number(form.get('amount') ?? 0);
    const bank_account = String(form.get('bank_account') ?? '');
    await payoutMutation.mutateAsync({ amount, bank_account });
    event.currentTarget.reset();
  };

  const handleProfileSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    
    if (profileImage) {
      form.append('store_image', profileImage);
    }
    profileImages.forEach((img: File, idx: number) => {
      form.append(`store_images_${idx}`, img);
    });
    
    if (selectedProvinceId) {
      form.append('province_id', String(selectedProvinceId));
    }
    if (selectedCityId) {
      form.append('city_id', String(selectedCityId));
    }
    
    products.forEach((product: { name: string; description: string; image?: File; price?: number }, idx: number) => {
      form.append(`products[${idx}][name]`, product.name);
      form.append(`products[${idx}][description]`, product.description);
      if (product.price) {
        form.append(`products[${idx}][price]`, String(product.price));
      }
      if (product.image) {
        form.append(`products[${idx}][image]`, product.image);
      }
    });

    await profileMutation.mutateAsync(form);
    queryClient.invalidateQueries({ queryKey: ['merchant', 'profile'] });
  };

  const categorySelectOptions = useMemo(() => {
    if (assignedCategories.length === 0) {
      return [<option key="none" value="">هیچ دسته‌بندی فعالی ثبت نشده است</option>];
    }

    return [
      <option key="placeholder" value="">
        انتخاب دسته‌بندی
      </option>,
      ...assignedCategories.map((category) => (
        <option key={category.id} value={category.id}>
          {category.name}
        </option>
      ))
    ];
  }, [assignedCategories]);

  const toggleCategorySelection = (categoryId: number) => {
    setCategorySelection((prev) => {
      if (prev.includes(categoryId)) {
        return prev.filter((id) => id !== categoryId);
      }
      return [...prev, categoryId];
    });
  };

  const handleCategoriesSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    await updateCategoriesMutation.mutateAsync({ category_ids: categorySelection });
  };

  // Product Handlers
  const handleCreateProductCategory = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const name = String(form.get('category_name') ?? '');
    const description = String(form.get('category_description') ?? '');
    if (!name) {
      toast.error('نام دسته‌بندی الزامی است.');
      return;
    }
    await createProductCategoryMutation.mutateAsync({ name, description: description || undefined });
    event.currentTarget.reset();
  };

  const handleProductSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formData = new FormData();
    formData.append('name', productForm.name);
    formData.append('description', productForm.description);
    formData.append('price', productForm.price);
    formData.append('stock_quantity', productForm.stock_quantity);
    formData.append('online_purchase_enabled', productForm.online_purchase_enabled ? '1' : '0');
    if (productForm.product_category_id) {
      formData.append('product_category_id', productForm.product_category_id);
    }
    if (productForm.image) {
      formData.append('image', productForm.image);
    }

    if (editingProduct) {
      await updateProductMutation.mutateAsync({ id: editingProduct.id, formData });
    } else {
      await createProductMutation.mutateAsync(formData);
    }
  };

  const handleEditProduct = (product: Product) => {
    setEditingProduct(product);
    setProductForm({
      name: product.name,
      description: product.description || '',
      price: product.price.toString(),
      product_category_id: product.product_category_id?.toString() || '',
      stock_quantity: product.stock_quantity.toString(),
      online_purchase_enabled: product.online_purchase_enabled,
      image: null
    });
  };

  const handleDeleteProduct = async (id: number) => {
    if (confirm('آیا از حذف این محصول اطمینان دارید؟')) {
      await deleteProductMutation.mutateAsync(id);
    }
  };

  // Order Handlers
  const handleUpdateOrderStatus = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!selectedOrder) return;
    await updateOrderStatusMutation.mutateAsync({
      id: selectedOrder.id,
      status: orderStatus || undefined,
      tracking_code: trackingCode || undefined
    });
  };

  const tabs = [
    { id: 'payment' as TabType, label: 'درخواست پرداخت', icon: '💳' },
    { id: 'products' as TabType, label: 'مدیریت محصولات', icon: '📦' },
    { id: 'orders' as TabType, label: 'سفارشات آنلاین', icon: '🛒' },
    { id: 'profile' as TabType, label: 'اطلاعات فروشگاه', icon: '🏪' },
    { id: 'analytics' as TabType, label: 'آمار و نمودارها', icon: '📈' },
    { id: 'transactions' as TabType, label: 'تراکنش‌ها', icon: '📊' },
    { id: 'payouts' as TabType, label: 'تسویه‌ها', icon: '💰' }
  ];

  return (
    <DashboardLayout 
      sidebarTabs={tabs}
      activeTab={activeTab}
      onTabChange={(tabId) => setActiveTab(tabId as TabType)}
    >
      <div className="min-h-screen">
        {/* Balance Card */}
        <div className="mb-6">
          <Card className="border border-[#E5E7EB] bg-white shadow-sm">
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-[#6B7280]">موجودی کیف پول</p>
                  <p className="mt-2 text-3xl font-bold text-[#1F2937]">{balance?.balance ?? 0}</p>
                </div>
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-[#EEF2FF]">
                  <span className="text-2xl">💰</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Payment Tab - Full Width and Bold */}
        {activeTab === 'payment' && (
          <div className="space-y-6">
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] bg-white">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">درخواست پرداخت از مشتری</CardTitle>
              </CardHeader>
              <CardContent>
                <form className="space-y-6" onSubmit={handlePaymentRequest}>
                  <div className="grid gap-6 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="national_id" className="text-base font-semibold">کد ملی مشتری</Label>
                      <Input
                        id="national_id"
                        name="national_id"
                        required
                        placeholder="1234567890"
                        value={nationalId}
                        onChange={(event) => setNationalId(event.target.value)}
                        className="h-12 text-lg"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="category_id" className="text-base font-semibold">دسته‌بندی پذیرنده</Label>
                      <Select
                        id="category_id"
                        name="category_id"
                        value={selectedCategory}
                        onChange={(event) => setSelectedCategory(event.target.value)}
                        className="h-12 text-lg"
                      >
                        {categorySelectOptions}
                      </Select>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="amount" className="text-base font-semibold">مبلغ</Label>
                    <Input
                      id="amount"
                      name="amount"
                      type="number"
                      required
                      min={0}
                      step={1000}
                      value={amountValue}
                      onChange={(event) => setAmountValue(event.target.value)}
                      className="h-12 text-2xl font-bold"
                    />
                  </div>
                  <div className="flex flex-col gap-3 sm:flex-row">
                    <Button
                      type="button"
                      variant="outline"
                      className="h-12 flex-1 text-base font-semibold"
                      onClick={handlePreview}
                      disabled={previewMutation.isPending}
                    >
                      {previewMutation.isPending ? 'در حال استعلام…' : 'استعلام موجودی'}
                    </Button>
                    <Button
                      type="submit"
                      className="h-12 flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-base font-bold text-white hover:from-blue-700 hover:to-purple-700"
                      disabled={paymentMutation.isPending}
                    >
                      {paymentMutation.isPending ? 'در حال ارسال…' : 'ثبت درخواست پرداخت'}
                    </Button>
                  </div>
                </form>

                {previewInfo && (
                  <div className="mt-6 space-y-2 rounded-lg border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                    <p className="text-base font-semibold">
                      <span className="font-bold">مشتری:</span> {previewInfo.employee_name || 'نامشخص'}
                    </p>
                    <p className="text-base">
                      <span className="font-semibold">سقف این دسته‌بندی:</span> {previewInfo.limit}
                    </p>
                    <p className="text-base">
                      <span className="font-semibold">مصرف شده:</span> {previewInfo.spent}
                    </p>
                    <p className="text-base">
                      <span className="font-semibold">باقی‌مانده دسته‌بندی:</span> {previewInfo.remaining}
                    </p>
                    <p className="text-base">
                      <span className="font-semibold">موجودی کیف پول:</span> {previewInfo.wallet_balance}
                    </p>
                    <p className="text-lg font-bold text-[#10B981]">
                      مبلغ قابل استفاده:
                      <span className="mr-2">{previewInfo.available_amount}</span>
                    </p>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        )}

        {/* Products Tab */}
        {activeTab === 'products' && (
          <div className="space-y-6">
            {/* Create Product Category */}
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">ایجاد دسته‌بندی محصول</CardTitle>
              </CardHeader>
              <CardContent>
                <form className="space-y-4" onSubmit={handleCreateProductCategory}>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="category_name">نام دسته‌بندی</Label>
                      <Input id="category_name" name="category_name" required />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="category_description">توضیحات (اختیاری)</Label>
                      <Input id="category_description" name="category_description" />
                    </div>
                  </div>
                  <Button type="submit" disabled={createProductCategoryMutation.isPending}>
                    {createProductCategoryMutation.isPending ? 'در حال ایجاد...' : 'ایجاد دسته‌بندی'}
                  </Button>
                </form>
              </CardContent>
            </Card>

            {/* Product Form */}
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">
                  {editingProduct ? 'ویرایش محصول' : 'افزودن محصول جدید'}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <form className="space-y-4" onSubmit={handleProductSubmit}>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="product_name">نام محصول</Label>
                      <Input
                        id="product_name"
                        required
                        value={productForm.name}
                        onChange={(e) => setProductForm({ ...productForm, name: e.target.value })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="product_category">دسته‌بندی محصول</Label>
                      <Select
                        id="product_category"
                        value={productForm.product_category_id}
                        onChange={(e) => setProductForm({ ...productForm, product_category_id: e.target.value })}
                      >
                        <option value="">بدون دسته‌بندی</option>
                        {productCategories.map((cat) => (
                          <option key={cat.id} value={cat.id}>
                            {cat.name}
                          </option>
                        ))}
                      </Select>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="product_description">توضیحات</Label>
                    <textarea
                      id="product_description"
                      rows={3}
                      className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={productForm.description}
                      onChange={(e) => setProductForm({ ...productForm, description: e.target.value })}
                    />
                  </div>
                  <div className="grid gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                      <Label htmlFor="product_price">قیمت</Label>
                      <Input
                        id="product_price"
                        type="number"
                        required
                        min={0}
                        step={1000}
                        value={productForm.price}
                        onChange={(e) => setProductForm({ ...productForm, price: e.target.value })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="stock_quantity">موجودی</Label>
                      <Input
                        id="stock_quantity"
                        type="number"
                        required
                        min={0}
                        value={productForm.stock_quantity}
                        onChange={(e) => setProductForm({ ...productForm, stock_quantity: e.target.value })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="product_image">تصویر محصول</Label>
                      <Input
                        id="product_image"
                        type="file"
                        accept="image/*"
                        onChange={(e) => setProductForm({ ...productForm, image: e.target.files?.[0] || null })}
                      />
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      id="online_purchase_enabled"
                      checked={productForm.online_purchase_enabled}
                      onChange={(e) => setProductForm({ ...productForm, online_purchase_enabled: e.target.checked })}
                      className="h-4 w-4"
                    />
                    <Label htmlFor="online_purchase_enabled">فعال کردن خرید آنلاین</Label>
                  </div>
                  <div className="flex gap-2">
                    <Button type="submit" disabled={createProductMutation.isPending || updateProductMutation.isPending}>
                      {editingProduct
                        ? updateProductMutation.isPending
                          ? 'در حال به‌روزرسانی...'
                          : 'به‌روزرسانی'
                        : createProductMutation.isPending
                          ? 'در حال ایجاد...'
                          : 'ایجاد محصول'}
                    </Button>
                    {editingProduct && (
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                          setEditingProduct(null);
                          setProductForm({
                            name: '',
                            description: '',
                            price: '',
                            product_category_id: '',
                            stock_quantity: '',
                            online_purchase_enabled: false,
                            image: null
                          });
                        }}
                      >
                        انصراف
                      </Button>
                    )}
                  </div>
                </form>
              </CardContent>
            </Card>

            {/* Products List */}
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">لیست محصولات</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>نام</TableHead>
                      <TableHead>دسته‌بندی</TableHead>
                      <TableHead>قیمت</TableHead>
                      <TableHead>موجودی</TableHead>
                      <TableHead>خرید آنلاین</TableHead>
                      <TableHead>عملیات</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {merchantProducts.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={6} className="text-center text-[#6B7280]">
                          محصولی ثبت نشده است.
                        </TableCell>
                      </TableRow>
                    ) : (
                      merchantProducts.map((product) => (
                        <TableRow key={product.id}>
                          <TableCell className="font-medium">{product.name}</TableCell>
                          <TableCell>{product.category_name || '—'}</TableCell>
                          <TableCell>{product.price.toLocaleString()} تومان</TableCell>
                          <TableCell>{product.stock_quantity}</TableCell>
                          <TableCell>
                            {product.online_purchase_enabled ? (
                              <span className="text-green-600">✓ فعال</span>
                            ) : (
                              <span className="text-gray-400">غیرفعال</span>
                            )}
                          </TableCell>
                          <TableCell>
                            <div className="flex gap-2">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleEditProduct(product)}
                              >
                                ویرایش
                              </Button>
                              <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => handleDeleteProduct(product.id)}
                                disabled={deleteProductMutation.isPending}
                              >
                                حذف
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Orders Tab */}
        {activeTab === 'orders' && (
          <div className="space-y-6">
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">سفارشات آنلاین</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>شماره سفارش</TableHead>
                      <TableHead>مشتری</TableHead>
                      <TableHead>مبلغ کل</TableHead>
                      <TableHead>وضعیت</TableHead>
                      <TableHead>کد پیگیری</TableHead>
                      <TableHead>تاریخ</TableHead>
                      <TableHead>عملیات</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {merchantOrders.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-center text-muted-foreground">
                          سفارشی ثبت نشده است.
                        </TableCell>
                      </TableRow>
                    ) : (
                      merchantOrders.map((order) => (
                        <TableRow key={order.id}>
                          <TableCell className="font-medium">{order.order_number}</TableCell>
                          <TableCell>
                            {order.customer_name} {order.customer_family}
                          </TableCell>
                          <TableCell>{order.total_amount.toLocaleString()} تومان</TableCell>
                          <TableCell>
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
                          </TableCell>
                          <TableCell>{order.tracking_code || '—'}</TableCell>
                          <TableCell>{new Date(order.created_at).toLocaleDateString('fa-IR')}</TableCell>
                          <TableCell>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => {
                                setSelectedOrder(order);
                                setOrderStatus(order.status);
                                setTrackingCode(order.tracking_code || '');
                              }}
                            >
                              مدیریت
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            {/* Order Detail Modal */}
            {selectedOrder && (
              <Card className="border border-[#E5E7EB] bg-white shadow-sm">
                <CardHeader className="border-b border-[#E5E7EB] pb-4">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-lg font-semibold text-[#1F2937]">مدیریت سفارش {selectedOrder.order_number}</CardTitle>
                    <Button variant="ghost" size="sm" onClick={() => setSelectedOrder(null)}>
                      بستن
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div>
                      <h3 className="font-semibold mb-2">اطلاعات مشتری:</h3>
                      <p>نام: {selectedOrder.customer_name} {selectedOrder.customer_family}</p>
                      <p>موبایل: {selectedOrder.customer_mobile}</p>
                      <p>آدرس: {selectedOrder.customer_address}</p>
                      <p>کد پستی: {selectedOrder.customer_postal_code}</p>
                    </div>
                    <div>
                      <h3 className="font-semibold mb-2">آیتم‌های سفارش:</h3>
                      <ul className="list-disc list-inside space-y-1">
                        {selectedOrder.items.map((item) => (
                          <li key={item.id}>
                            {item.product_name} - {item.quantity} عدد - {item.subtotal.toLocaleString()} تومان
                          </li>
                        ))}
                      </ul>
                      <p className="mt-2 font-bold">جمع کل: {selectedOrder.total_amount.toLocaleString()} تومان</p>
                    </div>
                    <form className="space-y-4" onSubmit={handleUpdateOrderStatus}>
                      <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                          <Label htmlFor="order_status">وضعیت سفارش</Label>
                          <Select
                            id="order_status"
                            value={orderStatus}
                            onChange={(e) => setOrderStatus(e.target.value)}
                          >
                            <option value="pending">در انتظار</option>
                            <option value="processing">در حال پردازش</option>
                            <option value="shipped">ارسال شده</option>
                            <option value="delivered">تحویل داده شده</option>
                            <option value="cancelled">لغو شده</option>
                          </Select>
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="tracking_code">کد پیگیری پستی</Label>
                          <Input
                            id="tracking_code"
                            value={trackingCode}
                            onChange={(e) => setTrackingCode(e.target.value)}
                            placeholder="کد پیگیری پستی را وارد کنید"
                          />
                        </div>
                      </div>
                      <Button type="submit" disabled={updateOrderStatusMutation.isPending}>
                        {updateOrderStatusMutation.isPending ? 'در حال به‌روزرسانی...' : 'به‌روزرسانی وضعیت'}
                      </Button>
                    </form>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {/* Profile Tab */}
        {activeTab === 'profile' && (
              <Card className="border border-[#E5E7EB] bg-white shadow-sm">
            <CardHeader className="border-b border-[#E5E7EB] pb-4">
              <CardTitle className="text-lg font-semibold text-[#1F2937]">اطلاعات فروشگاه</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-6" onSubmit={handleProfileSubmit}>
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="store_name">نام فروشگاه</Label>
                    <Input
                      id="store_name"
                      name="store_name"
                      value={profile.store_name || ''}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => setProfile({ ...profile, store_name: e.target.value })}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="store_slogan">شعار فروشگاه</Label>
                    <Input
                      id="store_slogan"
                      name="store_slogan"
                      value={profile.store_slogan || ''}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => setProfile({ ...profile, store_slogan: e.target.value })}
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="store_address">آدرس فروشگاه</Label>
                  <Input
                    id="store_address"
                    name="store_address"
                      value={profile.store_address || ''}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => setProfile({ ...profile, store_address: e.target.value })}
                  />
                </div>
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="province_id">استان</Label>
                    <Select
                      id="province_id"
                      name="province_id"
                      value={selectedProvinceId}
                      onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                        const provinceId = e.target.value ? Number(e.target.value) : '';
                        setSelectedProvinceId(provinceId);
                        setSelectedCityId('');
                        setProfile({ ...profile, province_id: provinceId as number, city_id: undefined });
                      }}
                    >
                      <option value="">انتخاب استان</option>
                      {iranProvinces.map((province) => (
                        <option key={province.id} value={province.id}>
                          {province.name}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="city_id">شهر</Label>
                    <Select
                      id="city_id"
                      name="city_id"
                      value={selectedCityId}
                      onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                        const cityId = e.target.value ? Number(e.target.value) : '';
                        setSelectedCityId(cityId);
                        setProfile({ ...profile, city_id: cityId as number });
                      }}
                      disabled={!selectedProvinceId}
                    >
                      <option value="">انتخاب شهر</option>
                      {availableCities.map((city) => (
                        <option key={city.id} value={city.id}>
                          {city.name}
                        </option>
                      ))}
                    </Select>
                  </div>
                </div>
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="phone">تلفن</Label>
                    <Input
                      id="phone"
                      name="phone"
                      value={profile.phone || ''}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => setProfile({ ...profile, phone: e.target.value })}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="mobile">موبایل</Label>
                    <Input
                      id="mobile"
                      name="mobile"
                      value={profile.mobile || ''}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => setProfile({ ...profile, mobile: e.target.value })}
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="store_description">توضیحات فروشگاه</Label>
                  <textarea
                    id="store_description"
                    name="store_description"
                    rows={4}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={profile.store_description || ''}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setProfile({ ...profile, store_description: e.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="store_image">تصویر اصلی فروشگاه</Label>
                  <Input
                    id="store_image"
                    type="file"
                    accept="image/*"
                    onChange={(e) => setProfileImage(e.target.files?.[0] || null)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="store_images">لیست تصاویر فروشگاه</Label>
                  <Input
                    id="store_images"
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={(e) => setProfileImages(Array.from(e.target.files || []))}
                  />
                </div>
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <Label className="text-lg font-semibold">محصولات</Label>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setProducts([...products, { name: '', description: '' }])}
                    >
                      افزودن محصول
                    </Button>
                  </div>
                  {products.map((product, idx) => (
                    <Card key={idx} className="p-4 border border-[#E5E7EB] bg-[#F9FAFB]">
                      <div className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                          <div className="space-y-2">
                            <Label>نام محصول</Label>
                            <Input
                              value={product.name}
                              onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                const newProducts = [...products];
                                newProducts[idx].name = e.target.value;
                                setProducts(newProducts);
                              }}
                            />
                          </div>
                          <div className="space-y-2">
                            <Label>قیمت (اختیاری)</Label>
                            <Input
                              type="number"
                              value={product.price || ''}
                              onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                const newProducts = [...products];
                                newProducts[idx].price = Number(e.target.value) || undefined;
                                setProducts(newProducts);
                              }}
                            />
                          </div>
                        </div>
                        <div className="space-y-2">
                          <Label>توضیحات محصول</Label>
                          <textarea
                            rows={2}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={product.description}
                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => {
                              const newProducts = [...products];
                              newProducts[idx].description = e.target.value;
                              setProducts(newProducts);
                            }}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>تصویر محصول</Label>
                          <Input
                            type="file"
                            accept="image/*"
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                              const newProducts = [...products];
                              newProducts[idx].image = e.target.files?.[0];
                              setProducts(newProducts);
                            }}
                          />
                        </div>
                        <Button
                          type="button"
                          variant="destructive"
                          size="sm"
                          onClick={() => setProducts(products.filter((_: unknown, i: number) => i !== idx))}
                        >
                          حذف محصول
                        </Button>
                      </div>
                    </Card>
                  ))}
                </div>
                <Button type="submit" className="w-full" disabled={profileMutation.isPending}>
                  {profileMutation.isPending ? 'در حال ذخیره…' : 'ذخیره اطلاعات فروشگاه'}
                </Button>
              </form>
            </CardContent>
          </Card>
        )}

        {/* Analytics Tab */}
        {activeTab === 'analytics' && (
          <div className="space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              <Card className="border border-[#E5E7EB] bg-white shadow-sm">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-[#6B7280]">درآمد امروز</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-2xl font-bold text-[#10B981]">
                    {revenueStats?.daily?.[revenueStats.daily.length - 1]?.amount?.toLocaleString() ?? 0} تومان
                  </p>
                </CardContent>
              </Card>
              <Card className="border border-[#E5E7EB] bg-white shadow-sm">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-[#6B7280]">درآمد این ماه</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-2xl font-bold text-primary">
                    {revenueStats?.monthly?.[revenueStats.monthly.length - 1]?.amount?.toLocaleString() ?? 0} تومان
                  </p>
                </CardContent>
              </Card>
              <Card className="border border-[#E5E7EB] bg-white shadow-sm">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-[#6B7280]">درآمد امسال</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-2xl font-bold text-primary">
                    {revenueStats?.yearly?.[revenueStats.yearly.length - 1]?.amount?.toLocaleString() ?? 0} تومان
                  </p>
                </CardContent>
              </Card>
            </div>

            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">نمودار درآمد روزانه (30 روز گذشته)</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={revenueStats?.daily ?? []}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                    <XAxis dataKey="date" stroke="#64748b" />
                    <YAxis stroke="#64748b" />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: '#fff',
                        border: '1px solid #e2e8f0',
                        borderRadius: '8px'
                      }}
                    />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="amount"
                      stroke="#3b82f6"
                      strokeWidth={2}
                      dot={{ fill: '#3b82f6', r: 4 }}
                      name="درآمد (تومان)"
                    />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">نمودار درآمد ماهانه (12 ماه گذشته)</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={revenueStats?.monthly ?? []}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                    <XAxis dataKey="date" stroke="#64748b" />
                    <YAxis stroke="#64748b" />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: '#fff',
                        border: '1px solid #e2e8f0',
                        borderRadius: '8px'
                      }}
                    />
                    <Legend />
                    <Bar dataKey="amount" fill="#8b5cf6" name="درآمد (تومان)" radius={[8, 8, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">نمودار درآمد سالانه (5 سال گذشته)</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={revenueStats?.yearly ?? []}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                    <XAxis dataKey="date" stroke="#64748b" />
                    <YAxis stroke="#64748b" />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: '#fff',
                        border: '1px solid #e2e8f0',
                        borderRadius: '8px'
                      }}
                    />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="amount"
                      stroke="#10b981"
                      strokeWidth={3}
                      dot={{ fill: '#10b981', r: 5 }}
                      name="درآمد (تومان)"
                    />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Transactions Tab */}
        {activeTab === 'transactions' && (
              <Card className="border border-[#E5E7EB] bg-white shadow-sm">
            <CardHeader className="border-b border-[#E5E7EB] pb-4">
              <CardTitle className="text-lg font-semibold text-[#1F2937]">آخرین تراکنش‌ها</CardTitle>
            </CardHeader>
            <CardContent>
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
                        تراکنشی ثبت نشده است.
                      </TableCell>
                    </TableRow>
                  ) : (
                    transactions.map((transaction: Transaction) => (
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
            </CardContent>
          </Card>
        )}

        {/* Payouts Tab */}
        {activeTab === 'payouts' && (
          <div className="space-y-6">
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">درخواست تسویه</CardTitle>
              </CardHeader>
              <CardContent>
                <form className="space-y-4" onSubmit={handlePayoutRequest}>
                  <div className="space-y-2">
                    <Label htmlFor="payout-amount">مبلغ</Label>
                    <Input id="payout-amount" name="amount" type="number" required min={0} step={1000} />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="bank_account">شماره حساب/شبا</Label>
                    <Input id="bank_account" name="bank_account" required placeholder="IRxxxxxxxxxxxx" />
                  </div>
                  <Button type="submit" className="w-full" disabled={payoutMutation.isPending}>
                    {payoutMutation.isPending ? 'در حال ارسال…' : 'ثبت درخواست تسویه'}
                  </Button>
                </form>
              </CardContent>
            </Card>
            <Card className="border border-[#E5E7EB] bg-white shadow-sm">
              <CardHeader className="border-b border-[#E5E7EB] pb-4">
                <CardTitle className="text-lg font-semibold text-[#1F2937]">وضعیت تسویه‌ها</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>شناسه</TableHead>
                      <TableHead>مبلغ</TableHead>
                      <TableHead>وضعیت</TableHead>
                      <TableHead>تاریخ</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {payoutStatus.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={4} className="text-center text-muted-foreground">
                          هنوز درخواستی برای تسویه ثبت نشده است.
                        </TableCell>
                      </TableRow>
                    ) : (
                      payoutStatus.map((payout: PayoutStatus) => (
                        <TableRow key={payout.id}>
                          <TableCell>{payout.id}</TableCell>
                          <TableCell>{payout.amount}</TableCell>
                          <TableCell>
                            <PayoutStatusBadge status={payout.status} />
                          </TableCell>
                          <TableCell>{payout.created_at}</TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}
      </div>

      <OTPModal
        open={otpOpen}
        onOpenChange={setOtpOpen}
        request={
          activeRequest
            ? {
                id: activeRequest.id,
                amount: activeRequest.amount,
                storeName: activeRequest.employeeName
              }
            : undefined
        }
        isSubmitting={confirmMutation.isPending}
        onSubmit={({ otp }) => {
          if (!activeRequest) return;
          confirmMutation.mutate({ request_id: activeRequest.id, otp_code: otp });
        }}
      />
    </DashboardLayout>
  );
};

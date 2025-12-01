import React, { useEffect, useMemo, useState } from 'react';
import PanelLayout from '../components/PanelLayout';
import SectionCard from '../components/SectionCard';
import StatCard from '../components/StatCard';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import Select from '../components/Select';
import Chart from '../components/Chart';
import apiClient from '../lib/apiClient';
import { useAuth } from '../contexts/AuthContext';

const customerDirectory = [
    { name: 'محمدرضا طاهری', nationalId: '1234567890', wallet: 2_450_000 },
    { name: 'سارا احمدی', nationalId: '0987654321', wallet: 1_280_000 },
    { name: 'مهدی رضایی', nationalId: '5566778899', wallet: 3_960_000 },
    { name: 'لیلا حسینی', nationalId: '1122334455', wallet: 640_000 },
    { name: 'الهام مرادی', nationalId: '2211445566', wallet: 4_100_000 },
];

const paymentRequestsSeed = [
    { id: 'REQ-2108', customer: 'محمدرضا طاهری', amount: 420_000, status: 'در انتظار تایید', created_at: '۱۴۰۲/۰۸/۰۹' },
    { id: 'REQ-2105', customer: 'مهدی رضایی', amount: 2_250_000, status: 'تایید شده', created_at: '۱۴۰۲/۰۸/۰۸' },
    { id: 'REQ-2099', customer: 'سارا احمدی', amount: 185_000, status: 'رد شده', created_at: '۱۴۰۲/۰۸/۰۷' },
];

const payoutHistorySeed = [
    { id: 'SET-901', amount: 12_500_000, status: 'پرداخت شده', created_at: '۱۴۰۲/۰۸/۰۵', account: 'حساب اصلی' },
    { id: 'SET-892', amount: 8_200_000, status: 'در انتظار پرداخت', created_at: '۱۴۰۲/۰۸/۰۳', account: 'کیف پول ارزی' },
    { id: 'SET-881', amount: 16_900_000, status: 'پرداخت شده', created_at: '۱۴۰۲/۰۷/۲۹', account: 'حساب اصلی' },
];

const MerchantPanel = () => {
    const { token } = useAuth();
    const [activeTab, setActiveTab] = useState('payment'); // 'payment' or 'store-info'
    const [balance, setBalance] = useState(0);
    const [nationalId, setNationalId] = useState('');
    const [amount, setAmount] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [payoutAmount, setPayoutAmount] = useState('');
    const [payoutAccount, setPayoutAccount] = useState('main');
    const [payouts, setPayouts] = useState(payoutHistorySeed);
    const [requests, setRequests] = useState(paymentRequestsSeed);
    
    // Store info state
    const [storeImage, setStoreImage] = useState('');
    const [storeImages, setStoreImages] = useState([]);
    const [storeName, setStoreName] = useState('');
    const [storeAddress, setStoreAddress] = useState('');
    const [storePhone, setStorePhone] = useState('');
    const [storeSlogan, setStoreSlogan] = useState('');
    const [storeProvince, setStoreProvince] = useState('');
    const [storeCity, setStoreCity] = useState('');
    const [storeDescription, setStoreDescription] = useState('');
    const [products, setProducts] = useState([]);
    const [productName, setProductName] = useState('');
    
    // Charts data
    const [dailyRevenue, setDailyRevenue] = useState([]);
    const [monthlyRevenue, setMonthlyRevenue] = useState([]);
    const [yearlyRevenue, setYearlyRevenue] = useState([]);
    
    // Provinces and cities
    const [provinces, setProvinces] = useState([]);
    const [cities, setCities] = useState([]);

    useEffect(() => {
        const fetchBalance = async () => {
            if (!token) {
                return;
            }
            try {
                const response = await apiClient.get('/wp-json/cwm/v1/wallet/balance', {
                    headers: { Authorization: `Bearer ${token}` },
                });
                setBalance(response.data.data.balance);
            } catch (error) {
                console.warn('Failed to fetch balance from API. Falling back to demo data.', error);
                setBalance(18_450_000);
            }
        };

        fetchBalance();
    }, [token]);

    useEffect(() => {
        const fetchRevenueData = async () => {
            if (!token) return;
            try {
                const [daily, monthly, yearly] = await Promise.all([
                    apiClient.get('/wp-json/cwm/v1/store/revenue/daily', {
                        headers: { Authorization: `Bearer ${token}` },
                    }),
                    apiClient.get('/wp-json/cwm/v1/store/revenue/monthly', {
                        headers: { Authorization: `Bearer ${token}` },
                    }),
                    apiClient.get('/wp-json/cwm/v1/store/revenue/yearly', {
                        headers: { Authorization: `Bearer ${token}` },
                    }),
                ]);

                setDailyRevenue(daily.data.data.map(item => ({
                    label: new Date(item.date).toLocaleDateString('fa-IR', { month: 'short', day: 'numeric' }),
                    revenue: item.revenue,
                })));

                setMonthlyRevenue(monthly.data.data.map(item => ({
                    label: item.month,
                    revenue: item.revenue,
                })));

                setYearlyRevenue(yearly.data.data.map(item => ({
                    label: item.year.toString(),
                    revenue: item.revenue,
                })));
            } catch (error) {
                console.warn('Failed to fetch revenue data', error);
            }
        };

        fetchRevenueData();
    }, [token]);

    useEffect(() => {
        const fetchProvinces = async () => {
            try {
                const response = await apiClient.get('/wp-json/cwm/v1/iran/provinces');
                setProvinces(response.data.data);
            } catch (error) {
                console.warn('Failed to fetch provinces', error);
            }
        };
        fetchProvinces();
    }, []);

    useEffect(() => {
        const fetchCities = async () => {
            if (!storeProvince) {
                setCities([]);
                return;
            }
            try {
                const response = await apiClient.get(`/wp-json/cwm/v1/iran/provinces/${storeProvince}/cities`);
                setCities(response.data.data);
            } catch (error) {
                console.warn('Failed to fetch cities', error);
            }
        };
        fetchCities();
    }, [storeProvince]);

    useEffect(() => {
        const fetchStoreInfo = async () => {
            if (!token || activeTab !== 'store-info') return;
            try {
                const response = await apiClient.get('/wp-json/cwm/v1/store/info', {
                    headers: { Authorization: `Bearer ${token}` },
                });
                const data = response.data.data;
                setStoreImage(data.store_image || '');
                setStoreImages(data.store_images || []);
                setStoreName(data.store_name || '');
                setStoreAddress(data.store_address || '');
                setStorePhone(data.store_phone || '');
                setStoreSlogan(data.store_slogan || '');
                setStoreProvince(data.store_province || '');
                setStoreCity(data.store_city || '');
                setStoreDescription(data.store_description || '');
                setProducts(data.products || []);
            } catch (error) {
                console.warn('Failed to fetch store info', error);
            }
        };
        fetchStoreInfo();
    }, [token, activeTab]);

    const filteredCustomers = useMemo(() => {
        if (!searchTerm) {
            return [];
        }
        return customerDirectory.filter((customer) =>
            [customer.name, customer.nationalId]
                .join(' ')
                .toLowerCase()
                .includes(searchTerm.toLowerCase())
        );
    }, [searchTerm]);

    const todaysPending = requests.filter((request) => request.status === 'در انتظار تایید').length;
    const todaysApproved = requests.filter((request) => request.status === 'تایید شده').length;

    const handleCustomerPick = (customer) => {
        setNationalId(customer.nationalId);
        setSearchTerm('');
    };
    
    const handleAddProduct = () => {
        if (productName.trim()) {
            setProducts([...products, { id: Date.now(), name: productName }]);
            setProductName('');
        }
    };
    
    const handleRemoveProduct = (id) => {
        setProducts(products.filter(p => p.id !== id));
    };
    
    const handleAddStoreImage = (url) => {
        if (url.trim()) {
            setStoreImages([...storeImages, { id: Date.now(), url }]);
        }
    };
    
    const handleRemoveStoreImage = (id) => {
        setStoreImages(storeImages.filter(img => img.id !== id));
    };

    const handlePaymentRequest = async (event) => {
        event.preventDefault();
        if (!nationalId || !amount) {
            alert('کد ملی و مبلغ پرداخت را کامل کنید.');
            return;
        }

        try {
            if (token) {
                await apiClient.post(
                    '/wp-json/cwm/v1/payment/request',
                    { national_id: nationalId, amount },
                    { headers: { Authorization: `Bearer ${token}` } }
                );
            }

            setRequests((prev) => [
                {
                    id: `REQ-${Math.floor(Math.random() * 9000) + 1000}`,
                    customer: nationalId,
                    amount: Number(amount),
                    status: 'در انتظار تایید',
                    created_at: new Date().toLocaleDateString('fa-IR'),
                },
                ...prev,
            ]);
            alert('درخواست پرداخت با موفقیت ثبت شد.');
            setAmount('');
            setNationalId('');
        } catch (error) {
            console.error('Payment request failed:', error);
            alert('ثبت درخواست با خطا مواجه شد.');
        }
    };

    const handlePayoutRequest = (event) => {
        event.preventDefault();
        if (!payoutAmount) {
            alert('مبلغ تسویه را وارد کنید.');
            return;
        }

        setPayouts((prev) => [
            {
                id: `SET-${Math.floor(Math.random() * 900) + 100}`,
                amount: Number(payoutAmount),
                status: 'در انتظار پرداخت',
                created_at: new Date().toLocaleDateString('fa-IR'),
                account: payoutAccount === 'main' ? 'حساب اصلی' : 'حساب ارزی',
            },
            ...prev,
        ]);
        alert('درخواست تسویه ثبت شد و برای تیم مالی ارسال گردید.');
        setPayoutAmount('');
    };

    return (
        <PanelLayout
            title="پنل پذیرندگان"
            description="پرداخت‌های مشتریان را با جست‌وجوی هوشمند بر پایه کد ملی ثبت کنید، موجودی کیف پول خود را بررسی و درخواست‌های تسویه را در یک فضای روشن و مینیمال مدیریت نمایید."
        >
            {/* Tabs */}
            <div className="flex gap-2 border-b border-white/10">
                <button
                    onClick={() => setActiveTab('payment')}
                    className={`px-6 py-3 text-sm font-semibold transition ${
                        activeTab === 'payment'
                            ? 'border-b-2 border-sky-400 text-white'
                            : 'text-slate-400 hover:text-white'
                    }`}
                >
                    درخواست پرداخت
                </button>
                <button
                    onClick={() => setActiveTab('analytics')}
                    className={`px-6 py-3 text-sm font-semibold transition ${
                        activeTab === 'analytics'
                            ? 'border-b-2 border-sky-400 text-white'
                            : 'text-slate-400 hover:text-white'
                    }`}
                >
                    آمار و نمودارها
                </button>
                <button
                    onClick={() => setActiveTab('store-info')}
                    className={`px-6 py-3 text-sm font-semibold transition ${
                        activeTab === 'store-info'
                            ? 'border-b-2 border-sky-400 text-white'
                            : 'text-slate-400 hover:text-white'
                    }`}
                >
                    اطلاعات فروشگاه
                </button>
            </div>

            {activeTab === 'payment' ? (
                <>
                    {/* Payment Request - Full Width and Bold */}
                    <div className="rounded-3xl border-2 border-sky-400/50 bg-gradient-to-br from-sky-500/20 via-indigo-500/20 to-purple-500/20 p-8 shadow-[0_25px_55px_-35px_rgba(59,130,246,0.5)]">
                        <div className="mb-6">
                            <h2 className="text-3xl font-bold text-white mb-2">درخواست پرداخت از مشتری</h2>
                            <p className="text-slate-300">کد ملی یا نام مشتری را وارد کنید، او را انتخاب و مبلغ را برای تایید ارسال کنید.</p>
                        </div>
                        <form className="grid gap-6 md:grid-cols-2" onSubmit={handlePaymentRequest}>
                            <div className="space-y-3">
                                <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">جست‌وجوی مشتری</label>
                                <Input
                                    value={searchTerm || nationalId}
                                    onChange={(event) => {
                                        setSearchTerm(event.target.value);
                                        setNationalId(event.target.value);
                                    }}
                                    placeholder="نام یا کد ملی مشتری"
                                    className="text-base py-3"
                                />
                                {filteredCustomers.length > 0 && (
                                    <div className="space-y-2 rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur">
                                        {filteredCustomers.slice(0, 4).map((customer) => (
                                            <button
                                                type="button"
                                                key={customer.nationalId}
                                                onClick={() => handleCustomerPick(customer)}
                                                className="flex w-full items-center justify-between rounded-xl bg-white/10 px-4 py-3 text-left text-base font-medium text-slate-100 transition hover:bg-white/20"
                                            >
                                                <span>{customer.name}</span>
                                                <span className="font-mono text-sm text-slate-300">{customer.nationalId}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div className="space-y-3">
                                <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">مبلغ پرداخت (ریال)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={amount}
                                    onChange={(event) => setAmount(event.target.value)}
                                    placeholder="مثلاً 1,200,000"
                                    className="text-base py-3"
                                />
                            </div>
                            <div className="md:col-span-2 flex items-center justify-between gap-4 pt-4">
                                <p className="text-sm text-slate-300">
                                    پس از تایید مشتری، مبلغ از کیف پول او کسر و به موجودی پذیرنده اضافه می‌شود.
                                </p>
                                <Button type="submit" className="px-8 py-3 text-base font-bold">
                                    ارسال برای تایید مشتری
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            title="موجودی لحظه‌ای پذیرنده"
                            value={`${balance.toLocaleString('fa-IR')} ریال`}
                            hint="موجودی قابل استفاده برای ثبت درخواست‌های تسویه"
                        />
                        <StatCard
                            title="درخواست‌های در انتظار"
                            value={`${todaysPending.toLocaleString('fa-IR')} مورد`}
                            accent="from-amber-500/80 to-orange-500/60"
                            hint="پرداخت‌هایی که نیازمند تایید مشتری هستند"
                        />
                        <StatCard
                            title="پرداخت‌های تایید شده امروز"
                            value={`${todaysApproved.toLocaleString('fa-IR')} تراکنش`}
                            accent="from-emerald-500/80 to-teal-500/60"
                            trend={{ direction: 'up', label: '۶.۴٪ بیشتر نسبت به دیروز' }}
                        />
                        <StatCard
                            title="درخواست تسویه فعال"
                            value={`${payouts.filter((payout) => payout.status !== 'پرداخت شده').length} مورد`}
                            accent="from-purple-500/80 to-blue-500/60"
                            hint="در حال بررسی توسط واحد مالی"
                        />
                    </div>

                    <div className="grid gap-6 xl:grid-cols-3">
                        <SectionCard
                            title="درخواست تسویه حساب"
                            description="با انتخاب حساب مقصد، تسویه‌های موردنیاز خود را ثبت کنید."
                        >
                            <form className="space-y-4" onSubmit={handlePayoutRequest}>
                                <div className="space-y-2">
                                    <label className="text-xs uppercase tracking-[0.3em] text-slate-400">حساب مقصد</label>
                                    <Select
                                        value={payoutAccount}
                                        onChange={(event) => setPayoutAccount(event.target.value)}
                                        options={[
                                            { value: 'main', label: 'حساب بانکی اصلی - بانک آینده' },
                                            { value: 'fx', label: 'حساب ارزی - بانک سامان' },
                                        ]}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs uppercase tracking-[0.3em] text-slate-400">مبلغ تسویه (ریال)</label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={payoutAmount}
                                        onChange={(event) => setPayoutAmount(event.target.value)}
                                        placeholder="حداکثر تا سقف موجودی"
                                    />
                                </div>
                                <Button type="submit" className="w-full">
                                    ثبت درخواست تسویه
                                </Button>
                            </form>
                        </SectionCard>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <SectionCard
                            title="درخواست‌های پرداخت اخیر"
                            description="وضعیت تایید هر درخواست را به تفکیک مشاهده کنید"
                        >
                            <Table
                                headers={['شناسه', 'مشتری', 'مبلغ (ریال)', 'وضعیت', 'تاریخ']}
                                data={requests}
                                renderRow={(request) => (
                                    <tr key={request.id}>
                                        <td className="px-6 py-4 font-mono text-xs text-slate-400">{request.id}</td>
                                        <td className="px-6 py-4 text-slate-200">{request.customer}</td>
                                        <td className="px-6 py-4 text-slate-200">{request.amount.toLocaleString('fa-IR')}</td>
                                        <td className="px-6 py-4">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-medium ${
                                                    request.status === 'در انتظار تایید'
                                                        ? 'bg-amber-500/15 text-amber-300'
                                                        : request.status === 'تایید شده'
                                                            ? 'bg-emerald-500/15 text-emerald-300'
                                                            : 'bg-rose-500/15 text-rose-300'
                                                }`}
                                            >
                                                {request.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-300">{request.created_at}</td>
                                    </tr>
                                )}
                            />
                        </SectionCard>

                        <SectionCard
                            title="سوابق تسویه"
                            description="درخواست‌های تسویه قبلی و وضعیت پرداخت هر کدام"
                        >
                            <Table
                                headers={['شناسه', 'مبلغ (ریال)', 'حساب مقصد', 'وضعیت', 'تاریخ']}
                                data={payouts}
                                renderRow={(payout) => (
                                    <tr key={payout.id}>
                                        <td className="px-6 py-4 font-mono text-xs text-slate-400">{payout.id}</td>
                                        <td className="px-6 py-4 text-slate-200">{payout.amount.toLocaleString('fa-IR')}</td>
                                        <td className="px-6 py-4 text-slate-300">{payout.account}</td>
                                        <td className="px-6 py-4">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-medium ${
                                                    payout.status === 'پرداخت شده'
                                                        ? 'bg-emerald-500/15 text-emerald-300'
                                                        : 'bg-amber-500/15 text-amber-300'
                                                }`}
                                            >
                                                {payout.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-300">{payout.created_at}</td>
                                    </tr>
                                )}
                            />
                        </SectionCard>
                    </div>
                </>
            ) : activeTab === 'analytics' ? (
                <div className="space-y-6">
                    <SectionCard
                        title="نمودار درآمد روزانه"
                        description="نمایش درآمد روزانه در ۳۰ روز گذشته"
                    >
                        <Chart 
                            data={dailyRevenue} 
                            type="line" 
                            color="#0ea5e9"
                            title="درآمد روزانه"
                        />
                    </SectionCard>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <SectionCard
                            title="نمودار درآمد ماهانه"
                            description="نمایش درآمد ماهانه در ۱۲ ماه گذشته"
                        >
                            <Chart 
                                data={monthlyRevenue} 
                                type="bar" 
                                color="#8b5cf6"
                                title="درآمد ماهانه"
                            />
                        </SectionCard>

                        <SectionCard
                            title="نمودار درآمد سالانه"
                            description="نمایش درآمد سالانه در ۵ سال گذشته"
                        >
                            <Chart 
                                data={yearlyRevenue} 
                                type="bar" 
                                color="#10b981"
                                title="درآمد سالانه"
                            />
                        </SectionCard>
                    </div>
                </div>
            ) : (
                <SectionCard
                    title="اطلاعات فروشگاه"
                    description="اطلاعات کامل فروشگاه خود را تکمیل کنید"
                >
                    <div className="space-y-6">
                        {/* Store Image */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">تصویر اصلی فروشگاه</label>
                            <div className="flex gap-4">
                                <Input
                                    value={storeImage}
                                    onChange={(e) => setStoreImage(e.target.value)}
                                    placeholder="آدرس URL تصویر فروشگاه"
                                    className="flex-1"
                                />
                                {storeImage && (
                                    <img src={storeImage} alt="Store" className="h-24 w-24 rounded-xl object-cover border border-white/10" />
                                )}
                            </div>
                        </div>

                        {/* Store Images List */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">لیست تصاویر فروشگاه</label>
                            <div className="flex gap-3">
                                <Input
                                    placeholder="آدرس URL تصویر"
                                    onKeyPress={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleAddStoreImage(e.target.value);
                                            e.target.value = '';
                                        }
                                    }}
                                    className="flex-1"
                                />
                                <Button onClick={() => {
                                    const input = document.querySelector('input[placeholder="آدرس URL تصویر"]');
                                    if (input?.value) {
                                        handleAddStoreImage(input.value);
                                        input.value = '';
                                    }
                                }}>
                                    افزودن
                                </Button>
                            </div>
                            {storeImages.length > 0 && (
                                <div className="grid grid-cols-4 gap-3 mt-3">
                                    {storeImages.map((img) => (
                                        <div key={img.id} className="relative group">
                                            <img src={img.url} alt="Store" className="h-24 w-full rounded-xl object-cover border border-white/10" />
                                            <button
                                                onClick={() => handleRemoveStoreImage(img.id)}
                                                className="absolute top-1 right-1 bg-rose-500/80 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Store Name */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">نام فروشگاه</label>
                            <Input
                                value={storeName}
                                onChange={(e) => setStoreName(e.target.value)}
                                placeholder="نام فروشگاه"
                            />
                        </div>

                        {/* Store Address */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">آدرس فروشگاه</label>
                            <Input
                                value={storeAddress}
                                onChange={(e) => setStoreAddress(e.target.value)}
                                placeholder="آدرس کامل فروشگاه"
                            />
                        </div>

                        {/* Store Phone */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">تلفن فروشگاه</label>
                            <Input
                                value={storePhone}
                                onChange={(e) => setStorePhone(e.target.value)}
                                placeholder="شماره تلفن فروشگاه"
                            />
                        </div>

                        {/* Store Province */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">استان</label>
                            <Select
                                value={storeProvince}
                                onChange={(e) => {
                                    setStoreProvince(e.target.value);
                                    setStoreCity(''); // Reset city when province changes
                                }}
                                options={[
                                    { value: '', label: 'انتخاب استان' },
                                    ...provinces.map(p => ({ value: p.id, label: p.name }))
                                ]}
                            />
                        </div>

                        {/* Store City */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">شهر</label>
                            <Select
                                value={storeCity}
                                onChange={(e) => setStoreCity(e.target.value)}
                                disabled={!storeProvince}
                                options={[
                                    { value: '', label: 'ابتدا استان را انتخاب کنید' },
                                    ...cities.map(c => ({ value: c.id, label: c.name }))
                                ]}
                            />
                        </div>

                        {/* Store Slogan */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">شعار فروشگاه</label>
                            <Input
                                value={storeSlogan}
                                onChange={(e) => setStoreSlogan(e.target.value)}
                                placeholder="شعار فروشگاه"
                            />
                        </div>

                        {/* Store Description */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">توضیحات فروشگاه</label>
                            <textarea
                                value={storeDescription}
                                onChange={(e) => setStoreDescription(e.target.value)}
                                placeholder="توضیحات کامل فروشگاه"
                                rows={4}
                                className="block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-400 shadow-inner shadow-black/5 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                            />
                        </div>

                        {/* Products */}
                        <div className="space-y-3">
                            <label className="block text-sm font-semibold uppercase tracking-[0.3em] text-slate-300">لیست محصولات</label>
                            <div className="flex gap-3">
                                <Input
                                    value={productName}
                                    onChange={(e) => setProductName(e.target.value)}
                                    placeholder="نام محصول"
                                    onKeyPress={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleAddProduct();
                                        }
                                    }}
                                    className="flex-1"
                                />
                                <Button onClick={handleAddProduct}>افزودن محصول</Button>
                            </div>
                            {products.length > 0 && (
                                <div className="space-y-2 mt-3">
                                    {products.map((product) => (
                                        <div key={product.id} className="flex items-center justify-between rounded-xl bg-white/5 px-4 py-2 border border-white/10">
                                            <span className="text-slate-200">{product.name}</span>
                                            <button
                                                onClick={() => handleRemoveProduct(product.id)}
                                                className="text-rose-400 hover:text-rose-300 text-sm"
                                            >
                                                حذف
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <Button 
                            className="w-full mt-6"
                            onClick={async (e) => {
                                e.preventDefault();
                                if (!token) return;
                                try {
                                    await apiClient.put('/wp-json/cwm/v1/store/info', {
                                        store_image: storeImage,
                                        store_images: storeImages.map(img => img.url),
                                        store_name: storeName,
                                        store_address: storeAddress,
                                        store_phone: storePhone,
                                        store_slogan: storeSlogan,
                                        store_province: storeProvince,
                                        store_city: storeCity,
                                        store_description: storeDescription,
                                        products: products.map(p => ({
                                            name: p.name,
                                            description: '',
                                            image: '',
                                        })),
                                    }, {
                                        headers: { Authorization: `Bearer ${token}` },
                                    });
                                    alert('اطلاعات فروشگاه با موفقیت ذخیره شد.');
                                } catch (error) {
                                    console.error('Failed to save store info', error);
                                    alert('خطا در ذخیره اطلاعات.');
                                }
                            }}
                        >
                            ذخیره اطلاعات
                        </Button>
                    </div>
                </SectionCard>
            )}
        </PanelLayout>
    );
};

export default MerchantPanel;

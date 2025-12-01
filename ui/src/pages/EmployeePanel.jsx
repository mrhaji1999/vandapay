import React, { useEffect, useMemo, useState } from 'react';
import PanelLayout from '../components/PanelLayout';
import SectionCard from '../components/SectionCard';
import StatCard from '../components/StatCard';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import Select from '../components/Select';
import Modal from '../components/Modal';
import apiClient from '../lib/apiClient';
import { useAuth } from '../contexts/AuthContext';

const pendingSeed = [
    { id: 'REQ-2108', merchant: 'سوپرمارکت مرکزی', amount: 420_000, status: 'در انتظار تایید', otpRequired: true },
    { id: 'REQ-2105', merchant: 'کافه لانژ', amount: 185_000, status: 'در انتظار تایید', otpRequired: true },
];

const transactionsSeed = [
    { id: 'TRX-9821', type: 'خرید', amount: 420_000, status: 'موفق', created_at: '۱۴۰۲/۰۸/۰۹' },
    { id: 'TRX-9814', type: 'شارژ کیف پول', amount: 5_000_000, status: 'موفق', created_at: '۱۴۰۲/۰۸/۰۱' },
    { id: 'TRX-9788', type: 'خرید', amount: 1_180_000, status: 'موفق', created_at: '۱۴۰۲/۰۷/۲۸' },
];

const storesSeed = [
    {
        id: 1,
        name: 'سوپرمارکت مرکزی',
        image: 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=400',
        description: 'فروشگاه مواد غذایی با بهترین کیفیت و قیمت مناسب',
        address: 'تهران، خیابان ولیعصر، پلاک ۱۲۳',
        phone: '021-12345678',
        products: [
            { id: 1, name: 'برنج طارم', image: 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=200', description: 'برنج طارم درجه یک، بسته ۵ کیلویی' },
            { id: 2, name: 'روغن آفتابگردان', image: 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=200', description: 'روغن آفتابگردان خالص، بطری ۹۰۰ میلی‌لیتر' },
            { id: 3, name: 'شکر سفید', image: 'https://images.unsplash.com/photo-1615485925511-ef4f953b3c6a?w=200', description: 'شکر سفید کله قندی، بسته ۱ کیلویی' },
        ],
    },
    {
        id: 2,
        name: 'کافه لانژ',
        image: 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=400',
        description: 'کافه و رستوران با فضای دنج و آرام',
        address: 'تهران، خیابان انقلاب، پلاک ۴۵۶',
        phone: '021-87654321',
        products: [
            { id: 1, name: 'کاپوچینو', image: 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?w=200', description: 'کاپوچینو ایتالیایی با شیر بخارزده' },
            { id: 2, name: 'لاته', image: 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=200', description: 'لاته با طعم ملایم و خامه‌ای' },
            { id: 3, name: 'کیک شکلاتی', image: 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=200', description: 'کیک شکلاتی خانگی با خامه تازه' },
        ],
    },
    {
        id: 3,
        name: 'فروشگاه لوازم خانگی',
        image: 'https://images.unsplash.com/photo-1556912172-45b7abe8b7e4?w=400',
        description: 'فروشگاه تخصصی لوازم خانگی و آشپزخانه',
        address: 'تهران، خیابان آزادی، پلاک ۷۸۹',
        phone: '021-11223344',
        products: [
            { id: 1, name: 'مایکروویو', image: 'https://images.unsplash.com/photo-1574269909862-7e1d70bb8078?w=200', description: 'مایکروویو ۲۵ لیتری با قابلیت گریل' },
            { id: 2, name: 'مخلوط کن', image: 'https://images.unsplash.com/photo-1556910096-6f5e72db6803?w=200', description: 'مخلوط کن ۵ سرعته با ظرفیت ۱.۵ لیتر' },
        ],
    },
];

const EmployeePanel = () => {
    const { token } = useAuth();
    const [activeTab, setActiveTab] = useState('dashboard'); // 'dashboard', 'stores', or 'product-search'
    const [balance, setBalance] = useState(0);
    const [pendingRequests, setPendingRequests] = useState(pendingSeed);
    const [transactions, setTransactions] = useState(transactionsSeed);
    const [otpValues, setOtpValues] = useState({});
    const [stores, setStores] = useState(storesSeed);
    const [selectedStore, setSelectedStore] = useState(null);
    const [productSearch, setProductSearch] = useState('');
    const [selectedProduct, setSelectedProduct] = useState(null);
    
    // Filters
    const [filterProvince, setFilterProvince] = useState('');
    const [filterCity, setFilterCity] = useState('');
    const [provinces, setProvinces] = useState([]);
    const [cities, setCities] = useState([]);
    
    // Global product search
    const [globalProductSearch, setGlobalProductSearch] = useState('');
    const [searchResults, setSearchResults] = useState([]);

    useEffect(() => {
        const fetchBalance = async () => {
            if (!token) {
                setBalance(4_860_000);
                return;
            }

            try {
                const response = await apiClient.get('/wp-json/cwm/v1/wallet/balance', {
                    headers: { Authorization: `Bearer ${token}` },
                });
                setBalance(response.data.data.balance);
            } catch (error) {
                console.warn('Failed to fetch balance from API. Using demo balance.', error);
                setBalance(4_860_000);
            }
        };

        fetchBalance();
    }, [token]);

    useEffect(() => {
        const fetchStores = async () => {
            if (!token) return;
            try {
                const params = new URLSearchParams();
                if (filterProvince) params.append('province', filterProvince);
                if (filterCity) params.append('city', filterCity);
                
                const response = await apiClient.get(`/wp-json/cwm/v1/stores?${params.toString()}`, {
                    headers: { Authorization: `Bearer ${token}` },
                });
                setStores(response.data.data);
            } catch (error) {
                console.warn('Failed to fetch stores', error);
            }
        };
        fetchStores();
    }, [token, filterProvince, filterCity]);

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
            if (!filterProvince) {
                setCities([]);
                return;
            }
            try {
                const response = await apiClient.get(`/wp-json/cwm/v1/iran/provinces/${filterProvince}/cities`);
                setCities(response.data.data);
            } catch (error) {
                console.warn('Failed to fetch cities', error);
            }
        };
        fetchCities();
    }, [filterProvince]);

    useEffect(() => {
        const searchProducts = async () => {
            if (!globalProductSearch || globalProductSearch.length < 2) {
                setSearchResults([]);
                return;
            }
            if (!token) return;
            
            try {
                const params = new URLSearchParams({ q: globalProductSearch });
                if (filterProvince) params.append('province', filterProvince);
                if (filterCity) params.append('city', filterCity);
                
                const response = await apiClient.get(`/wp-json/cwm/v1/products/search?${params.toString()}`, {
                    headers: { Authorization: `Bearer ${token}` },
                });
                setSearchResults(response.data.data);
            } catch (error) {
                console.warn('Failed to search products', error);
            }
        };

        const timeoutId = setTimeout(searchProducts, 300);
        return () => clearTimeout(timeoutId);
    }, [globalProductSearch, filterProvince, filterCity, token]);

    const monthlySpend = useMemo(
        () => transactions.filter((trx) => trx.type === 'خرید').reduce((acc, trx) => acc + trx.amount, 0),
        [transactions]
    );

    const handleOtpChange = (requestId, value) => {
        setOtpValues((prev) => ({ ...prev, [requestId]: value }));
    };

    const handleConfirmPayment = async (requestId) => {
        const otp_code = otpValues[requestId];
        if (!otp_code) {
            alert('کد تایید را وارد کنید.');
            return;
        }

        try {
            if (token) {
                await apiClient.post(
                    '/wp-json/cwm/v1/payment/confirm',
                    { request_id: requestId, otp_code },
                    { headers: { Authorization: `Bearer ${token}` } }
                );
            }

            setPendingRequests((prev) => prev.filter((request) => request.id !== requestId));
            setTransactions((prev) => [
                {
                    id: `TRX-${Math.floor(Math.random() * 9000) + 1000}`,
                    type: 'خرید',
                    amount: pendingRequests.find((request) => request.id === requestId)?.amount || 0,
                    status: 'موفق',
                    created_at: new Date().toLocaleDateString('fa-IR'),
                },
                ...prev,
            ]);
            alert('پرداخت با موفقیت تایید شد.');
            setOtpValues((prev) => ({ ...prev, [requestId]: '' }));
        } catch (error) {
            console.error('Payment confirmation failed:', error);
            alert('تایید پرداخت با خطا مواجه شد.');
        }
    };

    const filteredProducts = useMemo(() => {
        if (!selectedStore) return [];
        if (!productSearch) return selectedStore.products;
        return selectedStore.products.filter((product) =>
            product.name.toLowerCase().includes(productSearch.toLowerCase())
        );
    }, [selectedStore, productSearch]);

    const handleStoreClick = (store) => {
        setSelectedStore(store);
    };

    const handleProductClick = (product) => {
        setSelectedProduct(product);
    };

    const handleBackToStores = () => {
        setSelectedStore(null);
        setProductSearch('');
        setSelectedProduct(null);
    };

    return (
        <PanelLayout
            title="پنل مشتری"
            description="درخواست‌های پرداخت، مانده کیف پول و تاریخچه خریدهای خود را به شکل شفاف مدیریت کنید و همیشه بدانید موجودی شما در چه وضعیتی قرار دارد."
        >
            {/* Tabs */}
            <div className="flex gap-2 border-b border-white/10">
                <button
                    onClick={() => setActiveTab('dashboard')}
                    className={`px-6 py-3 text-sm font-semibold transition ${
                        activeTab === 'dashboard'
                            ? 'border-b-2 border-sky-400 text-white'
                            : 'text-slate-400 hover:text-white'
                    }`}
                >
                    داشبورد
                </button>
                <button
                    onClick={() => setActiveTab('stores')}
                    className={`px-6 py-3 text-sm font-semibold transition ${
                        activeTab === 'stores'
                            ? 'border-b-2 border-sky-400 text-white'
                            : 'text-slate-400 hover:text-white'
                    }`}
                >
                    فروشگاه‌ها
                </button>
                <button
                    onClick={() => setActiveTab('product-search')}
                    className={`px-6 py-3 text-sm font-semibold transition ${
                        activeTab === 'product-search'
                            ? 'border-b-2 border-sky-400 text-white'
                            : 'text-slate-400 hover:text-white'
                    }`}
                >
                    جستجوی محصول
                </button>
            </div>

            {activeTab === 'dashboard' ? (
                <>
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="موجودی فعلی"
                    value={`${balance.toLocaleString('fa-IR')} ریال`}
                    hint="کیف پول شما پس از تایید پرداخت‌ها به‌روز می‌شود"
                />
                <StatCard
                    title="درخواست‌های در انتظار"
                    value={`${pendingRequests.length.toLocaleString('fa-IR')} مورد`}
                    accent="from-amber-500/80 to-orange-500/60"
                    hint="پرداخت‌هایی که باید تایید یا رد کنید"
                />
                <StatCard
                    title="مصرف ماه جاری"
                    value={`${monthlySpend.toLocaleString('fa-IR')} ریال`}
                    accent="from-emerald-500/80 to-teal-500/60"
                    trend={{ direction: 'up', label: '۲.۸٪ کمتر از ماه قبل' }}
                />
                <StatCard
                    title="آخرین شارژ"
                    value={`۱۴۰۲/۰۸/۰۱`}
                    hint="۵,۰۰۰,۰۰۰ ریال توسط شرکت واریز شد"
                    accent="from-sky-500/80 to-indigo-500/60"
                />
            </div>

            <SectionCard
                title="درخواست‌های پرداخت در انتظار تایید"
                description="پس از بررسی مبلغ و پذیرنده، کد تایید پیامکی را وارد کرده و پرداخت را نهایی کنید."
            >
                <Table
                    headers={[
                        'شناسه درخواست',
                        'پذیرنده',
                        'مبلغ (ریال)',
                        'کد تایید پیامکی',
                        'عملیات',
                    ]}
                    data={pendingRequests}
                    renderRow={(request) => (
                        <tr key={request.id}>
                            <td className="px-6 py-4 font-mono text-xs text-slate-400">{request.id}</td>
                            <td className="px-6 py-4 text-slate-200">{request.merchant}</td>
                            <td className="px-6 py-4 text-slate-200">{request.amount.toLocaleString('fa-IR')}</td>
                            <td className="px-6 py-4">
                                <Input
                                    type="text"
                                    maxLength={6}
                                    value={otpValues[request.id] || ''}
                                    onChange={(event) => handleOtpChange(request.id, event.target.value)}
                                    placeholder="مثلاً 123456"
                                />
                            </td>
                            <td className="px-6 py-4">
                                <Button onClick={() => handleConfirmPayment(request.id)}>تایید پرداخت</Button>
                            </td>
                        </tr>
                    )}
                />
            </SectionCard>

            <div className="grid gap-6 lg:grid-cols-2">
                <SectionCard
                    title="تاریخچه تراکنش‌ها"
                    description="نمایی کامل از تمامی شارژها و خریدهای گذشته"
                >
                    <Table
                        headers={['شناسه', 'نوع تراکنش', 'مبلغ (ریال)', 'وضعیت', 'تاریخ']}
                        data={transactions}
                        renderRow={(transaction) => (
                            <tr key={transaction.id}>
                                <td className="px-6 py-4 font-mono text-xs text-slate-400">{transaction.id}</td>
                                <td className="px-6 py-4 text-slate-200">{transaction.type}</td>
                                <td className="px-6 py-4 text-slate-200">{transaction.amount.toLocaleString('fa-IR')}</td>
                                <td className="px-6 py-4">
                                    <span className="rounded-full bg-emerald-500/15 px-3 py-1 text-xs text-emerald-300">
                                        {transaction.status}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-slate-300">{transaction.created_at}</td>
                            </tr>
                        )}
                    />
                </SectionCard>

                <SectionCard
                    title="شاخص‌های مالی شخصی"
                    description="روند هزینه‌کرد و مانده قابل استفاده را بررسی کنید"
                >
                    <div className="space-y-4">
                        <div className="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div>
                                <p className="text-sm font-medium text-white">میانگین خرید هفتگی</p>
                                <p className="text-xs text-slate-400">با توجه به تراکنش‌های ۳۰ روز گذشته</p>
                            </div>
                            <span className="rounded-full bg-white/10 px-3 py-1 text-xs text-slate-200">۸۲۰,۰۰۰ ریال</span>
                        </div>
                        <div className="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div>
                                <p className="text-sm font-medium text-white">بودجه باقی‌مانده ماه جاری</p>
                                <p className="text-xs text-slate-400">بر اساس سقف مصرف تعیین شده توسط شرکت</p>
                            </div>
                            <span className="rounded-full bg-emerald-500/15 px-3 py-1 text-xs text-emerald-300">۳,۵۴۰,۰۰۰ ریال</span>
                        </div>
                        <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6 text-slate-300">
                            <p>
                                برای درخواست شارژ فوری یا تغییر سقف پرداخت، از طریق بخش پشتیبانی با مدیر شرکت در تماس باشید.
                                اعلان‌ها و پیام‌ها نیز در همین صفحه به زودی نمایش داده خواهند شد.
                            </p>
                        </div>
                    </div>
                </SectionCard>
            </div>
                </>
            ) : activeTab === 'product-search' ? (
                <div className="space-y-6">
                    <SectionCard
                        title="جستجوی محصول در تمام فروشگاه‌ها"
                        description="محصول مورد نظر خود را از بین تمام فروشگاه‌ها جستجو کنید"
                    >
                        <div className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label className="text-xs uppercase tracking-[0.3em] text-slate-400">استان</label>
                                    <Select
                                        value={filterProvince}
                                        onChange={(e) => {
                                            setFilterProvince(e.target.value);
                                            setFilterCity('');
                                        }}
                                        options={[
                                            { value: '', label: 'همه استان‌ها' },
                                            ...provinces.map(p => ({ value: p.id, label: p.name }))
                                        ]}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs uppercase tracking-[0.3em] text-slate-400">شهر</label>
                                    <Select
                                        value={filterCity}
                                        onChange={(e) => setFilterCity(e.target.value)}
                                        disabled={!filterProvince}
                                        options={[
                                            { value: '', label: 'همه شهرها' },
                                            ...cities.map(c => ({ value: c.id, label: c.name }))
                                        ]}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-[0.3em] text-slate-400">جستجوی محصول</label>
                                <Input
                                    value={globalProductSearch}
                                    onChange={(e) => setGlobalProductSearch(e.target.value)}
                                    placeholder="نام محصول را وارد کنید..."
                                />
                            </div>
                        </div>
                    </SectionCard>

                    {searchResults.length > 0 && (
                        <SectionCard title="نتایج جستجو">
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {searchResults.map((product) => (
                                    <div
                                        key={product.id}
                                        onClick={() => setSelectedProduct(product)}
                                        className="rounded-2xl border border-white/10 bg-white/5 p-4 cursor-pointer transition hover:bg-white/10 hover:border-sky-400/50"
                                    >
                                        <img
                                            src={product.image}
                                            alt={product.name}
                                            className="w-full h-48 rounded-xl object-cover mb-3 border border-white/10"
                                        />
                                        <h3 className="text-lg font-semibold text-white mb-1">{product.name}</h3>
                                        <p className="text-sm text-slate-400">{product.store_name}</p>
                                    </div>
                                ))}
                            </div>
                        </SectionCard>
                    )}

                    {globalProductSearch && searchResults.length === 0 && (
                        <SectionCard title="نتیجه‌ای یافت نشد">
                            <p className="text-slate-400">محصولی با این نام یافت نشد.</p>
                        </SectionCard>
                    )}

                    <Modal
                        isOpen={!!selectedProduct}
                        onClose={() => setSelectedProduct(null)}
                        title={selectedProduct?.name}
                    >
                        {selectedProduct && (
                            <div className="space-y-4">
                                <img
                                    src={selectedProduct.image}
                                    alt={selectedProduct.name}
                                    className="w-full h-64 rounded-xl object-cover"
                                />
                                <p className="text-slate-300 leading-relaxed">{selectedProduct.description}</p>
                                <p className="text-sm text-slate-400">فروشگاه: {selectedProduct.store_name}</p>
                            </div>
                        )}
                    </Modal>
                </div>
            ) : selectedStore ? (
                <div className="space-y-6">
                    {/* Store Header */}
                    <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <button
                            onClick={handleBackToStores}
                            className="mb-4 text-sm text-sky-400 hover:text-sky-300 flex items-center gap-2"
                        >
                            ← بازگشت به لیست فروشگاه‌ها
                        </button>
                        <div className="flex gap-6">
                            <img
                                src={selectedStore.image}
                                alt={selectedStore.name}
                                className="h-32 w-32 rounded-2xl object-cover border border-white/10"
                            />
                            <div className="flex-1">
                                <h2 className="text-2xl font-bold text-white mb-2">{selectedStore.name}</h2>
                                <p className="text-slate-300 mb-4">{selectedStore.description}</p>
                                <div className="space-y-2 text-sm text-slate-400">
                                    <p>📍 {selectedStore.address}</p>
                                    <p>📞 {selectedStore.phone}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Product Search */}
                    <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <Input
                            value={productSearch}
                            onChange={(e) => setProductSearch(e.target.value)}
                            placeholder="جستجوی محصول..."
                            className="w-full"
                        />
                    </div>

                    {/* Products Grid */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {filteredProducts.map((product) => (
                            <div
                                key={product.id}
                                onClick={() => handleProductClick(product)}
                                className="rounded-2xl border border-white/10 bg-white/5 p-4 cursor-pointer transition hover:bg-white/10 hover:border-sky-400/50"
                            >
                                <img
                                    src={product.image}
                                    alt={product.name}
                                    className="w-full h-48 rounded-xl object-cover mb-3 border border-white/10"
                                />
                                <h3 className="text-lg font-semibold text-white">{product.name}</h3>
                            </div>
                        ))}
                    </div>

                    {/* Product Modal */}
                    <Modal
                        isOpen={!!selectedProduct}
                        onClose={() => setSelectedProduct(null)}
                        title={selectedProduct?.name}
                    >
                        {selectedProduct && (
                            <div className="space-y-4">
                                <img
                                    src={selectedProduct.image}
                                    alt={selectedProduct.name}
                                    className="w-full h-64 rounded-xl object-cover"
                                />
                                <p className="text-slate-300 leading-relaxed">{selectedProduct.description}</p>
                            </div>
                        )}
                    </Modal>
                </div>
            ) : (
                <div className="space-y-4">
                    <SectionCard
                        title="فیلتر فروشگاه‌ها"
                        description="فروشگاه‌ها را بر اساس استان و شهر فیلتر کنید"
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-[0.3em] text-slate-400">استان</label>
                                <Select
                                    value={filterProvince}
                                    onChange={(e) => {
                                        setFilterProvince(e.target.value);
                                        setFilterCity('');
                                    }}
                                    options={[
                                        { value: '', label: 'همه استان‌ها' },
                                        ...provinces.map(p => ({ value: p.id, label: p.name }))
                                    ]}
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-xs uppercase tracking-[0.3em] text-slate-400">شهر</label>
                                <Select
                                    value={filterCity}
                                    onChange={(e) => setFilterCity(e.target.value)}
                                    disabled={!filterProvince}
                                    options={[
                                        { value: '', label: 'همه شهرها' },
                                        ...cities.map(c => ({ value: c.id, label: c.name }))
                                    ]}
                                />
                            </div>
                        </div>
                    </SectionCard>

                    {stores.map((store) => (
                        <div
                            key={store.id}
                            className="rounded-3xl border border-white/10 bg-white/5 p-6 hover:bg-white/10 transition cursor-pointer"
                            onClick={() => handleStoreClick(store)}
                        >
                            <div className="flex gap-6 items-center">
                                <img
                                    src={store.image}
                                    alt={store.name}
                                    className="h-24 w-24 rounded-2xl object-cover border border-white/10"
                                />
                                <div className="flex-1">
                                    <h3 className="text-xl font-bold text-white mb-2">{store.name}</h3>
                                    <p className="text-slate-300 text-sm mb-4">{store.description}</p>
                                    <Button>مشاهده محصولات</Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </PanelLayout>
    );
};

export default EmployeePanel;

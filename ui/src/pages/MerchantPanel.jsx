import React, { useEffect, useMemo, useState } from 'react';
import PanelLayout from '../components/PanelLayout';
import SectionCard from '../components/SectionCard';
import StatCard from '../components/StatCard';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import Select from '../components/Select';
import apiClient from '../lib/apiClient';
import { useAuth } from '../contexts/AuthContext';

const employeeDirectory = [
    { name: 'محمدرضا طاهری', nationalId: '1234567890', wallet: 2_450_000 },
    { name: 'سارا احمدی', nationalId: '0987654321', wallet: 1_280_000 },
    { name: 'مهدی رضایی', nationalId: '5566778899', wallet: 3_960_000 },
    { name: 'لیلا حسینی', nationalId: '1122334455', wallet: 640_000 },
    { name: 'الهام مرادی', nationalId: '2211445566', wallet: 4_100_000 },
];

const paymentRequestsSeed = [
    { id: 'REQ-2108', employee: 'محمدرضا طاهری', amount: 420_000, status: 'در انتظار تایید', created_at: '۱۴۰۲/۰۸/۰۹' },
    { id: 'REQ-2105', employee: 'مهدی رضایی', amount: 2_250_000, status: 'تایید شده', created_at: '۱۴۰۲/۰۸/۰۸' },
    { id: 'REQ-2099', employee: 'سارا احمدی', amount: 185_000, status: 'رد شده', created_at: '۱۴۰۲/۰۸/۰۷' },
];

const payoutHistorySeed = [
    { id: 'SET-901', amount: 12_500_000, status: 'پرداخت شده', created_at: '۱۴۰۲/۰۸/۰۵', account: 'حساب اصلی' },
    { id: 'SET-892', amount: 8_200_000, status: 'در انتظار پرداخت', created_at: '۱۴۰۲/۰۸/۰۳', account: 'کیف پول ارزی' },
    { id: 'SET-881', amount: 16_900_000, status: 'پرداخت شده', created_at: '۱۴۰۲/۰۷/۲۹', account: 'حساب اصلی' },
];

const MerchantPanel = () => {
    const { token } = useAuth();
    const [balance, setBalance] = useState(0);
    const [nationalId, setNationalId] = useState('');
    const [amount, setAmount] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [payoutAmount, setPayoutAmount] = useState('');
    const [payoutAccount, setPayoutAccount] = useState('main');
    const [payouts, setPayouts] = useState(payoutHistorySeed);
    const [requests, setRequests] = useState(paymentRequestsSeed);

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

    const filteredEmployees = useMemo(() => {
        if (!searchTerm) {
            return [];
        }
        return employeeDirectory.filter((employee) =>
            [employee.name, employee.nationalId]
                .join(' ')
                .toLowerCase()
                .includes(searchTerm.toLowerCase())
        );
    }, [searchTerm]);

    const todaysPending = requests.filter((request) => request.status === 'در انتظار تایید').length;
    const todaysApproved = requests.filter((request) => request.status === 'تایید شده').length;

    const handleEmployeePick = (employee) => {
        setNationalId(employee.nationalId);
        setSearchTerm('');
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
                    employee: nationalId,
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
            description="پرداخت‌های کارکنان را با جست‌وجوی هوشمند بر پایه کد ملی ثبت کنید، موجودی کیف پول خود را بررسی و درخواست‌های تسویه را در یک فضای روشن و مینیمال مدیریت نمایید."
        >
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
                    hint="پرداخت‌هایی که نیازمند تایید کارمند هستند"
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
                    title="جست‌وجوی کارمند و ثبت پرداخت"
                    description="کد ملی یا نام کارمند را وارد کنید، او را انتخاب و مبلغ را برای تایید ارسال کنید."
                    className="xl:col-span-2"
                >
                    <form className="grid gap-4 md:grid-cols-2" onSubmit={handlePaymentRequest}>
                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-[0.3em] text-slate-400">جست‌وجوی کارمند</label>
                            <Input
                                value={searchTerm || nationalId}
                                onChange={(event) => {
                                    setSearchTerm(event.target.value);
                                    setNationalId(event.target.value);
                                }}
                                placeholder="نام یا کد ملی کارمند"
                            />
                            {filteredEmployees.length > 0 && (
                                <div className="space-y-2 rounded-2xl border border-white/10 bg-white/5 p-3">
                                    {filteredEmployees.slice(0, 4).map((employee) => (
                                        <button
                                            type="button"
                                            key={employee.nationalId}
                                            onClick={() => handleEmployeePick(employee)}
                                            className="flex w-full items-center justify-between rounded-xl bg-white/5 px-3 py-2 text-left text-sm text-slate-200 transition hover:bg-white/10"
                                        >
                                            <span>{employee.name}</span>
                                            <span className="font-mono text-xs text-slate-400">{employee.nationalId}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-[0.3em] text-slate-400">مبلغ پرداخت (ریال)</label>
                            <Input
                                type="number"
                                min="0"
                                value={amount}
                                onChange={(event) => setAmount(event.target.value)}
                                placeholder="مثلاً 1,200,000"
                            />
                        </div>
                        <div className="md:col-span-2 flex items-center justify-between gap-3">
                            <p className="text-xs text-slate-400">
                                پس از تایید کارمند، مبلغ از کیف پول او کسر و به موجودی پذیرنده اضافه می‌شود.
                            </p>
                            <Button type="submit">ارسال برای تایید کارمند</Button>
                        </div>
                    </form>
                </SectionCard>

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
                        headers={['شناسه', 'کارمند', 'مبلغ (ریال)', 'وضعیت', 'تاریخ']}
                        data={requests}
                        renderRow={(request) => (
                            <tr key={request.id}>
                                <td className="px-6 py-4 font-mono text-xs text-slate-400">{request.id}</td>
                                <td className="px-6 py-4 text-slate-200">{request.employee}</td>
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
        </PanelLayout>
    );
};

export default MerchantPanel;

import React, { useEffect, useMemo, useState } from 'react';
import PanelLayout from '../components/PanelLayout';
import SectionCard from '../components/SectionCard';
import StatCard from '../components/StatCard';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
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

const EmployeePanel = () => {
    const { token } = useAuth();
    const [balance, setBalance] = useState(0);
    const [pendingRequests, setPendingRequests] = useState(pendingSeed);
    const [transactions, setTransactions] = useState(transactionsSeed);
    const [otpValues, setOtpValues] = useState({});

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

    return (
        <PanelLayout
            title="پنل کارمندان"
            description="درخواست‌های پرداخت، مانده کیف پول و تاریخچه خریدهای خود را به شکل شفاف مدیریت کنید و همیشه بدانید موجودی شما در چه وضعیتی قرار دارد."
        >
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
        </PanelLayout>
    );
};

export default EmployeePanel;

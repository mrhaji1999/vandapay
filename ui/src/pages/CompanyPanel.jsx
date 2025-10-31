import React, { useMemo, useState } from 'react';
import PanelLayout from '../components/PanelLayout';
import StatCard from '../components/StatCard';
import SectionCard from '../components/SectionCard';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import Select from '../components/Select';

const employeeSeed = [
    { id: 1, name: 'محمدرضا طاهری', national_id: '1234567890', wallet_balance: 2_450_000, purchases: 7 },
    { id: 2, name: 'سارا احمدی', national_id: '0987654321', wallet_balance: 1_280_000, purchases: 5 },
    { id: 3, name: 'مهدی رضایی', national_id: '5566778899', wallet_balance: 3_960_000, purchases: 11 },
    { id: 4, name: 'لیلا حسینی', national_id: '1122334455', wallet_balance: 640_000, purchases: 3 },
    { id: 5, name: 'الهام مرادی', national_id: '2211445566', wallet_balance: 4_100_000, purchases: 14 },
];

const purchasesSeed = [
    { id: 'INV-9821', employee: 'محمدرضا طاهری', merchant: 'سوپرمارکت مرکزی', amount: 420_000, date: '1402/08/09' },
    { id: 'INV-9822', employee: 'سارا احمدی', merchant: 'کافه لانژ', amount: 185_000, date: '1402/08/09' },
    { id: 'INV-9823', employee: 'مهدی رضایی', merchant: 'فروشگاه تجهیزات', amount: 2_250_000, date: '1402/08/08' },
    { id: 'INV-9824', employee: 'الهام مرادی', merchant: 'هایپر استار', amount: 1_180_000, date: '1402/08/07' },
];

const CompanyPanel = () => {
    const [selectedCompany, setSelectedCompany] = useState('vandapay');
    const [chargeAmount, setChargeAmount] = useState('');
    const [csvFile, setCsvFile] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');

    const totalEmployees = employeeSeed.length;
    const totalBalance = useMemo(
        () => employeeSeed.reduce((acc, employee) => acc + employee.wallet_balance, 0),
        []
    );
    const monthlySpend = useMemo(() => purchasesSeed.reduce((acc, purchase) => acc + purchase.amount, 0), []);
    const lowBalanceEmployees = useMemo(
        () => employeeSeed.filter((employee) => employee.wallet_balance < 750_000),
        []
    );

    const filteredEmployees = useMemo(() => {
        if (!searchTerm) {
            return employeeSeed;
        }
        return employeeSeed.filter((employee) =>
            [employee.name, employee.national_id]
                .join(' ')
                .toLowerCase()
                .includes(searchTerm.toLowerCase())
        );
    }, [searchTerm]);

    const handleFileChange = (event) => {
        const file = event.target.files?.[0];
        if (file) {
            setCsvFile(file);
        }
    };

    const handleUpload = (event) => {
        event.preventDefault();
        if (!csvFile) {
            alert('لطفاً فایل CSV کارکنان را انتخاب کنید.');
            return;
        }
        alert(`فایل «${csvFile.name}» برای شرکت انتخابی بارگذاری شد.`);
        setCsvFile(null);
        event.target.reset();
    };

    const handleChargeUsers = (event) => {
        event.preventDefault();
        if (!chargeAmount) {
            alert('مبلغ شارژ گروهی را وارد کنید.');
            return;
        }
        alert(`شارژ گروهی به مبلغ ${Number(chargeAmount).toLocaleString('fa-IR')} ریال ثبت شد.`);
        setChargeAmount('');
    };

    return (
        <PanelLayout
            title="پنل شرکت"
            description="تمام عملیات مرتبط با مدیریت شرکت‌ها، ساخت کاربرهای انبوه و شارژ خودکار کیف پول کارکنان را در یک فضای بصری و منظم دنبال کنید."
        >
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="مجموع کارکنان"
                    value={`${totalEmployees.toLocaleString('fa-IR')} نفر`}
                    hint="اطلاعات از فایل‌های CSV آپلود شده به صورت خودکار همگام می‌شود."
                />
                <StatCard
                    title="موجودی کل کیف پول‌ها"
                    value={`${totalBalance.toLocaleString('fa-IR')} ریال`}
                    trend={{ direction: 'up', label: '۳.۲٪ بیشتر از ماه قبل' }}
                />
                <StatCard
                    title="مصرف ماه جاری"
                    value={`${monthlySpend.toLocaleString('fa-IR')} ریال`}
                    hint="جمع پرداخت‌های نهایی‌شده توسط کارکنان در ۳۰ روز اخیر"
                    accent="from-emerald-500/80 to-teal-500/60"
                />
                <StatCard
                    title="کارمندان با موجودی کم"
                    value={`${lowBalanceEmployees.length.toLocaleString('fa-IR')} نفر`}
                    hint="هشدار فعال برای مانده‌های کمتر از ۷۵۰٬۰۰۰ ریال"
                    accent="from-rose-500/80 to-orange-500/60"
                    trend={{ direction: lowBalanceEmployees.length ? 'down' : 'neutral', label: 'اولویت پیگیری فوری' }}
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <SectionCard
                    title="بارگذاری فایل کارکنان"
                    description="برای هر شرکت فایل CSV حاوی شماره ملی و شماره همراه کارکنان را بارگذاری کنید تا اکانت‌ها و کیف پول‌ها به صورت خودکار ساخته شوند."
                    className="xl:col-span-2"
                >
                    <form className="grid gap-4 md:grid-cols-2" onSubmit={handleUpload}>
                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-[0.3em] text-slate-400">شرکت</label>
                            <Select
                                value={selectedCompany}
                                onChange={(event) => setSelectedCompany(event.target.value)}
                                options={[
                                    { label: 'VandaPay Holding', value: 'vandapay' },
                                    { label: 'Shahre Khodro', value: 'shahre-khodro' },
                                    { label: 'Atlas Tech', value: 'atlas-tech' },
                                ]}
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-[0.3em] text-slate-400">فایل CSV کارکنان</label>
                            <label className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-white/15 bg-white/5 px-4 py-6 text-center text-sm text-slate-300 transition hover:border-sky-300/60 hover:bg-white/10">
                                <span className="font-medium text-white">انتخاب فایل</span>
                                <span className="mt-2 text-xs text-slate-400">ستون‌های مورد نیاز: national_id, mobile</span>
                                <input type="file" accept=".csv" className="hidden" onChange={handleFileChange} />
                                {csvFile && <span className="mt-3 rounded-full bg-white/10 px-3 py-1 text-xs">{csvFile.name}</span>}
                            </label>
                        </div>
                        <div className="md:col-span-2 flex justify-end">
                            <Button type="submit">افزودن کاربران و ساخت کیف پول</Button>
                        </div>
                    </form>
                </SectionCard>

                <SectionCard
                    title="شارژ گروهی کیف پول"
                    description="پس از ساخت کاربران می‌توانید مبلغ مشخصی را برای تمامی کارکنان یا گروه منتخب اعمال کنید."
                >
                    <form className="space-y-4" onSubmit={handleChargeUsers}>
                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-[0.3em] text-slate-400">مبلغ شارژ (ریال)</label>
                            <Input
                                type="number"
                                min="0"
                                value={chargeAmount}
                                onChange={(event) => setChargeAmount(event.target.value)}
                                placeholder="مثلاً 5,000,000"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs uppercase tracking-[0.3em] text-slate-400">یادداشت داخلی</label>
                            <textarea
                                rows={3}
                                className="block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-400 shadow-inner shadow-black/5 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                                placeholder="توضیحی برای واحد مالی ثبت کنید (اختیاری)"
                            />
                        </div>
                        <Button type="submit" className="w-full">
                            ثبت شارژ گروهی
                        </Button>
                    </form>
                </SectionCard>
            </div>

            <SectionCard
                title="لیست کارکنان"
                description="وضعیت کیف پول و تعداد خرید هر کارمند را به صورت لحظه‌ای مشاهده و فیلتر کنید."
                action={
                    <div className="flex items-center gap-3">
                        <Input
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="جست‌وجوی نام یا کد ملی"
                        />
                    </div>
                }
            >
                <Table
                    headers={[
                        'نام و نام خانوادگی',
                        'کد ملی',
                        'موجودی کیف پول (ریال)',
                        'تعداد خرید',
                    ]}
                    data={filteredEmployees}
                    renderRow={(employee) => (
                        <tr key={employee.id}>
                            <td className="px-6 py-4 text-slate-200">{employee.name}</td>
                            <td className="px-6 py-4 font-mono text-sm text-slate-300">{employee.national_id}</td>
                            <td className="px-6 py-4 text-slate-200">{employee.wallet_balance.toLocaleString('fa-IR')}</td>
                            <td className="px-6 py-4 text-slate-300">{employee.purchases}</td>
                        </tr>
                    )}
                />
            </SectionCard>

            <div className="grid gap-6 lg:grid-cols-2">
                <SectionCard
                    title="گزارش خرید کارکنان"
                    description="لیست پرداخت‌های تایید شده برای پایش لحظه‌ای رفتار خرید هر شرکت"
                >
                    <Table
                        headers={['کد تراکنش', 'کارمند', 'پذیرنده', 'مبلغ (ریال)', 'تاریخ']}
                        data={purchasesSeed}
                        renderRow={(purchase) => (
                            <tr key={purchase.id}>
                                <td className="px-6 py-4 font-mono text-xs text-slate-400">{purchase.id}</td>
                                <td className="px-6 py-4 text-slate-200">{purchase.employee}</td>
                                <td className="px-6 py-4 text-slate-300">{purchase.merchant}</td>
                                <td className="px-6 py-4 text-slate-200">{purchase.amount.toLocaleString('fa-IR')}</td>
                                <td className="px-6 py-4 text-slate-300">{purchase.date}</td>
                            </tr>
                        )}
                    />
                </SectionCard>

                <SectionCard
                    title="هشدار مانده پایین"
                    description="کارکنانی که نیاز به شارژ فوری دارند را بررسی و به صورت انفرادی پیگیری کنید"
                >
                    <div className="space-y-4">
                        {lowBalanceEmployees.map((employee) => (
                            <div
                                key={employee.id}
                                className="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                            >
                                <div>
                                    <p className="text-sm font-medium text-white">{employee.name}</p>
                                    <p className="text-xs text-slate-400">کد ملی {employee.national_id}</p>
                                </div>
                                <span className="rounded-full bg-rose-500/15 px-3 py-1 text-xs text-rose-300">
                                    {employee.wallet_balance.toLocaleString('fa-IR')} ریال
                                </span>
                            </div>
                        ))}
                    </div>
                </SectionCard>
            </div>
        </PanelLayout>
    );
};

export default CompanyPanel;

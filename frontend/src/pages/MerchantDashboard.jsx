import { useEffect, useMemo, useState } from 'react';
import DashboardCard from '../components/common/DashboardCard.jsx';
import DataTable from '../components/common/DataTable.jsx';
import FormField from '../components/common/FormField.jsx';
import Badge from '../components/common/Badge.jsx';
import SearchInput from '../components/common/SearchInput.jsx';
import {
  createMerchantBankAccount,
  createMerchantPaymentRequest,
  createMerchantPayoutRequest,
  getMerchantBankAccounts,
  getMerchantPayments,
  getMerchantWallet,
  searchEmployeeByNationalId,
} from '../services/api.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { useToast } from '../hooks/useToast.jsx';

export default function MerchantDashboard() {
  const { showToast } = useToast();
  const [wallet, setWallet] = useState({ balance: 0, available: 0 });
  const [searchNationalId, setSearchNationalId] = useState('');
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [employeeLoading, setEmployeeLoading] = useState(false);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentLoading, setPaymentLoading] = useState(false);
  const [payments, setPayments] = useState([]);
  const [paymentsLoading, setPaymentsLoading] = useState(false);
  const [payoutAmount, setPayoutAmount] = useState('');
  const [payoutLoading, setPayoutLoading] = useState(false);
  const [bankAccounts, setBankAccounts] = useState([]);
  const [selectedBankAccount, setSelectedBankAccount] = useState('');
  const [bankForm, setBankForm] = useState({ bank_name: '', iban: '', card_number: '', holder: '' });
  const [bankLoading, setBankLoading] = useState(false);

  useEffect(() => {
    fetchWallet();
    fetchPayments();
    fetchBankAccounts();
  }, []);

  useEffect(() => {
    if (searchNationalId && searchNationalId.length >= 3) {
      const timeout = setTimeout(() => fetchEmployee(searchNationalId), 400);
      return () => clearTimeout(timeout);
    }
    setSelectedEmployee(null);
    return undefined;
  }, [searchNationalId]);

  const fetchWallet = async () => {
    try {
      const data = await getMerchantWallet();
      setWallet({
        balance: data.balance,
        available: data.available ?? data.balance,
      });
    } catch (error) {
      console.error(error);
    }
  };

  const fetchEmployee = async (nationalId) => {
    setEmployeeLoading(true);
    try {
      const data = await searchEmployeeByNationalId(nationalId);
      setSelectedEmployee(data || null);
    } catch (error) {
      console.error(error);
      setSelectedEmployee(null);
    } finally {
      setEmployeeLoading(false);
    }
  };

  const fetchPayments = async () => {
    setPaymentsLoading(true);
    try {
      const data = await getMerchantPayments({ per_page: 10 });
      setPayments(data.items || data || []);
    } catch (error) {
      console.error(error);
    } finally {
      setPaymentsLoading(false);
    }
  };

  const fetchBankAccounts = async () => {
    setBankLoading(true);
    try {
      const data = await getMerchantBankAccounts();
      const items = data.items || data || [];
      setBankAccounts(items);
      if (items.length > 0) {
        setSelectedBankAccount(items[0].id);
      }
    } catch (error) {
      console.error(error);
    } finally {
      setBankLoading(false);
    }
  };

  const handlePaymentSubmit = async (event) => {
    event.preventDefault();
    if (!selectedEmployee) {
      showToast({ title: 'کارمند انتخاب نشده است', type: 'error' });
      return;
    }
    setPaymentLoading(true);
    try {
      await createMerchantPaymentRequest({
        employee_id: selectedEmployee.id,
        amount: paymentAmount,
      });
      setPaymentAmount('');
      showToast({ title: 'درخواست پرداخت ثبت شد' });
      fetchPayments();
    } catch (error) {
      console.error(error);
      showToast({ title: 'ثبت ناموفق', type: 'error', message: 'لطفا دوباره تلاش کنید.' });
    } finally {
      setPaymentLoading(false);
    }
  };

  const handlePayoutSubmit = async (event) => {
    event.preventDefault();
    if (!selectedBankAccount) {
      showToast({ title: 'حساب بانکی را انتخاب کنید', type: 'error' });
      return;
    }
    if (!payoutAmount || Number(payoutAmount) > Number(wallet.available)) {
      showToast({ title: 'مبلغ بیش از موجودی است', type: 'error' });
      return;
    }
    setPayoutLoading(true);
    try {
      await createMerchantPayoutRequest({ amount: payoutAmount, bank_account_id: selectedBankAccount });
      showToast({ title: 'درخواست تسویه ثبت شد' });
      setPayoutAmount('');
      fetchWallet();
    } catch (error) {
      console.error(error);
      showToast({ title: 'ثبت ناموفق', type: 'error', message: 'امکان ثبت درخواست وجود ندارد.' });
    } finally {
      setPayoutLoading(false);
    }
  };

  const handleBankSubmit = async (event) => {
    event.preventDefault();
    setBankLoading(true);
    try {
      await createMerchantBankAccount(bankForm);
      setBankForm({ bank_name: '', iban: '', card_number: '', holder: '' });
      showToast({ title: 'حساب جدید ذخیره شد' });
      fetchBankAccounts();
    } catch (error) {
      console.error(error);
      showToast({ title: 'عدم موفقیت', type: 'error', message: 'ثبت حساب بانکی انجام نشد.' });
    } finally {
      setBankLoading(false);
    }
  };

  const paymentColumns = useMemo(
    () => [
      { label: 'کارمند', accessor: 'employee_name' },
      {
        label: 'مبلغ',
        accessor: 'amount',
        render: (row) => formatCurrency(row.amount),
      },
      {
        label: 'تاریخ',
        accessor: 'created_at',
        render: (row) => formatDate(row.created_at),
      },
      {
        label: 'وضعیت',
        accessor: 'status',
        render: (row) => <Badge status={row.status}>{row.status_label || row.status}</Badge>,
      },
    ],
    [],
  );

  return (
    <div className="merchant-dashboard">
      <section className="grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
        <DashboardCard title="موجودی فعلی" value={formatCurrency(wallet.balance)} footer="قابل برداشت" />
        <DashboardCard title="مبلغ قابل تسویه" value={formatCurrency(wallet.available)} />
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>پرداخت به کارمند</h2>
            <p>کارمند را با کد ملی جست‌وجو کنید و درخواست پرداخت ارسال کنید.</p>
          </div>
          <div style={{ minWidth: '260px' }}>
            <SearchInput
              placeholder="جست‌وجوی کد ملی"
              value={searchNationalId}
              onChange={setSearchNationalId}
              debounce={0}
            />
          </div>
        </div>
        {employeeLoading && <p>در حال جست‌وجوی کارمند...</p>}
        {selectedEmployee ? (
          <div className="selected-employee">
            <div>
              <h3>{selectedEmployee.name}</h3>
              <p>موجودی: {formatCurrency(selectedEmployee.balance)}</p>
            </div>
            <span>کد ملی: {selectedEmployee.national_id}</span>
          </div>
        ) : (
          <p className="section-subtitle">کارمندی انتخاب نشده است.</p>
        )}
        <form className="grid" style={{ gap: '16px', marginTop: '16px' }} onSubmit={handlePaymentSubmit}>
          <FormField label="مبلغ پرداخت">
            <input
              type="number"
              min="0"
              value={paymentAmount}
              onChange={(event) => setPaymentAmount(event.target.value)}
              placeholder="مثلا ۵۰۰٬۰۰۰ ریال"
            />
          </FormField>
          <button className="primary" type="submit" disabled={paymentLoading}>
            {paymentLoading ? 'در حال ارسال...' : 'ارسال درخواست پرداخت'}
          </button>
        </form>
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>درخواست‌های پرداخت اخیر</h2>
            <p>پیگیری وضعیت پرداخت‌ها</p>
          </div>
        </div>
        <DataTable
          columns={paymentColumns}
          data={payments}
          loading={paymentsLoading}
          emptyMessage="درخواستی ثبت نشده است."
        />
      </section>

      <section className="card" id="banks">
        <div className="section-header">
          <div>
            <h2>درخواست تسویه</h2>
            <p>مبلغ مورد نظر را از موجودی خود برداشت کنید.</p>
          </div>
        </div>
        <form className="grid" style={{ gap: '16px' }} onSubmit={handlePayoutSubmit}>
          <FormField label="انتخاب حساب بانکی">
            <select value={selectedBankAccount} onChange={(event) => setSelectedBankAccount(event.target.value)}>
              {bankAccounts.length === 0 && <option value=''>حسابی ثبت نشده است</option>}
              {bankAccounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.bank_name} - {account.iban}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="مبلغ درخواستی">
            <input
              type="number"
              min="0"
              max={wallet.available}
              value={payoutAmount}
              onChange={(event) => setPayoutAmount(event.target.value)}
            />
          </FormField>
          <button className="primary" type="submit" disabled={payoutLoading}>
            {payoutLoading ? 'در حال ارسال...' : 'ثبت درخواست تسویه'}
          </button>
        </form>
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>حساب‌های بانکی</h2>
            <p>حساب‌های خود را مدیریت کنید.</p>
          </div>
        </div>
        <div className="bank-accounts">
          {bankAccounts.length === 0 ? (
            <div className="empty-state" style={{ boxShadow: 'none', padding: '24px' }}>
              حساب بانکی ثبت نشده است.
            </div>
          ) : (
            <ul>
              {bankAccounts.map((account) => (
                <li key={account.id}>
                  <strong>{account.bank_name}</strong>
                  <span>شبا: {account.iban}</span>
                  <span>کارت: {account.card_number}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
        <form className="grid" style={{ gap: '12px', marginTop: '16px' }} onSubmit={handleBankSubmit}>
          <FormField label="نام بانک">
            <input
              value={bankForm.bank_name}
              onChange={(event) => setBankForm((prev) => ({ ...prev, bank_name: event.target.value }))}
            />
          </FormField>
          <FormField label="شماره شبا">
            <input value={bankForm.iban} onChange={(event) => setBankForm((prev) => ({ ...prev, iban: event.target.value }))} />
          </FormField>
          <FormField label="شماره کارت">
            <input
              value={bankForm.card_number}
              onChange={(event) => setBankForm((prev) => ({ ...prev, card_number: event.target.value }))}
            />
          </FormField>
          <FormField label="نام صاحب حساب">
            <input value={bankForm.holder} onChange={(event) => setBankForm((prev) => ({ ...prev, holder: event.target.value }))} />
          </FormField>
          <button className="primary" type="submit" disabled={bankLoading}>
            {bankLoading ? 'در حال ذخیره...' : 'افزودن حساب جدید'}
          </button>
        </form>
      </section>
    </div>
  );
}

import { useEffect, useMemo, useState } from 'react';
import DashboardCard from '../components/common/DashboardCard.jsx';
import DataTable from '../components/common/DataTable.jsx';
import Badge from '../components/common/Badge.jsx';
import Modal from '../components/common/Modal.jsx';
import OtpInput from '../components/common/OtpInput.jsx';
import {
  confirmEmployeePaymentRequest,
  getEmployeePendingRequests,
  getEmployeeTransactions,
  getEmployeeWalletSummary,
  verifyEmployeePaymentOtp,
} from '../services/api.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { useToast } from '../hooks/useToast.jsx';

export default function EmployeeDashboard() {
  const { showToast } = useToast();
  const [walletBalance, setWalletBalance] = useState(0);
  const [categoryLimits, setCategoryLimits] = useState([]);
  const [loadingWallet, setLoadingWallet] = useState(false);
  const [pendingRequests, setPendingRequests] = useState([]);
  const [transactions, setTransactions] = useState([]);
  const [loadingRequests, setLoadingRequests] = useState(false);
  const [loadingTransactions, setLoadingTransactions] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState(null);
  const [otpModalOpen, setOtpModalOpen] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [otpCode, setOtpCode] = useState('');

  useEffect(() => {
    fetchWalletSummary();
    fetchRequests();
    fetchTransactions();
  }, []);

  const fetchWalletSummary = async () => {
    setLoadingWallet(true);
    try {
      const data = await getEmployeeWalletSummary();
      if (data) {
        setWalletBalance(data.wallet_balance ?? 0);
        setCategoryLimits(Array.isArray(data.categories) ? data.categories : []);
      }
    } catch (error) {
      console.error(error);
    } finally {
      setLoadingWallet(false);
    }
  };

  const fetchRequests = async () => {
    setLoadingRequests(true);
    try {
      const data = await getEmployeePendingRequests();
      setPendingRequests(data.items || data || []);
    } catch (error) {
      console.error(error);
    } finally {
      setLoadingRequests(false);
    }
  };

  const fetchTransactions = async () => {
    setLoadingTransactions(true);
    try {
      const data = await getEmployeeTransactions();
      setTransactions(data.items || data || []);
    } catch (error) {
      console.error(error);
    } finally {
      setLoadingTransactions(false);
    }
  };

  const handleConfirm = async (request) => {
    try {
      await confirmEmployeePaymentRequest(request.id);
      setSelectedRequest(request);
      setOtpModalOpen(true);
    } catch (error) {
      console.error(error);
      showToast({ title: 'خطا', message: 'امکان ارسال کد یکبار مصرف نیست.', type: 'error' });
    }
  };

  const handleOtpSubmit = async (code) => {
    if (!selectedRequest) return;
    setVerifying(true);
    try {
      await verifyEmployeePaymentOtp(selectedRequest.id, code || otpCode);
      showToast({ title: 'پرداخت موفق', message: 'تراکنش با موفقیت تکمیل شد.' });
      setOtpCode('');
      setOtpModalOpen(false);
      setSelectedRequest(null);
      fetchWalletSummary();
      fetchRequests();
      fetchTransactions();
    } catch (error) {
      console.error(error);
      showToast({ title: 'کد نادرست', type: 'error', message: 'لطفا دوباره تلاش کنید.' });
      setOtpCode('');
    } finally {
      setVerifying(false);
    }
  };

  const pendingColumns = useMemo(
    () => [
      { label: 'پذیرنده', accessor: 'merchant_name' },
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

  const transactionColumns = useMemo(
    () => [
      { label: 'پذیرنده', accessor: 'merchant_name' },
      { label: 'مبلغ', accessor: 'amount', render: (row) => formatCurrency(row.amount) },
      { label: 'تاریخ', accessor: 'created_at', render: (row) => formatDate(row.created_at) },
      {
        label: 'وضعیت',
        accessor: 'status',
        render: (row) => <Badge status={row.status}>{row.status_label || row.status}</Badge>,
      },
    ],
    [],
  );

  return (
    <div className="employee-dashboard">
      <section className="grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
        <DashboardCard title="موجودی کیف پول" value={formatCurrency(walletBalance)} />
        {loadingWallet && (
          <div className="card">
            <div className="empty-state small">
              <span>در حال بارگذاری دسته‌بندی‌ها...</span>
            </div>
          </div>
        )}
        {!loadingWallet && categoryLimits.length === 0 && (
          <div className="card">
            <div className="empty-state small">
              <span>هنوز سقف‌بندی‌ای برای شما ثبت نشده است.</span>
            </div>
          </div>
        )}
        {!loadingWallet &&
          categoryLimits.map((category) => (
            <DashboardCard
              key={category.category_id}
              title={category.category_name}
              value={formatCurrency(category.remaining)}
              footer={`سقف: ${formatCurrency(category.limit)} | مصرف شده: ${formatCurrency(category.spent)}`}
            />
          ))}
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>درخواست‌های منتظر تایید</h2>
            <p>درخواست‌هایی که نیاز به تایید شما دارند.</p>
          </div>
        </div>
        <DataTable
          columns={pendingColumns}
          data={pendingRequests}
          loading={loadingRequests}
          emptyMessage="درخواستی برای تایید وجود ندارد."
          renderActions={(row) => (
            <button className="primary" type="button" onClick={() => handleConfirm(row)}>
              تایید
            </button>
          )}
        />
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>تراکنش‌های اخیر</h2>
            <p>نمایش آخرین تراکنش‌های شما.</p>
          </div>
        </div>
        <DataTable
          columns={transactionColumns}
          data={transactions}
          loading={loadingTransactions}
          emptyMessage="تراکنشی ثبت نشده است."
        />
      </section>

      {otpModalOpen && (
        <Modal
          title="کد تایید پرداخت"
          onClose={() => {
            setOtpModalOpen(false);
            setSelectedRequest(null);
          }}
          actions={(
            <>
              <button className="ghost" type="button" onClick={() => setOtpModalOpen(false)}>
                بستن
              </button>
              <button
                className="primary"
                type="button"
                disabled={verifying || otpCode.length < 5}
                onClick={() => handleOtpSubmit(otpCode)}
              >
                {verifying ? 'در حال تایید...' : 'تایید و پرداخت'}
              </button>
            </>
          )}
        >
          <p>کد ارسال شده به تلفن همراه خود را وارد کنید.</p>
          <OtpInput length={5} onChange={setOtpCode} onComplete={handleOtpSubmit} />
        </Modal>
      )}
    </div>
  );
}

import { useEffect, useMemo, useState } from 'react';
import DashboardCard from '../components/common/DashboardCard.jsx';
import DataTable from '../components/common/DataTable.jsx';
import SearchInput from '../components/common/SearchInput.jsx';
import UploadField from '../components/common/UploadField.jsx';
import FormField from '../components/common/FormField.jsx';
import Badge from '../components/common/Badge.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import {
  bulkChargeEmployees,
  getCompanyEmployees,
  getCompanyReports,
} from '../services/api.js';
import { formatCurrency, formatDate } from '../utils/format.js';
import { useToast } from '../hooks/useToast.jsx';

export default function CompanyDashboard() {
  const { user } = useAuth();
  const companyId = user?.company_id || user?.id;
  const { showToast } = useToast();
  const [stats, setStats] = useState({
    totalEmployees: 0,
    totalBalance: 0,
    monthlyTransactions: 0,
    lastCsvStatus: 'بدون بارگذاری',
  });
  const [employees, setEmployees] = useState([]);
  const [reports, setReports] = useState([]);
  const [search, setSearch] = useState('');
  const [pagination, setPagination] = useState({ page: 1, perPage: 10, total: 0 });
  const [loadingEmployees, setLoadingEmployees] = useState(false);
  const [loadingReports, setLoadingReports] = useState(false);
  const [bulkForm, setBulkForm] = useState({ company: companyId, amount: '', file: null });
  const [bulkSummary, setBulkSummary] = useState(null);
  const [bulkLoading, setBulkLoading] = useState(false);
  const [dateFilter, setDateFilter] = useState({ from: '', to: '' });

  useEffect(() => {
    if (companyId) {
      setBulkForm((prev) => ({ ...prev, company: companyId }));
    }
  }, [companyId]);

  useEffect(() => {
    fetchEmployees();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, pagination.page, pagination.perPage]);

  useEffect(() => {
    fetchReports();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dateFilter]);

  const fetchEmployees = async () => {
    if (!companyId) return;
    setLoadingEmployees(true);
    try {
      const data = await getCompanyEmployees(companyId, {
        search,
        page: pagination.page,
        per_page: pagination.perPage,
      });
      setEmployees(data.items || data || []);
      setPagination((prev) => ({ ...prev, total: data.total || data.items?.length || 0 }));
      setStats((prev) => ({
        ...prev,
        totalEmployees: data.meta?.total_employees ?? data.total ?? data.items?.length ?? prev.totalEmployees,
        totalBalance: data.meta?.total_balance ?? prev.totalBalance,
        monthlyTransactions: data.meta?.monthly_transactions ?? prev.monthlyTransactions,
      }));
    } catch (error) {
      console.error(error);
    } finally {
      setLoadingEmployees(false);
    }
  };

  const fetchReports = async () => {
    if (!companyId) return;
    setLoadingReports(true);
    try {
      const data = await getCompanyReports(companyId, dateFilter);
      setReports(data.items || data || []);
    } catch (error) {
      console.error(error);
    } finally {
      setLoadingReports(false);
    }
  };

  const handleBulkSubmit = async (event) => {
    event.preventDefault();
    if (!bulkForm.file) {
      showToast({ title: 'خطا', message: 'لطفا فایل CSV را انتخاب کنید.', type: 'error' });
      return;
    }
    setBulkLoading(true);
    setBulkSummary(null);
    try {
      const formData = new FormData();
      formData.append('company_id', bulkForm.company);
      formData.append('amount', bulkForm.amount);
      formData.append('file', bulkForm.file);
      const response = await bulkChargeEmployees(formData);
      setBulkSummary(response);
      setStats((prev) => ({ ...prev, lastCsvStatus: response.status || 'موفق' }));
      showToast({ title: 'بارگذاری موفق', message: 'نتیجه عملیات در جدول نمایش داده شد.' });
      fetchEmployees();
    } catch (error) {
      console.error(error);
      setBulkSummary({ status: 'خطا', message: 'امکان پردازش فایل وجود ندارد.' });
      showToast({ title: 'خطای سرور', message: 'بارگذاری انجام نشد.', type: 'error' });
    } finally {
      setBulkLoading(false);
    }
  };

  const employeeColumns = useMemo(
    () => [
      { label: 'نام', accessor: 'name' },
      { label: 'کد ملی', accessor: 'national_id' },
      { label: 'تلفن', accessor: 'phone' },
      {
        label: 'موجودی کیف پول',
        accessor: 'balance',
        render: (row) => formatCurrency(row.balance),
      },
      { label: 'تعداد خرید', accessor: 'purchases_count' },
      {
        label: 'آخرین تراکنش',
        accessor: 'last_transaction_at',
        render: (row) => formatDate(row.last_transaction_at),
      },
    ],
    [],
  );

  const reportColumns = useMemo(
    () => [
      { label: 'کارمند', accessor: 'employee_name' },
      { label: 'پذیرنده', accessor: 'merchant_name' },
      {
        label: 'مبلغ',
        accessor: 'amount',
        render: (row) => formatCurrency(row.amount),
      },
      {
        label: 'تاریخ',
        accessor: 'date',
        render: (row) => formatDate(row.date),
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
    <div className="company-dashboard">
      <section className="grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
        <DashboardCard title="تعداد کارمندان" value={stats.totalEmployees} />
        <DashboardCard title="مجموع موجودی کیف پول" value={formatCurrency(stats.totalBalance)} />
        <DashboardCard title="تراکنش‌های این ماه" value={stats.monthlyTransactions} />
        <DashboardCard title="آخرین بارگذاری CSV" value={stats.lastCsvStatus} />
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>لیست کارمندان</h2>
            <p>جست‌وجوی سریع بر اساس نام یا کد ملی</p>
          </div>
          <SearchInput placeholder="جست‌وجوی کارمند" value={search} onChange={setSearch} debounce={400} />
        </div>
        <DataTable
          columns={employeeColumns}
          data={employees}
          loading={loadingEmployees}
          emptyMessage="هنوز کارمندی ثبت نشده است."
        />
        <div className="pagination">
          <button
            type="button"
            className="ghost"
            onClick={() => setPagination((prev) => ({ ...prev, page: Math.max(prev.page - 1, 1) }))}
            disabled={pagination.page === 1}
          >
            قبلی
          </button>
          <span>
            صفحه {pagination.page} از {Math.max(1, Math.ceil((pagination.total || 1) / pagination.perPage))}
          </span>
          <button
            type="button"
            className="ghost"
            onClick={() =>
              setPagination((prev) => ({
                ...prev,
                page: Math.min(
                  prev.page + 1,
                  Math.max(1, Math.ceil((prev.total || employees.length || 1) / prev.perPage)),
                ),
              }))
            }
            disabled={pagination.page >= Math.ceil((pagination.total || employees.length || 1) / pagination.perPage)}
          >
            بعدی
          </button>
        </div>
      </section>

      <section className="card">
        <h2>بارگذاری گروهی و شارژ</h2>
        <p className="section-subtitle">فایل CSV با ستون‌های نام، کد ملی و شماره تماس را بارگذاری کنید.</p>
        <form className="grid" style={{ gap: '16px', marginTop: '16px' }} onSubmit={handleBulkSubmit}>
          <FormField label="انتخاب شرکت">
            <select
              value={bulkForm.company}
              onChange={(event) => setBulkForm((prev) => ({ ...prev, company: event.target.value }))}
            >
              <option value={companyId}>شرکت من</option>
            </select>
          </FormField>
          <FormField label="مبلغ شارژ برای هر کارمند">
            <input
              type="number"
              value={bulkForm.amount}
              onChange={(event) => setBulkForm((prev) => ({ ...prev, amount: event.target.value }))}
              placeholder="مثلا ۱٬۰۰۰٬۰۰۰ ریال"
              min="0"
            />
          </FormField>
          <UploadField
            label="فایل CSV"
            hint={bulkForm.file ? bulkForm.file.name : 'فایل خود را بکشید و رها کنید یا انتخاب کنید'}
            accept=".csv"
            onChange={(event) =>
              setBulkForm((prev) => ({
                ...prev,
                file: event.target.files?.[0] || null,
              }))
            }
          />
          <button className="primary" type="submit" disabled={bulkLoading}>
            {bulkLoading ? 'در حال ارسال...' : 'افزودن و شارژ کیف پول'}
          </button>
        </form>
        {bulkSummary && (
          <div className="bulk-summary">
            <h3>خلاصه عملیات</h3>
            <p>{bulkSummary.message || 'فرآیند تکمیل شد.'}</p>
          </div>
        )}
      </section>

      <section className="card">
        <div className="section-header">
          <div>
            <h2>گزارش خرید کارمندان</h2>
            <p>گزارش تراکنش‌ها بر اساس تاریخ</p>
          </div>
          <div className="filters">
            <FormField label="از تاریخ">
              <input
                type="date"
                value={dateFilter.from}
                onChange={(event) => setDateFilter((prev) => ({ ...prev, from: event.target.value }))}
              />
            </FormField>
            <FormField label="تا تاریخ">
              <input
                type="date"
                value={dateFilter.to}
                onChange={(event) => setDateFilter((prev) => ({ ...prev, to: event.target.value }))}
              />
            </FormField>
          </div>
        </div>
        <DataTable
          columns={reportColumns}
          data={reports}
          loading={loadingReports}
          emptyMessage="هنوز خریدی ثبت نشده است."
        />
      </section>
    </div>
  );
}

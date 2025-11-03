import { useEffect, useMemo, useState } from 'react';
import DataTable from '../components/common/DataTable.jsx';
import Badge from '../components/common/Badge.jsx';
import { getEmployeeTransactions } from '../services/api.js';
import { formatCurrency, formatDate } from '../utils/format.js';

export default function EmployeeTransactions() {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const loadTransactions = async () => {
      setLoading(true);
      try {
        const data = await getEmployeeTransactions();
        setTransactions(data.items || data || []);
      } catch (error) {
        console.error(error);
      } finally {
        setLoading(false);
      }
    };

    loadTransactions();
  }, []);

  const columns = useMemo(
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
    <div className="card">
      <div className="section-header">
        <div>
          <h2>سوابق خرید</h2>
          <p>لیست کامل خریدهای انجام شده بدون نیاز به تایید.</p>
        </div>
      </div>
      <DataTable
        columns={columns}
        data={transactions}
        loading={loading}
        emptyMessage="تراکنشی ثبت نشده است."
      />
    </div>
  );
}

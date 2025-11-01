import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { DateRangePicker } from '../../components/common/DateRangePicker';
import { DataTable, type Column } from '../../components/common/DataTable';
import { Button } from '../../components/ui/button';
import { apiClient } from '../../api/client';
import { exportToCsv } from '../../lib/csv';
import { unwrapWordPressList } from '../../api/wordpress';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  status?: string;
}

export const AdminTransactionsPage = () => {
  const [filters, setFilters] = useState<{ from?: string; to?: string }>({});

  const { data: transactions = [], isFetching } = useQuery({
    queryKey: ['admin', 'transactions', filters],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (filters.from) params.set('from', filters.from);
      if (filters.to) params.set('to', filters.to);
      const response = await apiClient.get(`/admin/transactions?${params.toString()}`);
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((transaction) => ({
        id: Number(transaction.id ?? 0),
        type: String(transaction.type ?? ''),
        amount: Number(transaction.amount ?? 0),
        created_at: String(transaction.created_at ?? ''),
        status: transaction.status ? String(transaction.status) : undefined
      }));
    }
  });

  const columns: Column<Transaction>[] = [
    { key: 'id', header: 'شناسه' },
    { key: 'type', header: 'نوع' },
    { key: 'amount', header: 'مبلغ' },
    { key: 'created_at', header: 'تاریخ' },
    {
      key: 'status',
      header: 'وضعیت',
      render: (transaction) => transaction.status ?? '—'
    }
  ];

  return (
    <DashboardLayout>
      <section className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold">گزارش تراکنش‌ها</h1>
            <p className="text-sm text-muted-foreground">تراکنش‌های کیف پول را بررسی، فیلتر و خروجی بگیرید.</p>
          </div>
          <Button variant="outline" onClick={() => exportToCsv('admin-transactions.csv', transactions)} disabled={!transactions.length}>
            دریافت CSV
          </Button>
        </div>
        <DateRangePicker onChange={setFilters} />
        <DataTable data={transactions} columns={columns} searchPlaceholder="جست‌وجوی تراکنش‌ها" />
        {isFetching && <p className="text-sm text-muted-foreground">در حال دریافت آخرین داده‌ها…</p>}
      </section>
    </DashboardLayout>
  );
};

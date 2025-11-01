import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ResponsiveContainer, LineChart, Line, CartesianGrid, XAxis, YAxis, Tooltip } from 'recharts';
import { apiClient } from '../../api/client';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { DataTable, type Column } from '../../components/common/DataTable';
import { Button } from '../../components/ui/button';
import { DateRangePicker } from '../../components/common/DateRangePicker';
import { PayoutStatusBadge } from '../../components/common/PayoutStatusBadge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { exportToCsv } from '../../lib/csv';
import { useAuth } from '../../store/auth';
import { hasCapability } from '../../types/user';

interface StatsResponse {
  total_companies: number;
  total_merchants: number;
  total_payouts_pending: number;
  total_transactions: number;
  chart?: { label: string; value: number }[];
}

interface Company {
  id: number;
  title: string;
  status: string;
  meta?: {
    _cwm_company_type?: string;
    company_email?: string;
  };
}

interface Employee {
  id: number;
  name: string;
  balance: number;
  national_id?: string;
}

interface Merchant {
  id: number;
  name: string;
  wallet_balance: number;
  pending_payouts: number;
}

interface Payout {
  id: number;
  company?: string;
  merchant?: string;
  amount: number;
  status: string;
  created_at?: string;
}

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
}

export const AdminDashboard = () => {
  const { user } = useAuth();
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [dateFilter, setDateFilter] = useState<{ from?: string; to?: string }>({});

  const { data: stats } = useQuery({
    queryKey: ['admin', 'stats'],
    queryFn: async () => (await apiClient.get<StatsResponse>('/admin/stats')).data
  });

  const { data: companies = [] } = useQuery({
    queryKey: ['admin', 'companies'],
    queryFn: async () => (await apiClient.get<Company[]>('/admin/companies')).data
  });

  const { data: merchants = [] } = useQuery({
    queryKey: ['admin', 'merchants'],
    queryFn: async () => (await apiClient.get<Merchant[]>('/admin/merchants')).data
  });

  const { data: payouts = [] } = useQuery({
    queryKey: ['admin', 'payouts'],
    queryFn: async () => (await apiClient.get<Payout[]>('/admin/payouts')).data
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['admin', 'transactions', dateFilter],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (dateFilter.from) params.set('from', dateFilter.from);
      if (dateFilter.to) params.set('to', dateFilter.to);
      const response = await apiClient.get<Transaction[]>(`/admin/transactions?${params.toString()}`);
      return response.data;
    }
  });

  const { data: employees = [] } = useQuery({
    queryKey: ['admin', 'companies', selectedCompany?.id, 'employees'],
    queryFn: async () => {
      if (!selectedCompany) return [] as Employee[];
      const response = await apiClient.get<Employee[]>(`/admin/companies/${selectedCompany.id}/employees`);
      return response.data;
    },
    enabled: Boolean(selectedCompany?.id)
  });

  const companyColumns: Column<Company>[] = [
    { key: 'id', header: 'ID' },
    { key: 'title', header: 'Title / Name' },
    { key: 'status', header: 'Status' },
    {
      key: 'type',
      header: 'Company type',
      render: (company) => company.meta?._cwm_company_type ?? '—'
    },
    {
      key: 'email',
      header: 'Company email',
      render: (company) => company.meta?.company_email ?? '—'
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (company) => (
        <Button size="sm" variant="outline" onClick={() => setSelectedCompany(company)}>
          View employees
        </Button>
      )
    }
  ];

  const merchantColumns: Column<Merchant>[] = [
    { key: 'name', header: 'Merchant' },
    { key: 'wallet_balance', header: 'Wallet balance' },
    { key: 'pending_payouts', header: 'Pending payouts' }
  ];

  const payoutColumns: Column<Payout>[] = [
    { key: 'id', header: 'ID' },
    { key: 'merchant', header: 'Merchant' },
    { key: 'company', header: 'Company' },
    { key: 'amount', header: 'Amount' },
    {
      key: 'status',
      header: 'Status',
      render: (payout) => <PayoutStatusBadge status={payout.status} />
    }
  ];

  const transactionColumns: Column<Transaction>[] = [
    { key: 'id', header: 'ID' },
    { key: 'type', header: 'Type' },
    { key: 'amount', header: 'Amount' },
    { key: 'created_at', header: 'Date' },
    { key: 'description', header: 'Description' }
  ];

  if (!hasCapability(user, 'manage_wallets')) {
    return (
      <DashboardLayout>
        <Card>
          <CardHeader>
            <CardTitle>Unauthorized</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">
              Your account does not have permission to access the admin dashboard.
            </p>
          </CardContent>
        </Card>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader>
            <CardTitle>Total companies</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_companies ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Total merchants</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_merchants ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Pending payouts</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_payouts_pending ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Total transactions</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{stats?.total_transactions ?? 0}</CardContent>
        </Card>
      </section>

      <section>
        <h2 className="text-xl font-semibold">Companies</h2>
        <DataTable data={companies} columns={companyColumns} searchPlaceholder="Search companies" />
      </section>

      {stats?.chart && stats.chart.length > 0 && (
        <section className="rounded-lg border bg-white p-6">
          <h2 className="text-xl font-semibold">Activity overview</h2>
          <p className="text-sm text-muted-foreground">Performance indicators extracted from /admin/stats.</p>
          <div className="mt-4 h-72">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={stats.chart}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="label" />
                <YAxis />
                <Tooltip />
                <Line type="monotone" dataKey="value" stroke="#1f2937" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </section>
      )}

      {selectedCompany && (
        <section className="rounded-lg border bg-white p-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold">Employees for {selectedCompany.title}</h3>
              <p className="text-sm text-muted-foreground">Wallet balances per employee</p>
            </div>
            <Button variant="ghost" onClick={() => setSelectedCompany(null)}>
              Close
            </Button>
          </div>
          <Table className="mt-4">
            <TableHeader>
              <TableRow>
                <TableHead>ID</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>National ID</TableHead>
                <TableHead>Balance</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {employees.map((employee) => (
                <TableRow key={employee.id}>
                  <TableCell>{employee.id}</TableCell>
                  <TableCell>{employee.name}</TableCell>
                  <TableCell>{employee.national_id ?? '—'}</TableCell>
                  <TableCell>{employee.balance}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </section>
      )}

      <section>
        <h2 className="text-xl font-semibold">Merchants</h2>
        <DataTable data={merchants} columns={merchantColumns} searchPlaceholder="Search merchants" />
      </section>

      <section>
        <h2 className="text-xl font-semibold">Payout requests</h2>
        <DataTable data={payouts} columns={payoutColumns} searchPlaceholder="Search payouts" />
      </section>

      <section className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 className="text-xl font-semibold">Transactions</h2>
            <p className="text-sm text-muted-foreground">Filter by date and export as CSV.</p>
          </div>
          <Button onClick={() => exportToCsv('transactions.csv', transactions)} variant="outline">
            Export CSV
          </Button>
        </div>
        <DateRangePicker onChange={setDateFilter} />
        <DataTable data={transactions} columns={transactionColumns} searchPlaceholder="Search transactions" />
      </section>
    </DashboardLayout>
  );
};

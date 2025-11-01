import { FormEvent, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Button } from '../../components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { PayoutStatusBadge } from '../../components/common/PayoutStatusBadge';
import { apiClient } from '../../api/client';

interface BalanceResponse {
  balance: number;
}

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
}

interface PayoutStatus {
  id: number;
  amount: number;
  status: string;
  created_at: string;
}

export const MerchantDashboard = () => {
  const queryClient = useQueryClient();
  const [paymentLoading, setPaymentLoading] = useState(false);
  const [payoutLoading, setPayoutLoading] = useState(false);

  const { data: balance } = useQuery({
    queryKey: ['merchant', 'balance'],
    queryFn: async () => (await apiClient.get<BalanceResponse>('/wallet/balance')).data
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['merchant', 'transactions'],
    queryFn: async () => (await apiClient.get<Transaction[]>('/transactions/history')).data
  });

  const { data: payoutStatus = [] } = useQuery({
    queryKey: ['merchant', 'payout-status'],
    queryFn: async () => (await apiClient.get<PayoutStatus[]>('/payout/status')).data
  });

  const paymentMutation = useMutation({
    mutationFn: async (payload: { national_id: string; amount: number }) => {
      await apiClient.post('/payment/request', payload);
    },
    onSuccess: () => {
      toast.success('رمز یکبار مصرف برای کارمند ارسال شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'transactions'] });
    }
  });

  const payoutMutation = useMutation({
    mutationFn: async (payload: { amount: number; bank_account: string }) => {
      await apiClient.post('/payout/request', payload);
    },
    onSuccess: () => {
      toast.success('درخواست تسویه ثبت شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'payout-status'] });
    }
  });

  const handlePaymentRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const national_id = String(form.get('national_id') ?? '');
    const amount = Number(form.get('amount') ?? 0);
    setPaymentLoading(true);
    try {
      await paymentMutation.mutateAsync({ national_id, amount });
      event.currentTarget.reset();
    } finally {
      setPaymentLoading(false);
    }
  };

  const handlePayoutRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const amount = Number(form.get('amount') ?? 0);
    const bank_account = String(form.get('bank_account') ?? '');
    setPayoutLoading(true);
    try {
      await payoutMutation.mutateAsync({ amount, bank_account });
      event.currentTarget.reset();
    } finally {
      setPayoutLoading(false);
    }
  };

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>موجودی کیف پول</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{balance?.balance ?? 0}</CardContent>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>درخواست پرداخت از کارمند</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={handlePaymentRequest}>
              <div className="space-y-2">
                <Label htmlFor="national_id">کد ملی کارمند</Label>
                <Input id="national_id" name="national_id" required placeholder="1234567890" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="amount">مبلغ</Label>
                <Input id="amount" name="amount" type="number" required min={0} step={1000} />
              </div>
              <Button type="submit" className="w-full" disabled={paymentLoading}>
                {paymentLoading ? 'در حال ارسال…' : 'ثبت درخواست پرداخت'}
              </Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>درخواست تسویه</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={handlePayoutRequest}>
              <div className="space-y-2">
                <Label htmlFor="payout-amount">مبلغ</Label>
                <Input id="payout-amount" name="amount" type="number" required min={0} step={1000} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="bank_account">شماره حساب/شبا</Label>
                <Input id="bank_account" name="bank_account" required placeholder="IRxxxxxxxxxxxx" />
              </div>
              <Button type="submit" className="w-full" disabled={payoutLoading}>
                {payoutLoading ? 'در حال ارسال…' : 'ثبت درخواست تسویه'}
              </Button>
            </form>
          </CardContent>
        </Card>
      </section>

      <section>
        <h2 className="text-xl font-semibold">آخرین تراکنش‌ها</h2>
        <Table className="mt-4">
          <TableHeader>
            <TableRow>
              <TableHead>شناسه</TableHead>
              <TableHead>نوع</TableHead>
              <TableHead>مبلغ</TableHead>
              <TableHead>تاریخ</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {transactions.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  تراکنشی ثبت نشده است.
                </TableCell>
              </TableRow>
            ) : (
              transactions.map((transaction) => (
                <TableRow key={transaction.id}>
                  <TableCell>{transaction.id}</TableCell>
                  <TableCell>{transaction.type}</TableCell>
                  <TableCell>{transaction.amount}</TableCell>
                  <TableCell>{transaction.created_at}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </section>

      <section>
        <h2 className="text-xl font-semibold">وضعیت تسویه‌ها</h2>
        <Table className="mt-4">
          <TableHeader>
            <TableRow>
              <TableHead>شناسه</TableHead>
              <TableHead>مبلغ</TableHead>
              <TableHead>وضعیت</TableHead>
              <TableHead>تاریخ</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {payoutStatus.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  هنوز درخواستی برای تسویه ثبت نشده است.
                </TableCell>
              </TableRow>
            ) : (
              payoutStatus.map((payout) => (
                <TableRow key={payout.id}>
                  <TableCell>{payout.id}</TableCell>
                  <TableCell>{payout.amount}</TableCell>
                  <TableCell>
                    <PayoutStatusBadge status={payout.status} />
                  </TableCell>
                  <TableCell>{payout.created_at}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </section>
    </DashboardLayout>
  );
};

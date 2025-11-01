import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { OTPModal } from '../../components/common/OTPModal';
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

export const EmployeeDashboard = () => {
  const queryClient = useQueryClient();
  const [confirmOpen, setConfirmOpen] = useState(false);

  const { data: balance } = useQuery({
    queryKey: ['employee', 'balance'],
    queryFn: async () => (await apiClient.get<BalanceResponse>('/wallet/balance')).data
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['employee', 'transactions'],
    queryFn: async () => (await apiClient.get<Transaction[]>('/transactions/history')).data
  });

  const confirmMutation = useMutation({
    mutationFn: async (payload: { request_id: string; otp_code: string }) => {
      await apiClient.post('/payment/confirm', payload);
    },
    onSuccess: () => {
      toast.success('پرداخت با موفقیت تأیید شد.');
      queryClient.invalidateQueries({ queryKey: ['employee', 'balance'] });
      queryClient.invalidateQueries({ queryKey: ['employee', 'transactions'] });
    }
  });

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>موجودی کیف پول</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{balance?.balance ?? 0}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>عملیات</CardTitle>
          </CardHeader>
          <CardContent>
            <Button onClick={() => setConfirmOpen(true)}>تأیید پرداخت</Button>
          </CardContent>
        </Card>
      </section>

      <section>
        <h2 className="text-xl font-semibold">تاریخچه تراکنش‌ها</h2>
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
                  هنوز تراکنشی ثبت نشده است.
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

      <OTPModal
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        onSubmit={async ({ requestId, otp }) => {
          try {
            await confirmMutation.mutateAsync({ request_id: requestId, otp_code: otp });
            setConfirmOpen(false);
          } catch (error) {
            console.error(error);
          }
        }}
        isSubmitting={confirmMutation.isPending}
      />
    </DashboardLayout>
  );
};

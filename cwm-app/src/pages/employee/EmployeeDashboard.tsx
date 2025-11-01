import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { OTPModal } from '../../components/common/OTPModal';
import { apiClient } from '../../api/client';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';
import { formatDateTime } from '../../utils/format';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
  status?: string;
}

interface BalancePayload {
  balance?: number;
  new_balance?: number;
}

interface PendingRequest {
  id: number;
  amount: number;
  merchantName: string;
  storeName: string;
  createdAt?: string;
}

export const EmployeeDashboard = () => {
  const queryClient = useQueryClient();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState<PendingRequest | null>(null);

  const { data: balance } = useQuery({
    queryKey: ['employee', 'balance'],
    queryFn: async () => {
      const response = await apiClient.get('/wallet/balance');
      const data = unwrapWordPressObject<BalancePayload>(response.data);
      if (!data) {
        return { balance: 0 };
      }

      return {
        balance: Number(data.balance ?? data.new_balance ?? 0)
      };
    }
  });

  const { data: transactions = [] } = useQuery({
    queryKey: ['employee', 'transactions'],
    queryFn: async () => {
      const response = await apiClient.get('/transactions/history');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((transaction) => ({
        id: Number(transaction.id ?? 0),
        type: String(transaction.type ?? ''),
        amount: Number(transaction.amount ?? 0),
        created_at: String(transaction.created_at ?? ''),
        description: transaction.description ? String(transaction.description) : undefined,
        status: transaction.status ? String(transaction.status) : undefined
      }));
    }
  });

  const { data: pendingRequests = [] } = useQuery({
    queryKey: ['employee', 'pendingRequests'],
    queryFn: async () => {
      const response = await apiClient.get('/payment/pending');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((request) => ({
        id: Number(request.id ?? 0),
        amount: Number(request.amount ?? 0),
        merchantName: request.merchant_name ? String(request.merchant_name) : '',
        storeName: request.store_name ? String(request.store_name) : '',
        createdAt: request.created_at ? String(request.created_at) : undefined
      }));
    }
  });

  const latestRequest = pendingRequests[0];

  const confirmMutation = useMutation({
    mutationFn: async (payload: { request_id: number; otp_code: string }) => {
      await apiClient.post('/payment/confirm', payload);
    },
    onSuccess: () => {
      toast.success('پرداخت با موفقیت تأیید شد.');
      queryClient.invalidateQueries({ queryKey: ['employee', 'balance'] });
      queryClient.invalidateQueries({ queryKey: ['employee', 'transactions'] });
      queryClient.invalidateQueries({ queryKey: ['employee', 'pendingRequests'] });
      setConfirmOpen(false);
      setSelectedRequest(null);
    },
    onError: () => {
      toast.error('تأیید پرداخت با خطا مواجه شد. لطفاً دوباره تلاش کنید.');
    }
  });

  const handleConfirmClick = (request: PendingRequest) => {
    setSelectedRequest(request);
    setConfirmOpen(true);
  };

  return (
    <DashboardLayout>
      {latestRequest && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm">
          <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="font-semibold">آخرین درخواست پرداخت در انتظار تأیید</p>
              <p className="text-amber-800">
                {latestRequest.storeName || latestRequest.merchantName} مبلغ{' '}
                <span className="font-medium">{latestRequest.amount}</span>
                {latestRequest.createdAt && (
                  <span className="ml-2 text-xs text-amber-700">
                    {formatDateTime(latestRequest.createdAt)}
                  </span>
                )}
              </p>
            </div>
            <Button size="sm" onClick={() => handleConfirmClick(latestRequest)}>
              تأیید همین پرداخت
            </Button>
          </div>
        </div>
      )}

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>موجودی کیف پول</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{balance?.balance ?? 0}</CardContent>
        </Card>
      </section>

      <section className="mt-6">
        <Card>
          <CardHeader>
            <CardTitle>درخواست‌های پرداخت در انتظار تأیید</CardTitle>
          </CardHeader>
          <CardContent>
            {pendingRequests.length === 0 ? (
              <p className="text-sm text-muted-foreground">در حال حاضر درخواستی برای تأیید وجود ندارد.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>فروشگاه</TableHead>
                    <TableHead>مبلغ</TableHead>
                    <TableHead>تاریخ</TableHead>
                    <TableHead className="text-right">عملیات</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {pendingRequests.map((request) => (
                    <TableRow key={request.id}>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="font-medium">{request.storeName || 'بدون نام'}</span>
                          {request.merchantName && (
                            <span className="text-xs text-muted-foreground">{request.merchantName}</span>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>{request.amount}</TableCell>
                      <TableCell>
                        {request.createdAt ? formatDateTime(request.createdAt) : '—'}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button size="sm" onClick={() => handleConfirmClick(request)}>
                          تأیید
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
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
        onOpenChange={(open) => {
          setConfirmOpen(open);
          if (!open) {
            setSelectedRequest(null);
          }
        }}
        request={selectedRequest ? {
          id: selectedRequest.id,
          amount: selectedRequest.amount,
          storeName: selectedRequest.storeName || selectedRequest.merchantName,
          merchantName: selectedRequest.merchantName
        } : undefined}
        onSubmit={async ({ otp }) => {
          if (!selectedRequest) {
            return;
          }

          try {
            await confirmMutation.mutateAsync({ request_id: selectedRequest.id, otp_code: otp });
          } catch (error) {
            console.error(error);
          }
        }}
        isSubmitting={confirmMutation.isPending}
      />
    </DashboardLayout>
  );
};

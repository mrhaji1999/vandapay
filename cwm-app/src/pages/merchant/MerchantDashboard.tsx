import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Button } from '../../components/ui/button';
import { Select } from '../../components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { PayoutStatusBadge } from '../../components/common/PayoutStatusBadge';
import { OTPModal } from '../../components/common/OTPModal';
import { apiClient } from '../../api/client';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';

interface Transaction {
  id: number;
  type: string;
  amount: number;
  created_at: string;
  description?: string;
  status?: string;
}

interface PayoutStatus {
  id: number;
  amount: number;
  status: string;
  created_at: string;
}

interface BalancePayload {
  balance?: number;
  new_balance?: number;
}

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface MerchantCategories {
  assigned: Category[];
  available: Category[];
}

interface PreviewPayload {
  employee_national_id: string;
  category_id: number;
}

interface PreviewResponse {
  employee_id: number;
  employee_name: string;
  category_id: number;
  limit_defined: boolean;
  limit: number;
  spent: number;
  remaining: number;
  wallet_balance: number;
  available_amount: number;
}

interface PaymentPayload {
  employee_national_id: string;
  category_id: number;
  amount: number;
}

export const MerchantDashboard = () => {
  const queryClient = useQueryClient();
  const [nationalId, setNationalId] = useState('');
  const [amountValue, setAmountValue] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [previewInfo, setPreviewInfo] = useState<PreviewResponse | null>(null);
  const [activeRequest, setActiveRequest] = useState<{ id: number; amount: number; employeeName?: string } | null>(null);
  const [otpOpen, setOtpOpen] = useState(false);

  useEffect(() => {
    setPreviewInfo(null);
  }, [nationalId, selectedCategory]);

  const { data: balance } = useQuery({
    queryKey: ['merchant', 'balance'],
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
    queryKey: ['merchant', 'transactions'],
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

  const { data: payoutStatus = [] } = useQuery({
    queryKey: ['merchant', 'payout-status'],
    queryFn: async () => {
      const response = await apiClient.get('/payout/status');
      return unwrapWordPressList<Record<string, unknown>>(response.data).map((payout) => ({
        id: Number(payout.id ?? 0),
        amount: Number(payout.amount ?? 0),
        status: String(payout.status ?? ''),
        created_at: String(payout.created_at ?? '')
      }));
    }
  });

  const { data: categoriesData } = useQuery({
    queryKey: ['merchant', 'categories'],
    queryFn: async () => {
      const response = await apiClient.get('/merchant/categories');
      const data = unwrapWordPressObject<MerchantCategories>(response.data);
      return {
        assigned: (data?.assigned ?? []).map((item) => ({
          id: Number(item.id ?? 0),
          name: String(item.name ?? ''),
          slug: String(item.slug ?? '')
        })),
        available: (data?.available ?? []).map((item) => ({
          id: Number(item.id ?? 0),
          name: String(item.name ?? ''),
          slug: String(item.slug ?? '')
        }))
      } satisfies MerchantCategories;
    }
  });

  const assignedCategories = categoriesData?.assigned ?? [];

  const previewMutation = useMutation({
    mutationFn: async (payload: PreviewPayload) => {
      const response = await apiClient.post('/payment/preview', payload);
      const data = unwrapWordPressObject<PreviewResponse>(response.data);
      if (!data) {
        throw new Error('PREVIEW_FAILED');
      }
      return data;
    },
    onSuccess: (data) => {
      setPreviewInfo(data);
      toast.success('سقف قابل استفاده به‌روزرسانی شد.');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'استعلام امکان‌پذیر نبود.';
      toast.error(message);
    }
  });

  const paymentMutation = useMutation({
    mutationFn: async (payload: PaymentPayload) => {
      const response = await apiClient.post('/payment/request', payload);
      return unwrapWordPressObject<{ request_id: number; remaining?: number; wallet_balance?: number }>(response.data);
    },
    onSuccess: (data, variables) => {
      toast.success('کد تأیید برای کارمند ارسال شد.');
      setActiveRequest({
        id: Number(data?.request_id ?? 0),
        amount: variables.amount,
        employeeName: previewInfo?.employee_name ?? undefined
      });
      setOtpOpen(true);
      queryClient.invalidateQueries({ queryKey: ['merchant', 'transactions'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ثبت درخواست پرداخت با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const confirmMutation = useMutation({
    mutationFn: async (payload: { request_id: number; otp_code: string }) => {
      await apiClient.post('/payment/confirm', payload);
    },
    onSuccess: () => {
      toast.success('پرداخت با موفقیت انجام شد.');
      setOtpOpen(false);
      setActiveRequest(null);
      setPreviewInfo(null);
      setAmountValue('');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'transactions'] });
      queryClient.invalidateQueries({ queryKey: ['merchant', 'balance'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'تأیید پرداخت با خطا روبه‌رو شد.';
      toast.error(message);
    }
  });

  const payoutMutation = useMutation({
    mutationFn: async (payload: { amount: number; bank_account: string }) => {
      await apiClient.post('/payout/request', payload);
    },
    onSuccess: () => {
      toast.success('درخواست تسویه ثبت شد.');
      queryClient.invalidateQueries({ queryKey: ['merchant', 'payout-status'] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'ثبت درخواست تسویه با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const handlePreview = () => {
    if (!nationalId || !selectedCategory) {
      toast.error('کد ملی و دسته‌بندی را مشخص کنید.');
      return;
    }

    previewMutation.mutate({
      employee_national_id: nationalId,
      category_id: Number(selectedCategory)
    });
  };

  const handlePaymentRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!selectedCategory) {
      toast.error('لطفاً دسته‌بندی پذیرنده را انتخاب کنید.');
      return;
    }

    const amount = Number(amountValue);
    if (!nationalId || amount <= 0) {
      toast.error('اطلاعات کارمند و مبلغ باید وارد شود.');
      return;
    }

    await paymentMutation.mutateAsync({
      employee_national_id: nationalId,
      category_id: Number(selectedCategory),
      amount
    });
  };

  const handlePayoutRequest = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const amount = Number(form.get('amount') ?? 0);
    const bank_account = String(form.get('bank_account') ?? '');
    await payoutMutation.mutateAsync({ amount, bank_account });
    event.currentTarget.reset();
  };

  const assignedCategoryOptions = useMemo(() => {
    if (assignedCategories.length === 0) {
      return [<option key="none" value="">هیچ دسته‌بندی فعالی ثبت نشده است</option>];
    }

    return [
      <option key="placeholder" value="">
        انتخاب دسته‌بندی
      </option>,
      ...assignedCategories.map((category) => (
        <option key={category.id} value={category.id}>
          {category.name}
        </option>
      ))
    ];
  }, [assignedCategories]);

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
            <CardTitle>دسته‌بندی‌های فعال</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-muted-foreground">
            {assignedCategories.length === 0 ? (
              <p>دسته‌بندی فعالی برای این پذیرنده تعریف نشده است.</p>
            ) : (
              <ul className="list-inside list-disc space-y-1">
                {assignedCategories.map((category) => (
                  <li key={category.id}>{category.name}</li>
                ))}
              </ul>
            )}
          </CardContent>
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
                <Input
                  id="national_id"
                  name="national_id"
                  required
                  placeholder="1234567890"
                  value={nationalId}
                  onChange={(event) => setNationalId(event.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="category_id">دسته‌بندی پذیرنده</Label>
                <Select
                  id="category_id"
                  name="category_id"
                  value={selectedCategory}
                  onChange={(event) => setSelectedCategory(event.target.value)}
                >
                  {assignedCategoryOptions}
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="amount">مبلغ</Label>
                <Input
                  id="amount"
                  name="amount"
                  type="number"
                  required
                  min={0}
                  step={1000}
                  value={amountValue}
                  onChange={(event) => setAmountValue(event.target.value)}
                />
              </div>
              <div className="flex flex-col gap-2 sm:flex-row">
                <Button
                  type="button"
                  variant="outline"
                  className="w-full sm:w-auto"
                  onClick={handlePreview}
                  disabled={previewMutation.isPending}
                >
                  {previewMutation.isPending ? 'در حال استعلام…' : 'استعلام موجودی'}
                </Button>
                <Button
                  type="submit"
                  className="w-full sm:flex-1"
                  disabled={paymentMutation.isPending}
                >
                  {paymentMutation.isPending ? 'در حال ارسال…' : 'ثبت درخواست پرداخت'}
                </Button>
              </div>
            </form>

            {previewInfo && (
              <div className="mt-4 space-y-1 rounded-md border border-muted-foreground/30 bg-muted/40 p-3 text-xs sm:text-sm">
                <p>
                  <span className="font-medium">کارمند:</span> {previewInfo.employee_name || 'نامشخص'}
                </p>
                <p>
                  <span className="font-medium">سقف این دسته‌بندی:</span> {previewInfo.limit}
                </p>
                <p>
                  <span className="font-medium">مصرف شده:</span> {previewInfo.spent}
                </p>
                <p>
                  <span className="font-medium">باقی‌مانده دسته‌بندی:</span> {previewInfo.remaining}
                </p>
                <p>
                  <span className="font-medium">موجودی کیف پول:</span> {previewInfo.wallet_balance}
                </p>
                <p className="font-medium text-emerald-700">
                  مبلغ قابل استفاده:
                  <span className="mr-2">{previewInfo.available_amount}</span>
                </p>
              </div>
            )}
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
              <Button type="submit" className="w-full" disabled={payoutMutation.isPending}>
                {payoutMutation.isPending ? 'در حال ارسال…' : 'ثبت درخواست تسویه'}
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

      <OTPModal
        open={otpOpen}
        onOpenChange={setOtpOpen}
        request={
          activeRequest
            ? {
                id: activeRequest.id,
                amount: activeRequest.amount,
                storeName: activeRequest.employeeName
              }
            : undefined
        }
        isSubmitting={confirmMutation.isPending}
        onSubmit={({ otp }) => {
          if (!activeRequest) return;
          confirmMutation.mutate({ request_id: activeRequest.id, otp_code: otp });
        }}
      />
    </DashboardLayout>
  );
};

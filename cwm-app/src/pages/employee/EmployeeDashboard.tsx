import { useQuery } from '@tanstack/react-query';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
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

interface CategoryBalance {
  category_id: number;
  category_name: string;
  limit: number;
  spent: number;
  remaining: number;
}

interface CategoryBalanceResponse {
  wallet_balance: number;
  categories: CategoryBalance[];
}

export const EmployeeDashboard = () => {

  const { data: categoryBalances } = useQuery({
    queryKey: ['employee', 'category-balances'],
    queryFn: async () => {
      const response = await apiClient.get('/employee/category-balances');
      const data = unwrapWordPressObject<CategoryBalanceResponse>(response.data);
      return {
        walletBalance: Number(data?.wallet_balance ?? 0),
        categories: (data?.categories ?? []).map((category) => ({
          category_id: Number(category.category_id ?? 0),
          category_name: String(category.category_name ?? ''),
          limit: Number(category.limit ?? 0),
          spent: Number(category.spent ?? 0),
          remaining: Number(category.remaining ?? 0)
        }))
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

  return (
    <DashboardLayout>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>موجودی کیف پول</CardTitle>
          </CardHeader>
          <CardContent className="text-3xl font-semibold">{categoryBalances?.walletBalance ?? 0}</CardContent>
        </Card>
      </section>

      <section className="mt-6 space-y-4">
        <h2 className="text-xl font-semibold">سقف استفاده از دسته‌بندی‌ها</h2>
        {categoryBalances && categoryBalances.categories.length > 0 ? (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {categoryBalances.categories.map((category) => (
              <Card key={category.category_id}>
                <CardHeader>
                  <CardTitle className="text-base font-medium">{category.category_name}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-1 text-sm">
                  <p>
                    سقف تعریف‌شده: <span className="font-semibold">{category.limit}</span>
                  </p>
                  <p>
                    مصرف‌شده: <span className="font-semibold text-rose-600">{category.spent}</span>
                  </p>
                  <p>
                    باقی‌مانده: <span className="font-semibold text-emerald-700">{category.remaining}</span>
                  </p>
                </CardContent>
              </Card>
            ))}
          </div>
        ) : (
          <Card>
            <CardContent className="py-6 text-sm text-muted-foreground">
              هنوز سقفی برای استفاده از دسته‌بندی‌ها تعیین نشده است.
            </CardContent>
          </Card>
        )}
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
                  تراکنشی برای نمایش وجود ندارد.
                </TableCell>
              </TableRow>
            ) : (
              transactions.map((transaction) => (
                <TableRow key={transaction.id}>
                  <TableCell>{transaction.id}</TableCell>
                  <TableCell>{transaction.type}</TableCell>
                  <TableCell>{transaction.amount}</TableCell>
                  <TableCell>{formatDateTime(transaction.created_at)}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </section>
    </DashboardLayout>
  );
};

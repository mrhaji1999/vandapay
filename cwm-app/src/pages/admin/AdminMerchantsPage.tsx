import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { DashboardLayout } from '../../components/layout/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import { Button } from '../../components/ui/button';
import { apiClient } from '../../api/client';
import { unwrapWordPressList, unwrapWordPressObject } from '../../api/wordpress';

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface MerchantRow {
  id: number;
  name: string;
  email?: string;
  balance: number;
  store_name?: string;
  pending_payouts: number;
  categories: Category[];
  category_ids: number[];
}

interface MerchantCategoriesResponse {
  assigned: Category[];
  available: Category[];
}

export const AdminMerchantsPage = () => {
  const queryClient = useQueryClient();
  const [selectedMerchantId, setSelectedMerchantId] = useState<number | null>(null);
  const [selectedCategoryIds, setSelectedCategoryIds] = useState<number[]>([]);

  const { data: merchants = [] } = useQuery<MerchantRow[]>({
    queryKey: ['admin', 'merchants'],
    queryFn: async () => {
      const response = await apiClient.get('/admin/merchants');
      const merchantList = unwrapWordPressList<Record<string, unknown>>(response.data) ?? [];
      return merchantList.map((merchant) => ({
        id: Number(merchant.id ?? 0),
        name: String(merchant.name ?? ''),
        email: merchant.email ? String(merchant.email) : undefined,
        balance: Number(merchant.balance ?? merchant.wallet_balance ?? 0),
        store_name: merchant.store_name ? String(merchant.store_name) : undefined,
        pending_payouts: Number(merchant.pending_payouts ?? 0),
        categories: Array.isArray(merchant.categories)
          ? (merchant.categories as Record<string, unknown>[])
              .filter((category) => category && typeof category === 'object')
              .map((category) => ({
                id: Number(category.id ?? 0),
                name: String(category.name ?? ''),
                slug: String(category.slug ?? '')
              }))
          : [],
        category_ids: Array.isArray(merchant.category_ids)
          ? (merchant.category_ids as (string | number)[])
              .map((value) => Number(value))
              .filter((value) => !Number.isNaN(value))
          : []
      }));
    }
  });

  const selectedMerchant = useMemo(
    () => merchants.find((merchant) => merchant.id === selectedMerchantId) ?? null,
    [merchants, selectedMerchantId]
  );

  const { data: selectedMerchantCategories } = useQuery<MerchantCategoriesResponse | null>({
    queryKey: ['admin', 'merchant-categories', selectedMerchantId],
    queryFn: async () => {
      if (!selectedMerchantId) return null;
      const response = await apiClient.get(`/admin/merchants/${selectedMerchantId}/categories`);
      const payload = unwrapWordPressObject<MerchantCategoriesResponse>(response.data) ?? {
        assigned: [],
        available: []
      };
      return {
        assigned: (payload.assigned ?? [])
          .filter((category) => category && typeof category === 'object')
          .map((category) => ({
            id: Number(category.id ?? 0),
            name: String(category.name ?? ''),
            slug: String(category.slug ?? '')
          })),
        available: (payload.available ?? [])
          .filter((category) => category && typeof category === 'object')
          .map((category) => ({
            id: Number(category.id ?? 0),
            name: String(category.name ?? ''),
            slug: String(category.slug ?? '')
          }))
      };
    },
    enabled: typeof selectedMerchantId === 'number'
  });

  const availableCategories = selectedMerchantCategories?.available ?? [];

  const assignedCategoryIds = useMemo(
    () => new Set((selectedMerchantCategories?.assigned ?? []).map((category) => category.id)),
    [selectedMerchantCategories]
  );

  useEffect(() => {
    if (selectedMerchantCategories) {
      setSelectedCategoryIds((selectedMerchantCategories.assigned ?? []).map((category) => category.id));
      return;
    }

    setSelectedCategoryIds([]);
  }, [selectedMerchantCategories, selectedMerchantId]);

  const toggleCategory = (categoryId: number) => {
    setSelectedCategoryIds((prev) => {
      if (prev.includes(categoryId)) {
        return prev.filter((id) => id !== categoryId);
      }
      return [...prev, categoryId];
    });
  };

  const updateMutation = useMutation({
    mutationFn: async (payload: { merchantId: number; categoryIds: number[] }) => {
      await apiClient.post(`/admin/merchants/${payload.merchantId}/categories`, {
        category_ids: payload.categoryIds
      });
    },
    onSuccess: (_, variables) => {
      toast.success('دسته‌بندی‌های پذیرنده با موفقیت به‌روزرسانی شد.');
      queryClient.invalidateQueries({ queryKey: ['admin', 'merchants'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'merchant-categories', variables.merchantId] });
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message ?? 'به‌روزرسانی دسته‌بندی‌ها با خطا مواجه شد.';
      toast.error(message);
    }
  });

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!selectedMerchantId) return;
    updateMutation.mutate({ merchantId: selectedMerchantId, categoryIds: selectedCategoryIds });
  };

  return (
    <DashboardLayout>
      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>مدیریت پذیرندگان</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="text-right">نام فروشگاه</TableHead>
                  <TableHead className="text-right">ایمیل</TableHead>
                  <TableHead className="text-right">دسته‌بندی‌ها</TableHead>
                  <TableHead className="text-right">اقدامات</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {merchants.map((merchant) => {
                  const categoryNames = merchant.categories.map((category) => category.name).join('، ');
                  return (
                    <TableRow key={merchant.id}>
                      <TableCell className="text-right font-medium">
                        {merchant.store_name || merchant.name || '—'}
                      </TableCell>
                      <TableCell className="text-right">{merchant.email || '—'}</TableCell>
                      <TableCell className="text-right">
                        {categoryNames.length > 0 ? categoryNames : 'بدون دسته‌بندی'}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          variant={selectedMerchantId === merchant.id ? 'default' : 'outline'}
                          onClick={() => setSelectedMerchantId(merchant.id)}
                        >
                          مدیریت دسته‌بندی
                        </Button>
                      </TableCell>
                    </TableRow>
                  );
                })}
                {merchants.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={4} className="py-6 text-center text-muted-foreground">
                      هیچ پذیرنده‌ای یافت نشد.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>انتخاب دسته‌بندی‌های پذیرنده</CardTitle>
          </CardHeader>
          <CardContent>
            {selectedMerchant ? (
              <form className="space-y-4" onSubmit={handleSubmit}>
                <div className="space-y-2">
                  <p className="text-sm text-muted-foreground">
                    دسته‌بندی‌های فعال برای «{selectedMerchant.store_name || selectedMerchant.name}» را انتخاب کنید.
                  </p>
                  <div className="max-h-64 space-y-2 overflow-y-auto rounded-md border p-3">
                    {availableCategories.length > 0 ? (
                      availableCategories.map((category) => (
                        <label key={category.id} className="flex items-center justify-between gap-3 text-sm">
                          <span>{category.name}</span>
                          <input
                            type="checkbox"
                            className="h-4 w-4"
                            checked={selectedCategoryIds.includes(category.id)}
                            onChange={() => toggleCategory(category.id)}
                          />
                        </label>
                      ))
                    ) : (
                      <p className="text-sm text-muted-foreground">هیچ دسته‌بندی فعالی ثبت نشده است.</p>
                    )}
                  </div>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setSelectedCategoryIds(Array.from(assignedCategoryIds))}
                    disabled={updateMutation.isPending}
                  >
                    بازگردانی مقادیر قبلی
                  </Button>
                  <Button type="submit" disabled={updateMutation.isPending}>
                    {updateMutation.isPending ? 'در حال ذخیره…' : 'ذخیره دسته‌بندی‌ها'}
                  </Button>
                </div>
              </form>
            ) : (
              <p className="text-sm text-muted-foreground">برای مدیریت، یک پذیرنده را از لیست انتخاب کنید.</p>
            )}
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  );
};

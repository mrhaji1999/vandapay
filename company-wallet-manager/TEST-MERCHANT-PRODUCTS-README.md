# راهنمای تست لیست محصولات پذیرنده

این فایل تست برای بررسی عملکرد endpoint `/merchant/products` استفاده می‌شود.

## نصب

1. فایل `test-merchant-products.php` را به ریشه WordPress کپی کنید (همان سطحی که `wp-load.php` قرار دارد)
2. اطمینان حاصل کنید که فایل قابل دسترسی است

## استفاده

### روش 1: از طریق مرورگر

```
http://yoursite.com/test-merchant-products.php?merchant_id=3
```

یا با استفاده از username و password:

```
http://yoursite.com/test-merchant-products.php?username=merchant_username&password=merchant_password
```

### روش 2: از طریق خط فرمان (CLI)

```bash
php test-merchant-products.php --merchant_id=3
```

یا:

```bash
php test-merchant-products.php --username=merchant_username --password=merchant_password
```

## خروجی تست

تست موارد زیر را بررسی می‌کند:

1. ✅ پیدا کردن کاربر پذیرنده
2. ✅ بررسی نقش کاربر (merchant)
3. ✅ ایجاد توکن JWT
4. ✅ تنظیم کاربر فعلی
5. ✅ بررسی مستقیم دیتابیس (تعداد محصولات)
6. ✅ تست API endpoint
7. ✅ مقایسه نتایج دیتابیس و API

## نکات امنیتی

⚠️ **مهم**: این فایل تست را بعد از استفاده حذف کنید تا از امنیت سایت محافظت شود.

## مثال خروجی

```
=== تست دریافت لیست محصولات پذیرنده ===

✓ کاربر پیدا شد: merchant_user (ID: 3)
✓ توکن JWT ایجاد شد
  توکن: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
✓ کاربر فعلی تنظیم شد: 3

=== بررسی مستقیم دیتابیس ===
✓ تعداد محصولات در دیتابیس برای merchant_id 3: 4

محصولات در دیتابیس:
  - ID: 1, نام: موبایل سامسونگ a7, قیمت: 10000, وضعیت: active
  - ID: 2, نام: موبایل سامسونگ a7, قیمت: 3000, وضعیت: active
  ...

=== تست API Endpoint ===
✓ درخواست API با موفقیت انجام شد (کد وضعیت: 200)
✓ پاسخ API دریافت شد
  وضعیت: success
  تعداد محصولات: 4

=== مقایسه نتایج ===
✓ تعداد محصولات در دیتابیس و API یکسان است: 4
```

## عیب‌یابی

اگر تست با خطا مواجه شد:

1. بررسی کنید که `JWT_AUTH_SECRET_KEY` در `wp-config.php` تعریف شده باشد
2. بررسی کنید که کاربر دارای نقش `merchant` باشد
3. بررسی کنید که جدول `wp_cwm_products` وجود داشته باشد
4. لاگ‌های WordPress را بررسی کنید


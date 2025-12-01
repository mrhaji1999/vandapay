# دستورالعمل Build و Deploy پروژه VandaPay Panel

## اطلاعات پروژه

- **URL پنل UI**: https://panel.vandapay.com/
- **URL WordPress API**: https://mr.vandapay.com/wp-json/cwm/v1

## مراحل Build

### 1. نصب Dependencies

```bash
cd ui
npm install
```

### 2. بررسی فایل‌های Environment

فایل `.env.production` برای build production استفاده می‌شود:

```
VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
```

این فایل قبلاً ایجاد شده و آماده استفاده است.

### 3. Build پروژه

```bash
npm run build
```

این دستور فایل‌های build شده را در پوشه `dist` ایجاد می‌کند.

### 4. محتویات پوشه dist

بعد از build، پوشه `dist` شامل موارد زیر است:

```
dist/
├── index.html
├── .htaccess (برای Apache)
├── assets/
│   ├── index-[hash].js
│   ├── index-[hash].css
│   ├── react-vendor-[hash].js
│   └── chart-vendor-[hash].js
└── vite.svg
```

## مراحل Deploy در دایرکت ادمین

### روش 1: استفاده از File Manager

1. وارد دایرکت ادمین شوید
2. به مسیر `public_html/panel` بروید (یا مسیر مورد نظر شما)
3. **تمام محتویات** پوشه `dist` را انتخاب و آپلود کنید
4. مطمئن شوید که:
   - فایل `index.html` در root directory قرار دارد
   - فایل `.htaccess` آپلود شده است
   - پوشه `assets` با تمام فایل‌هایش آپلود شده است

### روش 2: استفاده از FTP

1. از یک FTP client (مثل FileZilla) استفاده کنید
2. به سرور متصل شوید
3. به مسیر `public_html/panel` بروید
4. تمام محتویات پوشه `dist` را آپلود کنید

### روش 3: استفاده از SSH (اگر دسترسی دارید)

```bash
# فشرده‌سازی فایل‌ها
cd ui
tar -czf dist.tar.gz dist/

# آپلود به سرور (با SCP)
scp dist.tar.gz user@server:/path/to/public_html/panel/

# در سرور:
cd /path/to/public_html/panel/
tar -xzf dist.tar.gz
mv dist/* .
rm -rf dist dist.tar.gz
```

## بررسی بعد از Deploy

1. به آدرس https://panel.vandapay.com/ بروید
2. مطمئن شوید که:
   - صفحه لاگین نمایش داده می‌شود
   - Console مرورگر خطایی نشان نمی‌دهد
   - API calls به https://mr.vandapay.com/wp-json/cwm/v1/ درست کار می‌کند

## تنظیمات مهم

### CORS در WordPress

مطمئن شوید که CORS در WordPress برای دامنه `panel.vandapay.com` فعال است.

### .htaccess

فایل `.htaccess` در پوشه `dist` قرار دارد و شامل:
- تنظیمات React Router برای SPA
- CORS headers
- Cache headers برای static assets
- Gzip compression

## Troubleshooting

### مشکل: صفحه سفید نمایش داده می‌شود

1. Console مرورگر را باز کنید (F12)
2. بررسی کنید که فایل‌های JavaScript و CSS به درستی لود می‌شوند
3. بررسی کنید که base path درست است
4. مطمئن شوید که فایل `.htaccess` آپلود شده است

### مشکل: API calls کار نمی‌کند

1. Network tab در Developer Tools را بررسی کنید
2. مطمئن شوید که CORS در WordPress فعال است
3. بررسی کنید که URL API درست است: `https://mr.vandapay.com/wp-json/cwm/v1`
4. بررسی کنید که token در localStorage ذخیره می‌شود

### مشکل: Routing کار نمی‌کند

1. مطمئن شوید که فایل `.htaccess` آپلود شده است
2. بررسی کنید که mod_rewrite در Apache فعال است
3. بررسی کنید که `.htaccess` در root directory قرار دارد

### مشکل: فایل‌های static لود نمی‌شوند

1. بررسی کنید که پوشه `assets` به درستی آپلود شده است
2. بررسی کنید که path فایل‌ها درست است
3. بررسی کنید که permissions فایل‌ها درست است (644 برای فایل‌ها، 755 برای پوشه‌ها)

## به‌روزرسانی پروژه

برای به‌روزرسانی:

1. تغییرات را در کد اعمال کنید
2. دوباره build کنید: `npm run build`
3. فایل‌های جدید را در پوشه `dist` آپلود کنید
4. Cache مرورگر را پاک کنید (Ctrl+Shift+R)

## نکات مهم

- همیشه قبل از deploy، پروژه را در محیط local تست کنید
- از backup گرفتن قبل از deploy غافل نشوید
- فایل‌های `.env` را هرگز در repository commit نکنید
- فایل‌های build شده (dist) را در repository commit نکنید


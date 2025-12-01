# راهنمای نهایی Deploy پروژه VandaPay

## اطلاعات پروژه

- **URL پنل UI**: https://panel.vandapay.com/
- **URL WordPress API**: https://mr.vandapay.com/wp-json/cwm/v1

## مراحل آماده‌سازی

### 1. ایجاد فایل‌های Environment

در پوشه `ui` فایل‌های زیر را ایجاد کنید:

**`.env.production`**:
```
VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
```

**`.env.development`** (برای development):
```
VITE_API_BASE_URL=http://localhost/wp-json/cwm/v1
```

**نکته**: برای ایجاد فایل در Windows PowerShell:
```powershell
cd ui
"VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1" | Out-File -FilePath .env.production -Encoding utf8
```

### 2. Build پروژه

```bash
cd ui
npm install
npm run build
```

فایل‌های build شده در پوشه `dist` قرار می‌گیرند.

## محتویات پوشه dist

بعد از build، پوشه `dist` شامل موارد زیر است:

```
dist/
├── index.html
├── .htaccess
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
3. **تمام محتویات** پوشه `dist` را انتخاب و آپلود کنید:
   - `index.html`
   - `.htaccess` (مهم!)
   - پوشه `assets/` با تمام فایل‌هایش
4. مطمئن شوید که:
   - فایل `index.html` در root directory قرار دارد
   - فایل `.htaccess` آپلود شده است
   - پوشه `assets` با تمام فایل‌هایش آپلود شده است

### روش 2: استفاده از FTP

1. از یک FTP client (مثل FileZilla) استفاده کنید
2. به سرور متصل شوید
3. به مسیر `public_html/panel` بروید
4. تمام محتویات پوشه `dist` را آپلود کنید

### روش 3: استفاده از SSH

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

### .htaccess

فایل `.htaccess` در پوشه `dist` قرار دارد و شامل:
- تنظیمات React Router برای SPA
- CORS headers
- Cache headers برای static assets
- Gzip compression

**مهم**: حتماً فایل `.htaccess` را آپلود کنید، در غیر این صورت routing کار نمی‌کند.

### CORS در WordPress

مطمئن شوید که CORS در WordPress برای دامنه `panel.vandapay.com` فعال است.

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

## به‌روزرسانی پروژه

برای به‌روزرسانی:

1. تغییرات را در کد اعمال کنید
2. فایل `.env.production` را بررسی کنید
3. دوباره build کنید: `npm run build`
4. فایل‌های جدید را در پوشه `dist` آپلود کنید
5. Cache مرورگر را پاک کنید (Ctrl+Shift+R)


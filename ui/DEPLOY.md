# راهنمای Deploy پروژه UI

## پیش‌نیازها

1. Node.js نسخه 18 یا بالاتر
2. دسترسی به دایرکت ادمین برای آپلود فایل‌ها

## مراحل Build و Deploy

### 1. نصب Dependencies

```bash
cd ui
npm install
```

### 2. Build پروژه برای Production

```bash
npm run build
```

این دستور فایل‌های build شده را در پوشه `dist` ایجاد می‌کند.

### 3. آپلود به دایرکت ادمین

#### روش 1: استفاده از File Manager

1. وارد دایرکت ادمین شوید
2. به مسیر `public_html/panel` بروید (یا مسیر مورد نظر شما)
3. تمام محتویات پوشه `dist` را آپلود کنید
4. مطمئن شوید که فایل `index.html` در root directory قرار دارد

#### روش 2: استفاده از FTP

```bash
# از یک FTP client استفاده کنید و محتویات dist را به public_html/panel آپلود کنید
```

### 4. تنظیمات .htaccess (اختیاری)

اگر از Apache استفاده می‌کنید، یک فایل `.htaccess` در root directory ایجاد کنید:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

این تنظیمات برای React Router لازم است تا routing درست کار کند.

## تنظیمات Environment Variables

فایل `.env.production` برای build production استفاده می‌شود:

```
VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
```

اگر نیاز به تغییر URL API دارید، این فایل را ویرایش کنید و دوباره build کنید.

## ساختار فایل‌های Build شده

```
dist/
├── index.html
├── assets/
│   ├── index-[hash].js
│   ├── index-[hash].css
│   └── ...
└── vite.svg (اگر وجود داشته باشد)
```

## بررسی بعد از Deploy

1. به آدرس https://panel.vandapay.com/ بروید
2. مطمئن شوید که صفحه لاگین نمایش داده می‌شود
3. لاگین کنید و مطمئن شوید که API calls به https://mr.vandapay.com/wp-json/cwm/v1/ درست کار می‌کند

## Troubleshooting

### مشکل: صفحه سفید نمایش داده می‌شود

- Console مرورگر را بررسی کنید
- مطمئن شوید که فایل‌های JavaScript و CSS به درستی لود می‌شوند
- بررسی کنید که base path در vite.config.js درست تنظیم شده باشد

### مشکل: API calls کار نمی‌کند

- مطمئن شوید که CORS در WordPress فعال است
- بررسی کنید که URL API در .env.production درست است
- Network tab در Developer Tools را بررسی کنید

### مشکل: Routing کار نمی‌کند

- مطمئن شوید که فایل .htaccess درست تنظیم شده است
- بررسی کنید که mod_rewrite در Apache فعال است


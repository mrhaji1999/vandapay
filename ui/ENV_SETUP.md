# راهنمای تنظیم فایل‌های Environment

## ایجاد فایل‌های .env

قبل از build پروژه، باید فایل‌های environment را ایجاد کنید:

### 1. فایل .env.production (برای build production)

در پوشه `ui` یک فایل با نام `.env.production` ایجاد کنید و محتوای زیر را در آن قرار دهید:

```
VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
```

### 2. فایل .env.development (برای development)

در پوشه `ui` یک فایل با نام `.env.development` ایجاد کنید و محتوای زیر را در آن قرار دهید:

```
VITE_API_BASE_URL=http://localhost/wp-json/cwm/v1
```

### 3. فایل .env.example (برای reference)

در پوشه `ui` یک فایل با نام `.env.example` ایجاد کنید و محتوای زیر را در آن قرار دهید:

```
VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
```

## دستورات برای ایجاد فایل‌ها

### در Windows (PowerShell):

```powershell
cd ui
"VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1" | Out-File -FilePath .env.production -Encoding utf8
"VITE_API_BASE_URL=http://localhost/wp-json/cwm/v1" | Out-File -FilePath .env.development -Encoding utf8
"VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1" | Out-File -FilePath .env.example -Encoding utf8
```

### در Linux/Mac:

```bash
cd ui
echo "VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1" > .env.production
echo "VITE_API_BASE_URL=http://localhost/wp-json/cwm/v1" > .env.development
echo "VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1" > .env.example
```

## Build پروژه

بعد از ایجاد فایل‌های .env، پروژه را build کنید:

```bash
npm run build
```

فایل‌های build شده در پوشه `dist` قرار می‌گیرند.

## URL‌های پروژه

- **پنل UI**: https://panel.vandapay.com/
- **WordPress API**: https://mr.vandapay.com/wp-json/cwm/v1


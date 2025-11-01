# Company Wallet Manager React App

This Vite + React 18 project implements the multi-role dashboard for the Company Wallet Manager WordPress plugin. It communicates exclusively with the plugin's REST API (`/wp-json/cwm/v1`).

## Getting started

```bash
npm install
cp .env.example .env.local
# مقدار VITE_API_BASE_URL را با دامنه وردپرس خود به‌روزرسانی کنید
npm run dev
```

> **نکته:** در صورتی که متغیر محیطی `VITE_API_BASE_URL` تنظیم نشده باشد، برنامه به صورت خودکار از نشانی دامنه فعلی
> به‌همراه مسیر `/wp-json/cwm/v1` استفاده می‌کند. برای اتصال به دامنه‌ای متفاوت، مقدار متغیر محیطی را حتماً مشخص کنید.

The app ships with:

- React Router v6 for routing between login, registration and dashboard pages.
- Zustand for persistent JWT auth state.
- Axios with interceptors to attach the token and handle 401 responses.
- React Query for data fetching and caching.
- Tailwind CSS + shadcn-inspired UI primitives.
- Recharts for the admin statistics chart.
- React Hot Toast for notifications.

Each role (administrator, company, merchant, employee) has a dedicated dashboard consuming the plugin's existing endpoints.

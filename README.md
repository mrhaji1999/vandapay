# Vandapay

## Deploying the React panel on a different domain

The UI uses the WordPress REST API that is exposed by the Company Wallet Manager plugin. When you deploy the UI on a separate domain or subdomain (for example, `https://panel.vandapay.com`) while the WordPress site remains on `https://mr.vandapay.com`, complete the following steps:

1. **Configure the UI build** – create a `.env` file next to `frontend/package.json` and set the API base URL to the WordPress origin:

   ```env
   VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
   VITE_AUTH_BASE_URL=https://mr.vandapay.com/wp-json/jwt-auth/v1
   ```

   Rebuild the UI (`npm run build`) so that production assets send API requests to the correct host.

2. **Allow the new origin in WordPress** – starting from plugin version 1.0.3 you can whitelist dashboard hosts by defining the constant `CWM_ALLOWED_CORS_ORIGINS` in `wp-config.php` (a comma-separated string or PHP array). For example:

   ```php
   define( 'CWM_ALLOWED_CORS_ORIGINS', 'https://panel.vandapay.com' );
   define( 'JWT_AUTH_CORS_ENABLE', true );
   ```

   The plugin will now answer REST and JWT requests coming from `panel.vandapay.com` with the correct `Access-Control-Allow-*` headers, including OPTIONS preflight checks. Flush any caching layer so the new headers are returned immediately.

Once these steps are in place, the panel hosted on `panel.vandapay.com` can securely talk to the WordPress plugin on `mr.vandapay.com` via JWT-authenticated REST requests.

## انتشار پروژه در گیت‌هاب بدون فایل‌های باینری

اگر قصد دارید سورس را در یک مخزن GitHub جدید قرار دهید و با خطای «Binary files are not supported» روبه‌رو می‌شوید، این مراحل را دنبال کنید:

1. **کل مخزن را کلون کنید**

   ```bash
   git clone https://github.com/<your-account>/<your-repo>.git
   cd <your-repo>
   ```

2. **فایل‌های پروژه را کپی کنید** – پوشه‌های `frontend/` و `company-wallet-manager/` را در این مسیر قرار دهید. فایل‌های تولیدشده مثل `node_modules/` یا خروجی‌های `dist/` را منتقل نکنید؛ این فایل‌ها در `.gitignore` هستند و نباید به مخزن اضافه شوند.

3. **بررسی فایل‌های باینری** – برای اجتناب از فایل‌های ico و مشابه، در مسیر `frontend/public` یک فایل `favicon.svg` متنی وجود دارد و نسخه قدیمی باینری حذف شده است. اگر آیکون اختصاصی دیگری نیاز دارید، از فرمت‌های متنی مانند SVG استفاده کنید یا از Git LFS بهره ببرید.

4. **تغییرات را کمیت کنید و پوش کنید**

   ```bash
   git add .
   git commit -m "Add Vandapay dashboard"
   git push origin main
   ```

با این کار، مخزن شما فقط حاوی کدهای متنی خواهد بود و GitHub خطایی بابت فایل‌های باینری بزرگ یا نامجاز نشان نخواهد داد.

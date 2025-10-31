# Vandapay

## Deploying the React panel on a different domain

The UI uses the WordPress REST API that is exposed by the Company Wallet Manager plugin. When you deploy the UI on a separate domain or subdomain (for example, `https://panel.vandapay.com`) while the WordPress site remains on `https://mr.vandapay.com`, complete the following steps:

1. **Configure the UI build** – create a `.env` file next to `frontend/package.json` and set the API base URL to the WordPress origin:

   ```env
   VITE_API_BASE_URL=https://mr.vandapay.com/wp-json/cwm/v1
   VITE_AUTH_BASE_URL=https://mr.vandapay.com/wp-json/jwt-auth/v1
   ```

   Rebuild the UI (`npm run build`) so that production assets send API requests to the correct host.

2. **Allow the new origin in WordPress** – add the panel origin to the plugin's CORS allow-list by dropping the snippet below in `wp-content/mu-plugins/cwm-cors.php` (create the folder/file if it does not exist) or inside your theme's `functions.php`:

   ```php
   <?php
   add_action( 'rest_api_init', function () {
       remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
       add_filter( 'rest_pre_serve_request', function ( $value ) {
           header( 'Access-Control-Allow-Origin: https://panel.vandapay.com' );
           header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
           header( 'Access-Control-Allow-Credentials: true' );
           header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
           return $value;
       } );
   }, 15 );
  ```

   If you are using the official JWT authentication plugin, also ensure the constant below is defined in `wp-config.php` to let the token endpoint answer cross-domain requests:

   ```php
   define( 'JWT_AUTH_CORS_ENABLE', true );
   ```

   Flush any caching layer so the new headers are returned immediately.

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

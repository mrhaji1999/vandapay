# Vandapay

## Deploying the React panel on a different domain

The UI uses the WordPress REST API that is exposed by the Company Wallet Manager plugin. When you deploy the UI on a separate domain or subdomain (for example, `https://panel.vandapay.com`) while the WordPress site remains on `https://mr.vandapay.com`, complete the following steps:

1. **Configure the UI build** – create a `.env` file next to `ui/package.json` and set the API base URL to the WordPress origin:

   ```env
   VITE_API_BASE_URL=https://mr.vandapay.com
   ```

   Rebuild the UI (`npm run build`) so that production assets send API requests to the correct host.

2. **Allow the new origin in WordPress** – add the panel origin to the plugin's CORS allow-list by dropping the snippet below in `wp-content/mu-plugins/cwm-cors.php` (create the folder/file if it does not exist) or inside your theme's `functions.php`:

   ```php
   <?php
   add_filter( 'cwm_allowed_cors_origins', function ( array $origins ) {
       $origins[] = 'https://panel.vandapay.com';
       return $origins;
   } );
   ```

   Flush any caching layer so the new headers are returned immediately.

Once these steps are in place, the panel hosted on `panel.vandapay.com` can securely talk to the WordPress plugin on `mr.vandapay.com` via JWT-authenticated REST requests.
<?php
/**
 * Test script for merchant products list endpoint
 * 
 * Usage: 
 * 1. Copy this file to WordPress root directory (same level as wp-load.php)
 * 2. Access via browser: 
 *    http://yoursite.com/test-merchant-products.php?merchant_id=3
 *    یا
 *    http://yoursite.com/test-merchant-products.php?username=merchant_username&password=merchant_password
 * 3. Or run via CLI: 
 *    php test-merchant-products.php --merchant_id=3
 *    یا
 *    php test-merchant-products.php --username=merchant_username --password=merchant_password
 * 
 * Note: This file should be deleted after testing for security reasons.
 */

// Load WordPress
$wp_load_path = __DIR__ . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
    // Try one level up
    $wp_load_path = dirname( __DIR__ ) . '/wp-load.php';
    if ( ! file_exists( $wp_load_path ) ) {
        die( "خطا: فایل wp-load.php یافت نشد. لطفاً فایل تست را در ریشه WordPress قرار دهید." );
    }
}

// Set error handler to catch fatal errors
register_shutdown_function( function() {
    $error = error_get_last();
    if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
        global $is_cli;
        $message = "❌ خطای فatal: {$error['message']} در فایل {$error['file']} خط {$error['line']}";
        if ( isset( $is_cli ) && $is_cli ) {
            echo $message . "\n";
        } else {
            echo "<p style='color: red; font-family: monospace;'>{$message}</p>";
        }
    }
} );

require_once $wp_load_path;

// Check if we're in CLI or web mode
$is_cli = php_sapi_name() === 'cli';

if ( $is_cli ) {
    // Parse CLI arguments
    $options = getopt( '', [ 'merchant_id:', 'username:', 'password:' ] );
    $merchant_id = isset( $options['merchant_id'] ) ? (int) $options['merchant_id'] : 0;
    $username = isset( $options['username'] ) ? $options['username'] : '';
    $password = isset( $options['password'] ) ? $options['password'] : '';
} else {
    // Get from query string
    $merchant_id = isset( $_GET['merchant_id'] ) ? (int) $_GET['merchant_id'] : 0;
    $username = isset( $_GET['username'] ) ? sanitize_text_field( $_GET['username'] ) : '';
    $password = isset( $_GET['password'] ) ? sanitize_text_field( $_GET['password'] ) : '';
}

// Enable error reporting
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'log_errors', 1 );

// Function to output results
function output( $message, $is_error = false ) {
    global $is_cli;
    // Flush output immediately
    if ( $is_cli ) {
        echo $message . "\n";
        flush();
    } else {
        $color = $is_error ? 'red' : 'green';
        echo "<p style='color: {$color}; font-family: monospace;'>{$message}</p>";
        flush();
        if ( ob_get_level() > 0 ) {
            @ob_flush();
        }
    }
}

function output_json( $data ) {
    global $is_cli;
    if ( $is_cli ) {
        echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
    } else {
        echo "<pre>" . json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "</pre>";
    }
}

// Wrap everything in try-catch to catch any unexpected errors
try {
    output( "=== تست دریافت لیست محصولات پذیرنده ===" );
    output( "" );
    output( "PHP Version: " . PHP_VERSION );
    output( "WordPress Version: " . ( defined( 'WP_VERSION' ) ? WP_VERSION : 'تعریف نشده' ) );
    output( "مسیر فعلی: " . __DIR__ );
    output( "" );

    // Step 1: Get merchant user
if ( $merchant_id > 0 ) {
    $merchant_user = get_user_by( 'ID', $merchant_id );
    if ( ! $merchant_user ) {
        output( "❌ خطا: کاربر با ID {$merchant_id} یافت نشد.", true );
        exit;
    }
    
    // Check if user has merchant role
    if ( ! in_array( 'merchant', $merchant_user->roles, true ) ) {
        output( "⚠️ هشدار: کاربر با ID {$merchant_id} نقش پذیرنده ندارد. نقش‌های فعلی: " . implode( ', ', $merchant_user->roles ), true );
    }
    
    output( "✓ کاربر پیدا شد: {$merchant_user->user_login} (ID: {$merchant_id})" );
output( "  ایمیل: {$merchant_user->user_email}" );
output( "  نقش‌ها: " . implode( ', ', $merchant_user->roles ) );
} elseif ( ! empty( $username ) && ! empty( $password ) ) {
    // Try to authenticate
    $merchant_user = wp_authenticate( $username, $password );
    if ( is_wp_error( $merchant_user ) ) {
        output( "❌ خطا در احراز هویت: " . $merchant_user->get_error_message(), true );
        exit;
    }
    $merchant_id = $merchant_user->ID;
    output( "✓ احراز هویت موفق: {$merchant_user->user_login} (ID: {$merchant_id})" );
} else {
    output( "❌ خطا: باید merchant_id یا username/password را مشخص کنید.", true );
    output( "" );
    output( "استفاده:" );
    output( "  ?merchant_id=3" );
    output( "  یا" );
    output( "  ?username=merchant_username&password=merchant_password" );
    exit;
}

output( "" );
output( "در حال ادامه تست..." );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

// Step 2: Generate JWT token
output( "" );
output( "=== ایجاد توکن JWT ===" );
output( "در حال بررسی JWT_AUTH_SECRET_KEY..." );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) ) {
    output( "❌ خطا: JWT_AUTH_SECRET_KEY تعریف نشده است.", true );
    output( "  لطفاً در wp-config.php این خط را اضافه کنید:" );
    output( "  define('JWT_AUTH_SECRET_KEY', 'your-secret-key-here');" );
    exit;
}

output( "✓ JWT_AUTH_SECRET_KEY تعریف شده است" );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

// Check if vendor directory exists - try multiple paths
output( "در حال جستجوی vendor/autoload.php..." );
flush();
if ( ! $is_cli ) {
    ob_flush();
}
$possible_paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/company-wallet-manager/vendor/autoload.php',
    WP_PLUGIN_DIR . '/company-wallet-manager/vendor/autoload.php',
];

$vendor_path = null;
foreach ( $possible_paths as $path ) {
    if ( file_exists( $path ) ) {
        $vendor_path = $path;
        break;
    }
}

if ( ! $vendor_path ) {
    output( "❌ خطا: فایل vendor/autoload.php یافت نشد.", true );
    output( "  مسیرهای بررسی شده:" );
    foreach ( $possible_paths as $path ) {
        output( "    - {$path} " . ( file_exists( $path ) ? '✓' : '✗' ) );
    }
    output( "  مسیر فعلی: " . __DIR__ );
    output( "  WP_PLUGIN_DIR: " . ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : 'تعریف نشده' ) );
    exit;
}

output( "✓ فایل vendor/autoload.php یافت شد: {$vendor_path}" );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

output( "در حال بارگذاری vendor/autoload.php..." );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

try {
    if ( ! @require_once $vendor_path ) {
        throw new Exception( "خطا در require_once" );
    }
    output( "✓ vendor/autoload.php بارگذاری شد" );
    flush();
    if ( ! $is_cli ) {
        ob_flush();
    }
} catch ( Throwable $e ) {
    output( "❌ خطا در بارگذاری vendor/autoload.php: " . $e->getMessage(), true );
    output( "  نوع خطا: " . get_class( $e ) );
    output( "  فایل: " . $e->getFile() );
    output( "  خط: " . $e->getLine() );
    exit;
}

// Check if JWT class exists
if ( ! class_exists( '\Firebase\JWT\JWT' ) ) {
    output( "❌ خطا: کلاس Firebase\JWT\JWT یافت نشد.", true );
    output( "  بررسی اینکه آیا autoload درست کار می‌کند..." );
    
    // Try to manually check
    $jwt_path = str_replace( '/autoload.php', '/firebase/php-jwt/src/JWT.php', $vendor_path );
    if ( file_exists( $jwt_path ) ) {
        output( "  فایل JWT.php یافت شد: {$jwt_path}" );
        require_once $jwt_path;
    } else {
        output( "  فایل JWT.php یافت نشد در: {$jwt_path}" );
    }
    
    if ( ! class_exists( '\Firebase\JWT\JWT' ) ) {
        output( "❌ کلاس JWT هنوز یافت نشد. لطفاً composer install را اجرا کنید.", true );
        exit;
    }
}

output( "✓ کلاس Firebase\JWT\JWT یافت شد" );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

output( "در حال ایجاد توکن..." );
flush();
if ( ! $is_cli ) {
    ob_flush();
}

try {
    $issued_at = time();
    $expire = $issued_at + ( 24 * HOUR_IN_SECONDS );
    
    $token_data = [
        'iss'  => get_bloginfo( 'url' ),
        'iat'  => $issued_at,
        'nbf'  => $issued_at,
        'exp'  => $expire,
        'data' => [
            'user' => [
                'id' => $merchant_id,
            ],
        ],
    ];
    
    output( "  ایجاد توکن با داده‌های:" );
    output( "    user_id: {$merchant_id}" );
    output( "    issued_at: {$issued_at}" );
    output( "    expire: {$expire}" );
    output( "    secret key length: " . strlen( JWT_AUTH_SECRET_KEY ) );
    
    $token = \Firebase\JWT\JWT::encode( $token_data, JWT_AUTH_SECRET_KEY, 'HS256' );
    output( "✓ توکن JWT ایجاد شد" );
    output( "  توکن (50 کاراکتر اول): " . substr( $token, 0, 50 ) . "..." );
    output( "  طول توکن: " . strlen( $token ) . " کاراکتر" );
} catch ( Throwable $e ) {
    output( "❌ خطا در ایجاد توکن: " . $e->getMessage(), true );
    output( "  نوع خطا: " . get_class( $e ) );
    output( "  فایل: " . $e->getFile() );
    output( "  خط: " . $e->getLine() );
    if ( $e->getTrace() ) {
        output( "  Stack trace (3 خط اول):" );
        foreach ( array_slice( $e->getTrace(), 0, 3 ) as $index => $trace ) {
            $file = $trace['file'] ?? 'unknown';
            $line = $trace['line'] ?? 'unknown';
            $function = $trace['function'] ?? 'unknown';
            output( "    " . ( $index + 1 ) . ". {$function}() در {$file}:{$line}" );
        }
    }
    exit;
}

output( "" );

// Step 3: Set current user
output( "" );
output( "=== تنظیم کاربر فعلی ===" );
wp_set_current_user( $merchant_id );
$current_user_id = get_current_user_id();
output( "✓ کاربر فعلی تنظیم شد: {$current_user_id}" );

if ( $current_user_id !== $merchant_id ) {
    output( "❌ خطا: user_id تطابق ندارد! (current: {$current_user_id}, expected: {$merchant_id})", true );
} else {
    output( "✓ user_id تطابق دارد" );
}

// Verify user object
$current_user = wp_get_current_user();
output( "  نام کاربری: {$current_user->user_login}" );
output( "  نقش‌ها: " . implode( ', ', $current_user->roles ) );
output( "  ID: {$current_user->ID}" );

// Check permissions
output( "" );
output( "=== بررسی دسترسی‌ها ===" );
$is_merchant = in_array( 'merchant', $current_user->roles, true );
$is_admin = in_array( 'administrator', $current_user->roles, true );
$can_manage = user_can( $current_user, 'manage_wallets' );

output( "  نقش merchant: " . ( $is_merchant ? '✓' : '✗' ) );
output( "  نقش administrator: " . ( $is_admin ? '✓' : '✗' ) );
output( "  دسترسی manage_wallets: " . ( $can_manage ? '✓' : '✗' ) );

output( "" );

// Step 4: Check database directly
global $wpdb;
$products_table = $wpdb->prefix . 'cwm_products';

output( "=== بررسی مستقیم دیتابیس ===" );

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$products_table}'" );
if ( $products_table !== $table_exists ) {
    output( "❌ جدول {$products_table} وجود ندارد!", true );
    output( "  جدول پیدا شده: " . ( $table_exists ?: 'هیچ' ) );
} else {
    output( "✓ جدول {$products_table} وجود دارد" );
}

// Check table structure
output( "" );
output( "=== ساختار جدول ===" );
$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$products_table}", ARRAY_A );
output( "ستون‌های جدول:" );
foreach ( $columns as $column ) {
    output( "  - {$column['Field']} ({$column['Type']})" );
}

// Check all products regardless of merchant_id
output( "" );
output( "=== بررسی تمام محصولات ===" );
$all_products = $wpdb->get_results(
    "SELECT id, merchant_id, name, price, status, created_at 
     FROM {$products_table} 
     ORDER BY created_at DESC 
     LIMIT 20",
    ARRAY_A
);
output( "✓ تعداد کل محصولات در جدول: " . count( $all_products ) );

if ( ! empty( $all_products ) ) {
    output( "" );
    output( "نمونه محصولات (20 مورد آخر):" );
    foreach ( $all_products as $product ) {
        $highlight = (int) $product['merchant_id'] === $merchant_id ? '✓' : '  ';
        output( "  {$highlight} ID: {$product['id']}, merchant_id: {$product['merchant_id']}, نام: {$product['name']}, وضعیت: {$product['status']}" );
    }
}

// Check products for this specific merchant
output( "" );
output( "=== محصولات برای merchant_id {$merchant_id} ===" );
$db_products = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, merchant_id, name, price, status, created_at, product_category_id, stock_quantity, online_purchase_enabled
         FROM {$products_table} 
         WHERE merchant_id = %d 
         ORDER BY created_at DESC",
        $merchant_id
    ),
    ARRAY_A
);

output( "✓ تعداد محصولات در دیتابیس برای merchant_id {$merchant_id}: " . count( $db_products ) );

if ( ! empty( $db_products ) ) {
    output( "" );
    output( "محصولات در دیتابیس:" );
    foreach ( $db_products as $product ) {
        output( "  - ID: {$product['id']}, نام: {$product['name']}, قیمت: {$product['price']}, وضعیت: {$product['status']}, موجودی: {$product['stock_quantity']}" );
    }
} else {
    output( "⚠️ هیچ محصولی در دیتابیس برای این پذیرنده یافت نشد.", true );
    
    // Check if there are products with merchant_id = 0 or NULL
    output( "" );
    output( "=== بررسی محصولات بدون merchant_id ===" );
    $orphaned = $wpdb->get_results(
        "SELECT id, merchant_id, name, price, status, created_at 
         FROM {$products_table} 
         WHERE (merchant_id = 0 OR merchant_id IS NULL) 
         ORDER BY created_at DESC 
         LIMIT 10",
        ARRAY_A
    );
    if ( ! empty( $orphaned ) ) {
        output( "⚠️ محصولات بدون merchant_id یافت شد: " . count( $orphaned ) );
        foreach ( $orphaned as $product ) {
            output( "  - ID: {$product['id']}, merchant_id: " . ( $product['merchant_id'] ?? 'NULL' ) . ", نام: {$product['name']}" );
        }
    }
    
    // Check different merchant_ids
    output( "" );
    output( "=== بررسی merchant_id های مختلف ===" );
    $merchant_ids = $wpdb->get_col( "SELECT DISTINCT merchant_id FROM {$products_table} WHERE merchant_id IS NOT NULL AND merchant_id != 0 ORDER BY merchant_id" );
    output( "merchant_id های موجود در جدول: " . ( ! empty( $merchant_ids ) ? implode( ', ', $merchant_ids ) : 'هیچ' ) );
}

output( "" );

// Step 5: Test API endpoint via REST API
output( "" );
output( "=== تست API Endpoint ===" );

// Initialize REST API routes (in case they haven't been registered yet)
do_action( 'rest_api_init' );

// Get REST server
$server = rest_get_server();

// Check if route exists
output( "بررسی وجود route..." );
$routes = $server->get_routes();
$route_key = '/cwm/v1/merchant/products';
if ( ! isset( $routes[ $route_key ] ) ) {
    output( "❌ Route {$route_key} یافت نشد!", true );
    output( "Route های موجود با prefix /cwm/v1/merchant:" );
    foreach ( $routes as $route => $handlers ) {
        if ( strpos( $route, '/cwm/v1/merchant' ) === 0 ) {
            output( "  - {$route}" );
        }
    }
} else {
    output( "✓ Route {$route_key} یافت شد" );
    $handlers = $routes[ $route_key ];
    output( "  تعداد handler: " . count( $handlers ) );
    foreach ( $handlers as $handler ) {
        if ( isset( $handler['methods']['GET'] ) ) {
            output( "  - Method: GET" );
            if ( isset( $handler['permission_callback'] ) ) {
                output( "    Permission callback: " . ( is_array( $handler['permission_callback'] ) ? get_class( $handler['permission_callback'][0] ) . '::' . $handler['permission_callback'][1] : 'unknown' ) );
            }
        }
    }
}

// Create a REST request
output( "" );
output( "ایجاد درخواست REST..." );
$request = new WP_REST_Request( 'GET', '/cwm/v1/merchant/products' );
$request->set_header( 'Authorization', 'Bearer ' . $token );
output( "✓ درخواست ایجاد شد" );
output( "  URL: /cwm/v1/merchant/products" );
output( "  Method: GET" );
output( "  Authorization header: " . ( $request->get_header( 'Authorization' ) ? '✓ تنظیم شده' : '✗ تنظیم نشده' ) );

// Check permission first
output( "" );
output( "بررسی دسترسی..." );
$permission_check = apply_filters( 'rest_authentication_errors', null );
if ( is_wp_error( $permission_check ) ) {
    output( "⚠️ خطا در authentication: " . $permission_check->get_error_message(), true );
}

// Dispatch the request
output( "" );
output( "ارسال درخواست به سرور..." );
$response = $server->dispatch( $request );

if ( $response->is_error() ) {
    $error = $response->as_error();
    output( "❌ خطا در API: " . $error->get_error_message(), true );
    output( "  کد خطا: " . $error->get_error_code() );
    output( "  داده‌های خطا:" );
    output_json( $error->get_error_data() );
    
    // Additional debugging
    output( "" );
    output( "=== دیباگ اضافی ===" );
    output( "کد وضعیت HTTP: " . $response->get_status() );
    output( "Headers:" );
    output_json( $response->get_headers() );
    exit;
}

$response_data = $response->get_data();
$status_code = $response->get_status();

output( "✓ درخواست API با موفقیت انجام شد" );
output( "  کد وضعیت HTTP: {$status_code}" );

output( "" );
output( "=== تحلیل پاسخ API ===" );
output( "  وضعیت: " . ( $response_data['status'] ?? 'unknown' ) );
output( "  نوع داده data: " . gettype( $response_data['data'] ?? null ) );

if ( isset( $response_data['data'] ) ) {
    if ( is_array( $response_data['data'] ) ) {
        output( "  تعداد محصولات: " . count( $response_data['data'] ) );
    } else {
        output( "  ⚠️ data یک آرایه نیست! نوع: " . gettype( $response_data['data'] ), true );
    }
} else {
    output( "  ⚠️ کلید 'data' در پاسخ وجود ندارد!", true );
}

output( "" );

if ( ! empty( $response_data['data'] ) && is_array( $response_data['data'] ) ) {
    output( "=== لیست محصولات از API ===" );
    foreach ( $response_data['data'] as $index => $product ) {
        output( "  " . ( $index + 1 ) . ". ID: {$product['id']}, نام: {$product['name']}, قیمت: {$product['price']}, موجودی: {$product['stock_quantity']}, وضعیت: {$product['status']}" );
    }
    
    output( "" );
    output( "=== پاسخ کامل JSON ===" );
    output_json( $response_data );
} else {
    output( "⚠️ هیچ محصولی در پاسخ API یافت نشد.", true );
    output( "" );
    output( "=== ساختار کامل پاسخ ===" );
    output_json( $response_data );
    
    // Additional debugging
    output( "" );
    output( "=== بررسی ساختار پاسخ ===" );
    output( "کلیدهای موجود در response_data: " . implode( ', ', array_keys( $response_data ) ) );
    if ( isset( $response_data['data'] ) ) {
        output( "مقدار data: " . var_export( $response_data['data'], true ) );
    }
}

output( "" );

// Step 6: Compare results
output( "" );
output( "=== مقایسه نتایج ===" );
$db_count = count( $db_products );
$api_count = is_array( $response_data['data'] ?? null ) ? count( $response_data['data'] ) : 0;

output( "تعداد محصولات در دیتابیس: {$db_count}" );
output( "تعداد محصولات در API: {$api_count}" );

if ( $db_count === $api_count ) {
    output( "✓ تعداد محصولات در دیتابیس و API یکسان است: {$db_count}" );
} else {
    output( "❌ تعداد محصولات متفاوت است!", true );
    output( "  تفاوت: " . abs( $db_count - $api_count ) );
    
    // Compare IDs
    if ( $db_count > 0 && $api_count > 0 ) {
        output( "" );
        output( "=== مقایسه ID محصولات ===" );
        $db_ids = array_column( $db_products, 'id' );
        $api_ids = array_column( $response_data['data'], 'id' );
        
        $only_in_db = array_diff( $db_ids, $api_ids );
        $only_in_api = array_diff( $api_ids, $db_ids );
        
        if ( ! empty( $only_in_db ) ) {
            output( "⚠️ محصولاتی که فقط در دیتابیس هستند: " . implode( ', ', $only_in_db ), true );
        }
        if ( ! empty( $only_in_api ) ) {
            output( "⚠️ محصولاتی که فقط در API هستند: " . implode( ', ', $only_in_api ), true );
        }
    }
}

// Step 7: Test the actual function directly
output( "" );
output( "=== تست مستقیم تابع list_merchant_products ===" );
try {
    $api_handler = new CWM\API_Handler();
    $direct_request = new WP_REST_Request( 'GET', '/cwm/v1/merchant/products' );
    $direct_request->set_header( 'Authorization', 'Bearer ' . $token );
    
    // Use reflection to call the method directly
    $reflection = new ReflectionClass( $api_handler );
    $method = $reflection->getMethod( 'list_merchant_products' );
    $method->setAccessible( true );
    
    $direct_response = $method->invoke( $api_handler, $direct_request );
    
    if ( is_wp_error( $direct_response ) ) {
        output( "❌ خطا در فراخوانی مستقیم: " . $direct_response->get_error_message(), true );
    } else {
        $direct_data = $direct_response->get_data();
        output( "✓ فراخوانی مستقیم موفق بود" );
        output( "  تعداد محصولات: " . ( is_array( $direct_data['data'] ?? null ) ? count( $direct_data['data'] ) : 0 ) );
        
        if ( is_array( $direct_data['data'] ?? null ) && count( $direct_data['data'] ) !== $api_count ) {
            output( "⚠️ تعداد محصولات در فراخوانی مستقیم متفاوت از REST API است!", true );
        }
    }
} catch ( Exception $e ) {
    output( "❌ خطا در فراخوانی مستقیم: " . $e->getMessage(), true );
}

    output( "" );
    output( "=== تست کامل شد ===" );
    output( "" );
    output( "=== خلاصه ===" );
    output( "✓ کاربر: {$merchant_user->user_login} (ID: {$merchant_id})" );
    output( "✓ محصولات در دیتابیس: {$db_count}" );
    output( "✓ محصولات در API: {$api_count}" );
    if ( $db_count === $api_count && $api_count > 0 ) {
        output( "✅ همه چیز درست کار می‌کند!" );
    } elseif ( $db_count > 0 && $api_count === 0 ) {
        output( "❌ مشکل: محصولات در دیتابیس هستند اما در API نیستند!", true );
        output( "   احتمالاً مشکل از کوئری یا فیلتر status است." );
    } elseif ( $db_count === 0 ) {
        output( "⚠️ هیچ محصولی برای این پذیرنده در دیتابیس وجود ندارد." );
    }

} catch ( Throwable $e ) {
    output( "" );
    output( "❌❌❌ خطای غیرمنتظره رخ داد! ❌❌❌", true );
    output( "  پیام خطا: " . $e->getMessage(), true );
    output( "  نوع خطا: " . get_class( $e ), true );
    output( "  فایل: " . $e->getFile(), true );
    output( "  خط: " . $e->getLine(), true );
    output( "" );
    output( "  Stack Trace:", true );
    $trace = $e->getTrace();
    foreach ( array_slice( $trace, 0, 10 ) as $index => $item ) {
        $file = $item['file'] ?? 'unknown';
        $line = $item['line'] ?? 'unknown';
        $function = $item['function'] ?? 'unknown';
        $class = $item['class'] ?? '';
        $type = $item['type'] ?? '';
        output( "    " . ( $index + 1 ) . ". {$class}{$type}{$function}() در {$file}:{$line}", true );
    }
}


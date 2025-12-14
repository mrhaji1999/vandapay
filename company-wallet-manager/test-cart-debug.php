<?php
/**
 * تست دیباگ سبد خرید
 * 
 * این فایل برای بررسی مشکل نمایش محصولات در سبد خرید استفاده می‌شود.
 * 
 * نحوه استفاده:
 * 1. این فایل را در root وردپرس قرار دهید (همان سطح wp-load.php)
 * 2. از مرورگر به آدرس: https://your-site.com/test-cart-debug.php دسترسی پیدا کنید
 * 3. یا از CLI اجرا کنید: php test-cart-debug.php
 */

// Load WordPress
$wp_load_path = __DIR__ . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
    // Try one level up
    $wp_load_path = dirname( __DIR__ ) . '/wp-load.php';
    if ( ! file_exists( $wp_load_path ) ) {
        // Try two levels up (if in plugin directory)
        $wp_load_path = dirname( dirname( __DIR__ ) ) . '/wp-load.php';
        if ( ! file_exists( $wp_load_path ) ) {
            die( "خطا: فایل wp-load.php یافت نشد. لطفاً فایل تست را در ریشه WordPress قرار دهید." );
        }
    }
}

// Set error handler to catch fatal errors
register_shutdown_function( function() {
    $error = error_get_last();
    if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
        global $is_cli;
        $message = "❌ خطای fatal: {$error['message']} در فایل {$error['file']} خط {$error['line']}";
        if ( isset( $is_cli ) && $is_cli ) {
            echo $message . "\n";
        } else {
            echo "<p style='color: red; font-family: monospace; padding: 10px; background: #fee; border: 1px solid #fcc;'>{$message}</p>";
        }
    }
} );

require_once $wp_load_path;

// Check if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Enable error reporting for debugging
if (!$is_cli) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Output functions
function output($message, $type = 'info') {
    global $is_cli;
    $prefix = $is_cli ? '' : '<div style="margin: 5px 0; padding: 8px; border-left: 3px solid ';
    $suffix = $is_cli ? "\n" : '</div>';
    
    $colors = [
        'info' => '#2196F3',
        'success' => '#4CAF50',
        'error' => '#F44336',
        'warning' => '#FF9800'
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    
    if ($is_cli) {
        echo $message . $suffix;
    } else {
        echo $prefix . $color . '; background: ' . $color . '10; padding: 8px; margin: 5px 0;">' . htmlspecialchars($message) . $suffix;
    }
}

function output_json($data, $label = '') {
    global $is_cli;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($label) {
        output("=== $label ===", 'info');
    }
    if ($is_cli) {
        echo $json . "\n\n";
    } else {
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">' . htmlspecialchars($json) . '</pre>';
    }
}

// Start output buffering
if (!$is_cli) {
    ob_start();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8"><title>تست دیباگ سبد خرید</title><style>body{font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f5f5f5;}</style></head><body>';
}

output('=== تست دیباگ سبد خرید ===', 'info');
output('PHP Version: ' . PHP_VERSION, 'info');
output('WordPress Version: ' . (defined('WP_VERSION') ? WP_VERSION : 'تعریف نشده'), 'info');
output('مسیر فعلی: ' . __DIR__, 'info');
output('', 'info');

// Get employee user (first employee user found)
global $wpdb;
$employee_user = $wpdb->get_row(
    "SELECT * FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
    WHERE um.meta_key = 'wp_capabilities' 
    AND um.meta_value LIKE '%employee%'
    LIMIT 1",
    ARRAY_A
);

if (!$employee_user) {
    output('✗ هیچ کاربر employee یافت نشد!', 'error');
    output('لطفاً ابتدا یک کاربر employee ایجاد کنید.', 'warning');
    exit;
}

$employee_id = $employee_user['ID'];
output("✓ کاربر employee پیدا شد: {$employee_user['user_login']} (ID: $employee_id)", 'success');
output("ایمیل: {$employee_user['user_email']}", 'info');

// Get user roles
$caps = get_user_meta($employee_id, 'wp_capabilities', true);
$roles = is_array($caps) ? array_keys($caps) : [];
output("نقش‌ها: " . implode(', ', $roles), 'info');
output('', 'info');

// Set current user
wp_set_current_user($employee_id);
$current_user_id = get_current_user_id();
output("✓ کاربر فعلی تنظیم شد: $current_user_id", 'success');

if ($current_user_id != $employee_id) {
    output("⚠ هشدار: user_id تطابق ندارد! (expected: $employee_id, got: $current_user_id)", 'warning');
}
output('', 'info');

// Check cart table
$cart_table = $wpdb->prefix . 'cwm_cart_items';
$products_table = $wpdb->prefix . 'cwm_products';

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cart_table'") == $cart_table;
if (!$table_exists) {
    output("✗ جدول $cart_table وجود ندارد!", 'error');
    exit;
}
output("✓ جدول $cart_table وجود دارد", 'success');

$products_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$products_table'") == $products_table;
if (!$products_table_exists) {
    output("✗ جدول $products_table وجود ندارد!", 'error');
    exit;
}
output("✓ جدول $products_table وجود دارد", 'success');
output('', 'info');

// Check cart items directly
output('=== بررسی مستقیم سبد خرید در دیتابیس ===', 'info');
$cart_items = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$cart_table} WHERE employee_id = %d",
        $employee_id
    ),
    ARRAY_A
);

output("✓ تعداد آیتم‌های سبد خرید در دیتابیس: " . count($cart_items), 'success');

if (count($cart_items) > 0) {
    output('', 'info');
    output('=== آیتم‌های سبد خرید ===', 'info');
    foreach ($cart_items as $item) {
        output("  - ID: {$item['id']}, product_id: {$item['product_id']}, quantity: {$item['quantity']}, created_at: {$item['created_at']}", 'info');
    }
} else {
    output('⚠ سبد خرید خالی است!', 'warning');
    output('', 'info');
    
    // Check if there are any products available
    $products = $wpdb->get_results(
        "SELECT id, name, merchant_id, status, online_purchase_enabled, stock_quantity 
         FROM {$products_table} 
         LIMIT 10",
        ARRAY_A
    );
    
    output("=== بررسی محصولات موجود ===", 'info');
    output("✓ تعداد کل محصولات در جدول: " . count($products), 'success');
    
    if (count($products) > 0) {
        output('', 'info');
        output('نمونه محصولات:', 'info');
        foreach ($products as $product) {
            $status = $product['status'] ?? 'NULL';
            $online = $product['online_purchase_enabled'] ? 'true' : 'false';
            output("  - ID: {$product['id']}, نام: {$product['name']}, merchant_id: {$product['merchant_id']}, status: $status, online_purchase_enabled: $online, stock: {$product['stock_quantity']}", 'info');
        }
    }
}
output('', 'info');

// Test the get_cart query
output('=== تست Query سبد خرید ===', 'info');
$test_query = $wpdb->prepare(
    "SELECT ci.*, p.name as product_name, p.price, p.image, p.stock_quantity, 
            p.merchant_id, p.product_category_id,
            (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = p.merchant_id AND meta_key = '_cwm_store_name') as store_name
    FROM {$cart_table} ci
    INNER JOIN {$products_table} p ON ci.product_id = p.id
    WHERE ci.employee_id = %d
    ORDER BY ci.created_at DESC",
    $employee_id
);

$test_results = $wpdb->get_results($test_query, ARRAY_A);
output("✓ تعداد نتایج query: " . count($test_results), 'success');

if (count($test_results) > 0) {
    output('', 'info');
    output('=== نتایج Query ===', 'info');
    foreach ($test_results as $result) {
        output("  - Cart ID: {$result['id']}, Product: {$result['product_name']}, Price: {$result['price']}, Quantity: {$result['quantity']}, Store: {$result['store_name']}", 'info');
    }
} else {
    output('⚠ Query هیچ نتیجه‌ای برنگرداند!', 'warning');
    
    // Check if there are cart items but query failed
    if (count($cart_items) > 0) {
        output('⚠ مشکل: آیتم‌های سبد خرید در دیتابیس هستند اما query نتیجه نمی‌دهد!', 'error');
        output('', 'info');
        output('=== بررسی مشکل Query ===', 'info');
        
        // Check each cart item
        foreach ($cart_items as $item) {
            $product = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$products_table} WHERE id = %d", $item['product_id']),
                ARRAY_A
            );
            
            if ($product) {
                output("  ✓ محصول با ID {$item['product_id']} پیدا شد: {$product['name']}", 'success');
                output("    - status: " . ($product['status'] ?? 'NULL'), 'info');
                output("    - online_purchase_enabled: " . ($product['online_purchase_enabled'] ? 'true' : 'false'), 'info');
            } else {
                output("  ✗ محصول با ID {$item['product_id']} پیدا نشد!", 'error');
            }
        }
    }
}
output('', 'info');

// Test API endpoint
output('=== تست API Endpoint ===', 'info');

// Check if JWT auth is available
$jwt_secret = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : get_option('jwt_auth_secret_key');
if (!$jwt_secret) {
    output('⚠ JWT_AUTH_SECRET_KEY تعریف نشده است. نمی‌توانم توکن ایجاد کنم.', 'warning');
} else {
    // Try to load JWT library
    $vendor_paths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        WP_PLUGIN_DIR . '/company-wallet-manager/vendor/autoload.php'
    ];
    
    $vendor_loaded = false;
    foreach ($vendor_paths as $vendor_path) {
        if (file_exists($vendor_path)) {
            require_once $vendor_path;
            $vendor_loaded = true;
            output("✓ فایل vendor/autoload.php یافت شد: $vendor_path", 'success');
            break;
        }
    }
    
    if (!$vendor_loaded) {
        output('⚠ فایل vendor/autoload.php یافت نشد. مسیرهای بررسی شده:', 'warning');
        foreach ($vendor_paths as $path) {
            output("  - $path", 'info');
        }
    } elseif (class_exists('Firebase\JWT\JWT')) {
        try {
            // Create JWT token
            $issued_at = time();
            $expire = $issued_at + (24 * 60 * 60); // 24 hours
            
            $payload = [
                'iss' => get_bloginfo('url'),
                'iat' => $issued_at,
                'nbf' => $issued_at,
                'exp' => $expire,
                'data' => [
                    'user' => [
                        'id' => $employee_id
                    ]
                ]
            ];
            
            $token = \Firebase\JWT\JWT::encode($payload, $jwt_secret, 'HS256');
            output("✓ توکن JWT ایجاد شد", 'success');
            
            // Test API endpoint
            if (class_exists('CWM\API_Handler')) {
                $api_handler = new CWM\API_Handler();
                $request = new WP_REST_Request('GET', '/cwm/v1/cart');
                $request->set_header('Authorization', 'Bearer ' . $token);
                
                // Set current user for the request
                wp_set_current_user($employee_id);
                
                $response = $api_handler->get_cart($request);
                
                if (is_wp_error($response)) {
                    output("✗ خطا در API: " . $response->get_error_message(), 'error');
                    output("  کد خطا: " . $response->get_error_code(), 'error');
                } else {
                    $response_data = $response->get_data();
                    output("✓ API با موفقیت پاسخ داد", 'success');
                    output("  - status: " . ($response_data['status'] ?? 'N/A'), 'info');
                    output("  - تعداد آیتم‌ها: " . (isset($response_data['data']) ? count($response_data['data']) : 0), 'info');
                    
                    if (isset($response_data['data']) && count($response_data['data']) > 0) {
                        output('', 'info');
                        output('=== آیتم‌های سبد خرید از API ===', 'info');
                        foreach ($response_data['data'] as $item) {
                            output("  - ID: {$item['id']}, Product: {$item['product_name']}, Price: {$item['price']}, Quantity: {$item['quantity']}, Subtotal: {$item['subtotal']}", 'info');
                        }
                    } else {
                        output('⚠ API هیچ آیتمی برنگرداند!', 'warning');
                    }
                    
                    output_json($response_data, 'پاسخ کامل API');
                }
            } else {
                output('⚠ کلاس CWM\API_Handler یافت نشد.', 'warning');
            }
        } catch (Exception $e) {
            output("✗ خطا در ایجاد توکن یا فراخوانی API: " . $e->getMessage(), 'error');
            output("  فایل: " . $e->getFile() . " خط: " . $e->getLine(), 'error');
        }
    } else {
        output('⚠ کلاس Firebase\JWT\JWT یافت نشد.', 'warning');
    }
}
output('', 'info');

// Summary
output('=== خلاصه ===', 'info');
output("✓ کاربر: {$employee_user['user_login']} (ID: $employee_id)", 'success');
output("✓ آیتم‌های سبد در دیتابیس: " . count($cart_items), count($cart_items) > 0 ? 'success' : 'warning');
output("✓ نتایج Query: " . count($test_results), count($test_results) > 0 ? 'success' : 'warning');

if (count($cart_items) > 0 && count($test_results) == 0) {
    output('', 'info');
    output('⚠⚠⚠ مشکل پیدا شد! ⚠⚠⚠', 'error');
    output('آیتم‌های سبد خرید در دیتابیس هستند اما query نتیجه نمی‌دهد.', 'error');
    output('احتمالاً مشکل از JOIN با جدول products است.', 'error');
} elseif (count($cart_items) == 0) {
    output('', 'info');
    output('⚠ سبد خرید خالی است. ابتدا محصولی به سبد اضافه کنید.', 'warning');
} else {
    output('', 'info');
    output('✅ همه چیز درست به نظر می‌رسد!', 'success');
}

if (!$is_cli) {
    echo '</body></html>';
    ob_end_flush();
}


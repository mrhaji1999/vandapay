<?php
/**
 * Simple Test Script - No errors
 * 
 * Access: yoursite.com/wp-content/plugins/company-wallet-manager/test-simple.php
 */

// Load WordPress
$wp_load_paths = [
    dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php',
    dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php',
];

foreach ( $wp_load_paths as $path ) {
    if ( file_exists( $path ) ) {
        require_once( $path );
        break;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    die( 'WordPress not loaded' );
}

if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied' );
}

global $wpdb;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Database Test</h1>
        
        <?php
        $table_name = $wpdb->prefix . 'cwm_company_category_caps';
        
        // Check table
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( $exists ) {
            echo '<p class="success">✓ Table exists</p>';
            
            // Get columns
            $columns = $wpdb->get_results( "DESCRIBE $table_name", ARRAY_A );
            $column_names = array_column( $columns, 'Field' );
            
            echo '<h2>Table Columns</h2>';
            echo '<table>';
            echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
            foreach ( $columns as $col ) {
                $status = '';
                if ( $col['Field'] === 'limit_type' || $col['Field'] === 'limit_value' ) {
                    $status = ' <span class="success">(Required)</span>';
                }
                echo '<tr>';
                echo '<td>' . esc_html( $col['Field'] ) . $status . '</td>';
                echo '<td>' . esc_html( $col['Type'] ) . '</td>';
                echo '<td>' . esc_html( $col['Null'] ) . '</td>';
                echo '<td>' . esc_html( $col['Key'] ) . '</td>';
                echo '<td>' . esc_html( $col['Default'] ?? 'NULL' ) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // Check required columns
            $has_limit_type = in_array( 'limit_type', $column_names, true );
            $has_limit_value = in_array( 'limit_value', $column_names, true );
            
            echo '<h2>Status</h2>';
            if ( $has_limit_type && $has_limit_value ) {
                echo '<p class="success">✓ All required columns exist</p>';
            } else {
                echo '<p class="error">✗ Missing columns:</p>';
                echo '<ul>';
                if ( ! $has_limit_type ) echo '<li class="error">limit_type</li>';
                if ( ! $has_limit_value ) echo '<li class="error">limit_value</li>';
                echo '</ul>';
                echo '<p class="info">Run fix-category-caps-table.php to add missing columns</p>';
            }
            
            // Test query
            echo '<h2>Test Query</h2>';
            $test_query = "SELECT COUNT(*) FROM $table_name";
            $count = $wpdb->get_var( $test_query );
            if ( $count !== null ) {
                echo '<p class="success">✓ Query successful. Row count: ' . esc_html( $count ) . '</p>';
            } else {
                echo '<p class="error">✗ Query failed: ' . esc_html( $wpdb->last_error ) . '</p>';
            }
            
        } else {
            echo '<p class="error">✗ Table does not exist</p>';
        }
        ?>
        
        <hr>
        <p><small>Remove this file after testing</small></p>
    </div>
</body>
</html>


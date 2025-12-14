<?php
/**
 * Fix Category Caps Table - Add missing columns
 * 
 * Place this file in the plugin root and access it via: 
 * yoursite.com/wp-content/plugins/company-wallet-manager/fix-category-caps-table.php
 * 
 * IMPORTANT: Remove this file after running!
 */

// Try to find wp-load.php
$wp_load_paths = [
    dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php',
    dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php',
    dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php',
];

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
    if ( file_exists( $path ) ) {
        require_once( $path );
        $wp_loaded = true;
        break;
    }
}

if ( ! $wp_loaded ) {
    die( 'Error: Could not find wp-load.php. Please check the file path.' );
}

if ( ! function_exists( 'current_user_can' ) ) {
    die( 'Error: WordPress not loaded properly.' );
}

if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied. You must be logged in as an administrator.' );
}

global $wpdb;

echo '<h1>Fix Category Caps Table</h1>';
echo '<style>body { font-family: Arial; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>';

$table_name = $wpdb->prefix . 'cwm_company_category_caps';

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

if ( ! $table_exists ) {
    echo '<p class="error">✗ Table does not exist: ' . esc_html( $table_name ) . '</p>';
    echo '<p>Please activate the plugin first to create the table.</p>';
    exit;
}

echo '<p class="success">✓ Table exists: ' . esc_html( $table_name ) . '</p>';

// Check current structure
$columns = $wpdb->get_results( "DESCRIBE $table_name", ARRAY_A );
$column_names = array_column( $columns, 'Field' );

echo '<h2>Current Table Structure</h2>';
echo '<ul>';
foreach ( $columns as $column ) {
    echo '<li>' . esc_html( $column['Field'] ) . ' - ' . esc_html( $column['Type'] ) . '</li>';
}
echo '</ul>';

// Check if limit_type column exists
$has_limit_type = in_array( 'limit_type', $column_names, true );
$has_limit_value = in_array( 'limit_value', $column_names, true );

echo '<h2>Migration Status</h2>';

if ( $has_limit_type && $has_limit_value ) {
    echo '<p class="success">✓ All required columns exist. No migration needed.</p>';
} else {
    echo '<p class="info">⚠ Missing columns detected. Adding them now...</p>';
    
    $errors = [];
    
    // Add limit_type column if missing
    if ( ! $has_limit_type ) {
        $sql = "ALTER TABLE $table_name ADD COLUMN limit_type VARCHAR(20) DEFAULT 'amount' AFTER spending_cap";
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            $errors[] = 'Failed to add limit_type column: ' . $wpdb->last_error;
            echo '<p class="error">✗ Failed to add limit_type column: ' . esc_html( $wpdb->last_error ) . '</p>';
        } else {
            echo '<p class="success">✓ Added limit_type column</p>';
        }
    } else {
        echo '<p class="success">✓ limit_type column already exists</p>';
    }
    
    // Add limit_value column if missing
    if ( ! $has_limit_value ) {
        $sql = "ALTER TABLE $table_name ADD COLUMN limit_value DECIMAL(20,6) DEFAULT NULL AFTER limit_type";
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            $errors[] = 'Failed to add limit_value column: ' . $wpdb->last_error;
            echo '<p class="error">✗ Failed to add limit_value column: ' . esc_html( $wpdb->last_error ) . '</p>';
        } else {
            echo '<p class="success">✓ Added limit_value column</p>';
        }
    } else {
        echo '<p class="success">✓ limit_value column already exists</p>';
    }
    
    if ( empty( $errors ) ) {
        echo '<h2 class="success">✓ Migration completed successfully!</h2>';
        
        // Verify the changes
        $columns_after = $wpdb->get_results( "DESCRIBE $table_name", ARRAY_A );
        $column_names_after = array_column( $columns_after, 'Field' );
        
        if ( in_array( 'limit_type', $column_names_after, true ) && in_array( 'limit_value', $column_names_after, true ) ) {
            echo '<p class="success">✓ Verification: All columns are now present</p>';
        } else {
            echo '<p class="error">✗ Verification failed: Columns still missing</p>';
        }
    } else {
        echo '<h2 class="error">✗ Migration completed with errors</h2>';
        echo '<ul>';
        foreach ( $errors as $error ) {
            echo '<li class="error">' . esc_html( $error ) . '</li>';
        }
        echo '</ul>';
    }
}

echo '<hr>';
echo '<p><strong>Note:</strong> Remove this file after running for security reasons.</p>';


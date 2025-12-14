<?php
/**
 * Debug script for category caps issue
 * 
 * Place this file in the plugin root and access it via: 
 * yoursite.com/wp-content/plugins/company-wallet-manager/test-category-caps-debug.php
 * 
 * IMPORTANT: Remove this file after debugging!
 */

// Try to find wp-load.php
$wp_load_paths = [
    dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php', // Standard location
    dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php', // Alternative
    dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php', // Another alternative
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

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
    die( 'Access denied. You must be logged in as an administrator or editor.' );
}

global $wpdb;

echo '<h1>Category Caps Debug Information</h1>';
echo '<style>body { font-family: Arial; margin: 20px; } table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; } .error { color: red; } .success { color: green; }</style>';

// Check table existence
$table_name = $wpdb->prefix . 'cwm_company_category_caps';
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

echo '<h2>1. Table Check</h2>';
if ( $table_exists ) {
    echo '<p class="success">✓ Table exists: ' . esc_html( $table_name ) . '</p>';
    
    // Check table structure
    $columns = $wpdb->get_results( "DESCRIBE $table_name", ARRAY_A );
    echo '<h3>Table Structure:</h3>';
    echo '<table>';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    foreach ( $columns as $column ) {
        echo '<tr>';
        echo '<td>' . esc_html( $column['Field'] ) . '</td>';
        echo '<td>' . esc_html( $column['Type'] ) . '</td>';
        echo '<td>' . esc_html( $column['Null'] ) . '</td>';
        echo '<td>' . esc_html( $column['Key'] ) . '</td>';
        echo '<td>' . esc_html( $column['Default'] ?? 'NULL' ) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Check for required columns
    $required_columns = [ 'id', 'company_id', 'category_id', 'limit_type', 'limit_value' ];
    $existing_columns = array_column( $columns, 'Field' );
    $missing_columns = array_diff( $required_columns, $existing_columns );
    
    if ( ! empty( $missing_columns ) ) {
        echo '<p class="error">✗ Missing columns: ' . esc_html( implode( ', ', $missing_columns ) ) . '</p>';
    } else {
        echo '<p class="success">✓ All required columns exist</p>';
    }
} else {
    echo '<p class="error">✗ Table does not exist: ' . esc_html( $table_name ) . '</p>';
    echo '<p>You may need to run the plugin activation hook or create the table manually.</p>';
}

// Check current user and company
echo '<h2>2. Current User & Company</h2>';
$current_user = wp_get_current_user();
echo '<p>Current User ID: ' . esc_html( $current_user->ID ) . '</p>';
echo '<p>Current User Email: ' . esc_html( $current_user->user_email ) . '</p>';

$company_posts = get_posts( [
    'post_type'   => 'cwm_company',
    'meta_key'    => '_cwm_company_user_id',
    'meta_value'  => $current_user->ID,
    'post_status' => 'any',
    'numberposts' => 1,
] );

if ( ! empty( $company_posts ) ) {
    $company_id = $company_posts[0]->ID;
    echo '<p class="success">✓ Company found: ID ' . esc_html( $company_id ) . '</p>';
    echo '<p>Company Post Title: ' . esc_html( $company_posts[0]->post_title ) . '</p>';
    
    // Check existing caps
    echo '<h2>3. Existing Category Caps</h2>';
    $existing_caps = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE company_id = %d",
            $company_id
        ),
        ARRAY_A
    );
    
    if ( empty( $existing_caps ) ) {
        echo '<p>No category caps found for this company.</p>';
    } else {
        echo '<table>';
        echo '<tr><th>ID</th><th>Category ID</th><th>Limit Type</th><th>Limit Value</th><th>Created At</th><th>Updated At</th></tr>';
        foreach ( $existing_caps as $cap ) {
            echo '<tr>';
            echo '<td>' . esc_html( $cap['id'] ) . '</td>';
            echo '<td>' . esc_html( $cap['category_id'] ) . '</td>';
            echo '<td>' . esc_html( $cap['limit_type'] ?? 'N/A' ) . '</td>';
            echo '<td>' . esc_html( $cap['limit_value'] ?? 'N/A' ) . '</td>';
            echo '<td>' . esc_html( $cap['created_at'] ?? 'N/A' ) . '</td>';
            echo '<td>' . esc_html( $cap['updated_at'] ?? 'N/A' ) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // Test insert
    echo '<h2>4. Test Insert/Update</h2>';
    $test_category_id = 1; // Change this to a real category ID
    $test_limit_type = 'amount';
    $test_limit_value = 1000000;
    
    $test_data = [
        'company_id'  => $company_id,
        'category_id' => $test_category_id,
        'limit_type'  => $test_limit_type,
        'limit_value' => $test_limit_value,
    ];
    
    $format = [ '%d', '%d', '%s', '%f' ];
    
    // Check if exists
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $table_name WHERE company_id = %d AND category_id = %d",
        $company_id,
        $test_category_id
    ) );
    
    if ( $existing ) {
        echo '<p>Updating existing cap (ID: ' . esc_html( $existing ) . ')...</p>';
        $result = $wpdb->update(
            $table_name,
            $test_data,
            [ 'id' => $existing ],
            $format,
            [ '%d' ]
        );
        
        if ( false === $result ) {
            echo '<p class="error">✗ Update failed!</p>';
            echo '<p>Last Error: ' . esc_html( $wpdb->last_error ) . '</p>';
            echo '<p>Last Query: ' . esc_html( $wpdb->last_query ) . '</p>';
        } else {
            echo '<p class="success">✓ Update successful! Rows affected: ' . esc_html( $result ) . '</p>';
        }
    } else {
        echo '<p>Inserting new cap...</p>';
        $result = $wpdb->insert(
            $table_name,
            $test_data,
            $format
        );
        
        if ( false === $result ) {
            echo '<p class="error">✗ Insert failed!</p>';
            echo '<p>Last Error: ' . esc_html( $wpdb->last_error ) . '</p>';
            echo '<p>Last Query: ' . esc_html( $wpdb->last_query ) . '</p>';
        } else {
            echo '<p class="success">✓ Insert successful! Insert ID: ' . esc_html( $wpdb->insert_id ) . '</p>';
        }
    }
    
    // Verify the data was saved
    if ( $result !== false ) {
        $verify = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE company_id = %d AND category_id = %d",
            $company_id,
            $test_category_id
        ), ARRAY_A );
        
        if ( $verify ) {
            echo '<h3>Verified Data:</h3>';
            echo '<pre>' . print_r( $verify, true ) . '</pre>';
        } else {
            echo '<p class="error">✗ Data not found after save!</p>';
        }
    }
    
} else {
    echo '<p class="error">✗ No company found for current user</p>';
    echo '<p>Make sure you are logged in as a company user.</p>';
}

// Check categories
echo '<h2>5. Available Categories</h2>';
$categories_table = $wpdb->prefix . 'cwm_categories';
$categories = $wpdb->get_results( "SELECT id, name, slug FROM $categories_table ORDER BY name", ARRAY_A );

if ( empty( $categories ) ) {
    echo '<p>No categories found.</p>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Name</th><th>Slug</th></tr>';
    foreach ( $categories as $cat ) {
        echo '<tr>';
        echo '<td>' . esc_html( $cat['id'] ) . '</td>';
        echo '<td>' . esc_html( $cat['name'] ) . '</td>';
        echo '<td>' . esc_html( $cat['slug'] ) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

echo '<hr>';
echo '<p><strong>Note:</strong> Remove this file after debugging for security reasons.</p>';


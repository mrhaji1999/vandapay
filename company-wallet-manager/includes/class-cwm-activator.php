<?php

namespace CWM;

/**
 * Handle creation of custom tables on plugin activation.
 */
class CWM_Activator {
    /**
     * Create database tables related to category management.
     */
    public static function create_category_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $categories_table   = $wpdb->prefix . 'cwm_categories';
        $company_caps_table = $wpdb->prefix . 'cwm_company_category_caps';

        $categories_sql = "CREATE TABLE {$categories_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
        ) {$charset_collate};";

        $company_caps_sql = "CREATE TABLE {$company_caps_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                company_id BIGINT(20) UNSIGNED NOT NULL,
                category_id BIGINT(20) UNSIGNED NOT NULL,
                spending_cap DECIMAL(20,6) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY company_category (company_id, category_id),
                KEY category_id (category_id)
        ) {$charset_collate};";

        dbDelta( $categories_sql );
        dbDelta( $company_caps_sql );
    }
}

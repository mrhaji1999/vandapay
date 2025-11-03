<?php

namespace CWM;

require_once __DIR__ . '/class-cwm-activator.php';

/**
 * Class Plugin_Loader
 *
 * @package CWM
 */
class Plugin_Loader {

	/**
	 * Plugin activation hook.
	 */
	public static function activate() {
		// Create database tables.
		self::create_tables();

		// Create custom user roles.
		Role_Manager::create_roles();

		// Create the logs directory.
                if ( ! file_exists( CWM_PLUGIN_DIR . 'logs' ) ) {
                        wp_mkdir_p( CWM_PLUGIN_DIR . 'logs' );
                }

                $htaccess_path = CWM_PLUGIN_DIR . 'logs/.htaccess';
                if ( ! file_exists( $htaccess_path ) ) {
                        file_put_contents( $htaccess_path, "Deny from all\n" );
                }

                // Flush rewrite rules.
                flush_rewrite_rules();
        }

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate() {
		// Remove custom user roles.
		Role_Manager::remove_roles();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $table_name = $wpdb->prefix . 'cwm_wallets';
                $sql        = "CREATE TABLE $table_name (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        user_id BIGINT(20) UNSIGNED NOT NULL,
                        balance DECIMAL(20, 6) NOT NULL DEFAULT 0,
                        currency VARCHAR(10) NOT NULL DEFAULT 'IRT',
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY user_id (user_id)
                ) $charset_collate;";
                dbDelta( $sql );

                $table_name = $wpdb->prefix . 'cwm_transactions';
                $sql        = "CREATE TABLE $table_name (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        type VARCHAR(50) NOT NULL,
                        context VARCHAR(191) DEFAULT NULL,
                        sender_id BIGINT(20) UNSIGNED DEFAULT NULL,
                        receiver_id BIGINT(20) UNSIGNED DEFAULT NULL,
                        related_request BIGINT(20) UNSIGNED DEFAULT NULL,
                        amount DECIMAL(20, 6) NOT NULL DEFAULT 0,
                        balance_snapshot_sender DECIMAL(20, 6) DEFAULT NULL,
                        balance_snapshot_receiver DECIMAL(20, 6) DEFAULT NULL,
                        status VARCHAR(50) NOT NULL DEFAULT 'completed',
                        metadata LONGTEXT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        KEY type (type),
                        KEY sender_id (sender_id),
                        KEY receiver_id (receiver_id),
                        KEY related_request (related_request),
                        KEY created_at (created_at)
                ) $charset_collate;";
                dbDelta( $sql );

                $table_name = $wpdb->prefix . 'cwm_payment_requests';
                $sql        = "CREATE TABLE $table_name (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        merchant_id BIGINT(20) UNSIGNED NOT NULL,
                        employee_id BIGINT(20) UNSIGNED NOT NULL,
                        category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                        amount DECIMAL(20, 6) NOT NULL DEFAULT 0,
                        otp VARCHAR(10) NOT NULL,
                        otp_expires_at DATETIME DEFAULT NULL,
                        failed_attempts TINYINT(2) NOT NULL DEFAULT 0,
                        locked_at DATETIME DEFAULT NULL,
                        status VARCHAR(50) NOT NULL DEFAULT 'pending',
                        metadata LONGTEXT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        KEY merchant_id (merchant_id),
                        KEY employee_id (employee_id),
                        KEY category_id (category_id),
                        KEY status (status),
                        KEY created_at (created_at)
                ) $charset_collate;";
                dbDelta( $sql );

                $table_name = $wpdb->prefix . 'cwm_payout_requests';
                $sql        = "CREATE TABLE $table_name (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        merchant_id BIGINT(20) UNSIGNED NOT NULL,
                        amount DECIMAL(20, 6) NOT NULL DEFAULT 0,
                        bank_account VARCHAR(191) NOT NULL,
                        bank_meta LONGTEXT NULL,
                        status VARCHAR(50) NOT NULL DEFAULT 'pending',
                        approved_by BIGINT(20) UNSIGNED DEFAULT NULL,
                        approved_at DATETIME DEFAULT NULL,
                        processed_at DATETIME DEFAULT NULL,
                        notes LONGTEXT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        KEY merchant_id (merchant_id),
                        KEY status (status),
                        KEY created_at (created_at)
                ) $charset_collate;";
                dbDelta( $sql );

                CWM_Activator::create_category_tables();

                $table_name = $wpdb->prefix . 'cwm_category_merchants';
                $sql        = "CREATE TABLE $table_name (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        merchant_id BIGINT(20) UNSIGNED NOT NULL,
                        category_id BIGINT(20) UNSIGNED NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY merchant_category (merchant_id, category_id),
                        KEY category_id (category_id)
                ) $charset_collate;";
                dbDelta( $sql );

                $table_name = $wpdb->prefix . 'cwm_employee_category_limits';
                $sql        = "CREATE TABLE $table_name (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        employee_id BIGINT(20) UNSIGNED NOT NULL,
                        company_id BIGINT(20) UNSIGNED DEFAULT NULL,
                        category_id BIGINT(20) UNSIGNED NOT NULL,
                        spending_limit DECIMAL(20,6) NOT NULL DEFAULT 0,
                        spent_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY employee_category (employee_id, category_id),
                        KEY company_id (company_id),
                        KEY category_id (category_id)
                ) $charset_collate;";
                dbDelta( $sql );
        }
}

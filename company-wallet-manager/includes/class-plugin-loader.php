<?php

namespace CWM;

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
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			balance decimal(10, 2) NOT NULL DEFAULT '0.00',
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'cwm_transactions';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			type varchar(255) NOT NULL,
			sender_id bigint(20) NOT NULL,
			receiver_id bigint(20) NOT NULL,
			amount decimal(10, 2) NOT NULL,
			status varchar(255) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'cwm_payment_requests';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			merchant_id bigint(20) NOT NULL,
			employee_id bigint(20) NOT NULL,
			amount decimal(10, 2) NOT NULL,
			otp varchar(255) NOT NULL,
			status varchar(255) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'cwm_payout_requests';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			merchant_id bigint(20) NOT NULL,
			amount decimal(10, 2) NOT NULL,
			bank_account text NOT NULL,
			status varchar(255) NOT NULL,
			approved_by bigint(20) NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );
	}
}

<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Company_Wallet_Manager
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cwm_wallets" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cwm_transactions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cwm_payment_requests" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cwm_payout_requests" );

// Remove custom user roles.
remove_role( 'company' );
remove_role( 'merchant' );
remove_role( 'employee' );
remove_role( 'finance_officer' );

// Remove capabilities from the administrator role.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$admin_role->remove_cap( 'manage_wallets' );
	$admin_role->remove_cap( 'approve_payouts' );
}

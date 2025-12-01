<?php

namespace CWM;

/**
 * Class Role_Manager
 *
 * @package CWM
 */
class Role_Manager {

	/**
	 * Create custom user roles.
	 */
	public static function create_roles() {
		add_role( 'company', 'Company', array( 'read' => true, 'manage_wallets' => true ) );
		add_role( 'merchant', 'Merchant', array( 'read' => true ) );
		add_role( 'employee', 'مشتری', array( 'read' => true ) );
		add_role( 'finance_officer', 'Finance Officer', array( 'read' => true, 'approve_payouts' => true ) );

		$admin_role = get_role( 'administrator' );
		$admin_role->add_cap( 'manage_wallets' );
		$admin_role->add_cap( 'approve_payouts' );

		// Add filter to change role display name
		add_filter( 'wp_roles', array( __CLASS__, 'change_role_display_name' ) );
	}

	/**
	 * Change role display name for employee to مشتری.
	 *
	 * @param \WP_Roles $wp_roles The WP_Roles object.
	 * @return \WP_Roles
	 */
	public static function change_role_display_name( $wp_roles ) {
		if ( isset( $wp_roles->roles['employee'] ) ) {
			$wp_roles->roles['employee']['name'] = 'مشتری';
		}
		return $wp_roles;
	}

	/**
	 * Remove custom user roles.
	 */
	public static function remove_roles() {
		remove_role( 'company' );
		remove_role( 'merchant' );
		remove_role( 'employee' );
		remove_role( 'finance_officer' );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( 'manage_wallets' );
			$admin_role->remove_cap( 'approve_payouts' );
		}
	}
}

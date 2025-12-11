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
		add_role( 'employee', 'Employee', array( 'read' => true ) );
		add_role( 'finance_officer', 'Finance Officer', array( 'read' => true, 'approve_payouts' => true ) );

		$admin_role = get_role( 'administrator' );
		$admin_role->add_cap( 'manage_wallets' );
		$admin_role->add_cap( 'approve_payouts' );
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

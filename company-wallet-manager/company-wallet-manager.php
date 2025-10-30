<?php
/**
 * Plugin Name:       Company Wallet Manager
 * Plugin URI:        https://example.com/
 * Description:       A 3-level wallet management system for companies, merchants, and employees integrated with WooCommerce.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       company-wallet-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants
 */
define( 'CWM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Require the Composer autoloader.
require_once CWM_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Register the activation and deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'CWM\\Plugin_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CWM\\Plugin_Loader', 'deactivate' ) );

/**
 * Initialize the settings page
 */
if ( is_admin() ) {
    new CWM\Settings_Page();
}

/**
 * Initialize the API handler
 */
new CWM\API_Handler();

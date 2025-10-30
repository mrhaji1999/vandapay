<?php
/**
 * Plugin Name:       Company Wallet Manager
 * Plugin URI:        https://example.com/
 * Description:       A 3-level wallet management system for companies, merchants, and employees integrated with WooCommerce.
 * Version:           1.0.1
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
 * Initialize the plugin.
 *
 * This function is hooked to `plugins_loaded` to ensure that all classes
 * are available and the plugin is loaded at the correct time.
 */
function cwm_plugin_init() {
	// Initialize the settings page
	if ( is_admin() ) {
		new CWM\Settings_Page();
	}

	// Initialize the API handler
	new CWM\API_Handler();
}
add_action( 'plugins_loaded', 'cwm_plugin_init' );


/**
 * Register the shortcode to display the React app.
 */
function cwm_register_shortcode() {
    add_shortcode( 'cwm_dashboard', 'cwm_render_react_app' );
}
add_action( 'init', 'cwm_register_shortcode' );

/**
 * Render the React app.
 */
function cwm_render_react_app() {
    // Enqueue the script.
    wp_enqueue_script(
        'cwm-react-app',
        plugin_dir_url( __FILE__ ) . 'assets/js/ui-bundle.js',
        array(),
        '1.0.1', // Updated version
        true
    );

    // Provide a root element for the React app.
    return '<div id="root"></div>';
}

<?php
/**
 * Plugin Name:       Company Wallet Manager
 * Plugin URI:        https://example.com/
 * Description:       A 3-level wallet management system for companies, merchants, and employees integrated with WooCommerce.
 * Version:           1.0.3
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
 * Manually include the Plugin_Loader class.
 * This is necessary because the class is needed during activation,
 * a time when the Composer autoloader may not be fully available.
 */
require_once CWM_PLUGIN_DIR . 'includes/class-plugin-loader.php';

/**
 * The callback function for plugin activation.
 */
function cwm_activate_plugin() {
    CWM\Plugin_Loader::activate();
}

/**
 * The callback function for plugin deactivation.
 */
function cwm_deactivate_plugin() {
    CWM\Plugin_Loader::deactivate();
}

register_activation_hook( __FILE__, 'cwm_activate_plugin' );
register_deactivation_hook( __FILE__, 'cwm_deactivate_plugin' );


/**
 * Initialize the plugin.
 */
function cwm_plugin_init() {
	if ( is_admin() ) {
		new CWM\Settings_Page();
	}
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
    wp_enqueue_script(
        'cwm-react-app',
        plugin_dir_url( __FILE__ ) . 'assets/js/ui-bundle.js',
        array(),
        '1.0.3', // Updated version
        true
    );
    return '<div id="root"></div>';
}

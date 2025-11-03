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
require_once CWM_PLUGIN_DIR . 'includes/api/class-cwm-employee-controller.php';

/**
 * The callback function for plugin activation.
 *
 * This function calls the activate method on the Plugin_Loader class.
 * Using a wrapper function prevents a fatal error if the class is not found.
 */
function cwm_activate_plugin() {
    CWM\Plugin_Loader::activate();
}

/**
 * The callback function for plugin deactivation.
 *
 * This function calls the deactivate method on the Plugin_Loader class.
 */
function cwm_deactivate_plugin() {
    CWM\Plugin_Loader::deactivate();
}

register_activation_hook( __FILE__, 'cwm_activate_plugin' );
register_deactivation_hook( __FILE__, 'cwm_deactivate_plugin' );


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

        // Custom post type registration.
        new CWM\Post_Types_Manager();

        // Initialize the CORS manager so external dashboards can reach the REST API.
        new CWM\CORS_Manager();

        // Initialize the API handler
        new CWM\API_Handler();

        // Register employee specific API endpoints.
        new CWM\API\CWM_Employee_Controller();
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
        '1.0.3',
        true
    );

    // Provide a root element for the React app.
    return '<div id="root"></div>';
}
add_action('rest_api_init', function () {
    register_rest_route('cwm/v1', '/token', [
        'methods'  => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (\WP_REST_Request $request) {
            $username = $request->get_param('username');
            $password = $request->get_param('password');

            // ریکوئست رو شبیه ریکوئست jwt-auth می‌کنیم
            $jwt_request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
            $jwt_request->set_param('username', $username);
            $jwt_request->set_param('password', $password);

            // حالا رانش می‌کنیم
            $response = rest_do_request($jwt_request);

            // همون رو برگردون
            return $response;
        },
    ]);
});

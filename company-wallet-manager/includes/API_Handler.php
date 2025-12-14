<?php

namespace CWM;

use CWM\API\Category_Controller;
use CWM\API\Company_Category_Cap_Controller;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class API_Handler
 *
 * @package CWM
 */
class API_Handler {

    /**
     * The namespace for the API.
     *
     * @var string
     */
    protected $namespace = 'cwm/v1';

    /**
     * Handles company registrations.
     *
     * @var Company_Registration
     */
    protected $company_registration;

    /**
     * Handles merchant registrations.
     *
     * @var Merchant_Registration
     */
    protected $merchant_registration;

    /**
     * Handles category and allowance persistence.
     *
     * @var Category_Manager
     */
    protected $category_manager;

    /**
     * REST controller for category operations.
     *
     * @var Category_Controller
     */
    protected $category_controller;

    /**
     * REST controller for company caps.
     *
     * @var Company_Category_Cap_Controller
     */
    protected $company_cap_controller;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->category_manager        = new Category_Manager();
        $this->category_controller     = new Category_Controller( $this->category_manager );
        $this->company_cap_controller  = new Company_Category_Cap_Controller( $this->category_manager );
        $this->company_registration    = new Company_Registration();
        $this->merchant_registration   = new Merchant_Registration( $this->category_manager );

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'rest_api_init', array( $this, 'configure_cors_support' ), 15 );
    }

    /**
     * Configure CORS headers for the plugin's REST namespace.
     */
    public function configure_cors_support() {
        // CORS is handled by CORS_Manager, but we keep this for backward compatibility
        add_filter( 'rest_pre_serve_request', array( $this, 'send_cors_headers' ), 0, 4 );
    }

    /**
     * Send CORS headers for requests hitting the plugin namespace.
     *
     * @param bool                   $served  Whether the request has already been served.
     * @param \WP_HTTP_Response      $result  Result to send to the client. Usually a \WP_REST_Response.
     * @param \WP_REST_Request       $request The request object.
     * @param \WP_REST_Server        $server  Server instance.
     *
     * @return bool
     */
    public function send_cors_headers( $served, $result, $request, $server ) {
        unset( $result, $server );

        $route = $request->get_route();

        if ( 0 !== strpos( $route, '/' . $this->namespace ) ) {
            return $served;
        }

        $origin           = get_http_origin();
        $allowed_origins  = $this->get_allowed_cors_origins();
        $sanitized_origin = $origin && in_array( $origin, $allowed_origins, true ) ? esc_url_raw( $origin ) : esc_url_raw( get_site_url() );

        header( 'Access-Control-Allow-Origin: ' . $sanitized_origin );
        header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, Content-Disposition' );

        if ( 'OPTIONS' === $request->get_method() ) {
            status_header( 200 );
            return true;
        }

        return $served;
    }


    /**
     * Return the list of allowed CORS origins.
     *
     * @return array
     */
    protected function get_allowed_cors_origins() {
        $origins = array( get_site_url() );

        // Add panel subdomain if main domain is configured
        $site_url = get_site_url();
        $parsed = wp_parse_url( $site_url );
        if ( ! empty( $parsed['host'] ) ) {
            $host = $parsed['host'];
            // If main domain is like mr.vandapay.com, add panel.vandapay.com
            if ( strpos( $host, 'mr.' ) === 0 ) {
                $panel_host = str_replace( 'mr.', 'panel.', $host );
                $panel_url = $parsed['scheme'] . '://' . $panel_host;
                if ( ! empty( $parsed['port'] ) ) {
                    $panel_url .= ':' . $parsed['port'];
                }
                $origins[] = $panel_url;
            }
        }

        /**
         * Filter the allowed CORS origins for Company Wallet Manager REST requests.
         *
         * @since 1.0.0
         *
         * @param string[] $origins Array of allowed origins.
         */
        $filtered = apply_filters( 'cwm_allowed_cors_origins', $origins );

        if ( ! is_array( $filtered ) ) {
            return $origins;
        }

        return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $filtered ) ) ) );
    }

    /**
     * Validate that a request argument is a string.
     *
     * @param mixed            $value   Value to validate.
     * @param WP_REST_Request  $request Current request.
     * @param string           $param   Parameter name.
     *
     * @return bool
     */
    public function validate_string( $value, $request = null, $param = '' ) {
        unset( $request, $param );

        return is_string( $value );
    }

    /**
     * Validate that a request argument is numeric.
     *
     * @param mixed            $value   Value to validate.
     * @param WP_REST_Request  $request Current request.
     * @param string           $param   Parameter name.
     *
     * @return bool
     */
    public function validate_numeric( $value, $request = null, $param = '' ) {
        unset( $request, $param );

        return is_numeric( $value );
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/public/company/register', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_company_registration' ],
                'permission_callback' => '__return_true',
            ],
        ] );

        register_rest_route( $this->namespace, '/public/merchant/register', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_merchant_registration' ],
                'permission_callback' => '__return_true',
            ],
        ] );

        register_rest_route( $this->namespace, '/token', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'generate_token' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'username' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                    'password' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/token/refresh', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'refresh_token' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'refresh_token' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payment/request', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'request_payment' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
                'args'                => [
                    'employee_national_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                    'category_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                    'amount' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payment/preview', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'preview_payment' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
                'args'                => [
                    'employee_national_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                    'category_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payment/confirm', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'confirm_payment' ],
                'permission_callback' => [ $this, 'merchant_or_employee_permission_check' ],
                'args'                => [
                    'request_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                    'otp_code' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payment/pending', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_pending_payment_requests' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/wallet/balance', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_wallet_balance' ],
                'permission_callback' => [ $this, 'any_authenticated_user_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/profile', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_profile' ],
                'permission_callback' => [ $this, 'any_authenticated_user_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/wallet/charge', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'charge_wallet' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
                'args'                => [
                    'user_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                    'amount' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/categories', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_categories' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_new_category' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/merchant/categories', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_merchant_categories_route' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_merchant_categories' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
                'args'                => [
                    'category_ids' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_array( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/employee/category-balances', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_employee_category_balances' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/employee/merchants', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_employee_merchants' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/employee/merchants/(?P<id>\d+)/products', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_merchant_products' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/employee/products/search', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'search_all_products' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/merchant/revenue-stats', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_merchant_revenue_stats' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/merchant/profile', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_merchant_profile' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_merchant_profile' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/company/employees/(?P<employee_id>\d+)/limits', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_company_employee_limits' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_company_employee_limits' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payout/request', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'request_payout' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
                'args'                => [
                    'amount' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_numeric( $value );
                        },
                    ],
                    'bank_account' => [
                        'required'          => true,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payout/status', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_payout_status' ],
                'permission_callback' => [ $this, 'merchant_or_finance_officer_permission_check' ],
                'args'                => [
                    'status' => [
                        'required'          => false,
                        'validate_callback' => function( $value, $request, $param ) {
                            unset( $request, $param );
                            return is_string( $value );
                        },
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/transactions/history', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_transaction_history' ],
                'permission_callback' => [ $this, 'any_authenticated_user_permission_check' ],
            ],
        ] );

        // Product Categories Routes (shared, visible to all merchants)
        register_rest_route( $this->namespace, '/product-categories', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_product_categories' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_product_category' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        // Products Routes
        register_rest_route( $this->namespace, '/merchant/products', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_merchant_products' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_product' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/merchant/products/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_product' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'update_product' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_product' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        // Cart Routes
        register_rest_route( $this->namespace, '/cart', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_cart' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/cart/add', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'add_to_cart' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/cart/remove/(?P<id>\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'remove_from_cart' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/cart/update/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'update_cart_item' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/cart/clear', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'clear_cart' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        // Orders Routes
        register_rest_route( $this->namespace, '/orders', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_orders' ],
                'permission_callback' => [ $this, 'any_authenticated_user_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_order' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/orders/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_order' ],
                'permission_callback' => [ $this, 'any_authenticated_user_permission_check' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'update_order_status' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/merchant/orders', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_merchant_orders' ],
                'permission_callback' => [ $this, 'merchant_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/employee/orders', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_employee_orders' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        // Online purchase enabled products for employees
        register_rest_route( $this->namespace, '/employee/products/online', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_online_products' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
            ],
        ] );

        // Company credit management
        register_rest_route( $this->namespace, '/company/credit', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_company_credit' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        // Company employee credit allocation via Excel
        register_rest_route( $this->namespace, '/company/employees/allocate-credit', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'allocate_employee_credit_from_excel' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        // Company employee import via CSV
        register_rest_route( $this->namespace, '/company/employees/import', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'import_company_employees' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        // Company employees list
        register_rest_route( $this->namespace, '/company/employees', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_company_employees' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        $this->register_admin_routes();

        $this->category_controller->register_routes();
        $this->company_cap_controller->register_routes();
    }

    /**
     * Delegate company registration.
     */
    public function handle_company_registration( WP_REST_Request $request ) {
        return $this->company_registration->register( $request );
    }

    /**
     * Delegate merchant registration.
     */
    public function handle_merchant_registration( WP_REST_Request $request ) {
        return $this->merchant_registration->register( $request );
    }

    /**
     * Register admin management endpoints.
     */
    protected function register_admin_routes() {
        register_rest_route( $this->namespace, '/admin/companies', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_companies' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'admin_create_company' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/companies/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'admin_update_company' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'admin_delete_company' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/companies/(?P<id>\d+)/credit', [
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'admin_update_company_credit' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/companies/(?P<id>\d+)/employees', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_company_employees' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/companies/(?P<id>\d+)/employees/import', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'import_admin_company_employees' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/merchants', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_merchants' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/merchants/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'admin_update_merchant' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'admin_delete_merchant' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/users/(?P<id>\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'admin_delete_user' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/products', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_products' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/products/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'update_product' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_product' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/merchants/(?P<id>\d+)/categories', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_merchant_categories' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_admin_merchant_categories' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/payouts', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_payouts' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/transactions', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_transactions' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/stats', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_stats' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/reports/orders', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_order_reports' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/admin/reports/products', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_product_reports' ],
                'permission_callback' => [ $this, 'admin_permission_check' ],
            ],
        ] );

        // Company category caps management
        register_rest_route( $this->namespace, '/company/category-caps', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_company_category_caps' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_company_category_caps' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        // Company reports - top merchants
        register_rest_route( $this->namespace, '/company/reports/top-merchants', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_company_top_merchants' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        // Company employee online purchase access
        register_rest_route( $this->namespace, '/company/employees/(?P<employee_id>\d+)/online-access', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_employee_online_access' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_employee_online_access' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/company/reports/employees', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_company_employee_reports' ],
                'permission_callback' => [ $this, 'company_permission_check' ],
            ],
        ] );
    }

    /**
     * Get the transaction history for the current user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_transaction_history( WP_REST_Request $request ) {
        global $wpdb;

        $user_id   = get_current_user_id();
        $per_page  = min( 200, max( 1, (int) $request->get_param( 'per_page' ) ?: 50 ) );
        $table     = $wpdb->prefix . 'cwm_transactions';
        $results   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE (sender_id = %d OR receiver_id = %d) ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $user_id,
                $per_page
            ),
            ARRAY_A
        );

        return rest_ensure_response(
            [
                'status' => 'success',
                'count'  => count( $results ),
                'data'   => $results,
            ]
        );
    }

    /**
     * Get the status of all payout requests.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_payout_status( WP_REST_Request $request ) {
        global $wpdb;

        $user       = wp_get_current_user();
        $table      = $wpdb->prefix . 'cwm_payout_requests';
        $where      = '1=1';
        $params     = [];

        if ( $this->user_has_role( $user, 'merchant' ) ) {
            $where   .= ' AND merchant_id = %d';
            $params[] = $user->ID;
        }

        if ( $status = $request->get_param( 'status' ) ) {
            $where   .= ' AND status = %s';
            $params[] = sanitize_text_field( $status );
        }

        $sql     = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC";
        $results = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        return rest_ensure_response(
            [
                'status' => 'success',
                'count'  => count( $results ),
                'data'   => $results,
            ]
        );
    }

    /**
     * Request a payout for a merchant.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function request_payout( WP_REST_Request $request ) {
        global $wpdb;

        $merchant_id  = get_current_user_id();
        $amount       = (float) $request->get_param( 'amount' );
        $bank_account = sanitize_text_field( $request->get_param( 'bank_account' ) );

        if ( $amount <= 0 ) {
            return new WP_Error( 'cwm_invalid_amount', __( 'Amount must be greater than zero.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $wallet_system = new Wallet_System();
        $balance       = $wallet_system->get_balance( $merchant_id );

        if ( $balance < $amount ) {
            return new WP_Error( 'insufficient_funds', __( 'Insufficient funds.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $table   = $wpdb->prefix . 'cwm_payout_requests';
        $insert  = $wpdb->insert(
            $table,
            [
                'merchant_id'  => $merchant_id,
                'amount'       => $amount,
                'bank_account' => $bank_account,
                'status'       => 'pending',
            ]
        );

        if ( false === $insert ) {
            return new WP_Error( 'cwm_payout_error', __( 'Unable to create payout request.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        $request_id = (int) $wpdb->insert_id;

        $wallet_system->update_balance( $merchant_id, -$amount );

        $logger = new Transaction_Logger();
        $logger->log( 'payout_request', $merchant_id, 0, $amount, 'pending', [
            'related_request' => $request_id,
            'context'         => 'payout',
            'metadata'        => [ 'bank_account' => $bank_account ],
        ] );

        wp_insert_post(
            [
                'post_type'   => 'cwm_payout',
                'post_status' => 'pending',
                'post_title'  => sprintf( __( 'Payout #%d', 'company-wallet-manager' ), $request_id ),
                'meta_input'  => [
                    '_cwm_payout_request_id' => $request_id,
                    '_cwm_payout_amount'     => $amount,
                    '_cwm_payout_merchant'   => $merchant_id,
                    '_cwm_payout_bank'       => $bank_account,
                ],
            ]
        );

        return rest_ensure_response(
            [
                'status'     => 'success',
                'message'    => __( 'Payout request created.', 'company-wallet-manager' ),
                'request_id' => $request_id,
                'balance'    => $wallet_system->get_balance( $merchant_id ),
            ]
        );
    }

    /**
     * Charge a user's wallet.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function charge_wallet( WP_REST_Request $request ) {
        $user_id = (int) $request->get_param( 'user_id' );
        $amount  = (float) $request->get_param( 'amount' );

        if ( 0 === $user_id || 0.0 === $amount ) {
            return new WP_Error( 'cwm_invalid_parameters', __( 'User ID and amount are required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $wallet = new Wallet_System();

        if ( ! $wallet->update_balance( $user_id, $amount ) ) {
            return new WP_Error( 'cwm_wallet_update_failed', __( 'Unable to update wallet balance.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        $new_balance = $wallet->get_balance( $user_id );

        $logger = new Transaction_Logger();
        $logger->log( 'charge', get_current_user_id(), $user_id, $amount, 'completed', [ 'context' => 'manual_charge' ] );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'user_id'     => $user_id,
                    'new_balance' => $new_balance,
                ],
            ]
        );
    }

    /**
     * Get the wallet balance for the current user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_wallet_balance( WP_REST_Request $request ) {
        unset( $request );

        $user_id = get_current_user_id();
        $wallet  = new Wallet_System();

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'user_id' => $user_id,
                    'balance' => $wallet->get_balance( $user_id ),
                ],
            ]
        );
    }

    /**
     * List available merchant categories.
     */
    public function list_categories( WP_REST_Request $request ) {
        unset( $request );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => $this->category_manager->get_all_categories(),
            ]
        );
    }

    /**
     * Create a new merchant category.
     */
    public function create_new_category( WP_REST_Request $request ) {
        $name = $request->get_param( 'name' );

        $created = $this->category_manager->create_category( $name );
        if ( is_wp_error( $created ) ) {
            return $created;
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'id' => $created,
                ],
            ]
        );
    }

    /**
     * Return the categories assigned to the authenticated merchant.
     */
    public function get_merchant_categories_route( WP_REST_Request $request ) {
        unset( $request );

        $merchant_id = get_current_user_id();

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'assigned'  => $this->category_manager->get_merchant_categories( $merchant_id ),
                    'available' => $this->category_manager->get_all_categories(),
                ],
            ]
        );
    }

    /**
     * Update the category assignments for the authenticated merchant.
     */
    public function update_merchant_categories( WP_REST_Request $request ) {
        $category_ids = $request->get_param( 'category_ids' );

        if ( ! is_array( $category_ids ) ) {
            return new WP_Error( 'cwm_invalid_categories', __( 'Category IDs must be an array.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $available = wp_list_pluck( $this->category_manager->get_all_categories(), 'id' );
        $category_ids = array_values( array_intersect( array_map( 'absint', $category_ids ), $available ) );

        $this->category_manager->sync_merchant_categories( get_current_user_id(), $category_ids );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [ 'assigned' => $this->category_manager->get_merchant_categories( get_current_user_id() ) ],
            ]
        );
    }

    /**
     * Return category allowance cards for the authenticated employee.
     */
    public function get_employee_category_balances( WP_REST_Request $request ) {
        unset( $request );

        $employee_id = get_current_user_id();
        $wallet      = new Wallet_System();
        $balance     = $wallet->get_balance( $employee_id );

        $all_categories = $this->category_manager->get_all_categories();
        $limits         = $this->category_manager->get_employee_limits( $employee_id );

        $indexed_limits = [];
        foreach ( $limits as $limit ) {
            $indexed_limits[ $limit['category_id'] ] = $limit;
        }

        $categories = [];
        foreach ( $all_categories as $category ) {
            $entry = isset( $indexed_limits[ $category['id'] ] ) ? $indexed_limits[ $category['id'] ] : null;

            $limit = $entry ? (float) $entry['limit'] : 0.0;
            $spent = $entry ? (float) $entry['spent'] : 0.0;
            $remaining = max( 0.0, $limit - $spent );

            $categories[] = [
                'category_id'   => (int) $category['id'],
                'category_name' => $category['name'],
                'limit'         => $limit,
                'spent'         => $spent,
                'remaining'     => $remaining,
            ];
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'wallet_balance' => $balance,
                    'categories'     => $categories,
                ],
            ]
        );
    }

    /**
     * Get list of merchants for employees.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_employee_merchants( WP_REST_Request $request ) {
        $province_id = $request->get_param( 'province_id' );
        $city_id     = $request->get_param( 'city_id' );

        $args = [ 'role' => 'merchant' ];
        $merchants = get_users( $args );
        $result    = [];

        foreach ( $merchants as $merchant ) {
            $merchant_province_id = (int) get_user_meta( $merchant->ID, '_cwm_province_id', true );
            $merchant_city_id     = (int) get_user_meta( $merchant->ID, '_cwm_city_id', true );

            // Apply filters
            if ( $province_id && $merchant_province_id !== (int) $province_id ) {
                continue;
            }
            if ( $city_id && $merchant_city_id !== (int) $city_id ) {
                continue;
            }

            $store_name        = get_user_meta( $merchant->ID, '_cwm_store_name', true );
            $store_address     = get_user_meta( $merchant->ID, '_cwm_store_address', true );
            $store_description = get_user_meta( $merchant->ID, '_cwm_store_description', true );
            $store_image       = get_user_meta( $merchant->ID, '_cwm_store_image', true );
            $phone             = get_user_meta( $merchant->ID, 'cwm_phone', true );

            $result[] = [
                'id'                => $merchant->ID,
                'name'              => $merchant->display_name,
                'store_name'        => $store_name ?: $merchant->display_name,
                'store_description' => $store_description ?: '',
                'store_image'       => $store_image ?: '',
                'store_address'     => $store_address ?: '',
                'phone'             => $phone ?: '',
                'email'             => $merchant->user_email,
                'province_id'       => $merchant_province_id ?: null,
                'city_id'           => $merchant_city_id ?: null,
            ];
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => $result,
            ]
        );
    }

    /**
     * Get products for a specific merchant.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_merchant_products( WP_REST_Request $request ) {
        global $wpdb;
        $merchant_id = (int) $request->get_param( 'id' );

        if ( ! $merchant_id ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'شناسه پذیرنده معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $products_table = $wpdb->prefix . 'cwm_products';
        $categories_table = $wpdb->prefix . 'cwm_product_categories';
        
        // Ensure products table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$products_table}'" );
        if ( $products_table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$products_table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        } else {
            // Table exists, ensure status column exists
            $status_column = $wpdb->get_results( "SHOW COLUMNS FROM {$products_table} LIKE 'status'" );
            if ( empty( $status_column ) ) {
                $wpdb->query( "ALTER TABLE {$products_table} ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER is_featured" );
                $wpdb->query( "ALTER TABLE {$products_table} ADD KEY status (status)" );
                // Update existing products with NULL or empty status to 'active'
                $wpdb->query( "UPDATE {$products_table} SET status = 'active' WHERE status IS NULL OR status = ''" );
            } else {
                // Ensure existing products with NULL or empty status are set to 'active'
                $wpdb->query( $wpdb->prepare( "UPDATE {$products_table} SET status = 'active' WHERE (status IS NULL OR status = '') AND merchant_id = %d", $merchant_id ) );
            }
        }

        // Update any products with NULL or empty status to 'active' before querying
        $wpdb->query( $wpdb->prepare( "UPDATE {$products_table} SET status = 'active' WHERE (status IS NULL OR status = '') AND merchant_id = %d", $merchant_id ) );

        // Check if categories table exists
        $categories_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$categories_table}'" );
        
        // Build the query - always use simple query first to ensure we get products
        // Then enhance with category info if categories table exists
        $products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$products_table} WHERE merchant_id = %d AND status = %s ORDER BY created_at DESC",
                $merchant_id,
                'active'
            ),
            ARRAY_A
        );
        
        // If we have products and categories table exists, add category info
        if ( ! empty( $products ) && $categories_table === $categories_table_exists ) {
            // Get category info for products that have category_id
            $category_ids = array_filter( array_column( $products, 'product_category_id' ) );
            if ( ! empty( $category_ids ) ) {
                $category_ids_placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
                $categories = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, name, slug FROM {$categories_table} WHERE id IN ({$category_ids_placeholders})",
                        ...$category_ids
                    ),
                    ARRAY_A
                );
                
                // Create a map of category_id => category info
                $category_map = [];
                foreach ( $categories as $cat ) {
                    $category_map[ $cat['id'] ] = [
                        'name' => $cat['name'],
                        'slug' => $cat['slug']
                    ];
                }
                
                // Add category info to products
                foreach ( $products as &$product ) {
                    $cat_id = $product['product_category_id'] ?? null;
                    if ( $cat_id && isset( $category_map[ $cat_id ] ) ) {
                        $product['category_name'] = $category_map[ $cat_id ]['name'];
                        $product['category_slug'] = $category_map[ $cat_id ]['slug'];
                    } else {
                        $product['category_name'] = null;
                        $product['category_slug'] = null;
                    }
                }
                unset( $product );
            } else {
                // No categories, add null fields
                foreach ( $products as &$product ) {
                    $product['category_name'] = null;
                    $product['category_slug'] = null;
                }
                unset( $product );
            }
        } elseif ( ! empty( $products ) ) {
            // Categories table doesn't exist, add null fields
            foreach ( $products as &$product ) {
                $product['category_name'] = null;
                $product['category_slug'] = null;
            }
            unset( $product );
        }

        // Ensure products is an array
        $products_array = is_array( $products ) ? $products : [];
        
        error_log( sprintf( 'CWM Debug get_merchant_products: Returning %d products for merchant_id %d', count( $products_array ), $merchant_id ) );
        
        $response_data = [
            'status' => 'success',
            'data'   => array_map( function( $product ) {
                return [
                    'id'                      => (int) $product['id'],
                    'merchant_id'             => (int) $product['merchant_id'],
                    'product_category_id'     => $product['product_category_id'] ? (int) $product['product_category_id'] : null,
                    'category_name'           => $product['category_name'] ?? null,
                    'category_slug'           => $product['category_slug'] ?? null,
                    'name'                    => $product['name'],
                    'description'             => $product['description'] ?? '',
                    'price'                   => (float) $product['price'],
                    'image'                   => $product['image'] ?? null,
                    'stock_quantity'          => (int) $product['stock_quantity'],
                    'online_purchase_enabled' => (bool) $product['online_purchase_enabled'],
                    'is_featured'             => isset( $product['is_featured'] ) ? (bool) $product['is_featured'] : false,
                    'status'                  => $product['status'],
                    'created_at'              => $product['created_at'],
                    'updated_at'              => $product['updated_at'],
                ];
            }, $products_array ),
        ];
        
        error_log( sprintf( 'CWM Debug get_merchant_products: Response data structure - status: %s, data count: %d', $response_data['status'], count( $response_data['data'] ) ) );
        
        return rest_ensure_response( $response_data );
    }

    /**
     * Get merchant profile.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_merchant_profile( WP_REST_Request $request ) {
        unset( $request );

        $merchant_id = get_current_user_id();
        $merchant    = get_userdata( $merchant_id );

        if ( ! $merchant ) {
            return new WP_Error( 'cwm_merchant_not_found', __( 'پذیرنده یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $profile = [
            'store_name'        => get_user_meta( $merchant_id, '_cwm_store_name', true ),
            'store_address'     => get_user_meta( $merchant_id, '_cwm_store_address', true ),
            'store_description' => get_user_meta( $merchant_id, '_cwm_store_description', true ),
            'store_slogan'      => get_user_meta( $merchant_id, '_cwm_store_slogan', true ),
            'store_image'       => get_user_meta( $merchant_id, '_cwm_store_image', true ),
            'store_images'      => get_user_meta( $merchant_id, '_cwm_store_images', true ) ?: [],
            'phone'             => get_user_meta( $merchant_id, 'cwm_phone', true ),
            'mobile'            => get_user_meta( $merchant_id, 'mobile', true ),
            'email'             => $merchant->user_email,
            'province_id'       => (int) get_user_meta( $merchant_id, '_cwm_province_id', true ) ?: null,
            'city_id'           => (int) get_user_meta( $merchant_id, '_cwm_city_id', true ) ?: null,
            'products'          => get_user_meta( $merchant_id, '_cwm_products', true ) ?: [],
        ];

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => $profile,
            ]
        );
    }

    /**
     * Update merchant profile.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_merchant_profile( WP_REST_Request $request ) {
        $merchant_id = get_current_user_id();

        $store_name        = $request->get_param( 'store_name' );
        $store_address     = $request->get_param( 'store_address' );
        $store_description = $request->get_param( 'store_description' );
        $store_slogan      = $request->get_param( 'store_slogan' );
        $phone             = $request->get_param( 'phone' );
        $mobile            = $request->get_param( 'mobile' );
        $province_id       = $request->get_param( 'province_id' );
        $city_id           = $request->get_param( 'city_id' );

        if ( $store_name ) {
            update_user_meta( $merchant_id, '_cwm_store_name', sanitize_text_field( $store_name ) );
        }
        if ( $store_address ) {
            update_user_meta( $merchant_id, '_cwm_store_address', sanitize_text_field( $store_address ) );
        }
        if ( $store_description ) {
            update_user_meta( $merchant_id, '_cwm_store_description', sanitize_textarea_field( $store_description ) );
        }
        if ( $store_slogan ) {
            update_user_meta( $merchant_id, '_cwm_store_slogan', sanitize_text_field( $store_slogan ) );
        }
        if ( $phone ) {
            update_user_meta( $merchant_id, 'cwm_phone', sanitize_text_field( $phone ) );
        }
        if ( $mobile ) {
            update_user_meta( $merchant_id, 'mobile', sanitize_text_field( $mobile ) );
        }
        if ( $province_id ) {
            update_user_meta( $merchant_id, '_cwm_province_id', (int) $province_id );
        }
        if ( $city_id ) {
            update_user_meta( $merchant_id, '_cwm_city_id', (int) $city_id );
        }

        // Handle file uploads
        if ( ! empty( $_FILES['store_image'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload( $_FILES['store_image'], [ 'test_form' => false ] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                update_user_meta( $merchant_id, '_cwm_store_image', $upload['url'] );
            }
        }

        // Handle products
        $products = $request->get_param( 'products' );
        if ( is_array( $products ) ) {
            $sanitized_products = [];
            foreach ( $products as $product ) {
                $sanitized_products[] = [
                    'name'        => sanitize_text_field( $product['name'] ?? '' ),
                    'description' => sanitize_textarea_field( $product['description'] ?? '' ),
                    'price'       => isset( $product['price'] ) ? floatval( $product['price'] ) : null,
                ];
            }
            update_user_meta( $merchant_id, '_cwm_products', $sanitized_products );
        }

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'اطلاعات فروشگاه با موفقیت به‌روزرسانی شد.', 'company-wallet-manager' ),
            ]
        );
    }

    /**
     * Search all products across all merchants.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function search_all_products( WP_REST_Request $request ) {
        $search_query = $request->get_param( 'search' );

        if ( empty( $search_query ) ) {
            return rest_ensure_response(
                [
                    'status' => 'success',
                    'data'   => [],
                ]
            );
        }

        $merchants = get_users( [ 'role' => 'merchant' ] );
        $results   = [];

        foreach ( $merchants as $merchant ) {
            $products = get_user_meta( $merchant->ID, '_cwm_products', true );
            if ( ! is_array( $products ) ) {
                continue;
            }

            $store_name = get_user_meta( $merchant->ID, '_cwm_store_name', true ) ?: $merchant->display_name;

            foreach ( $products as $index => $product ) {
                $product_name = $product['name'] ?? '';
                if ( stripos( $product_name, $search_query ) !== false ) {
                    $results[] = [
                        'id'           => $index,
                        'name'         => $product_name,
                        'description'  => $product['description'] ?? '',
                        'image'        => $product['image'] ?? '',
                        'price'        => isset( $product['price'] ) ? floatval( $product['price'] ) : null,
                        'merchant_id'  => $merchant->ID,
                        'merchant_name' => $merchant->display_name,
                        'store_name'   => $store_name,
                    ];
                }
            }
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => $results,
            ]
        );
    }

    /**
     * Get revenue statistics for merchant.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_merchant_revenue_stats( WP_REST_Request $request ) {
        unset( $request );

        global $wpdb;
        $merchant_id = get_current_user_id();

        $table = $wpdb->prefix . 'cwm_transactions';

        // Daily revenue (last 30 days)
        $daily_data = [];
        for ( $i = 29; $i >= 0; $i-- ) {
            $date     = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $next_date = date( 'Y-m-d', strtotime( "-{$i} days +1 day" ) );

            $amount = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(amount) FROM {$table} 
                    WHERE receiver_id = %d 
                    AND type = 'payment' 
                    AND status = 'completed'
                    AND created_at >= %s 
                    AND created_at < %s",
                    $merchant_id,
                    $date,
                    $next_date
                )
            );

            $daily_data[] = [
                'date'   => $date,
                'amount' => floatval( $amount ?: 0 ),
            ];
        }

        // Monthly revenue (last 12 months)
        $monthly_data = [];
        for ( $i = 11; $i >= 0; $i-- ) {
            $month_start = date( 'Y-m-01', strtotime( "-{$i} months" ) );
            $month_end   = date( 'Y-m-t', strtotime( "-{$i} months" ) );

            $amount = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(amount) FROM {$table} 
                    WHERE receiver_id = %d 
                    AND type = 'payment' 
                    AND status = 'completed'
                    AND created_at >= %s 
                    AND created_at <= %s",
                    $merchant_id,
                    $month_start,
                    $month_end
                )
            );

            $monthly_data[] = [
                'date'   => date( 'Y-m', strtotime( "-{$i} months" ) ),
                'amount' => floatval( $amount ?: 0 ),
            ];
        }

        // Yearly revenue (last 5 years)
        $yearly_data = [];
        for ( $i = 4; $i >= 0; $i-- ) {
            $year_start = date( 'Y-01-01', strtotime( "-{$i} years" ) );
            $year_end   = date( 'Y-12-31', strtotime( "-{$i} years" ) );

            $amount = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(amount) FROM {$table} 
                    WHERE receiver_id = %d 
                    AND type = 'payment' 
                    AND status = 'completed'
                    AND created_at >= %s 
                    AND created_at <= %s",
                    $merchant_id,
                    $year_start,
                    $year_end
                )
            );

            $yearly_data[] = [
                'date'   => date( 'Y', strtotime( "-{$i} years" ) ),
                'amount' => floatval( $amount ?: 0 ),
            ];
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'daily'   => $daily_data,
                    'monthly' => $monthly_data,
                    'yearly'  => $yearly_data,
                ],
            ]
        );
    }

    /**
     * Generate a JWT token for the user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function generate_token( WP_REST_Request $request ) {
        $username = $request->get_param( 'username' );
        $password = $request->get_param( 'password' );

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'invalid_credentials', __( 'Invalid username or password.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $access  = $this->issue_access_token( $user->ID );
        if ( is_wp_error( $access ) ) {
            return $access;
        }

        $refresh = $this->issue_refresh_token( $user->ID );
        if ( is_wp_error( $refresh ) ) {
            return $refresh;
        }

        return rest_ensure_response(
            [
                'token'         => $access['token'],
                'expires_in'    => $access['expires_in'],
                'refresh_token' => $refresh['token'],
                'refresh_expires_in' => $refresh['expires_in'],
            ]
        );
    }

    /**
     * Return profile data for the authenticated user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_profile( $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return new \WP_Error( 'jwt_auth_invalid_token', 'Invalid token provided.', array( 'status' => 403 ) );
        }

        $user = wp_get_current_user();

        if ( ! $user || 0 === $user->ID ) {
            return new \WP_Error( 'rest_user_invalid', 'Authenticated user could not be determined.', array( 'status' => 404 ) );
        }

        $roles        = array_values( (array) $user->roles );
        $primary_role = ! empty( $roles ) ? $roles[0] : null;
        // Use allcaps so role-based capabilities (like administrator defaults) are included.
        $capabilities = array_keys( array_filter( (array) $user->allcaps ) );

        if ( in_array( 'administrator', $roles, true ) && ! in_array( 'manage_wallets', $capabilities, true ) ) {
            $capabilities[] = 'manage_wallets';
        }

        $this->debug_log(
            'Resolved profile data',
            [
                'user_id'      => $user->ID,
                'roles'        => $roles,
                'capabilities' => $capabilities,
            ]
        );

        $data = array(
            'id'           => (int) $user->ID,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'name'         => $user->display_name,
            'role'         => $primary_role,
            'roles'        => $roles,
            'capabilities' => $capabilities,
        );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => $data,
            ]
        );
    }

    /**
     * Refresh the JWT token using a long-lived refresh token.
     */
    public function refresh_token( WP_REST_Request $request ) {
        $token = trim( (string) $request->get_param( 'refresh_token' ) );

        if ( empty( $token ) || false === strpos( $token, '.' ) ) {
            return new WP_Error( 'cwm_invalid_refresh_token', __( 'Refresh token is invalid.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        list( $user_id_part ) = explode( '.', $token, 2 );
        $user_id = absint( $user_id_part );

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'cwm_invalid_refresh_user', __( 'Refresh token is invalid.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $hash    = get_user_meta( $user_id, 'cwm_refresh_token_hash', true );
        $expires = (int) get_user_meta( $user_id, 'cwm_refresh_token_expiration', true );

        if ( empty( $hash ) || empty( $expires ) ) {
            return new WP_Error( 'cwm_refresh_token_expired', __( 'Refresh token has expired.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        if ( time() > $expires ) {
            return new WP_Error( 'cwm_refresh_token_expired', __( 'Refresh token has expired.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        if ( ! wp_check_password( $token, $hash, $user_id ) ) {
            return new WP_Error( 'cwm_invalid_refresh_token', __( 'Refresh token is invalid.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $access  = $this->issue_access_token( $user_id );
        if ( is_wp_error( $access ) ) {
            return $access;
        }

        $refresh = $this->issue_refresh_token( $user_id );
        if ( is_wp_error( $refresh ) ) {
            return $refresh;
        }

        return rest_ensure_response(
            [
                'token'             => $access['token'],
                'expires_in'        => $access['expires_in'],
                'refresh_token'     => $refresh['token'],
                'refresh_expires_in'=> $refresh['expires_in'],
            ]
        );
    }

    /**
     * Retrieve category limits for an employee within the company's context.
     */
    public function get_company_employee_limits( WP_REST_Request $request ) {
        $employee_id = (int) $request->get_param( 'employee_id' );
        if ( $employee_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_employee', __( 'Invalid employee identifier.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $company_id = $this->resolve_employee_company_context( $employee_id );
        if ( is_wp_error( $company_id ) ) {
            return $company_id;
        }

        $all_categories = $this->category_manager->get_all_categories();
        $limits         = $this->category_manager->get_employee_limits( $employee_id );

        $indexed = [];
        foreach ( $limits as $limit ) {
            $indexed[ $limit['category_id'] ] = $limit;
        }

        $categories = [];
        foreach ( $all_categories as $category ) {
            $entry = isset( $indexed[ $category['id'] ] ) ? $indexed[ $category['id'] ] : null;
            $limit = $entry ? (float) $entry['limit'] : 0.0;
            $spent = $entry ? (float) $entry['spent'] : 0.0;
            $remaining = max( 0.0, $limit - $spent );

            $categories[] = [
                'category_id'   => (int) $category['id'],
                'category_name' => $category['name'],
                'limit'         => $limit,
                'spent'         => $spent,
                'remaining'     => $remaining,
            ];
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'employee_id' => $employee_id,
                    'company_id'  => $company_id,
                    'categories'  => $categories,
                ],
            ]
        );
    }

    /**
     * Update the per-category limits for an employee.
     */
    public function update_company_employee_limits( WP_REST_Request $request ) {
        $employee_id = (int) $request->get_param( 'employee_id' );
        if ( $employee_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_employee', __( 'Invalid employee identifier.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $company_id = $this->resolve_employee_company_context( $employee_id );
        if ( is_wp_error( $company_id ) ) {
            return $company_id;
        }

        $payload = $request->get_json_params();
        $limits  = [];

        if ( isset( $payload['limits'] ) && is_array( $payload['limits'] ) ) {
            $limits = $payload['limits'];
        } elseif ( is_array( $payload ) && isset( $payload[0] ) ) {
            $limits = $payload;
        } elseif ( is_array( $request->get_param( 'limits' ) ) ) {
            $limits = $request->get_param( 'limits' );
        }

        if ( ! is_array( $limits ) ) {
            return new WP_Error( 'cwm_invalid_limits', __( 'Category limits payload is invalid.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $sanitized = [];
        foreach ( $limits as $limit ) {
            if ( ! is_array( $limit ) ) {
                continue;
            }

            $category_id = isset( $limit['category_id'] ) ? absint( $limit['category_id'] ) : 0;
            $amount      = isset( $limit['limit'] ) ? (float) $limit['limit'] : 0.0;

            if ( $category_id <= 0 ) {
                continue;
            }

            if ( $amount < 0 ) {
                $amount = 0.0;
            }

            $sanitized[] = [
                'category_id' => $category_id,
                'limit'       => $amount,
            ];
        }

        $this->category_manager->set_employee_limits( $employee_id, $sanitized, $company_id );

        return $this->get_company_employee_limits( $request );
    }

    /**
     * Admin endpoint: list companies.
     */
    public function get_admin_companies( WP_REST_Request $request ) {
        $args  = [
            'post_type'      => 'cwm_company',
            'posts_per_page' => 200,
            'post_status'    => [ 'pending', 'publish', 'draft', 'private' ],
        ];

        $query = new WP_Query( $args );
        $rows  = [];

        foreach ( $query->posts as $post ) {
            $rows[] = [
                'id'             => $post->ID,
                'title'          => $post->post_title,
                'status'         => $post->post_status,
                'company_type'   => get_post_meta( $post->ID, '_cwm_company_type', true ),
                'email'          => get_post_meta( $post->ID, '_cwm_company_email', true ),
                'phone'          => get_post_meta( $post->ID, '_cwm_company_phone', true ),
                'economic_code'  => get_post_meta( $post->ID, '_cwm_company_economic_code', true ),
                'national_id'    => get_post_meta( $post->ID, '_cwm_company_national_id', true ),
                'user_id'        => get_post_meta( $post->ID, '_cwm_company_user_id', true ),
                'credit_amount'  => get_post_meta( $post->ID, '_cwm_company_credit', true ),
                'created_at'     => $post->post_date,
            ];
        }

        wp_reset_postdata();

        return $this->respond_with_format( $request, $rows, [ 'id', 'title', 'status', 'company_type', 'email', 'phone', 'economic_code', 'national_id', 'user_id', 'created_at' ], 'companies.csv' );
    }

    /**
     * Admin endpoint: create a company record manually.
     */
    public function admin_create_company( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : [];

        $status = $request->get_param( 'status' );
        $result = $this->company_registration->register_from_admin(
            $payload,
            [
                'status' => $status ? sanitize_key( $status ) : 'publish',
            ]
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    /**
     * Admin endpoint: update company details.
     */
    public function admin_update_company( WP_REST_Request $request ) {
        $company_id = (int) $request->get_param( 'id' );
        $post       = get_post( $company_id );

        if ( ! $post || 'cwm_company' !== $post->post_type ) {
            return new WP_Error( 'cwm_invalid_company', __( 'شرکت موردنظر یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $post_updates   = [ 'ID' => $company_id ];
        $should_update  = false;
        $title          = $request->get_param( 'title' );
        $status         = $request->get_param( 'status' );

        if ( $title ) {
            $post_updates['post_title'] = sanitize_text_field( $title );
            $should_update               = true;
        }

        if ( $status ) {
            $post_updates['post_status'] = sanitize_key( $status );
            $should_update               = true;
        }

        if ( $should_update ) {
            wp_update_post( $post_updates );
        }

        $meta_map = [
            '_cwm_company_phone'         => 'phone',
            '_cwm_company_email'         => 'email',
            '_cwm_company_type'          => 'company_type',
            '_cwm_company_economic_code' => 'economic_code',
            '_cwm_company_national_id'   => 'national_id',
            '_cwm_company_credit'        => 'credit_amount',
        ];

        foreach ( $meta_map as $meta_key => $param ) {
            if ( $request->has_param( $param ) ) {
                update_post_meta( $company_id, $meta_key, sanitize_text_field( $request->get_param( $param ) ) );
            }
        }

        $user_id = (int) get_post_meta( $company_id, '_cwm_company_user_id', true );

        if ( $user_id ) {
            $user_updates  = [ 'ID' => $user_id ];
            $has_user_data = false;

            if ( $request->has_param( 'email' ) ) {
                $user_updates['user_email'] = sanitize_email( $request->get_param( 'email' ) );
                $has_user_data              = true;
            }

            if ( $request->has_param( 'full_name' ) ) {
                $name = sanitize_text_field( $request->get_param( 'full_name' ) );
                $user_updates['display_name'] = $name;
                $user_updates['first_name']   = $name;
                $has_user_data                = true;
                $this->maybe_update_user_name_fields( $user_id, $name );
            }

            if ( $has_user_data ) {
                $updated = wp_update_user( $user_updates );
                if ( is_wp_error( $updated ) ) {
                    return $updated;
                }
            }
        }

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'اطلاعات شرکت به‌روزرسانی شد.', 'company-wallet-manager' ),
            ]
        );
    }

    /**
     * Admin endpoint: update company credit.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function admin_update_company_credit( WP_REST_Request $request ) {
        $company_id = (int) $request->get_param( 'id' );
        $post       = get_post( $company_id );

        if ( ! $post || 'cwm_company' !== $post->post_type ) {
            return new WP_Error( 'cwm_invalid_company', __( 'شرکت موردنظر یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $payload = $request->get_json_params();
        $credit_amount = isset( $payload['credit_amount'] ) ? floatval( $payload['credit_amount'] ) : null;
        $action = isset( $payload['action'] ) ? sanitize_text_field( $payload['action'] ) : 'set'; // 'set' or 'add'

        if ( $credit_amount === null ) {
            return new WP_Error( 'cwm_invalid_credit', __( 'مبلغ اعتبار نامعتبر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $user_id = (int) get_post_meta( $company_id, '_cwm_company_user_id', true );
        if ( ! $user_id ) {
            return new WP_Error( 'cwm_no_user', __( 'کاربر شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $wallet_system = new Wallet_System();
        $current_balance = $wallet_system->get_balance( $user_id );
        $current_credit = get_post_meta( $company_id, '_cwm_company_credit', true );
        $current_credit = $current_credit ? floatval( $current_credit ) : 0;

        if ( $action === 'add' ) {
            // Add credit to existing
            $new_credit = $current_credit + $credit_amount;
            $balance_diff = $credit_amount;
        } else {
            // Set credit to specific amount
            $new_credit = $credit_amount;
            $balance_diff = $credit_amount - $current_credit;
        }

        // Update company credit meta
        update_post_meta( $company_id, '_cwm_company_credit', $new_credit );

        // Update wallet balance
        if ( $balance_diff != 0 ) {
            $wallet_system->update_balance( $user_id, $balance_diff );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'اعتبار شرکت به‌روزرسانی شد.', 'company-wallet-manager' ),
            'data'    => [
                'company_id'     => $company_id,
                'credit_amount'  => $new_credit,
                'wallet_balance' => $wallet_system->get_balance( $user_id ),
            ],
        ] );
    }

    /**
     * Admin endpoint: delete a company and its user.
     */
    public function admin_delete_company( WP_REST_Request $request ) {
        $company_id = (int) $request->get_param( 'id' );
        $post       = get_post( $company_id );

        if ( ! $post || 'cwm_company' !== $post->post_type ) {
            return new WP_Error( 'cwm_invalid_company', __( 'شرکت موردنظر یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $user_id = (int) get_post_meta( $company_id, '_cwm_company_user_id', true );

        if ( $user_id ) {
            wp_delete_user( $user_id );
        }

        wp_delete_post( $company_id, true );

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'شرکت و کاربر مرتبط حذف شد.', 'company-wallet-manager' ),
            ]
        );
    }

    /**
     * Admin endpoint: list employees for a company.
     */
    public function get_admin_company_employees( WP_REST_Request $request ) {
        $company_id = (int) $request['id'];

        $employees = get_users(
            [
                'meta_key'   => '_cwm_company_id',
                'meta_value' => $company_id,
                'number'     => 500,
            ]
        );

        $wallet = new Wallet_System();
        $rows   = [];

        $employee_ids = wp_list_pluck( $employees, 'ID' );
        $limits_map   = $this->category_manager->get_limits_for_employees( $employee_ids );

        foreach ( $employees as $employee ) {
            $limits = isset( $limits_map[ $employee->ID ] ) ? $limits_map[ $employee->ID ] : [];

            $rows[] = [
                'id'        => $employee->ID,
                'name'      => $employee->display_name,
                'email'     => $employee->user_email,
                'balance'   => $wallet->get_balance( $employee->ID ),
                'national_id' => get_user_meta( $employee->ID, 'national_id', true ),
                'phone'     => get_user_meta( $employee->ID, 'mobile', true ),
                'category_limits' => $limits,
            ];
        }

        return $this->respond_with_format( $request, $rows, [ 'id', 'name', 'email', 'balance', 'national_id', 'phone', 'category_limits' ], 'company-employees.csv' );
    }

    /**
     * Company endpoint: get list of employees for the company.
     */
    public function get_company_employees( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return rest_ensure_response( [
                'status' => 'success',
                'data'   => [],
            ] );
        }

        $company_id = $company_posts[0]->ID;

        $employees = get_users(
            [
                'meta_key'   => '_cwm_company_id',
                'meta_value' => $company_id,
                'number'     => 500,
            ]
        );

        $wallet = new Wallet_System();
        $rows   = [];

        $employee_ids = wp_list_pluck( $employees, 'ID' );
        $limits_map   = $this->category_manager->get_limits_for_employees( $employee_ids );

        foreach ( $employees as $employee ) {
            $limits = isset( $limits_map[ $employee->ID ] ) ? $limits_map[ $employee->ID ] : [];

            $rows[] = [
                'id'        => $employee->ID,
                'name'      => $employee->display_name,
                'email'     => $employee->user_email,
                'balance'   => $wallet->get_balance( $employee->ID ),
                'national_id' => get_user_meta( $employee->ID, 'national_id', true ),
                'phone'     => get_user_meta( $employee->ID, 'mobile', true ),
                'category_limits' => $limits,
            ];
        }

        return $this->respond_with_format( $request, $rows, [ 'id', 'name', 'email', 'balance', 'national_id', 'phone', 'category_limits' ], 'company-employees.csv' );
    }

    /**
     * Company endpoint: import or update employees for the company via CSV upload.
     */
    public function import_company_employees( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_id = $company_posts[0]->ID;

        $files = $request->get_file_params();
        $file  = isset( $files['file'] ) ? $files['file'] : ( $files['csv'] ?? null );

        if ( ! $file ) {
            return new WP_Error( 'cwm_missing_csv', __( 'فایل CSV کارکنان ارسال نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( ! empty( $file['error'] ) ) {
            return new WP_Error(
                'cwm_csv_upload_error',
                sprintf( __( 'خطا در بارگذاری فایل (کد %d).', 'company-wallet-manager' ), (int) $file['error'] ),
                [ 'status' => 400 ]
            );
        }

        $tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : null;

        if ( ! $tmp_name || ! file_exists( $tmp_name ) || ! is_readable( $tmp_name ) ) {
            return new WP_Error( 'cwm_csv_unreadable', __( 'امکان خواندن فایل CSV وجود ندارد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $handle = fopen( $tmp_name, 'r' );

        if ( false === $handle ) {
            return new WP_Error( 'cwm_csv_open_failed', __( 'فایل CSV قابل باز شدن نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $first_line = fgets( $handle );
        if ( false === $first_line ) {
            fclose( $handle );
            return new WP_Error( 'cwm_csv_empty', __( 'فایل CSV خالی است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $delimiter = $this->detect_csv_delimiter( $first_line );
        rewind( $handle );

        $header_row = fgetcsv( $handle, 0, $delimiter );
        if ( false === $header_row ) {
            fclose( $handle );
            return new WP_Error( 'cwm_csv_header_missing', __( 'ردیف سرستون CSV قابل خواندن نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $normalized_headers = $this->normalize_csv_headers( $header_row );

        // حداقل یکی از شناسه‌ها باید باشد: کد ملی یا ایمیل یا شماره همراه
        if (
            ! in_array( 'email', $normalized_headers, true )
            && ! in_array( 'national_id', $normalized_headers, true )
            && ! in_array( 'mobile', $normalized_headers, true )
        ) {
            fclose( $handle );
            return new WP_Error( 'cwm_csv_missing_identifiers', __( 'فایل باید حداقل شامل یکی از ستون‌های کد ملی، ایمیل یا شماره همراه باشد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $wallet = new Wallet_System();
        $logger = new Transaction_Logger();

        // Optional bulk credit amount to add to every processed employee
        $bulk_amount_param = $request->get_param( 'amount' );
        $bulk_credit_amount = $this->parse_decimal_value( $bulk_amount_param );
        if ( null !== $bulk_credit_amount && $bulk_credit_amount < 0 ) {
            return new WP_Error( 'cwm_invalid_amount', __( 'مبلغ اعتباردهی نمی‌تواند منفی باشد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $results = [
            'processed'          => 0,
            'created'            => 0,
            'updated'            => 0,
            'balances_adjusted'  => 0,
            'errors'             => [],
        ];

        $row_number = 1; // header already consumed.

        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $row_number++;

            if ( empty( array_filter( $row, 'strlen' ) ) ) {
                continue;
            }

            $results['processed']++;

            $employee_data = $this->map_csv_row_to_employee( $row, $normalized_headers );

            if ( empty( $employee_data['email'] ) && empty( $employee_data['national_id'] ) && empty( $employee_data['mobile'] ) ) {
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'ستون کد ملی، ایمیل یا شماره همراه برای این ردیف خالی است.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $email       = isset( $employee_data['email'] ) ? sanitize_email( $employee_data['email'] ) : '';
            $national_id = isset( $employee_data['national_id'] ) ? sanitize_text_field( $employee_data['national_id'] ) : '';
            $mobile      = isset( $employee_data['mobile'] ) ? sanitize_text_field( $employee_data['mobile'] ) : '';
            $name        = isset( $employee_data['name'] ) ? sanitize_text_field( $employee_data['name'] ) : '';

            if ( $email && ! is_email( $email ) ) {
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'فرمت ایمیل معتبر نیست.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $user = $this->find_employee_user( $email, $national_id, $mobile );

            $created = false;

            if ( ! $user ) {
                $user_id = $this->create_employee_user( $email, $name, $mobile, $national_id );

                if ( is_wp_error( $user_id ) ) {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => $user_id->get_error_message(),
                    ];
                    continue;
                }

                $user    = get_user_by( 'id', $user_id );
                $created = true;
                $results['created']++;
            } else {
                $update_args = [ 'ID' => $user->ID ];
                $has_updates = false;

                if ( $email && strtolower( $user->user_email ) !== strtolower( $email ) ) {
                    $update_args['user_email'] = $email;
                    $has_updates               = true;
                }

                if ( $name && $user->display_name !== $name ) {
                    $update_args['display_name'] = $name;
                    $has_updates                 = true;
                }

                if ( $has_updates ) {
                    $updated = wp_update_user( $update_args );

                    if ( is_wp_error( $updated ) ) {
                        $results['errors'][] = [
                            'row'     => $row_number,
                            'message' => $updated->get_error_message(),
                        ];
                        continue;
                    }
                }

                $results['updated']++;
            }

            if ( ! $user ) {
                // Should never happen but guard to avoid notices.
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'امکان ایجاد یا به‌روزرسانی کاربر وجود ندارد.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $user_id = $user->ID;

            // Ensure the employee role is assigned.
            if ( ! in_array( 'employee', (array) $user->roles, true ) ) {
                $user->add_role( 'employee' );
            }

            update_user_meta( $user_id, '_cwm_company_id', $company_id );

            if ( $national_id ) {
                update_user_meta( $user_id, 'national_id', $national_id );
            }

            if ( $mobile ) {
                update_user_meta( $user_id, 'mobile', $mobile );
            }

            if ( $name ) {
                $this->maybe_update_user_name_fields( $user_id, $name );
            }

            // Apply optional bulk credit amount for each employee
            if ( null !== $bulk_credit_amount && $bulk_credit_amount > 0 ) {
                $credited = $wallet->update_balance( $user_id, $bulk_credit_amount );
                if ( $credited ) {
                    $results['balances_adjusted']++;
                    $logger->log(
                        'company_bulk_credit',
                        $user_id, // company user id as sender
                        $user_id, // employee user id as receiver
                        $bulk_credit_amount,
                        'completed',
                        [
                            'context'  => 'employee_import',
                            'metadata' => [
                                'company_id' => $company_id,
                                'row'        => $row_number,
                                'source'     => 'csv_upload',
                                'mode'       => 'bulk_amount',
                            ],
                        ]
                    );
                } else {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => __( 'اعتباردهی گروهی به کیف پول با خطا مواجه شد.', 'company-wallet-manager' ),
                    ];
                }
            }

            if ( isset( $employee_data['balance'] ) && '' !== $employee_data['balance'] ) {
                $target_balance = $this->parse_decimal_value( $employee_data['balance'] );

                if ( null === $target_balance ) {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => __( 'مقدار موجودی معتبر نیست.', 'company-wallet-manager' ),
                    ];
                } else {
                    $current_balance = $wallet->get_balance( $user_id );
                    $delta           = $target_balance - $current_balance;

                    if ( abs( $delta ) >= 0.01 ) {
                        $updated_balance = $wallet->update_balance( $user_id, $delta );

                        if ( $updated_balance ) {
                            $results['balances_adjusted']++;
                            $logger->log(
                                'company_balance_adjustment',
                                $user_id, // company user id as sender
                                $user_id, // employee user id as receiver
                                $delta,
                                'completed',
                                [
                                    'context'  => 'employee_import',
                                    'metadata' => [
                                        'company_id'      => $company_id,
                                        'row'             => $row_number,
                                        'source'          => 'csv_upload',
                                        'target_balance' => $target_balance,
                                        'previous_balance' => $current_balance,
                                    ],
                                ]
                            );
                        } else {
                            $results['errors'][] = [
                                'row'     => $row_number,
                                'message' => __( 'به‌روزرسانی موجودی با خطا مواجه شد.', 'company-wallet-manager' ),
                            ];
                        }
                    }
                }
            }
        }

        fclose( $handle );

        return rest_ensure_response( $results );
    }

    /**
     * Admin endpoint: import or update employees for a company via CSV upload.
     */
    public function import_admin_company_employees( WP_REST_Request $request ) {
        $company_id = (int) $request['id'];

        if ( $company_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_company', __( 'شناسه شرکت نامعتبر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $company_post = get_post( $company_id );
        if ( ! $company_post || 'cwm_company' !== $company_post->post_type ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت مورد نظر یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $files = $request->get_file_params();
        $file  = isset( $files['file'] ) ? $files['file'] : ( $files['csv'] ?? null );

        if ( ! $file ) {
            return new WP_Error( 'cwm_missing_csv', __( 'فایل CSV کارکنان ارسال نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( ! empty( $file['error'] ) ) {
            return new WP_Error(
                'cwm_csv_upload_error',
                sprintf( __( 'خطا در بارگذاری فایل (کد %d).', 'company-wallet-manager' ), (int) $file['error'] ),
                [ 'status' => 400 ]
            );
        }

        $tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : null;

        if ( ! $tmp_name || ! file_exists( $tmp_name ) || ! is_readable( $tmp_name ) ) {
            return new WP_Error( 'cwm_csv_unreadable', __( 'امکان خواندن فایل CSV وجود ندارد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $handle = fopen( $tmp_name, 'r' );

        if ( false === $handle ) {
            return new WP_Error( 'cwm_csv_open_failed', __( 'فایل CSV قابل باز شدن نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $first_line = fgets( $handle );
        if ( false === $first_line ) {
            fclose( $handle );
            return new WP_Error( 'cwm_csv_empty', __( 'فایل CSV خالی است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $delimiter = $this->detect_csv_delimiter( $first_line );
        rewind( $handle );

        $header_row = fgetcsv( $handle, 0, $delimiter );
        if ( false === $header_row ) {
            fclose( $handle );
            return new WP_Error( 'cwm_csv_header_missing', __( 'ردیف سرستون CSV قابل خواندن نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $normalized_headers = $this->normalize_csv_headers( $header_row );

        // حداقل یکی از شناسهD0ها باید باشد: کد ملی یا ایمیل یا شماره همراه
        if (
            ! in_array( 'email', $normalized_headers, true )
            && ! in_array( 'national_id', $normalized_headers, true )
            && ! in_array( 'mobile', $normalized_headers, true )
        ) {
            fclose( $handle );
            return new WP_Error( 'cwm_csv_missing_identifiers', __( 'فایل باید حداقل شامل یکی از ستونD0های کد ملی، ایمیل یا شماره همراه باشد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $wallet = new Wallet_System();
        $logger = new Transaction_Logger();

        // Optional bulk credit amount to add to every processed employee
        $bulk_amount_param = $request->get_param( 'amount' );
        $bulk_credit_amount = $this->parse_decimal_value( $bulk_amount_param );
        if ( null !== $bulk_credit_amount && $bulk_credit_amount < 0 ) {
            return new WP_Error( 'cwm_invalid_amount', __( 'مبلغ اعتباردهی نمیD0تواند منفی باشد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $results = [
            'processed'          => 0,
            'created'            => 0,
            'updated'            => 0,
            'balances_adjusted'  => 0,
            'errors'             => [],
        ];

        $row_number = 1; // header already consumed.

        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $row_number++;

            if ( empty( array_filter( $row, 'strlen' ) ) ) {
                continue;
            }

            $results['processed']++;

            $employee_data = $this->map_csv_row_to_employee( $row, $normalized_headers );

            if ( empty( $employee_data['email'] ) && empty( $employee_data['national_id'] ) && empty( $employee_data['mobile'] ) ) {
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'ستون کد ملی، ایمیل یا شماره همراه برای این ردیف خالی است.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $email       = isset( $employee_data['email'] ) ? sanitize_email( $employee_data['email'] ) : '';
            $national_id = isset( $employee_data['national_id'] ) ? sanitize_text_field( $employee_data['national_id'] ) : '';
            $mobile      = isset( $employee_data['mobile'] ) ? sanitize_text_field( $employee_data['mobile'] ) : '';
            $name        = isset( $employee_data['name'] ) ? sanitize_text_field( $employee_data['name'] ) : '';

            if ( $email && ! is_email( $email ) ) {
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'فرمت ایمیل معتبر نیست.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $user = $this->find_employee_user( $email, $national_id, $mobile );

            $created = false;

            if ( ! $user ) {
                $user_id = $this->create_employee_user( $email, $name, $mobile, $national_id );

                if ( is_wp_error( $user_id ) ) {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => $user_id->get_error_message(),
                    ];
                    continue;
                }

                $user    = get_user_by( 'id', $user_id );
                $created = true;
                $results['created']++;
            } else {
                $update_args = [ 'ID' => $user->ID ];
                $has_updates = false;

                if ( $email && strtolower( $user->user_email ) !== strtolower( $email ) ) {
                    $update_args['user_email'] = $email;
                    $has_updates               = true;
                }

                if ( $name && $user->display_name !== $name ) {
                    $update_args['display_name'] = $name;
                    $has_updates                 = true;
                }

                if ( $has_updates ) {
                    $updated = wp_update_user( $update_args );

                    if ( is_wp_error( $updated ) ) {
                        $results['errors'][] = [
                            'row'     => $row_number,
                            'message' => $updated->get_error_message(),
                        ];
                        continue;
                    }
                }

                $results['updated']++;
            }

            if ( ! $user ) {
                // Should never happen but guard to avoid notices.
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'امکان ایجاد یا به‌روزرسانی کاربر وجود ندارد.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $user_id = $user->ID;

            // Ensure the employee role is assigned.
            if ( ! in_array( 'employee', (array) $user->roles, true ) ) {
                $user->add_role( 'employee' );
            }

            update_user_meta( $user_id, '_cwm_company_id', $company_id );

            if ( $national_id ) {
                update_user_meta( $user_id, 'national_id', $national_id );
            }

            if ( $mobile ) {
                update_user_meta( $user_id, 'mobile', $mobile );
            }

            if ( $name ) {
                $this->maybe_update_user_name_fields( $user_id, $name );
            }

            // Apply optional bulk credit amount for each employee
            if ( null !== $bulk_credit_amount && $bulk_credit_amount > 0 ) {
                $credited = $wallet->update_balance( $user_id, $bulk_credit_amount );
                if ( $credited ) {
                    $results['balances_adjusted']++;
                    $logger->log(
                        'admin_bulk_credit',
                        0,
                        $user_id,
                        $bulk_credit_amount,
                        'completed',
                        [
                            'context'  => 'employee_import',
                            'metadata' => [
                                'company_id' => $company_id,
                                'row'        => $row_number,
                                'source'     => 'csv_upload',
                                'mode'       => 'bulk_amount',
                            ],
                        ]
                    );
                } else {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => __( 'اعتباردهی گروهی به کیف پول با خطا مواجه شد.', 'company-wallet-manager' ),
                    ];
                }
            }

            if ( isset( $employee_data['balance'] ) && '' !== $employee_data['balance'] ) {
                $target_balance = $this->parse_decimal_value( $employee_data['balance'] );

                if ( null === $target_balance ) {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => __( 'مقدار موجودی معتبر نیست.', 'company-wallet-manager' ),
                    ];
                } else {
                    $current_balance = $wallet->get_balance( $user_id );
                    $delta           = $target_balance - $current_balance;

                    if ( abs( $delta ) >= 0.01 ) {
                        $updated_balance = $wallet->update_balance( $user_id, $delta );

                        if ( $updated_balance ) {
                            $results['balances_adjusted']++;
                            $logger->log(
                                'admin_adjustment',
                                0,
                                $user_id,
                                abs( $delta ),
                                'completed',
                                [
                                    'context'  => 'employee_import',
                                    'metadata' => [
                                        'company_id' => $company_id,
                                        'change'      => $delta,
                                        'row'         => $row_number,
                                        'source'      => 'csv_upload',
                                    ],
                                ]
                            );
                        } else {
                            $results['errors'][] = [
                                'row'     => $row_number,
                                'message' => __( 'به‌روزرسانی موجودی کیف پول با خطا مواجه شد.', 'company-wallet-manager' ),
                            ];
                        }
                    }
                }
            }

            if ( $created && empty( $email ) ) {
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => __( 'کاربر بدون ایمیل ایجاد شد. لطفاً برای ارسال اطلاعات ورود، ایمیل را تکمیل کنید.', 'company-wallet-manager' ),
                ];
            }
        }

        fclose( $handle );

        return rest_ensure_response( $results );
    }

    /**
     * Admin endpoint: list merchants.
     */
    public function get_admin_merchants( WP_REST_Request $request ) {
        $users = get_users(
            [
                'role__in' => [ 'merchant' ],
            ]
        );

        $wallet = new Wallet_System();
        $rows   = [];

        foreach ( $users as $user ) {
            $categories = $this->category_manager->get_merchant_categories( $user->ID );
            $rows[] = [
                'id'            => $user->ID,
                'name'          => $user->display_name,
                'email'         => $user->user_email,
                'balance'       => $wallet->get_balance( $user->ID ),
                'store_name'    => get_user_meta( $user->ID, '_cwm_store_name', true ),
                'store_address' => get_user_meta( $user->ID, '_cwm_store_address', true ),
                'phone'         => get_user_meta( $user->ID, 'cwm_phone', true ),
                'mobile'        => get_user_meta( $user->ID, 'cwm_mobile', true ),
                'pending_payouts' => $this->get_pending_payout_total( $user->ID ),
                'categories'    => $categories,
                'category_ids'  => array_map( 'intval', wp_list_pluck( $categories, 'id' ) ),
            ];
        }

        return $this->respond_with_format( $request, $rows, [ 'id', 'name', 'email', 'balance', 'store_name', 'store_address', 'phone', 'mobile', 'pending_payouts' ], 'merchants.csv' );
    }

    /**
     * Admin endpoint: list products across all merchants.
     */
    public function get_admin_products( WP_REST_Request $request ) {
        global $wpdb;

        $products_table = $wpdb->prefix . 'cwm_products';
        $users_table    = $wpdb->users;
        
        // Ensure products table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$products_table}'" );
        if ( $products_table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$products_table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        }

        $conditions = [];
        $params     = [];

        $search = $request->get_param( 'search' );
        if ( $search ) {
            $like         = '%' . $wpdb->esc_like( $search ) . '%';
            $conditions[] = '(p.name LIKE %s OR u.display_name LIKE %s)';
            $params[]     = $like;
            $params[]     = $like;
        }

        $where = '';
        if ( ! empty( $conditions ) ) {
            $where = 'WHERE ' . implode( ' AND ', $conditions );
        }

        $limit = absint( $request->get_param( 'limit' ) );
        if ( $limit <= 0 || $limit > 1000 ) {
            $limit = 500;
        }

        $params[] = $limit;

        $sql = "SELECT p.*, u.display_name as merchant_name, u.user_email as merchant_email
                FROM {$products_table} p
                LEFT JOIN {$users_table} u ON p.merchant_id = u.ID
                {$where}
                ORDER BY p.created_at DESC
                LIMIT %d";

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        $formatted = array_map(
            static function ( $row ) {
                return [
                    'id'                      => (int) $row['id'],
                    'name'                    => $row['name'],
                    'price'                   => isset( $row['price'] ) ? (float) $row['price'] : 0,
                    'stock_quantity'          => isset( $row['stock_quantity'] ) ? (int) $row['stock_quantity'] : 0,
                    'status'                  => $row['status'],
                    'merchant_id'             => (int) $row['merchant_id'],
                    'merchant_name'           => $row['merchant_name'],
                    'merchant_email'          => $row['merchant_email'],
                    'online_purchase_enabled' => (bool) $row['online_purchase_enabled'],
                    'is_featured'             => isset( $row['is_featured'] ) ? (bool) $row['is_featured'] : false,
                    'product_category_id'     => isset( $row['product_category_id'] ) ? (int) $row['product_category_id'] : null,
                    'image'                   => $row['image'],
                    'created_at'              => $row['created_at'],
                ];
            },
            $rows ?: []
        );

        return $this->respond_with_format(
            $request,
            $formatted,
            [ 'id', 'name', 'price', 'stock_quantity', 'status', 'merchant_id', 'merchant_name', 'merchant_email', 'online_purchase_enabled' ],
            'products.csv'
        );
    }

    /**
     * Admin endpoint: update merchant profile information.
     */
    public function admin_update_merchant( WP_REST_Request $request ) {
        $merchant_id = (int) $request->get_param( 'id' );
        $user        = get_user_by( 'id', $merchant_id );

        if ( ! $user || ! in_array( 'merchant', (array) $user->roles, true ) ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'پذیرنده یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $updates     = [ 'ID' => $merchant_id ];
        $has_updates = false;

        if ( $request->has_param( 'full_name' ) ) {
            $name                      = sanitize_text_field( $request->get_param( 'full_name' ) );
            $updates['display_name']   = $name;
            $updates['first_name']     = $name;
            $has_updates               = true;
            $this->maybe_update_user_name_fields( $merchant_id, $name );
        }

        if ( $request->has_param( 'email' ) ) {
            $updates['user_email'] = sanitize_email( $request->get_param( 'email' ) );
            $has_updates           = true;
        }

        if ( $has_updates ) {
            $result = wp_update_user( $updates );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        $meta_map = [
            '_cwm_store_name'    => 'store_name',
            '_cwm_store_address' => 'store_address',
            '_cwm_store_description' => 'store_description',
            'cwm_phone'          => 'phone',
            'cwm_mobile'         => 'mobile',
        ];

        foreach ( $meta_map as $meta_key => $param ) {
            if ( $request->has_param( $param ) ) {
                $value = sanitize_text_field( $request->get_param( $param ) );
                update_user_meta( $merchant_id, $meta_key, $value );
            }
        }

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'اطلاعات پذیرنده به‌روزرسانی شد.', 'company-wallet-manager' ),
            ]
        );
    }

    /**
     * Admin endpoint: delete a merchant account.
     */
    public function admin_delete_merchant( WP_REST_Request $request ) {
        $merchant_id = (int) $request->get_param( 'id' );
        $user        = get_user_by( 'id', $merchant_id );

        if ( ! $user || ! in_array( 'merchant', (array) $user->roles, true ) ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'پذیرنده یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        wp_delete_user( $merchant_id );

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'پذیرنده حذف شد.', 'company-wallet-manager' ),
            ]
        );
    }

    /**
     * Admin endpoint: delete any user account.
     */
    public function admin_delete_user( WP_REST_Request $request ) {
        $user_id    = (int) $request->get_param( 'id' );
        $current_id = get_current_user_id();

        if ( $user_id === $current_id ) {
            return new WP_Error( 'cwm_forbidden', __( 'امکان حذف حساب کاربری خودتان وجود ندارد.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new WP_Error( 'cwm_invalid_user', __( 'کاربر یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        wp_delete_user( $user_id );

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'کاربر حذف شد.', 'company-wallet-manager' ),
            ]
        );
    }

    /**
     * Admin endpoint: fetch category assignments for a specific merchant.
     */
    public function get_admin_merchant_categories( WP_REST_Request $request ) {
        $merchant_id = absint( $request->get_param( 'id' ) );

        if ( ! $merchant_id ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'Merchant not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $user = get_user_by( 'id', $merchant_id );

        if ( ! $user || ! in_array( 'merchant', (array) $user->roles, true ) ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'Merchant not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'assigned'  => $this->category_manager->get_merchant_categories( $merchant_id ),
                    'available' => $this->category_manager->get_all_categories(),
                ],
            ]
        );
    }

    /**
     * Admin endpoint: update category assignments for a merchant.
     */
    public function update_admin_merchant_categories( WP_REST_Request $request ) {
        $merchant_id = absint( $request->get_param( 'id' ) );

        if ( ! $merchant_id ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'Merchant not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $user = get_user_by( 'id', $merchant_id );

        if ( ! $user || ! in_array( 'merchant', (array) $user->roles, true ) ) {
            return new WP_Error( 'cwm_invalid_merchant', __( 'Merchant not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $category_ids = $request->get_param( 'category_ids' );

        if ( ! is_array( $category_ids ) ) {
            return new WP_Error( 'cwm_invalid_categories', __( 'Category IDs must be an array.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $available    = wp_list_pluck( $this->category_manager->get_all_categories(), 'id' );
        $category_ids = array_values( array_intersect( array_map( 'absint', $category_ids ), $available ) );

        $this->category_manager->sync_merchant_categories( $merchant_id, $category_ids );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'assigned' => $this->category_manager->get_merchant_categories( $merchant_id ),
                ],
            ]
        );
    }

    /**
     * Admin endpoint: payout requests.
     */
    public function get_admin_payouts( WP_REST_Request $request ) {
        global $wpdb;

        $table  = $wpdb->prefix . 'cwm_payout_requests';
        $status = $request->get_param( 'status' );

        $where  = '1=1';
        $params = [];

        if ( $status ) {
            $where   .= ' AND status = %s';
            $params[] = sanitize_text_field( $status );
        }

        $sql   = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC";
        $rows  = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        return $this->respond_with_format( $request, $rows, [ 'id', 'merchant_id', 'amount', 'bank_account', 'status', 'created_at' ], 'payouts.csv' );
    }

    /**
     * Admin endpoint: transaction history.
     */
    public function get_admin_transactions( WP_REST_Request $request ) {
        global $wpdb;

        $table = $wpdb->prefix . 'cwm_transactions';
        $from  = $request->get_param( 'from' );
        $to    = $request->get_param( 'to' );

        $where  = '1=1';
        $params = [];

        if ( $from ) {
            $where   .= ' AND created_at >= %s';
            $params[] = sanitize_text_field( $from );
        }

        if ( $to ) {
            $where   .= ' AND created_at <= %s';
            $params[] = sanitize_text_field( $to );
        }

        $sql  = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT 500";
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        return $this->respond_with_format( $request, $rows, [ 'id', 'type', 'sender_id', 'receiver_id', 'amount', 'status', 'created_at' ], 'transactions.csv' );
    }

    /**
     * Admin endpoint: aggregate stats.
     */
    public function get_admin_stats( WP_REST_Request $request ) {
        global $wpdb;

        unset( $request );

        $counts          = wp_count_posts( 'cwm_company' );
        $total_companies = array_sum( (array) $counts );

        $wallet_table = $wpdb->prefix . 'cwm_wallets';
        $totals       = $wpdb->get_row( "SELECT SUM(balance) as total_balance, COUNT(*) as wallet_count FROM $wallet_table", ARRAY_A );

        $payout_table  = $wpdb->prefix . 'cwm_payout_requests';
        $pending_total = $wpdb->get_var( "SELECT SUM(amount) FROM $payout_table WHERE status = 'pending'" );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'total_companies'    => (int) $total_companies,
                    'total_wallets'      => (int) $totals['wallet_count'],
                    'total_balance'      => (float) $totals['total_balance'],
                    'pending_payout_sum' => (float) $pending_total,
                ],
            ]
        );
    }

    /**
     * Format response as JSON or CSV depending on request.
     */
    protected function respond_with_format( WP_REST_Request $request, array $rows, array $columns, $filename ) {
        $format = strtolower( (string) $request->get_param( 'format' ) );

        if ( 'csv' === $format ) {
            $handle = fopen( 'php://temp', 'w+' );
            fputcsv( $handle, $columns );

            foreach ( $rows as $row ) {
                $line = [];
                foreach ( $columns as $column ) {
                    $line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
                }
                fputcsv( $handle, $line );
            }

            rewind( $handle );
            $csv = stream_get_contents( $handle );
            fclose( $handle );

            $response = new WP_REST_Response( $csv );
            $response->set_headers(
                [
                    'Content-Type'        => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename=' . sanitize_file_name( $filename ),
                ]
            );

            return $response;
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'count'  => count( $rows ),
                'data'   => $rows,
            ]
        );
    }

    /**
     * Detect the delimiter used in a CSV line.
     */
    protected function detect_csv_delimiter( $line ) {
        $candidates = [ ',', ';', "\t" ];
        $counts     = [];

        foreach ( $candidates as $candidate ) {
            $counts[ $candidate ] = substr_count( $line, $candidate );
        }

        arsort( $counts );
        $delimiter = key( $counts );

        return $delimiter ? $delimiter : ',';
    }

    /**
     * Normalize CSV headers to predictable keys.
     */
    protected function normalize_csv_headers( array $headers ) {
        $normalized = [];

        foreach ( $headers as $header ) {
            $normalized[] = $this->normalize_csv_key( $header );
        }

        return $normalized;
    }

    /**
     * Normalize a single header key.
     */
    protected function normalize_csv_key( $key ) {
        $key = strtolower( trim( $key ) );
        $key = preg_replace( '/^\xEF\xBB\xBF/', '', $key ); // Remove UTF-8 BOM.
        $key = str_replace( [ '-', ' ', '.' ], '_', $key );
        $key = preg_replace( '/[^a-z0-9_]/', '', $key );

        switch ( $key ) {
            case 'fullname':
            case 'full_name':
            case 'employee_name':
            case 'name':
                return 'name';
            case 'first_name':
                return 'first_name';
            case 'last_name':
                return 'last_name';
            case 'emailaddress':
            case 'email':
                return 'email';
            case 'nationalcode':
            case 'nationalcodeid':
            case 'nationalid':
            case 'national_id':
                return 'national_id';
            case 'mobilephone':
            case 'mobile_number':
            case 'phonenumber':
            case 'phone':
            case 'mobile':
                return 'mobile';
            case 'amount':
            case 'wallet':
            case 'wallet_balance':
            case 'balance':
                return 'balance';
            default:
                return $key;
        }
    }

    /**
     * Map CSV row values to an associative array using normalized headers.
     */
    protected function map_csv_row_to_employee( array $row, array $headers ) {
        $data = [];

        foreach ( $headers as $index => $key ) {
            if ( ! isset( $row[ $index ] ) ) {
                continue;
            }

            $value = trim( (string) $row[ $index ] );

            if ( '' === $value ) {
                continue;
            }

            $data[ $key ] = $value;
        }

        if ( empty( $data['name'] ) && ( ! empty( $data['first_name'] ) || ! empty( $data['last_name'] ) ) ) {
            $first         = isset( $data['first_name'] ) ? $data['first_name'] : '';
            $last          = isset( $data['last_name'] ) ? $data['last_name'] : '';
            $data['name'] = trim( $first . ' ' . $last );
        }

        return $data;
    }

    /**
     * Attempt to find an existing employee by email or national ID.
     */
    protected function find_employee_user( $email, $national_id, $mobile = '' ) {
        // Try by email
        if ( $email ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                return $user;
            }
        }

        // Try by username/mobile
        if ( $mobile ) {
            $by_login = get_user_by( 'login', $mobile );
            if ( $by_login ) {
                return $by_login;
            }

            $users = get_users(
                [
                    'meta_key'    => 'mobile',
                    'meta_value'  => $mobile,
                    'number'      => 1,
                    'count_total' => false,
                ]
            );
            if ( $users && isset( $users[0] ) ) {
                return $users[0];
            }
        }

        if ( $national_id ) {
            $users = get_users(
                [
                    'meta_key'    => 'national_id',
                    'meta_value'  => $national_id,
                    'number'      => 1,
                    'count_total' => false,
                ]
            );

            if ( $users && isset( $users[0] ) ) {
                return $users[0];
            }
        }

        return null;
    }

    /**
     * Create a new employee user account.
     */
    protected function create_employee_user( $email, $name, $mobile = '', $national_id = '' ) {
        // Username policy: prefer mobile as username; fallback to email local-part; else name; else 'employee'
        $login_source = '';
        if ( $mobile ) {
            $login_source = $mobile;
        } elseif ( $email ) {
            $login_source = strstr( $email, '@', true );
        } elseif ( $name ) {
            $login_source = $name;
        } else {
            $login_source = 'employee';
        }

        $login_source = sanitize_user( $login_source, true );
        if ( empty( $login_source ) ) {
            $login_source = 'employee';
        }

        $username = $login_source;
        $suffix   = 1;
        while ( username_exists( $username ) ) {
            $username = $login_source . $suffix;
            $suffix++;
        }

        // Email policy: if not provided, and national_id exists, synthesize email from national_id
        if ( empty( $email ) && $national_id ) {
            // You may change the domain if needed
            $email = $national_id . '@example.com';
        }

        $user_data = [
            'user_login'   => $username,
            'user_pass'    => wp_generate_password( 12, true ),
            'display_name' => $name ? $name : $username,
            'role'         => 'employee',
        ];

        if ( $email ) {
            $user_data['user_email'] = $email;
        }

        if ( $name ) {
            $parts = preg_split( '/\s+/', $name, 2 );
            if ( isset( $parts[0] ) ) {
                $user_data['first_name'] = $parts[0];
            }
            if ( isset( $parts[1] ) ) {
                $user_data['last_name'] = $parts[1];
            }
        }

        $new_user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $new_user_id ) ) {
            return $new_user_id;
        }

        // Persist mobile and national_id if available
        if ( $mobile ) {
            update_user_meta( $new_user_id, 'mobile', $mobile );
        }
        if ( $national_id ) {
            update_user_meta( $new_user_id, 'national_id', $national_id );
        }

        return $new_user_id;
    }

    /**
     * Update first and last name meta when a display name is provided.
     */
    protected function maybe_update_user_name_fields( $user_id, $display_name ) {
        $parts = preg_split( '/\s+/', $display_name, 2 );

        if ( isset( $parts[0] ) && $parts[0] ) {
            update_user_meta( $user_id, 'first_name', $parts[0] );
        }

        if ( isset( $parts[1] ) && $parts[1] ) {
            update_user_meta( $user_id, 'last_name', $parts[1] );
        }
    }

    /**
     * Parse a decimal value from CSV input.
     */
    protected function parse_decimal_value( $value ) {
        if ( is_numeric( $value ) ) {
            return (float) $value;
        }

        if ( ! is_string( $value ) ) {
            return null;
        }

        $normalized = str_replace( [ "\xC2\xA0", ' ', '٬' ], '', $value );

        if ( false !== strpos( $normalized, ',' ) && false === strpos( $normalized, '.' ) ) {
            $normalized = str_replace( ',', '.', $normalized );
        } else {
            $normalized = str_replace( ',', '', $normalized );
        }

        $normalized = preg_replace( '/[^0-9\.-]/', '', $normalized );

        if ( '' === $normalized || '-' === $normalized ) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * Sum pending payouts for a merchant.
     */
    protected function get_pending_payout_total( $merchant_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'cwm_payout_requests';

        $total = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table WHERE merchant_id = %d AND status = 'pending'", $merchant_id ) );

        return (float) $total;
    }

    /**
     * Ensure the current user can access admin endpoints.
     */
    public function admin_permission_check( WP_REST_Request $request ) {
        $validation = $this->validate_token( $request );
        if ( is_wp_error( $validation ) ) {
            $this->debug_log(
                'Admin permission denied: invalid token',
                [
                    'error_code'    => $validation->get_error_code(),
                    'error_message' => $validation->get_error_message(),
                ]
            );
            return false;
        }

        $user = wp_get_current_user();

        if ( user_can( $user, 'manage_wallets' ) ) {
            $this->debug_log(
                'Admin permission granted via capability',
                [
                    'user_id' => $user->ID,
                    'roles'   => $user->roles,
                ]
            );
            return true;
        }

        $has_admin_role = $this->user_has_role( $user, 'administrator' );

        $this->debug_log(
            $has_admin_role
                ? 'Admin permission granted via administrator role'
                : 'Admin permission denied: missing capability and role',
            [
                'user_id'      => $user->ID,
                'roles'        => $user->roles,
                'capabilities' => array_keys( array_filter( (array) $user->allcaps ) ),
            ]
        );

        return $has_admin_role;
    }

    /**
     * Issue a short-lived access token.
     */
    protected function issue_access_token( $user_id ) {
        if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) ) {
            return new WP_Error( 'jwt_auth_secret_not_defined', __( 'JWT secret key is not defined in wp-config.php.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        $issued_at = time();
        $expires   = $issued_at + HOUR_IN_SECONDS;

        $payload = [
            'iss'  => get_bloginfo( 'url' ),
            'iat'  => $issued_at,
            'exp'  => $expires,
            'data' => [
                'user' => [
                    'id' => $user_id,
                ],
            ],
        ];

        $user = get_userdata( $user_id );
        if ( $user instanceof \WP_User ) {
            $roles = array_values( (array) $user->roles );

            if ( ! empty( $roles ) ) {
                $primary_role = $roles[0];

                $payload['role']  = $primary_role;
                $payload['roles'] = $roles;

                $payload['data']['user']['role']  = $primary_role;
                $payload['data']['user']['roles'] = $roles;
            }
        }

        return [
            'token'      => \Firebase\JWT\JWT::encode( $payload, JWT_AUTH_SECRET_KEY, 'HS256' ),
            'expires_in' => HOUR_IN_SECONDS,
        ];
    }

    /**
     * Issue and persist a refresh token.
     */
    protected function issue_refresh_token( $user_id ) {
        try {
            $random = bin2hex( random_bytes( 32 ) );
        } catch ( \Exception $e ) {
            $random = bin2hex( wp_random_bytes( 32 ) );
        }
        $refresh_token = $user_id . '.' . $random;
        $expires       = time() + ( 14 * DAY_IN_SECONDS );

        update_user_meta( $user_id, 'cwm_refresh_token_hash', wp_hash_password( $refresh_token ) );
        update_user_meta( $user_id, 'cwm_refresh_token_expiration', $expires );

        return [
            'token'      => $refresh_token,
            'expires_in' => 14 * DAY_IN_SECONDS,
        ];
    }

    /**
     * Retrieve pending payment requests for the authenticated employee.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_pending_payment_requests( WP_REST_Request $request ) {
        unset( $request );

        global $wpdb;

        $employee_id = get_current_user_id();
        $table       = $wpdb->prefix . 'cwm_payment_requests';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, merchant_id, category_id, amount, status, created_at FROM $table WHERE employee_id = %d AND status = %s ORDER BY created_at DESC",
                $employee_id,
                'pending'
            ),
            ARRAY_A
        );

        if ( null === $rows ) {
            return new WP_Error( 'cwm_db_error', __( 'خطا در دریافت درخواست‌های پرداخت.', 'company-wallet-manager' ), 500 );
        }

        $formatted = array_map(
            function( $row ) {
                $merchant      = get_userdata( (int) $row['merchant_id'] );
                $merchant_name = $merchant ? $merchant->display_name : '';
                $store_name    = $merchant ? get_user_meta( $merchant->ID, '_cwm_store_name', true ) : '';

                if ( empty( $store_name ) && $merchant_name ) {
                    $store_name = $merchant_name;
                }

                $created_at = ! empty( $row['created_at'] ) ? mysql_to_rfc3339( $row['created_at'] ) : null;

                return [
                    'id'            => (int) $row['id'],
                    'amount'        => (float) $row['amount'],
                    'category_id'   => isset( $row['category_id'] ) ? (int) $row['category_id'] : 0,
                    'status'        => $row['status'],
                    'merchant_id'   => (int) $row['merchant_id'],
                    'merchant_name' => $merchant_name,
                    'store_name'    => is_string( $store_name ) ? $store_name : '',
                    'created_at'    => $created_at,
                ];
            },
            $rows
        );

        return rest_ensure_response( $formatted );
    }

    /**
     * Confirm a payment from an employee.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function confirm_payment( WP_REST_Request $request ) {
        global $wpdb;

        $current_user = wp_get_current_user();
        $request_id   = (int) $request->get_param( 'request_id' );
        $otp_code     = sanitize_text_field( $request->get_param( 'otp_code' ) );

        $is_employee = $this->user_has_role( $current_user, 'employee' );
        $is_merchant = $this->user_has_role( $current_user, 'merchant' );

        if ( ! $is_employee && ! $is_merchant ) {
            return new WP_Error( 'cwm_permission_denied', __( 'You are not allowed to confirm this payment.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $table = $wpdb->prefix . 'cwm_payment_requests';

        if ( $is_employee ) {
            $payment = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d AND employee_id = %d",
                    $request_id,
                    $current_user->ID
                ),
                ARRAY_A
            );
        } else {
            $payment = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d AND merchant_id = %d",
                    $request_id,
                    $current_user->ID
                ),
                ARRAY_A
            );
        }

        if ( ! $payment ) {
            return new WP_Error( 'cwm_invalid_request', __( 'Invalid payment request.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        if ( 'completed' === $payment['status'] ) {
            return new WP_Error( 'cwm_request_completed', __( 'Payment request has already been completed.', 'company-wallet-manager' ), [ 'status' => 409 ] );
        }

        if ( ! empty( $payment['locked_at'] ) || (int) $payment['failed_attempts'] >= 5 ) {
            return new WP_Error( 'cwm_request_locked', __( 'Payment request is locked due to too many failed attempts.', 'company-wallet-manager' ), [ 'status' => 423 ] );
        }

        $now = current_time( 'timestamp', true );
        if ( ! empty( $payment['otp_expires_at'] ) && $now > strtotime( $payment['otp_expires_at'] ) ) {
            $wpdb->update( $table, [ 'status' => 'expired' ], [ 'id' => $request_id ] );

            return new WP_Error( 'cwm_otp_expired', __( 'OTP code has expired.', 'company-wallet-manager' ), [ 'status' => 410 ] );
        }

        if ( $payment['otp'] !== $otp_code ) {
            $attempts = (int) $payment['failed_attempts'] + 1;
            $data     = [ 'failed_attempts' => $attempts ];

            if ( $attempts >= 5 ) {
                $data['locked_at'] = gmdate( 'Y-m-d H:i:s', $now );
                $data['status']    = 'locked';
            }

            $wpdb->update( $table, $data, [ 'id' => $request_id ] );

            return new WP_Error( 'cwm_invalid_otp', __( 'Invalid OTP code.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $employee_id = (int) $payment['employee_id'];
        $merchant_id = (int) $payment['merchant_id'];
        $amount      = (float) $payment['amount'];
        $category_id = isset( $payment['category_id'] ) ? (int) $payment['category_id'] : 0;

        $wallet           = new Wallet_System();
        $current_balance  = $wallet->get_balance( $employee_id );
        $remaining_after  = null;
        $context          = null;

        if ( $amount > $current_balance ) {
            return new WP_Error(
                'cwm_insufficient_funds',
                __( 'موجودی کیف پول کاربر کافی نیست.', 'company-wallet-manager' ),
                [ 'status' => 400 ]
            );
        }

        if ( $category_id > 0 ) {
            $context = $this->build_payment_context( $merchant_id, $employee_id, $category_id );
            if ( is_wp_error( $context ) ) {
                return $context;
            }

            if ( ! $context['limit_defined'] ) {
                return new WP_Error( 'cwm_category_limit_missing', __( 'هیچ سقفی برای این دسته‌بندی ثبت نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            if ( $amount > $context['available'] ) {
                return new WP_Error( 'cwm_category_limit_exceeded', __( 'سقف استفاده از این دسته‌بندی تکمیل شده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            $consumed = $this->category_manager->consume_allowance( $employee_id, $category_id, $amount );
            if ( ! $consumed ) {
                return new WP_Error( 'cwm_category_limit_exceeded', __( 'سقف استفاده از این دسته‌بندی تکمیل شده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            $remaining_after = max( 0.0, $context['remaining'] - $amount );
        }

        $success = $wallet->transfer( $employee_id, $merchant_id, $amount );

        if ( ! $success ) {
            if ( $category_id > 0 ) {
                $this->category_manager->release_allowance( $employee_id, $category_id, $amount );
            }

            return new WP_Error(
                'cwm_insufficient_funds',
                __( 'موجودی کیف پول کاربر کافی نیست.', 'company-wallet-manager' ),
                [ 'status' => 400 ]
            );
        }

        $updated_balance = $wallet->get_balance( $employee_id );

        $metadata = [
            'confirmed_at'           => gmdate( 'c', $now ),
            'confirmed_by'           => $current_user->ID,
            'wallet_balance_before'  => $current_balance,
            'wallet_balance_after'   => $updated_balance,
        ];
        if ( $category_id > 0 ) {
            $metadata['category_id'] = $category_id;
            if ( null !== $remaining_after ) {
                $metadata['category_remaining_after'] = $remaining_after;
            }
        }

        $wpdb->update(
            $table,
            [
                'status'          => 'completed',
                'metadata'        => wp_json_encode( $metadata ),
                'failed_attempts' => 0,
            ],
            [ 'id' => $request_id ]
        );

        $logger = new Transaction_Logger();
        $logger->log( 'payment', $employee_id, $merchant_id, $amount, 'completed', [
            'related_request' => $request_id,
            'context'         => $is_employee ? 'employee_payment' : 'merchant_confirmed_payment',
            'metadata'        => $metadata,
        ] );

        $response = [
            'status'         => 'success',
            'message'        => __( 'Payment confirmed.', 'company-wallet-manager' ),
            'wallet_balance' => $updated_balance,
        ];

        if ( null !== $remaining_after ) {
            $response['category_remaining'] = $remaining_after;
        }

        return rest_ensure_response( $response );
    }

    /**
     * Request a payment from an employee.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function request_payment( WP_REST_Request $request ) {
        global $wpdb;

        $merchant_id = get_current_user_id();
        $identifiers = $this->parse_employee_identifiers( $request );
        $amount      = (float) $request->get_param( 'amount' );
        $category_id = (int) $request->get_param( 'category_id' );

        if ( $amount <= 0 ) {
            return new WP_Error( 'cwm_invalid_amount', __( 'Amount must be greater than zero.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( $category_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_category', __( 'A valid category is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $employee_id = $this->resolve_employee_user_from_identifiers( $identifiers['national_id'], $identifiers['mobile'] );
        if ( is_wp_error( $employee_id ) ) {
            return $employee_id;
        }

        $context = $this->build_payment_context( $merchant_id, $employee_id, $category_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        if ( ! $context['limit_defined'] ) {
            return new WP_Error( 'cwm_category_limit_missing', __( 'هیچ سقفی برای این دسته‌بندی ثبت نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( $amount > $context['available'] ) {
            return new WP_Error( 'cwm_category_limit_exceeded', __( 'مبلغ درخواستی از سقف مجاز این دسته‌بندی یا موجودی کیف پول بیشتر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $otp      = wp_rand( 100000, 999999 );
        $expires  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) + ( 5 * MINUTE_IN_SECONDS ) );
        $table    = $wpdb->prefix . 'cwm_payment_requests';
        $inserted = $wpdb->insert(
            $table,
            [
                'merchant_id'     => $merchant_id,
                'employee_id'     => $employee_id,
                'category_id'     => $category_id,
                'amount'          => $amount,
                'otp'             => (string) $otp,
                'otp_expires_at'  => $expires,
                'failed_attempts' => 0,
                'status'          => 'pending',
            ]
        );

        if ( false === $inserted ) {
            return new WP_Error( 'cwm_payment_request_failed', __( 'Unable to create payment request.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        $request_id = (int) $wpdb->insert_id;

        $phone      = get_user_meta( $employee_id, 'mobile', true );
        $sms        = new SMS_Handler();
        $sms->send_otp( $phone, $otp );

        $logger = new Transaction_Logger();
        $logger->log( 'payment_request', $merchant_id, $employee_id, $amount, 'pending', [
            'related_request' => $request_id,
            'context'         => 'merchant_payment_request',
            'metadata'        => [ 'expires_at' => $expires, 'category_id' => $category_id ],
        ] );

        return rest_ensure_response(
            [
                'status'          => 'success',
                'message'         => __( 'Payment request created.', 'company-wallet-manager' ),
                'request_id'      => $request_id,
                'remaining'       => $context['remaining'] - $amount,
                'wallet_balance'  => $context['wallet_balance'] - $amount,
            ]
        );
    }


    /**
     * Preview payment availability for a merchant before creating a request.
     */
    public function preview_payment( WP_REST_Request $request ) {
        $merchant_id = get_current_user_id();
        $identifiers = $this->parse_employee_identifiers( $request );
        $category_id = (int) $request->get_param( 'category_id' );

        if ( $category_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_category', __( 'A valid category is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $employee_id = $this->resolve_employee_user_from_identifiers( $identifiers['national_id'], $identifiers['mobile'] );
        if ( is_wp_error( $employee_id ) ) {
            return $employee_id;
        }

        $context = $this->build_payment_context( $merchant_id, $employee_id, $category_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }

        $employee = get_userdata( $employee_id );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'employee_id'      => $employee_id,
                    'employee_name'    => $employee ? $employee->display_name : '',
                    'category_id'      => $category_id,
                    'limit_defined'    => $context['limit_defined'],
                    'limit'            => $context['limit'],
                    'spent'            => $context['spent'],
                    'remaining'        => $context['remaining'],
                    'wallet_balance'   => $context['wallet_balance'],
                    'available_amount' => $context['available'],
                ],
            ]
        );
    }
    /**
     * Validate the JWT token and authenticate the user.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function validate_token( $request ) {
        $auth_header = $request->get_header( 'Authorization' );
        if ( ! $auth_header ) {
            return new \WP_Error( 'jwt_auth_no_auth_header', 'Authorization header not found.', array( 'status' => 403 ) );
        }

        list( $token ) = sscanf( $auth_header, 'Bearer %s' );
        if ( ! $token ) {
            return new \WP_Error( 'jwt_auth_bad_auth_header', 'Authorization header malformed.', array( 'status' => 403 ) );
        }

        // Ensure the secret key is defined in wp-config.php.
        if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) ) {
            return new \WP_Error( 'jwt_auth_secret_not_defined', 'JWT secret key is not defined in wp-config.php.', array( 'status' => 500 ) );
        }

        try {
            $decoded = \Firebase\JWT\JWT::decode( $token, new \Firebase\JWT\Key( JWT_AUTH_SECRET_KEY, 'HS256' ) );
            $token_user_id = $decoded->data->user->id ?? 0;
            
            error_log( sprintf( 'CWM Debug validate_token: Token decoded, user_id from token = %d', $token_user_id ) );
            
            if ( ! $token_user_id || $token_user_id <= 0 ) {
                return new \WP_Error( 'jwt_auth_invalid_token', 'User ID not found in token', array( 'status' => 403 ) );
            }
            
            wp_set_current_user( $token_user_id );
            
            // Verify the user was set correctly
            $current_user_id = get_current_user_id();
            error_log( sprintf( 'CWM Debug validate_token: After wp_set_current_user, get_current_user_id() = %d', $current_user_id ) );
            
            if ( $current_user_id !== $token_user_id ) {
                error_log( sprintf( 'CWM Debug validate_token: WARNING - user_id mismatch! Token has %d but get_current_user_id() returned %d', $token_user_id, $current_user_id ) );
            }
            
            return true;
        } catch ( \Exception $e ) {
            error_log( sprintf( 'CWM Debug validate_token: Exception - %s', $e->getMessage() ) );
            return new \WP_Error( 'jwt_auth_invalid_token', $e->getMessage(), array( 'status' => 403 ) );
        }
    }


    /**
     * Extract employee identifiers from a payment request.
     */
    protected function parse_employee_identifiers( WP_REST_Request $request ) {
        $national = $request->get_param( 'employee_national_id' );
        if ( null === $national ) {
            $national = $request->get_param( 'national_id' );
        }
        if ( null === $national ) {
            $national = $request->get_param( 'nid' );
        }

        $mobile = $request->get_param( 'mobile' );
        if ( null === $mobile ) {
            $mobile = $request->get_param( 'employee_mobile' );
        }

        return [
            'national_id' => sanitize_text_field( (string) $national ),
            'mobile'       => sanitize_text_field( (string) $mobile ),
        ];
    }

    /**
     * Resolve an employee user ID from identifiers.
     */
    protected function resolve_employee_user_from_identifiers( $national_id, $mobile = '' ) {
        $national_id = trim( (string) $national_id );
        $mobile      = trim( (string) $mobile );

        $employee = [];

        if ( $national_id ) {
            $employee = get_users(
                [
                    'meta_query' => [
                        'relation' => 'OR',
                        [
                            'key'   => 'cwm_national_id',
                            'value' => $national_id,
                        ],
                        [
                            'key'   => 'national_id',
                            'value' => $national_id,
                        ],
                    ],
                    'number' => 1,
                    'fields' => 'ID',
                ]
            );
        }

        if ( empty( $employee ) && $mobile ) {
            $by_login = get_user_by( 'login', $mobile );
            if ( $by_login ) {
                $employee = [ $by_login->ID ];
            } else {
                $found = get_users(
                    [
                        'meta_key'    => 'mobile',
                        'meta_value'  => $mobile,
                        'number'      => 1,
                        'fields'      => 'ID',
                        'count_total' => false,
                    ]
                );
                if ( $found ) {
                    $employee = $found;
                }
            }
        }

        if ( empty( $employee ) ) {
            return new WP_Error( 'employee_not_found', __( 'کارمند با مشخصات وارد‌شده یافت نشد. (کد ملی/شماره همراه)', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return (int) $employee[0];
    }

    /**
     * Build the payment context including remaining allowance and wallet balance.
     */
    protected function build_payment_context( $merchant_id, $employee_id, $category_id ) {
        $categories = $this->category_manager->get_merchant_categories( $merchant_id );
        $assigned   = array_map( 'intval', wp_list_pluck( $categories, 'id' ) );

        if ( $category_id > 0 && ! in_array( (int) $category_id, $assigned, true ) ) {
            return new WP_Error( 'cwm_category_not_assigned', __( 'این دسته‌بندی برای پذیرنده فعال نیست.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $limits = $this->category_manager->get_employee_limits( $employee_id );
        $entry  = null;
        foreach ( $limits as $limit ) {
            if ( (int) $limit['category_id'] === (int) $category_id ) {
                $entry = $limit;
                break;
            }
        }

        // If no employee-specific limit exists, check company category caps
        if ( null === $entry ) {
            $company_id = (int) get_user_meta( $employee_id, '_cwm_company_id', true );
            error_log( "build_payment_context - Employee $employee_id, Company ID from meta: $company_id, Category ID: $category_id" );
            
            if ( $company_id > 0 ) {
                $company_caps = $this->category_manager->get_company_category_caps( $company_id );
                error_log( "build_payment_context - Found " . count( $company_caps ) . " company caps for company $company_id" );
                
                foreach ( $company_caps as $cap ) {
                    if ( (int) $cap['category_id'] === (int) $category_id ) {
                        error_log( "build_payment_context - Found matching cap: category_id={$cap['category_id']}, limit_type={$cap['limit_type']}, limit_value={$cap['limit_value']}" );
                        
                        // Only use amount-type caps as fallback (percentage would need employee balance)
                        // Check both limit_value and cap (for backward compatibility)
                        $limit_value = isset( $cap['limit_value'] ) ? $cap['limit_value'] : null;
                        $cap_value = null;
                        
                        if ( $limit_value !== null && $limit_value > 0 ) {
                            $cap_value = (float) $limit_value;
                        } elseif ( isset( $cap['cap'] ) && $cap['cap'] > 0 ) {
                            $cap_value = (float) $cap['cap'];
                        }
                        
                        error_log( "build_payment_context - Cap value calculation: limit_value={$limit_value}, cap={$cap['cap']}, final_cap_value={$cap_value}" );
                        
                        if ( $cap['limit_type'] === 'amount' && $cap_value !== null && $cap_value > 0 ) {
                            $entry = [
                                'category_id' => (int) $cap['category_id'],
                                'limit' => $cap_value,
                                'spent' => 0.0,
                            ];
                            error_log( "build_payment_context - Using company cap as limit: " . $entry['limit'] );
                            break;
                        } else {
                            error_log( "build_payment_context - Cap not usable: limit_type={$cap['limit_type']}, limit_value={$cap['limit_value']}" );
                        }
                    }
                }
                
                if ( null === $entry ) {
                    error_log( "build_payment_context - No matching company cap found for category $category_id" );
                }
            } else {
                error_log( "build_payment_context - No company ID found for employee $employee_id" );
            }
        }

        $limit_defined = null !== $entry;
        $limit_amount  = $entry ? (float) $entry['limit'] : 0.0;
        $spent_amount  = $entry ? (float) $entry['spent'] : 0.0;
        $remaining     = max( 0.0, $limit_amount - $spent_amount );

        $wallet         = new Wallet_System();
        $wallet_balance = $wallet->get_balance( $employee_id );

        return [
            'limit_defined'   => $limit_defined,
            'limit'           => $limit_amount,
            'spent'           => $spent_amount,
            'remaining'       => $remaining,
            'wallet_balance'  => $wallet_balance,
            'available'       => min( $remaining, $wallet_balance ),
        ];
    }

    /**
     * Resolve the company context for an employee ensuring the requester has access.
     */
    protected function resolve_employee_company_context( $employee_id ) {
        $company_id = (int) get_user_meta( $employee_id, '_cwm_company_id', true );

        if ( $company_id <= 0 ) {
            return new WP_Error( 'cwm_employee_company_missing', __( 'این کارمند به هیچ شرکتی متصل نیست.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $current_user = wp_get_current_user();

        if ( $this->user_has_role( $current_user, 'company' ) ) {
            $current_company_id = $this->get_company_post_id_for_user( $current_user->ID );
            if ( $current_company_id && (int) $current_company_id !== $company_id ) {
                return new WP_Error( 'cwm_forbidden_employee', __( 'شما دسترسی به مدیریت این کارمند ندارید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
            }
        }

        return $company_id;
    }

    /**
     * Retrieve the company post ID linked to a user account.
     */
    protected function get_company_post_id_for_user( $user_id ) {
        $posts = get_posts(
            [
                'post_type'      => 'cwm_company',
                'post_status'    => 'any',
                'meta_key'       => '_cwm_company_user_id',
                'meta_value'     => $user_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]
        );

        return $posts ? (int) $posts[0] : 0;
    }

    /**
     * Check if the user is a merchant.
     *
     * @return bool
     */
    public function merchant_permission_check( WP_REST_Request $request ) {
        $token_validation = $this->validate_token( $request );
        if ( is_wp_error( $token_validation ) ) {
            error_log( sprintf( 'CWM Debug merchant_permission_check: Token validation failed - %s', $token_validation->get_error_message() ) );
            return false;
        }

        $user = wp_get_current_user();
        $user_id = $user ? $user->ID : 0;
        
        error_log( sprintf( 'CWM Debug merchant_permission_check: user_id = %d, roles = %s', $user_id, implode( ', ', $user->roles ?? [] ) ) );

        $has_permission = $this->user_has_role( $user, 'merchant' ) || $this->user_has_role( $user, 'administrator' ) || user_can( $user, 'manage_wallets' );
        
        error_log( sprintf( 'CWM Debug merchant_permission_check: has_permission = %s', $has_permission ? 'true' : 'false' ) );
        
        return $has_permission;
    }

    /**
     * Allow merchants or employees to access a route.
     */
    public function merchant_or_employee_permission_check( WP_REST_Request $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }

        $user = wp_get_current_user();

        return $this->user_has_role( $user, 'merchant' ) || $this->user_has_role( $user, 'employee' );
    }

    /**
     * Check if the user is an employee.
     *
     * @return bool
     */
    public function employee_permission_check( WP_REST_Request $request ) {
        $token_validation = $this->validate_token( $request );
        if ( is_wp_error( $token_validation ) ) {
            error_log( sprintf( 'CWM Debug employee_permission_check: Token validation failed - %s', $token_validation->get_error_message() ) );
            return false;
        }

        $user = wp_get_current_user();
        $user_id = $user ? $user->ID : 0;
        $has_role = $this->user_has_role( $user, 'employee' );
        
        error_log( sprintf( 'CWM Debug employee_permission_check: user_id=%d, has_role=%s, roles=%s', 
            $user_id, 
            $has_role ? 'true' : 'false',
            implode( ', ', $user->roles ?? [] )
        ) );

        return $has_role;
    }

    /**
     * Check if the user is a company or an admin.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function company_permission_check( WP_REST_Request $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }
        $user = wp_get_current_user();

        return $this->user_has_role( $user, 'company' ) || user_can( $user, 'manage_wallets' );
    }

    /**
     * Check if the user is a merchant or a finance officer.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function merchant_or_finance_officer_permission_check( WP_REST_Request $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }
        $user = wp_get_current_user();

        return $this->user_has_role( $user, 'merchant' ) || $this->user_has_role( $user, 'finance_officer' ) || user_can( $user, 'approve_payouts' );
    }

    /**
     * Check if any user is authenticated.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function any_authenticated_user_permission_check( WP_REST_Request $request ) {
        return ! is_wp_error( $this->validate_token( $request ) );
    }

    /**
     * Determine whether the current user has the given role.
     */
    protected function user_has_role( $user, $role ) {
        if ( ! $user ) {
            return false;
        }

        return in_array( $role, (array) $user->roles, true );
    }

    /**
     * Write debug information to the error log when WP_DEBUG is enabled.
     */
    protected function debug_log( $message, array $context = [] ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( ! empty( $context ) ) {
                $message .= ' ' . wp_json_encode( $context );
            }

            error_log( '[CWM] ' . $message );
        }
    }

    // ==================== Product Categories Methods ====================

    /**
     * List all product categories (shared across all merchants).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_product_categories( WP_REST_Request $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cwm_product_categories';

        $categories = $wpdb->get_results(
            "SELECT id, name, slug, description, created_at, updated_at FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => array_map( function( $cat ) {
                return [
                    'id'          => (int) $cat['id'],
                    'name'        => $cat['name'],
                    'slug'        => $cat['slug'],
                    'description' => $cat['description'] ?? '',
                ];
            }, $categories ?: [] ),
        ] );
    }

    /**
     * Create a new product category.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_product_category( WP_REST_Request $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cwm_product_categories';

        $name        = sanitize_text_field( $request->get_param( 'name' ) );
        $description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
        $slug        = sanitize_title( $name );

        if ( empty( $name ) ) {
            return new WP_Error( 'cwm_invalid_name', __( 'نام دسته‌بندی الزامی است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Check if slug exists
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );
        if ( $existing ) {
            $slug = $slug . '-' . time();
        }

        $result = $wpdb->insert(
            $table,
            [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
            ],
            [ '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            return new WP_Error( 'cwm_db_error', __( 'خطا در ایجاد دسته‌بندی.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'دسته‌بندی با موفقیت ایجاد شد.', 'company-wallet-manager' ),
            'data'    => [
                'id'          => $wpdb->insert_id,
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
            ],
        ] );
    }

    // ==================== Products Methods ====================

    /**
     * List products for current merchant.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_merchant_products( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Debug: Log user_id for troubleshooting
        error_log( sprintf( 'CWM Debug list_merchant_products: user_id = %d', $user_id ) );
        
        // Validate user_id
        if ( ! $user_id || $user_id <= 0 ) {
            error_log( 'CWM Debug list_merchant_products: Invalid user_id, returning error' );
            return new WP_Error( 'cwm_invalid_user', __( 'کاربر معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }
        
        $products_table = $wpdb->prefix . 'cwm_products';
        $categories_table = $wpdb->prefix . 'cwm_product_categories';
        
        // Ensure products table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$products_table}'" );
        if ( $products_table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$products_table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        } else {
            // Table exists, ensure status column exists
            $status_column = $wpdb->get_results( "SHOW COLUMNS FROM {$products_table} LIKE 'status'" );
            if ( empty( $status_column ) ) {
                $wpdb->query( "ALTER TABLE {$products_table} ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER is_featured" );
                $wpdb->query( "ALTER TABLE {$products_table} ADD KEY status (status)" );
                // Update existing products with NULL or empty status to 'active'
                $wpdb->query( "UPDATE {$products_table} SET status = 'active' WHERE status IS NULL OR status = ''" );
            } else {
                // Ensure existing products with NULL or empty status are set to 'active'
                $wpdb->query( $wpdb->prepare( "UPDATE {$products_table} SET status = 'active' WHERE (status IS NULL OR status = '') AND merchant_id = %d", $user_id ) );
            }
        }

        // Update any products with NULL or empty status to 'active' before querying
        $wpdb->query( $wpdb->prepare( "UPDATE {$products_table} SET status = 'active' WHERE (status IS NULL OR status = '') AND merchant_id = %d", $user_id ) );

        // Debug: Check total products for this merchant_id
        $total_products = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$products_table} WHERE merchant_id = %d", $user_id ) );
        error_log( sprintf( 'CWM Debug list_merchant_products: Total products for merchant_id %d = %d', $user_id, $total_products ) );
        
        // Debug: Check products with active status
        $active_products = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$products_table} WHERE merchant_id = %d AND status = %s", $user_id, 'active' ) );
        error_log( sprintf( 'CWM Debug list_merchant_products: Active products for merchant_id %d = %d', $user_id, $active_products ) );
        
        // Debug: Check user_id value
        error_log( sprintf( 'CWM Debug list_merchant_products: user_id = %d, get_current_user_id() = %d', $user_id, get_current_user_id() ) );

        // Check if categories table exists
        $categories_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$categories_table}'" );
        error_log( sprintf( 'CWM Debug list_merchant_products: Categories table exists: %s', $categories_table === $categories_table_exists ? 'yes' : 'no' ) );
        
        // Build the query - always use simple query first to ensure we get products
        // Then enhance with category info if categories table exists
        $products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$products_table} WHERE merchant_id = %d AND status = %s ORDER BY created_at DESC",
                $user_id,
                'active'
            ),
            ARRAY_A
        );
        
        error_log( sprintf( 'CWM Debug list_merchant_products: Simple query returned %d products for merchant_id %d', count( $products ), $user_id ) );
        
        // If we have products and categories table exists, add category info
        if ( ! empty( $products ) && $categories_table === $categories_table_exists ) {
            // Get category info for products that have category_id
            $category_ids = array_filter( array_column( $products, 'product_category_id' ) );
            if ( ! empty( $category_ids ) ) {
                $category_ids_placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
                $categories = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, name, slug FROM {$categories_table} WHERE id IN ({$category_ids_placeholders})",
                        ...$category_ids
                    ),
                    ARRAY_A
                );
                
                // Create a map of category_id => category info
                $category_map = [];
                foreach ( $categories as $cat ) {
                    $category_map[ $cat['id'] ] = [
                        'name' => $cat['name'],
                        'slug' => $cat['slug']
                    ];
                }
                
                // Add category info to products
                foreach ( $products as &$product ) {
                    $cat_id = $product['product_category_id'] ?? null;
                    if ( $cat_id && isset( $category_map[ $cat_id ] ) ) {
                        $product['category_name'] = $category_map[ $cat_id ]['name'];
                        $product['category_slug'] = $category_map[ $cat_id ]['slug'];
                    } else {
                        $product['category_name'] = null;
                        $product['category_slug'] = null;
                    }
                }
                unset( $product );
            } else {
                // No categories, add null fields
                foreach ( $products as &$product ) {
                    $product['category_name'] = null;
                    $product['category_slug'] = null;
                }
                unset( $product );
            }
        } elseif ( ! empty( $products ) ) {
            // Categories table doesn't exist, add null fields
            foreach ( $products as &$product ) {
                $product['category_name'] = null;
                $product['category_slug'] = null;
            }
            unset( $product );
        }
        
        error_log( sprintf( 'CWM Debug list_merchant_products: Final products count: %d', count( $products ) ) );
        
        // If no products found, check what's in the database
        if ( empty( $products ) ) {
            // Check all products to see what merchant_ids exist
            $all_products_sample = $wpdb->get_results(
                "SELECT id, merchant_id, name, status FROM {$products_table} ORDER BY created_at DESC LIMIT 10",
                ARRAY_A
            );
            error_log( sprintf( 
                'CWM Debug: Sample of all products in database: %s', 
                json_encode( $all_products_sample, JSON_UNESCAPED_UNICODE )
            ) );
            
            $orphaned_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$products_table} WHERE (merchant_id = 0 OR merchant_id IS NULL) AND (status = 'active' OR status IS NULL)" );
            
            if ( $orphaned_count > 0 ) {
                error_log( sprintf( 
                    'CWM Debug: Merchant %d has no products, but found %d orphaned products (merchant_id = 0 or NULL)', 
                    $user_id, 
                    $orphaned_count 
                ) );
            }
            
            // Also check if there are products with different merchant_id values
            $all_merchant_ids = $wpdb->get_col( "SELECT DISTINCT merchant_id FROM {$products_table} WHERE status = 'active' ORDER BY merchant_id" );
            error_log( sprintf( 
                'CWM Debug: Found products with merchant_ids: %s', 
                implode( ', ', $all_merchant_ids ) 
            ) );
            
            // Check if there are products with the exact merchant_id we're looking for
            $direct_check = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$products_table} WHERE merchant_id = %d", $user_id ) );
            error_log( sprintf( 
                'CWM Debug: Direct count check - products with merchant_id = %d: %d', 
                $user_id, 
                $direct_check 
            ) );
        }

        // Ensure products is an array
        $products_array = is_array( $products ) ? $products : [];
        
        error_log( sprintf( 'CWM Debug list_merchant_products: Returning %d products for merchant_id %d', count( $products_array ), $user_id ) );
        
        $response_data = [
            'status' => 'success',
            'data'   => array_map( function( $product ) {
                return [
                    'id'                      => (int) $product['id'],
                    'merchant_id'             => (int) $product['merchant_id'],
                    'product_category_id'     => $product['product_category_id'] ? (int) $product['product_category_id'] : null,
                    'category_name'           => $product['category_name'] ?? null,
                    'category_slug'           => $product['category_slug'] ?? null,
                    'name'                    => $product['name'],
                    'description'             => $product['description'] ?? '',
                    'price'                   => (float) $product['price'],
                    'image'                   => $product['image'] ?? null,
                    'stock_quantity'          => (int) $product['stock_quantity'],
                    'online_purchase_enabled' => (bool) $product['online_purchase_enabled'],
                    'is_featured'             => isset( $product['is_featured'] ) ? (bool) $product['is_featured'] : false,
                    'status'                  => $product['status'],
                    'created_at'              => $product['created_at'],
                    'updated_at'              => $product['updated_at'],
                ];
            }, $products_array ),
        ];
        
        error_log( sprintf( 'CWM Debug list_merchant_products: Response data structure - status: %s, data count: %d', $response_data['status'], count( $response_data['data'] ) ) );
        
        return rest_ensure_response( $response_data );
    }

    /**
     * Create a new product.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_product( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Validate user_id
        if ( ! $user_id || $user_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_user', __( 'کاربر معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }
        
        $table = $wpdb->prefix . 'cwm_products';
        
        // Ensure table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( $table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        } else {
            // Table exists, ensure is_featured column exists
            $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'is_featured'" );
            if ( empty( $column_exists ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER online_purchase_enabled" );
                $wpdb->query( "ALTER TABLE {$table} ADD KEY is_featured (is_featured)" );
            }
            // Ensure status column exists
            $status_column = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'status'" );
            if ( empty( $status_column ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER is_featured" );
                $wpdb->query( "ALTER TABLE {$table} ADD KEY status (status)" );
                // Update existing products with NULL or empty status to 'active'
                $wpdb->query( "UPDATE {$table} SET status = 'active' WHERE status IS NULL OR status = ''" );
            } else {
                // Ensure existing products with NULL or empty status are set to 'active'
                $wpdb->query( "UPDATE {$table} SET status = 'active' WHERE status IS NULL OR status = ''" );
            }
        }

        $name                    = sanitize_text_field( $request->get_param( 'name' ) );
        $description             = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
        $price                   = floatval( $request->get_param( 'price' ) ?? 0 );
        $product_category_id     = $request->get_param( 'product_category_id' ) ? (int) $request->get_param( 'product_category_id' ) : null;
        $stock_quantity          = (int) ( $request->get_param( 'stock_quantity' ) ?? 0 );
        $online_purchase_enabled = (bool) $request->get_param( 'online_purchase_enabled' );
        $is_featured             = (bool) ( $request->get_param( 'is_featured' ) ?? false );

        if ( empty( $name ) ) {
            return new WP_Error( 'cwm_invalid_name', __( 'نام محصول الزامی است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Handle image upload
        $image_url = null;
        if ( ! empty( $_FILES['image'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload( $_FILES['image'], [ 'test_form' => false ] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                $image_url = $upload['url'];
            }
        }

        // Double-check user_id before insert
        if ( ! $user_id || $user_id <= 0 ) {
            $user = wp_get_current_user();
            $user_id = $user && $user->ID > 0 ? $user->ID : 0;
        }
        
        if ( ! $user_id || $user_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_user', __( 'شناسه کاربر معتبر نیست. لطفاً دوباره وارد شوید.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }

        $result = $wpdb->insert(
            $table,
            [
                'merchant_id'             => $user_id,
                'product_category_id'     => $product_category_id,
                'name'                    => $name,
                'description'             => $description,
                'price'                   => $price,
                'image'                   => $image_url,
                'stock_quantity'          => $stock_quantity,
                'online_purchase_enabled' => $online_purchase_enabled ? 1 : 0,
                'is_featured'             => $is_featured ? 1 : 0,
                'status'                  => 'active',
            ],
            [ '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s' ]
        );

        if ( false === $result ) {
            $error_message = $wpdb->last_error ? $wpdb->last_error : __( 'خطا در ایجاد محصول.', 'company-wallet-manager' );
            return new WP_Error( 'cwm_db_error', $error_message, [ 'status' => 500 ] );
        }
        
        // Verify the inserted product has correct merchant_id
        $inserted_product = $wpdb->get_row(
            $wpdb->prepare( "SELECT merchant_id FROM {$table} WHERE id = %d", $wpdb->insert_id ),
            ARRAY_A
        );
        
        if ( $inserted_product && (int) $inserted_product['merchant_id'] !== (int) $user_id ) {
            // Fix the merchant_id if it was incorrectly stored
            $wpdb->update(
                $table,
                [ 'merchant_id' => $user_id ],
                [ 'id' => $wpdb->insert_id ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'محصول با موفقیت ایجاد شد.', 'company-wallet-manager' ),
            'data'    => [
                'id' => $wpdb->insert_id,
            ],
        ] );
    }

    /**
     * Get a single product.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_product( WP_REST_Request $request ) {
        global $wpdb;
        $product_id = (int) $request->get_param( 'id' );
        $products_table = $wpdb->prefix . 'cwm_products';
        $categories_table = $wpdb->prefix . 'cwm_product_categories';
        
        // Ensure products table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$products_table}'" );
        if ( $products_table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$products_table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        }

        $product = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.*, pc.name as category_name, pc.slug as category_slug 
                FROM {$products_table} p 
                LEFT JOIN {$categories_table} pc ON p.product_category_id = pc.id 
                WHERE p.id = %d",
                $product_id
            ),
            ARRAY_A
        );

        if ( ! $product ) {
            return new WP_Error( 'cwm_product_not_found', __( 'محصول یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'id'                      => (int) $product['id'],
                'merchant_id'             => (int) $product['merchant_id'],
                'product_category_id'     => $product['product_category_id'] ? (int) $product['product_category_id'] : null,
                'category_name'           => $product['category_name'] ?? null,
                'category_slug'           => $product['category_slug'] ?? null,
                'name'                    => $product['name'],
                'description'             => $product['description'] ?? '',
                'price'                   => (float) $product['price'],
                'image'                   => $product['image'] ?? null,
                'stock_quantity'          => (int) $product['stock_quantity'],
                'online_purchase_enabled' => (bool) $product['online_purchase_enabled'],
                'status'                  => $product['status'],
                'created_at'              => $product['created_at'],
                'updated_at'              => $product['updated_at'],
            ],
        ] );
    }

    /**
     * Update a product.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_product( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $is_admin = $this->user_has_role( $current_user, 'administrator' ) || user_can( $current_user, 'manage_wallets' );
        $product_id = (int) $request->get_param( 'id' );
        $table = $wpdb->prefix . 'cwm_products';
        
        // Ensure table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( $table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        }

        // Verify ownership
        $product = $wpdb->get_row( $wpdb->prepare( "SELECT merchant_id FROM {$table} WHERE id = %d", $product_id ), ARRAY_A );
        if ( ! $product || ( ! $is_admin && (int) $product['merchant_id'] !== $user_id ) ) {
            return new WP_Error( 'cwm_forbidden', __( 'شما مجاز به ویرایش این محصول نیستید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $update_data = [];
        $update_format = [];

        if ( $request->has_param( 'name' ) ) {
            $update_data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
            $update_format[] = '%s';
        }

        if ( $request->has_param( 'description' ) ) {
            $update_data['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
            $update_format[] = '%s';
        }

        if ( $request->has_param( 'price' ) ) {
            $update_data['price'] = floatval( $request->get_param( 'price' ) );
            $update_format[] = '%f';
        }

        if ( $request->has_param( 'product_category_id' ) ) {
            $update_data['product_category_id'] = $request->get_param( 'product_category_id' ) ? (int) $request->get_param( 'product_category_id' ) : null;
            $update_format[] = '%d';
        }

        if ( $request->has_param( 'stock_quantity' ) ) {
            $update_data['stock_quantity'] = (int) $request->get_param( 'stock_quantity' );
            $update_format[] = '%d';
        }

        if ( $request->has_param( 'online_purchase_enabled' ) ) {
            $update_data['online_purchase_enabled'] = (bool) $request->get_param( 'online_purchase_enabled' ) ? 1 : 0;
            $update_format[] = '%d';
        }

        if ( $request->has_param( 'is_featured' ) ) {
            $update_data['is_featured'] = (bool) $request->get_param( 'is_featured' ) ? 1 : 0;
            $update_format[] = '%d';
        }

        if ( $request->has_param( 'status' ) ) {
            $update_data['status'] = sanitize_text_field( $request->get_param( 'status' ) );
            $update_format[] = '%s';
        }

        // Handle image upload
        if ( ! empty( $_FILES['image'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload( $_FILES['image'], [ 'test_form' => false ] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                $update_data['image'] = $upload['url'];
                $update_format[] = '%s';
            }
        }

        if ( empty( $update_data ) ) {
            return new WP_Error( 'cwm_no_data', __( 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            [ 'id' => $product_id ],
            $update_format,
            [ '%d' ]
        );

        if ( false === $result ) {
            return new WP_Error( 'cwm_db_error', __( 'خطا در به‌روزرسانی محصول.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'محصول با موفقیت به‌روزرسانی شد.', 'company-wallet-manager' ),
        ] );
    }

    /**
     * Delete a product.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_product( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $is_admin = $this->user_has_role( $current_user, 'administrator' ) || user_can( $current_user, 'manage_wallets' );
        $product_id = (int) $request->get_param( 'id' );
        $table = $wpdb->prefix . 'cwm_products';
        
        // Ensure table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( $table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        }

        // Verify ownership
        $product = $wpdb->get_row( $wpdb->prepare( "SELECT merchant_id FROM {$table} WHERE id = %d", $product_id ), ARRAY_A );
        if ( ! $product || ( ! $is_admin && (int) $product['merchant_id'] !== $user_id ) ) {
            return new WP_Error( 'cwm_forbidden', __( 'شما مجاز به حذف این محصول نیستید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $result = $wpdb->delete( $table, [ 'id' => $product_id ], [ '%d' ] );

        if ( false === $result ) {
            return new WP_Error( 'cwm_db_error', __( 'خطا در حذف محصول.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'محصول با موفقیت حذف شد.', 'company-wallet-manager' ),
        ] );
    }

    /**
     * Get online purchase enabled products for employees.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_online_products( WP_REST_Request $request ) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'cwm_products';
        $categories_table = $wpdb->prefix . 'cwm_product_categories';
        $merchants_table = $wpdb->prefix . 'users';
        
        // Ensure products table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$products_table}'" );
        if ( $products_table !== $table_exists ) {
            // Table doesn't exist, create it
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$products_table} (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    merchant_id BIGINT(20) UNSIGNED NOT NULL,
                    product_category_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    name VARCHAR(191) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(20, 6) NOT NULL DEFAULT 0,
                    image VARCHAR(500) NULL,
                    stock_quantity INT(11) NOT NULL DEFAULT 0,
                    online_purchase_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    is_featured TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY merchant_id (merchant_id),
                    KEY product_category_id (product_category_id),
                    KEY online_purchase_enabled (online_purchase_enabled),
                    KEY is_featured (is_featured),
                    KEY status (status)
            ) {$charset_collate};";
            dbDelta( $sql );
        }

        $products = $wpdb->get_results(
            "SELECT p.*, pc.name as category_name, pc.slug as category_slug, 
                    u.display_name as merchant_name,
                    (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = p.merchant_id AND meta_key = '_cwm_store_name') as store_name
            FROM {$products_table} p 
            LEFT JOIN {$categories_table} pc ON p.product_category_id = pc.id 
            LEFT JOIN {$merchants_table} u ON p.merchant_id = u.ID
            WHERE p.online_purchase_enabled = 1 AND p.status = 'active' AND p.stock_quantity > 0
            ORDER BY p.is_featured DESC, p.created_at DESC",
            ARRAY_A
        );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => array_map( function( $product ) {
                return [
                    'id'                      => (int) $product['id'],
                    'merchant_id'             => (int) $product['merchant_id'],
                    'merchant_name'           => $product['merchant_name'] ?? '',
                    'store_name'              => $product['store_name'] ?? '',
                    'product_category_id'     => $product['product_category_id'] ? (int) $product['product_category_id'] : null,
                    'category_name'           => $product['category_name'] ?? null,
                    'category_slug'           => $product['category_slug'] ?? null,
                    'name'                    => $product['name'],
                    'description'             => $product['description'] ?? '',
                    'price'                   => (float) $product['price'],
                    'image'                   => $product['image'] ?? null,
                    'stock_quantity'          => (int) $product['stock_quantity'],
                    'online_purchase_enabled' => true,
                    'is_featured'             => isset( $product['is_featured'] ) ? (bool) $product['is_featured'] : false,
                ];
            }, $products ?: [] ),
        ] );
    }

    // ==================== Cart Methods ====================

    /**
     * Get cart items for current employee.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_cart( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        error_log( sprintf( 'CWM Debug get_cart: Starting - user_id=%d', $user_id ) );
        
        if ( $user_id === 0 ) {
            error_log( 'CWM Debug get_cart: WARNING - user_id is 0! User may not be authenticated.' );
            $current_user = wp_get_current_user();
            error_log( sprintf( 'CWM Debug get_cart: wp_get_current_user() - ID=%d, roles=%s', 
                $current_user->ID ?? 0,
                implode( ', ', $current_user->roles ?? [] )
            ) );
        }
        
        $cart_table = $wpdb->prefix . 'cwm_cart_items';
        $products_table = $wpdb->prefix . 'cwm_products';
        $categories_table = $wpdb->prefix . 'cwm_product_categories';

        // First, get all cart items for the user (simple query without JOIN)
        $cart_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$cart_table} WHERE employee_id = %d ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );
        
        error_log( sprintf( 'CWM Debug get_cart: Found %d cart items in cart table for user_id %d', count( $cart_items ?: [] ), $user_id ) );
        
        if ( empty( $cart_items ) ) {
            error_log( sprintf( 'CWM Debug get_cart: No cart items found for employee_id %d', $user_id ) );
            return rest_ensure_response( [
                'status' => 'success',
                'data'   => [],
            ] );
        }
        
        // Now fetch product details for each cart item
        $items = [];
        foreach ( $cart_items as $cart_item ) {
            $product = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$products_table} WHERE id = %d", $cart_item['product_id'] ),
                ARRAY_A
            );
            
            if ( ! $product ) {
                error_log( sprintf( 'CWM Debug get_cart: Product %d not found for cart item %d', $cart_item['product_id'], $cart_item['id'] ) );
                continue; // Skip this item if product doesn't exist
            }
            
            // Get category name if exists
            $category_name = null;
            if ( ! empty( $product['product_category_id'] ) ) {
                $category = $wpdb->get_row(
                    $wpdb->prepare( "SELECT name FROM {$categories_table} WHERE id = %d", $product['product_category_id'] ),
                    ARRAY_A
                );
                $category_name = $category['name'] ?? null;
            }
            
            // Get store name
            $store_name = get_user_meta( $product['merchant_id'], '_cwm_store_name', true );
            if ( empty( $store_name ) ) {
                $store_name = '';
            }
            
            // Combine cart item with product data
            $items[] = array_merge( $cart_item, [
                'product_name'        => $product['name'],
                'price'               => $product['price'],
                'image'               => $product['image'],
                'stock_quantity'      => $product['stock_quantity'],
                'merchant_id'         => $product['merchant_id'],
                'product_category_id' => $product['product_category_id'],
                'category_name'       => $category_name,
                'store_name'          => $store_name,
            ] );
        }
        
        error_log( sprintf( 'CWM Debug get_cart: Processed %d items after merging product data', count( $items ) ) );
        
        // Log each item for debugging
        if ( ! empty( $items ) ) {
            foreach ( $items as $item ) {
                error_log( sprintf( 'CWM Debug get_cart item: id=%d, product_id=%d, product_name=%s, quantity=%d', 
                    $item['id'] ?? 0, 
                    $item['product_id'] ?? 0, 
                    $item['product_name'] ?? 'N/A',
                    $item['quantity'] ?? 0
                ) );
            }
        }
        
        $mapped_items = array_map( function( $item ) {
            return [
                'id'                  => (int) $item['id'],
                'product_id'          => (int) $item['product_id'],
                'quantity'            => (int) $item['quantity'],
                'product_name'        => $item['product_name'],
                'price'               => (float) $item['price'],
                'subtotal'            => (float) $item['price'] * (int) $item['quantity'],
                'image'               => $item['image'] ?? null,
                'stock_quantity'      => (int) $item['stock_quantity'],
                'merchant_id'         => (int) $item['merchant_id'],
                'store_name'          => $item['store_name'] ?? '',
                'product_category_id' => $item['product_category_id'] ? (int) $item['product_category_id'] : null,
                'category_name'       => $item['category_name'] ?? null,
            ];
        }, $items ?: [] );
        
        error_log( sprintf( 'CWM Debug get_cart: Returning %d mapped items', count( $mapped_items ) ) );
        
        $response_data = [
            'status' => 'success',
            'data'   => $mapped_items,
        ];
        
        error_log( sprintf( 'CWM Debug get_cart: Response structure - status: %s, data count: %d', 
            $response_data['status'], 
            count( $response_data['data'] ) 
        ) );

        return rest_ensure_response( $response_data );
    }

    /**
     * Add item to cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function add_to_cart( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $product_id = (int) $request->get_param( 'product_id' );
        $quantity = (int) ( $request->get_param( 'quantity' ) ?? 1 );

        error_log( sprintf( 'CWM Debug add_to_cart: Starting - user_id=%d, product_id=%d, quantity=%d', $user_id, $product_id, $quantity ) );
        
        if ( $user_id === 0 ) {
            error_log( 'CWM Debug add_to_cart: ERROR - user_id is 0! User may not be authenticated.' );
            $current_user = wp_get_current_user();
            error_log( sprintf( 'CWM Debug add_to_cart: wp_get_current_user() - ID=%d, roles=%s', 
                $current_user->ID ?? 0,
                implode( ', ', $current_user->roles ?? [] )
            ) );
            return new WP_Error( 'cwm_not_authenticated', __( 'برای افزودن به سبد خرید ابتدا وارد شوید.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }

        $products_table = $wpdb->prefix . 'cwm_products';
        $cart_table = $wpdb->prefix . 'cwm_cart_items';

        // Verify product exists and is available for online purchase
        $product = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, stock_quantity, online_purchase_enabled, status FROM {$products_table} WHERE id = %d",
                $product_id
            ),
            ARRAY_A
        );

        if ( ! $product ) {
            error_log( sprintf( 'CWM Debug add_to_cart: Product %d not found', $product_id ) );
            return new WP_Error( 'cwm_product_not_found', __( 'محصول یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        error_log( sprintf( 'CWM Debug add_to_cart: Product found - online_purchase_enabled=%d, status=%s, stock=%d', 
            $product['online_purchase_enabled'], 
            $product['status'], 
            $product['stock_quantity'] 
        ) );

        if ( ! $product['online_purchase_enabled'] || $product['status'] !== 'active' ) {
            error_log( sprintf( 'CWM Debug add_to_cart: Product not available for online purchase' ) );
            return new WP_Error( 'cwm_product_not_available', __( 'این محصول برای خرید آنلاین در دسترس نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( $quantity > (int) $product['stock_quantity'] ) {
            error_log( sprintf( 'CWM Debug add_to_cart: Insufficient stock - requested=%d, available=%d', $quantity, $product['stock_quantity'] ) );
            return new WP_Error( 'cwm_insufficient_stock', __( 'موجودی محصول کافی نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Check if item already in cart
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, quantity FROM {$cart_table} WHERE employee_id = %d AND product_id = %d",
                $user_id,
                $product_id
            ),
            ARRAY_A
        );

        if ( $existing ) {
            error_log( sprintf( 'CWM Debug add_to_cart: Item already exists in cart - id=%d, current_quantity=%d', $existing['id'], $existing['quantity'] ) );
            $new_quantity = (int) $existing['quantity'] + $quantity;
            if ( $new_quantity > (int) $product['stock_quantity'] ) {
                error_log( sprintf( 'CWM Debug add_to_cart: Insufficient stock after adding - new_quantity=%d, available=%d', $new_quantity, $product['stock_quantity'] ) );
                return new WP_Error( 'cwm_insufficient_stock', __( 'موجودی محصول کافی نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            $update_result = $wpdb->update(
                $cart_table,
                [ 'quantity' => $new_quantity ],
                [ 'id' => (int) $existing['id'] ],
                [ '%d' ],
                [ '%d' ]
            );
            
            error_log( sprintf( 'CWM Debug add_to_cart: Update result=%s, rows_affected=%d', $update_result !== false ? 'success' : 'failed', $update_result ) );
            if ( $update_result === false ) {
                error_log( sprintf( 'CWM Debug add_to_cart: Update error - %s', $wpdb->last_error ) );
            }
        } else {
            error_log( sprintf( 'CWM Debug add_to_cart: Inserting new item into cart' ) );
            $insert_result = $wpdb->insert(
                $cart_table,
                [
                    'employee_id' => $user_id,
                    'product_id'  => $product_id,
                    'quantity'    => $quantity,
                ],
                [ '%d', '%d', '%d' ]
            );
            
            error_log( sprintf( 'CWM Debug add_to_cart: Insert result=%s, insert_id=%d', $insert_result !== false ? 'success' : 'failed', $wpdb->insert_id ) );
            if ( $insert_result === false ) {
                error_log( sprintf( 'CWM Debug add_to_cart: Insert error - %s', $wpdb->last_error ) );
                return new WP_Error( 'cwm_insert_failed', __( 'خطا در افزودن محصول به سبد خرید.', 'company-wallet-manager' ), [ 'status' => 500 ] );
            }
            
            // Verify the item was inserted
            $verify = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$cart_table} WHERE id = %d",
                    $wpdb->insert_id
                ),
                ARRAY_A
            );
            error_log( sprintf( 'CWM Debug add_to_cart: Verification - item %s', $verify ? 'found' : 'NOT found' ) );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'محصول به سبد خرید اضافه شد.', 'company-wallet-manager' ),
        ] );
    }

    /**
     * Remove item from cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function remove_from_cart( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $item_id = (int) $request->get_param( 'id' );
        $cart_table = $wpdb->prefix . 'cwm_cart_items';

        // Verify ownership
        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM {$cart_table} WHERE id = %d AND employee_id = %d", $item_id, $user_id ),
            ARRAY_A
        );

        if ( ! $item ) {
            return new WP_Error( 'cwm_item_not_found', __( 'آیتم در سبد خرید یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $wpdb->delete( $cart_table, [ 'id' => $item_id ], [ '%d' ] );

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'آیتم از سبد خرید حذف شد.', 'company-wallet-manager' ),
        ] );
    }

    /**
     * Update cart item quantity.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_cart_item( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $item_id = (int) $request->get_param( 'id' );
        $quantity = (int) $request->get_param( 'quantity' );

        $cart_table = $wpdb->prefix . 'cwm_cart_items';
        $products_table = $wpdb->prefix . 'cwm_products';

        if ( $quantity <= 0 ) {
            return $this->remove_from_cart( $request );
        }

        // Verify ownership and get product info
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ci.*, p.stock_quantity FROM {$cart_table} ci
                INNER JOIN {$products_table} p ON ci.product_id = p.id
                WHERE ci.id = %d AND ci.employee_id = %d",
                $item_id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $item ) {
            return new WP_Error( 'cwm_item_not_found', __( 'آیتم در سبد خرید یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        if ( $quantity > (int) $item['stock_quantity'] ) {
            return new WP_Error( 'cwm_insufficient_stock', __( 'موجودی محصول کافی نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $wpdb->update(
            $cart_table,
            [ 'quantity' => $quantity ],
            [ 'id' => $item_id ],
            [ '%d' ],
            [ '%d' ]
        );

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'تعداد به‌روزرسانی شد.', 'company-wallet-manager' ),
        ] );
    }

    /**
     * Clear cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function clear_cart( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $cart_table = $wpdb->prefix . 'cwm_cart_items';

        $wpdb->delete( $cart_table, [ 'employee_id' => $user_id ], [ '%d' ] );

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'سبد خرید خالی شد.', 'company-wallet-manager' ),
        ] );
    }

    // ==================== Orders Methods ====================

    /**
     * List orders (for employees or merchants based on context).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_orders( WP_REST_Request $request ) {
        // This will be handled by list_employee_orders or list_merchant_orders
        return $this->list_employee_orders( $request );
    }

    /**
     * List orders for current employee.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_employee_orders( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';

        $orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, 
                        (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = o.merchant_id AND meta_key = '_cwm_store_name') as store_name
                FROM {$orders_table} o
                WHERE o.employee_id = %d
                ORDER BY o.created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        $result = [];
        foreach ( $orders as $order ) {
            $items = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$order_items_table} WHERE order_id = %d", (int) $order['id'] ),
                ARRAY_A
            );

            $result[] = [
                'id'                => (int) $order['id'],
                'order_number'      => $order['order_number'],
                'employee_id'       => (int) $order['employee_id'],
                'merchant_id'       => (int) $order['merchant_id'],
                'store_name'        => $order['store_name'] ?? '',
                'total_amount'      => (float) $order['total_amount'],
                'customer_name'     => $order['customer_name'],
                'customer_family'   => $order['customer_family'],
                'customer_address'  => $order['customer_address'],
                'customer_mobile'   => $order['customer_mobile'],
                'customer_postal_code' => $order['customer_postal_code'],
                'tracking_code'     => $order['tracking_code'] ?? null,
                'status'            => $order['status'],
                'payment_status'    => $order['payment_status'],
                'created_at'        => $order['created_at'],
                'updated_at'        => $order['updated_at'],
                'items'             => array_map( function( $item ) {
                    return [
                        'id'            => (int) $item['id'],
                        'product_id'    => (int) $item['product_id'],
                        'product_name'  => $item['product_name'],
                        'product_price' => (float) $item['product_price'],
                        'quantity'      => (int) $item['quantity'],
                        'subtotal'      => (float) $item['subtotal'],
                    ];
                }, $items ?: [] ),
            ];
        }

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => $result,
        ] );
    }

    /**
     * List orders for current merchant.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_merchant_orders( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';

        $orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.* FROM {$orders_table} o
                WHERE o.merchant_id = %d
                ORDER BY o.created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        $result = [];
        foreach ( $orders as $order ) {
            $items = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$order_items_table} WHERE order_id = %d", (int) $order['id'] ),
                ARRAY_A
            );

            $result[] = [
                'id'                => (int) $order['id'],
                'order_number'      => $order['order_number'],
                'employee_id'       => (int) $order['employee_id'],
                'merchant_id'       => (int) $order['merchant_id'],
                'total_amount'      => (float) $order['total_amount'],
                'customer_name'     => $order['customer_name'],
                'customer_family'   => $order['customer_family'],
                'customer_address'  => $order['customer_address'],
                'customer_mobile'   => $order['customer_mobile'],
                'customer_postal_code' => $order['customer_postal_code'],
                'tracking_code'     => $order['tracking_code'] ?? null,
                'status'            => $order['status'],
                'payment_status'    => $order['payment_status'],
                'created_at'        => $order['created_at'],
                'updated_at'        => $order['updated_at'],
                'items'             => array_map( function( $item ) {
                    return [
                        'id'            => (int) $item['id'],
                        'product_id'    => (int) $item['product_id'],
                        'product_name'  => $item['product_name'],
                        'product_price' => (float) $item['product_price'],
                        'quantity'      => (int) $item['quantity'],
                        'subtotal'      => (float) $item['subtotal'],
                    ];
                }, $items ?: [] ),
            ];
        }

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => $result,
        ] );
    }

    /**
     * Get a single order.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_order( WP_REST_Request $request ) {
        global $wpdb;
        $order_id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';

        $order = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE id = %d", $order_id ),
            ARRAY_A
        );

        if ( ! $order ) {
            return new WP_Error( 'cwm_order_not_found', __( 'سفارش یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        // Check permission
        $is_employee = in_array( 'employee', (array) $user->roles, true );
        $is_merchant = in_array( 'merchant', (array) $user->roles, true );

        if ( $is_employee && (int) $order['employee_id'] !== $user_id ) {
            return new WP_Error( 'cwm_forbidden', __( 'شما مجاز به مشاهده این سفارش نیستید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        if ( $is_merchant && (int) $order['merchant_id'] !== $user_id ) {
            return new WP_Error( 'cwm_forbidden', __( 'شما مجاز به مشاهده این سفارش نیستید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$order_items_table} WHERE order_id = %d", $order_id ),
            ARRAY_A
        );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'id'                => (int) $order['id'],
                'order_number'      => $order['order_number'],
                'employee_id'       => (int) $order['employee_id'],
                'merchant_id'       => (int) $order['merchant_id'],
                'total_amount'      => (float) $order['total_amount'],
                'customer_name'     => $order['customer_name'],
                'customer_family'   => $order['customer_family'],
                'customer_address'  => $order['customer_address'],
                'customer_mobile'   => $order['customer_mobile'],
                'customer_postal_code' => $order['customer_postal_code'],
                'tracking_code'     => $order['tracking_code'] ?? null,
                'status'            => $order['status'],
                'payment_status'    => $order['payment_status'],
                'created_at'        => $order['created_at'],
                'updated_at'        => $order['updated_at'],
                'items'             => array_map( function( $item ) {
                    return [
                        'id'            => (int) $item['id'],
                        'product_id'    => (int) $item['product_id'],
                        'product_name'  => $item['product_name'],
                        'product_price' => (float) $item['product_price'],
                        'quantity'      => (int) $item['quantity'],
                        'subtotal'      => (float) $item['subtotal'],
                    ];
                }, $items ?: [] ),
            ],
        ] );
    }

    /**
     * Create a new order from cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_order( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $cart_table = $wpdb->prefix . 'cwm_cart_items';
        $products_table = $wpdb->prefix . 'cwm_products';
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';
        $wallet_table = $wpdb->prefix . 'cwm_wallets';
        $transactions_table = $wpdb->prefix . 'cwm_transactions';
        $limits_table = $wpdb->prefix . 'cwm_employee_category_limits';

        // Get customer info
        $customer_name     = sanitize_text_field( $request->get_param( 'customer_name' ) );
        $customer_family   = sanitize_text_field( $request->get_param( 'customer_family' ) );
        $customer_address  = sanitize_textarea_field( $request->get_param( 'customer_address' ) );
        $customer_mobile   = sanitize_text_field( $request->get_param( 'customer_mobile' ) );
        $customer_postal_code = sanitize_text_field( $request->get_param( 'customer_postal_code' ) );

        if ( empty( $customer_name ) || empty( $customer_family ) || empty( $customer_address ) || empty( $customer_mobile ) ) {
            return new WP_Error( 'cwm_invalid_data', __( 'لطفاً تمام فیلدهای الزامی را پر کنید.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Get cart items grouped by merchant
        $cart_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ci.*, p.merchant_id, p.price, p.name as product_name, p.product_category_id, p.stock_quantity
                FROM {$cart_table} ci
                INNER JOIN {$products_table} p ON ci.product_id = p.id
                WHERE ci.employee_id = %d AND p.online_purchase_enabled = 1 AND p.status = 'active'",
                $user_id
            ),
            ARRAY_A
        );

        if ( empty( $cart_items ) ) {
            return new WP_Error( 'cwm_empty_cart', __( 'سبد خرید شما خالی است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Group by merchant
        $merchant_orders = [];
        foreach ( $cart_items as $item ) {
            $merchant_id = (int) $item['merchant_id'];
            if ( ! isset( $merchant_orders[ $merchant_id ] ) ) {
                $merchant_orders[ $merchant_id ] = [];
            }
            $merchant_orders[ $merchant_id ][] = $item;
        }

        $wpdb->query( 'START TRANSACTION' );

        try {
            $created_orders = [];

            foreach ( $merchant_orders as $merchant_id => $items ) {
                $total_amount = 0;
                $order_items_data = [];

                // Calculate total and validate stock
                foreach ( $items as $item ) {
                    if ( (int) $item['quantity'] > (int) $item['stock_quantity'] ) {
                        throw new \Exception( sprintf( __( 'موجودی محصول %s کافی نیست.', 'company-wallet-manager' ), $item['product_name'] ) );
                    }

                    $subtotal = (float) $item['price'] * (int) $item['quantity'];
                    $total_amount += $subtotal;

                    $order_items_data[] = [
                        'product_id'    => (int) $item['product_id'],
                        'product_name'  => $item['product_name'],
                        'product_price' => (float) $item['price'],
                        'quantity'      => (int) $item['quantity'],
                        'subtotal'      => $subtotal,
                        'category_id'   => $item['product_category_id'] ? (int) $item['product_category_id'] : null,
                    ];
                }

                // Check wallet balance
                $wallet = $wpdb->get_row(
                    $wpdb->prepare( "SELECT balance FROM {$wallet_table} WHERE user_id = %d", $user_id ),
                    ARRAY_A
                );

                $current_balance = $wallet ? (float) $wallet['balance'] : 0;

                if ( $current_balance < $total_amount ) {
                    throw new \Exception( __( 'موجودی کیف پول کافی نیست.', 'company-wallet-manager' ) );
                }

                // Check category limits (similar to payment request validation)
                foreach ( $order_items_data as $order_item ) {
                    if ( $order_item['category_id'] ) {
                        $limit = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT spending_limit, spent_amount FROM {$limits_table} 
                                WHERE employee_id = %d AND category_id = %d",
                                $user_id,
                                $order_item['category_id']
                            ),
                            ARRAY_A
                        );

                        if ( $limit ) {
                            $remaining = (float) $limit['spending_limit'] - (float) $limit['spent_amount'];
                            if ( $order_item['subtotal'] > $remaining ) {
                                throw new \Exception( sprintf( __( 'سقف دسته‌بندی برای محصول %s کافی نیست.', 'company-wallet-manager' ), $order_item['product_name'] ) );
                            }
                        }
                    }
                }

                // Generate order number
                $order_number = 'ORD-' . date( 'Ymd' ) . '-' . str_pad( (string) time(), 10, '0', STR_PAD_LEFT ) . '-' . $merchant_id;

                // Create order
                $order_result = $wpdb->insert(
                    $orders_table,
                    [
                        'order_number'        => $order_number,
                        'employee_id'         => $user_id,
                        'merchant_id'         => $merchant_id,
                        'total_amount'        => $total_amount,
                        'customer_name'       => $customer_name,
                        'customer_family'     => $customer_family,
                        'customer_address'    => $customer_address,
                        'customer_mobile'     => $customer_mobile,
                        'customer_postal_code' => $customer_postal_code,
                        'status'              => 'pending',
                        'payment_status'      => 'pending',
                    ],
                    [ '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
                );

                if ( false === $order_result ) {
                    throw new \Exception( __( 'خطا در ایجاد سفارش.', 'company-wallet-manager' ) );
                }

                $order_id = $wpdb->insert_id;

                // Create order items
                foreach ( $order_items_data as $item_data ) {
                    $wpdb->insert(
                        $order_items_table,
                        [
                            'order_id'      => $order_id,
                            'product_id'    => $item_data['product_id'],
                            'product_name'  => $item_data['product_name'],
                            'product_price' => $item_data['product_price'],
                            'quantity'      => $item_data['quantity'],
                            'subtotal'      => $item_data['subtotal'],
                        ],
                        [ '%d', '%d', '%s', '%f', '%d', '%f' ]
                    );

                    // Update stock
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$products_table} SET stock_quantity = stock_quantity - %d WHERE id = %d",
                            $item_data['quantity'],
                            $item_data['product_id']
                        )
                    );

                    // Update category limits
                    if ( $item_data['category_id'] ) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT INTO {$limits_table} (employee_id, category_id, spending_limit, spent_amount)
                                VALUES (%d, %d, 0, %f)
                                ON DUPLICATE KEY UPDATE spent_amount = spent_amount + %f",
                                $user_id,
                                $item_data['category_id'],
                                $item_data['subtotal'],
                                $item_data['subtotal']
                            )
                        );
                    }
                }

                // Deduct from wallet
                $new_balance = $current_balance - $total_amount;
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO {$wallet_table} (user_id, balance) VALUES (%d, %f)
                        ON DUPLICATE KEY UPDATE balance = %f",
                        $user_id,
                        $new_balance,
                        $new_balance
                    )
                );

                // Create transaction
                $wpdb->insert(
                    $transactions_table,
                    [
                        'type'                      => 'online_purchase',
                        'sender_id'                 => $user_id,
                        'receiver_id'               => $merchant_id,
                        'related_request'           => $order_id,
                        'amount'                    => $total_amount,
                        'balance_snapshot_sender'   => $new_balance,
                        'status'                    => 'completed',
                        'metadata'                  => wp_json_encode( [
                            'order_number' => $order_number,
                            'order_id'     => $order_id,
                        ] ),
                    ],
                    [ '%s', '%d', '%d', '%d', '%f', '%f', '%s', '%s' ]
                );

                // Add to merchant wallet
                $merchant_wallet = $wpdb->get_row(
                    $wpdb->prepare( "SELECT balance FROM {$wallet_table} WHERE user_id = %d", $merchant_id ),
                    ARRAY_A
                );

                $merchant_balance = $merchant_wallet ? (float) $merchant_wallet['balance'] : 0;
                $merchant_new_balance = $merchant_balance + $total_amount;

                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO {$wallet_table} (user_id, balance) VALUES (%d, %f)
                        ON DUPLICATE KEY UPDATE balance = %f",
                        $merchant_id,
                        $merchant_new_balance,
                        $merchant_new_balance
                    )
                );

                // Update order payment status
                $wpdb->update(
                    $orders_table,
                    [ 'payment_status' => 'paid' ],
                    [ 'id' => $order_id ],
                    [ '%s' ],
                    [ '%d' ]
                );

                $created_orders[] = [
                    'id'           => $order_id,
                    'order_number' => $order_number,
                    'total_amount' => $total_amount,
                ];
            }

            // Clear cart
            $wpdb->delete( $cart_table, [ 'employee_id' => $user_id ], [ '%d' ] );

            $wpdb->query( 'COMMIT' );

            return rest_ensure_response( [
                'status'  => 'success',
                'message' => __( 'سفارش با موفقیت ثبت شد.', 'company-wallet-manager' ),
                'data'    => [
                    'orders' => $created_orders,
                ],
            ] );

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'cwm_order_error', $e->getMessage(), [ 'status' => 400 ] );
        }
    }

    /**
     * Update order status (for merchants).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_order_status( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $order_id = (int) $request->get_param( 'id' );
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        $tracking_code = sanitize_text_field( $request->get_param( 'tracking_code' ) ?? '' );

        $orders_table = $wpdb->prefix . 'cwm_orders';

        // Verify ownership
        $order = $wpdb->get_row(
            $wpdb->prepare( "SELECT merchant_id FROM {$orders_table} WHERE id = %d", $order_id ),
            ARRAY_A
        );

        if ( ! $order || (int) $order['merchant_id'] !== $user_id ) {
            return new WP_Error( 'cwm_forbidden', __( 'شما مجاز به ویرایش این سفارش نیستید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $update_data = [];
        $update_format = [];

        if ( ! empty( $status ) ) {
            $valid_statuses = [ 'pending', 'processing', 'shipped', 'delivered', 'cancelled' ];
            if ( ! in_array( $status, $valid_statuses, true ) ) {
                return new WP_Error( 'cwm_invalid_status', __( 'وضعیت نامعتبر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }
            $update_data['status'] = $status;
            $update_format[] = '%s';
        }

        if ( $request->has_param( 'tracking_code' ) ) {
            $update_data['tracking_code'] = $tracking_code;
            $update_format[] = '%s';
        }

        if ( empty( $update_data ) ) {
            return new WP_Error( 'cwm_no_data', __( 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $result = $wpdb->update(
            $orders_table,
            $update_data,
            [ 'id' => $order_id ],
            $update_format,
            [ '%d' ]
        );

        if ( false === $result ) {
            return new WP_Error( 'cwm_db_error', __( 'خطا در به‌روزرسانی سفارش.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'وضعیت سفارش به‌روزرسانی شد.', 'company-wallet-manager' ),
        ] );
    }

    // ==================== Reports Methods ====================

    /**
     * Get admin order reports.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_admin_order_reports( WP_REST_Request $request ) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';
        $transactions_table = $wpdb->prefix . 'cwm_transactions';

        // Total online orders
        $total_online_orders = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$orders_table} WHERE payment_status = 'paid'"
        );

        // Total online sales
        $total_online_sales = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$orders_table} WHERE payment_status = 'paid'"
        );

        // Total in-person sales (from payment_requests)
        $payment_requests_table = $wpdb->prefix . 'cwm_payment_requests';
        $total_in_person_sales = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM {$transactions_table} 
            WHERE type = 'payment' AND status = 'completed'"
        );

        // Orders by status
        $orders_by_status = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$orders_table} GROUP BY status",
            ARRAY_A
        );

        // Sales by merchant
        $sales_by_merchant = $wpdb->get_results(
            "SELECT o.merchant_id, 
                    (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = o.merchant_id AND meta_key = '_cwm_store_name') as store_name,
                    COUNT(*) as order_count,
                    SUM(o.total_amount) as total_sales
            FROM {$orders_table} o
            WHERE o.payment_status = 'paid'
            GROUP BY o.merchant_id
            ORDER BY total_sales DESC",
            ARRAY_A
        );

        // Sales by product category
        $sales_by_category = $wpdb->get_results(
            "SELECT pc.id, pc.name, COUNT(DISTINCT o.id) as order_count, SUM(oi.subtotal) as total_sales
            FROM {$order_items_table} oi
            INNER JOIN {$orders_table} o ON oi.order_id = o.id
            INNER JOIN {$wpdb->prefix}cwm_products p ON oi.product_id = p.id
            LEFT JOIN {$wpdb->prefix}cwm_product_categories pc ON p.product_category_id = pc.id
            WHERE o.payment_status = 'paid'
            GROUP BY pc.id, pc.name
            ORDER BY total_sales DESC",
            ARRAY_A
        );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'total_online_orders'    => $total_online_orders,
                'total_online_sales'     => $total_online_sales,
                'total_in_person_sales'  => $total_in_person_sales,
                'orders_by_status'       => array_map( function( $row ) {
                    return [
                        'status' => $row['status'],
                        'count'  => (int) $row['count'],
                    ];
                }, $orders_by_status ?: [] ),
                'sales_by_merchant'      => array_map( function( $row ) {
                    return [
                        'merchant_id' => (int) $row['merchant_id'],
                        'store_name'  => $row['store_name'] ?? '',
                        'order_count' => (int) $row['order_count'],
                        'total_sales' => (float) $row['total_sales'],
                    ];
                }, $sales_by_merchant ?: [] ),
                'sales_by_category'      => array_map( function( $row ) {
                    return [
                        'category_id'  => (int) $row['id'],
                        'category_name' => $row['name'] ?? 'بدون دسته‌بندی',
                        'order_count'  => (int) $row['order_count'],
                        'total_sales'  => (float) $row['total_sales'],
                    ];
                }, $sales_by_category ?: [] ),
            ],
        ] );
    }

    /**
     * Get admin product reports.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_admin_product_reports( WP_REST_Request $request ) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'cwm_products';
        $product_categories_table = $wpdb->prefix . 'cwm_product_categories';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';
        $orders_table = $wpdb->prefix . 'cwm_orders';

        // Total products
        $total_products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$products_table}" );

        // Products by category
        $products_by_category = $wpdb->get_results(
            "SELECT pc.id, pc.name, COUNT(p.id) as product_count
            FROM {$products_table} p
            LEFT JOIN {$product_categories_table} pc ON p.product_category_id = pc.id
            GROUP BY pc.id, pc.name
            ORDER BY product_count DESC",
            ARRAY_A
        );

        // Top selling products
        $top_products = $wpdb->get_results(
            "SELECT p.id, p.name, p.merchant_id,
                    (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = p.merchant_id AND meta_key = '_cwm_store_name') as store_name,
                    SUM(oi.quantity) as total_sold,
                    SUM(oi.subtotal) as total_revenue
            FROM {$order_items_table} oi
            INNER JOIN {$orders_table} o ON oi.order_id = o.id
            INNER JOIN {$products_table} p ON oi.product_id = p.id
            WHERE o.payment_status = 'paid'
            GROUP BY p.id, p.name, p.merchant_id
            ORDER BY total_revenue DESC
            LIMIT 20",
            ARRAY_A
        );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'total_products'         => $total_products,
                'products_by_category'   => array_map( function( $row ) {
                    return [
                        'category_id'   => (int) $row['id'],
                        'category_name' => $row['name'] ?? 'بدون دسته‌بندی',
                        'product_count' => (int) $row['product_count'],
                    ];
                }, $products_by_category ?: [] ),
                'top_products'           => array_map( function( $row ) {
                    return [
                        'product_id'   => (int) $row['id'],
                        'product_name' => $row['name'],
                        'merchant_id'  => (int) $row['merchant_id'],
                        'store_name'   => $row['store_name'] ?? '',
                        'total_sold'   => (int) $row['total_sold'],
                        'total_revenue' => (float) $row['total_revenue'],
                    ];
                }, $top_products ?: [] ),
            ],
        ] );
    }

    /**
     * Get company employee reports.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_company_employee_reports( WP_REST_Request $request ) {
        global $wpdb;
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user->ID,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return rest_ensure_response( [
                'status' => 'success',
                'data'   => [
                    'employees' => [],
                ],
            ] );
        }

        $company_id = $company_posts[0]->ID;

        // Get company employees
        $employees = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.ID as employee_id, u.display_name, u.user_email
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = '_cwm_company_id' AND um.meta_value = %d",
                $company_id
            ),
            ARRAY_A
        );

        if ( empty( $employees ) ) {
            return rest_ensure_response( [
                'status' => 'success',
                'data'   => [
                    'employees' => [],
                ],
            ] );
        }

        $employee_ids = array_map( function( $emp ) {
            return (int) $emp['employee_id'];
        }, $employees );

        $placeholders = implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) );
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $transactions_table = $wpdb->prefix . 'cwm_transactions';

        // Get orders for employees
        $orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT employee_id, COUNT(*) as order_count, SUM(total_amount) as total_spent
                FROM {$orders_table}
                WHERE employee_id IN ($placeholders) AND payment_status = 'paid'
                GROUP BY employee_id",
                ...$employee_ids
            ),
            ARRAY_A
        );

        // Get in-person transactions
        $in_person_transactions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sender_id as employee_id, COUNT(*) as transaction_count, SUM(amount) as total_spent
                FROM {$transactions_table}
                WHERE sender_id IN ($placeholders) AND type = 'payment' AND status = 'completed'
                GROUP BY sender_id",
                ...$employee_ids
            ),
            ARRAY_A
        );

        $result = [];
        foreach ( $employees as $employee ) {
            $emp_id = (int) $employee['employee_id'];
            $order_data = array_filter( $orders, function( $o ) use ( $emp_id ) {
                return (int) $o['employee_id'] === $emp_id;
            } );
            $transaction_data = array_filter( $in_person_transactions, function( $t ) use ( $emp_id ) {
                return (int) $t['employee_id'] === $emp_id;
            } );

            $order_info = ! empty( $order_data ) ? reset( $order_data ) : null;
            $transaction_info = ! empty( $transaction_data ) ? reset( $transaction_data ) : null;

            $result[] = [
                'employee_id'         => $emp_id,
                'employee_name'       => $employee['display_name'],
                'employee_email'      => $employee['user_email'],
                'online_orders'       => $order_info ? (int) $order_info['order_count'] : 0,
                'online_spent'        => $order_info ? (float) $order_info['total_spent'] : 0,
                'in_person_transactions' => $transaction_info ? (int) $transaction_info['transaction_count'] : 0,
                'in_person_spent'     => $transaction_info ? (float) $transaction_info['total_spent'] : 0,
                'total_spent'         => ( $order_info ? (float) $order_info['total_spent'] : 0 ) + ( $transaction_info ? (float) $transaction_info['total_spent'] : 0 ),
            ];
        }

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'employees' => $result,
            ],
        ] );
    }

    /**
     * Get company credit balance.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_company_credit( WP_REST_Request $request ) {
        global $wpdb;
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_id = $company_posts[0]->ID;
        $credit_amount = get_post_meta( $company_id, '_cwm_company_credit', true );
        $credit_amount = $credit_amount ? floatval( $credit_amount ) : 0;

        // Get wallet balance
        $wallet_system = new Wallet_System();
        $wallet_balance = $wallet_system->get_balance( $user_id );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'credit_amount'  => $credit_amount,
                'wallet_balance' => $wallet_balance,
                'available'      => $wallet_balance,
            ],
        ] );
    }

    /**
     * Allocate credit to employees from Excel file.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function allocate_employee_credit_from_excel( WP_REST_Request $request ) {
        global $wpdb;
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        // Handle file upload - WordPress REST API uses $_FILES for multipart/form-data
        $file = null;
        if ( ! empty( $_FILES['file'] ) && is_array( $_FILES['file'] ) ) {
            $file = $_FILES['file'];
        } else {
            $file_params = $request->get_file_params();
            if ( ! empty( $file_params ) && ! empty( $file_params['file'] ) ) {
                $file = $file_params['file'];
            }
        }

        if ( empty( $file ) || ( is_array( $file ) && empty( $file['tmp_name'] ) ) ) {
            return new WP_Error( 'cwm_no_file', __( 'فایل ارسال نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Get bulk amount if provided
        $bulk_amount = $request->get_param( 'amount' );
        $bulk_amount = ! empty( $bulk_amount ) ? sanitize_text_field( $bulk_amount ) : '';

        $csv_handler = new \CWM\Company_CSV_Handler();
        $result = $csv_handler->process_employee_credit_allocation( $file, $user_id, $bulk_amount );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get company category caps.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_company_category_caps( WP_REST_Request $request ) {
        global $wpdb;
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_id = $company_posts[0]->ID;
        $caps = $this->category_manager->get_company_category_caps( $company_id );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'company_id' => $company_id,
                'caps'       => $caps,
            ],
        ] );
    }

    /**
     * Update company category caps.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_company_category_caps( WP_REST_Request $request ) {
        global $wpdb;
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_id = $company_posts[0]->ID;
        $payload = $request->get_json_params();
        $caps = isset( $payload['caps'] ) ? $payload['caps'] : [];

        if ( ! is_array( $caps ) ) {
            return new WP_Error( 'cwm_invalid_caps', __( 'فرمت محدودیت‌ها نامعتبر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( empty( $caps ) ) {
            return new WP_Error( 'cwm_empty_caps', __( 'هیچ محدودیتی ارسال نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $saved_count = 0;
        $errors = [];

        // Log received data for debugging
        error_log( 'Category Caps Update - Received payload: ' . print_r( $caps, true ) );
        error_log( 'Category Caps Update - Full request: ' . print_r( $request->get_json_params(), true ) );

        foreach ( $caps as $index => $cap ) {
            // Log each cap before processing
            error_log( "Processing cap at index $index: " . print_r( $cap, true ) );
            
            // More detailed validation
            $category_id = isset( $cap['category_id'] ) ? absint( $cap['category_id'] ) : 0;
            
            // Get limit_type with better handling - always default to 'amount' if invalid
            $raw_limit_type = isset( $cap['limit_type'] ) ? $cap['limit_type'] : '';
            
            // Sanitize and validate limit_type
            if ( ! empty( $raw_limit_type ) ) {
                $limit_type = trim( sanitize_text_field( (string) $raw_limit_type ) );
            } else {
                $limit_type = '';
            }
            
            // If limit_type is empty or invalid, default to 'amount'
            if ( empty( $limit_type ) || ! in_array( $limit_type, [ 'percentage', 'amount' ], true ) ) {
                error_log( "Category $category_id - Invalid or empty limit_type: '$limit_type' (raw: '$raw_limit_type'), defaulting to 'amount'" );
                $limit_type = 'amount';
            }
            
            // Log the transformation
            error_log( "Category $category_id - Raw limit_type: '$raw_limit_type', Final: '$limit_type'" );
            
            $limit_value = isset( $cap['limit_value'] ) ? floatval( $cap['limit_value'] ) : 0;

            // Check all required fields
            if ( $category_id <= 0 ) {
                $errors[] = sprintf( __( 'محدودیت شماره %d: شناسه دسته‌بندی نامعتبر است.', 'company-wallet-manager' ), $index + 1 );
                error_log( "Category Cap Error - Invalid category_id at index $index: " . print_r( $cap, true ) );
                continue;
            }

            if ( $limit_value <= 0 ) {
                $errors[] = sprintf( __( 'محدودیت دسته‌بندی %d: مقدار محدودیت باید بیشتر از صفر باشد.', 'company-wallet-manager' ), $category_id );
                error_log( "Category Cap Error - Invalid limit_value for category $category_id: $limit_value" );
                continue;
            }

            // Final validation before saving
            if ( $limit_type !== 'amount' && $limit_type !== 'percentage' ) {
                error_log( "Category $category_id - Final validation failed, limit_type is still invalid: '$limit_type'" );
                $limit_type = 'amount'; // Force to 'amount' as last resort
            }
            
            error_log( "Category $category_id - Saving with limit_type: '$limit_type', limit_value: $limit_value" );
            
            $result = $this->category_manager->set_company_category_cap( $company_id, $category_id, $limit_type, $limit_value );
            
            if ( false === $result ) {
                global $wpdb;
                $db_error = $wpdb->last_error ? $wpdb->last_error : __( 'خطای نامشخص دیتابیس', 'company-wallet-manager' );
                $errors[] = sprintf( __( 'خطا در ذخیره محدودیت دسته‌بندی %d: %s', 'company-wallet-manager' ), $category_id, $db_error );
                error_log( "Category $category_id - Database save failed: $db_error" );
            } else {
                $saved_count++;
                error_log( "Category $category_id - Successfully saved" );
            }
        }

        if ( ! empty( $errors ) && $saved_count === 0 ) {
            return new WP_Error( 'cwm_save_failed', implode( ' ', $errors ), [ 'status' => 500 ] );
        }

        $message = $saved_count > 0 
            ? sprintf( __( '%d محدودیت با موفقیت ذخیره شد.', 'company-wallet-manager' ), $saved_count )
            : __( 'محدودیت‌های دسته‌بندی به‌روزرسانی شد.', 'company-wallet-manager' );

        if ( ! empty( $errors ) ) {
            $message .= ' ' . __( 'برخی خطاها رخ داد:', 'company-wallet-manager' ) . ' ' . implode( ' ', $errors );
        }

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => $message,
            'saved'   => $saved_count,
            'errors'  => $errors,
        ] );
    }

    /**
     * Get top merchants for company.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_company_top_merchants( WP_REST_Request $request ) {
        global $wpdb;
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user->ID,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return rest_ensure_response( [
                'status' => 'success',
                'data'   => [
                    'merchants' => [],
                ],
            ] );
        }

        $company_id = $company_posts[0]->ID;

        // Get company employees
        $employees = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.ID as employee_id
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = '_cwm_company_id' AND um.meta_value = %d",
                $company_id
            ),
            ARRAY_A
        );

        if ( empty( $employees ) ) {
            return rest_ensure_response( [
                'status' => 'success',
                'data'   => [
                    'merchants' => [],
                ],
            ] );
        }

        $employee_ids = array_map( function( $emp ) {
            return (int) $emp['employee_id'];
        }, $employees );

        $placeholders = implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) );
        $orders_table = $wpdb->prefix . 'cwm_orders';
        $order_items_table = $wpdb->prefix . 'cwm_order_items';

        // Get top merchants by sales
        $top_merchants = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.merchant_id,
                        (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = o.merchant_id AND meta_key = '_cwm_store_name') as store_name,
                        COUNT(DISTINCT o.id) as order_count,
                        SUM(o.total_amount) as total_sales
                FROM {$orders_table} o
                WHERE o.employee_id IN ($placeholders) AND o.payment_status = 'paid'
                GROUP BY o.merchant_id
                ORDER BY total_sales DESC
                LIMIT 20",
                ...$employee_ids
            ),
            ARRAY_A
        );

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'merchants' => array_map( function( $row ) {
                    return [
                        'merchant_id' => (int) $row['merchant_id'],
                        'store_name'  => $row['store_name'] ?? '',
                        'order_count' => (int) $row['order_count'],
                        'total_sales' => (float) $row['total_sales'],
                    ];
                }, $top_merchants ?: [] ),
            ],
        ] );
    }

    /**
     * Get employee online purchase access.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_employee_online_access( WP_REST_Request $request ) {
        $employee_id = (int) $request->get_param( 'employee_id' );
        $user = wp_get_current_user();

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user->ID,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_id = $company_posts[0]->ID;

        // Verify employee belongs to company
        $employee_company_id = (int) get_user_meta( $employee_id, '_cwm_company_id', true );
        if ( $employee_company_id !== $company_id ) {
            return new WP_Error( 'cwm_unauthorized', __( 'دسترسی غیرمجاز.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $has_access = get_user_meta( $employee_id, '_cwm_online_purchase_enabled', true );
        $has_access = $has_access === '1' || $has_access === true;

        return rest_ensure_response( [
            'status' => 'success',
            'data'   => [
                'employee_id'  => $employee_id,
                'has_access'   => $has_access,
            ],
        ] );
    }

    /**
     * Update employee online purchase access.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_employee_online_access( WP_REST_Request $request ) {
        $employee_id = (int) $request->get_param( 'employee_id' );
        $user = wp_get_current_user();

        // Get company post
        $company_posts = get_posts( [
            'post_type'   => 'cwm_company',
            'meta_key'    => '_cwm_company_user_id',
            'meta_value'  => $user->ID,
            'post_status' => 'any',
            'numberposts' => 1,
        ] );

        if ( empty( $company_posts ) ) {
            return new WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_id = $company_posts[0]->ID;

        // Verify employee belongs to company
        $employee_company_id = (int) get_user_meta( $employee_id, '_cwm_company_id', true );
        if ( $employee_company_id !== $company_id ) {
            return new WP_Error( 'cwm_unauthorized', __( 'دسترسی غیرمجاز.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $payload = $request->get_json_params();
        $has_access = isset( $payload['has_access'] ) ? (bool) $payload['has_access'] : false;

        update_user_meta( $employee_id, '_cwm_online_purchase_enabled', $has_access ? '1' : '0' );

        return rest_ensure_response( [
            'status'  => 'success',
            'message' => __( 'دسترسی خرید آنلاین به‌روزرسانی شد.', 'company-wallet-manager' ),
        ] );
    }
}

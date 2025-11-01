<?php

namespace CWM;

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
     * Constructor.
     */
    public function __construct() {
        $this->company_registration  = new Company_Registration();
        $this->merchant_registration = new Merchant_Registration();

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'rest_api_init', array( $this, 'configure_cors_support' ), 15 );
    }

    /**
     * Configure CORS headers for the plugin's REST namespace.
     */
    public function configure_cors_support() {
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
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );

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
                        'validate_callback' => 'is_string',
                    ],
                    'password' => [
                        'required'          => true,
                        'validate_callback' => 'is_string',
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
                        'validate_callback' => 'is_string',
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
                        'validate_callback' => 'is_string',
                    ],
                    'amount' => [
                        'required'          => true,
                        'validate_callback' => 'is_numeric',
                    ],
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/payment/confirm', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'confirm_payment' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
                'args'                => [
                    'request_id' => [
                        'required'          => true,
                        'validate_callback' => 'is_numeric',
                    ],
                    'otp_code' => [
                        'required'          => true,
                        'validate_callback' => 'is_string',
                    ],
                ],
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
                        'validate_callback' => 'is_numeric',
                    ],
                    'amount' => [
                        'required'          => true,
                        'validate_callback' => 'is_numeric',
                    ],
                ],
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
                        'validate_callback' => 'is_numeric',
                    ],
                    'bank_account' => [
                        'required'          => true,
                        'validate_callback' => 'is_string',
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
                        'validate_callback' => 'is_string',
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

        $this->register_admin_routes();
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
        ] );

        register_rest_route( $this->namespace, '/admin/companies/(?P<id>\d+)/employees', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_admin_company_employees' ],
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
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'status'       => $post->post_status,
                'company_type' => get_post_meta( $post->ID, '_cwm_company_type', true ),
                'email'        => get_post_meta( $post->ID, '_cwm_company_email', true ),
                'phone'        => get_post_meta( $post->ID, '_cwm_company_phone', true ),
                'user_id'      => get_post_meta( $post->ID, '_cwm_company_user_id', true ),
                'created_at'   => $post->post_date,
            ];
        }

        wp_reset_postdata();

        return $this->respond_with_format( $request, $rows, [ 'id', 'title', 'status', 'company_type', 'email', 'phone', 'user_id', 'created_at' ], 'companies.csv' );
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

        foreach ( $employees as $employee ) {
            $rows[] = [
                'id'        => $employee->ID,
                'name'      => $employee->display_name,
                'email'     => $employee->user_email,
                'balance'   => $wallet->get_balance( $employee->ID ),
                'national_id' => get_user_meta( $employee->ID, 'national_id', true ),
                'phone'     => get_user_meta( $employee->ID, 'mobile', true ),
            ];
        }

        return $this->respond_with_format( $request, $rows, [ 'id', 'name', 'email', 'balance', 'national_id', 'phone' ], 'company-employees.csv' );
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
            $rows[] = [
                'id'            => $user->ID,
                'name'          => $user->display_name,
                'email'         => $user->user_email,
                'balance'       => $wallet->get_balance( $user->ID ),
                'store_name'    => get_user_meta( $user->ID, '_cwm_store_name', true ),
                'pending_payouts' => $this->get_pending_payout_total( $user->ID ),
            ];
        }

        return $this->respond_with_format( $request, $rows, [ 'id', 'name', 'email', 'balance', 'store_name', 'pending_payouts' ], 'merchants.csv' );
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
            return false;
        }

        $user = wp_get_current_user();

        return user_can( $user, 'manage_wallets' );
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
     * Confirm a payment from an employee.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function confirm_payment( WP_REST_Request $request ) {
        global $wpdb;

        $employee_id = get_current_user_id();
        $request_id  = (int) $request->get_param( 'request_id' );
        $otp_code    = sanitize_text_field( $request->get_param( 'otp_code' ) );

        $table   = $wpdb->prefix . 'cwm_payment_requests';
        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND employee_id = %d",
                $request_id,
                $employee_id
            ),
            ARRAY_A
        );

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

        $wallet  = new Wallet_System();
        $success = $wallet->transfer( $employee_id, (int) $payment['merchant_id'], (float) $payment['amount'] );

        if ( ! $success ) {
            return new WP_Error( 'cwm_insufficient_funds', __( 'Insufficient funds.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $wpdb->update(
            $table,
            [
                'status'      => 'completed',
                'metadata'    => wp_json_encode( [ 'confirmed_at' => gmdate( 'c', $now ) ] ),
                'failed_attempts' => 0,
            ],
            [ 'id' => $request_id ]
        );

        $logger = new Transaction_Logger();
        $logger->log( 'payment', $employee_id, (int) $payment['merchant_id'], (float) $payment['amount'], 'completed', [
            'related_request' => $request_id,
            'context'         => 'employee_payment',
        ] );

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'Payment confirmed.', 'company-wallet-manager' ),
            ]
        );
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
        $national_id = sanitize_text_field( $request->get_param( 'employee_national_id' ) );
        $amount      = (float) $request->get_param( 'amount' );

        if ( $amount <= 0 ) {
            return new WP_Error( 'cwm_invalid_amount', __( 'Amount must be greater than zero.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

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

        if ( empty( $employee ) ) {
            return new WP_Error( 'employee_not_found', __( 'Employee not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $employee_id = (int) $employee[0];

        $otp      = wp_rand( 100000, 999999 );
        $expires  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) + ( 5 * MINUTE_IN_SECONDS ) );
        $table    = $wpdb->prefix . 'cwm_payment_requests';
        $inserted = $wpdb->insert(
            $table,
            [
                'merchant_id'     => $merchant_id,
                'employee_id'     => $employee_id,
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
            'metadata'        => [ 'expires_at' => $expires ],
        ] );

        return rest_ensure_response(
            [
                'status'     => 'success',
                'message'    => __( 'Payment request created.', 'company-wallet-manager' ),
                'request_id' => $request_id,
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
            wp_set_current_user( $decoded->data->user->id );
            return true;
        } catch ( \Exception $e ) {
            return new \WP_Error( 'jwt_auth_invalid_token', $e->getMessage(), array( 'status' => 403 ) );
        }
    }


    /**
     * Check if the user is a merchant.
     *
     * @return bool
     */
    public function merchant_permission_check( WP_REST_Request $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }

        return $this->user_has_role( wp_get_current_user(), 'merchant' );
    }

    /**
     * Check if the user is an employee.
     *
     * @return bool
     */
    public function employee_permission_check( WP_REST_Request $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }

        return $this->user_has_role( wp_get_current_user(), 'employee' );
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
}

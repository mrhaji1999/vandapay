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

        register_rest_route( $this->namespace, '/payment/confirm', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'confirm_payment' ],
                'permission_callback' => [ $this, 'employee_permission_check' ],
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
                "SELECT id, merchant_id, amount, status, created_at FROM $table WHERE employee_id = %d AND status = %s ORDER BY created_at DESC",
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
        // Accept multiple aliases for better compatibility with clients
        $national_id = sanitize_text_field( $request->get_param( 'employee_national_id' ) ?: $request->get_param( 'national_id' ) ?: $request->get_param( 'nid' ) );
        $mobile      = sanitize_text_field( $request->get_param( 'mobile' ) ?: $request->get_param( 'employee_mobile' ) );
        $amount      = (float) $request->get_param( 'amount' );

        if ( $amount <= 0 ) {
            return new WP_Error( 'cwm_invalid_amount', __( 'Amount must be greater than zero.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Try by national_id first (both meta keys), otherwise by mobile meta/username
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
            return new WP_Error( 'employee_not_found', __( 'کارمند با مشخصات واردD0شده یافت نشد. (کد ملی/شماره همراه)', 'company-wallet-manager' ), [ 'status' => 404 ] );
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
}

<?php

namespace CWM;

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
     * Constructor.
     */
    public function __construct() {
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
        // Register the payment request route.
        register_rest_route( $this->namespace, '/payment/request', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'request_payment' ),
                'permission_callback' => array( $this, 'merchant_permission_check' ),
                'args'                => array(
                    'national_id' => array(
                        'required' => true,
                        'validate_callback' => 'is_string',
                    ),
                    'amount' => array(
                        'required' => true,
                        'validate_callback' => 'is_numeric',
                    ),
                ),
            ),
        ) );

        // Register the payment confirmation route.
        register_rest_route( $this->namespace, '/payment/confirm', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'confirm_payment' ),
                'permission_callback' => array( $this, 'employee_permission_check' ),
                'args'                => array(
                    'request_id' => array(
                        'required' => true,
                        'validate_callback' => 'is_numeric',
                    ),
                    'otp_code' => array(
                        'required' => true,
                        'validate_callback' => 'is_string',
                    ),
                ),
            ),
        ) );

        // Register the token generation route.
        register_rest_route( $this->namespace, '/token', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'generate_token' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'username' => array(
                        'required' => true,
                        'validate_callback' => 'is_string',
                    ),
                    'password' => array(
                        'required' => true,
                        'validate_callback' => 'is_string',
                    ),
                ),
            ),
        ) );

        // Register the wallet balance route.
        register_rest_route( $this->namespace, '/wallet/balance', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_wallet_balance' ),
                'permission_callback' => array( $this, 'any_authenticated_user_permission_check' ),
            ),
        ) );

        // Register the authenticated user's profile route.
        register_rest_route( $this->namespace, '/profile', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_profile' ),
                'permission_callback' => array( $this, 'any_authenticated_user_permission_check' ),
            ),
        ) );

        // Register the wallet charge route.
        register_rest_route( $this->namespace, '/wallet/charge', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'charge_wallet' ),
                'permission_callback' => array( $this, 'company_permission_check' ),
                'args'                => array(
                    'user_id' => array(
                        'required' => true,
                        'validate_callback' => 'is_numeric',
                    ),
                    'amount' => array(
                        'required' => true,
                        'validate_callback' => 'is_numeric',
                    ),
                ),
            ),
        ) );

        // Register the payout request route.
        register_rest_route( $this->namespace, '/payout/request', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'request_payout' ),
                'permission_callback' => array( $this, 'merchant_permission_check' ),
                'args'                => array(
                    'amount' => array(
                        'required' => true,
                        'validate_callback' => 'is_numeric',
                    ),
                    'selected_account_id' => array(
                        'required' => true,
                        'validate_callback' => 'is_string',
                    ),
                ),
            ),
        ) );

        // Register the payout status route.
        register_rest_route( $this->namespace, '/payout/status', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_payout_status' ),
                'permission_callback' => array( $this, 'merchant_or_finance_officer_permission_check' ),
            ),
        ) );

        // Register the transaction history route.
        register_rest_route( $this->namespace, '/transactions/history', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_transaction_history' ),
                'permission_callback' => array( $this, 'any_authenticated_user_permission_check' ),
            ),
        ) );
    }

    /**
     * Get the transaction history for the current user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_transaction_history( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'cwm_transactions';
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE sender_id = %d OR receiver_id = %d ORDER BY created_at DESC",
            $user_id,
            $user_id
        ) );

        return new \WP_REST_Response( array( 'status' => 'success', 'data' => $results ), 200 );
    }

    /**
     * Get the status of all payout requests.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_payout_status( $request ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cwm_payout_requests';
        $results = $wpdb->get_results( "SELECT * FROM $table_name" );

        return new \WP_REST_Response( array( 'status' => 'success', 'data' => $results ), 200 );
    }

    /**
     * Request a payout for a merchant.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function request_payout( $request ) {
        global $wpdb;

        $merchant_id = get_current_user_id();
        $amount      = $request['amount'];
        $bank_account = $request['selected_account_id'];

        // Check the merchant's balance.
        $wallet_system = new Wallet_System();
        $balance = $wallet_system->get_balance( $merchant_id );
        if ( $balance < $amount ) {
            return new \WP_Error( 'insufficient_funds', 'Insufficient funds.', array( 'status' => 400 ) );
        }

        // Create a new payout request.
        $table_name = $wpdb->prefix . 'cwm_payout_requests';
        $wpdb->insert(
            $table_name,
            array(
                'merchant_id'  => $merchant_id,
                'amount'       => $amount,
                'bank_account' => $bank_account,
                'status'       => 'pending',
            )
        );
        $request_id = $wpdb->insert_id;

        // Deduct the amount from the merchant's wallet.
        $wallet_system->update_balance( $merchant_id, -$amount );

        // Log the transaction.
        $logger = new Transaction_Logger();
        $logger->log( 'payout', $merchant_id, 0, $amount, 'pending' );

        return new \WP_REST_Response( array( 'status' => 'success', 'message' => 'Payout request created.', 'request_id' => $request_id ), 200 );
    }

    /**
     * Charge a user's wallet.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function charge_wallet( $request ) {
        $user_id = $request['user_id'];
        $amount  = $request['amount'];

        $wallet_system = new Wallet_System();
        $wallet_system->update_balance( $user_id, $amount );

        $new_balance = $wallet_system->get_balance( $user_id );

        // Log the transaction.
        $logger = new Transaction_Logger();
        $logger->log( 'charge', get_current_user_id(), $user_id, $amount, 'completed' );

        return new \WP_REST_Response( array( 'status' => 'success', 'data' => array( 'user_id' => $user_id, 'new_balance' => $new_balance ) ), 200 );
    }

    /**
     * Get the wallet balance for the current user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function get_wallet_balance( $request ) {
        $user_id = get_current_user_id();
        $wallet_system = new Wallet_System();
        $balance = $wallet_system->get_balance( $user_id );

        return new \WP_REST_Response( array( 'status' => 'success', 'data' => array( 'user_id' => $user_id, 'balance' => $balance ) ), 200 );
    }

    /**
     * Generate a JWT token for the user.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function generate_token( $request ) {
        $username = $request['username'];
        $password = $request['password'];

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            return new \WP_Error( 'invalid_credentials', 'Invalid username or password.', array( 'status' => 403 ) );
        }

        $issued_at = time();
        $expiration_time = $issued_at + ( 60 * 60 ); // 1 hour

        // Ensure the secret key is defined in wp-config.php.
        if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) ) {
            return new \WP_Error( 'jwt_auth_secret_not_defined', 'JWT secret key is not defined in wp-config.php.', array( 'status' => 500 ) );
        }

        $payload = array(
            'iss' => get_bloginfo( 'url' ),
            'iat' => $issued_at,
            'exp' => $expiration_time,
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                ),
            ),
        );

        $token = \Firebase\JWT\JWT::encode( $payload, JWT_AUTH_SECRET_KEY, 'HS256' );

        return new \WP_REST_Response( array( 'token' => $token ), 200 );
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

        $data = array(
            'id'           => $user->ID,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'roles'        => $user->roles,
        );

        return new \WP_REST_Response( array( 'status' => 'success', 'data' => $data ), 200 );
    }

    /**
     * Confirm a payment from an employee.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function confirm_payment( $request ) {
        global $wpdb;

        $employee_id = get_current_user_id();
        $request_id  = $request['request_id'];
        $otp_code    = $request['otp_code'];

        // Get the payment request.
        $table_name = $wpdb->prefix . 'cwm_payment_requests';
        $payment_request = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND employee_id = %d AND status = 'pending'",
            $request_id,
            $employee_id
        ) );

        if ( ! $payment_request ) {
            return new \WP_Error( 'invalid_request', 'Invalid payment request.', array( 'status' => 400 ) );
        }

        // Validate the OTP.
        if ( $payment_request->otp !== $otp_code ) {
            return new \WP_Error( 'invalid_otp', 'Invalid OTP code.', array( 'status' => 400 ) );
        }

        // Check the employee's balance.
        $wallet_system = new Wallet_System();
        $balance = $wallet_system->get_balance( $employee_id );
        if ( $balance < $payment_request->amount ) {
            return new \WP_Error( 'insufficient_funds', 'Insufficient funds.', array( 'status' => 400 ) );
        }

        // Transfer the funds.
        $wallet_system->transfer( $employee_id, $payment_request->merchant_id, $payment_request->amount );

        // Update the payment request status.
        $wpdb->update(
            $table_name,
            array( 'status' => 'completed' ),
            array( 'id' => $request_id )
        );

        // Log the transaction.
        $logger = new Transaction_Logger();
        $logger->log( 'transfer', $employee_id, $payment_request->merchant_id, $payment_request->amount, 'completed' );

        return new \WP_REST_Response( array( 'status' => 'success', 'message' => 'Payment confirmed.' ), 200 );
    }

    /**
     * Request a payment from an employee.
     *
     * @param \WP_REST_Request $request Full details about the request.
     * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
     */
    public function request_payment( $request ) {
        global $wpdb;

        $merchant_id = get_current_user_id();
        $national_id = $request['national_id'];
        $amount      = $request['amount'];

        // Find the employee by their national ID.
        $employee = get_users( array(
            'meta_key'   => 'national_id',
            'meta_value' => $national_id,
            'number'     => 1,
            'fields'     => 'ID',
        ) );

        if ( empty( $employee ) ) {
            return new \WP_Error( 'employee_not_found', 'Employee not found.', array( 'status' => 404 ) );
        }
        $employee_id = $employee[0];

        // Generate a random OTP.
        $otp = rand( 100000, 999999 );

        // Create a new payment request.
        $table_name = $wpdb->prefix . 'cwm_payment_requests';
        $wpdb->insert(
            $table_name,
            array(
                'merchant_id' => $merchant_id,
                'employee_id' => $employee_id,
                'amount'      => $amount,
                'otp'         => $otp,
                'status'      => 'pending',
            )
        );
        $request_id = $wpdb->insert_id;

        // "Send" the OTP.
        $sms_handler = new SMS_Handler();
        $employee_phone = get_user_meta( $employee_id, 'mobile', true );
        $sms_handler->send_otp( $employee_phone, $otp );

        return new \WP_REST_Response( array( 'status' => 'success', 'message' => 'Payment request created.', 'request_id' => $request_id ), 200 );
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
    public function merchant_permission_check( $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }
        return user_can( wp_get_current_user(), 'merchant' );
    }

    /**
     * Check if the user is an employee.
     *
     * @return bool
     */
    public function employee_permission_check( $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }
        return user_can( wp_get_current_user(), 'employee' );
    }

    /**
     * Check if the user is a company or an admin.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function company_permission_check( $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }
        $user = wp_get_current_user();
        return user_can( $user, 'company' ) || user_can( $user, 'manage_options' );
    }

    /**
     * Check if the user is a merchant or a finance officer.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function merchant_or_finance_officer_permission_check( $request ) {
        if ( is_wp_error( $this->validate_token( $request ) ) ) {
            return false;
        }
        $user = wp_get_current_user();
        return user_can( $user, 'merchant' ) || user_can( $user, 'finance_officer' );
    }

    /**
     * Check if any user is authenticated.
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function any_authenticated_user_permission_check( $request ) {
        return ! is_wp_error( $this->validate_token( $request ) );
    }
}

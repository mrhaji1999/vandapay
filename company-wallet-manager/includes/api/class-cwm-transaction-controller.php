<?php
namespace CWM\API;

use CWM\Category_Manager;
use CWM\SMS_Handler;
use CWM\Transaction_Logger;
use CWM\Wallet_System;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function rest_ensure_response;

/**
 * REST controller for merchant initiated transactions.
 */
class Transaction_Controller {
    /**
     * REST namespace.
     *
     * @var string
     */
    protected $namespace = 'cwm/v1';

    /**
     * Category manager instance.
     *
     * @var Category_Manager
     */
    protected $category_manager;

    /**
     * Wallet system instance.
     *
     * @var Wallet_System
     */
    protected $wallet_system;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->category_manager = new Category_Manager();
        $this->wallet_system    = new Wallet_System();

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/merchant/transactions/category-limit',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_category_limit' ],
                'permission_callback' => [ $this, 'ensure_merchant_permission' ],
                'args'                => [
                    'national_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value ) {
                            return is_string( $value ) && '' !== trim( $value );
                        },
                    ],
                    'amount' => [
                        'required'          => true,
                        'validate_callback' => function( $value ) {
                            return is_numeric( $value ) && (float) $value > 0;
                        },
                    ],
                    'category_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value ) {
                            return is_numeric( $value ) && (int) $value > 0;
                        },
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/merchant/transactions/confirm',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'confirm_payment' ],
                'permission_callback' => [ $this, 'ensure_merchant_permission' ],
                'args'                => [
                    'request_id' => [
                        'required'          => true,
                        'validate_callback' => function( $value ) {
                            return is_numeric( $value ) && (int) $value > 0;
                        },
                    ],
                    'otp_code' => [
                        'required'          => true,
                        'validate_callback' => function( $value ) {
                            return is_string( $value ) && '' !== trim( $value );
                        },
                    ],
                ],
            ]
        );
    }

    /**
     * Ensure the authenticated user is a merchant.
     *
     * @return bool|WP_Error
     */
    public function ensure_merchant_permission() {
        $user = wp_get_current_user();

        if ( ! $user || ! in_array( 'merchant', (array) $user->roles, true ) ) {
            return new WP_Error( 'cwm_permission_denied', __( 'دسترسی برای پذیرندگان مجاز است.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        return true;
    }

    /**
     * Handle category limit check and OTP dispatch.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_category_limit( WP_REST_Request $request ) {
        global $wpdb;

        $merchant_id = get_current_user_id();
        $national_id = sanitize_text_field( (string) $request->get_param( 'national_id' ) );
        $amount      = (float) $request->get_param( 'amount' );
        $category_id = (int) $request->get_param( 'category_id' );

        $employee_id = $this->resolve_employee_by_national_id( $national_id );
        if ( is_wp_error( $employee_id ) ) {
            return $employee_id;
        }

        $assigned_categories = $this->category_manager->get_merchant_categories( $merchant_id );
        $assigned_ids        = array_map( 'intval', wp_list_pluck( $assigned_categories, 'id' ) );

        if ( ! in_array( $category_id, $assigned_ids, true ) ) {
            return new WP_Error( 'cwm_category_not_assigned', __( 'این دسته‌بندی برای پذیرنده فعال نیست.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        $limit_entry = $this->find_employee_category_limit( $employee_id, $category_id );
        if ( ! $limit_entry ) {
            return new WP_Error( 'cwm_category_limit_missing', __( 'هیچ سقفی برای این دسته‌بندی ثبت نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $remaining      = max( 0.0, (float) $limit_entry['limit'] - (float) $limit_entry['spent'] );
        $wallet_balance = $this->wallet_system->get_balance( $employee_id );
        $available      = min( $remaining, $wallet_balance );

        if ( $available <= 0 ) {
            return new WP_Error( 'cwm_category_limit_exceeded', __( 'سقف یا موجودی کاربر برای این دسته‌بندی تکمیل شده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        if ( $amount > $available ) {
            return new WP_Error( 'cwm_category_limit_exceeded', __( 'مبلغ درخواستی از سقف مجاز یا موجودی کیف پول بیشتر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $otp     = str_pad( (string) wp_rand( 100000, 999999 ), 6, '0', STR_PAD_LEFT );
        $expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) + ( 5 * MINUTE_IN_SECONDS ) );

        $table    = $wpdb->prefix . 'cwm_payment_requests';
        $inserted = $wpdb->insert(
            $table,
            [
                'merchant_id'     => $merchant_id,
                'employee_id'     => $employee_id,
                'category_id'     => $category_id,
                'amount'          => $amount,
                'otp'             => $otp,
                'otp_expires_at'  => $expires,
                'failed_attempts' => 0,
                'status'          => 'pending',
            ]
        );

        if ( false === $inserted ) {
            return new WP_Error( 'cwm_payment_request_failed', __( 'ثبت درخواست پرداخت با خطا مواجه شد.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        $request_id = (int) $wpdb->insert_id;

        $phone = get_user_meta( $employee_id, 'mobile', true );
        if ( ! empty( $phone ) ) {
            $sms = new SMS_Handler();
            $sms->send_otp( $phone, $otp );
        }

        $logger = new Transaction_Logger();
        $logger->log(
            'payment_request',
            $merchant_id,
            $employee_id,
            $amount,
            'pending',
            [
                'related_request' => $request_id,
                'context'         => 'merchant_checkout',
                'metadata'        => [
                    'category_id'   => $category_id,
                    'limit'         => (float) $limit_entry['limit'],
                    'remaining'     => $remaining,
                    'wallet_balance'=> $wallet_balance,
                    'otp_expires'   => $expires,
                ],
            ]
        );

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'درخواست پرداخت ثبت و رمز یکبار مصرف ارسال شد.', 'company-wallet-manager' ),
                'data'    => [
                    'request_id'      => $request_id,
                    'employee_id'     => $employee_id,
                    'limit'           => (float) $limit_entry['limit'],
                    'spent'           => (float) $limit_entry['spent'],
                    'remaining'       => $remaining,
                    'wallet_balance'  => $wallet_balance,
                    'available'       => $available,
                    'amount'          => $amount,
                ],
            ]
        );
    }

    /**
     * Confirm payment by validating OTP and moving funds.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function confirm_payment( WP_REST_Request $request ) {
        global $wpdb;

        $request_id = (int) $request->get_param( 'request_id' );
        $otp_code   = sanitize_text_field( (string) $request->get_param( 'otp_code' ) );
        $merchant_id = get_current_user_id();

        $table   = $wpdb->prefix . 'cwm_payment_requests';
        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND merchant_id = %d",
                $request_id,
                $merchant_id
            ),
            ARRAY_A
        );

        if ( ! $payment ) {
            return new WP_Error( 'cwm_invalid_request', __( 'درخواست پرداخت معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        if ( 'completed' === $payment['status'] ) {
            return new WP_Error( 'cwm_request_completed', __( 'این پرداخت قبلاً تکمیل شده است.', 'company-wallet-manager' ), [ 'status' => 409 ] );
        }

        if ( ! empty( $payment['locked_at'] ) || (int) $payment['failed_attempts'] >= 5 ) {
            return new WP_Error( 'cwm_request_locked', __( 'به دلیل تلاش‌های ناموفق متعدد، این درخواست مسدود شده است.', 'company-wallet-manager' ), [ 'status' => 423 ] );
        }

        $now = current_time( 'timestamp', true );
        if ( ! empty( $payment['otp_expires_at'] ) && $now > strtotime( $payment['otp_expires_at'] ) ) {
            $wpdb->update( $table, [ 'status' => 'expired' ], [ 'id' => $request_id ] );

            return new WP_Error( 'cwm_otp_expired', __( 'رمز یکبار مصرف منقضی شده است.', 'company-wallet-manager' ), [ 'status' => 410 ] );
        }

        if ( $payment['otp'] !== $otp_code ) {
            $attempts = (int) $payment['failed_attempts'] + 1;
            $data     = [ 'failed_attempts' => $attempts ];

            if ( $attempts >= 5 ) {
                $data['locked_at'] = gmdate( 'Y-m-d H:i:s', $now );
                $data['status']    = 'locked';
            }

            $wpdb->update( $table, $data, [ 'id' => $request_id ] );

            return new WP_Error( 'cwm_invalid_otp', __( 'رمز وارد شده صحیح نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $employee_id = (int) $payment['employee_id'];
        $category_id = isset( $payment['category_id'] ) ? (int) $payment['category_id'] : 0;
        $amount      = (float) $payment['amount'];

        $remaining_after = null;
        if ( $category_id > 0 ) {
            $limit_entry = $this->find_employee_category_limit( $employee_id, $category_id );
            if ( ! $limit_entry ) {
                return new WP_Error( 'cwm_category_limit_missing', __( 'هیچ سقفی برای این دسته‌بندی ثبت نشده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            $remaining = max( 0.0, (float) $limit_entry['limit'] - (float) $limit_entry['spent'] );
            if ( $amount > $remaining ) {
                return new WP_Error( 'cwm_category_limit_exceeded', __( 'سقف این دسته‌بندی تکمیل شده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            $consumed = $this->category_manager->consume_allowance( $employee_id, $category_id, $amount );
            if ( ! $consumed ) {
                return new WP_Error( 'cwm_category_limit_exceeded', __( 'سقف این دسته‌بندی تکمیل شده است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
            }

            $remaining_after = $this->category_manager->get_remaining_allowance( $employee_id, $category_id );
        }

        $transfer_success = $this->wallet_system->transfer( $employee_id, $merchant_id, $amount );
        if ( ! $transfer_success ) {
            if ( $category_id > 0 ) {
                $this->category_manager->release_allowance( $employee_id, $category_id, $amount );
            }

            return new WP_Error( 'cwm_insufficient_funds', __( 'موجودی کیف پول کاربر کافی نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $metadata = [
            'confirmed_at' => gmdate( 'c', $now ),
            'confirmed_by' => get_current_user_id(),
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
        $logger->log(
            'payment',
            $employee_id,
            $merchant_id,
            $amount,
            'completed',
            [
                'related_request' => $request_id,
                'context'         => 'merchant_checkout',
                'metadata'        => $metadata,
            ]
        );

        $employee_balance = $this->wallet_system->get_balance( $employee_id );
        $remaining_after  = null !== $remaining_after ? $remaining_after : ( $category_id > 0 ? $this->category_manager->get_remaining_allowance( $employee_id, $category_id ) : 0 );

        return rest_ensure_response(
            [
                'status'  => 'success',
                'message' => __( 'پرداخت با موفقیت انجام شد.', 'company-wallet-manager' ),
                'data'    => [
                    'wallet_balance'    => $employee_balance,
                    'category_remaining'=> $remaining_after,
                    'request_id'        => $request_id,
                ],
            ]
        );
    }

    /**
     * Resolve employee by national ID meta.
     *
     * @param string $national_id National ID value.
     *
     * @return int|WP_Error
     */
    protected function resolve_employee_by_national_id( $national_id ) {
        $national_id = trim( (string) $national_id );

        if ( '' === $national_id ) {
            return new WP_Error( 'cwm_invalid_national_id', __( 'کد ملی معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $users = get_users(
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

        if ( empty( $users ) ) {
            return new WP_Error( 'employee_not_found', __( 'کاربری با این کد ملی یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return (int) $users[0];
    }

    /**
     * Find category limit entry for an employee.
     *
     * @param int $employee_id Employee identifier.
     * @param int $category_id Category identifier.
     *
     * @return array<string, mixed>|null
     */
    protected function find_employee_category_limit( $employee_id, $category_id ) {
        $limits = $this->category_manager->get_employee_limits( $employee_id );

        foreach ( $limits as $limit ) {
            if ( (int) $limit['category_id'] === (int) $category_id ) {
                return $limit;
            }
        }

        return null;
    }
}

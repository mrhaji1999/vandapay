<?php

namespace CWM;

use WP_Error;

/**
 * Handles merchant onboarding and wallet provisioning.
 */
class Merchant_Registration {

        const RATE_LIMIT_WINDOW = 600;
        const RATE_LIMIT_MAX    = 3;

        /**
         * Process the registration request.
         *
         * @param \WP_REST_Request $request The REST request.
         * @return \WP_REST_Response|WP_Error
         */
        public function register( $request ) {
                try {
                        $this->enforce_rate_limit();
                } catch ( \RuntimeException $e ) {
                        return new WP_Error( 'cwm_rate_limited', __( 'Too many requests. Please try again later.', 'company-wallet-manager' ), [ 'status' => 429 ] );
                }

                $payload = $this->sanitize_payload( $request->get_json_params() );

                $validation = $this->validate_payload( $payload );
                if ( is_wp_error( $validation ) ) {
                        return $validation;
                }

                $user_id = $this->create_user( $payload );
                if ( is_wp_error( $user_id ) ) {
                        return $user_id;
                }

                $this->store_profile_meta( $user_id, $payload );

                $wallet = new Wallet_System();
                $wallet->get_balance( $user_id ); // Ensure wallet row exists.

                $token = $this->maybe_generate_token( $user_id );

                return rest_ensure_response(
                        [
                                'status'  => 'success',
                                'message' => __( 'Merchant registered successfully.', 'company-wallet-manager' ),
                                'data'    => [
                                        'user_id' => $user_id,
                                        'token'   => $token,
                                ],
                        ]
                );
        }

        /**
         * Normalize payload data.
         *
         * @param array $payload Raw input.
         * @return array
         */
        private function sanitize_payload( $payload ) {
                $payload = is_array( $payload ) ? $payload : [];

                $fields = [
                        'full_name'     => isset( $payload['full_name'] ) ? sanitize_text_field( $payload['full_name'] ) : '',
                        'store_name'    => isset( $payload['store_name'] ) ? sanitize_text_field( $payload['store_name'] ) : '',
                        'store_address' => isset( $payload['store_address'] ) ? sanitize_textarea_field( $payload['store_address'] ) : '',
                        'phone'         => isset( $payload['phone'] ) ? sanitize_text_field( $payload['phone'] ) : '',
                        'mobile'        => isset( $payload['mobile'] ) ? sanitize_text_field( $payload['mobile'] ) : '',
                        'email'         => isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '',
                        'password'      => isset( $payload['password'] ) ? $payload['password'] : '',
                ];

                return $fields;
        }

        /**
         * Validate payload.
         *
         * @param array $data Sanitized data.
         * @return true|WP_Error
         */
        private function validate_payload( array $data ) {
                $required = [ 'full_name', 'store_name', 'store_address', 'phone', 'mobile', 'email', 'password' ];

                foreach ( $required as $field ) {
                        if ( empty( $data[ $field ] ) ) {
                                return new WP_Error( 'cwm_missing_field', sprintf( __( '%s is required.', 'company-wallet-manager' ), $field ), [ 'status' => 400 ] );
                        }
                }

                if ( ! is_email( $data['email'] ) ) {
                        return new WP_Error( 'cwm_invalid_email', __( 'A valid email address is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
                }

                if ( email_exists( $data['email'] ) ) {
                        return new WP_Error( 'cwm_email_exists', __( 'An account with this email already exists.', 'company-wallet-manager' ), [ 'status' => 409 ] );
                }

                $existing = get_users(
                        [
                                'meta_key'   => 'cwm_phone',
                                'meta_value' => $data['phone'],
                                'fields'     => 'ids',
                        ]
                );
                if ( ! empty( $existing ) ) {
                        return new WP_Error( 'cwm_phone_exists', __( 'An account with this phone number already exists.', 'company-wallet-manager' ), [ 'status' => 409 ] );
                }

                return true;
        }

        /**
         * Create the WordPress user.
         *
         * @param array $data Validated data.
         * @return int|WP_Error
         */
        private function create_user( array $data ) {
                $user_id = wp_insert_user(
                        [
                                'user_login'   => $this->generate_login( $data['email'] ),
                                'user_pass'    => $data['password'],
                                'user_email'   => $data['email'],
                                'display_name' => $data['full_name'],
                                'first_name'   => $data['full_name'],
                                'role'         => 'merchant',
                        ]
                );

                if ( is_wp_error( $user_id ) ) {
                        return $user_id;
                }

                update_user_meta( $user_id, 'cwm_phone', $data['phone'] );
                update_user_meta( $user_id, 'cwm_mobile', $data['mobile'] );

                return $user_id;
        }

        /**
         * Persist additional profile details.
         *
         * @param int   $user_id User identifier.
         * @param array $data    Data payload.
         */
        private function store_profile_meta( $user_id, array $data ) {
                update_user_meta( $user_id, '_cwm_store_name', $data['store_name'] );
                update_user_meta( $user_id, '_cwm_store_address', $data['store_address'] );
                update_user_meta( $user_id, '_cwm_store_phone', $data['phone'] );
        }

        /**
         * Optionally generate a JWT token for immediate API access.
         *
         * @param int $user_id User identifier.
         * @return string|null
         */
        private function maybe_generate_token( $user_id ) {
                if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) ) {
                        return null;
                }

                $issued_at  = time();
                $expiration = $issued_at + HOUR_IN_SECONDS;

                $payload = [
                        'iss' => get_bloginfo( 'url' ),
                        'iat' => $issued_at,
                        'exp' => $expiration,
                        'data' => [
                                'user' => [
                                        'id' => $user_id,
                                ],
                        ],
                ];

                return \Firebase\JWT\JWT::encode( $payload, JWT_AUTH_SECRET_KEY, 'HS256' );
        }

        /**
         * Generate a unique username from an email.
         *
         * @param string $email Email address.
         * @return string
         */
        private function generate_login( $email ) {
                $base   = sanitize_user( current( explode( '@', $email ) ), true );
                $login  = $base;
                $suffix = 1;

                while ( username_exists( $login ) ) {
                        $login = $base . $suffix;
                        ++$suffix;
                }

                return $login;
        }

        /**
         * Simple per-IP rate limiting.
         */
        private function enforce_rate_limit() {
                $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
                $key     = 'cwm_merchant_rate_' . md5( $ip );
                $current = (int) get_transient( $key );

                if ( $current >= self::RATE_LIMIT_MAX ) {
                        throw new \RuntimeException( 'rate_limited' );
                }

                if ( 0 === $current ) {
                        set_transient( $key, 1, self::RATE_LIMIT_WINDOW );
                } else {
                        set_transient( $key, $current + 1, self::RATE_LIMIT_WINDOW );
                }
        }
}


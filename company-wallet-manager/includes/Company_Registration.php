<?php

namespace CWM;

use WP_Error;

/**
 * Handle company onboarding via REST.
 */
class Company_Registration {

        const RATE_LIMIT_WINDOW = 600; // 10 minutes.
        const RATE_LIMIT_MAX     = 3;

       /**
        * Process the incoming request and create a pending company record.
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

                $company_type = $request->get_param( 'company_type' );
                $company_type = $company_type ? strtolower( $company_type ) : 'legal';

                if ( ! in_array( $company_type, [ 'legal', 'real' ], true ) ) {
                        return new WP_Error( 'cwm_invalid_company_type', __( 'Invalid company type supplied.', 'company-wallet-manager' ), [ 'status' => 400 ] );
                }

                $data = $this->sanitize_payload( $request->get_json_params(), $company_type );

                $validation = $this->validate_payload( $data, $company_type );
                if ( is_wp_error( $validation ) ) {
                        return $validation;
                }

                $user_id = $this->create_or_update_user( $data );
                if ( is_wp_error( $user_id ) ) {
                        return $user_id;
                }

                $post_id = $this->create_company_post( $user_id, $company_type, $data );

               $this->notify_admins( $post_id, $data );

               return rest_ensure_response(
                       [
                               'status'  => 'success',
                               'message' => __( 'Your registration request has been submitted and awaits admin approval.', 'company-wallet-manager' ),
                               'company' => [
                                       'post_id'      => $post_id,
                                       'wp_user_id'   => $user_id,
                                       'company_type' => $company_type,
                               ],
                       ]
               );
       }

       /**
        * Create a company entry initiated by an administrator (no rate limit).
        *
        * @param array $payload Raw payload provided by the admin UI.
        * @param array $args    Optional arguments (e.g. post_status).
        *
        * @return array|WP_Error
        */
       public function register_from_admin( array $payload, array $args = [] ) {
               $company_type = isset( $payload['company_type'] ) ? strtolower( $payload['company_type'] ) : 'legal';

               $data = $this->sanitize_payload( $payload, $company_type );

               if ( empty( $data['password'] ) ) {
                       $data['password'] = wp_generate_password( 12 );
               }

               $validation = $this->validate_payload( $data, $company_type );
               if ( is_wp_error( $validation ) ) {
                       return $validation;
               }

               $user_id = $this->create_or_update_user( $data );
               if ( is_wp_error( $user_id ) ) {
                       return $user_id;
               }

               $post_id = $this->create_company_post( $user_id, $company_type, $data );

               if ( ! empty( $args['status'] ) ) {
                       $status = sanitize_key( $args['status'] );
                       wp_update_post(
                               [
                                       'ID'          => $post_id,
                                       'post_status' => $status,
                               ]
                       );
               }

               return [
                       'status'  => 'success',
                       'company' => [
                               'post_id'      => $post_id,
                               'wp_user_id'   => $user_id,
                               'company_type' => $company_type,
                       ],
               ];
       }

        /**
         * Normalize incoming parameters.
         *
         * @param array  $payload Submitted payload.
         * @param string $company_type legal|real
         * @return array
         */
        private function sanitize_payload( $payload, $company_type ) {
                $payload = is_array( $payload ) ? $payload : [];

                $fields = [
                        'company_type'  => sanitize_text_field( $company_type ),
                        'company_name'  => isset( $payload['company_name'] ) ? sanitize_text_field( $payload['company_name'] ) : '',
                        'company_email' => isset( $payload['company_email'] ) ? sanitize_email( $payload['company_email'] ) : '',
                        'company_phone' => isset( $payload['company_phone'] ) ? sanitize_text_field( $payload['company_phone'] ) : '',
                        'economic_code' => isset( $payload['economic_code'] ) ? sanitize_text_field( $payload['economic_code'] ) : '',
                        'national_id'   => isset( $payload['national_id'] ) ? sanitize_text_field( $payload['national_id'] ) : '',
                        'password'      => isset( $payload['password'] ) ? $payload['password'] : '',
                        'full_name'     => isset( $payload['full_name'] ) ? sanitize_text_field( $payload['full_name'] ) : '',
                        'email'         => isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '',
                        'phone'         => isset( $payload['phone'] ) ? sanitize_text_field( $payload['phone'] ) : '',
                ];

                return $fields;
        }

        /**
         * Validate the request payload.
         *
         * @param array  $data Sanitized data.
         * @param string $company_type legal|real
         * @return true|WP_Error
         */
        private function validate_payload( array $data, $company_type ) {
                $required = [ 'password' ];

                if ( 'legal' === $company_type ) {
                        $required = array_merge( $required, [ 'company_name', 'company_email', 'company_phone' ] );
                } else {
                        $required = array_merge( $required, [ 'full_name', 'email', 'phone' ] );
                }

                foreach ( $required as $field ) {
                        if ( empty( $data[ $field ] ) ) {
                                return new WP_Error( 'cwm_missing_field', sprintf( __( '%s is required.', 'company-wallet-manager' ), $field ), [ 'status' => 400 ] );
                        }
                }

                $email = 'legal' === $company_type ? $data['company_email'] : $data['email'];
                if ( ! is_email( $email ) ) {
                        return new WP_Error( 'cwm_invalid_email', __( 'A valid email address is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
                }

                if ( email_exists( $email ) ) {
                        return new WP_Error( 'cwm_email_exists', __( 'An account with this email already exists.', 'company-wallet-manager' ), [ 'status' => 409 ] );
                }

                $phone = 'legal' === $company_type ? $data['company_phone'] : $data['phone'];
                if ( empty( $phone ) ) {
                        return new WP_Error( 'cwm_invalid_phone', __( 'A phone number is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
                }

                $existing = get_users(
                        [
                                'meta_key'   => 'cwm_phone',
                                'meta_value' => $phone,
                                'fields'     => 'ids',
                        ]
                );
                if ( ! empty( $existing ) ) {
                        return new WP_Error( 'cwm_phone_exists', __( 'An account with this phone number already exists.', 'company-wallet-manager' ), [ 'status' => 409 ] );
                }

                return true;
        }

        /**
         * Creates a WordPress user for the company contact.
         *
         * @param array $data Validated data.
         * @return int|WP_Error
         */
        private function create_or_update_user( array $data ) {
                $email    = ! empty( $data['company_email'] ) ? $data['company_email'] : $data['email'];
                $fullName = ! empty( $data['company_name'] ) ? $data['company_name'] : $data['full_name'];

                $user_id = wp_insert_user(
                        [
                                'user_login' => $this->generate_login( $email ),
                                'user_email' => $email,
                                'display_name' => $fullName,
                                'first_name' => $fullName,
                                'role'        => 'company',
                                'user_pass'   => $data['password'],
                        ]
                );

                if ( is_wp_error( $user_id ) ) {
                        return $user_id;
                }

                update_user_meta( $user_id, 'cwm_phone', ! empty( $data['company_phone'] ) ? $data['company_phone'] : $data['phone'] );

                if ( ! empty( $data['national_id'] ) ) {
                        update_user_meta( $user_id, 'cwm_national_id', $data['national_id'] );
                }

                return $user_id;
        }

        /**
         * Create the custom post type entry to track approval and metadata.
         *
         * @param int    $user_id WordPress user identifier.
         * @param string $company_type Company type.
         * @param array  $data Submission data.
         * @return int Post ID.
         */
        private function create_company_post( $user_id, $company_type, array $data ) {
                $title = 'legal' === $company_type ? $data['company_name'] : $data['full_name'];

                $credit_amount = isset( $data['credit_amount'] ) ? floatval( $data['credit_amount'] ) : 0;

                $post_id = wp_insert_post(
                        [
                                'post_type'   => 'cwm_company',
                                'post_status' => 'pending',
                                'post_title'  => $title,
                                'meta_input'  => [
                                        '_cwm_company_user_id' => $user_id,
                                        '_cwm_company_type'    => $company_type,
                                        '_cwm_company_phone'   => ! empty( $data['company_phone'] ) ? $data['company_phone'] : $data['phone'],
                                        '_cwm_company_email'   => ! empty( $data['company_email'] ) ? $data['email'] : $data['email'],
                                        '_cwm_company_economic_code' => $data['economic_code'],
                                        '_cwm_company_national_id'   => $data['national_id'],
                                        '_cwm_company_credit'   => $credit_amount,
                                ],
                        ]
                );

                // If credit amount is provided, add it to company's wallet
                if ( $credit_amount > 0 ) {
                        $wallet_system = new Wallet_System();
                        $wallet_system->update_balance( $user_id, $credit_amount );
                }

                return (int) $post_id;
        }

        /**
         * Notify site administrators about the new registration request.
         *
         * @param int   $post_id The company post ID.
         * @param array $data    Submitted data.
         */
        private function notify_admins( $post_id, array $data ) {
                $subject = __( 'New company registration pending approval', 'company-wallet-manager' );
                $body    = sprintf(
                        "A new company registration has been submitted.\n\nCompany: %s\nEmail: %s\nPhone: %s\nReview: %s",
                        ! empty( $data['company_name'] ) ? $data['company_name'] : $data['full_name'],
                        ! empty( $data['company_email'] ) ? $data['company_email'] : $data['email'],
                        ! empty( $data['company_phone'] ) ? $data['company_phone'] : $data['phone'],
                        admin_url( sprintf( 'post.php?post=%d&action=edit', $post_id ) )
                );

                $admins = get_users( [ 'role' => 'administrator', 'fields' => [ 'user_email' ] ] );
                foreach ( $admins as $admin ) {
                        wp_mail( $admin->user_email, $subject, $body );
                }
        }

        /**
         * Generate a unique login value based on the email.
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
         * Basic rate limiting per IP address.
         *
         * @throws WP_Error When the limit is exceeded.
         */
        private function enforce_rate_limit() {
                $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
                $key     = 'cwm_company_rate_' . md5( $ip );
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


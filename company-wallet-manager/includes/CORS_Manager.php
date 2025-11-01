<?php

namespace CWM;

/**
 * Manage REST API CORS headers so the React panel can call WordPress endpoints from approved origins.
 */
class CORS_Manager {
        /**
         * Bootstrap hooks.
         */
        public function __construct() {
                add_action( 'init', [ $this, 'maybe_handle_preflight' ] );
                add_action( 'rest_api_init', [ $this, 'register_cors_headers' ], 15 );
        }

        /**
         * Ensure OPTIONS preflight requests receive the same headers as regular REST responses.
         */
        public function maybe_handle_preflight() {
                if ( 'OPTIONS' !== $_SERVER['REQUEST_METHOD'] ) {
                        return;
                }

                if ( empty( $_SERVER['REQUEST_URI'] ) ) {
                        return;
                }

                $rest_prefix = '/' . ltrim( rest_get_url_prefix(), '/' );
                if ( 0 !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ) {
                        return;
                }

                $origin = get_http_origin();
                if ( ! $origin || ! $this->is_origin_allowed( $origin ) ) {
                        return;
                }

                $this->send_cors_headers( $origin );

                // End execution early so WordPress does not try to render a page.
                status_header( 200 );
                exit;
        }

        /**
         * Attach CORS headers to REST responses for allowed origins.
         */
        public function register_cors_headers() {
                remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

                add_filter(
                        'rest_pre_serve_request',
                        function ( $value ) {
                                $origin = get_http_origin();
                                if ( $origin && $this->is_origin_allowed( $origin ) ) {
                                        $this->send_cors_headers( $origin );
                                }

                                return $value;
                        },
                        15
                );
        }

        /**
         * Determine whether the given origin has been whitelisted.
         *
         * @param string $origin The HTTP origin making the request.
         * @return bool
         */
        private function is_origin_allowed( $origin ) {
                $origin = $this->normalize_origin( $origin );
                if ( ! $origin ) {
                        return false;
                }

                $allowed_origins = $this->get_allowed_origins();

                return in_array( $origin, $allowed_origins, true );
        }

        /**
         * Retrieve the list of allowed origins.
         *
         * @return array
         */
        private function get_allowed_origins() {
                $defaults = array(
                        $this->normalize_origin( home_url() ),
                        $this->normalize_origin( site_url() ),
                );

                $configured = array();

                if ( defined( 'CWM_ALLOWED_CORS_ORIGINS' ) ) {
                        $raw = CWM_ALLOWED_CORS_ORIGINS;
                        if ( is_string( $raw ) ) {
                                $configured = array_merge( $configured, array_map( 'trim', explode( ',', $raw ) ) );
                        } elseif ( is_array( $raw ) ) {
                                $configured = array_merge( $configured, $raw );
                        }
                }

                $settings = get_option( 'cwm_settings', [] );
                if ( ! empty( $settings['allowed_origins'] ) ) {
                        $configured = array_merge( $configured, array_map( 'trim', explode( ',', $settings['allowed_origins'] ) ) );
                }

                $configured = array_map( [ $this, 'normalize_origin' ], $configured );
                $origins    = array_filter( array_merge( $defaults, $configured ) );
                $origins    = array_unique( $origins );

                /**
                 * Filter the list of CORS origins that may access the REST API.
                 *
                 * @since 1.0.3
                 *
                 * @param array $origins The allowed origins.
                 */
                return apply_filters( 'cwm_allowed_cors_origins', $origins );
        }

        /**
         * Normalize a potential origin string.
         *
         * @param string $origin Possible origin value.
         * @return string|null
         */
        private function normalize_origin( $origin ) {
                if ( ! $origin || ! is_string( $origin ) ) {
                        return null;
                }

                $parsed = wp_parse_url( trim( $origin ) );
                if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
                        return null;
                }

                $port = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';

                return strtolower( $parsed['scheme'] . '://' . $parsed['host'] . $port );
        }

        /**
         * Output the headers shared by both preflight and regular REST responses.
         *
         * @param string $origin Origin to echo in the header values.
         */
        private function send_cors_headers( $origin ) {
                header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
                header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Credentials: true' );
                header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
                header( 'Vary: Origin' );
        }
}

<?php

namespace CWM\API;

use CWM\Category_Manager;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST controller to manage company category caps.
 */
class Company_Category_Cap_Controller extends WP_REST_Controller {
    /**
     * Category manager instance.
     *
     * @var Category_Manager
     */
    protected $category_manager;

    /**
     * Constructor.
     */
    public function __construct( ?Category_Manager $category_manager = null ) {
        $this->category_manager = $category_manager ?: new Category_Manager();
        $this->namespace        = 'cwm/v1';
        $this->rest_base        = 'admin/company-category-caps';
    }

    /**
     * Register routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'company_id' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_company_param' ],
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'company_id' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_company_param' ],
                        ],
                        'caps'       => [
                            'required'          => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<company_id>\d+)/(?P<category_id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );
    }

    /**
     * Ensure the current user can manage caps.
     */
    public function permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return current_user_can( 'manage_options' );
    }

    /**
     * Validate company parameter from request.
     *
     * @param mixed $value Value to validate.
     * @return bool
     */
    public function validate_company_param( $value ) {
        $company_id = absint( $value );

        if ( $company_id <= 0 ) {
            return false;
        }

        $user = get_userdata( $company_id );

        return (bool) $user;
    }

    /**
     * Fetch company caps.
     */
    public function get_items( $request ) {
        $company_id = absint( $request->get_param( 'company_id' ) );

        $company_validation = $this->validate_company( $company_id );
        if ( is_wp_error( $company_validation ) ) {
            return $company_validation;
        }

        return rest_ensure_response(
            [
                'company_id' => $company_id,
                'caps'       => $this->category_manager->get_company_category_caps( $company_id ),
            ]
        );
    }

    /**
     * Create or update company caps.
     */
    public function create_item( $request ) {
        $company_id = absint( $request->get_param( 'company_id' ) );

        $company_validation = $this->validate_company( $company_id );
        if ( is_wp_error( $company_validation ) ) {
            return $company_validation;
        }

        $caps = $request->get_param( 'caps' );
        if ( ! is_array( $caps ) ) {
            return new WP_Error( 'cwm_invalid_caps', __( 'Caps payload must be an array.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $normalized = [];
        foreach ( $caps as $key => $value ) {
            if ( is_array( $value ) ) {
                $category_id = isset( $value['category_id'] ) ? absint( $value['category_id'] ) : absint( $key );
                $amount      = isset( $value['cap'] ) ? $value['cap'] : ( $value['amount'] ?? null );
            } else {
                $category_id = absint( $key );
                $amount      = $value;
            }

            if ( $category_id <= 0 ) {
                continue;
            }

            $normalized[] = [
                'category_id' => $category_id,
                'cap'         => is_numeric( $amount ) ? (float) $amount : 0,
            ];
        }

        $this->category_manager->sync_company_category_caps( $company_id, $normalized );

        return rest_ensure_response(
            [
                'company_id' => $company_id,
                'caps'       => $this->category_manager->get_company_category_caps( $company_id ),
            ]
        );
    }

    /**
     * Delete a single cap entry.
     */
    public function delete_item( $request ) {
        $company_id  = absint( $request['company_id'] );
        $category_id = absint( $request['category_id'] );

        $company_validation = $this->validate_company( $company_id );
        if ( is_wp_error( $company_validation ) ) {
            return $company_validation;
        }

        $this->category_manager->delete_company_category_cap( $company_id, $category_id );

        return rest_ensure_response(
            [
                'deleted'     => true,
                'company_id'  => $company_id,
                'category_id' => $category_id,
            ]
        );
    }

    /**
     * Ensure a company exists.
     *
     * @param int $company_id Company identifier.
     * @return true|WP_Error
     */
    protected function validate_company( $company_id ) {
        if ( $company_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_company', __( 'Invalid company identifier.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $user = get_userdata( $company_id );
        if ( ! $user ) {
            return new WP_Error( 'cwm_company_not_found', __( 'Company not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return true;
    }
}

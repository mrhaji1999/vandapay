<?php

namespace CWM\API;

use CWM\Category_Manager;
use CWM\Wallet_System;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class CWM_Employee_Controller extends WP_REST_Controller {
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

    public function __construct( Category_Manager $category_manager = null, Wallet_System $wallet_system = null ) {
        $this->category_manager = $category_manager ?: new Category_Manager();
        $this->wallet_system    = $wallet_system ?: new Wallet_System();

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes handled by this controller.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/employee/wallet-summary',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_wallet_summary' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );
    }

    /**
     * Ensure the current user can access employee endpoints.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return true|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        unset( $request );

        $user = wp_get_current_user();
        if ( ! $user || 0 === $user->ID ) {
            return new WP_Error( 'cwm_not_authenticated', __( 'برای دسترسی ابتدا وارد شوید.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }

        if ( ! in_array( 'employee', (array) $user->roles, true ) ) {
            return new WP_Error( 'cwm_forbidden', __( 'شما اجازه دسترسی به این بخش را ندارید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
        }

        return true;
    }

    /**
     * Return the wallet balance and per-category limits for the authenticated employee.
     *
     * @param WP_REST_Request $request Request instance (unused).
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function get_wallet_summary( WP_REST_Request $request ) {
        unset( $request );

        $employee_id = get_current_user_id();
        if ( ! $employee_id ) {
            return new WP_Error( 'cwm_not_authenticated', __( 'برای دسترسی ابتدا وارد شوید.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }

        $balance = $this->wallet_system->get_balance( $employee_id );
        $limits  = $this->category_manager->get_employee_limits( $employee_id );

        return rest_ensure_response(
            [
                'wallet_balance' => $balance,
                'categories'     => $limits,
            ]
        );
    }
}

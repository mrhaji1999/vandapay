<?php

namespace CWM\API;

use CWM\Category_Manager;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Manage REST endpoints for categories.
 */
class Category_Controller extends WP_REST_Controller {
    /**
     * Category manager instance.
     *
     * @var Category_Manager
     */
    protected $category_manager;

    /**
     * Category_Controller constructor.
     */
    public function __construct( ?Category_Manager $category_manager = null ) {
        $this->category_manager = $category_manager ?: new Category_Manager();
        $this->namespace        = 'cwm/v1';
        $this->rest_base        = 'admin/categories';
    }

    /**
     * Register category routes.
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
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'name' => [
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'name' => [
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );
    }

    /**
     * Check admin permissions.
     */
    public function permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return current_user_can( 'manage_options' );
    }

    /**
     * List all categories.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items( $request ) {
        return rest_ensure_response(
            [
                'categories' => $this->category_manager->get_all_categories(),
            ]
        );
    }

    /**
     * Create a category.
     */
    public function create_item( $request ) {
        $name = $request->get_param( 'name' );

        $created = $this->category_manager->create_category( $name );
        if ( is_wp_error( $created ) ) {
            return $created;
        }

        $category = $this->category_manager->get_category( $created );

        return rest_ensure_response( $category );
    }

    /**
     * Retrieve a single category.
     */
    public function get_item( $request ) {
        $category = $this->category_manager->get_category( (int) $request['id'] );

        if ( ! $category ) {
            return new WP_Error( 'cwm_category_not_found', __( 'Category not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return rest_ensure_response( $category );
    }

    /**
     * Update a category.
     */
    public function update_item( $request ) {
        $category_id = (int) $request['id'];
        $name        = $request->get_param( 'name' );

        $updated = $this->category_manager->update_category( $category_id, $name );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $category = $this->category_manager->get_category( $category_id );

        return rest_ensure_response( $category );
    }

    /**
     * Delete a category.
     */
    public function delete_item( $request ) {
        $category_id = (int) $request['id'];

        $deleted = $this->category_manager->delete_category( $category_id );
        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }

        return rest_ensure_response(
            [
                'deleted' => true,
                'id'      => $category_id,
            ]
        );
    }
}

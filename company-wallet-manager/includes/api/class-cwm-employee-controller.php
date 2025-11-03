<?php
/**
 * REST controller to manage employee category limits via WordPress dashboard.
 *
 * @package Company_Wallet_Manager
 */

namespace CWM\API;

use CWM\Category_Manager;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class CWM_Employee_Controller extends WP_REST_Controller {
    /**
     * Category manager instance.
     *
     * @var Category_Manager
     */
    protected $category_manager;

    /**
     * Constructor.
     *
     * @param Category_Manager|null $category_manager Optional dependency injection for testing.
     */
    public function __construct( ?Category_Manager $category_manager = null ) {
        $this->namespace        = 'cwm/v1';
        $this->rest_base        = 'admin/employees';
        $this->category_manager = $category_manager ?: new Category_Manager();

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes handled by the controller.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/admin/categories',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'list_categories' ],
                    'permission_callback' => [ $this, 'permissions_check_categories' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<employee_id>\d+)/limits',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_employee_limits' ],
                    'permission_callback' => [ $this, 'permissions_check_employee' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'update_employee_limits' ],
                    'permission_callback' => [ $this, 'permissions_check_employee' ],
                    'args'                => [
                        'limits' => [
                            'description' => __( 'مقادیر سقف دسته‌بندی‌ها.', 'company-wallet-manager' ),
                            'type'        => 'array',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Permission check for category listing.
     */
    public function permissions_check_categories( WP_REST_Request $request ) {
        unset( $request );

        $permission = $this->ensure_permission();

        return $permission instanceof WP_Error ? $permission : true;
    }

    /**
     * Permission check for employee-specific routes.
     */
    public function permissions_check_employee( WP_REST_Request $request ) {
        $employee_id = (int) $request['employee_id'];
        $permission  = $this->ensure_permission( $employee_id );

        return $permission instanceof WP_Error ? $permission : true;
    }

    /**
     * List available categories for dashboard usage.
     */
    public function list_categories( WP_REST_Request $request ) {
        unset( $request );

        $categories = $this->category_manager->get_all_categories();
        $formatted  = array_map(
            static function ( $category ) {
                return [
                    'id'   => isset( $category['id'] ) ? (int) $category['id'] : 0,
                    'name' => isset( $category['name'] ) ? (string) $category['name'] : '',
                    'slug' => isset( $category['slug'] ) ? (string) $category['slug'] : '',
                ];
            },
            $categories
        );

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => $formatted,
            ]
        );
    }

    /**
     * Retrieve category limits for a specific employee.
     */
    public function get_employee_limits( WP_REST_Request $request ) {
        $employee_id = (int) $request['employee_id'];
        if ( $employee_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_employee', __( 'شناسه کارمند معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $permission = $this->ensure_permission( $employee_id );
        if ( $permission instanceof WP_Error ) {
            return $permission;
        }

        $company_id = $this->resolve_employee_company_context( $employee_id );
        if ( $company_id instanceof WP_Error ) {
            return $company_id;
        }

        $all_categories = $this->category_manager->get_all_categories();
        $limits         = $this->category_manager->get_employee_limits( $employee_id );

        $indexed_limits = [];
        foreach ( $limits as $limit ) {
            $indexed_limits[ $limit['category_id'] ] = $limit;
        }

        $categories = [];
        foreach ( $all_categories as $category ) {
            $category_id = isset( $category['id'] ) ? (int) $category['id'] : 0;
            if ( $category_id <= 0 ) {
                continue;
            }

            $entry     = isset( $indexed_limits[ $category_id ] ) ? $indexed_limits[ $category_id ] : null;
            $limit     = $entry ? (float) $entry['limit'] : 0.0;
            $spent     = $entry ? (float) $entry['spent'] : 0.0;
            $remaining = $entry ? (float) $entry['remaining'] : max( 0.0, $limit - $spent );

            if ( ! $entry ) {
                $remaining = max( 0.0, $limit );
            }

            $categories[] = [
                'category_id'   => $category_id,
                'category_name' => isset( $category['name'] ) ? (string) $category['name'] : '',
                'limit'         => $limit,
                'spent'         => $spent,
                'remaining'     => $remaining,
            ];
        }

        return rest_ensure_response(
            [
                'status' => 'success',
                'data'   => [
                    'employee_id' => $employee_id,
                    'company_id'  => $company_id,
                    'categories'  => $categories,
                ],
            ]
        );
    }

    /**
     * Update employee category limits.
     */
    public function update_employee_limits( WP_REST_Request $request ) {
        $employee_id = (int) $request['employee_id'];
        if ( $employee_id <= 0 ) {
            return new WP_Error( 'cwm_invalid_employee', __( 'شناسه کارمند معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $permission = $this->ensure_permission( $employee_id );
        if ( $permission instanceof WP_Error ) {
            return $permission;
        }

        $company_id = $this->resolve_employee_company_context( $employee_id );
        if ( $company_id instanceof WP_Error ) {
            return $company_id;
        }

        $limits_payload = $request->get_param( 'limits' );
        if ( null === $limits_payload ) {
            $json_params    = $request->get_json_params();
            $limits_payload = isset( $json_params['limits'] ) ? $json_params['limits'] : $json_params;
        }

        if ( ! is_array( $limits_payload ) ) {
            return new WP_Error( 'cwm_invalid_limits', __( 'ساختار داده ارسالی معتبر نیست.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $sanitized = [];
        foreach ( $limits_payload as $limit ) {
            if ( ! is_array( $limit ) ) {
                continue;
            }

            $category_id = isset( $limit['category_id'] ) ? absint( $limit['category_id'] ) : 0;
            if ( $category_id <= 0 ) {
                continue;
            }

            $amount = isset( $limit['limit'] ) ? (float) $limit['limit'] : 0.0;
            if ( $amount < 0 ) {
                $amount = 0.0;
            }

            $sanitized[] = [
                'category_id' => $category_id,
                'limit'       => $amount,
            ];
        }

        $this->category_manager->set_employee_limits( $employee_id, $sanitized, $company_id );

        return $this->get_employee_limits( $request );
    }

    /**
     * Ensure the current user may manage employee limits.
     *
     * @param int $employee_id Optional employee identifier.
     * @return true|WP_Error
     */
    protected function ensure_permission( $employee_id = 0 ) {
        $user = wp_get_current_user();

        if ( ! $user || 0 === $user->ID ) {
            return new WP_Error( 'rest_forbidden', __( 'برای دسترسی به این بخش ابتدا وارد شوید.', 'company-wallet-manager' ), [ 'status' => 401 ] );
        }

        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_wallets' ) || user_can( $user, 'edit_users' ) ) {
            return true;
        }

        if ( $this->user_has_role( $user, 'company' ) ) {
            if ( $employee_id <= 0 ) {
                return true;
            }

            $company_id = $this->resolve_employee_company_context( $employee_id );
            if ( $company_id instanceof WP_Error ) {
                return $company_id;
            }

            $current_company_id = $this->get_company_post_id_for_user( $user->ID );
            if ( ! $current_company_id ) {
                return new WP_Error( 'cwm_company_profile_missing', __( 'اطلاعات شرکت شما کامل نشده است.', 'company-wallet-manager' ), [ 'status' => 403 ] );
            }

            if ( (int) $current_company_id !== (int) $company_id ) {
                return new WP_Error( 'cwm_forbidden_employee', __( 'شما دسترسی به مدیریت این کارمند ندارید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
            }

            return true;
        }

        return new WP_Error( 'cwm_forbidden', __( 'برای انجام این عملیات دسترسی لازم را ندارید.', 'company-wallet-manager' ), [ 'status' => 403 ] );
    }

    /**
     * Resolve company context for an employee.
     *
     * @param int $employee_id Employee identifier.
     * @return int|WP_Error
     */
    protected function resolve_employee_company_context( $employee_id ) {
        $company_id = (int) get_user_meta( $employee_id, '_cwm_company_id', true );

        if ( $company_id <= 0 ) {
            return new WP_Error( 'cwm_employee_company_missing', __( 'این کارمند به هیچ شرکتی متصل نیست.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        return $company_id;
    }

    /**
     * Retrieve company post ID linked to the authenticated user.
     *
     * @param int $user_id User identifier.
     * @return int
     */
    protected function get_company_post_id_for_user( $user_id ) {
        $posts = get_posts(
            [
                'post_type'      => 'cwm_company',
                'post_status'    => 'any',
                'meta_key'       => '_cwm_company_user_id',
                'meta_value'     => $user_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]
        );

        return $posts ? (int) $posts[0] : 0;
    }

    /**
     * Determine if a user has a specific role.
     *
     * @param \WP_User $user WordPress user object.
     * @param string    $role Role slug to check.
     * @return bool
     */
    protected function user_has_role( $user, $role ) {
        if ( ! $user ) {
            return false;
        }

        return in_array( $role, (array) $user->roles, true );
    }
}

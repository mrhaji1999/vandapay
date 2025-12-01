<?php

namespace CWM;

/**
 * Register custom post types used by the plugin.
 */
class Post_Types_Manager {

        /**
         * Bootstraps hooks.
         */
        public function __construct() {
                add_action( 'init', [ $this, 'register_post_types' ] );
        }

        /**
         * Register the custom post types required by the wallet system.
         */
        public function register_post_types() {
                $this->register_company_post_type();
                $this->register_payout_post_type();
                $this->register_store_post_type();
                $this->register_product_post_type();
        }

        /**
         * Registers the Company post type used to store onboarding metadata.
         */
        private function register_company_post_type() {
                $labels = [
                        'name'               => __( 'Companies', 'company-wallet-manager' ),
                        'singular_name'      => __( 'Company', 'company-wallet-manager' ),
                        'menu_name'          => __( 'Companies', 'company-wallet-manager' ),
                        'add_new'            => __( 'Add Company', 'company-wallet-manager' ),
                        'add_new_item'       => __( 'Add New Company', 'company-wallet-manager' ),
                        'edit_item'          => __( 'Edit Company', 'company-wallet-manager' ),
                        'new_item'           => __( 'New Company', 'company-wallet-manager' ),
                        'view_item'          => __( 'View Company', 'company-wallet-manager' ),
                        'search_items'       => __( 'Search Companies', 'company-wallet-manager' ),
                        'not_found'          => __( 'No companies found', 'company-wallet-manager' ),
                        'not_found_in_trash' => __( 'No companies found in Trash', 'company-wallet-manager' ),
                ];

                register_post_type(
                        'cwm_company',
                        [
                                'labels'              => $labels,
                                'public'              => false,
                                'show_ui'             => true,
                                'show_in_menu'        => true,
                                'menu_icon'           => 'dashicons-building',
                                'supports'            => [ 'title' ],
                                'capability_type'     => 'post',
                                'map_meta_cap'        => true,
                                'rewrite'             => false,
                                'show_in_rest'        => false,
                        ]
                );
        }

        /**
         * Registers the payout request mirror CPT used for back-office processing.
         */
        private function register_payout_post_type() {
                $labels = [
                        'name'               => __( 'Payout Requests', 'company-wallet-manager' ),
                        'singular_name'      => __( 'Payout Request', 'company-wallet-manager' ),
                        'menu_name'          => __( 'Payout Requests', 'company-wallet-manager' ),
                        'add_new_item'       => __( 'Add New Payout Request', 'company-wallet-manager' ),
                        'edit_item'          => __( 'Review Payout Request', 'company-wallet-manager' ),
                        'view_item'          => __( 'View Payout Request', 'company-wallet-manager' ),
                        'search_items'       => __( 'Search Payout Requests', 'company-wallet-manager' ),
                        'not_found'          => __( 'No payout requests found', 'company-wallet-manager' ),
                        'not_found_in_trash' => __( 'No payout requests found in Trash', 'company-wallet-manager' ),
                ];

                register_post_type(
                        'cwm_payout',
                        [
                                'labels'              => $labels,
                                'public'              => false,
                                'show_ui'             => true,
                        'show_in_menu'        => true,
                                'menu_icon'           => 'dashicons-money',
                                'supports'            => [ 'title', 'editor' ],
                                'capability_type'     => 'post',
                                'map_meta_cap'        => true,
                                'rewrite'             => false,
                                'show_in_rest'        => false,
                        ]
                );
        }

        /**
         * Registers the Store post type for merchant store information.
         */
        private function register_store_post_type() {
                $labels = [
                        'name'               => __( 'Stores', 'company-wallet-manager' ),
                        'singular_name'      => __( 'Store', 'company-wallet-manager' ),
                        'menu_name'          => __( 'Stores', 'company-wallet-manager' ),
                        'add_new'            => __( 'Add Store', 'company-wallet-manager' ),
                        'add_new_item'       => __( 'Add New Store', 'company-wallet-manager' ),
                        'edit_item'          => __( 'Edit Store', 'company-wallet-manager' ),
                        'new_item'           => __( 'New Store', 'company-wallet-manager' ),
                        'view_item'          => __( 'View Store', 'company-wallet-manager' ),
                        'search_items'       => __( 'Search Stores', 'company-wallet-manager' ),
                        'not_found'          => __( 'No stores found', 'company-wallet-manager' ),
                        'not_found_in_trash' => __( 'No stores found in Trash', 'company-wallet-manager' ),
                ];

                register_post_type(
                        'cwm_store',
                        [
                                'labels'              => $labels,
                                'public'              => false,
                                'show_ui'             => true,
                                'show_in_menu'        => true,
                                'menu_icon'           => 'dashicons-store',
                                'supports'            => [ 'title', 'editor', 'thumbnail' ],
                                'capability_type'     => 'post',
                                'map_meta_cap'        => true,
                                'rewrite'             => false,
                                'show_in_rest'        => true,
                        ]
                );
        }

        /**
         * Registers the Product post type for store products.
         */
        private function register_product_post_type() {
                $labels = [
                        'name'               => __( 'Products', 'company-wallet-manager' ),
                        'singular_name'      => __( 'Product', 'company-wallet-manager' ),
                        'menu_name'          => __( 'Products', 'company-wallet-manager' ),
                        'add_new'            => __( 'Add Product', 'company-wallet-manager' ),
                        'add_new_item'       => __( 'Add New Product', 'company-wallet-manager' ),
                        'edit_item'          => __( 'Edit Product', 'company-wallet-manager' ),
                        'new_item'           => __( 'New Product', 'company-wallet-manager' ),
                        'view_item'          => __( 'View Product', 'company-wallet-manager' ),
                        'search_items'       => __( 'Search Products', 'company-wallet-manager' ),
                        'not_found'          => __( 'No products found', 'company-wallet-manager' ),
                        'not_found_in_trash' => __( 'No products found in Trash', 'company-wallet-manager' ),
                ];

                register_post_type(
                        'cwm_product',
                        [
                                'labels'              => $labels,
                                'public'              => false,
                                'show_ui'             => true,
                                'show_in_menu'        => true,
                                'menu_icon'           => 'dashicons-cart',
                                'supports'            => [ 'title', 'editor', 'thumbnail' ],
                                'capability_type'     => 'post',
                                'map_meta_cap'        => true,
                                'rewrite'             => false,
                                'show_in_rest'        => true,
                        ]
                );
        }
}


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
}


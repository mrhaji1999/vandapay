<?php

namespace CWM;

/**
 * Class Settings_Page
 *
 * @package CWM
 */
class Settings_Page {

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        // This page will be under "Settings"
        add_options_page(
            'Company Wallet Manager Settings',
            'Company Wallet Manager',
            'manage_options',
            'cwm-settings-admin',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $selected_company_id = $this->handle_admin_actions();

        $this->options = get_option( 'cwm_settings' );

        $category_manager = new Category_Manager();
        $categories       = $category_manager->get_all_categories();
        $companies        = get_users(
            [
                'role'    => 'company',
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'number'  => -1,
            ]
        );

        if ( $selected_company_id <= 0 && isset( $_GET['cwm_company_id'] ) ) {
            $selected_company_id = absint( $_GET['cwm_company_id'] );
        }

        $company_caps      = $selected_company_id > 0 ? $category_manager->get_company_category_caps( $selected_company_id ) : [];
        $company_caps_map  = [];

        foreach ( $company_caps as $cap ) {
            if ( null === $cap['cap'] ) {
                continue;
            }

            $company_caps_map[ $cap['category_id'] ] = (float) $cap['cap'];
        }

        $options = $this->options;

        include CWM_PLUGIN_DIR . 'templates/admin/category-management.php';
    }

    /**
     * Process category management submissions.
     *
     * @return int Selected company identifier from submission.
     */
    protected function handle_admin_actions() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            return 0;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return 0;
        }

        $selected_company_id = 0;
        $category_manager    = new Category_Manager();

        $manage_categories_nonce = isset( $_POST['cwm_manage_categories_nonce'] ) ? wp_unslash( $_POST['cwm_manage_categories_nonce'] ) : '';

        if ( $manage_categories_nonce && wp_verify_nonce( $manage_categories_nonce, 'cwm_manage_categories' ) ) {
            $action = isset( $_POST['cwm_manage_categories_action'] ) ? sanitize_text_field( wp_unslash( $_POST['cwm_manage_categories_action'] ) ) : '';

            switch ( $action ) {
                case 'create':
                    $name   = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
                    $result = $category_manager->create_category( $name );
                    $this->handle_category_result( $result, __( 'Category created successfully.', 'company-wallet-manager' ) );
                    break;
                case 'update':
                    $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
                    $name        = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
                    $result      = $category_manager->update_category( $category_id, $name );
                    $this->handle_category_result( $result, __( 'Category updated successfully.', 'company-wallet-manager' ) );
                    break;
                case 'delete':
                    $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
                    $result      = $category_manager->delete_category( $category_id );
                    $this->handle_category_result( $result, __( 'Category removed successfully.', 'company-wallet-manager' ) );
                    break;
            }
        }

        $assign_caps_nonce = isset( $_POST['cwm_assign_caps_nonce'] ) ? wp_unslash( $_POST['cwm_assign_caps_nonce'] ) : '';

        if ( $assign_caps_nonce && wp_verify_nonce( $assign_caps_nonce, 'cwm_assign_caps' ) ) {
            $selected_company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;

            if ( $selected_company_id <= 0 ) {
                add_settings_error( 'cwm_category_manager', 'cwm_invalid_company', __( 'Please choose a company before saving caps.', 'company-wallet-manager' ), 'error' );
            } else {
                $caps_input = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : [];
                $caps       = [];

                foreach ( $caps_input as $category_id => $amount ) {
                    $category_id = absint( $category_id );
                    if ( $category_id <= 0 ) {
                        continue;
                    }

                    $caps[] = [
                        'category_id' => $category_id,
                        'cap'         => is_numeric( $amount ) ? (float) $amount : 0,
                    ];
                }

                $category_manager->sync_company_category_caps( $selected_company_id, $caps );

                add_settings_error( 'cwm_category_manager', 'cwm_caps_saved', __( 'Company category caps saved.', 'company-wallet-manager' ), 'updated' );
            }
        }

        return $selected_company_id;
    }

    /**
     * Normalize category manager responses into admin notices.
     *
     * @param mixed  $result  Result from the manager.
     * @param string $message Success message.
     */
    protected function handle_category_result( $result, $message ) {
        if ( is_wp_error( $result ) ) {
            add_settings_error( 'cwm_category_manager', $result->get_error_code(), $result->get_error_message(), 'error' );
            return;
        }

        add_settings_error( 'cwm_category_manager', 'cwm_category_success', $message, 'updated' );
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            'cwm_option_group', // Option group
            'cwm_settings', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'cwm_setting_section_id', // ID
            'API Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'cwm-settings-admin' // Page
        );

        add_settings_field(
            'sms_username',
            'SMS Gateway Username',
            array( $this, 'sms_username_callback' ),
            'cwm-settings-admin',
            'cwm_setting_section_id'
        );

        add_settings_field(
            'sms_password',
            'SMS Gateway Password',
            array( $this, 'sms_password_callback' ),
            'cwm-settings-admin',
            'cwm_setting_section_id'
        );

        add_settings_field(
            'sms_body_id',
            'SMS Body ID (Template Code)',
            array( $this, 'sms_body_id_callback' ),
            'cwm-settings-admin',
            'cwm_setting_section_id'
        );

        add_settings_field(
            'allowed_origins',
            __( 'Allowed CORS Origins', 'company-wallet-manager' ),
            array( $this, 'allowed_origins_callback' ),
            'cwm-settings-admin',
            'cwm_setting_section_id'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['sms_username'] ) )
            $new_input['sms_username'] = sanitize_text_field( $input['sms_username'] );

        if( isset( $input['sms_password'] ) )
            $new_input['sms_password'] = sanitize_text_field( $input['sms_password'] );

        if( isset( $input['sms_body_id'] ) )
            $new_input['sms_body_id'] = absint( $input['sms_body_id'] );

        if ( isset( $input['allowed_origins'] ) )
            $new_input['allowed_origins'] = sanitize_textarea_field( $input['allowed_origins'] );

        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info() {
        print 'Enter your SMS gateway settings below:';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function sms_username_callback() {
        printf(
            '<input type="text" id="sms_username" name="cwm_settings[sms_username]" value="%s" />',
            isset( $this->options['sms_username'] ) ? esc_attr( $this->options['sms_username']) : ''
        );
    }

    public function sms_password_callback() {
        printf(
            '<input type="password" id="sms_password" name="cwm_settings[sms_password]" value="%s" />',
            isset( $this->options['sms_password'] ) ? esc_attr( $this->options['sms_password']) : ''
        );
    }

    public function sms_body_id_callback() {
        printf(
            '<input type="text" id="sms_body_id" name="cwm_settings[sms_body_id]" value="%s" />',
            isset( $this->options['sms_body_id'] ) ? esc_attr( $this->options['sms_body_id']) : ''
        );
    }

    public function allowed_origins_callback() {
        printf(
            '<textarea id="allowed_origins" name="cwm_settings[allowed_origins]" rows="4" cols="50" placeholder="https://app.example.com">%s</textarea><p class="description">%s</p>',
            isset( $this->options['allowed_origins'] ) ? esc_textarea( $this->options['allowed_origins'] ) : '',
            esc_html__( 'Comma separated list of fully-qualified origins allowed to call the REST API.', 'company-wallet-manager' )
        );
    }
}

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
        // Set class property
        $this->options = get_option( 'cwm_settings' );
        ?>
        <div class="wrap">
            <h1>Company Wallet Manager Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'cwm_option_group' );
                do_settings_sections( 'cwm-settings-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
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
}

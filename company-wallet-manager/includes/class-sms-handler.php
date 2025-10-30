<?php

namespace CWM;

/**
 * Class SMS_Handler
 *
 * @package CWM
 */
class SMS_Handler {

    /**
     * SMS Gateway settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings = get_option( 'cwm_settings' );
    }

    /**
     * Send an OTP code.
     *
     * @param string $to The recipient's phone number.
     * @param string $otp The OTP code.
     *
     * @return bool True on success, false on failure.
     */
    public function send_otp( $to, $otp ) {
        if ( empty( $to ) || empty( $otp ) ) {
            return false;
        }

        $username = isset( $this->settings['sms_username'] ) ? $this->settings['sms_username'] : '';
        $password = isset( $this->settings['sms_password'] ) ? $this->settings['sms_password'] : '';
        $bodyId   = isset( $this->settings['sms_body_id'] ) ? $this->settings['sms_body_id'] : '';

        if ( empty( $username ) || empty( $password ) || empty( $bodyId ) ) {
            // Log the error.
            $log_file = CWM_PLUGIN_DIR . 'logs/sms_error_log.txt';
            $log_message = sprintf(
                "[%s] SMS settings are not configured.\n",
                date( 'Y-m-d H:i:s' )
            );
            file_put_contents( $log_file, $log_message, FILE_APPEND );
            return false;
        }

        try {
            ini_set("soap.wsdl_cache_enabled","0");
            $sms = new \SoapClient("http://api.payamak-panel.com/post/Send.asmx?wsdl",array("encoding"=>"UTF-8"));
            $data = array(
                "username" => $username,
                "password" => $password,
                "text"     => array( $otp ),
                "to"       => $to,
                "bodyId"   => $bodyId,
            );
            $send_Result = $sms->SendByBaseNumber($data)->SendByBaseNumberResult;

            // Check if the result is a recId (a long number)
            if ( is_numeric($send_Result) && strlen($send_Result) > 10 ) {
                return true;
            } else {
                // Log the error.
                $log_file = CWM_PLUGIN_DIR . 'logs/sms_error_log.txt';
                $log_message = sprintf(
                    "[%s] SMS sending failed for %s. Error code: %s\n",
                    date( 'Y-m-d H:i:s' ),
                    $to,
                    $send_Result
                );
                file_put_contents( $log_file, $log_message, FILE_APPEND );
                return false;
            }
        } catch ( \Exception $e ) {
            // Log the exception.
            $log_file = CWM_PLUGIN_DIR . 'logs/sms_error_log.txt';
            $log_message = sprintf(
                "[%s] SMS sending failed for %s. Exception: %s\n",
                date( 'Y-m-d H:i:s' ),
                $to,
                $e->getMessage()
            );
            file_put_contents( $log_file, $log_message, FILE_APPEND );
            return false;
        }
    }
}

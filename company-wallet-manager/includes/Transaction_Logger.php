<?php

namespace CWM;

/**
 * Class Transaction_Logger
 *
 * @package CWM
 */
class Transaction_Logger {

    /**
     * Log a transaction.
     *
     * @param string $type The type of transaction (e.g., 'charge', 'transfer', 'payout').
     * @param int    $sender_id The ID of the sender.
     * @param int    $receiver_id The ID of the receiver.
     * @param float  $amount The amount of the transaction.
     * @param string $status The status of the transaction (e.g., 'completed', 'pending').
     */
    public function log( $type, $sender_id, $receiver_id, $amount, $status, array $args = array() ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cwm_transactions';
        $metadata   = isset( $args['metadata'] ) ? wp_json_encode( $args['metadata'] ) : null;
        $wpdb->insert(
            $table_name,
            array(
                'type'        => $type,
                'sender_id'   => $sender_id,
                'receiver_id' => $receiver_id,
                'amount'      => $amount,
                'status'      => $status,
                'context'     => isset( $args['context'] ) ? $args['context'] : null,
                'related_request' => isset( $args['related_request'] ) ? $args['related_request'] : null,
                'metadata'    => $metadata,
            )
        );
    }
}

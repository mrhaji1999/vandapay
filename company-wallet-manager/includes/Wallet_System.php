<?php

namespace CWM;

/**
 * Class Wallet_System
 *
 * @package CWM
 */
class Wallet_System {

    /**
     * Get the balance of a user.
     *
     * @param int $user_id The ID of the user.
     * @return float The balance of the user.
     */
    public function get_balance( $user_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cwm_wallets';
        $balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT balance FROM $table_name WHERE user_id = %d",
            $user_id
        ) );

        if ( is_null( $balance ) ) {
            // If the user doesn't have a wallet, create one.
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'balance' => 0,
                )
            );
            return 0.0;
        }

        return (float) $balance;
    }

    /**
     * Update the balance of a user.
     *
     * @param int $user_id The ID of the user.
     * @param float $amount The amount to add or subtract.
     * @return bool True on success, false on failure.
     */
    public function update_balance( $user_id, $amount ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cwm_wallets';

        // Check if the user has a wallet.
        $this->get_balance( $user_id );

        if ( $amount < 0 ) {
            $affected = $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name SET balance = balance + %f, updated_at = CURRENT_TIMESTAMP WHERE user_id = %d AND balance >= %f",
                $amount,
                $user_id,
                abs( $amount )
            ) );
        } else {
            $affected = $wpdb->query( $wpdb->prepare(
                "UPDATE $table_name SET balance = balance + %f, updated_at = CURRENT_TIMESTAMP WHERE user_id = %d",
                $amount,
                $user_id
            ) );
        }

        return false !== $affected && 0 !== $affected;
    }

    /**
     * Transfer funds from one user to another.
     *
     * @param int $sender_id The ID of the sender.
     * @param int $receiver_id The ID of the receiver.
     * @param float $amount The amount to transfer.
     * @return bool True on success, false on failure.
     */
    public function transfer( $sender_id, $receiver_id, $amount ) {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $sender_balance_updated = $this->update_balance( $sender_id, -$amount );
        if ( ! $sender_balance_updated ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $receiver_balance_updated = $this->update_balance( $receiver_id, $amount );
        if ( ! $receiver_balance_updated ) {
            $wpdb->query( 'ROLLBACK' );
            $this->update_balance( $sender_id, $amount );
            return false;
        }

        $wpdb->query( 'COMMIT' );

        return true;
    }
}

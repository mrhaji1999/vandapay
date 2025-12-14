<?php

namespace CWM;

/**
 * Handle CSV import for employee credit allocation.
 */
class Company_CSV_Handler {

    /**
     * Process employee credit allocation from CSV file.
     *
     * @param array  $file File upload array.
     * @param int    $company_user_id Company user ID.
     * @param string $bulk_amount Optional bulk amount to add to all employees.
     * @return array|WP_Error
     */
    public function process_employee_credit_allocation( $file, $company_user_id, $bulk_amount = '' ) {
        global $wpdb;

        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new \WP_Error( 'cwm_invalid_file', __( 'فایل نامعتبر است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( false === $handle ) {
            return new \WP_Error( 'cwm_file_read_error', __( 'امکان خواندن فایل وجود ندارد.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        // Get company email
        $company_user = get_userdata( $company_user_id );
        if ( ! $company_user ) {
            fclose( $handle );
            return new \WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $company_email = $company_user->user_email;
        $wallet_system = new Wallet_System();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        // Read header
        $header = fgetcsv( $handle );
        if ( false === $header ) {
            fclose( $handle );
            return new \WP_Error( 'cwm_empty_file', __( 'فایل خالی است.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        // Normalize header
        $header = array_map( 'strtolower', array_map( 'trim', $header ) );
        $header_map = [];
        foreach ( $header as $index => $col ) {
            $header_map[ $col ] = $index;
        }

        // Required columns - national_id is always required, amount is required only if bulk_amount is not provided
        $required = [ 'national_id' ];
        if ( empty( $bulk_amount ) ) {
            $required[] = 'amount';
        }
        foreach ( $required as $req ) {
            if ( ! isset( $header_map[ $req ] ) ) {
                fclose( $handle );
                return new \WP_Error( 'cwm_missing_column', sprintf( __( 'ستون %s در فایل وجود ندارد.', 'company-wallet-manager' ), $req ), [ 'status' => 400 ] );
            }
        }

        $row_num = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_num++;
            $processed++;

            if ( count( $row ) < count( $header ) ) {
                $errors[] = [
                    'row'     => $row_num,
                    'message' => __( 'تعداد ستون‌ها نامعتبر است.', 'company-wallet-manager' ),
                ];
                continue;
            }

            $national_id = trim( $row[ $header_map['national_id'] ] ?? '' );
            
            if ( empty( $national_id ) ) {
                $errors[] = [
                    'row'     => $row_num,
                    'message' => __( 'کد ملی خالی است.', 'company-wallet-manager' ),
                ];
                continue;
            }

            // Determine amount: use CSV amount if available, otherwise use bulk amount
            $amount = 0;
            if ( isset( $header_map['amount'] ) && ! empty( $row[ $header_map['amount'] ] ?? '' ) ) {
                $amount = floatval( $row[ $header_map['amount'] ] ?? 0 );
            }
            
            // If amount is 0 or empty, use bulk amount if provided
            if ( $amount <= 0 && ! empty( $bulk_amount ) ) {
                $amount = floatval( $bulk_amount );
            }

            if ( $amount <= 0 ) {
                $errors[] = [
                    'row'     => $row_num,
                    'message' => __( 'مبلغ نامعتبر است. لطفاً مبلغ را در فایل CSV وارد کنید یا مبلغ شارژ عمومی را تنظیم کنید.', 'company-wallet-manager' ),
                ];
                continue;
            }

            // Get company post ID
            $company_posts = get_posts( [
                'post_type'   => 'cwm_company',
                'meta_key'    => '_cwm_company_user_id',
                'meta_value'  => $company_user_id,
                'post_status' => 'any',
                'numberposts' => 1,
            ] );

            if ( empty( $company_posts ) ) {
                fclose( $handle );
                return new \WP_Error( 'cwm_company_not_found', __( 'شرکت یافت نشد.', 'company-wallet-manager' ), [ 'status' => 404 ] );
            }

            $company_id = $company_posts[0]->ID;

            // Find employee by national_id
            $employee = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT u.ID, um.meta_value as employee_company_id
                    FROM {$wpdb->users} u
                    INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'cwm_national_id' AND um1.meta_value = %s
                    LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '_cwm_company_id'
                    LIMIT 1",
                    $national_id
                )
            );

            if ( ! $employee ) {
                $errors[] = [
                    'row'     => $row_num,
                    'message' => sprintf( __( 'کارمند با کد ملی %s یافت نشد.', 'company-wallet-manager' ), $national_id ),
                ];
                continue;
            }

            // Verify employee belongs to company
            $employee_company_id = (int) $employee->employee_company_id;
            if ( $employee_company_id !== $company_id ) {
                $errors[] = [
                    'row'     => $row_num,
                    'message' => sprintf( __( 'کارمند با کد ملی %s به این شرکت تعلق ندارد.', 'company-wallet-manager' ), $national_id ),
                ];
                continue;
            }

            $employee_id = (int) $employee->ID;

            // Check if employee has wallet, create if not
            $current_balance = $wallet_system->get_balance( $employee_id );

            // Add credit
            $success = $wallet_system->update_balance( $employee_id, $amount );
            if ( $success ) {
                if ( $current_balance == 0 ) {
                    $created++;
                } else {
                    $updated++;
                }

                // Log transaction
                $wpdb->insert(
                    $wpdb->prefix . 'cwm_transactions',
                    [
                        'sender_id'   => $company_user_id,
                        'receiver_id' => $employee_id,
                        'type'        => 'credit_allocation',
                        'amount'      => $amount,
                        'status'      => 'completed',
                        'description' => sprintf( __( 'تخصیص اعتبار از طریق فایل اکسل - کد ملی: %s', 'company-wallet-manager' ), $national_id ),
                    ],
                    [ '%d', '%d', '%s', '%f', '%s', '%s' ]
                );
            } else {
                $errors[] = [
                    'row'     => $row_num,
                    'message' => sprintf( __( 'خطا در تخصیص اعتبار به کارمند با کد ملی %s.', 'company-wallet-manager' ), $national_id ),
                ];
            }
        }

        fclose( $handle );

        return [
            'processed'         => $processed,
            'created'           => $created,
            'updated'           => $updated,
            'balances_adjusted' => $processed - count( $errors ),
            'errors'            => $errors,
        ];
    }
}


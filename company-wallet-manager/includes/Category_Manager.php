<?php

namespace CWM;

/**
 * Manage merchant categories and employee category limits.
 */
class Category_Manager {
    /**
     * @var string
     */
    protected $categories_table;

    /**
     * @var string
     */
    protected $merchant_table;

    /**
     * @var string
     */
    protected $limits_table;

    /**
     * Category_Manager constructor.
     */
    public function __construct() {
        global $wpdb;

        $this->categories_table = $wpdb->prefix . 'cwm_categories';
        $this->merchant_table   = $wpdb->prefix . 'cwm_category_merchants';
        $this->limits_table     = $wpdb->prefix . 'cwm_employee_category_limits';
    }

    /**
     * Return all available categories.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_all_categories() {
        global $wpdb;

        $rows = $wpdb->get_results( "SELECT id, name, slug FROM {$this->categories_table} ORDER BY name ASC", ARRAY_A );

        return array_map(
            function( $row ) {
                return [
                    'id'   => (int) $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                ];
            },
            $rows ?: []
        );
    }

    /**
     * Create a new category.
     *
     * @param string $name Category name.
     * @return int|\WP_Error
     */
    public function create_category( $name ) {
        global $wpdb;

        $name = trim( (string) $name );
        if ( '' === $name ) {
            return new \WP_Error( 'cwm_invalid_category', __( 'Category name is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $slug = sanitize_title( $name );
        if ( '' === $slug ) {
            $slug = uniqid( 'category_', true );
        }

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->categories_table} WHERE slug = %s", $slug ) );
        if ( $existing ) {
            return (int) $existing;
        }

        $inserted = $wpdb->insert(
            $this->categories_table,
            [
                'name' => $name,
                'slug' => $slug,
            ]
        );

        if ( false === $inserted ) {
            return new \WP_Error( 'cwm_category_create_failed', __( 'Unable to create category.', 'company-wallet-manager' ), [ 'status' => 500 ] );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Retrieve categories assigned to the merchant.
     *
     * @param int $merchant_id Merchant identifier.
     * @return array<int, array<string, mixed>>
     */
    public function get_merchant_categories( $merchant_id ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT c.id, c.name, c.slug FROM {$this->categories_table} c\n             INNER JOIN {$this->merchant_table} mc ON mc.category_id = c.id\n             WHERE mc.merchant_id = %d\n             ORDER BY c.name ASC",
            $merchant_id
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return array_map(
            function( $row ) {
                return [
                    'id'   => (int) $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                ];
            },
            $rows ?: []
        );
    }

    /**
     * Assign categories to merchant.
     *
     * @param int   $merchant_id Merchant identifier.
     * @param array $category_ids Array of category IDs.
     */
    public function sync_merchant_categories( $merchant_id, array $category_ids ) {
        global $wpdb;

        $category_ids = array_values( array_unique( array_filter( array_map( 'absint', $category_ids ) ) ) );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->merchant_table} WHERE merchant_id = %d", $merchant_id ) );

        if ( empty( $category_ids ) ) {
            return;
        }

        $values = [];
        foreach ( $category_ids as $category_id ) {
            $values[] = $wpdb->prepare( '(%d, %d)', $merchant_id, $category_id );
        }

        if ( ! empty( $values ) ) {
            $sql = "INSERT INTO {$this->merchant_table} (merchant_id, category_id) VALUES " . implode( ',', $values );
            $wpdb->query( $sql );
        }
    }

    /**
     * Fetch limits for a specific employee.
     *
     * @param int $employee_id Employee identifier.
     * @return array<int, array<string, mixed>>
     */
    public function get_employee_limits( $employee_id ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT l.category_id, l.spending_limit, l.spent_amount, c.name FROM {$this->limits_table} l\n             INNER JOIN {$this->categories_table} c ON c.id = l.category_id\n             WHERE l.employee_id = %d ORDER BY c.name ASC",
            $employee_id
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return array_map(
            function( $row ) {
                $limit = isset( $row['spending_limit'] ) ? (float) $row['spending_limit'] : 0.0;
                $spent = isset( $row['spent_amount'] ) ? (float) $row['spent_amount'] : 0.0;

                return [
                    'category_id' => (int) $row['category_id'],
                    'category_name' => $row['name'],
                    'limit' => $limit,
                    'spent' => $spent,
                    'remaining' => max( 0.0, $limit - $spent ),
                ];
            },
            $rows ?: []
        );
    }

    /**
     * Fetch limits for multiple employees.
     *
     * @param int[] $employee_ids Employee identifiers.
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function get_limits_for_employees( array $employee_ids ) {
        global $wpdb;

        $employee_ids = array_values( array_unique( array_filter( array_map( 'absint', $employee_ids ) ) ) );
        if ( empty( $employee_ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) );
        $sql          = $wpdb->prepare(
            "SELECT l.employee_id, l.category_id, l.spending_limit, l.spent_amount, c.name\n             FROM {$this->limits_table} l\n             INNER JOIN {$this->categories_table} c ON c.id = l.category_id\n             WHERE l.employee_id IN ($placeholders)",
            $employee_ids
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        $map  = [];

        foreach ( $rows as $row ) {
            $employee_id = (int) $row['employee_id'];
            if ( ! isset( $map[ $employee_id ] ) ) {
                $map[ $employee_id ] = [];
            }

            $limit = isset( $row['spending_limit'] ) ? (float) $row['spending_limit'] : 0.0;
            $spent = isset( $row['spent_amount'] ) ? (float) $row['spent_amount'] : 0.0;

            $map[ $employee_id ][] = [
                'category_id'   => (int) $row['category_id'],
                'category_name' => $row['name'],
                'limit'         => $limit,
                'spent'         => $spent,
                'remaining'     => max( 0.0, $limit - $spent ),
            ];
        }

        return $map;
    }

    /**
     * Persist employee category limits.
     *
     * @param int   $employee_id Employee identifier.
     * @param array $limits      Array of [ 'category_id' => int, 'limit' => float ].
     * @param int   $company_id  Company identifier.
     */
    public function set_employee_limits( $employee_id, array $limits, $company_id = 0 ) {
        global $wpdb;

        $values = [];
        foreach ( $limits as $limit ) {
            if ( empty( $limit['category_id'] ) ) {
                continue;
            }

            $category_id = absint( $limit['category_id'] );
            $amount      = isset( $limit['limit'] ) ? (float) $limit['limit'] : 0.0;
            if ( $category_id <= 0 ) {
                continue;
            }

            $values[] = $wpdb->prepare( '(%d, %d, %d, %f)', $employee_id, $company_id, $category_id, $amount );
        }

        if ( empty( $values ) ) {
            return;
        }

        $sql = "INSERT INTO {$this->limits_table} (employee_id, company_id, category_id, spending_limit)
                VALUES " . implode( ',', $values ) . '
                ON DUPLICATE KEY UPDATE
                    company_id = VALUES(company_id),
                    spending_limit = VALUES(spending_limit),
                    updated_at = CURRENT_TIMESTAMP,
                    spent_amount = LEAST(spent_amount, VALUES(spending_limit))';

        $wpdb->query( $sql );
    }

    /**
     * Consume allowance from a specific employee/category combination.
     *
     * @param int   $employee_id Employee identifier.
     * @param int   $category_id Category identifier.
     * @param float $amount      Amount to deduct.
     * @return bool Whether the deduction was successful.
     */
    public function consume_allowance( $employee_id, $category_id, $amount ) {
        global $wpdb;

        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->limits_table} SET spent_amount = spent_amount + %f, updated_at = CURRENT_TIMESTAMP\n                 WHERE employee_id = %d AND category_id = %d AND spending_limit >= spent_amount + %f",
                $amount,
                $employee_id,
                $category_id,
                $amount
            )
        );

        return $affected > 0;
    }

    /**
     * Roll back a previously consumed allowance portion.
     *
     * @param int   $employee_id Employee identifier.
     * @param int   $category_id Category identifier.
     * @param float $amount      Amount to restore.
     */
    public function release_allowance( $employee_id, $category_id, $amount ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->limits_table} SET spent_amount = GREATEST(spent_amount - %f, 0), updated_at = CURRENT_TIMESTAMP\n                 WHERE employee_id = %d AND category_id = %d",
                $amount,
                $employee_id,
                $category_id
            )
        );
    }

    /**
     * Retrieve the remaining allowance for an employee/category pair.
     *
     * @param int $employee_id Employee identifier.
     * @param int $category_id Category identifier.
     * @return float
     */
    public function get_remaining_allowance( $employee_id, $category_id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT spending_limit, spent_amount FROM {$this->limits_table} WHERE employee_id = %d AND category_id = %d",
                $employee_id,
                $category_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return 0.0;
        }

        $limit = isset( $row['spending_limit'] ) ? (float) $row['spending_limit'] : 0.0;
        $spent = isset( $row['spent_amount'] ) ? (float) $row['spent_amount'] : 0.0;

        return max( 0.0, $limit - $spent );
    }
}

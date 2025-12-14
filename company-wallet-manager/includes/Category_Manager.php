<?php

namespace CWM;

/**
 * Manage merchant categories and employee category limits.
 */
class Category_Manager {
    /**
     * Whether the custom tables have been verified for the current request lifecycle.
     *
     * @var bool
     */
    protected static $tables_verified = false;

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
     * @var string
     */
    protected $company_caps_table;

    /**
     * Category_Manager constructor.
     */
    public function __construct() {
        global $wpdb;

        $this->categories_table = $wpdb->prefix . 'cwm_categories';
        $this->merchant_table   = $wpdb->prefix . 'cwm_category_merchants';
        $this->limits_table     = $wpdb->prefix . 'cwm_employee_category_limits';
        $this->company_caps_table = $wpdb->prefix . 'cwm_company_category_caps';

        $this->maybe_create_tables();
    }

    /**
     * Ensure the custom tables required for category management exist.
     *
     * On existing installations the activation hook that creates these tables may not have
     * executed yet. We defensively run the table creation logic so API requests do not fail
     * with database errors when the tables are missing.
     */
    protected function maybe_create_tables() {
        if ( self::$tables_verified ) {
            return;
        }

        global $wpdb;

        $expected_tables = [
            $this->categories_table,
            $this->merchant_table,
            $this->limits_table,
            $this->company_caps_table,
        ];

        $missing_tables = [];
        foreach ( $expected_tables as $table ) {
            $query          = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
            $table_existing = $wpdb->get_var( $query );

            if ( $table_existing !== $table ) {
                $missing_tables[] = $table;
            }
        }

        if ( empty( $missing_tables ) ) {
            self::$tables_verified = true;
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $schemas = [
            $this->categories_table => "CREATE TABLE {$this->categories_table} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name VARCHAR(191) NOT NULL,
                        slug VARCHAR(191) NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY slug (slug)
                ) {$charset_collate};",
            $this->merchant_table   => "CREATE TABLE {$this->merchant_table} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        merchant_id BIGINT(20) UNSIGNED NOT NULL,
                        category_id BIGINT(20) UNSIGNED NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY merchant_category (merchant_id, category_id),
                        KEY category_id (category_id)
                ) {$charset_collate};",
            $this->limits_table     => "CREATE TABLE {$this->limits_table} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        employee_id BIGINT(20) UNSIGNED NOT NULL,
                        company_id BIGINT(20) UNSIGNED DEFAULT NULL,
                        category_id BIGINT(20) UNSIGNED NOT NULL,
                        spending_limit DECIMAL(20,6) NOT NULL DEFAULT 0,
                        spent_amount DECIMAL(20,6) NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY employee_category (employee_id, category_id),
                        KEY company_id (company_id),
                        KEY category_id (category_id)
                ) {$charset_collate};",
            $this->company_caps_table => "CREATE TABLE IF NOT EXISTS {$this->company_caps_table} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        company_id BIGINT(20) UNSIGNED NOT NULL,
                        category_id BIGINT(20) UNSIGNED NOT NULL,
                        spending_cap DECIMAL(20,6) NOT NULL DEFAULT 0,
                        limit_type VARCHAR(20) DEFAULT 'amount',
                        limit_value DECIMAL(20,6) DEFAULT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY company_category (company_id, category_id),
                        KEY category_id (category_id)
                ) {$charset_collate};",
        ];

        foreach ( $schemas as $table => $schema ) {
            if ( in_array( $table, $missing_tables, true ) ) {
                dbDelta( $schema );
            }
        }

        // Check and add missing columns to existing tables
        $this->maybe_add_missing_columns();

        self::$tables_verified = true;
    }

    /**
     * Add missing columns to existing tables if they don't exist.
     */
    protected function maybe_add_missing_columns() {
        global $wpdb;

        // Check if company_caps_table exists and has required columns
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->company_caps_table ) ) === $this->company_caps_table;
        
        if ( ! $table_exists ) {
            return;
        }

        // Get existing columns
        $columns = $wpdb->get_results( "DESCRIBE {$this->company_caps_table}", ARRAY_A );
        $column_names = array_column( $columns, 'Field' );

        // Add limit_type if missing
        if ( ! in_array( 'limit_type', $column_names, true ) ) {
            $wpdb->query( "ALTER TABLE {$this->company_caps_table} ADD COLUMN limit_type VARCHAR(20) DEFAULT 'amount' AFTER spending_cap" );
        }

        // Add limit_value if missing
        if ( ! in_array( 'limit_value', $column_names, true ) ) {
            $wpdb->query( "ALTER TABLE {$this->company_caps_table} ADD COLUMN limit_value DECIMAL(20,6) DEFAULT NULL AFTER limit_type" );
        }
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
            $error_message = $wpdb->last_error ? $wpdb->last_error : __( 'Unable to create category.', 'company-wallet-manager' );

            return new \WP_Error(
                'cwm_category_create_failed',
                $error_message,
                [
                    'status' => 500,
                ]
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Retrieve a single category by its identifier.
     *
     * @param int $category_id Category identifier.
     * @return array<string, mixed>|null
     */
    public function get_category( $category_id ) {
        global $wpdb;

        $category_id = absint( $category_id );
        if ( $category_id <= 0 ) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, slug FROM {$this->categories_table} WHERE id = %d",
                $category_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return [
            'id'   => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
        ];
    }

    /**
     * Update an existing category.
     *
     * @param int    $category_id Category identifier.
     * @param string $name        New category name.
     * @return true|\WP_Error
     */
    public function update_category( $category_id, $name ) {
        global $wpdb;

        $category_id = absint( $category_id );
        if ( $category_id <= 0 ) {
            return new \WP_Error( 'cwm_invalid_category', __( 'Invalid category identifier.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $name = trim( (string) $name );
        if ( '' === $name ) {
            return new \WP_Error( 'cwm_invalid_category', __( 'Category name is required.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $existing = $this->get_category( $category_id );
        if ( ! $existing ) {
            return new \WP_Error( 'cwm_category_not_found', __( 'Category not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $slug = sanitize_title( $name );
        if ( '' === $slug ) {
            $slug = $existing['slug'];
        }

        $conflict = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->categories_table} WHERE slug = %s AND id != %d",
                $slug,
                $category_id
            )
        );

        if ( $conflict ) {
            $slug = sprintf( '%s-%d', $slug, $category_id );
        }

        $updated = $wpdb->update(
            $this->categories_table,
            [
                'name'       => $name,
                'slug'       => $slug,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $category_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $error_message = $wpdb->last_error ? $wpdb->last_error : __( 'Unable to update category.', 'company-wallet-manager' );

            return new \WP_Error(
                'cwm_category_update_failed',
                $error_message,
                [
                    'status' => 500,
                ]
            );
        }

        return true;
    }

    /**
     * Delete a category and its related assignments.
     *
     * @param int $category_id Category identifier.
     * @return true|\WP_Error
     */
    public function delete_category( $category_id ) {
        global $wpdb;

        $category_id = absint( $category_id );
        if ( $category_id <= 0 ) {
            return new \WP_Error( 'cwm_invalid_category', __( 'Invalid category identifier.', 'company-wallet-manager' ), [ 'status' => 400 ] );
        }

        $deleted = $wpdb->delete( $this->categories_table, [ 'id' => $category_id ], [ '%d' ] );

        if ( false === $deleted ) {
            $error_message = $wpdb->last_error ? $wpdb->last_error : __( 'Unable to delete category.', 'company-wallet-manager' );

            return new \WP_Error(
                'cwm_category_delete_failed',
                $error_message,
                [ 'status' => 500 ]
            );
        }

        if ( 0 === $deleted ) {
            return new \WP_Error( 'cwm_category_not_found', __( 'Category not found.', 'company-wallet-manager' ), [ 'status' => 404 ] );
        }

        $wpdb->delete( $this->merchant_table, [ 'category_id' => $category_id ], [ '%d' ] );
        $wpdb->delete( $this->limits_table, [ 'category_id' => $category_id ], [ '%d' ] );
        $wpdb->delete( $this->company_caps_table, [ 'category_id' => $category_id ], [ '%d' ] );

        return true;
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

    /**
     * Retrieve category caps configured for a company.
     *
     * @param int $company_id Company identifier.
     * @return array<int, array<string, mixed>>
     */
    public function get_company_category_caps( $company_id ) {
        global $wpdb;

        $company_id = absint( $company_id );

        $sql = $wpdb->prepare(
            "SELECT c.id, c.name, c.slug, caps.spending_cap, caps.limit_type, caps.limit_value FROM {$this->categories_table} c\n             LEFT JOIN {$this->company_caps_table} caps ON caps.category_id = c.id AND caps.company_id = %d\n             ORDER BY c.name ASC",
            $company_id
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return array_map(
            function( $row ) {
                return [
                    'category_id'   => (int) $row['id'],
                    'category_name' => $row['name'],
                    'slug'          => $row['slug'],
                    'cap'           => isset( $row['spending_cap'] ) ? (float) $row['spending_cap'] : null,
                    'limit_type'    => isset( $row['limit_type'] ) ? $row['limit_type'] : null,
                    'limit_value'   => isset( $row['limit_value'] ) ? (float) $row['limit_value'] : null,
                ];
            },
            $rows ?: []
        );
    }

    /**
     * Set a single company category cap.
     *
     * @param int    $company_id  Company identifier.
     * @param int    $category_id Category identifier.
     * @param string $limit_type  'percentage' or 'amount'.
     * @param float  $limit_value Limit value.
     * @return bool
     */
    public function set_company_category_cap( $company_id, $category_id, $limit_type, $limit_value ) {
        global $wpdb;

        $company_id = absint( $company_id );
        $category_id = absint( $category_id );
        $limit_type = sanitize_text_field( $limit_type );
        $limit_value = floatval( $limit_value );

        if ( $company_id <= 0 || $category_id <= 0 ) {
            return false;
        }

        if ( ! in_array( $limit_type, [ 'percentage', 'amount' ], true ) ) {
            return false;
        }

        // Check if cap exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->company_caps_table} WHERE company_id = %d AND category_id = %d",
            $company_id,
            $category_id
        ) );

        $data = [
            'company_id'  => $company_id,
            'category_id' => $category_id,
            'limit_type'  => $limit_type,
            'limit_value' => $limit_value,
        ];

        $format = [ '%d', '%d', '%s', '%f' ];

        if ( $existing ) {
            $result = $wpdb->update(
                $this->company_caps_table,
                $data,
                [ 'id' => $existing ],
                $format,
                [ '%d' ]
            );
            
            // wpdb->update returns false on error, or number of rows affected (0 if no change)
            if ( false === $result ) {
                // Check for actual database error
                if ( ! empty( $wpdb->last_error ) ) {
                    return false;
                }
            }
            // Even if 0 rows affected (data unchanged), it's still a success
        } else {
            $result = $wpdb->insert(
                $this->company_caps_table,
                $data,
                $format
            );
            
            if ( false === $result ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Persist caps for a company.
     *
     * @param int   $company_id Company identifier.
     * @param array $caps       Array of [ 'category_id' => int, 'cap' => float ].
     */
    public function sync_company_category_caps( $company_id, array $caps ) {
        global $wpdb;

        $company_id = absint( $company_id );
        if ( $company_id <= 0 ) {
            return;
        }

        $values      = [];
        $delete_ids  = [];

        foreach ( $caps as $cap ) {
            if ( empty( $cap['category_id'] ) ) {
                continue;
            }

            $category_id = absint( $cap['category_id'] );
            if ( $category_id <= 0 ) {
                continue;
            }

            $amount = isset( $cap['cap'] ) ? (float) $cap['cap'] : 0.0;

            if ( $amount <= 0 ) {
                $delete_ids[] = $category_id;
                continue;
            }

            $values[] = $wpdb->prepare( '(%d, %d, %f)', $company_id, $category_id, $amount );
        }

        if ( ! empty( $values ) ) {
            $sql = "INSERT INTO {$this->company_caps_table} (company_id, category_id, spending_cap) VALUES " . implode( ',', $values ) . '
                ON DUPLICATE KEY UPDATE spending_cap = VALUES(spending_cap), updated_at = CURRENT_TIMESTAMP';

            $wpdb->query( $sql );
        }

        if ( ! empty( $delete_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $delete_ids ), '%d' ) );
            $params       = array_merge( [ $company_id ], $delete_ids );

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->company_caps_table} WHERE company_id = %d AND category_id IN ($placeholders)",
                    $params
                )
            );
        }
    }

    /**
     * Remove a single category cap for a company.
     *
     * @param int $company_id  Company identifier.
     * @param int $category_id Category identifier.
     * @return void
     */
    public function delete_company_category_cap( $company_id, $category_id ) {
        global $wpdb;

        $company_id  = absint( $company_id );
        $category_id = absint( $category_id );

        if ( $company_id <= 0 || $category_id <= 0 ) {
            return;
        }

        $wpdb->delete(
            $this->company_caps_table,
            [
                'company_id'  => $company_id,
                'category_id' => $category_id,
            ],
            [ '%d', '%d' ]
        );
    }
}

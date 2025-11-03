<?php
/**
 * Admin template for managing categories and company caps.
 *
 * @var array $options
 * @var array $categories
 * @var array $companies
 * @var int   $selected_company_id
 * @var array $company_caps
 * @var array $company_caps_map
 */
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Company Wallet Manager Settings', 'company-wallet-manager' ); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php" class="cwm-settings-form">
        <?php
        settings_fields( 'cwm_option_group' );
        do_settings_sections( 'cwm-settings-admin' );
        submit_button();
        ?>
    </form>

    <hr class="wp-header-end" />

    <h2><?php esc_html_e( 'Category Management', 'company-wallet-manager' ); ?></h2>

    <p><?php esc_html_e( 'Create or update spending categories available to merchants and companies.', 'company-wallet-manager' ); ?></p>

    <form method="post" class="cwm-add-category-form">
        <?php wp_nonce_field( 'cwm_manage_categories', 'cwm_manage_categories_nonce' ); ?>
        <input type="hidden" name="cwm_manage_categories_action" value="create" />
        <label for="cwm-new-category" class="screen-reader-text"><?php esc_html_e( 'Category name', 'company-wallet-manager' ); ?></label>
        <input type="text" id="cwm-new-category" name="category_name" class="regular-text" placeholder="<?php esc_attr_e( 'New category name', 'company-wallet-manager' ); ?>" required />
        <?php submit_button( __( 'Add Category', 'company-wallet-manager' ), 'secondary', 'submit', false ); ?>
    </form>

    <table class="widefat striped cwm-category-table">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'ID', 'company-wallet-manager' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Slug', 'company-wallet-manager' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Manage', 'company-wallet-manager' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $categories ) ) : ?>
            <tr>
                <td colspan="3"><?php esc_html_e( 'No categories have been created yet.', 'company-wallet-manager' ); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ( $categories as $category ) : ?>
                <tr>
                    <td><?php echo esc_html( $category['id'] ); ?></td>
                    <td><?php echo esc_html( $category['slug'] ); ?></td>
                    <td>
                        <form method="post" class="cwm-category-inline-form">
                            <?php wp_nonce_field( 'cwm_manage_categories', 'cwm_manage_categories_nonce' ); ?>
                            <input type="hidden" name="category_id" value="<?php echo esc_attr( $category['id'] ); ?>" />
                            <label class="screen-reader-text" for="cwm-category-name-<?php echo esc_attr( $category['id'] ); ?>"><?php esc_html_e( 'Category name', 'company-wallet-manager' ); ?></label>
                            <input type="text" id="cwm-category-name-<?php echo esc_attr( $category['id'] ); ?>" name="category_name" value="<?php echo esc_attr( $category['name'] ); ?>" class="regular-text" />
                            <button type="submit" name="cwm_manage_categories_action" value="update" class="button button-primary"><?php esc_html_e( 'Update', 'company-wallet-manager' ); ?></button>
                            <button type="submit" name="cwm_manage_categories_action" value="delete" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this category? This action cannot be undone.', 'company-wallet-manager' ) ); ?>');"><?php esc_html_e( 'Delete', 'company-wallet-manager' ); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h2><?php esc_html_e( 'Company Category Caps', 'company-wallet-manager' ); ?></h2>

    <p><?php esc_html_e( 'Assign spending caps per category for each company. Leave a value blank or set it to zero to remove the cap.', 'company-wallet-manager' ); ?></p>

    <form method="get" class="cwm-company-selector">
        <input type="hidden" name="page" value="cwm-settings-admin" />
        <label for="cwm-company-select"><?php esc_html_e( 'Select company', 'company-wallet-manager' ); ?></label>
        <select id="cwm-company-select" name="cwm_company_id">
            <option value="0"><?php esc_html_e( 'Choose a company…', 'company-wallet-manager' ); ?></option>
            <?php foreach ( $companies as $company ) : ?>
                <option value="<?php echo esc_attr( $company->ID ); ?>" <?php selected( $selected_company_id, $company->ID ); ?>>
                    <?php echo esc_html( $company->display_name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php submit_button( __( 'Load Caps', 'company-wallet-manager' ), 'secondary', 'submit', false ); ?>
    </form>

    <?php if ( empty( $categories ) ) : ?>
        <p><?php esc_html_e( 'Add at least one category before assigning caps to companies.', 'company-wallet-manager' ); ?></p>
    <?php elseif ( $selected_company_id <= 0 ) : ?>
        <p><?php esc_html_e( 'Select a company to manage its category caps.', 'company-wallet-manager' ); ?></p>
    <?php else : ?>
        <form method="post" class="cwm-company-caps-form">
            <?php wp_nonce_field( 'cwm_assign_caps', 'cwm_assign_caps_nonce' ); ?>
            <input type="hidden" name="company_id" value="<?php echo esc_attr( $selected_company_id ); ?>" />
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Category', 'company-wallet-manager' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Cap amount', 'company-wallet-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $categories as $category ) :
                    $field_id    = 'cwm-cap-' . $category['id'];
                    $cap_value   = array_key_exists( $category['id'], $company_caps_map ) ? $company_caps_map[ $category['id'] ] : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $category['name'] ); ?></td>
                        <td>
                            <label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>"><?php printf( esc_html__( 'Cap for %s', 'company-wallet-manager' ), esc_html( $category['name'] ) ); ?></label>
                            <input type="number" step="0.01" min="0" id="<?php echo esc_attr( $field_id ); ?>" name="caps[<?php echo esc_attr( $category['id'] ); ?>]" value="<?php echo esc_attr( '' !== $cap_value ? $cap_value : '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button( __( 'Save Caps', 'company-wallet-manager' ) ); ?>
        </form>
    <?php endif; ?>
</div>

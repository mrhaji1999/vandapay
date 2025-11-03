<?php
/**
 * Employee form category limits section.
 *
 * @package Company_Wallet_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$employee_id = 0;

if ( isset( $args['employee_id'] ) ) {
    $employee_id = absint( $args['employee_id'] );
} elseif ( isset( $_GET['user_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $employee_id = absint( $_GET['user_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$rest_root = esc_url_raw( rest_url( 'cwm/v1' ) );
$rest_nonce = wp_create_nonce( 'wp_rest' );
?>

<div
    id="cwm-employee-category-limits"
    class="cwm-employee-category-limits"
    data-api-root="<?php echo esc_attr( $rest_root ); ?>"
    data-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
    data-employee-id="<?php echo esc_attr( $employee_id ); ?>"
>
    <h2><?php esc_html_e( 'سقف‌های دسته‌بندی', 'company-wallet-manager' ); ?></h2>
    <p class="description">
        <?php esc_html_e( 'برای هر دسته‌بندی سقف مجاز مصرف را تعیین کنید. این مقادیر در زمان تایید تراکنش‌ها اعمال می‌شوند.', 'company-wallet-manager' ); ?>
    </p>

    <?php if ( 0 === $employee_id ) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e( 'برای تعیین سقف‌های دسته‌بندی ابتدا اطلاعات کارمند را ذخیره کنید.', 'company-wallet-manager' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="cwm-category-fields" aria-live="polite"></div>
    <p class="cwm-category-loading" hidden><?php esc_html_e( 'در حال بارگذاری دسته‌بندی‌ها…', 'company-wallet-manager' ); ?></p>
    <div class="cwm-category-status" role="alert"></div>

    <button type="button" class="button button-primary cwm-save-category-limits" <?php disabled( 0 === $employee_id ); ?>>
        <?php esc_html_e( 'ذخیره سقف‌ها', 'company-wallet-manager' ); ?>
    </button>
</div>

<style>
    #cwm-employee-category-limits {
        margin-top: 1.5rem;
        padding: 1.5rem;
        border: 1px solid #dcdcde;
        background: #fff;
    }

    #cwm-employee-category-limits .cwm-category-fields {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        margin-top: 1rem;
        margin-bottom: 1rem;
    }

    #cwm-employee-category-limits .cwm-category-field {
        border: 1px solid #dcdcde;
        border-radius: 4px;
        padding: 12px;
        background: #f9f9f9;
    }

    #cwm-employee-category-limits .cwm-category-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
    }

    #cwm-employee-category-limits .cwm-category-field input[type="number"] {
        width: 100%;
    }

    #cwm-employee-category-limits .cwm-category-status {
        margin-top: 1rem;
    }
</style>

<script>
(function () {
    const container = document.getElementById('cwm-employee-category-limits');
    if (!container) {
        return;
    }

    const apiRoot = String(container.dataset.apiRoot || '').replace(/\/$/, '');
    const nonce = container.dataset.nonce;
    const employeeId = parseInt(container.dataset.employeeId || '0', 10);
    const fieldsWrapper = container.querySelector('.cwm-category-fields');
    const statusNode = container.querySelector('.cwm-category-status');
    const loadingNode = container.querySelector('.cwm-category-loading');
    const saveButton = container.querySelector('.cwm-save-category-limits');

    if (!fieldsWrapper || !loadingNode || !saveButton) {
        return;
    }

    const request = async (path, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', nonce);
        if (!headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }

        const response = await fetch(apiRoot + path, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers,
            body: options.body ? JSON.stringify(options.body) : undefined,
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok) {
            const message = payload && payload.message ? payload.message : '<?php echo esc_js( __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'company-wallet-manager' ) ); ?>';
            const error = new Error(message);
            error.response = payload;
            throw error;
        }

        return payload;
    };

    const buildLimitsMap = (limits) => {
        const map = new Map();
        if (Array.isArray(limits)) {
            limits.forEach((limit) => {
                const categoryId = Number(limit.category_id || limit.id || 0);
                if (!categoryId) {
                    return;
                }
                map.set(categoryId, {
                    limit: Number(limit.limit || 0),
                    spent: Number(limit.spent || 0),
                    remaining: Number(limit.remaining || 0),
                });
            });
        }
        return map;
    };

    let cachedCategories = [];

    const renderCategories = (categories, limitsMap) => {
        fieldsWrapper.innerHTML = '';

        if (!Array.isArray(categories) || categories.length === 0) {
            const emptyNode = document.createElement('p');
            emptyNode.className = 'description';
            emptyNode.textContent = '<?php echo esc_js( __( 'هیچ دسته‌بندی فعالی یافت نشد.', 'company-wallet-manager' ) ); ?>';
            fieldsWrapper.appendChild(emptyNode);
            return;
        }

        categories.forEach((category) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'cwm-category-field';

            const label = document.createElement('label');
            label.htmlFor = `cwm-category-limit-${category.id}`;
            label.textContent = category.name;

            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.step = 'any';
            input.className = 'regular-text';
            input.id = `cwm-category-limit-${category.id}`;
            input.dataset.categoryId = String(category.id);

            const helper = document.createElement('p');
            helper.className = 'description';

            const entry = limitsMap.get(category.id);
            if (entry) {
                input.value = Number.isFinite(entry.limit) ? String(entry.limit) : '';
                helper.textContent = `<?php echo esc_js( __( 'مصرف‌شده', 'company-wallet-manager' ) ); ?>: ${entry.spent} — <?php echo esc_js( __( 'باقی‌مانده', 'company-wallet-manager' ) ); ?>: ${entry.remaining}`;
            } else {
                input.value = '';
                helper.textContent = '<?php echo esc_js( __( 'برای این دسته‌بندی سقفی ثبت نشده است.', 'company-wallet-manager' ) ); ?>';
            }

            wrapper.appendChild(label);
            wrapper.appendChild(input);
            wrapper.appendChild(helper);

            fieldsWrapper.appendChild(wrapper);
        });
    };

    const setStatus = (message, type = '') => {
        statusNode.textContent = '';
        statusNode.className = 'cwm-category-status';

        if (!message) {
            return;
        }

        statusNode.textContent = message;
        if (type) {
            statusNode.classList.add('notice', type);
        }
    };

    const loadData = async () => {
        loadingNode.hidden = false;
        try {
            const categoriesResponse = await request('/admin/categories', {
                method: 'GET',
            });
            const categories = Array.isArray(categoriesResponse?.data) ? categoriesResponse.data : [];
            cachedCategories = categories.map((category) => ({
                id: Number(category.id || category.category_id || 0),
                name: String(category.name || category.category_name || ''),
                slug: String(category.slug || ''),
            })).filter((category) => category.id > 0);

            let limitsMap = new Map();
            if (employeeId > 0) {
                try {
                    const limitsResponse = await request(`/admin/employees/${employeeId}/limits`, { method: 'GET' });
                    const limits = limitsResponse?.data?.categories || [];
                    limitsMap = buildLimitsMap(limits);
                } catch (error) {
                    if (error && error.message) {
                        setStatus(error.message, 'notice-warning');
                    }
                }
            }

            renderCategories(cachedCategories, limitsMap);
        } catch (error) {
            setStatus(error && error.message ? error.message : '<?php echo esc_js( __( 'بارگذاری دسته‌بندی‌ها با خطا مواجه شد.', 'company-wallet-manager' ) ); ?>', 'notice-error');
            cachedCategories = [];
            renderCategories([], new Map());
            saveButton.disabled = true;
        } finally {
            loadingNode.hidden = true;
            if (employeeId > 0) {
                saveButton.disabled = false;
            }
        }
    };

    const gatherPayload = () => {
        const inputs = fieldsWrapper.querySelectorAll('input[data-category-id]');
        const limits = [];
        inputs.forEach((input) => {
            const categoryId = parseInt(input.dataset.categoryId || '0', 10);
            if (!categoryId) {
                return;
            }
            const rawValue = input.value.trim();
            const limit = rawValue === '' ? 0 : Number(rawValue);
            limits.push({
                category_id: categoryId,
                limit: Number.isFinite(limit) ? limit : 0,
            });
        });

        return limits;
    };

    const handleSave = async () => {
        if (employeeId <= 0) {
            setStatus('<?php echo esc_js( __( 'پس از ذخیره کارمند می‌توانید سقف‌های دسته‌بندی را تعیین کنید.', 'company-wallet-manager' ) ); ?>', 'notice-warning');
            return;
        }

        const limits = gatherPayload();
        saveButton.disabled = true;
        setStatus('<?php echo esc_js( __( 'در حال ذخیره‌سازی…', 'company-wallet-manager' ) ); ?>');

        try {
            const response = await request(`/admin/employees/${employeeId}/limits`, {
                method: 'POST',
                body: { limits },
            });

            const limitsMap = buildLimitsMap(response?.data?.categories || []);
            renderCategories(cachedCategories, limitsMap);
            setStatus('<?php echo esc_js( __( 'سقف‌های دسته‌بندی با موفقیت ذخیره شد.', 'company-wallet-manager' ) ); ?>', 'notice-success');
        } catch (error) {
            setStatus(error && error.message ? error.message : '<?php echo esc_js( __( 'ذخیره سقف‌ها با خطا مواجه شد.', 'company-wallet-manager' ) ); ?>', 'notice-error');
        } finally {
            saveButton.disabled = employeeId <= 0;
        }
    };

    saveButton.addEventListener('click', handleSave);

    loadData();
})();
</script>

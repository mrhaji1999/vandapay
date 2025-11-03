<?php
use CWM\Category_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    echo '<div class="cwm-alert cwm-alert--error">' . esc_html__( 'برای استفاده از این بخش ابتدا وارد شوید.', 'company-wallet-manager' ) . '</div>';
    return;
}

$current_user = wp_get_current_user();
if ( ! in_array( 'merchant', (array) $current_user->roles, true ) ) {
    echo '<div class="cwm-alert cwm-alert--error">' . esc_html__( 'دسترسی این بخش فقط برای پذیرندگان فعال است.', 'company-wallet-manager' ) . '</div>';
    return;
}

$category_manager = new Category_Manager();
$categories        = $category_manager->get_merchant_categories( $current_user->ID );
$rest_nonce        = wp_create_nonce( 'wp_rest' );
$check_endpoint    = esc_url( rest_url( 'cwm/v1/merchant/transactions/category-limit' ) );
$confirm_endpoint  = esc_url( rest_url( 'cwm/v1/merchant/transactions/confirm' ) );
?>
<div class="cwm-checkout" data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>" data-check-endpoint="<?php echo esc_attr( $check_endpoint ); ?>" data-confirm-endpoint="<?php echo esc_attr( $confirm_endpoint ); ?>">
    <?php if ( empty( $categories ) ) : ?>
        <div class="cwm-alert cwm-alert--error"><?php esc_html_e( 'هیچ دسته‌بندی فعالی برای این پذیرنده ثبت نشده است. ابتدا دسته‌بندی‌های مجاز را فعال کنید.', 'company-wallet-manager' ); ?></div>
    <?php endif; ?>

    <form id="cwm-checkout-form" class="cwm-checkout__form" <?php echo empty( $categories ) ? 'aria-disabled="true"' : ''; ?>>
        <h2 class="cwm-checkout__title"><?php esc_html_e( 'ثبت تراکنش جدید', 'company-wallet-manager' ); ?></h2>
        <div class="cwm-field">
            <label for="cwm-national-id" class="cwm-field__label"><?php esc_html_e( 'کد ملی کاربر', 'company-wallet-manager' ); ?></label>
            <input type="text" id="cwm-national-id" name="national_id" class="cwm-field__input" required maxlength="10" autocomplete="off" />
        </div>
        <div class="cwm-field">
            <label for="cwm-amount" class="cwm-field__label"><?php esc_html_e( 'مبلغ تراکنش (ریال)', 'company-wallet-manager' ); ?></label>
            <input type="number" id="cwm-amount" name="amount" class="cwm-field__input" min="1000" step="100" required />
        </div>
        <div class="cwm-field">
            <label for="cwm-category" class="cwm-field__label"><?php esc_html_e( 'دسته‌بندی پذیرنده', 'company-wallet-manager' ); ?></label>
            <select id="cwm-category" name="category_id" class="cwm-field__input" required>
                <option value="">
                    <?php esc_html_e( 'انتخاب دسته‌بندی', 'company-wallet-manager' ); ?>
                </option>
                <?php foreach ( $categories as $category ) : ?>
                    <option value="<?php echo esc_attr( $category['id'] ); ?>"><?php echo esc_html( $category['name'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="cwm-button cwm-button--primary" <?php echo empty( $categories ) ? 'disabled' : ''; ?>><?php esc_html_e( 'استعلام و ارسال رمز یکبار مصرف', 'company-wallet-manager' ); ?></button>
        <div class="cwm-form__feedback" role="alert" aria-live="polite"></div>
    </form>

    <form id="cwm-otp-form" class="cwm-checkout__form cwm-checkout__form--otp" hidden>
        <h3 class="cwm-checkout__subtitle"><?php esc_html_e( 'تأیید پرداخت', 'company-wallet-manager' ); ?></h3>
        <p class="cwm-checkout__summary"></p>
        <div class="cwm-field">
            <label for="cwm-otp" class="cwm-field__label"><?php esc_html_e( 'رمز یکبار مصرف', 'company-wallet-manager' ); ?></label>
            <input type="text" id="cwm-otp" name="otp_code" class="cwm-field__input" required maxlength="6" autocomplete="one-time-code" inputmode="numeric" />
        </div>
        <button type="submit" class="cwm-button cwm-button--success"><?php esc_html_e( 'تأیید و پرداخت', 'company-wallet-manager' ); ?></button>
        <div class="cwm-form__feedback" role="alert" aria-live="polite"></div>
    </form>

    <div id="cwm-checkout-result" class="cwm-checkout__result" hidden>
        <h3 class="cwm-checkout__subtitle"><?php esc_html_e( 'نتیجه تراکنش', 'company-wallet-manager' ); ?></h3>
        <ul class="cwm-result__list">
            <li><strong><?php esc_html_e( 'مانده کیف پول کاربر:', 'company-wallet-manager' ); ?></strong> <span data-result="wallet"></span></li>
            <li><strong><?php esc_html_e( 'سقف باقی‌مانده دسته‌بندی:', 'company-wallet-manager' ); ?></strong> <span data-result="remaining"></span></li>
            <li><strong><?php esc_html_e( 'وضعیت:', 'company-wallet-manager' ); ?></strong> <span data-result="message"></span></li>
        </ul>
    </div>
</div>

<style>
.cwm-checkout {
    max-width: 520px;
    margin: 2rem auto;
    padding: 1.5rem;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
    display: grid;
    gap: 1.5rem;
}
.cwm-checkout__title, .cwm-checkout__subtitle {
    margin-top: 0;
    text-align: center;
}
.cwm-checkout__form {
    display: grid;
    gap: 1rem;
}
.cwm-field {
    display: grid;
    gap: 0.3rem;
}
.cwm-field__label {
    font-weight: 600;
}
.cwm-field__input {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 0.65rem 0.75rem;
    font-size: 1rem;
}
.cwm-button {
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    cursor: pointer;
    font-weight: 600;
}
.cwm-button--primary {
    background: #2563eb;
    color: #fff;
}
.cwm-button--primary:hover {
    background: #1d4ed8;
}
.cwm-button--success {
    background: #059669;
    color: #fff;
}
.cwm-button--success:hover {
    background: #047857;
}
.cwm-alert {
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem auto;
    max-width: 520px;
}
.cwm-alert--error {
    background: #fee2e2;
    color: #b91c1c;
}
.cwm-form__feedback {
    min-height: 1.25rem;
    font-size: 0.9rem;
}
.cwm-form__feedback--error {
    color: #b91c1c;
}
.cwm-form__feedback--success {
    color: #047857;
}
.cwm-checkout__summary {
    margin: 0;
    line-height: 1.6;
    text-align: center;
}
.cwm-result__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 0.5rem;
}
.cwm-checkout__result {
    background: #f9fafb;
    border-radius: 10px;
    padding: 1rem;
}
</style>

<script>
(function() {
    const container = document.querySelector('.cwm-checkout');
    if (!container) {
        return;
    }

    const restNonce = container.getAttribute('data-rest-nonce');
    const checkEndpoint = container.getAttribute('data-check-endpoint');
    const confirmEndpoint = container.getAttribute('data-confirm-endpoint');

    const checkoutForm = container.querySelector('#cwm-checkout-form');
    const otpForm = container.querySelector('#cwm-otp-form');
    const resultBox = container.querySelector('#cwm-checkout-result');
    const summaryBox = otpForm.querySelector('.cwm-checkout__summary');
    const checkoutFeedback = checkoutForm.querySelector('.cwm-form__feedback');
    const otpFeedback = otpForm.querySelector('.cwm-form__feedback');
    const walletResult = resultBox.querySelector('[data-result="wallet"]');
    const remainingResult = resultBox.querySelector('[data-result="remaining"]');
    const messageResult = resultBox.querySelector('[data-result="message"]');

    let pendingRequestId = null;

    function setFeedback(element, message, isError = false) {
        element.textContent = message || '';
        element.classList.toggle('cwm-form__feedback--error', Boolean(isError));
        element.classList.toggle('cwm-form__feedback--success', Boolean(!isError && message));
    }

    function extractMessage(result, fallback) {
        if (result && typeof result === 'object') {
            if (result.message) {
                return result.message;
            }
            if (result.data && result.data.message) {
                return result.data.message;
            }
        }
        return fallback || '';
    }

    function formatCurrency(value) {
        const numeric = Number(value || 0);
        if (isNaN(numeric)) {
            return '0';
        }
        try {
            return numeric.toLocaleString('fa-IR');
        } catch (error) {
            return numeric.toLocaleString();
        }
    }

    checkoutForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (checkoutForm.getAttribute('aria-disabled') === 'true') {
            return;
        }
        setFeedback(checkoutFeedback, '<?php echo esc_js( __( 'در حال بررسی...', 'company-wallet-manager' ) ); ?>');
        setFeedback(otpFeedback, '');
        resultBox.hidden = true;
        otpForm.hidden = true;
        pendingRequestId = null;

        const formData = new FormData(checkoutForm);
        const payload = {
            national_id: formData.get('national_id'),
            amount: parseFloat(formData.get('amount')),
            category_id: parseInt(formData.get('category_id'), 10)
        };

        try {
            const response = await fetch(checkEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || result.status !== 'success') {
                const message = extractMessage(result, response.statusText);
                setFeedback(checkoutFeedback, message || '<?php echo esc_js( __( 'خطای ناشناخته‌ای رخ داد.', 'company-wallet-manager' ) ); ?>', true);
                return;
            }

            pendingRequestId = result.data.request_id;

            const summaryMessage = '<?php echo esc_js( __( 'رمز یکبار مصرف برای شماره ثبت‌شده ارسال شد. مبلغ تراکنش: %s ریال. سقف قابل استفاده: %s ریال.', 'company-wallet-manager' ) ); ?>'
                .replace('%s', formatCurrency(payload.amount))
                .replace('%s', formatCurrency(result.data.available));

            summaryBox.textContent = summaryMessage;
            otpForm.hidden = false;
            setFeedback(checkoutFeedback, result.message || '<?php echo esc_js( __( 'درخواست با موفقیت ثبت شد.', 'company-wallet-manager' ) ); ?>', false);
            setFeedback(otpFeedback, '');
        } catch (error) {
            console.error('CWM checkout error', error);
            setFeedback(checkoutFeedback, '<?php echo esc_js( __( 'برقراری ارتباط با سرور ممکن نشد.', 'company-wallet-manager' ) ); ?>', true);
        }
    });

    otpForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (!pendingRequestId) {
            setFeedback(otpFeedback, '<?php echo esc_js( __( 'ابتدا باید درخواست پرداخت ثبت شود.', 'company-wallet-manager' ) ); ?>', true);
            return;
        }

        setFeedback(otpFeedback, '<?php echo esc_js( __( 'در حال تأیید...', 'company-wallet-manager' ) ); ?>');

        const formData = new FormData(otpForm);
        const payload = {
            request_id: pendingRequestId,
            otp_code: formData.get('otp_code')
        };

        try {
            const response = await fetch(confirmEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || result.status !== 'success') {
                const message = extractMessage(result, response.statusText);
                setFeedback(otpFeedback, message || '<?php echo esc_js( __( 'خطایی در تأیید تراکنش رخ داد.', 'company-wallet-manager' ) ); ?>', true);
                return;
            }

            setFeedback(otpFeedback, result.message || '<?php echo esc_js( __( 'پرداخت با موفقیت انجام شد.', 'company-wallet-manager' ) ); ?>', false);
            walletResult.textContent = formatCurrency(result.data.wallet_balance) + ' ' + '<?php echo esc_js( __( 'ریال', 'company-wallet-manager' ) ); ?>';
            remainingResult.textContent = formatCurrency(result.data.category_remaining) + ' ' + '<?php echo esc_js( __( 'ریال', 'company-wallet-manager' ) ); ?>';
            messageResult.textContent = result.message || '<?php echo esc_js( __( 'پرداخت موفق', 'company-wallet-manager' ) ); ?>';
            resultBox.hidden = false;
            otpForm.hidden = true;
            checkoutForm.reset();
            pendingRequestId = null;
        } catch (error) {
            console.error('CWM checkout confirm error', error);
            setFeedback(otpFeedback, '<?php echo esc_js( __( 'برقراری ارتباط با سرور ممکن نشد.', 'company-wallet-manager' ) ); ?>', true);
        }
    });
})();
</script>

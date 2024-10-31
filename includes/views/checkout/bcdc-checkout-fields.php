<?php
/** @var Paypal_Brasil_BCDC_Gateway $this */

// Exit if not in WordPress.
if (!defined('ABSPATH')) {
    exit;
}

$error = false;
$data = null;
$data_error = null;

try {

    $data = $this->get_posted_data();
} catch (Exception $ex) {
    $data_error = $ex->getMessage();
    wc_add_notice($data_error, 'error');
} catch (PayPal_Brasil_API_Exception $ex) {
    $error = $ex->getData();
    wc_add_notice($error, 'error');
}
?>
<div id="wc-bcdc-brasil-wrappers">
    <?php echo isset($data['cart_hash']) ? $data['cart_hash'] : ''; ?>
    <?php if ($error): ?>
        <input type="hidden" id="wc-bcdc-brasil-api-error-data" name="wc-bcdc-brasil-data"
            value="<?php echo htmlentities(json_encode($error)); ?>">
    <?php else: ?>
        <input type="hidden" id="wc-bcdc-brasil-data" name="wc-bcdc-brasil-data"
            value="<?php echo htmlentities(json_encode($data)); ?>">
        <input type="hidden" id="wc-bcdc-brasil-response" name="wc-bcdc-brasil-response" value="">
        <input type="hidden" id="wc-bcdc-brasil-error" name="wc-bcdc-brasil-error"
            value="<?php if ($data_error) {
                echo htmlentities(json_encode($data_error));
            } ?>">
        <div id="wc-bcdc-brasil-container-loading" class="hidden">
            <div class="paypal-loading"></div>
        </div>
        <div id="wc-bcdc-brasil-container"></div>
        <div id="wc-bcdc-brasil-container-overlay" class="hidden">
            <div class="icon-lock"></div>
        </div>
    <?php endif; ?>

    <div id="wc-bcdc-brasil-banner-wrapper">
        <div id="wc-bcdc-brasil-banner" style="width:80%;">
            <span id="pay-with-text" class="wc-bcdc-paypal-text">Finalize o pagamento</span>
            <span id="pay-on-click-text" class="wc-bcdc-paypal-text">Clique no botão abaixo, para preencher os dados do seu cartão e finalize o pagamento.</span>
            <div id="line"></div>
            <div id="card-brands-value">
                <img src="<?php echo esc_url( plugins_url( 'assets/images/bcdc-card-brands.png', PAYPAL_PAYMENTS_MAIN_FILE ) ); ?>" alt="">
                <span id="bcdc-value-cart">Valor total: <span id="bcdc-total-cart-label"></span></span>
            </div>
        </div>

    </div>
</div>
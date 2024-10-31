<?php

/**
 * Plugin Name: PayPal Brasil para WooCommerce
 * Description: Adicione facilmente opções de pagamento do PayPal à sua loja do WooCommerce.
 * Version: 1.5.6
 * Author: PayPal
 * Author URI: https://paypal.com.br
 * Requires at least: 4.4
 * Tested up to: 6.6.1
 * Text Domain: paypal-brasil-para-woocommerce
 * Domain Path: /languages
 * WC requires at least: 3.6
 * WC tested up to: 9.1.4
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Init PayPal Payments.
 */
function paypal_brasil_init() {
	include_once dirname( __FILE__ ) . '/vendor/autoload.php';
	include_once dirname( __FILE__ ) . '/class-paypal-brasil.php';

	// Define files.
	define( 'PAYPAL_PAYMENTS_MAIN_FILE', __FILE__ );
	define( 'PAYPAL_PAYMENTS_VERSION', '1.5.6' );
    define('WC_PAYPAL_PLUGIN_SLUG','paypal-brasil-para-woocommerce');

	// Init plugin.
	PayPal_Brasil::get_instance();
}


function my_plugin_load_my_own_textdomain( $mofile, $domain ) {

	if ( "paypal-brasil-para-woocommerce" === $domain && false !== strpos( $mofile, WP_LANG_DIR . '/plugins/' ) ) {
		$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
		$mofile = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/' . $domain . '-' . $locale . '.mo';
	}
	return $mofile;
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

function statistic_tag_update_plugin()
{
    try {
        if (class_exists('WC_Payment_Gateway')) {
			
            $gateway_settings_bcdc = get_option( 'woocommerce_paypal-brasil-bcdc-gateway_settings' );
            $gateway_settings_spb = get_option( 'woocommerce_paypal-brasil-spb-gateway_settings' );
            $gateway_settings_ppp = get_option( 'woocommerce_paypal-brasil-plus-gateway_settings' );
    		$plugin_id = get_option('plugin_id');
			
            // Verificar se o método de pagamento desejado está presente na lista de métodos ativos
            $data = array(
				'uuid' => $plugin_id ? $plugin_id : null,
                'status' => 'updated',
                'store_url' => home_url(),
				'plugin_version' => PAYPAL_PAYMENTS_VERSION,
                'spb_enabled' => isset($gateway_settings_spb) ? $gateway_settings_spb['enabled'] : false,
                'ppp_enabled' => isset($gateway_settings_ppp) ? $gateway_settings_ppp['enabled'] : false,
                'bcdc_enabled' => isset($gateway_settings_bcdc) ? $gateway_settings_bcdc['enabled'] : false,
            );
    
            // Inicializar a sessão cURL
            $ch = curl_init();
    
            // Definir as opções da requisição cURL
            curl_setopt($ch, CURLOPT_URL, 'https://paypalpcpnuvem.com/validate');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            

            $response = curl_exec($ch);
            
            if (!curl_errno($ch)) {
                $response_data = json_decode($response, true);
            }

            curl_close($ch);
    

            if ($response_data != null) {
                update_option('token_authentication_hash', $response_data['token_authentication_hash']);
				update_option('plugin_id', $response_data['uuid']);
            }

        }
    } catch (\Throwable $th) {
        return;
    }
    
}


// Init plugin.
paypal_brasil_init();
//register_activation_hook(PAYPAL_PAYMENTS_MAIN_FILE, 'statistic_tag_update_plugin', 10, 2);
add_action('upgrader_process_complete', 'statistic_tag_update_plugin', 10, 2);
add_filter( 'load_textdomain_mofile', 'my_plugin_load_my_own_textdomain', 10, 2 );
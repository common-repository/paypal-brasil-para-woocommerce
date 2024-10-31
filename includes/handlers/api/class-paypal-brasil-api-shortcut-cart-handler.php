<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayPal_Brasil_API_Shortcut_Cart_Handler extends PayPal_Brasil_API_Handler {

	public function __construct() {
		add_filter( 'paypal_brasil_handlers', array( $this, 'add_handlers' ) );
	}

	public function add_handlers( $handlers ) {
		$handlers['shortcut-cart'] = array(
			'callback' => array( $this, 'handle' ),
			'method'   => 'POST',
		);

		return $handlers;
	}

	/**
	 * Add validators and input fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array(
			array(
				'name'     => __( 'nonce', "paypal-brasil-para-woocommerce" ),
				'key'      => 'nonce',
				'sanitize' => 'sanitize_text_field',
//				'validation' => array( $this, 'required_nonce' ),
			),
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle() {
		try {

			$validation = $this->validate_input_data();

			if ( ! $validation['success'] ) {
				$this->send_error_response(
					__( 'Some fields are missing to initiate the payment.', 'paypal-brasil' ),
					array(
						'errors' => $validation['errors']
					)
				);
			}

			// Get the wanted gateway.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-spb-gateway' );

			// Set the gateway as default payment method
			WC()->session->set( 'chosen_payment_method', $gateway->id );

			// Recalculate totals
			WC()->cart->calculate_totals();

			// Store cart.
			$cart = WC()->cart;

			// Check if there is anything on cart.
			if ( ! $cart->get_totals()['total'] ) {
				$this->send_error_response( __( 'You cannot pay for an empty order.', "paypal-brasil-para-woocommerce" ) );
			}

			$wc_cart = WC()->cart;
			$wc_cart_totals = new WC_Cart_Totals($wc_cart);
			$cart_totals = $wc_cart_totals->get_totals(true);

			$only_digital_items = paypal_brasil_is_cart_only_digital();

			$data = array (
				'purchase_units' => array(
					array(
							'items'      => array(
								array(
									'name'     => sprintf( __( 'Store order %s - carrinho', "paypal-brasil-para-woocommerce" ),get_bloginfo( 'name' ) ),
									'unit_amount' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] - $cart_totals['shipping_total'] ) ),
									),
									'quantity' => 1,
									'sku'      => 'order-items',
								),
							),
							'amount' => array(
								'currency_code' => get_woocommerce_currency(),
								'value' => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] ) ),
								'breakdown' => array(
									'item_total' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] - $cart_totals['shipping_total'] ) )
									),
									'tax_total' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => '0.00'
									),
									'discount' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => '0.00'
									),
									'shipping' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['shipping_total']))
									),
								)
							),
						),
				),
				'intent' => 'CAPTURE',
				'payment_source'               => array(
					'paypal'      => array(
						'experience_context' => array(
							'shipping_preference' => $only_digital_items ? 'NO_SHIPPING' : 'GET_FROM_FILE',
							'return_url' => home_url(),
							'cancel_url' => home_url(),
						),
						'user_action' => 'CONTINUE'
					),
				),
				'application_context' => array(
					'brand_name'          => get_bloginfo( 'name' ),
					'user_action'         => 'CONTINUE',
				),
			);

			// Create the payment in API.
			$create_payment = $gateway->api->create_payment( $data, array(), 'shortcut' );

			// Get the response links.
			$links = $gateway->api->parse_links( $create_payment['links'] );

			// Extract EC token from response.
			//preg_match( '/(EC-\w+)/', $links['approval_url'], $ec_token );

			// Parse a URL para obter os parâmetros
			$urlParse = parse_url($links['payer-action']);

			// Obtenha os parâmetros da consulta
			parse_str($urlParse['query'], $param);

			// Obtenha o valor do parâmetro 'token'
			$token = isset($param['token']) ? $param['token'] : null;

			// Separate data.
			$data = array(
				'pay_id'   => $create_payment['id'],
				'ec'       => $token,
				'postcode' => preg_replace( '/[^0-9]/', '', WC()->customer->get_shipping_postcode() ),
			);

			// Store the requested data in session.
			WC()->session->set( 'paypal_brasil_spb_shortcut_data', $data );

			// Send success response with data.
			$this->send_success_response( __( 'Payment created successfully.', "paypal-brasil-para-woocommerce" ), $data );
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}

	// CUSTOM VALIDATORS

	public function required_nonce( $data, $key, $name ) {
		if ( wp_verify_nonce( $data, 'paypal-brasil-checkout' ) ) {
			return true;
		}

		return sprintf( __( 'The %s is invalid.', "paypal-brasil-para-woocommerce" ), $name );
	}

	// CUSTOM SANITIZER

	public function sanitize_boolean( $data, $key ) {
		return ! ! $data;
	}

}

new PayPal_Brasil_API_Shortcut_Cart_Handler();
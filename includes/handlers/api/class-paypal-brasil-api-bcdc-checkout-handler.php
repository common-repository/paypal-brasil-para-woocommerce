<?php

if (!defined('ABSPATH')) {
	exit;
}

class PayPal_Brasil_API_Bcdc_Checkout_Handler extends PayPal_Brasil_API_Handler
{

	public function __construct()
	{
		add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
	}		/**
			 * Get the posted data in the checkout.
			 *
			 * @return array
			 * @throws Exception
			 */
	public function get_posted_data()
	{
		$order_id = get_query_var('order-pay');
		$order = $order_id ? new WC_Order($order_id) : null;
		$data = array();
		$defaults = array(
			'first_name' => '',
			'last_name' => '',
			'person_type' => '',
			'cpf' => '',
			'cnpj' => '',
			'phone' => '',
			'email' => '',
			'postcode' => '',
			'address' => '',
			'number' => '',
			'address_2' => '',
			'neighborhood' => '',
			'city' => '',
			'state' => '',
			'country' => '',
			'approval_url' => '',
			'payment_id' => '',
			'dummy' => false,
			'invalid' => array(),
		);
		if ($order) {
			$billing_cellphone = get_post_meta($order->get_id(), '_billing_cellphone', true);
			$data['postcode'] = $order->get_shipping_postcode();
			$data['address'] = $order->get_shipping_address_1();
			$data['address_2'] = $order->get_shipping_address_2();
			$data['city'] = $order->get_shipping_city();
			$data['state'] = $order->get_shipping_state();
			$data['country'] = $order->get_shipping_country();
			$data['neighborhood'] = get_post_meta($order->get_id(), '_billing_neighborhood', true);
			$data['number'] = get_post_meta($order->get_id(), '_billing_number', true);
			$data['first_name'] = $order->get_billing_first_name();
			$data['last_name'] = $order->get_billing_last_name();
			$data['person_type'] = get_post_meta($order->get_id(), '_billing_persontype', true);
			$data['cpf'] = get_post_meta($order->get_id(), '_billing_cpf', true);
			$data['cnpj'] = get_post_meta($order->get_id(), '_billing_cnpj', true);
			$data['phone'] = $billing_cellphone ? $billing_cellphone : $order->get_billing_phone();
			$data['email'] = $order->get_billing_email();
		} else if ($_POST) {
			$data['postcode'] = isset($_POST['s_postcode']) ? preg_replace('/[^0-9]/', '', $_POST['s_postcode']) : '';
			$data['address'] = isset($_POST['s_address']) ? sanitize_text_field($_POST['s_address']) : '';
			$data['address_2'] = isset($_POST['s_address_2']) ? sanitize_text_field($_POST['s_address_2']) : '';
			$data['city'] = isset($_POST['s_city']) ? sanitize_text_field($_POST['s_city']) : '';
			$data['state'] = isset($_POST['s_state']) ? sanitize_text_field($_POST['s_state']) : '';
			$data['country'] = isset($_POST['s_country']) ? sanitize_text_field($_POST['s_country']) : '';
			// Now get other post data that other fields can send.
			$post_data = array();
			if (isset($_POST['post_data'])) {
				parse_str($_POST['post_data'], $post_data);
			}
			$billing_cellphone = isset($post_data['billing_cellphone']) ? sanitize_text_field($post_data['billing_cellphone']) : '';
			$data['neighborhood'] = isset($post_data['billing_neighborhood']) ? sanitize_text_field($post_data['billing_neighborhood']) : '';
			$data['number'] = isset($post_data['billing_number']) ? sanitize_text_field($post_data['billing_number']) : '';
			$data['first_name'] = isset($post_data['billing_first_name']) ? sanitize_text_field($post_data['billing_first_name']) : '';
			$data['last_name'] = isset($post_data['billing_last_name']) ? sanitize_text_field($post_data['billing_last_name']) : '';
			$data['person_type'] = isset($post_data['billing_persontype']) ? sanitize_text_field($post_data['billing_persontype']) : '';
			$data['cpf'] = isset($post_data['billing_cpf']) ? sanitize_text_field($post_data['billing_cpf']) : '';
			$data['cnpj'] = isset($post_data['billing_cnpj']) ? sanitize_text_field($post_data['billing_cnpj']) : '';
			$data['phone'] = $billing_cellphone ? $billing_cellphone : (isset($post_data['billing_phone']) ? sanitize_text_field($post_data['billing_phone']) : '');
			$data['email'] = isset($post_data['billing_email']) ? sanitize_text_field($post_data['billing_email']) : '';
		}
		if (paypal_brasil_needs_cpf()) {
			// Get wcbcf settings
			$wcbcf_settings = get_option('wcbcf_settings');
			// Set the person type default if we don't have any person type defined
			if ($wcbcf_settings && isset($data['person_type']) && ($wcbcf_settings['person_type'] == '2' || $wcbcf_settings['person_type'] == '3')) {
				// The value 2 from person_type in settings is CPF (1) and 3 is CNPJ (2), and 1 is both, that won't reach here.
				$data['person_type'] = $wcbcf_settings['person_type'] == '2' ? '1' : '2';
				$data['person_type_default'] = true;
			}
		}
		// Now set the invalid.
		$data = wp_parse_args($data, $defaults);
		$data = apply_filters('wc_bcdc_brasil_user_data', $data);
		$invalid = $this->validate_data($data);
		// if its invalid, return demo data.
		// Also check if we are on our payment method. If not, render demo data.
		if (!$order && isset($post_data['payment_method']) && $post_data['payment_method'] !== $this->id) {
			$invalid['wrong-payment-method'] = __('PayPal payment method is not selected.', "paypal-brasil-para-woocommerce");
		}

		if ($invalid) {
			$data = array(
				'first_name' => 'PayPal',
				'last_name' => 'Brasil',
				'person_type' => '2',
				'cpf' => '',
				'cnpj' => '10.878.448/0001-66',
				'phone' => '(21) 99999-99999',
				'email' => 'contato@paypal.com.br',
				'postcode' => '01310-100',
				'address' => 'Av. Paulista',
				'number' => '1048',
				'address_2' => '',
				'neighborhood' => 'Bela Vista',
				'city' => 'São Paulo',
				'state' => 'SP',
				'country' => 'BR',
				'dummy' => true,
				'invalid' => $invalid,
			);
		}
		// Add session if is dummy data to check it later.
		WC()->session->set('wc-bcdc-brasil-dummy-data', $data['dummy']);
		// Return the data if is dummy. We don't need to process this.
		if ($invalid) {
			return $data;
		}
		// Create the payment.
		$payment = $order ? $this->create_payment_for_order($data, $order, $data['dummy']) : $this->create_payment_for_cart($data, $data['dummy']);

		// Add the saved remember card, approval link and the payment URL.
		//$data['remembered_cards'] = is_user_logged_in() ? get_user_meta(get_current_user_id(), 'wc_ppp_brasil_remembered_cards', true) : '';
		$data['approval_url'] = $payment['links'][1]['href'];
		$data['payment_id'] = $payment['id'];

		return $data;
	}

	public function add_handlers($handlers)
	{
		$handlers['checkout_bcdc'] = array(
			'callback' => array($this, 'handle'),
			'method' => 'POST',
		);

		return $handlers;
	}

	/**
	 * Add validators and input fields.
	 *
	 * @return array
	 */
	public function get_fields()
	{
		return array(
			array(
				'name' => __('nonce', "paypal-brasil-para-woocommerce"),
				'key' => 'nonce',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_nonce'),
			),
			array(
				'name' => __('name', "paypal-brasil-para-woocommerce"),
				'key' => 'first_name',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('surname', "paypal-brasil-para-woocommerce"),
				'key' => 'last_name',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('city', "paypal-brasil-para-woocommerce"),
				'key' => 'city',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('country', "paypal-brasil-para-woocommerce"),
				'key' => 'country',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_country'),
			),
			array(
				'name' => __('zip code', "paypal-brasil-para-woocommerce"),
				'key' => 'postcode',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_postcode'),
			),
			array(
				'name' => __('state', "paypal-brasil-para-woocommerce"),
				'key' => 'state',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_state'),
			),
			array(
				'name' => __('address', "paypal-brasil-para-woocommerce"),
				'key' => 'address_line_1',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('number', "paypal-brasil-para-woocommerce"),
				'key' => 'number',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('complement', "paypal-brasil-para-woocommerce"),
				'key' => 'address_line_2',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name' => __('neighborhood', "paypal-brasil-para-woocommerce"),
				'key' => 'neighborhood',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name' => __('phone', "paypal-brasil-para-woocommerce"),
				'key' => 'phone',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle()
	{
		try {

			$validation = $this->validate_input_data();

			if (!$validation['success']) {
				$this->send_error_response(
					__('Some fields are missing to initiate the payment.', 'paypal-brasil'),
					array(
						'errors' => $validation['errors']
					)
				);
			}

			$posted_data = $validation['data'];

			// Get the wanted gateway.
			$gateway = $this->get_paypal_gateway('paypal-brasil-bcdc-gateway');

			// Force to calculate cart.
			WC()->cart->calculate_totals();

			// Store cart.
			$cart = WC()->cart;

			// Check if there is anything on cart.
			if (!$cart->get_totals()['total']) {
				$this->send_error_response(__('You cannot pay for an empty order.', "paypal-brasil-para-woocommerce"));
			}

			$wc_cart = WC()->cart;
			$wc_cart_totals = new WC_Cart_Totals($wc_cart);
			$cart_totals = $wc_cart_totals->get_totals(true);

			$data = array(
				'purchase_units' => array(
					array(
						'items' => array(
							array(
								'name' => sprintf(__('Store order %s', "paypal-brasil-para-woocommerce"),
									get_bloginfo('name')),
								'unit_amount' => array(
									'currency_code' => get_woocommerce_currency(),
									'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'] - $cart_totals['shipping_total'])),
								),
								'quantity' => 1,
								'sku' => 'order-items',
							),
						),
						'amount' => array(
							'currency_code' => get_woocommerce_currency(),
							'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'])),
							'breakdown' => array(
								'item_total' => array(
									'currency_code' => get_woocommerce_currency(),
									'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'] - $cart_totals['shipping_total']))
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
				'payment_source' => array(
					'paypal' => array(
						'experience_context' => array(
							'return_url' => home_url(),
							'cancel_url' => home_url(),
						),
						'user_action' => 'CONTINUE'
					),
				),
				'application_context' => array(
					'brand_name' => get_bloginfo('name'),
					'user_action' => 'CONTINUE',
				),
			);

			// Create the payment in API.
			$create_payment = $gateway->api->create_payment($data, array(), 'bcdc');

			// Get the response links.
			$links = $gateway->api->parse_links($create_payment['links']);

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
				'fields' => $this->get_fields(),
				'pay_id' => $create_payment['id'],
				'ec' => $token,
			);

			// Store the requested data in session.
			WC()->session->set('paypal_brasil_bcdc_data', $data);

			// Send success response with data.
			$this->send_success_response(__('Payment created successfully.', "paypal-brasil-para-woocommerce"), $data);
		} catch (Exception $ex) {
			$this->send_error_response($ex->getMessage());
		}
	}

	// CUSTOM VALIDATORS

	public function required_text($data, $key, $name)
	{
		if (!empty($data)) {
			return true;
		}

		return sprintf(__('The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce"),  $name);
	}

	public function required_country($data, $key, $name)
	{
		return $this->required_text($data, $key, $name);
	}

	public function required_state($data, $key, $name, $input)
	{
		$country = isset($input['country']) && !empty($input['country']) ? $input['country'] : '';
		$states = WC()->countries->get_states($country);

		if (!$states) {
			return true;
		}

		if (empty($data)) {
			
			return sprintf(__('The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce"), $name);
		} else if (!isset($states[$data])) {
			
			return sprintf(__('The field <strong>%s</strong> is invalid.', "paypal-brasil-para-woocommerce"),
			$name);
		}

		return true;
	}

	public function required_postcode($data, $key, $name)
	{
		return $this->required_text($data, $key, $name);
	}

	public function required_nonce($data, $key, $name)
	{
		if (wp_verify_nonce($data, 'paypal-brasil-checkout')) {
			return true;
		}
		
		return sprintf(__('The %s is invalid.', "paypal-brasil-para-woocommerce"),/* */ $name);
	}

}

new PayPal_Brasil_API_Bcdc_Checkout_Handler();
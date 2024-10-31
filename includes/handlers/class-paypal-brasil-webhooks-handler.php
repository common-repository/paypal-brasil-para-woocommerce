<?php

// Exit if not in WordPress.
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WC_PAYPAL_LOGGER')) {
	require_once plugin_dir_path(__FILE__) . '../class-wc-paypal-logger.php';
}
use Automattic\WooCommerce\Utilities\OrderUtil;

// Check if class already exists before create.
if (!class_exists('PayPal_Brasil_Webhooks_Handler')) {

	/**
	 * Class WC_PPP_Brasil_Webhooks_Handler.
	 */
	class PayPal_Brasil_Webhooks_Handler
	{

		private $gateway_id;

		/**
		 * @var PayPal_Brasil_Gateway
		 */
		private $gateway;

		/**
		 * WC_PPP_Brasil_Webhooks_Handler constructor.
		 *
		 * @param $gateway_id string
		 * @param $gateway PayPal_Brasil_Gateway
		 */
		public function __construct($gateway_id, $gateway)
		{
			$this->gateway_id = $gateway_id;
			$this->gateway = $gateway;
		}

		private function log($data)
		{
			if ($this->gateway) {
				WC_PAYPAL_LOGGER::log($data, $this->gateway_id);
			}
		}

		/**
		 * Handle the event.
		 *
		 * @param $event
		 *
		 * @throws Exception
		 */
		public function handle($event)
		{
			global $wpdb;

			
			$gateway_meta_keys = [
				"paypal-brasil-plus-gateway" => 'wc_ppp_brasil_sale_id',
				"paypal-brasil-spb-gateway" => 'paypal_brasil_sale_id',
				"paypal-brasil-bcdc-gateway" => 'wc_bcdc_brasil_sale_id'
			];


			$method_name = 'handle_process_' . str_replace('.', '_', strtolower($event['event_type']));
			$method_name = str_replace('-', '_', strtolower($method_name));
			$this->log('Handling process method: ' . $method_name);
			if (method_exists($this, $method_name)) {
				$this->log('Method name: ' . $method_name);
				$resource_id = isset($event['resource']['sale_id']) ? $event['resource']['sale_id'] : $event['resource']['id'];
				$this->log('Resource ID: ' . $resource_id);


				if (OrderUtil::custom_orders_table_usage_is_enabled()) {

					if (isset($gateway_meta_keys[$this->gateway_id])) {
						$resource_meta_key = $gateway_meta_keys[$this->gateway_id];

						$order_query = new WC_Order_Query(
							array(
								'limit' => 1,
								'return' => 'ids',
								'meta_query' => array(
									array(
										'key' => $resource_meta_key,
										'value' => $resource_id,
										'compare' => '='
									)
								),
								'status' => array(
									'wc-pending',
									'wc-processing',
									'wc-on-hold',
									'wc-completed',
									'wc-cancelled',
									'wc-refunded',
									'wc-failed'
								)
							)
						);
					} else {
						$this->log('Invalid gateway ID: ' . $this->gateway_id);
						throw new Exception("Error on webhook handling");
					}


					$order_ids = $order_query->get_orders();


					// If found the order ID with this sale ID.
					if (!empty($order_ids)) {
						$order_id = $order_ids[0];
						$this->log('Order ID: ' . $order_id);
						$order = wc_get_order($order_id);

						$payment_method = !empty($order) ? $order->get_payment_method() : "";
						$this->log('Payment method: ' . $payment_method);

						//Checks if order found has the same order id as webhook.
						if ($order->get_meta($resource_meta_key) !== $resource_id) {
							$this->log('Resource ID mismatch');
							return;
						}

						// If is this gateway, process the order.
						if ($payment_method === $this->gateway_id) {
							$this->log('Processing webhook for payment method: ' . $payment_method);
							$this->{$method_name}($order, $event);
						} else {
							$this->log('Payment method not found: ' . $payment_method);
							
						}
					} else {
						$this->log('Order not found with this resource_id');
						throw new Exception("Order not found");
					}
				} else {
					$order_id_query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key IN ('paypal_brasil_id', 'wc_ppp_brasil_sale_id','wc_bcdc_brasil_sale_id') AND meta_value = %s", $resource_id);
					$this->log('Order ID query: ' . $order_id_query);
					$order_id = $wpdb->get_var($order_id_query);

					// If found the order ID with this sale ID.
					if (!empty($order_id)) {
						$this->log('Order ID: ' . $order_id);
						$order = wc_get_order($order_id);

						$payment_method = !empty($order) ? $order->get_payment_method() : "";
						$this->log('Payment method: ' . $payment_method);

						// If is this gateway, process the order.
						if ($payment_method === $this->gateway_id) {
							$this->log('Processing for payment method: ' . $payment_method);
							$this->{$method_name}($order, $event);
						} else {
							$this->log('Payment method not found: ' . $payment_method);
						}
					}
				}

			} else {
				throw new Exception('Invalid method to handle.');
			}
		}

		/**
		 * When payment is marked as completed.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_payment_sale_completed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing completed was not initiated because there is no order.');

				return;
			}

			$resource_id = isset($event['resource']['sale_id']) ? $event['resource']['sale_id'] : $event['resource']['id'];

			$this->log('Processing completed initiated.');
			// Check if the current status isn't processing or completed.
			if (
				!in_array($order->get_status(), array(
					'processing',
					'completed',
					'refunded',
					'cancelled'
				), true)
			) {
				$order->add_order_note(__('PayPal: Paid transaction.', "paypal-brasil-para-woocommerce"));
				$order->add_order_note(
					sprintf(
						__('Payment processed by PayPal. Transaction ID: <a href="%s" target="_blank" rel="noopener">%s</a>.', "paypal-brasil-para-woocommerce"),
						$this->gateway->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$resource_id}" : "https://www.paypal.com/activity/payment/{$resource_id}",
						$resource_id
					)
				);
				$order->payment_complete();
				$this->log('Processing completed finished.');
			}
		}

		/**
		 * When payment is denied.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_payment_sale_denied($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing denied was not initiated because there is no order.');

				return;
			}

			$this->log('Processing denied initiated.');
			// Check if the current status isn't failed.
			if (!in_array($order->get_status(), array('failed', 'completed', 'processing'), true)) {
				$order->update_status('failed', __('PayPal: The transaction was rejected by the card company or for fraud.', "paypal-brasil-para-woocommerce"));
				$this->log('Processing denied finished.');
			} else {
				$this->log('Processing denied did not change anything.');
			}
		}

		/**
		 * When payment is refunded.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_sale_refunded($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing refunded was not initiated because there is no order.');

				return;
			}

			$this->log('Processing refunded initiated.');

			// Check if is partial refund.
			$partial_refund = paypal_brasil_money_format($order->get_total() - $order->get_total_refunded()) !== paypal_brasil_money_format($event['resource']['amount']['total']);

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				// Check if is total refund
				if ($partial_refund) {
					$order->add_order_note(__('PayPal: The transaction was partially refunded.', "paypal-brasil-para-woocommerce"));
				} else {
					$order->update_status('refunded', __('PayPal: The transaction has been refunded in full.', "paypal-brasil-para-woocommerce"));
				}

				// Create the refund.
				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($event['resource']['amount']['total']),
						'reason' => $partial_refund ? __('PayPal: The transaction was partially refunded.', "paypal-brasil-para-woocommerce") : __('PayPal: transaction refunded in full.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error refunding.');
					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing refunded finished.');
			} else {
				$this->log('Processing refunded did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}

		/**
		 * When payment is reversed.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_sale_reversed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing reversed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing reversed initiated.');

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				$order->update_status('refunded', __('PayPal: The transaction has been rolled back.', "paypal-brasil-para-woocommerce"));

				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($order->get_total() - $order->get_total_refunded()),
						'reason' => __('PayPal: reversed transaction.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error reversing.');

					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing reversed finished.');

			} else {
				$this->log('Processing reversed did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}

		/**
		 * When payment is marked as completed.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_checkout_order_completed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing completed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing completed initiated.');
			// Check if the current status isn't processing or completed.
			if (
				!in_array($order->get_status(), array(
					'completed',
					'refunded',
					'cancelled'
				), true)
			) {
				$order->add_order_note(__('PayPal: Paid transaction.', "paypal-brasil-para-woocommerce"));
				$order->payment_complete();
				$this->log('Processing completed finished.');
			}
		}

		/**
		 * When payment is reversed.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_checkout_payment_approval_reversed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing reversed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing reversed initiated.');

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				$order->update_status('refunded', __('PayPal: The transaction has been rolled back.', "paypal-brasil-para-woocommerce"));

				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($order->get_total() - $order->get_total_refunded()),
						'reason' => __('PayPal: reversed transaction.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error reversing.');

					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing reversed finished.');

			} else {
				$this->log('Processing reversed did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}

		/**
		 * When payment is cancelled.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_order_cancelled($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing reversed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing reversed initiated.');

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				$order->update_status('refunded', __('PayPal: The transaction has been rolled back.', "paypal-brasil-para-woocommerce"));

				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($order->get_total() - $order->get_total_refunded()),
						'reason' => __('PayPal: reversed transaction.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error reversing.');

					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing reversed finished.');

			} else {
				$this->log('Processing reversed did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}


		/**
		 * When payment is marked as completed.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_payment_capture_completed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing completed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing completed initiated.');
			$resource_id = isset($event['resource']['sale_id']) ? $event['resource']['sale_id'] : $event['resource']['id'];
			// Check if the current status isn't processing or completed.
			if (
				!in_array($order->get_status(), array(
					'completed',
					'refunded',
					'cancelled'
				), true)
			) {
				$order->add_order_note(__('PayPal: Paid transaction.', "paypal-brasil-para-woocommerce"));
				$order->add_order_note(
					sprintf(
						__('Payment processed by PayPal. Transaction ID: <a href="%s" target="_blank" rel="noopener">%s</a>.', "paypal-brasil-para-woocommerce"),
						$this->gateway->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$resource_id}" : "https://www.paypal.com/activity/payment/{$resource_id}",
						$resource_id
					)
				);
				$order->payment_complete();
				$this->log('Processing completed finished.');
			}
		}



	}




}
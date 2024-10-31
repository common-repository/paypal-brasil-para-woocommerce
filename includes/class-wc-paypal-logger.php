<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Utilize WC logger class
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class WC_PAYPAL_LOGGER {
	/**
	 * Add a log entry.
	 *
	 * @param string $message Log message.
	 */
	public static function log( $message, $gateway_id ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		$options     = get_option( "woocommerce_{$gateway_id}_settings" );

		if ( empty( $options ) || ( isset( $options['debug'] ) && 'yes' !== $options['debug'] ) ) {
			return;
		}

		$logger = wc_get_logger();
		$context = array( 'source' => $gateway_id );

		$log_message  = PHP_EOL . '==== Paypal Brasil para woocommerce Version: ' . PAYPAL_PAYMENTS_VERSION . ' ====' . PHP_EOL;
		$log_message .= PHP_EOL;
		$log_message .= '=== Start Log ===' . PHP_EOL;
		$log_message .= $message . PHP_EOL;
		$log_message .= '=== End Log ===' . PHP_EOL;
		$log_message .= PHP_EOL;

		$logger->debug( $log_message, $context );
	}
}

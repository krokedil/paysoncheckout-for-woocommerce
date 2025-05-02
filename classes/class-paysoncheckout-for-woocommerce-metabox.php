<?php
/**
 * Handles metaboxes for Payson Checkout.
 *
 * @package Payson_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

use KrokedilPaysonDeps\Krokedil\WooCommerce\OrderMetabox;

/**
 * Payson_Metabox class.
 */
class PaysonCheckout_For_WooCommerce_Metabox extends OrderMetabox {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'payson-checkout-for-woocommerce', __( 'Payson order data', 'payson-checkout-for-woocommerce' ), 'payson-checkout-for-woocommerce' );

		add_action( 'init', array( $this, 'handle_sync_order_action' ), 9999 );

		$this->scripts[] = 'payson-checkout-metabox';
	}

	/**
	 * Render the metabox.
	 *
	 * @param WP_Post $post The WordPress post.
	 *
	 * @return void
	 */
	public function metabox_content( $post ) {
		// Get the WC Order from the post.
		$order = null;
		if ( is_a( $post, WC_Order::class ) ) {
			$order = $post;
		} else {
			$order = wc_get_order( $post->ID );
		}

		if ( ! $order ) {
			return;
		}

		$payson_order_id  = 'payson_order_id'; // $order->get_meta( '_qliro_one_order_id' );
		$payson_reference = 'payson_reference'; // $order->get_meta( '_qliro_one_merchant_reference' );
		$order_sync       = 'order_sync'; // $order->get_meta( '_qliro_order_sync_enabled' );

		$payson_order = QOC_WC()->api->get_qliro_one_admin_order( $payson_order_id );

		if ( is_wp_error( $payson_order ) ) {
			self::output_error( $payson_order->get_error_message() );
			return;
		}

		$last_transaction    = self::get_last_transaction( $payson_order['PaymentTransactions'] ?? array() );
		$transaction_type    = $last_transaction['Type'] ?? __( 'Not found', 'payson-checkout-for-woocommerce' );
		$transaction_status  = $last_transaction['Status'] ?? __( 'Order status was not found.', 'payson-checkout-for-woocommerce' );
		$order_sync_disabled = 'no' === $order_sync;

		self::output_info( __( 'Payment method', 'payson-checkout-for-woocommerce' ), self::get_payment_method_name( $order ), self::get_payment_method_subtype( $order ) );
		self::output_info( __( 'Order id', 'payson-checkout-for-woocommerce' ), $payson_order_id );
		self::output_info( __( 'Reference', 'payson-checkout-for-woocommerce' ), $payson_reference );
		self::output_info( __( 'Order status', 'payson-checkout-for-woocommerce' ), $transaction_type, $transaction_status );
		self::output_info( __( 'Total amount', 'payson-checkout-for-woocommerce' ), self::get_amount( $last_transaction ) );
		if ( $order_sync_disabled ) {
			self::output_info( __( 'Order synchronization', 'payson-checkout-for-woocommerce' ), __( 'Disabled', 'payson-checkout-for-woocommerce' ) );
		}
		echo '<br />';

		self::output_sync_order_button( $order, $payson_order, $last_transaction, $order_sync_disabled );
		self::output_collapsable_section( 'qliro-advanced', __( 'Advanced', 'payson-checkout-for-woocommerce' ), self::get_advanced_section_content( $order ) );
	}

	/**
	 * Maybe localize the script with data.
	 *
	 * @param string $handle The script handle.
	 *
	 * @return void
	 */
	public function maybe_localize_script( $handle ) {
		if ( 'payson-checkout-metabox' === $handle ) {
			$localize_data = array(
				'ajax'    => array(
					'setOrderSync' => array(
						'url'    => admin_url( 'admin-ajax.php' ),
						'action' => 'woocommerce_qliro_one_wc_set_order_sync',
						'nonce'  => wp_create_nonce( 'qliro_one_wc_set_order_sync' ),
					),
				),
				'orderId' => $this->get_id(),
			);
			wp_localize_script( 'payson-checkout-metabox', 'qliroMetaboxParams', $localize_data );
		}
	}

	/**
	 * Get the advanced section content.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	private static function get_advanced_section_content( $order ) {
		$order_sync = $order->get_meta( '_qliro_order_sync_enabled' );

		// Default the order sync to be enabled. Unset metadata is returned as a empty string.
		if ( empty( $order_sync ) ) {
			$order_sync = 'yes';
		}

		$title   = __( 'Order synchronization', 'payson-checkout-for-woocommerce' );
		$tip     = __( 'Disable this to turn off the automatic synchronization with the Payson Merchant Portal. When disabled, any changes in either system have to be done manually.', 'payson-checkout-for-woocommerce' );
		$enabled = 'yes' === $order_sync;

		ob_start();
		self::output_toggle_switch( $title, $enabled, $tip, 'qliro-toggle-order-sync', array( 'qliro-order-sync' => $order_sync ) );
		return ob_get_clean();
	}

	/**
	 * Handle the sync order action request.
	 *
	 * @return void
	 */
	public function handle_sync_order_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce           = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action          = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id        = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$payson_order_id = filter_input( INPUT_GET, 'qliro_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $action ) || empty( $order_id ) ) {
			return;
		}

		if ( 'qliro_one_sync_order' !== $action ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'qliro_one_sync_order' ) ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
			exit;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || $this->payment_method_id !== $order->get_payment_method() ) {
			return;
		}

		$response = QOC_WC()->api->om_update_qliro_one_order( $payson_order_id, $order_id );

		if ( is_wp_error( $response ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to sync order with Payson. Error: %s', 'payson-checkout-for-woocommerce' ),
					$response->get_error_message()
				)
			);
			return;
		}

		// Get the new payment transaction id from the response, and update the order meta with it.
		$transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'] ?? '';
		$order->update_meta_data( '_qliro_payment_transaction_id', $transaction_id );
		$order->save();

		$order->add_order_note(
			// translators: %s: new transaction id from Payson.
			sprintf( __( 'Order synced with Payson. Transaction ID: %s', 'payson-checkout-for-woocommerce' ), $transaction_id )
		);

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
	}

	/**
	 * Get the last transaction from a Payson Checkout order.
	 *
	 * @param array $transactions
	 *
	 * @return array
	 */
	private static function get_last_transaction( $transactions ) {
		// Sort the transactions based on the timestamp.
		usort(
			$transactions,
			function ( $a, $b ) {
				return strtotime( $a['Timestamp'] ?? '' ) - strtotime( $b['Timestamp'] ?? '' );
			}
		);

		// Get the last transaction.
		$last_transaction = end( $transactions );

		return $last_transaction;
	}

	/**
	 * Get the amount from the payment transaction.
	 *
	 * @param array $transaction
	 *
	 * @return string
	 */
	private static function get_amount( $transaction ) {
		$amount   = $transaction['Amount'] ?? '0';
		$currency = $transaction['Currency'] ?? '';
		$amount   = wc_price( $amount, array( 'currency' => $currency ) );

		return $amount;
	}

	/**
	 * Get the status of a Payson Checkout order from the payment transaction.
	 *
	 * @param array $transaction
	 *
	 * @return string
	 */
	private static function get_order_status( $transaction ) {
		// Get the status and type from the transaction.
		$status = $transaction['Status'];
		$type   = $transaction['Type'];

		return $type . wc_help_tip( $status );
	}

	/**
	 * Get the Payson payment method name.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	private static function get_payment_method_name( $order ) {
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );

		// Replace any _ with a space.
		$payment_method = str_replace( '_', ' ', $payment_method );

		// Return the method but ensure only the first letter is uppercase.
		return ucfirst( strtolower( $payment_method ) );
	}

	/**
	 * Get the subtype of the Payson payment method.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	private static function get_payment_method_subtype( $order ) {
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );
		$subtype        = $order->get_meta( 'qliro_one_payment_method_subtype_code' );

		// If the payment method starts with QLIRO_, it is a Payson Checkout payment method.
		if ( strpos( $payment_method, 'QLIRO_' ) === 0 ) {
			$payment_method = str_replace( 'QLIRO_', '', $payment_method );
			$subtype        = __( 'Payson payment method', 'payson-checkout-for-woocommerce' );
		}

		return $subtype;
	}

	/**
	 * Output the sync order action button.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $payson_order The Payson order.
	 * @param array    $last_transaction The last transaction from the Payson order.
	 *
	 * @return void
	 */
	private static function output_sync_order_button( $order, $payson_order, $last_transaction, $order_sync_disabled ) {
		$is_captured             = $order->get_meta( '_qliro_order_captured' );
		$is_cancelled            = $order->get_meta( '_qliro_order_cancelled' );
		$payment_method          = $order->get_meta( 'qliro_one_payment_method_name' );
		$last_transaction_amount = $last_transaction['Amount'] ?? 0;

		// Only output the sync button if the order is a Payson payment method order. Cant update card orders for example.
		if ( strpos( $payment_method, 'QLIRO_' ) !== 0 && strpos( $payment_method, 'TRUSTLY_' ) !== 0 ) {
			return;
		}

		// If the order is captured or cancelled, do not output the sync button.
		if ( $is_captured || $is_cancelled ) {
			return;
		}

		$query_args = array(
			'action'          => 'qliro_one_sync_order',
			'order_id'        => $order->get_id(),
			'payson_order_id' => $payson_order['OrderId'] ?? '',
		);

		$action_url = wp_nonce_url(
			add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) ),
			'payson_checkout_sync_order'
		);

		$classes = ( floatval( $order->get_total() ) === $last_transaction_amount ) ? 'button-secondary' : 'button-primary';

		if ( $order_sync_disabled ) {
			$classes .= ' disabled';
		}

		self::output_action_button(
			__( 'Sync order with Payson', 'payson-checkout-for-woocommerce' ),
			$action_url,
			false,
			$classes
		);
	}
}

<?php
/**
 * Admin Actions class file.
 *
 * @package PaysonCheckout/Classes
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class PaysonCheckout_For_WooCommerce_Admin_Actions {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_faulty_actions' ) );
	}

	/**
	 * Enqueue admin styles and scripts.
	 */
	public function enqueue_admin_scripts() {
		wp_register_style(
			'pco_admin',
			PAYSONCHECKOUT_URL . '/assets/css/pco_admin_style.css',
			array(),
			PAYSONCHECKOUT_VERSION
		);
		wp_enqueue_style( 'pco_admin' );

		wp_register_script(
			'pco_admin',
			PAYSONCHECKOUT_URL . '/assets/js/pco_admin.js',
			array( 'jquery' ),
			PAYSONCHECKOUT_VERSION,
			true
		);
		wp_enqueue_script( 'pco_admin' );
	}

	/**
	 * Display faulty scheduled actions related to a WooCommerce order.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function display_faulty_actions( $order ) {
		$order_id         = $order->get_id();
		$today            = new DateTime();
		$order_payment_id = $order->get_meta( '_payson_checkout_id' );

		$failed_actions = as_get_scheduled_actions(
			array(
				'status'     => ActionScheduler_Store::STATUS_FAILED,
				'hooks'      => array( 'pco_check_for_order' ),
				'meta_key'   => 'payment_id',
				'meta_value' => $order_payment_id,
				'before'     => ( clone $today )->modify( '-30 days' )->format( 'Y-m-d H:i:s' ),
				'per_page'   => 5,
			)
		);

		$pending_actions = as_get_scheduled_actions(
			array(
				'status'     => ActionScheduler_Store::STATUS_PENDING,
				'hooks'      => array( 'pco_check_for_order' ),
				'meta_key'   => 'payment_id',
				'meta_value' => $order_payment_id,
				'after'      => ( clone $today )->modify( '-24 hours' )->format( 'Y-m-d H:i:s' ),
				'before'     => ( clone $today )->modify( '-30 days' )->format( 'Y-m-d H:i:s' ),
				'per_page'   => 5,
			)
		);

		$faulty_actions = array_merge( $failed_actions, $pending_actions );
		$listed_actions = array();

		if ( ! empty( $faulty_actions ) ) {
			foreach ( $faulty_actions as $faulty_action ) {
				$days             = rand( 2, 29 );
				$sample_date      = ( clone $today )->modify( '-' . $days . ' days' );
				$listed_actions[] = array(
					'status' => 'Pending',
					'hook'   => 'pco_check_for_order',
					'date'   => $sample_date,
				);
			}
		}

		if ( ! empty( $listed_actions ) ) {
			?>
			<div class="faulty-actions">
				<p>This order has recently failed scheduled actions.</p>
				<a href="#failed-actions">View failed actions →</a>
				<ul class="faulty-actions__ul">
					<?php
					foreach ( $listed_actions as $action ) {
						$since = $action['date']->diff( $today )->days;
						?>
						<li>
							<strong>Status:</strong> <?php echo esc_html( $action['status'] ); ?><br>
							<strong>Hook:</strong> <?php echo esc_html( $action['hook'] ); ?><br>
							<strong>Created:</strong> <?php echo esc_html( $action['date']->format( 'Y-m-d H:i:s' ) ); ?><br>
							<strong>Issue:</strong> <?php echo esc_html( 'Has status pending since ' . $since . ' days ago.' ); ?>
						</li>
					<?php } ?>
				</ul>
			</div>
			<?php
		}
	}
}

new PaysonCheckout_For_WooCommerce_Admin_Actions();

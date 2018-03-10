<?php
/**
 * Payson Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package payson-checkout-for-woocommerce
 */

do_action( 'wc_payson_before_checkout_form' );
?>

<form name="checkout" class="checkout woocommerce-checkout">
	<div id="payson-wrapper">
		<div id="payson-order-review">
			<?php do_action( 'wc_payson_before_order_review' ); ?>
			<?php woocommerce_order_review(); ?>
			<?php do_action( 'wc_payson_after_order_review' ); ?>
		</div>

		<div id="payson-iframe">
			<?php do_action( 'wc_payson_before_snippet' ); ?>
			<?php wc_payson_show_snippet(); ?>
			<?php do_action( 'wc_payson_after_snippet' ); ?>
		</div>
	</div>
</form>

<?php do_action( 'wc_payson_after_checkout_form' ); ?>
<?php
/**
 * PaysonCheckout pay for order page.
 *
 * Overrides /checkout/form-pay.php.
 *
 * @package PaysonCheckout/Templates
 */

?>
<div id="pco-iframe">
	<?php do_action( 'pco_wc_before_snippet' ); ?>
	<?php pco_wc_show_pay_for_order_snippet(); ?>
	<?php do_action( 'pco_wc_after_snippet' ); ?>
</div>

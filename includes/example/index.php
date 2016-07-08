<?php

require_once 'config.php';

/*
 * Step 1: Set up details
 */

$callPaysonApi = new  PaysonEmbedded\PaysonApi($merchantId, $apiKey, $environment);
$paysonMerchant = new  PaysonEmbedded\Merchant($checkoutUri, $confirmationUri, $notificationUri, $termsUri, 1);

$payData = new  PaysonEmbedded\PayData(PaysonEmbedded\CurrencyCode::SEK);

$payData->AddOrderItem(new  PaysonEmbedded\OrderItem('Test product', 500, 1, 0.25, 'MD0', PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
$payData->AddOrderItem(new  PaysonEmbedded\OrderItem('discount', -20, 1, 0.1, 'a',PaysonEmbedded\OrderItemType::DISCOUNT));


$gui = new  PaysonEmbedded\Gui('sv', 'blue', 'none', 0);
$customer = new  PaysonEmbedded\Customer('Firstname', 'Lastname', 'test@test.com', 'Phone', '8765432100', 'City', 'Country', '99999', 'Street');
$checkout = new  PaysonEmbedded\Checkout($paysonMerchant, $payData, $gui,$customer); 

/*
 * Step 2 Create checkout
 */

$checkoutId = $callPaysonApi->CreateCheckout($checkout);

/*
 * Step 3 Get checkout object
 */

$checkout = $callPaysonApi->GetCheckout($checkoutId);

/*
 * Step 4 Print out checkout html
 */

print '<h1 style="text-align:center"> CheckoutId:'.$checkout->id.'</h1>';
print '<div style="width:100%;  margin-left:auto; margin-right:auto;">';
    echo $checkout->snippet; 
print "</div>";

?>
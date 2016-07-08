<?php

require_once 'config.php';

$callPaysonApi = new  PaysonEmbedded\PaysonApi($merchantId, $apiKey, $environment);
$paysonMerchant = new  PaysonEmbedded\Merchant($checkoutUri, $confirmationUri, $notificationUri, $termsUri, 1);
$payData = new  PaysonEmbedded\PayData(PaysonEmbedded\CurrencyCode::SEK);
$payData->AddOrderItem(new  PaysonEmbedded\OrderItem('Test product', 500, 1, 0.25, 'MD0'));


$customer = new  PaysonEmbedded\Customer('firstName', 'lastName', 'email', 'phone', 'identityNumber', 'city', 'country', 'postalCode', 'street');
$gui = new  PaysonEmbedded\Gui('sv', 'gray', 'none', 0);
$checkout = new  PaysonEmbedded\Checkout($paysonMerchant, $payData, $gui,$customer);

// Update the purchase with the checkoutID.
$checkoutId = $callPaysonApi->CreateCheckout($checkout);
$checkout = $callPaysonApi->GetCheckout($checkoutId);

$checkout->customer->email = 'test@test.com';
$checkout->customer->firstName = 'FirstName 2';
$checkout->customer->identityNumber = '99999999';
$checkout->gui->colorScheme = 'blue';
echo '<pre>';
print_r($checkout);
echo '</pre>';
$checkout = $callPaysonApi->UpdateCheckout($checkout);

print $checkout->snippet;




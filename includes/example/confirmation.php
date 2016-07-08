<?php

require_once 'config.php';

// Get the details about this purchase with the checkoutID.
$checkoutId = 'ENTER THE CHECKOUT ID';
$callPaysonApi = new  PaysonEmbedded\PaysonApi($merchantId, $apiKey, $environment);

$checkout = $callPaysonApi->GetCheckout($checkoutId);

if($checkout->status == 'canceled'){
    echo '<H3> canceled .... </H3>';
}elseif($checkout->status == 'readyToShip'){
     echo "Purchase has been completed <br /><h4>Details</h4><pre>" ;
    echo '<pre>';print_r($checkout);echo '</pre>';
}elseif($checkout->status == 'denied'){
    echo "The purchase is denied by any reason <br /><h4>Details</h4><pre>" ;
    echo '<pre>';print_r($checkout);echo '</pre>';
}else {
    echo '<H3>Something happened when .... </H3>';
}
?>
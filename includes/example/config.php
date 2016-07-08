<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
/*
 * Payson API Integration example for PHP (Payson Checkout 2.0)
 *
 * More information can be found att https://api.payson.se
 *
 */

/*
 * On every page you need to use the API you
 * need to include the file lib/paysonapi.php
 * from where you installed it.
 */
require_once '../lib/paysonapi.php';

/*
 * Account information. Below is all the variables needed to perform a purchase with
 * payson. Replace the placeholders with your actual information 
 */

// Your merchant ID and apikey. Information about the merchant and the integration.
$merchantId = "";
$apiKey = "";
$environment = true;
if ($environment == false) {
    //$environment = false;
    $merchantId = "ENTER YOUR MERCHANT ID";
    $apiKey     = "ENTER YOUR API KEY";
} else {
    $environment = true;
    $merchantId = "4";
    $apiKey = "2acab30d-fe50-426f-90d7-8c60a7eb31d4";
}

// URLs used by payson for redirection after a completed/canceled/notification purchase.
$checkoutUri     = "http://krokedilserver1.se/checkout/";
$confirmationUri = "http://krokedilserver1.se/checkout/order-received/";
$notificationUri = "http://my.local/phpAPI/example/notification.php";
$termsUri        = "http://my.local/phpAPI/example/terms.php";

?>
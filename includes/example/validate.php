<?php

require_once 'config.php';

/*
 * Step 1: Set up details
 */

$callPaysonApi = new  PaysonEmbedded\PaysonApi($merchantId, $apiKey, $environment);

/*
 * Step 2 Validate credentials
 */

try {
    $account = $callPaysonApi->Validate();
    print_r($account);
} catch(Exception $ex) {
    print "Wrong credentials";
}




?>
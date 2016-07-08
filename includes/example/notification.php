<?php
// Fetch the checkoutID that are returned
$checkoutID = $_GET['checkout'];
myFile('***** HELLO IPN *****');
myFile($checkoutID);

function myFile($arg, $arg2 = NULL) {  
 	    $myFile = "notificationFile.txt";  
 	    if($myFile == NULL){  
 	    	$myFile =  fopen($myFile, "w+");  
 	    	fwrite($fh, "\r\n".date("Y-m-d H:i:s")."Remove the file notificationFile.txt when you want. The file auto creates by ipn-call");     
 	    }  
 	    $fh = fopen($myFile, 'a') or die("can't open file");  
 	    fwrite($fh, "\r\n".date("Y-m-d H:i:s")." **");  
 	    fwrite($fh, $arg.'**');  
 	    fwrite($fh, $arg2);  
 	    fclose($fh);  
 }  
?>

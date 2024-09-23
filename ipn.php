<?php
// ipn.php

// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PayPal configuration
require_once 'gateway-config.php';

// Function to get shop configuration
function getShopConfig() {
    $configFile = 'shop_config.json';
    if (!file_exists($configFile)) {
        die("Shop configuration file not found.");
    }
    $json = file_get_contents($configFile);
    return json_decode($json, true) ?: [];
}

// Function to log IPN messages
function logIpnMessage($message) {
    file_put_contents('ipn_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Function to get user purchases
function getUserPurchases($email) {
    $purchasesFile = 'user_purchases.json';
    if (!file_exists($purchasesFile)) {
        file_put_contents($purchasesFile, json_encode([]));
        return [];
    }
    $purchases = json_decode(file_get_contents($purchasesFile), true);
    return $purchases[$email] ?? [];
}

// Function to save user purchases
function saveUserPurchase($email, $productId, $transactionId) {
    $purchasesFile = 'user_purchases.json';
    $purchases = json_decode(file_get_contents($purchasesFile), true) ?: [];
    
    if (!isset($purchases[$email])) {
        $purchases[$email] = [];
    }
    
    $purchases[$email][] = [
        'product_id' => $productId,
        'transaction_id' => $transactionId,
        'purchase_date' => date('Y-m-d H:i:s'),
        'status' => 'purchased'
    ];
    
    file_put_contents($purchasesFile, json_encode($purchases, JSON_PRETTY_PRINT));
}

// Main IPN handling
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
foreach ($raw_post_array as $keyval) {
    $keyval = explode('=', $keyval);
    if (count($keyval) == 2) {
        $myPost[$keyval[0]] = urldecode($keyval[1]);
    }
}

// Read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';
foreach ($myPost as $key => $value) {
    $value = urlencode($value);
    $req .= "&$key=$value";
}

// Send back to PayPal system to validate
$paypalUrl = PAYPAL_SANDBOX ? "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr" : "https://ipnpb.paypal.com/cgi-bin/webscr";
$ch = curl_init($paypalUrl);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
$res = curl_exec($ch);

if (!$res) {
    $errno = curl_errno($ch);
    $errstr = curl_error($ch);
    curl_close($ch);
    logIpnMessage("cURL error: [$errno] $errstr");
    die();
}

curl_close($ch);

// Inspect IPN validation result and act accordingly
if (strcmp($res, "VERIFIED") == 0) {
    // The IPN is verified, process it
    $item_name = $_POST['item_name'];
    $item_number = $_POST['item_number'];
    $payment_status = $_POST['payment_status'];
    $payment_amount = $_POST['mc_gross'];
    $payment_currency = $_POST['mc_currency'];
    $txn_id = $_POST['txn_id'];
    $receiver_email = $_POST['receiver_email'];
    $payer_email = $_POST['payer_email'];
    
    // Check that the payment status is Completed
    if ($payment_status == 'Completed') {
        // Check that txn_id has not been previously processed
        // Check that receiver_email is your Primary PayPal email
        // Check that payment_amount/payment_currency are correct
        // Process payment
        
        $shopConfig = getShopConfig();
        if ($receiver_email == $shopConfig['paypal_email']) {
            saveUserPurchase($payer_email, $item_number, $txn_id);
            logIpnMessage("Payment completed: $item_name purchased by $payer_email");
        } else {
            logIpnMessage("Receiver email mismatch: $receiver_email");
        }
    } else {
        logIpnMessage("Payment status not completed: $payment_status");
    }
} else if (strcmp($res, "INVALID") == 0) {
    // IPN invalid, log for manual investigation
    logIpnMessage("Invalid IPN: $req");
}
?>
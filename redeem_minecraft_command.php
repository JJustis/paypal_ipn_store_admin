<?php

session_start();

error_reporting(E_ALL);

ini_set('display_errors', 0);



require_once 'rcon.php';



define('PRODUCTS_FILE', 'products.json');

define('PURCHASES_FILE', 'purchases.json');

define('LOG_FILE', 'minecraft_redemption.log');



function log_message($message) {

    $timestamp = date('[Y-m-d H:i:s]');

    file_put_contents(LOG_FILE, "$timestamp $message\n", FILE_APPEND);

}



function load_json_file($file) {

    if (!file_exists($file)) {

        log_message("Error: File not found: $file");

        return null;

    }

    $content = file_get_contents($file);

    if ($content === false) {

        log_message("Error: Failed to read file: $file");

        return null;

    }

    $data = json_decode($content, true);

    if ($data === null) {

        log_message("Error: Failed to decode JSON from file: $file. Error: " . json_last_error_msg());

        return null;

    }

    return $data;

}



function save_json_file($file, $data) {

    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    if ($result === false) {

        log_message("Error: Failed to write to file: $file");

        return false;

    }

    return true;

}



function send_json_response($success, $message, $data = null) {

    $response = [

        'success' => $success,

        'message' => $message

    ];

    if ($data !== null) {

        $response['data'] = $data;

    }

    header('Content-Type: application/json');

    echo json_encode($response);

    exit;

}



log_message("Starting Minecraft command redemption process");

log_message("Session data: " . print_r($_SESSION, true));

log_message("POST data: " . print_r($_POST, true));



// Check if user is authenticated

if (!isset($_SESSION['user']) || !isset($_SESSION['user_email'])) {

    log_message("Error: User not authenticated");

    send_json_response(false, "User not authenticated");

}



// Validate input

$purchaseId = $_POST['purchase_id'] ?? '';

$productId = $_POST['product_id'] ?? '';

$playerName = $_POST['player_name'] ?? '';



if (empty($purchaseId) || empty($productId) || empty($playerName)) {

    log_message("Error: Missing required information");

    send_json_response(false, "Missing required information");

}



// Load purchases

$purchases = load_json_file(PURCHASES_FILE);

if ($purchases === null) {

    send_json_response(false, "Failed to load purchases data");

}



$userEmail = $_SESSION['user_email'];

$username = $_SESSION['user'];



log_message("User email: $userEmail");

log_message("Username: $username");



// Find user purchases

$userPurchases = $purchases[$userEmail] ?? $purchases[$username] ?? null;

if (!$userPurchases) {

    log_message("Error: No purchases found for user");

    send_json_response(false, "No purchases found for this user");

}



log_message("User purchases: " . print_r($userPurchases, true));



// Find specific purchase

$purchase = null;

foreach ($userPurchases as $p) {

    if ($p['id'] === $purchaseId && $p['product_id'] === $productId) {

        $purchase = $p;

        break;

    }

}



if (!$purchase) {

    log_message("Error: Purchase not found. Purchase ID: $purchaseId, Product ID: $productId");

    send_json_response(false, "Purchase not found");

}



log_message("Purchase found: " . print_r($purchase, true));



// Check if already redeemed

if (isset($purchase['redeemed']) && $purchase['redeemed']) {

    log_message("Error: Command already redeemed");

    send_json_response(false, "This command has already been redeemed");

}



// Load products

$products = load_json_file(PRODUCTS_FILE);

if ($products === null) {

    send_json_response(false, "Failed to load products data");

}



// Find specific product

$product = null;

foreach ($products as $p) {

    if ($p['id'] === $productId && $p['type'] === 'minecraft') {

        $product = $p;

        break;

    }

}



if (!$product) {

    log_message("Error: Minecraft product not found. Product ID: $productId");

    send_json_response(false, "Minecraft product not found");

}



log_message("Product found: " . print_r($product, true));

require_once 'encryption_functions.php';

// Connect to Minecraft server

try {
    $decryptedPassword = decryptRconPassword($product['rcon_password']);
    $rcon = new Thedudeguy\Rcon($product['server_ip'], $product['server_port'], $decryptedPassword, 3);
    if (!$rcon->connect()) {
        throw new Exception("Failed to connect to the Minecraft server");
    }



    // Execute command

    $command = str_replace('@p', $playerName, $product['command']);

    log_message("Executing command: $command");

    $response = $rcon->sendCommand($command);

    

    if ($response === false) {

        throw new Exception("Failed to execute command on the server");

    }



    log_message("Command executed successfully. Response: $response");



    // Mark as redeemed

    if (isset($purchases[$userEmail])) {

        foreach ($purchases[$userEmail] as &$p) {

            if ($p['id'] === $purchaseId) {

                $p['redeemed'] = true;

                break;

            }

        }

    } elseif (isset($purchases[$username])) {

        foreach ($purchases[$username] as &$p) {

            if ($p['id'] === $purchaseId) {

                $p['redeemed'] = true;

                break;

            }

        }

    }



    if (!save_json_file(PURCHASES_FILE, $purchases)) {

        throw new Exception("Failed to save updated purchase data");

    }



    log_message("Purchase marked as redeemed and saved");

    send_json_response(true, "Command executed successfully", ['response' => $response]);



} catch (Exception $e) {

    log_message("Error: " . $e->getMessage());

    log_message("Stack trace: " . $e->getTraceAsString());

    send_json_response(false, "An error occurred: " . $e->getMessage());

}

?>
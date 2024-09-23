<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'gateway-config.php';

define('PRODUCTS_FILE', 'products.json');
define('PURCHASES_FILE', 'purchases.json');
define('USERS_FILE', 'users.json');

$debugMessages = [];

function debug($message, $data = null) {
    global $debugMessages;
    $debugMessage = "DEBUG: $message\n";
    if ($data !== null) {
        $debugMessage .= print_r($data, true);
    }
    $debugMessages[] = $debugMessage;
}

function loadJsonFile($file) {
    if (!file_exists($file)) {
        debug("File not found", $file);
        return null;
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        debug("JSON decode error", json_last_error_msg());
        return null;
    }
    return $data;
}

function saveJsonFile($file, $data) {
    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    if ($result === false) {
        debug("Failed to write to file", $file);
        return false;
    }
    return true;
}

function getProductDetails($productId) {
    $products = loadJsonFile(PRODUCTS_FILE);
    if ($products === null) {
        debug("Failed to load products file");
        return null;
    }
    debug("All products", $products);
    foreach ($products as $product) {
        if ($product['id'] === $productId) {
            return $product;
        }
    }
    debug("Product not found", $productId);
    return null;
}

function recordPurchase($username, $productId, $transactionId) {
    $purchases = loadJsonFile(PURCHASES_FILE) ?: [];

    if (!isset($purchases[$username])) {
        $purchases[$username] = [];
    }

    $product = getProductDetails($productId);
    if (!$product) {
        debug("Product not found while recording purchase", $productId);
        return false;
    }

    $newPurchase = [
        'id' => uniqid($username . '_'),
        'product_id' => $productId,
        'product_name' => $product['name'],
        'transaction_id' => $transactionId,
        'price' => $product['price'],
        'date' => date('Y-m-d H:i:s'),
        'type' => $product['type'],
        'payment_status' => 'Completed'
    ];

    $purchases[$username][] = $newPurchase;
    return saveJsonFile(PURCHASES_FILE, $purchases);
}

debug("GET data", $_GET);
debug("Session data", $_SESSION);

$productId = $_GET['product_id'] ?? '';
$transactionId = $_GET['transaction_id'] ?? '';
$payerId = $_GET['PayerID'] ?? '';

if (empty($productId) || empty($transactionId) || empty($payerId)) {
    die("Error: Insufficient transaction information provided.");
}

$currentUser = $_SESSION['user'] ?? null;
debug("Current user from session", $currentUser);

if (!$currentUser) {
    $users = loadJsonFile(USERS_FILE);
    foreach ($users as $email => $userData) {
        if (isset($userData['pending_transaction']) && $userData['pending_transaction'] === $transactionId) {
            $currentUser = $email;
            $_SESSION['user'] = $email;
            break;
        }
    }
    debug("Identified user from pending transaction", $currentUser);
}

if (!$currentUser) {
    die("Error: Unable to identify user. Please log in and try again.");
}

$product = getProductDetails($productId);
debug("Product details", $product);

if (!$product) {
    die("Error: Product not found. Please contact support. Product ID: $productId");
}

$purchaseRecorded = recordPurchase($currentUser, $productId, $transactionId);
debug("Purchase recorded", $purchaseRecorded);

if (!$purchaseRecorded) {
    die("Error: Failed to record purchase. Please contact support.");
}

$users = loadJsonFile(USERS_FILE);
if (isset($users[$currentUser]['pending_transaction'])) {
    unset($users[$currentUser]['pending_transaction']);
    saveJsonFile(USERS_FILE, $users);
    debug("Cleared pending transaction for user", $currentUser);
}

debug("Final session data", $_SESSION);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Purchase Successful</h1>
        <div class="alert alert-success">
            Your purchase of "<?php echo htmlspecialchars($product['name']); ?>" was successful!
        </div>
        <p>Transaction ID: <?php echo htmlspecialchars($transactionId); ?></p>
        <p>Price: $<?php echo number_format($product['price'], 2); ?></p>
        <?php if ($product['type'] === 'digital'): ?>
            <p>You can download your digital product from your profile page.</p>
        <?php elseif ($product['type'] === 'physical'): ?>
            <p>Your physical product will be shipped to you soon. Check your profile for shipping updates.</p>
        <?php elseif ($product['type'] === 'minecraft'): ?>
            <p>Your Minecraft item will be available for redemption in your profile.</p>
        <?php endif; ?>
        <a href="index.php" class="btn btn-primary">Return to Shop</a>

        <!-- Debug Information Spoiler -->
        <div class="mt-5">
            <p>
                <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugInfo" aria-expanded="false" aria-controls="debugInfo">
                    Show Debug Information
                </button>
            </p>
            <div class="collapse" id="debugInfo">
                <div class="card card-body">
                    <pre><?php echo implode("\n\n", $debugMessages); ?></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
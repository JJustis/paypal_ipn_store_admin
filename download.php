<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'gateway-config.php';
define('PRODUCTS_FILE', 'products.json');
define('PURCHASES_FILE', 'purchases.json');

function loadJsonFile($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function getProductDetails($productId) {
    $products = loadJsonFile(PRODUCTS_FILE);
    foreach ($products as $product) {
        if ($product['id'] === $productId) {
            return $product;
        }
    }
    return null;
}

function getUserPurchases($username) {
    $purchases = loadJsonFile(PURCHASES_FILE);
    return $purchases[$username] ?? [];
}

function verifyPurchase($username, $productId) {
    $purchases = getUserPurchases($username);
    foreach ($purchases as $purchase) {
        if ($purchase['product_id'] === $productId && $purchase['payment_status'] === 'Completed') {
            return true;
        }
    }
    return false;
}

$productId = $_GET['id'] ?? '';
$transactionId = $_GET['transaction_id'] ?? '';
$username = $_SESSION['user'] ?? null;

if (empty($productId) || empty($transactionId) || !$username) {
    http_response_code(400);
    die("Error: Invalid request or user not logged in.");
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $productId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $transactionId)) {
    http_response_code(400);
    die("Error: Invalid product ID or transaction ID.");
}

if (!verifyPurchase($username, $productId)) {
    http_response_code(403);
    die("Error: You have not purchased this product or the purchase could not be verified.");
}

$product = getProductDetails($productId);

if (!$product || $product['type'] !== 'digital' || empty($product['file_path'])) {
    http_response_code(404);
    die("Error: Product not found or is not a digital product.");
}

$filePath = $product['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die("Error: File not found. Please contact support.");
}

$fileName = basename($filePath);
$fileSize = filesize($filePath);

header("Content-Type: application/octet-stream");
header("Content-Transfer-Encoding: Binary");
header("Content-Length: " . $fileSize);
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
readfile($filePath);
exit;
?>
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PRODUCTS_FILE', 'products.json');

function loadJsonFile($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$productId = $_GET['product_id'] ?? '';

if (empty($productId)) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$products = loadJsonFile(PRODUCTS_FILE);
$product = null;

foreach ($products as $p) {
    if ($p['id'] === $productId && $p['type'] === 'minecraft') {
        $product = $p;
        break;
    }
}

if ($product) {
    echo json_encode(['success' => true, 'command' => $product['command']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Minecraft product not found']);
}
?>
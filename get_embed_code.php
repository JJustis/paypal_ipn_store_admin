<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PRODUCTS_FILE', 'products.json');

function loadJsonFile($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

$productId = $_GET['product_id'] ?? '';

if (empty($productId)) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$products = loadJsonFile(PRODUCTS_FILE);
$product = null;

foreach ($products as $p) {
    if ($p['id'] === $productId) {
        $product = $p;
        break;
    }
}

if ($product) {
    $productUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/paypalipnhowto/item.php?id=' . urlencode($product['id']);
    $embedCode = '<a href="' . $productUrl . '" target="_blank" style="display:inline-block;text-decoration:none;color:inherit;">';
    $embedCode .= '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '" width="50" height="50" style="vertical-align:middle;margin-right:10px;">';
    $embedCode .= '<span style="display:inline-block;vertical-align:middle;">' . htmlspecialchars($product['name']) . '</span>';
    $embedCode .= '</a>';
    
    echo json_encode(['success' => true, 'embedCode' => $embedCode]);
} else {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
}
?>
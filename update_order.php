<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PURCHASES_FILE', 'purchases.json');
define('PRODUCTS_FILE', 'products.json');

function loadJsonFile($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function saveJsonFile($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Check if the admin is logged in
if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userEmail = $_POST['user_email'] ?? '';
    $productId = $_POST['product_id'] ?? '';

    $purchases = loadJsonFile(PURCHASES_FILE);
    $products = loadJsonFile(PRODUCTS_FILE);

    if (!isset($purchases[$userEmail])) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $userPurchases = &$purchases[$userEmail];
    $purchaseIndex = array_search($productId, array_column($userPurchases, 'product_id'));

    if ($purchaseIndex === false) {
        echo json_encode(['success' => false, 'message' => 'Purchase not found']);
        exit;
    }

    $purchase = &$userPurchases[$purchaseIndex];
    $product = null;
    foreach ($products as $p) {
        if ($p['id'] === $productId) {
            $product = $p;
            break;
        }
    }

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    switch ($action) {
        case 'update_order_status':
            $newStatus = $_POST['new_status'] ?? '';
            if (empty($newStatus)) {
                echo json_encode(['success' => false, 'message' => 'New status is required']);
                exit;
            }

            $purchase['status'] = $newStatus;

            if ($newStatus === 'shipped' && $product['type'] === 'physical') {
                $trackingNumber = $_POST['tracking_number'] ?? '';
                if (!empty($trackingNumber)) {
                    $purchase['tracking_number'] = $trackingNumber;
                }
            }

            if ($newStatus === 'paid') {
                $purchase['payment_status'] = 'Completed';
            }

            saveJsonFile(PURCHASES_FILE, $purchases);
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
            break;

        case 'update_tracking':
            if ($product['type'] !== 'physical') {
                echo json_encode(['success' => false, 'message' => 'Tracking number can only be updated for physical products']);
                exit;
            }

            $trackingNumber = $_POST['tracking_number'] ?? '';
            if (empty($trackingNumber)) {
                echo json_encode(['success' => false, 'message' => 'Tracking number is required']);
                exit;
            }

            $purchase['tracking_number'] = $trackingNumber;
            $purchase['status'] = 'shipped';

            saveJsonFile(PURCHASES_FILE, $purchases);
            echo json_encode(['success' => true, 'message' => 'Tracking number updated successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
}
?>
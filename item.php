<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('CONFIG_FILE', 'shop_config.json');
define('PRODUCTS_FILE', 'products.json');
define('USERS_FILE', 'users.json');

function loadJsonFile($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function saveJsonFile($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function getShopConfig() {
    return loadJsonFile(CONFIG_FILE);
}

function generatePayPalButton($product, $userEmail) {
    $shopConfig = getShopConfig();
    $paypalUrl = $shopConfig['paypal_sandbox'] ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
    $transactionId = 'txn_' . uniqid();
    $users = loadJsonFile(USERS_FILE);
    if (!isset($users[$userEmail])) {
        $users[$userEmail] = [];
    }
    $users[$userEmail]['pending_transaction'] = $transactionId;
    saveJsonFile(USERS_FILE, $users);
    $returnUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/paypalipnhowto/success.php?product_id=' . urlencode($product['id']) . '&transaction_id=' . urlencode($transactionId);
    $html = '<form action="' . $paypalUrl . '" method="post" target="_top">';
    $html .= '<input type="hidden" name="cmd" value="_xclick">';
    $html .= '<input type="hidden" name="business" value="' . $shopConfig['paypal_email'] . '">';
    $html .= '<input type="hidden" name="item_name" value="' . htmlspecialchars($product['name'] ?? 'Unknown Product') . '">';
    $html .= '<input type="hidden" name="item_number" value="' . htmlspecialchars($product['id'] ?? '') . '">';
    $html .= '<input type="hidden" name="amount" value="' . ($product['price'] ?? 0) . '">';
    $html .= '<input type="hidden" name="currency_code" value="' . $shopConfig['paypal_currency'] . '">';
    $html .= '<input type="hidden" name="return" value="' . $returnUrl . '">';
    $html .= '<input type="hidden" name="cancel_return" value="https://' . $_SERVER['HTTP_HOST'] . '/paypalipnhowto/cancel.php">';
    $html .= '<input type="hidden" name="notify_url" value="https://' . $_SERVER['HTTP_HOST'] . '/paypalipnhowto/ipn.php">';
    $html .= '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynow_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">';
    $html .= '<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">';
    $html .= '</form>';
    return $html;
}

function searchProducts($query) {
    $products = loadJsonFile(PRODUCTS_FILE);
    $results = [];
    foreach ($products as $product) {
        if (stripos($product['name'], $query) !== false) {
            $results[] = $product;
        }
    }
    return $results;
}

$productId = $_GET['id'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$products = loadJsonFile(PRODUCTS_FILE);
$product = null;

if ($searchQuery) {
    $searchResults = searchProducts($searchQuery);
} elseif ($productId) {
    foreach ($products as $p) {
        if ($p['id'] === $productId) {
            $product = $p;
            break;
        }
    }
}

$shopConfig = getShopConfig();
$currentUser = $_SESSION['user'] ?? null;
$userEmail = $_SESSION['user_email'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $searchQuery ? 'Search Results' : ($product ? htmlspecialchars($product['name']) : 'Our Shop'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card {
            height: 100%;
        }
        .product-card img {
            height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">Our Shop</a>
            <div class="navbar-nav ms-auto">
                <?php if ($currentUser): ?>
                    <span class="nav-item nav-link">Welcome, <?php echo htmlspecialchars($currentUser); ?></span>
                    <a class="nav-item nav-link" href="index.php">Back to Shop</a>
                <?php else: ?>
                    <a class="nav-item nav-link" href="index.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <form action="item.php" method="get" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search for products" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <?php if ($searchQuery): ?>
            <h2 class="mb-4">Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h2>
            <?php if (empty($searchResults)): ?>
                <div class="alert alert-info" role="alert">
                    No products found matching your search.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php foreach ($searchResults as $result): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <img src="<?php echo htmlspecialchars($result['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($result['name']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($result['name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars(substr($result['description'], 0, 100)) . '...'; ?></p>
                                    <p class="card-text"><strong>$<?php echo number_format($result['price'], 2); ?></strong></p>
                                    <a href="item.php?id=<?php echo urlencode($result['id']); ?>" class="btn btn-primary mt-auto">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($product): ?>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid rounded">
                </div>
                <div class="col-md-6">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="lead"><?php echo htmlspecialchars($product['description']); ?></p>
                    <p class="h3 text-primary mb-4">$<?php echo number_format($product['price'], 2); ?></p>
                    <?php if ($product['type'] === 'minecraft'): ?>
                        <div class="mb-3">
                            <label for="ign" class="form-label">Minecraft IGN:</label>
                            <input type="text" class="form-control" id="ign" name="ign" required>
                        </div>
                    <?php endif; ?>
                    <?php
                    if ($currentUser && $userEmail) {
                        echo generatePayPalButton($product, $userEmail);
                    } else {
                        echo '<a href="index.php" class="btn btn-primary btn-lg">Login to Purchase</a>';
                    }
                    ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                Welcome to our shop! Use the search bar above to find products or browse our categories.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ignInput = document.getElementById('ign');
            var paypalForm = document.querySelector('form[action^="https://www.paypal.com"]');
            
            if (ignInput && paypalForm) {
                paypalForm.addEventListener('submit', function(e) {
                    var ignValue = ignInput.value.trim();
                    if (ignValue === '') {
                        e.preventDefault();
                        alert('Please enter your Minecraft IGN before purchasing.');
                    } else {
                        var inputElement = document.createElement('input');
                        inputElement.type = 'hidden';
                        inputElement.name = 'custom';
                        inputElement.value = ignValue;
                        this.appendChild(inputElement);
                    }
                });
            }
        });
    </script>
</body>
</html>
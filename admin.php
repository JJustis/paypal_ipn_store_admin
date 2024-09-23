<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('CONFIG_FILE', 'shop_config.json');
define('PRODUCTS_FILE', 'products.json');
define('USERS_FILE', 'users.json');
define('PURCHASES_FILE', 'purchases.json');
define('UPLOADS_DIR', 'uploads/');

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

function getShopConfig() {
    $config = loadJsonFile(CONFIG_FILE);
    $defaultConfig = [
        'paypal_email' => '',
        'paypal_sandbox' => true,
        'paypal_currency' => 'USD',
    ];
    return array_merge($defaultConfig, $config);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$shopConfig = getShopConfig();
$users = loadJsonFile(USERS_FILE);
$isFirstUser = empty($users);
$isLoggedIn = isset($_SESSION['admin_user']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    require_once 'encryption_functions.php';
    switch ($action) {
        case 'register':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $errors = [];

            if (!validateEmail($email)) {
                $errors[] = "Invalid email address.";
            }
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long.";
            }

            if (empty($errors)) {
                $users[$email] = [
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'is_admin' => $isFirstUser
                ];
                saveJsonFile(USERS_FILE, $users);
                $_SESSION['admin_user'] = $email;
                $isLoggedIn = true;
                $successMessage = "Account created successfully!";
            }
            break;

        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            if (isset($users[$email]) && password_verify($password, $users[$email]['password'])) {
                if ($users[$email]['is_admin']) {
                    $_SESSION['admin_user'] = $email;
                    $isLoggedIn = true;
                } else {
                    $errors[] = "Access denied. Admin rights required.";
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
            break;

        case 'logout':
            unset($_SESSION['admin_user']);
            $isLoggedIn = false;
            break;

        case 'add_product':
            if ($isLoggedIn) {
                $newProduct = [
                    'id' => uniqid(),
                    'name' => $_POST['name'] ?? '',
                    'price' => floatval($_POST['price'] ?? 0),
                    'description' => $_POST['description'] ?? '',
                    'type' => $_POST['type'] ?? 'physical',
                    'image' => $_POST['image'] ?? ''
                ];
                
                if ($newProduct['type'] === 'digital') {
                    $targetDir = UPLOADS_DIR;
                    if (!file_exists($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    $targetFile = $targetDir . basename($_FILES["digitalFile"]["name"]);
                    $uploadOk = 1;
                    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                    
                    if ($_FILES["digitalFile"]["size"] > 500000000) {
                        $errors[] = "Sorry, your file is too large.";
                        $uploadOk = 0;
                    }
                    
                    if($fileType != "zip" && $fileType != "pdf" && $fileType != "doc" && $fileType != "docx") {
                        $errors[] = "Sorry, only ZIP, PDF, DOC & DOCX files are allowed.";
                        $uploadOk = 0;
                    }
                    
                    if ($uploadOk == 1) {
                        if (move_uploaded_file($_FILES["digitalFile"]["tmp_name"], $targetFile)) {
                            $newProduct['file_path'] = $targetFile;
                        } else {
                            $errors[] = "Sorry, there was an error uploading your file.";
                        }
                    }
                } elseif ($newProduct['type'] === 'minecraft') {
                    $newProduct['command'] = $_POST['command'] ?? '';
                    $newProduct['server_ip'] = $_POST['server_ip'] ?? '';
                    $newProduct['server_port'] = intval($_POST['server_port'] ?? 25565);
                    $newProduct['rcon_password'] = encryptRconPassword($_POST['rcon_password'] ?? '');
                }
                
                if (empty($errors)) {
                    $products = loadJsonFile(PRODUCTS_FILE);
                    $products[] = $newProduct;
                    saveJsonFile(PRODUCTS_FILE, $products);
                    $successMessage = "Product added successfully!";
                }
            }
            break;

        case 'delete_product':
            if ($isLoggedIn && isset($_POST['id'])) {
                $products = loadJsonFile(PRODUCTS_FILE);
                $products = array_filter($products, function($product) {
                    return $product['id'] !== $_POST['id'];
                });
                saveJsonFile(PRODUCTS_FILE, array_values($products));
                $successMessage = "Product deleted successfully!";
            }
            break;

        case 'update_order_status':
            if ($isLoggedIn) {
                $purchases = loadJsonFile(PURCHASES_FILE);
                $userEmail = $_POST['user_email'] ?? '';
                $productId = $_POST['product_id'] ?? '';
                $newStatus = $_POST['new_status'] ?? '';
                $trackingNumber = $_POST['tracking_number'] ?? '';
                
                if (isset($purchases[$userEmail])) {
                    foreach ($purchases[$userEmail] as &$purchase) {
                        if ($purchase['product_id'] === $productId) {
                            $purchase['status'] = $newStatus;
                            if ($newStatus === 'shipped' && !empty($trackingNumber)) {
                                $purchase['tracking_number'] = $trackingNumber;
                            }
                            if ($newStatus === 'paid') {
                                $purchase['payment_status'] = 'Completed';
                            }
                            break;
                        }
                    }
                    saveJsonFile(PURCHASES_FILE, $purchases);
                    $successMessage = "Order status updated successfully!";
                } else {
                    $errors[] = "User or purchase not found.";
                }
            }
            break;

        case 'update_paypal_config':
            if ($isLoggedIn) {
                $shopConfig['paypal_email'] = $_POST['paypal_email'] ?? '';
                $shopConfig['paypal_sandbox'] = isset($_POST['paypal_sandbox']);
                $shopConfig['paypal_currency'] = $_POST['paypal_currency'] ?? 'USD';
                saveJsonFile(CONFIG_FILE, $shopConfig);
                $successMessage = "PayPal configuration updated successfully!";
            }
            break;
    }
}

$products = loadJsonFile(PRODUCTS_FILE);
$purchases = loadJsonFile(PURCHASES_FILE);

$totalProducts = count($products);
$totalOrders = array_sum(array_map('count', $purchases));
$revenue = 0;
foreach ($purchases as $userPurchases) {
    foreach ($userPurchases as $purchase) {
        $product = current(array_filter($products, function($p) use ($purchase) {
            return $p['id'] === $purchase['product_id'];
        }));
        $revenue += $product['price'] ?? 0;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body { padding-top: 60px; }
        .sidebar { position: fixed; top: 56px; bottom: 0; left: 0; z-index: 100; padding: 48px 0 0; box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1); }
        .sidebar-sticky { position: relative; top: 0; height: calc(100vh - 48px); padding-top: .5rem; overflow-x: hidden; overflow-y: auto; }
        .nav-link { font-weight: 500; color: #333; }
        .nav-link:hover { color: #007bff; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">E-commerce Admin</a>
            <?php if ($isLoggedIn): ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="btn btn-link nav-link">Logout</button>
                        </form>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <?php if ($isLoggedIn): ?>
                <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                    <div class="position-sticky pt-3">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="#dashboard">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#products">
                                    <i class="fas fa-box"></i> Products
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#orders">
                                    <i class="fas fa-shopping-cart"></i> Orders
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#paypal_config">
                                    <i class="fab fa-paypal"></i> PayPal Config
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
            <?php endif; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mt-3">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <?php if (!$isLoggedIn): ?>
                    <div class="card mt-5">
                        <div class="card-body">
                            <h2 class="card-title"><?php echo $isFirstUser ? 'Create Admin Account' : 'Login'; ?></h2>
                            <form method="POST">
                                <input type="hidden" name="action" value="<?php echo $isFirstUser ? 'register' : 'login'; ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary"><?php echo $isFirstUser ? 'Create Account' : 'Login'; ?></button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="dashboard" class="mt-4">
                        <h2>Dashboard</h2>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Products</h5>
                                        <p class="card-text display-4"><?php echo $totalProducts; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Orders</h5>
                                        <p class="card-text display-4"><?php echo $totalOrders; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Revenue</h5>
                                        <p class="card-text display-4">$<?php echo number_format($revenue, 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="products" class="mt-5">
                        <h2>Product Management</h2>
                        <form method="POST" enctype="multipart/form-data" class="mb-4">
                            <input type="hidden" name="action" value="add_product">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="name" placeholder="Product Name" required>
                                </div>
                                <div class="col-md-2">
								
								<input type="number" class="form-control" name="price" placeholder="Price" step="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="description" placeholder="Description" required>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-control" name="type" id="productType" required>
                                        <option value="physical">Physical</option>
                                        <option value="digital">Digital</option>
                                        <option value="minecraft">Minecraft Command</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="url" class="form-control" name="image" placeholder="Image URL" required>
                                </div>
                                <div class="col-md-3" id="digitalFileUpload" style="display:none;">
                                    <input type="file" class="form-control" name="digitalFile" accept=".zip,.pdf,.doc,.docx">
                                </div>
                                <div id="minecraftFields" style="display:none;">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="command" placeholder="Minecraft Command">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="server_ip" placeholder="Server IP">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control" name="server_port" placeholder="Server Port">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="password" class="form-control" name="rcon_password" placeholder="RCON Password">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">Add Product</button>
                                </div>
                            </div>
                        </form>

                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" width="50" height="50"></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($product['description']); ?></td>
                                        <td><?php echo htmlspecialchars($product['type']); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="orders" class="mt-5">
                        <h2>Order Management</h2>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User Email</th>
                                    <th>Product</th>
                                    <th>Purchase Date</th>
                                    <th>Status</th>
                                    <th>Tracking Number / Command</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $email => $userPurchases): ?>
                                    <?php foreach ($userPurchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($email); ?></td>
                                            <td>
                                                <?php
                                                $product = current(array_filter($products, function($p) use ($purchase) {
                                                    return $p['id'] === $purchase['product_id'];
                                                }));
                                                echo htmlspecialchars($product['name'] ?? 'Unknown Product');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($purchase['date']); ?></td>
                                            <td><?php echo htmlspecialchars($purchase['status'] ?? 'pending'); ?></td>
                                            <td>
                                                <?php if ($product['type'] === 'physical'): ?>
                                                    <input type="text" class="form-control tracking-number" 
                                                           data-email="<?php echo htmlspecialchars($email); ?>"
                                                           data-product-id="<?php echo htmlspecialchars($purchase['product_id']); ?>"
                                                           value="<?php echo htmlspecialchars($purchase['tracking_number'] ?? ''); ?>"
                                                           placeholder="Enter tracking number">
                                                <?php elseif ($product['type'] === 'minecraft'): ?>
                                                    <?php echo htmlspecialchars($product['command'] ?? 'N/A'); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <select class="form-control update-status"
                                                        data-email="<?php echo htmlspecialchars($email); ?>"
                                                        data-product-id="<?php echo htmlspecialchars($purchase['product_id']); ?>">
                                                    <option value="pending" <?php echo ($purchase['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo ($purchase['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="shipped" <?php echo ($purchase['status'] ?? '') === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo ($purchase['status'] ?? '') === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="paid" <?php echo ($purchase['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="paypal_config" class="mt-5">
                        <h2>PayPal Configuration</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_paypal_config">
                            <div class="mb-3">
                                <label for="paypal_email" class="form-label">PayPal Business Email</label>
                                <input type="email" class="form-control" id="paypal_email" name="paypal_email" value="<?php echo htmlspecialchars($shopConfig['paypal_email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="paypal_sandbox" name="paypal_sandbox" <?php echo ($shopConfig['paypal_sandbox'] ?? false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="paypal_sandbox">Use PayPal Sandbox (for testing)</label>
                            </div>
                            <div class="mb-3">
                                <label for="paypal_currency" class="form-label">Currency</label>
                                <input type="text" class="form-control" id="paypal_currency" name="paypal_currency" value="<?php echo htmlspecialchars($shopConfig['paypal_currency'] ?? 'USD'); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update PayPal Configuration</button>
                        </form>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var productType = document.getElementById('productType');
            var digitalFileUpload = document.getElementById('digitalFileUpload');
            var minecraftFields = document.getElementById('minecraftFields');

            if (productType && digitalFileUpload && minecraftFields) {
                productType.addEventListener('change', function() {
                    if (this.value === 'digital') {
                        digitalFileUpload.style.display = 'block';
                        minecraftFields.style.display = 'none';
                    } else if (this.value === 'minecraft') {
                        digitalFileUpload.style.display = 'none';
                        minecraftFields.style.display = 'block';
                    } else {
                        digitalFileUpload.style.display = 'none';
                        minecraftFields.style.display = 'none';
                    }
                });
            }

            // Update order status
            var updateStatusSelects = document.querySelectorAll('.update-status');
            updateStatusSelects.forEach(function(select) {
                select.addEventListener('change', function() {
                    var email = this.getAttribute('data-email');
                    var productId = this.getAttribute('data-product-id');
                    var newStatus = this.value;

                    fetch('update_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=update_order_status&user_email=' + encodeURIComponent(email) + '&product_id=' + encodeURIComponent(productId) + '&new_status=' + encodeURIComponent(newStatus)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Order status updated successfully');
                        } else {
                            alert('Failed to update order status');
                        }
                    });
                });
            });

            // Update tracking number
            var trackingInputs = document.querySelectorAll('.tracking-number');
            trackingInputs.forEach(function(input) {
                input.addEventListener('blur', function() {
                    var email = this.getAttribute('data-email');
                    var productId = this.getAttribute('data-product-id');
                    var trackingNumber = this.value;

                    fetch('update_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=update_tracking&user_email=' + encodeURIComponent(email) + '&product_id=' + encodeURIComponent(productId) + '&tracking_number=' + encodeURIComponent(trackingNumber)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Tracking number updated successfully');
                        } else {
                            alert('Failed to update tracking number');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
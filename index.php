<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('CONFIG_FILE', 'shop_config.json');
define('PRODUCTS_FILE', 'products.json');
define('USERS_FILE', 'users.json');
define('PURCHASES_FILE', 'purchases.json');

function loadJsonFile($file) {
    if (file_exists($file)) {
        $jsonData = file_get_contents($file);
        if ($jsonData !== false) {
            $data = json_decode($jsonData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            } else {
                error_log("Error decoding JSON file: " . json_last_error_msg() . " in file: " . $file);
                return [];
            }
        } else {
            error_log("Error reading JSON file: " . $file);
            return [];
        }
    } else {
        error_log("JSON file not found: " . $file);
        return [];
    }
}

function saveJsonFile($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function getShopConfig() {
    return loadJsonFile(CONFIG_FILE);
}

function getProducts() {
    return loadJsonFile(PRODUCTS_FILE);
}

function getUserData($username) {
    $users = loadJsonFile(USERS_FILE);
    return $users[$username] ?? null;
}

function getUserPurchases($username) {
    $purchases = loadJsonFile(PURCHASES_FILE);
    return $purchases[$username] ?? [];
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

$shopConfig = getShopConfig();
$products = getProducts();
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $userData = getUserData($username);
            if ($userData && password_verify($password, $userData['password'])) {
                $_SESSION['user'] = $username;
                $_SESSION['user_email'] = $userData['email'];
                header('Location: index.php');
                exit;
            } else {
                $loginError = "Invalid username or password";
            }
        } elseif ($_POST['action'] === 'logout') {
            unset($_SESSION['user']);
            unset($_SESSION['user_email']);
            header('Location: index.php');
            exit;
        }
    }
}

$currentUser = $_SESSION['user'] ?? null;
$userPurchases = $currentUser ? getUserPurchases($currentUser) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real Shop with PayPal Integration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card { height: 100%; }
        .product-card .card-body { display: flex; flex-direction: column; }
        .product-card .card-text { flex-grow: 1; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">Real Shop</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
			    <form class="d-flex me-auto" action="item.php" method="get">
                    <input class="form-control me-2" type="search" placeholder="Search products" aria-label="Search" name="search">
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form>
                <ul class="navbar-nav ms-auto">
                    <?php if ($currentUser): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a>
                        </li>
                        <li class="nav-item">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="btn btn-link nav-link">Logout</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#registrationModal">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="mb-4">Welcome to Our Real Shop</h1>
        <?php if (empty($shopConfig['paypal_email'])): ?>
            <div class="alert alert-warning">PayPal email is not configured. Please set it up in the admin panel.</div>
        <?php elseif (empty($products)): ?>
            <div class="alert alert-info">No products available at the moment.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card product-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name'] ?? 'Unknown Product'); ?></h5>
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/400x600'); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Unknown Product'); ?>" class="img-fluid">
                                <p class="card-text"><?php echo htmlspecialchars($product['description'] ?? 'No description available'); ?></p>
                                <p class="card-text"><strong>Price: $<?php echo number_format($product['price'] ?? 0, 2); ?></strong></p>
                                <?php if ($product['type'] === 'minecraft'): ?>
                                    <div class="mb-3">
                                        <label for="ign_<?php echo $product['id']; ?>" class="form-label">Minecraft IGN:</label>
                                        <input type="text" class="form-control" id="ign_<?php echo $product['id']; ?>" name="ign" required>
                                    </div>
                                <?php endif; ?>
                                <?php
                                if ($currentUser) {
                                    echo generatePayPalButton($product, $currentUser);
                                } else {
                                    echo '<a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Login to Purchase</a>';
                                }
                                ?>
                                <button class="btn btn-secondary mt-2" onclick="showEmbedCode('<?php echo $product['id']; ?>')">Embed Code</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($loginError): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($loginError); ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label for="username" class="form-label">Email</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registrationModal" tabindex="-1" aria-labelledby="registrationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registrationModalLabel">Create an Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="registrationForm">
                        <div class="mb-3">
                            <label for="regUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="regUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="regEmail" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="regEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="regPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="regPassword" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="regConfirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="regConfirmPassword" name="confirmPassword" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Register</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <?php if ($currentUser): ?>
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4>Purchase History</h4>
                    <?php if (empty($userPurchases)): ?>
                        <p>No purchases found.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Date</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($userPurchases as $purchase): 
                                $product = getProductDetails($purchase['product_id']);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['date']); ?></td>
                                    <td>$<?php echo number_format($purchase['price'], 2); ?></td>
                                    <td>
                                        <?php if ($product['type'] === 'digital'): ?>
                                            <a href="download.php?id=<?php echo urlencode($purchase['product_id']); ?>&transaction_id=<?php echo urlencode($purchase['transaction_id']); ?>" class="btn btn-sm btn-primary">Download</a>
                                        <?php elseif ($product['type'] === 'physical'): ?>

<button class="btn btn-sm btn-info" onclick="showTracking('<?php echo htmlspecialchars($purchase['tracking_number'] ?? 'N/A'); ?>')">Tracking</button>
                                        <?php elseif ($product['type'] === 'minecraft'): ?>
                                            <button class="btn btn-sm btn-success btn-redeem-minecraft" 
                                                    data-purchase-id="<?php echo htmlspecialchars($purchase['id']); ?>" 
                                                    data-product-id="<?php echo htmlspecialchars($purchase['product_id']); ?>">
                                                Redeem
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Minecraft Redemption Modal -->
    <div class="modal fade" id="minecraftRedemptionModal" tabindex="-1" aria-labelledby="minecraftRedemptionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="minecraftRedemptionModalLabel">Redeem Minecraft Command</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="minecraftRedemptionForm">
                        <input type="hidden" id="purchaseId" name="purchase_id">
                        <input type="hidden" id="productId" name="product_id">
                        <div class="mb-3">
                            <label for="playerName" class="form-label">Enter your Minecraft player name:</label>
                            <input type="text" class="form-control" id="playerName" name="player_name" required>
                        </div>
                        <div class="mb-3">
                            <p>Command to be executed: <strong id="commandPreview"></strong></p>
                        </div>
                        <button type="submit" class="btn btn-primary">Redeem Command</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Embed Code Modal -->
    <div class="modal fade" id="embedCodeModal" tabindex="-1" aria-labelledby="embedCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="embedCodeModalLabel">Embed Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea id="embedCodeText" class="form-control" rows="5" readonly></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="copyEmbedCode()">Copy Code</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
 document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var username = document.getElementById('regUsername').value;
        var email = document.getElementById('regEmail').value;
        var password = document.getElementById('regPassword').value;
        var confirmPassword = document.getElementById('regConfirmPassword').value;

        // Client-side check for matching passwords
        if (password !== confirmPassword) {
            alert('Passwords do not match');
            return; // Prevent submission if passwords don't match
        }

        fetch('register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                email: email,
                password: password,
                confirm_password: confirmPassword
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Registration successful! Please log in.');
                var registrationModal = bootstrap.Modal.getInstance(document.getElementById('registrationModal'));
                registrationModal.hide();
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            } else {
                alert('Registration failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('An error occurred during registration. Please try again.');
        });
    });


        window.showTracking = function(trackingNumber) {
            alert('Tracking Number: ' + trackingNumber);
        };

        window.showMinecraftRedemption = function(purchaseId, productId) {
            document.getElementById('purchaseId').value = purchaseId;
            document.getElementById('productId').value = productId;
            fetch('get_minecraft_command.php?product_id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('commandPreview').textContent = data.command.replace(/@p/g, '________');
                        var modal = new bootstrap.Modal(document.getElementById('minecraftRedemptionModal'));
                        modal.show();
                    } else {
                        alert('Failed to retrieve command: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while retrieving the command.');
                });
        };

        document.getElementById('minecraftRedemptionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            fetch('redeem_minecraft_command.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Command redeemed successfully: ' + data.message);
                    var modal = bootstrap.Modal.getInstance(document.getElementById('minecraftRedemptionModal'));
                    modal.hide();
                } else {
                    alert('Failed to redeem command: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while redeeming the command.');
            });
        });

        window.showEmbedCode = function(productId) {
            fetch('get_embed_code.php?product_id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('embedCodeText').value = data.embedCode;
                        var modal = new bootstrap.Modal(document.getElementById('embedCodeModal'));
                        modal.show();
                    } else {
                        alert('Failed to generate embed code: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while generating the embed code.');
                });
        };

        window.copyEmbedCode = function() {
            var embedCodeText = document.getElementById('embedCodeText');
            embedCodeText.select();
            document.execCommand('copy');
            alert('Embed code copied to clipboard!');
        };

        // Add click event listeners to all redeem buttons
        document.querySelectorAll('.btn-redeem-minecraft').forEach(function(button) {
            button.addEventListener('click', function() {
                var purchaseId = this.getAttribute('data-purchase-id');
                var productId = this.getAttribute('data-product-id');
                showMinecraftRedemption(purchaseId, productId);
            });
        });

        <?php if ($loginError): ?>
        var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
        <?php endif; ?>
    });
    </script>
</body>
</html>
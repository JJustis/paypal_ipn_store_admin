<?php
session_start();
require_once 'gateway-config.php';

// Clear any pending transaction data
unset($_SESSION['transaction_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-warning">Payment Cancelled</h2>
                        <p>Your payment has been cancelled. No charges have been made to your account.</p>
                        <p>If you experienced any issues during the checkout process, please don't hesitate to contact our support team for assistance.</p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary me-2">Return to Shop</a>
                            <a href="contact.php" class="btn btn-secondary">Contact Support</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
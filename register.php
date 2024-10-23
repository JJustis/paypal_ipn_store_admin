<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('USERS_FILE', 'users.json');

// Load users from the JSON file
function loadJsonFile($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

// Save users to the JSON file
function saveJsonFile($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Check if the email is valid
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Load users
$users = loadJsonFile(USERS_FILE);

// Determine if this is the first user (admin)
$isFirstUser = empty($users);
$isLoggedIn = isset($_SESSION['admin_user']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $errors = [];

    // Validate email
    if (!validateEmail($email)) {
        $errors[] = "Invalid email address.";
    }

    // Check password length
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Confirm password match
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    if (isset($users[$email])) {
        $errors[] = "Email already exists.";
    }

    // If there are no errors, register the user
    if (empty($errors)) {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Add user to the array
        $users[$email] = [
            'password' => $hashedPassword,
            'is_admin' => $isFirstUser // First user gets admin rights
        ];

        // Save the updated users list to the JSON file
        saveJsonFile(USERS_FILE, $users);

        // Log the new user in and mark them as admin if necessary
        $_SESSION['admin_user'] = $email;
        $isLoggedIn = true;

        // Redirect to the admin page or a success page
        header("Location: admin_dashboard.php");
        exit;
    }
}
?>
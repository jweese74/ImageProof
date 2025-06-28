<?php
/**
 * index.php
 *
 * Entry point for Infinite Image Tools.  
 * Requires users to log in or create an account.
 * Once logged in, they are redirected to "image_actions.php".
 */

session_start();

// Load configuration (database connection, etc.)
$config = require __DIR__ . '/config.php';

// If user is already logged in, redirect to image_actions.php
if (isset($_SESSION['user_id'])) {
    header('Location: image_actions.php');
    exit;
}

// Initialise variables for error/success messages (for display in form)
$loginError = '';
$registrationError = '';
$registrationSuccess = '';

// Check if POST request was sent (user trying to log in or register)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Login
    if ($action === 'login') {
        $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
        $password        = trim($_POST['password'] ?? '');

        if ($usernameOrEmail && $password) {
            // Attempt to find user by username or email
            $stmt = $config['pdo']->prepare("
                SELECT user_id, username, email, password 
                  FROM users
                 WHERE username = :ue 
                    OR email = :ue
                LIMIT 1
            ");
            $stmt->execute(['ue' => $usernameOrEmail]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, log the user in
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['email']     = $user['email'];

                // Redirect to main actions page
                header('Location: image_actions.php');
                exit;
            } else {
                $loginError = 'Invalid credentials. Please try again.';
            }
        } else {
            $loginError = 'Please fill in both fields.';
        }
    }

    // Handle Registration
    elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username && $email && $password) {
            // 1. Check if username or email already exists
            $stmt = $config['pdo']->prepare("
                SELECT COUNT(*) as count
                  FROM users
                 WHERE username = :username
                    OR email = :email
            ");
            $stmt->execute([
                'username' => $username,
                'email'    => $email
            ]);
            $row = $stmt->fetch();

            if ((int)$row['count'] > 0) {
                $registrationError = 'Username or Email already exists.';
            } else {
                // 2. Insert new user
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $config['pdo']->prepare("
                    INSERT INTO users (username, email, password, user_role)
                    VALUES (:username, :email, :password, 'registered')
                ");

                $inserted = $insertStmt->execute([
                    'username' => $username,
                    'email'    => $email,
                    'password' => $hashedPassword
                ]);

                if ($inserted) {
                    $registrationSuccess = 'Registration successful! You may now log in.';
                } else {
                    $registrationError = 'Registration failed. Please try again.';
                }
            }
        } else {
            $registrationError = 'Please fill in all fields.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Infinite Image Tools - Login / Register</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1, h2 { text-align: center; }
        form { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 8px; box-sizing: border-box;
        }
        .error { color: red; }
        .success { color: green; }
        .submit { margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Infinite Image Tools</h1>
    <h2>Welcome! Please log in or create an account.</h2>

    <!-- Login Form -->
    <form method="POST" action="">
        <h3>Log In</h3>
        <?php if ($loginError): ?>
            <div class="error"><?php echo $loginError; ?></div>
        <?php endif; ?>
        <label for="username_or_email">Username or Email</label>
        <input type="text" name="username_or_email" id="username_or_email" required>

        <label for="password">Password</label>
        <input type="password" name="password" id="password" required>

        <input type="hidden" name="action" value="login">
        <button type="submit" class="submit">Log In</button>
    </form>

    <!-- Registration Form -->
    <form method="POST" action="">
        <h3>Register</h3>
        <?php if ($registrationError): ?>
            <div class="error"><?php echo $registrationError; ?></div>
        <?php endif; ?>
        <?php if ($registrationSuccess): ?>
            <div class="success"><?php echo $registrationSuccess; ?></div>
        <?php endif; ?>
        <label for="username">Choose a Username</label>
        <input type="text" name="username" id="username" required>

        <label for="email">Your Email Address</label>
        <input type="email" name="email" id="email" required>

        <label for="password">Choose a Password</label>
        <input type="password" name="password" id="password" required>

        <input type="hidden" name="action" value="register">
        <button type="submit" class="submit">Register</button>
    </form>

</div>
</body>
</html>

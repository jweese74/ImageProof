<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/config.php';
$next = $_GET['next'] ?? 'index.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $pwd = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT user_id,password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pwd, $u['password_hash'])) {
        $errors[] = 'Invalid e-mail or password.';
    } else {
        login_user($u['user_id']);
        header('Location: ' . $next);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Log in | PixlKey</title>
    <style>
        body {
            font-family: sans-serif;
            background: #111;
            color: #eee
        }

        form {
            max-width: 420px;
            margin: 4em auto;
            padding: 2em;
            background: #222;
            border-radius: 8px
        }

        input,
        button {
            width: 100%;
            padding: .6em;
            margin: .4em 0;
            background: #333;
            border: 1px solid #444;
            color: #eee
        }

        .error {
            color: #e74c3c
        }
    </style>
</head>

<body>
    <form method="post" novalidate>
        <h1>Sign in</h1>
        <?php if ($errors) : ?>
            <div class="error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next) ?>">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Log in</button>
        <p><a href="register.php">Need an account? Register</a></p>
    </form>
</body>

</html>
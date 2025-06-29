<?php
// Attempt to load environment variables from a `.env` file if present
$envPath = __DIR__ . '/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '') {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

// Database configuration using environment variables with sensible defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_NAME', getenv('DB_NAME') ?: 'infinite_image_tools');
define('DB_USER', getenv('DB_USER') ?: 'infinite_image_user');
define('DB_PASS', getenv('DB_PASS') ?: 'JASmine is D3ad!');

// Optional: Additional settings for error handling and debugging
define('DB_CHARSET', 'utf8mb4');         // Database charset
define('DB_DEBUG', filter_var(getenv('DB_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN));

// Global upload limit (MB)
define('MAX_UPLOAD_MB', getenv('MAX_UPLOAD_MB') ?: 200);

// Create a connection (using PDO)
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Enable exceptions for errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch data as associative arrays
    ]);
} catch (PDOException $e) {
    if (DB_DEBUG) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}
?>
<?php
// Database configuration

define('DB_HOST', 'localhost');          // Database host (e.g., IP or hostname)
define('DB_PORT', 3306);                 // Database port (default for MariaDB/MySQL)
define('DB_NAME', 'infinite_image_tools'); // Database name
define('DB_USER', 'infinite_image_user'); // Database username
define('DB_PASS', 'JASmine is D3ad!');  // Database password

// Optional: Additional settings for error handling and debugging
define('DB_CHARSET', 'utf8mb4');         // Database charset
define('DB_DEBUG', true);                // Enable debugging (set to false in production)

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
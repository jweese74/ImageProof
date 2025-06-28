<?php
/**
 * /config.php
 *
 * Central configuration file for Infinite Image Tools.
 *
 * Note: In a production environment, consider storing these credentials
 *       in environment variables or a secure vault service.
 */

/** Database Configuration */
$db_config = [
    'host'     => 'localhost',            // Database host
    'port'     => '3306',                 // Database port (3306 for MySQL/MariaDB)
    'dbname'   => 'infinite_image_tools', // Name of your database
    'username' => 'infinite_image_user',  // Database user
    'password' => 'infinite_image_pass',  // Database password
    'charset'  => 'utf8mb4'               // Character set
];

/** SMTP (Email) Configuration */
$smtp_config = [
    'host'       => 'smtp.mailtrap.io',    // Host of your SMTP server
    'port'       => 2525,                  // SMTP port (often 587 for TLS, 465 for SSL)
    'encryption' => 'tls',                 // Encryption method (tls or ssl)
    'username'   => 'YOUR_SMTP_USERNAME',  // SMTP user
    'password'   => 'YOUR_SMTP_PASSWORD',  // SMTP password
    'from_email' => 'noreply@yourdomain.com',
    'from_name'  => 'Infinite Image Tools'
];

/** Application-Wide Configuration */
$app_config = [
    'base_url'       => 'https://yourdomain.com/', // Used for generating links/URLs
    'debug_mode'     => true,                      // Toggle debugging output
    'upload_path'    => __DIR__ . '/uploads',      // File system path for uploads
    'log_path'       => __DIR__ . '/logs',         // Log directory
    'session_lifetime' => 3600                     // Session lifetime in seconds
];

/**
 * Optional: Create a PDO object for database connections
 *           (You can also do this in a separate script.)
 */
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                   $db_config['host'],
                   $db_config['port'],
                   $db_config['dbname'],
                   $db_config['charset']);

    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Handle connection errors here
    if ($app_config['debug_mode']) {
        echo 'Database connection failed: ' . $e->getMessage();
    }
    exit;
}

/**
 * Return all configurations (and the PDO object) in an array, or
 * use global variables as needed.
 */
return [
    'db_config'   => $db_config,
    'smtp_config' => $smtp_config,
    'app_config'  => $app_config,
    'pdo'         => $pdo
];

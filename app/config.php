<?php

/**
 * PixlKey – central configuration & PDO bootstrap.
 *
 * Sensitive values are pulled from environment variables that can be supplied
 * either by a `.env` file (loaded via php-dotenv) or by Apache SetEnv
 * directives.  Hard-coding secrets is no longer necessary.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Optional: load a .env file if one exists and you’re using Composer.
// Comment these three lines out if you’re not using php-dotenv yet.
// ---------------------------------------------------------------------
// require_once __DIR__ . '/vendor/autoload.php';
// if (class_exists(\Dotenv\Dotenv::class)) {
//     \Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();
// }

// ---- ENV → constants ------------------------------------------------
define('DB_HOST',  getenv('DB_HOST')  ?: 'localhost');
define('DB_PORT',  getenv('DB_PORT')  ?: '3306');
define('DB_NAME',  getenv('DB_NAME')  ?: 'infinite_image_tools');
define('DB_USER',  getenv('DB_USER')  ?: 'infinite_image_user');

// empty string is OK, but not recommended in production
// Note: DB_PASS is intentionally left empty here; set it in your .env or Apache
// config.  This allows you to use a different password in production without
// changing the codebase.
define('DB_PASS',  getenv('DB_PASS')  ?: ''); // empty string is OK, but not recommended in production

define('DB_DEBUG', filter_var(getenv('DB_DEBUG'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);

define('MAX_UPLOAD_MB', (int)(getenv('MAX_UPLOAD_MB') ?: 200));

// ---- Enforce PHP upload limits at runtime ---------------------------
@ini_set('upload_max_filesize', MAX_UPLOAD_MB . 'M');
@ini_set('post_max_size', (MAX_UPLOAD_MB + 10) . 'M'); // +10 MB head-room

// ---- PDO connection -------------------------------------------------
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // In dev you may wish to see the stack trace; in prod we log & die quietly.
    if (DB_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    error_log('PixlKey DB connection error: ' . $e->getMessage());
    http_response_code(500);
    die('Internal Server Error');
}

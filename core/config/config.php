<?php
/**
 * PixlKey – central configuration & PDO bootstrap
 * Location: /core/config/config.php
 *
 * This file sets up PDO, secure headers, HTTPS enforcement, and global constants
 * such as upload limits and rate-limiting thresholds.
 * Sensitive values are pulled from environment variables that can be supplied
 * either by a `.env` file (loaded via php-dotenv) or by Apache SetEnv
 * directives.  Hard-coding secrets is no longer necessary.
 */

declare(strict_types=1);

// ---- App Metadata ---------------------------------------------------
define('APP_VERSION', '0.5.0-Alpha');
define('APP_NAME', 'PixlKey');

// Rotating tagline pool
$taglines = [
    // 🔐 Serious / Professional
    'Proof of Vision',
    'Own Your Image',
    'Signature by Light',
    'Trust the Pixel',
    'The Artist\'s Ledger',
    'Verify. License. Protect.',
    'Chain of Creation',
    'Secured by Light, Delivered by Trust',
    'HTTPS or GTFO',
    'Image. Identity. Immutable.',
    'Every Image. Accounted For.',
    'Your Signature, Embedded',
    'Secure. Signed. Stored.',
    'Art That Knows Its Owner',
    'Authenticated Access, Evolving Securely',
    'Passwords that Age Like Wine—But Get Rehashed Anyway',
    'Cryptographic Confidence',
    'Provenance Made Portable',
    'Proof-of-Creation for the Visual Web',
    'Adaptive Defence, Seamless Creation',
    'Own What You Create',
    'Token Integrity Matters',
    'Immutable Identity, Every Session',


    // 🧠 Poetic / Mythic
    'Each Pixel Remembers',
    'Where Vision Becomes Record',
    'From Light to Ledger',
    'Imagination, Authenticated',
    'A Key for Every Vision',
    'What Was Seen, Now Sealed',
    'Proof of Muse',

    // 😂 Funny / Irreverent
    'Don\'t NFT Me, Bro',
    'Art So Secure, Even You Can’t Delete It',
    'Putting the “Pro” in Provenance',
    'Pixels with a Paper Trail',
    'Now Boarding: TLS Only. All Others Will Be Ejected.',
    'Your Pixels Called—They Demand Encrypted Transit.',
    'Because Metadata Is Sexy',
    'Rehash Responsibly™',
    'Rate-Limited: Because Bots Need Bedtime, Too.',
    'Proof of Work? More Like Proof of Art!',
    'Our Hashes Got an Upgrade—Your Uncle’s MD5 Didn’t',
    'Sign It Like You Meme It',
    'Crypto-ish, Without the Collapse',
    'Less Blockchain, More Brain Cells',
    'Your Art Called—It Wants Rights',
    'Now with 86% fewer replay attacks!',
    'Because stale tokens are for salad bars.',
    'The Only Fingerprint Artists Actually Want'
];


$chosenTagline = $taglines[array_rand($taglines)];
define('APP_TITLE', APP_NAME . ' ' . APP_VERSION . ' – ' . $chosenTagline);
define('APP_HEADER', APP_TITLE);

// ---- Transport Security Enforcement (global) -------------------------
if (
    php_sapi_name() !== 'cli' &&  // allow CLI scripts to skip HTTPS enforcement
    (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') &&
    (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')
) {
    http_response_code(403);
    exit('PixlKey requires a secure HTTPS connection.');
}

header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// ---------------------------------------------------------------------
// Optional: load a .env file if one exists and you’re using Composer.
// Comment these three lines out if you’re not using php-dotenv yet.
// ---------------------------------------------------------------------
require_once __DIR__ . '/../../vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../')->safeLoad();
}

// ---- ENV → constants ------------------------------------------------
define('DB_HOST',  getenv('DB_HOST')  ?: 'localhost');
define('DB_PORT',  getenv('DB_PORT')  ?: '3306');
define('DB_NAME',  getenv('DB_NAME')  ?: 'infinite_image_tools');
define('DB_USER',  getenv('DB_USER')  ?: 'infinite_image_user');
define('DB_PASS',  getenv('DB_PASS')  ?: 'JASmine is D3ad!');
define('DB_DEBUG', filter_var(getenv('DB_DEBUG'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);

define('MAX_UPLOAD_MB', (int)(getenv('MAX_UPLOAD_MB') ?: 200));

// ---- Rate Limiting Configuration -----------------------------------
// Default limits can be overridden by environment or .env
define('LOGIN_ATTEMPT_LIMIT',     (int)(getenv('LOGIN_ATTEMPT_LIMIT')     ?: 5));
define('LOGIN_DECAY_SECONDS',     (int)(getenv('LOGIN_DECAY_SECONDS')     ?: 900));  // 15 minutes

define('REGISTER_ATTEMPT_LIMIT',  (int)(getenv('REGISTER_ATTEMPT_LIMIT')  ?: 5));
define('REGISTER_DECAY_SECONDS',  (int)(getenv('REGISTER_DECAY_SECONDS')  ?: 1800)); // 30 minutes

define('DOWNLOAD_ATTEMPT_LIMIT',  (int)(getenv('DOWNLOAD_ATTEMPT_LIMIT')  ?: 10));
define('DOWNLOAD_DECAY_SECONDS',  (int)(getenv('DOWNLOAD_DECAY_SECONDS')  ?: 60));   // 1 minute

// Optional global toggle (future-proofing for IP/user-agent persistence)
define('RATE_LIMITING_ENABLED',   filter_var(getenv('RATE_LIMITING_ENABLED'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true);

// ---- Enforce PHP upload limits at runtime ---------------------------
 @ini_set('upload_max_filesize', MAX_UPLOAD_MB . 'M');
 @ini_set('post_max_size',       (MAX_UPLOAD_MB + 10) . 'M'); // +10 MB head-room

// ---- Enforce PHP upload limits at runtime ---------------------------
@ini_set('upload_max_filesize', MAX_UPLOAD_MB . 'M');
@ini_set('post_max_size',       (MAX_UPLOAD_MB + 10) . 'M'); // +10 MB head-room

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
    if (DB_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    error_log('PixlKey DB connection error: ' . $e->getMessage());
    http_response_code(500);
    die('Internal Server Error');
}
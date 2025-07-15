<?php
/**
 * PixlKey – central configuration & PDO bootstrap.
 *
 * Sensitive values are pulled from environment variables that can be supplied
 * either by a `.env` file (loaded via php-dotenv) or by Apache SetEnv
 * directives.  Hard-coding secrets is no longer necessary.
 */

declare(strict_types=1);

// ---- App Metadata ---------------------------------------------------
define('APP_VERSION', '0.4.6-beta');
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
    'Image. Identity. Immutable.',
    'Every Image. Accounted For.',
    'Your Signature, Embedded',
    'Secure. Signed. Stored.',
    'Art That Knows Its Owner',
    'Cryptographic Confidence',
    'Provenance Made Portable',
    'Proof-of-Creation for the Visual Web',
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
    'Because Metadata Is Sexy',
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
define('DB_PASS',  getenv('DB_PASS')  ?: 'JASmine is D3ad!');
define('DB_DEBUG', filter_var(getenv('DB_DEBUG'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);

define('MAX_UPLOAD_MB', (int)(getenv('MAX_UPLOAD_MB') ?: 200));

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
    // In dev you may wish to see the stack trace; in prod we log & die quietly.
    if (DB_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    error_log('PixlKey DB connection error: ' . $e->getMessage());
    http_response_code(500);
    die('Internal Server Error');
}
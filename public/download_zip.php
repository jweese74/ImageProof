<?php

/**
 * download_zip.php — Securely serves a processed ZIP archive to the authenticated user
 *
 * PixlKey Project – Beta 0.5.0
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Handles secure download of final image asset archives by validating session auth,
 * verifying user-runId ownership, and applying rate limiting to mitigate abuse.
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.2-alpha
 * @see        /core/process.php, /core/auth/rate_limiter.php, /processed/
 */
require_once __DIR__ . '/../core/auth/auth.php';
require_once __DIR__ . '/../core/session/SessionBootstrap.php';
require_once __DIR__ . '/../core/security/CsrfToken.php';

\PixlKey\Session\startSecureSession();
require_login();                       // ensure session + user

require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/auth/rate_limiter.php';

// Validate CSRF for GET/POST hybrid download (supports API header token)
\PixlKey\Security\validateToken();

// Get current user ID
$userId = current_user()['user_id'];

// Optional: construct per-IP+user+runId composite key for fine-grained throttling
$rateKey = 'zip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ':' . $userId . ':' . ($_GET['runId'] ?? '');

if (RATE_LIMITING_ENABLED && too_many_attempts($rateKey, DOWNLOAD_ATTEMPT_LIMIT, DOWNLOAD_DECAY_SECONDS)) {
    rate_limit_exceeded_response(DOWNLOAD_DECAY_SECONDS);
}

/* ----------------------------------------------------------------
   1.  Validate query string
----------------------------------------------------------------- */
if (!isset($_GET['runId'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing runId.';
    record_failed_attempt($rateKey);
    exit;
}

/** strip anything except safe URL chars (letters, numbers, _ -) */
$runId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['runId']);
if ($runId === '') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid runId.';
    record_failed_attempt($rateKey);
    exit;
}

// Verify that the runId belongs to the current user
$stmt = $pdo->prepare('SELECT 1 FROM processing_runs WHERE run_id = ? AND user_id = ?');
$stmt->execute([$runId, $userId]);
if (!$stmt->fetchColumn()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Unauthorized access.';
    exit;
}

/* ----------------------------------------------------------------
   2.  Build expected file path
----------------------------------------------------------------- */
$userId       = current_user()['user_id'];             // UUID from session
$processedDir = __DIR__ . '/../processed';

// Accept whatever .zip the run actually produced
$runDir  = $processedDir . '/' . $userId . '/' . $runId;
$hits    = glob($runDir . '/*.zip');
if (!$hits) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found.';
    record_failed_attempt($rateKey);
    exit;
}
$zipFile = $hits[0];                       // first (and only) archive

// Optional: verify writable ownership checks here
// Passed all checks: don't count as abuse
clear_failed_attempts($rateKey);

/* ----------------------------------------------------------------
   3.  Stream the archive
----------------------------------------------------------------- */
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
exit;

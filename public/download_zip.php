<?php

/**
 * download_zip.php
 * ---------------------------------------------------------------
 * Serve the ZIP archive created by process.php for the
 * currently-logged-in user and the requested runId.
 */

require_once __DIR__ . '/../core/auth/auth.php';
require_login();                       // ensure session + user

require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/auth/rate_limiter.php';

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
$zipFile      = $processedDir . '/' . $userId . '/' . $runId . '/final_assets.zip';

if (!file_exists($zipFile)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found.';
    record_failed_attempt($rateKey);
    exit;
}

// Passed all checks: don't count as abuse
clear_failed_attempts($rateKey);

/* ----------------------------------------------------------------
   3.  Stream the archive
----------------------------------------------------------------- */
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="final_assets.zip"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
exit;

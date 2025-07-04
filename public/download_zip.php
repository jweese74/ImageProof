<?php

/**
 * download_zip.php
 * ---------------------------------------------------------------
 * Serve the ZIP archive created by process.php for the
 * currently-logged-in user and the requested runId.
 */

require_once __DIR__ . '/../app/auth.php';
require_login();  // ensure session + user

require_once __DIR__ . '/../app/config.php';

$user = current_user();
$userId = $user['user_id'];

/* ----------------------------------------------------------------
   1.  Validate and sanitize runId
----------------------------------------------------------------- */
if (!isset($_GET['runId'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing runId.';
    exit;
}

$runId = $_GET['runId'];

// Strict UUID format (8-4-4-4-12 hex)
if (!preg_match('/^[a-f0-9\-]{36}$/', $runId)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid runId format.';
    exit;
}

/* ----------------------------------------------------------------
   2.  Ownership check against database
----------------------------------------------------------------- */
$stmt = $pdo->prepare(
    'SELECT zip_path
       FROM processing_runs
      WHERE run_id = :runId AND user_id = :userId
      LIMIT 1'
);
$stmt->execute(['runId' => $runId, 'userId' => $userId]);
$record = $stmt->fetch();

if (!$record || !is_readable($record['zip_path'])) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found or access denied.';
    exit;
}

/* ----------------------------------------------------------------
   3.  Stream the archive
----------------------------------------------------------------- */
$zipFile = $record['zip_path'];

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="final_assets.zip"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
exit;
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

/* ----------------------------------------------------------------
   1.  Validate query string
----------------------------------------------------------------- */
if (!isset($_GET['runId'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing runId.';
    exit;
}

/** strip anything except safe URL chars (letters, numbers, _ -) */
$runId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['runId']);
if ($runId === '') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid runId.';
    exit;
}

/* ----------------------------------------------------------------
   2.  Build expected file path
----------------------------------------------------------------- */
$userId = current_user()['user_id'];  // UUID from session
$processedDir = dirname(__DIR__) . '/processed';
$zipFile = $processedDir . '/' . $userId . '/' . $runId . '/final_assets.zip';

if (!file_exists($zipFile)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found.';
    exit;
}

/* ----------------------------------------------------------------
   3.  Stream the archive
----------------------------------------------------------------- */
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="final_assets.zip"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
exit;

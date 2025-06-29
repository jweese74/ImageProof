<?php
// download_zip.php
// Serve the final ZIP for a given runId, if it exists.
require_once 'auth.php';
require_login();                       //

require_once 'config.php';



$processedDir = __DIR__ . '/processed';

if (!isset($_GET['runId'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Missing runId.";
    exit;
}

// Sanitise runId to prevent directory traversal
$runId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['runId']);
if (empty($runId)) {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid runId.";
    exit;
}

$zipFile = $processedDir . '/' . $runId . '/final_assets.zip';
if (!file_exists($zipFile)) {
    header("HTTP/1.1 404 Not Found");
    echo "File not found.";
    exit;
}

// Serve it as a download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="final_assets.zip"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
exit;

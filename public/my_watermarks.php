<?php

/**
 * my_watermarks.php — Authenticated user dashboard for managing watermark images
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Handles secure upload, listing, deletion, and default-setting of watermark files (PNG, JPG, JPEG, WEBP)  
 * for authenticated users. Implements CSRF protection and per-user/IP rate limiting.  
 * Integrates with database to store and retrieve watermark metadata for use in downstream image processing.
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.0-beta
 * @see        /core/helpers/functions.php, /core/auth/rate_limiter.php, /core/config/config.php
 */

require_once __DIR__ . '/../core/auth/auth.php';
require_login();
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/auth/rate_limiter.php';
require_once __DIR__ . '/../core/helpers/functions.php';

/**
 * Watermark-specific rate-limit thresholds.
 * Falls back to download limits until dedicated values are added to config.php
 */
defined('WM_ATTEMPT_LIMIT')  || define('WM_ATTEMPT_LIMIT',  DOWNLOAD_ATTEMPT_LIMIT);
defined('WM_DECAY_SECONDS')  || define('WM_DECAY_SECONDS',  DOWNLOAD_DECAY_SECONDS);

$user       = current_user();
$userId     = $user['user_id'];
$uploadDir  = dirname(__DIR__) . "/watermarks/$userId/";
$errors     = [];
$messages   = [];

// Ensure per-user directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/* ------------------------------------------------------------------
   Handle POST actions
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limit watermark POST rate: IP+user scoped
    $rateKey = 'wm:' . $userId . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (RATE_LIMITING_ENABLED &&
        too_many_attempts($rateKey, WM_ATTEMPT_LIMIT, WM_DECAY_SECONDS)) {
        rate_limit_exceeded_response(WM_DECAY_SECONDS);
    }

    // Only record failed attempts for upload errors
    $recordFailure = false;
    validate_csrf_token();
    $action = $_POST['action'] ?? '';

    /* ---- upload ------------------------------------------------- */
    if ($action === 'upload') {
        if (!isset($_FILES['wm_file']) || $_FILES['wm_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'No file selected or upload error.';
            $recordFailure = true;
        } else {
            $ext = strtolower(pathinfo($_FILES['wm_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                $errors[] = 'Only PNG, JPG, JPEG or WEBP files allowed.';
                $recordFailure = true;
            } else {
                $newName  = uniqid('wm_') . '.' . $ext;
                $destAbs  = $uploadDir . $newName;
                if (move_uploaded_file($_FILES['wm_file']['tmp_name'], $destAbs)) {
                    $relPath  = "/watermarks/$userId/$newName";
                    /* first watermark? → mark as default */
                    $stmt = $pdo->prepare(
                        'SELECT 1 FROM watermarks WHERE user_id = ? AND is_default = 1'
                    );
                    $stmt->execute([$userId]);
                    // true  = first watermark for this user → make it default
                    $isDefault = ($stmt->fetch() === false);

                    $pdo->prepare(
                        'INSERT INTO watermarks (watermark_id,user_id,filename,path,is_default)
             VALUES (UUID(),?,?,?,?)'
                    )->execute([$userId, $newName, $relPath, $isDefault ? 1 : 0]);
                    $messages[] = 'Watermark uploaded.';
                } else {
                    $errors[] = 'Could not move uploaded file.';
                    $recordFailure = true;
                }
            }
        }

        // Log rate attempt on failure only
        if ($recordFailure && RATE_LIMITING_ENABLED) {
            record_failed_attempt($rateKey);
        }

        /* ---- set default ------------------------------------------- */
    } elseif ($action === 'set_default' && !empty($_POST['wm_id'])) {
        $wmId = $_POST['wm_id'];
        $pdo->prepare(
            'UPDATE watermarks SET is_default = 0 WHERE user_id = ?'
        )->execute([$userId]);
        $pdo->prepare(
            'UPDATE watermarks SET is_default = 1 WHERE watermark_id = ? AND user_id = ?'
        )->execute([$wmId, $userId]);
        $messages[] = 'Default watermark updated.';

        /* ---- delete ------------------------------------------------- */
    } elseif ($action === 'delete' && !empty($_POST['wm_id'])) {
        $wmId = $_POST['wm_id'];
        $row  = $pdo->prepare(
            'SELECT path FROM watermarks WHERE watermark_id = ? AND user_id = ?'
        );
        $row->execute([$wmId, $userId]);
        if ($r = $row->fetch()) {
            @unlink(dirname(__DIR__) . '/' . ltrim($r['path'], '/'));
        }
        $pdo->prepare(
            'DELETE FROM watermarks WHERE watermark_id = ? AND user_id = ?'
        )->execute([$wmId, $userId]);
        $messages[] = 'Watermark deleted.';
    }
}

/* ------------------------------------------------------------------
   Fetch watermarks for display
------------------------------------------------------------------ */
$watermarks = $pdo->prepare(
    'SELECT watermark_id,filename,path,is_default,uploaded_at
     FROM watermarks WHERE user_id = ? ORDER BY uploaded_at DESC'
);
$watermarks->execute([$userId]);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>My Watermarks</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px auto;
            max-width: 720px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem
        }

        th,
        td {
            padding: .5rem;
            border-bottom: 1px solid #ccc;
            text-align: left
        }

        img.thumb {
            max-height: 60px
        }

        .msg {
            color: #27ae60
        }

        .err {
            color: #c0392b
        }
    </style>
</head>

<body>

    <h1>My Watermarks</h1>
    <p><a href="/index.php">← back to uploader</a></p>

    <?php foreach ($messages as $m) {
        echo "<p class='msg'>" . htmlspecialchars($m) . "</p>";
    }
    foreach ($errors   as $e) {
        echo "<p class='err'>" . htmlspecialchars($e) . "</p>";
    } ?>

    <!-- upload form -->
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <label>Select PNG/JPEG/WEBP file:
            <input type="file" name="wm_file" required>
        </label>
        <button type="submit">Upload</button>
    </form>

    <!-- list -->
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>Filename</th>
                <th>Default</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($watermarks as $wm): ?>
                <tr>
                    <td><img src="<?= htmlspecialchars('/' . ltrim($wm['path'], '/')) ?>" class="thumb" alt=""></td>
                    <td><?= htmlspecialchars($wm['filename']) ?></td>
                    <td><?= $wm['is_default'] ? '✔' : '' ?></td>
                    <td>
                        <?php if (!$wm['is_default']): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="wm_id" value="<?= htmlspecialchars($wm['watermark_id']) ?>">
                                <button type="submit">Make default</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this watermark?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="wm_id" value="<?= htmlspecialchars($wm['watermark_id']) ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>

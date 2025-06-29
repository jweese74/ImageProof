<?php
// index.php

require_once 'auth.php';
require_login();
require_once 'config.php';
require_once 'functions.php';

$userId = current_user()['user_id'];

// ------------------------------------------------------------------
// Fetch watermark & licence options for the <select> controls
// ------------------------------------------------------------------
$watermarkOptions = $pdo->prepare(
    'SELECT watermark_id, filename, is_default
       FROM watermarks
      WHERE user_id = ?
   ORDER BY uploaded_at DESC'
);
$watermarkOptions->execute([$userId]);

$licenseOptions = $pdo->prepare(
    'SELECT license_id, name, is_default
       FROM licenses
      WHERE user_id = ?
   ORDER BY created_at DESC'
);
$licenseOptions->execute([$userId]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Infinite Muse Toolkit</title>
    <style>
        /* ---------- Core layout ---------- */
        html, body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #111;
            color: #eee;
        }
        h1 {
            text-align: center;
            margin-top: 30px;
        }
        img.header-image {
            display: block;
            margin: 20px auto 10px;
            max-width: 280px;
        }
        p.description {
            max-width: 80ch;
            margin: 20px auto;
            line-height: 1.6;
            text-align: justify;
        }

        /* ---------- Form styling ---------- */
        form {
            width: 80%;
            margin: 0 auto;
            border: 1px solid #444;
            padding: 20px;
            border-radius: 6px;
            background-color: #1a1a1a;
        }
        fieldset {
            border: 1px solid #555;
            border-radius: 4px;
            margin-bottom: 25px;
            padding: 15px;
        }
        legend {
            padding: 0 8px;
            font-weight: bold;
        }
        label {
            display: block;
            margin: 0.8rem auto 0.4rem;
            width: 90%;
            max-width: 90%;
        }
        input[type="text"],
        textarea,
        select,
        input[type="date"] {
            width: 90%;
            max-width: 90%;
            margin: 0 auto;
            display: block;
            padding: 8px;
            border: 1px solid #555;
            border-radius: 4px;
            background-color: #222;
            color: #eee;
            transition: border-color .3s ease, background-color .3s ease;
        }
        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            border-color: #888;
            background-color: #333;
            outline: none;
        }
        textarea { height: 120px; resize: vertical; }
        input[type="file"] { margin: 10px auto; display: block; color: #eee; }

        /* ---------- Preview images ---------- */
        img.preview           { max-width: 150px; margin: 10px auto; display: none; }
        /* watermark uses .preview; upload grid uses .preview-slot */
        .thumb-table          { margin: 10px auto; border-collapse: collapse; display: none; }
        .thumb-table td       { width: 120px; height: 120px; border: 1px solid #555; }
        .thumb-table img.preview-slot { width: 100%; height: auto; display: block; }

        /* ---------- Buttons ---------- */
        .button-group { text-align: center; margin-top: 30px; }
        button {
            padding: 10px 20px;
            background-color: #444;
            color: #fff;
            border: 1px solid #666;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color .3s ease, outline .3s ease;
            margin: 0 10px;
            font-size: 1em;
        }
        button:hover  { background-color: #666; }
        button:focus  { outline: 2px solid #888; outline-offset: 2px; }

        /* ---------- Misc ---------- */
        .faq-link   { display: block; margin: 1rem auto; text-align: center; font-weight: bold; }
        .highlight  { color: #d2b648; }
        p.notice,
        p.fine-print {
            font-size: .85em;
            text-align: center;
            color: #aaa;
            margin-top: 25px;
        }
        p.fine-print { color: #ccc; }
    </style>
</head>
<body>
<header>
    <img src="./watermarks/muse_signature_black.png"
         alt="Muse Signature" class="header-image">
</header>

<h1>Infinite Muse Toolkit</h1>

<a href="faq.html" target="_blank"
   title="Frequently Asked Questions" class="faq-link">Frequently Asked Questions</a>

<p class="description">
    Welcome to your ultimate tool for digital art management and protection! …
</p>

<nav style="text-align:right">
    <a href="my_watermarks.php">My Watermarks</a> |
    <a href="my_licenses.php">My Licences</a> |
    <a href="logout.php">Logout</a>
</nav>

<!-- =====================  MAIN UPLOAD FORM  ===================== -->
<form action="process.php" method="post" enctype="multipart/form-data" id="uploadForm">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(generate_csrf_token())?>">

    <!-- NEW: select watermark -->
    <label>Apply watermark:
        <select name="watermark_id">
            <option value="">— none —</option>
            <?php foreach($watermarkOptions as $opt): ?>
            <option value="<?=htmlspecialchars($opt['watermark_id'])?>"
                <?=$opt['is_default']?'selected':''?>>
                <?=htmlspecialchars($opt['filename'])?><?=$opt['is_default']?' (default)':''?>
            </option>
            <?php endforeach;?>
        </select>
        or&nbsp;upload&nbsp;<input type="file" name="watermark_upload" accept=".png,.jpg,.jpeg,.webp">
    </label>

    <!-- NEW: select licence -->
    <label>Attach licence:
        <select name="license_id">
            <option value="">— none —</option>
            <?php foreach($licenseOptions as $opt): ?>
            <option value="<?=htmlspecialchars($opt['license_id'])?>"
                <?=$opt['is_default']?'selected':''?>>
                <?=htmlspecialchars($opt['name'])?><?=$opt['is_default']?' (default)':''?>
            </option>
            <?php endforeach;?>
        </select>
    </label>

    <!-- existing image file chooser -->
    <input type="file" name="images[]" multiple required>
    …(rest of the existing preview-grid, buttons, footer)…
</form>

<p class="notice">
    All uploaded files are cleared daily at midnight (EST) for security and storage management.
</p>
<p class="fine-print">
    &copy; 2025&nbsp;Infinite Muse Arts<br>
    <img src="./watermarks/muse_signature_black.png"
         alt="Infinite Muse Logo" class="header-image" style="max-width:200px;">
</p>
</body>
</html>

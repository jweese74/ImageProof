<?php
// index.php

require_once 'auth.php';
require_login();                  // 🔒 session guard

require_once 'config.php';
require_once 'functions.php';
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

<form method="post" enctype="multipart/form-data">
    <!-- CSRF & upload limits -->
    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars(generate_csrf_token()) ?>" />
    <input type="hidden" name="MAX_FILE_SIZE" value="209715200" />

    <!-- ========================== Basic information ========================== -->
    <fieldset>
        <legend>Basic Information</legend>

        <label>
            Title of the Artwork <span class="required">*</span>
        </label>
        <input type="text" name="title" required placeholder="e.g. Starry Night" />

        <label>Description of the Artwork</label>
        <textarea name="description"
                  placeholder="Describe the theme or concept of your artwork…"></textarea>
    </fieldset>

    <!-- =========================== Watermark upload ========================== -->
    <fieldset>
        <legend>Watermark Image (optional)</legend>
        <input type="file" name="watermark_upload" accept=".png,.jpg,.jpeg,.webp" />
        <img src="" alt="Watermark Preview"
             class="preview" id="watermarkPreview">
        <script>
            const watermarkInput   = document.querySelector('input[name="watermark_upload"]');
            const watermarkPreview = document.getElementById('watermarkPreview');
            watermarkInput.addEventListener('change', evt => {
                const file = evt.target.files[0];
                if (!file) {
                    watermarkPreview.src = '';
                    watermarkPreview.style.display = 'none';
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => {
                    watermarkPreview.src = e.target.result;
                    watermarkPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            });
        </script>
    </fieldset>

    <!-- ======================= Artwork files to process ====================== -->
    <fieldset>
        <legend>Artwork Files to Process</legend>
        <p style="text-align:center;">Select the files to upload:</p>
        <input type="file" name="images[]" multiple required />

        <p class="highlight">Maximum individual file size: 200&nbsp;MB</p>

        <table id="thumbTable" class="thumb-table">
            <tbody>
                <tr>
                    <td><img class="preview-slot" alt="Preview 1" /></td>
                    <td><img class="preview-slot" alt="Preview 2" /></td>
                    <td><img class="preview-slot" alt="Preview 3" /></td>
                    <td><img class="preview-slot" alt="Preview 4" /></td>
                    <td><img class="preview-slot" alt="Preview 5" /></td>
                </tr>
                <tr>
                    <td><img class="preview-slot" alt="Preview 6" /></td>
                    <td><img class="preview-slot" alt="Preview 7" /></td>
                    <td><img class="preview-slot" alt="Preview 8" /></td>
                    <td><img class="preview-slot" alt="Preview 9" /></td>
                    <td><img class="preview-slot" alt="Preview 10" /></td>
                </tr>
            </tbody>
        </table>

        <script>
            const fileInput  = document.querySelector('input[name="images[]"]');
            const thumbTable = document.getElementById('thumbTable');
            const slots      = thumbTable.querySelectorAll('img.preview-slot');

            fileInput.addEventListener('change', evt => {
                slots.forEach(img => { img.src = ''; img.style.display = 'none'; });
                const files = Array.from(evt.target.files);

                if (files.length === 0) {
                    thumbTable.style.display = 'none';
                    return;
                }
                thumbTable.style.display = 'table';

                /* Show up to 10 previews */
                files.slice(0, slots.length).forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        slots[idx].src = e.target.result;
                        slots[idx].style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                });
            });
        </script>
    </fieldset>

    <!-- ============================== Buttons =============================== -->
    <div class="button-group">
        <button type="submit" name="submit" value="1">Process</button>
        <button type="reset">Clear</button>
    </div>
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

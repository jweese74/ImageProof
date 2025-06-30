<?php
// index.php  — unified public / member landing page
// --------------------------------------------------

require_once 'auth.php';
require_once 'config.php';
require_once 'functions.php';

$user      = current_user();                 // null when signed-out
$loggedIn  = $user !== null;
$thumbs    = [];

/* ------------------------------------------------------------------
   1.  Latest-thumbnail query
------------------------------------------------------------------ */
if ($loggedIn) {
    $stmt = $pdo->prepare(
        'SELECT thumbnail_path
           FROM images
          WHERE user_id = ?
       ORDER BY created_at DESC
          LIMIT 10'
    );
    $stmt->execute([$user['user_id']]);
    $thumbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $stmt   = $pdo->query(
        'SELECT thumbnail_path
           FROM images
       ORDER BY created_at DESC
          LIMIT 10'
    );
    $thumbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* ------------------------------------------------------------------
   2.  Helpers for the upload form
------------------------------------------------------------------ */
if ($loggedIn) {
    // now fetch `path` too so we can preview the watermark
    $watermarkOptions = $pdo->prepare(
        'SELECT watermark_id, filename, path, is_default
           FROM watermarks
          WHERE user_id = ?
       ORDER BY uploaded_at DESC'
    );
    $watermarkOptions->execute([$user['user_id']]);

    $licenseOptions = $pdo->prepare(
        'SELECT license_id, name, is_default
           FROM licenses
          WHERE user_id = ?
       ORDER BY created_at DESC'
    );
    $licenseOptions->execute([$user['user_id']]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Infinite Muse Toolkit</title>
    <style>
        /* ---------- core ---------- */
        html,
        body {
            margin: 0;
            padding: 0;
            font-family: Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #111;
            color: #eee
        }

        h1 {
            margin: 30px 0;
            text-align: center
        }

        img.header {
            display: block;
            margin: 20px auto 10px;
            max-width: 260px
        }

        /* ---------- thumb grid ---------- */
        .thumb-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            width: 90%;
            max-width: 900px;
            margin: 10px auto
        }

        .thumb-grid img {
            width: 100%;
            height: auto;
            border-radius: 4px;
            border: 1px solid #444
        }

        /* ---------- buttons ---------- */
        .big-btn {
            display: inline-block;
            padding: 14px 28px;
            margin: 20px 12px;
            font-size: 1.1em;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: #444;
            border: 1px solid #666;
            border-radius: 6px
        }

        .big-btn:hover {
            background: #666
        }

        /* ---------- nav ---------- */
        nav {
            text-align: center;
            margin: 10px auto 0
        }

        nav a {
            color: #d2b648;
            text-decoration: none;
            margin-left: 12px
        }

        nav a:hover {
            text-decoration: underline
        }

        /* ---------- form ---------- */
        form {
            width: 80%;
            max-width: 900px;
            margin: 30px auto;
            border: 1px solid #444;
            padding: 20px;
            border-radius: 6px;
            background: #1a1a1a
        }

        fieldset {
            border: 1px solid #555;
            border-radius: 4px;
            margin-bottom: 25px;
            padding: 15px
        }

        label {
            display: block;
            width: 90%;
            margin: .8rem auto .4rem
        }

        input[type=text],
        textarea,
        select,
        input[type=date] {
            width: 90%;
            margin: 0 auto;
            display: block;
            padding: 8px;
            border: 1px solid #555;
            border-radius: 4px;
            background: #222;
            color: #eee
        }

        input[type=file] {
            margin: 10px auto;
            display: block;
            color: #eee
        }

        button {
            padding: 10px 20px;
            background: #444;
            color: #fff;
            border: 1px solid #666;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 10px
        }

        button:hover {
            background: #666
        }

        /* ---------- live previews ---------- */
        .preview-box {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-top: 18px;
            flex-wrap: wrap
        }

        .preview-box figure {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0
        }

        .preview-box img {
            max-width: 180px;
            max-height: 180px;
            border: 1px solid #666;
            border-radius: 6px;
            background: #000
        }

        .preview-box figcaption {
            margin-top: 6px;
            font-size: .85em;
            color: #aaa;
            text-align: center
        }

        .notice,
        .fine-print {
            font-size: .85em;
            text-align: center;
            color: #aaa;
            margin-top: 25px
        }
    </style>
</head>

<body>

    <header><img src="./watermarks/muse_signature_black.png" alt="Muse signature" class="header"></header>
    <h1>Infinite Muse Toolkit</h1>

    <?php if ($loggedIn): ?>
        <!-- ===== MEMBER VIEW ===== -->
        <nav>
            <span>Welcome, <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></span>
            | <a href="my_watermarks.php">My Watermarks</a>
            | <a href="my_licenses.php">My Licences</a>
            | <a href="logout.php">Logout</a>
        </nav>

        <section class="thumb-grid">
            <?php if ($thumbs): foreach ($thumbs as $t): ?>
                    <img src="<?= htmlspecialchars($t) ?>" alt="recent thumbnail">
                <?php endforeach;
            else: ?>
                <p style="grid-column:1 / -1;text-align:center">No images yet – upload one below!</p>
            <?php endif; ?>
        </section>

        <div style="text-align:center;margin-top:10px">
            <a href="#uploadForm" class="big-btn">Process another image</a>
        </div>

        <!-- ===== UPLOAD FORM ===== -->
        <form action="process.php" method="post" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

            <!-- watermark select -->
            <label>Apply watermark:
                <select name="watermark_id" id="wmSelect">
                    <option value="" data-thumb="">— none —</option>
                    <?php foreach ($watermarkOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['watermark_id']) ?>"
                            data-thumb="<?= htmlspecialchars($opt['path']) ?>"
                            <?= $opt['is_default'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['filename']) ?>
                            <?= $opt['is_default'] ? ' (default)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                or <input type="file" name="watermark_upload" id="wmUpload" accept=".png,.jpg,.jpeg,.webp">
            </label>

            <!-- licence select -->
            <label>Attach licence:
                <select name="license_id">
                    <option value="">— none —</option>
                    <?php foreach ($licenseOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['license_id']) ?>"
                            <?= $opt['is_default'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['name']) ?>
                            <?= $opt['is_default'] ? ' (default)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- image chooser -->
            <label>Select images (max 200&nbsp;MB each):
                <input type="file" name="images[]" id="imgUpload" multiple required>
            </label>

            <!-- live previews -->
            <div class="preview-box">
                <figure>
                    <img id="wmPreview" src="" alt="Watermark preview">
                    <figcaption>Watermark</figcaption>
                </figure>
                <figure>
                    <img id="imgPreview" src="" alt="Image preview">
                    <figcaption>First Image</figcaption>
                </figure>
            </div>

            <div style="text-align:center;margin-top:18px">
                <button type="submit" name="submit" value="1">Start processing</button>
            </div>

            <!-- metadata (unchanged) -->
            <fieldset>
                <legend>Artwork Metadata</legend>
                <!-- … metadata inputs stay exactly as you had them … -->
                <?php /* metadata inputs omitted for brevity – no changes inside */ ?>
            </fieldset>
        </form>

    <?php else: ?>
        <!-- ===== PUBLIC VIEW ===== -->
        <section class="thumb-grid">
            <?php if ($thumbs): foreach ($thumbs as $t): ?>
                    <img src="<?= htmlspecialchars($t) ?>" alt="latest thumbnail">
                <?php endforeach;
            else: ?>
                <p style="grid-column:1 / -1;text-align:center">No images have been processed yet.</p>
            <?php endif; ?>
        </section>

        <div style="text-align:center">
            <a href="login.php" class="big-btn">Log in</a>
            <a href="register.php" class="big-btn">Register</a>
        </div>
    <?php endif; ?>

    <p class="notice">Original uploads are capped at 200&nbsp;MB. Thumbnails shown above refresh automatically.</p>
    <p class="fine-print">&copy; 2025 Infinite Muse Arts</p>

    <script>
        /* ----- live watermark preview ----- */
        const wmSelect = document.getElementById('wmSelect');
        const wmUpload = document.getElementById('wmUpload');
        const wmPrevImg = document.getElementById('wmPreview');

        /* saved watermark change */
        function updateSavedWm() {
            const opt = wmSelect.options[wmSelect.selectedIndex];
            const thumb = opt.dataset.thumb || '';
            if (thumb && !wmUpload.files.length) { // only when no custom file
                wmPrevImg.src = thumb;
            } else if (!wmUpload.files.length) {
                wmPrevImg.src = '';
            }
        }
        wmSelect.addEventListener('change', updateSavedWm);

        /* custom watermark upload */
        wmUpload.addEventListener('change', e => {
            if (!e.target.files.length) {
                updateSavedWm();
                return;
            }
            const file = e.target.files[0];
            const rdr = new FileReader();
            rdr.onload = ev => {
                wmPrevImg.src = ev.target.result;
            };
            rdr.readAsDataURL(file);
        });

        /* ----- live image preview (first selected) ----- */
        const imgUpload = document.getElementById('imgUpload');
        const imgPrevImg = document.getElementById('imgPreview');

        imgUpload.addEventListener('change', e => {
            if (!e.target.files.length) {
                imgPrevImg.src = '';
                return;
            }
            const file = e.target.files[0];
            const rdr = new FileReader();
            rdr.onload = ev => {
                imgPrevImg.src = ev.target.result;
            };
            rdr.readAsDataURL(file);
        });

        /* initialise previews on page load */
        updateSavedWm();
    </script>
</body>

</html>
<?php
// index.php  — unified public / member landing page
// --------------------------------------------------

require_once 'auth.php';
require_once 'config.php';
require_once 'functions.php';

$user      = current_user();           // null when signed-out :contentReference[oaicite:0]{index=0}
$loggedIn  = $user !== null;
$thumbs    = [];

/* ------------------------------------------------------------------
   1.  Latest-thumbnail query
   ---------------------------------------------------------------- */
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
    // site-wide 5×2 grid
    $stmt = $pdo->query(
        'SELECT thumbnail_path
           FROM images
       ORDER BY created_at DESC
          LIMIT 10'
    );
    $thumbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* ------------------------------------------------------------------
   2.  Member-only helpers (watermarks / licences) reused by upload form
   ---------------------------------------------------------------- */
if ($loggedIn) {
    /* include `path` so we can preview the saved watermark */
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
    <title>PixlKey 0.4.1-beta</title>
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

        /* ---------- form (members) ---------- */
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

        /* ---- preview thumbs ---- */
        .preview {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1rem 0;
        }

        .preview img {
            max-width: 140px;
            max-height: 140px;
            border: 1px solid #555;
            border-radius: 4px;
            background: #000;
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

        .notice,
        .fine-print {
            font-size: .85em;
            text-align: center;
            color: #aaa;
            margin-top: 25px
        }
    </style>

    <script>
        /**
         * Initialise previews once the DOM is ready
         */
        document.addEventListener('DOMContentLoaded', () => {
            // --- DOM handles ---
            const wmSelect = document.querySelector('select[name="watermark_id"]');
            const wmUpload = document.getElementById('watermark_upload');
            const wmPreview = document.getElementById('wmPreview');
            const imgChooser = document.querySelector('input[name="images[]"]');
            const imgPreview = document.getElementById('imgPreview');

            /** read a File → data-URL and show it */
            function fileToImg(file, imgEl) {
                if (!file) {
                    imgEl.removeAttribute('src');
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => {
                    imgEl.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }

            /** show the saved-watermark thumbnail selected in the <select> */
            function showWmFromSelect() {
                const opt = wmSelect?.options[wmSelect.selectedIndex];
                if (opt && opt.dataset.path) {
                    wmPreview.src = opt.dataset.path;
                } else {
                    wmPreview.removeAttribute('src');
                }
            }

            // live events
            wmSelect?.addEventListener('change', showWmFromSelect);
            wmUpload?.addEventListener('change', e => {
                fileToImg(e.target.files[0], wmPreview);
            });
            imgChooser?.addEventListener('change', e => {
                fileToImg(e.target.files[0], imgPreview);
            });

            // initial display on page load
            showWmFromSelect();
        });
    </script>

</head>

<body>

    <header>
        <img src="./watermarks/pixlkey2.png" alt="PixlKey Logo" class="header">
    </header>

    <h1>PixlKey 0.4.1-beta</h1>

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

        <!-- ===== UPLOAD FORM (unchanged except CSRF token) ===== -->
        <form action="process.php" method="post" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

            <!-- watermark select -->
            <label>Apply watermark:
                <select name="watermark_id">
                    <option value="">— none —</option>
                    <?php foreach ($watermarkOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['watermark_id']) ?>"
                            data-path="<?= htmlspecialchars($opt['path']) ?>"
                            <?= $opt['is_default'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['filename']) ?>
                            <?= $opt['is_default'] ? ' (default)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                or <input type="file" id="watermark_upload"
                    name="watermark_upload" accept=".png,.jpg,.jpeg,.webp">
            </label>

            <!-- live previews -->
            <div class="preview">
                <figure>
                    <figcaption>Watermark preview</figcaption>
                    <img id="wmPreview" alt="watermark preview">
                </figure>
                <figure>
                    <figcaption>Image preview</figcaption>
                    <img id="imgPreview" alt="image preview">
                </figure>
            </div>

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
            <label>Select images (max 20 MB each):
                <input type="file" name="images[]" multiple required>
            </label>

            <div style="text-align:center;margin-top:18px">
                <button type="submit" name="submit" value="1">Start processing</button>
            </div>
            <fieldset>
                <legend>Artwork Metadata</legend>

                <label>Title
                    <input type="text" name="title" required>
                </label>

                <label>Creator (your name or studio)
                    <input type="text" name="creator" required>
                </label>

                <label>Creation Date
                    <input type="date" name="creation_date" required>
                </label>

                <label>Description
                    <textarea name="description" rows="4" required></textarea>
                </label>

                <label>Intellectual&nbsp;Genre
                    <input type="text" name="genre" placeholder="e.g. Surrealism">
                </label>

                <label>Subject / Keywords (comma-separated)
                    <input type="text" name="keywords">
                </label>

                <label>Web Statement&nbsp;(URL)
                    <input type="text" name="webstatement" placeholder="https://…">
                </label>

                <label>By-line&nbsp;(display credit)
                    <input type="text" name="byline_name">
                </label>

                <label>Copyright&nbsp;Notice
                    <input type="text" name="copyright_notice" placeholder="© 2025 Infinite Muse Arts">
                </label>

                <label>Author’s&nbsp;Position (optional)
                    <input type="text" name="position" placeholder="Photographer / Painter / …">
                </label>

                <label>Headline (SEO headline)
                    <input type="text" name="seo_headline">
                </label>
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

    <p class="notice">Original uploads are capped at 10 MB. Thumbnails shown above refresh automatically.</p>
    <p class="fine-print">&copy; 2025 PixlKey by Infinite Muse Arts</p>
</body>

</html>
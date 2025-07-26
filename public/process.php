<?php

/**
 * process.php — Handles full upload-to-output workflow for artwork processing
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * This controller processes user-submitted artwork files by validating inputs,
 * applying optional watermarks, stripping metadata, converting formats,
 * embedding rights/licensing metadata, generating SHA-256 hashes,
 * producing thumbnails/previews, and extracting provenance details.
 * It writes certificates of authenticity, stores image records to MariaDB,
 * and packages final assets into a downloadable ZIP archive.
 *
 * Security measures include:
 * - Login enforcement
 * - CSRF validation
 * - Upload size limits
 * - Per-IP rate limiting
 * - Sanitisation of all user input
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.1-alpha
 * @see        /download_zip.php, /core/processing/process_helpers.php, /core/metadata/metadata_extractor.php
 */

require_once __DIR__ . '/../core/session/SessionBootstrap.php';
require_once __DIR__ . '/../core/auth/auth.php';
\PixlKey\Session\startSecureSession();
require_login();
require_once __DIR__ . '/../core/auth/rate_limiter.php';
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/helpers/functions.php';

validate_csrf_token();                //  ← run early

$userId = current_user()['user_id'];

/* =====  constants & helpers  =================================== */
$allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];   // for upload filter
$processedDir      = __DIR__ . '/../processed';

/* ================================================================
   1.  Resolve watermark to use
   --------------------------------------------------------------- */
$selectedWatermark     = '';   // absolute filesystem path
$uploadedWatermarkPath = '';   // temp one-off watermark

// (a) saved watermark chosen from <select>
if (!empty($_POST['watermark_id'])) {
    $stmt = $pdo->prepare(
        'SELECT path FROM watermarks WHERE watermark_id = ? AND user_id = ?'
    );
    $stmt->execute([$_POST['watermark_id'], $userId]);
    if ($row = $stmt->fetch()) {
        // DB stores path relative to project root, e.g. "watermarks/{$userId}/sig.png"
        // Real path must be resolved from the PROCESS script which sits in /public.
        $candidate = realpath(__DIR__ . '/../' . ltrim($row['path'], '/'));
        $selectedWatermark = $candidate ?: '';

        // Fallback: if the DB ever stores an absolute path just accept it
        if (!$selectedWatermark && file_exists($row['path'])) {
            $selectedWatermark = $row['path'];
        }
    }
}

// (b) one-off upload overrides the select
if (
    !empty($_FILES['watermark_upload']['tmp_name'])
    && $_FILES['watermark_upload']['error'] === UPLOAD_ERR_OK
) {

    $wmExt = strtolower(pathinfo($_FILES['watermark_upload']['name'], PATHINFO_EXTENSION));
    if (in_array($wmExt, $allowedExtensions)) {
        $runDir = sys_get_temp_dir() . '/' . uniqid('wm_run_');
        mkdir($runDir, 0700, true);
        $uploadedWatermarkPath = "$runDir/custom_wm.$wmExt";
        move_uploaded_file($_FILES['watermark_upload']['tmp_name'], $uploadedWatermarkPath);
        $selectedWatermark = $uploadedWatermarkPath;   // overrides saved one
    }
}

/* ================================================================
   2.  Resolve licence text
   --------------------------------------------------------------- */
$licenseInfo = '';
if (!empty($_POST['license_id'])) {
    $stmt = $pdo->prepare(
        'SELECT text_blob FROM licenses WHERE license_id = ? AND user_id = ?'
    );
    $stmt->execute([$_POST['license_id'], $userId]);
    $licenseInfo = $stmt->fetchColumn() ?: '';
}
if ($licenseInfo === '') {
    $licenseInfo = "Sold for personal use and enjoyment only.";
}

/* --------------------------------------------------------------- *
 *  UPLOAD CONSTRAINTS – 200 MB per file
 * --------------------------------------------------------------- */
define('MAX_UPLOAD_BYTES', 209_715_200);
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size',      '210M');

if (!empty($_FILES['images']['size'])) {
    foreach ((array)$_FILES['images']['size'] as $s) {
        if ($s > MAX_UPLOAD_BYTES) {
            http_response_code(413);
            echoStep("Error: one or more files exceed the 200 MB limit.", 'error');
            exit;
        }
    }
}

require_once __DIR__ . '/../core/processing/process_helpers.php';
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/helpers/functions.php';

// 2) Disable output buffering and enable implicit flushing
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// 3) Send headers to prevent caching and buffering by web servers
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // For Nginx to disable buffering

// 4) Output initial HTML structure with "Processing" message and a container for steps
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Processing - Infinite Muse Toolkit</title>
    <style>
        body {
            background-color: #000;
            color: #ccc;
            font-family: Arial, sans-serif;
            margin: 20px;
            text-align: center;
        }

        h1 {
            color: #d2b648;
            /* Old Gold */
        }

        #steps {
            width: 80%;
            margin: 20px auto;
            text-align: left;
            max-height: 70vh;
            overflow-y: auto;
            padding: 10px;
            background-color: #222;
            border-radius: 8px;
        }

        .step {
            margin: 10px 0;
            padding: 10px;
            background-color: #333;
            border-radius: 4px;
            font-family: monospace;
        }

        .step.success {
            color: #2ecc71;
            /* Green */
        }

        .step.error {
            color: #e74c3c;
            /* Red */
        }

        .step.info {
            color: #3498db;
            /* Blue */
        }

        .step.download {
            color: #f1c40f;
            font-size: 1.8em;
            font-weight: bold;
            margin-top: 20px;

            display: flex;
            justify-content: center;
            gap: 1.2rem;
        }

        /* reusable glow-button */
        .btn {
            display: inline-block;
            padding: 10px 22px;
            margin: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #000;
            background: #d2b648;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn:hover {
            background: #e0c96a
        }

        a {
            color: #d2b648;
            /* Old Gold */
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <h1>Processing - Do Not Refresh</h1>
    <div id="steps"></div>
</body>

</html>
<?php
// Flush the initial HTML to the browser
flush();

// 5. Define the fakePath function
function fakePath($path)
{
    global $processedDir;
    $fakeBase = '.../processed';
    // Ensure $processedDir ends with a slash for accurate replacement
    $processedDirWithSlash = rtrim($processedDir, '/\\') . '/';
    // Remove the base processed directory from the path
    $relativePath = str_replace($processedDirWithSlash, '', $path);
    // Replace backslashes with forward slashes for consistency
    $relativePath = str_replace('\\', '/', $relativePath);
    return $fakeBase . '/' . $relativePath;
}

// 6. Start processing the form data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Grab form data
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $seoHeadline     = trim($_POST['seo_headline'] ?? '');
    $creationDate    = trim($_POST['creation_date'] ?? '');
    $bylineName      = trim($_POST['byline_name'] ?? '');
    $copyrightNotice = trim($_POST['copyright_notice'] ?? '');
    $creator         = trim($_POST['creator'] ?? '');
    $position        = trim($_POST['position'] ?? '');
    $webStatement    = trim($_POST['webstatement'] ?? '');
    $keywords        = trim($_POST['keywords'] ?? '');
    $genre           = trim($_POST['genre'] ?? '');
    $overlayText     = trim($_POST['overlay_text'] ?? '');

    // Sanitise for HTML output
    $title           = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $description     = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $seoHeadline     = htmlspecialchars($seoHeadline, ENT_QUOTES, 'UTF-8');
    $creationDate    = htmlspecialchars($creationDate, ENT_QUOTES, 'UTF-8');
    $bylineName      = htmlspecialchars($bylineName, ENT_QUOTES, 'UTF-8');
    $copyrightNotice = htmlspecialchars($copyrightNotice, ENT_QUOTES, 'UTF-8');
    $creator         = htmlspecialchars($creator, ENT_QUOTES, 'UTF-8');
    $position        = htmlspecialchars($position, ENT_QUOTES, 'UTF-8');
    $webStatement    = htmlspecialchars($webStatement, ENT_QUOTES, 'UTF-8');
    $keywords        = htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8');
    $genre           = htmlspecialchars($genre, ENT_QUOTES, 'UTF-8');

    // Validate creation date
    echoStep("Validating Creation Date...");
    if (empty($creationDate)) {
        echoStep("Error: Creation Date is required.", 'error');
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $creationDate)) {
        echoStep("Error: Invalid Creation Date format (YYYY-MM-DD).", 'error');
        exit;
    }
    $dateParts = explode('-', $creationDate);
    if (!checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
        echoStep("Error: Invalid Creation Date.", 'error');
        exit;
    }
    echoStep("Creation Date validated successfully.", 'success');

    // Handle optional watermark upload
    echoStep("Processing Watermark Upload...");
    $uploadedWatermarkPath = '';
    if (!empty($_FILES['watermark_upload']['tmp_name'])) {
        $wmError    = $_FILES['watermark_upload']['error'];
        $wmTmpName  = $_FILES['watermark_upload']['tmp_name'];
        $wmOrigName = $_FILES['watermark_upload']['name'];
        $wmExt      = strtolower(pathinfo($wmOrigName, PATHINFO_EXTENSION));
        if ($wmError === UPLOAD_ERR_OK && in_array($wmExt, ['png', 'jpg', 'jpeg', 'webp'])) {
            $uniqueWmName          = 'uploaded_wm_' . uniqid() . '.' . $wmExt;
            $uploadedWatermarkPath = $processedDir . '/' . $uniqueWmName;
            if (move_uploaded_file($wmTmpName, $uploadedWatermarkPath)) {
                echoStep("Watermark uploaded successfully.", 'success');
            } else {
                echoStep("Error: Unable to move uploaded watermark file.", 'error');
                $uploadedWatermarkPath = '';
            }
        } else {
            echoStep("Error: Invalid watermark file type or upload error.", 'error');
        }
    } else {
        echoStep("No watermark uploaded. Proceeding without custom watermark.", 'info');
    }

    // If the user uploaded a one-off watermark, override the saved one
    if ($uploadedWatermarkPath) {
        $selectedWatermark = $uploadedWatermarkPath;
    }

    // Create unique subdirectory
    echoStep("Creating processing directory...");
    $runId        = date('Ymd_His') . '_' . uniqid();
    $userProcBase = $processedDir . '/' . $userId;          // user-scoped

    // Rate limit ZIP processing to prevent abuse
    $rateKey = 'zipproc_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (RATE_LIMITING_ENABLED && too_many_attempts($rateKey, DOWNLOAD_ATTEMPT_LIMIT, DOWNLOAD_DECAY_SECONDS)) {
        http_response_code(429);
        echoStep("Too many download packaging requests from your IP. Please wait before retrying.", 'error');
        echoStep("Download throttled. Try again later.", 'error');
        exit;
    }

    /* -----------------------------------------------------------
       Record the attempt only when the limiter is active. This
       prevents the counter from filling up when admins have
       RATE_LIMITING_ENABLED = false (e.g. during load-testing).
    ----------------------------------------------------------- */
    if (RATE_LIMITING_ENABLED) {
        record_failed_attempt($rateKey);
    }

    // Register runId in processing_runs table
    $stmt = $pdo->prepare('INSERT INTO processing_runs (run_id, user_id, zip_path) VALUES (?, ?, ?)');
    $stmt->execute([$runId, $userId, '']);

    if (!is_dir($userProcBase) && !mkdir($userProcBase, 0775, true)) {
        echoStep("Error: unable to create user processing folder.", 'error');
        exit;
    }
    $runDir = $userProcBase . '/' . $runId;
    if (!mkdir($runDir, 0775, true)) {
        echoStep("Error: unable to create run folder.", 'error');
        exit;
    }
    echoStep("Processing directory created: " . fakePath($runDir), 'success');

    // Collect images
    echoStep("Collecting uploaded images...");
    $uploadedFiles = $_FILES['images'] ?? null;
    if (!$uploadedFiles || empty($uploadedFiles['name'][0])) {
        echoStep("Error: No images uploaded.", 'error');
        exit;
    }
    $numFiles = count($uploadedFiles['name']);
    echoStep("Number of images to process: {$numFiles}", 'info');

    /* -----------------------------------------------------------
       Decide what to call the ZIP archive
       -----------------------------------------------------------
       • Single file  →  <original-name>.zip   (sanitised)
       • Multi-file   →  final_assets.zip      (current behaviour)
    ----------------------------------------------------------- */
    $zipBaseName = 'final_assets';
    if ($numFiles === 1) {
        $firstName   = pathinfo($uploadedFiles['name'][0], PATHINFO_FILENAME);
        $zipBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $firstName);
        if ($zipBaseName === '') {
            $zipBaseName = 'final_assets';
        }
    }

    // Prepare final results array
    $resultsToZip = [];

    // Iterate over each uploaded file
    for ($i = 0; $i < $numFiles; $i++) {
        $tmpName  = $uploadedFiles['tmp_name'][$i];
        $origName = $uploadedFiles['name'][$i];
        $error    = $uploadedFiles['error'][$i];

        echoStep("Processing file " . ($i + 1) . " of " . $numFiles . ": " . $origName, 'info');

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
            echoStep("Skipping file due to upload error: {$origName}", 'error');
            continue;
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            echoStep("Skipping file '{$origName}': invalid extension.", 'error');
            continue;
        }

        // Move to runDir
        $fileBase          = pathinfo($origName, PATHINFO_FILENAME);
        $fileBaseSanitised = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fileBase);
        $serverFile        = $runDir . '/' . $fileBaseSanitised . '.' . $ext;

        echoStep("Moving file to processing directory: " . fakePath($serverFile), 'info');
        if (!move_uploaded_file($tmpName, $serverFile)) {
            echoStep("Error: Failed to move uploaded file: {$origName}", 'error');
            continue;
        }
        echoStep("File moved successfully.", 'success');

        // Remove existing metadata
        echoStep("Removing existing metadata from file: " . fakePath($serverFile), 'info');
        $cmdRemove = "exiftool -overwrite_original -all= " . escapeshellarg($serverFile);
        shell_exec($cmdRemove . " 2>&1");
        echoStep("Existing metadata removed.", 'success');

        // Convert to PNG
        echoStep("Converting '" . fakePath($serverFile) . "' to PNG format...", 'info');
        $processedPng = $runDir . '/' . $fileBaseSanitised . '.png';
        $cmdConvert   = "convert " . escapeshellarg($serverFile) . " " . escapeshellarg($processedPng);
        shell_exec($cmdConvert . " 2>&1");
        if (!file_exists($processedPng)) {
            echoStep("Error: Unable to convert to PNG for file: {$origName}", 'error');
            continue;
        }
        echoStep("Conversion to PNG successful: " . fakePath($processedPng), 'success');

        // Watermark the main PNG
        echoStep("Creating signed image copy...", 'info');
        $signedImage = $runDir . '/' . $fileBaseSanitised . '_signed.png';
        if (!copy($processedPng, $signedImage)) {
            echoStep("Error: Failed to create signed image copy for file: {$origName}", 'error');
            continue;
        }
        echoStep("Signed image copy created: " . fakePath($signedImage), 'success');

        // Apply main watermark (if available)
        if ($selectedWatermark && file_exists($selectedWatermark)) {
            echoStep("Applying watermark to '" . fakePath($signedImage) . "'...", 'info');
            // Scale watermark to ~10% of width, place bottom-right
            $identifyCmd = "identify -format '%w' " . escapeshellarg($signedImage);
            $artWidth    = trim(shell_exec($identifyCmd));
            if (is_numeric($artWidth)) {
                $sigWidth   = floor($artWidth * 0.1);
                $resizedWm  = $runDir . '/signature_small.png';
                $cmdResize  = "convert " . escapeshellarg($selectedWatermark) . " -resize {$sigWidth} " . escapeshellarg($resizedWm);
                shell_exec($cmdResize . " 2>&1");
                echoStep("Watermark resized to {$sigWidth}px width.", 'success');

                $margin       = floor($artWidth * 0.02);
                $cmdComposite = "convert " . escapeshellarg($signedImage) . " " . escapeshellarg($resizedWm)
                    . " -gravity southeast -geometry +{$margin}+{$margin} -composite "
                    . escapeshellarg($signedImage);
                shell_exec($cmdComposite . " 2>&1");
                @unlink($resizedWm);
                echoStep("Watermark applied successfully.", 'success');
            } else {
                echoStep("Error: Unable to determine image width for watermarking.", 'error');
            }
        } else {
            echoStep("No valid watermark selected. Skipping watermark application.", 'info');
        }

        // Generate hash
        echoStep("Generating SHA-256 hash for '" . fakePath($signedImage) . "'...", 'info');
        $shaCmd    = "sha256sum " . escapeshellarg($signedImage) . " | awk '{print $1}'";
        $imageHash = trim(shell_exec($shaCmd));
        echoStep("Hash generated: {$imageHash}", 'success');

        // Embed metadata (including Keywords & IntellectualGenre)
        echoStep("Embedding metadata into '" . fakePath($signedImage) . "'...", 'info');
        $cmdEmbed    = "exiftool -overwrite_original "
            . "-Iptc:By-line=" . escapeshellarg($bylineName) . " "
            . "-Iptc:CopyrightNotice=" . escapeshellarg($copyrightNotice) . " "
            . "-XMP-dc:Creator=" . escapeshellarg($creator) . " "
            . "-XMP-dc:Rights=" . escapeshellarg($copyrightNotice) . " "
            . "-XMP-photoshop:AuthorsPosition=" . escapeshellarg($position) . " "
            . "-XMP-xmpRights:Marked=true "
            . "-XMP-xmpRights:WebStatement=" . escapeshellarg($webStatement) . " "
            . "-XMP-dc:Title=" . escapeshellarg($title) . " "
            . "-XMP-dc:Description=" . escapeshellarg($description) . " "
            . "-XMP-dc:Date=" . escapeshellarg($creationDate) . " "
            . "-XMP-photoshop:Headline=" . escapeshellarg($seoHeadline) . " "
            . "-XMP-dc:Subject=" . escapeshellarg($keywords) . " "
            . "-XMP-iptcCore:IntellectualGenre=" . escapeshellarg($genre) . " "
            . "-XMP-xmpMM:DocumentID=uuid:" . escapeshellarg($imageHash) . " "
            . "-XMP-xmpMM:InstanceID=uuid:" . escapeshellarg($imageHash) . " "
            . "-XMP:Rights=" . escapeshellarg($resolvedLicense) . " "
            . escapeshellarg($signedImage);

        exec($cmdEmbed . " 2>&1", $outputEmbed, $retEmbed);
        if ($retEmbed === 0) {
            echoStep("Metadata embedded successfully.", 'success');
        } else {
            echoStep("Error: Failed to embed metadata into '{$origName}'.", 'error');
        }

        // Recompute hash after embedding
        echoStep("Recomputing SHA-256 hash for '" . fakePath($signedImage) . "'...", 'info');
        $newHash = trim(shell_exec($shaCmd));
        echoStep("New hash generated: {$newHash}", 'success');

        // Generate thumbnail & preview
        echoStep("Generating thumbnail and preview for '" . fakePath($signedImage) . "'...", 'info');
        $thumbnail = $runDir . '/' . $fileBaseSanitised . '_thumbnail.png';
        $preview   = $runDir . '/' . $fileBaseSanitised . '_preview.png';
        /* 400-px thumbnail per spec */
        $cmdThumb  = "convert " . escapeshellarg($signedImage) . " -resize 400x400 " . escapeshellarg($thumbnail);
        $cmdPrev   = "convert " . escapeshellarg($signedImage) . " -resize 800x800 " . escapeshellarg($preview);
        shell_exec($cmdThumb . " 2>&1");
        shell_exec($cmdPrev  . " 2>&1");

        /* ------------------------------------------------------- *
         *  Persist ORIGINAL-AND-THUMB info to MariaDB            *
         * ------------------------------------------------------- */
        $imgInfo = getimagesize($signedImage);          // [0]=w, [1]=h, 'mime'=>…

        $webBase  = __DIR__ . '/';                     //  …/public_html/art-processing/
        $relOrig  = ltrim(str_replace($webBase, '', $signedImage), '/');
        $relThumb = ltrim(str_replace($webBase, '', $thumbnail), '/');

        $stmt = $pdo->prepare("
            INSERT INTO images (
                image_id,  user_id,
                original_path, thumbnail_path,
                filesize,   width, height, mime_type,
                sha256,     created_at
            ) VALUES (
                UUID(),    :uid,
                :orig,     :thumb,
                :size,     :w, :h, :mime,
                :sha,      NOW()
            )
        ");

        $stmt->execute([
            ':uid'   => $userId,
            ':orig'  => $relOrig,
            ':thumb' => $relThumb,
            ':size'  => filesize($signedImage),
            ':w'     => $imgInfo[0]  ?? null,
            ':h'     => $imgInfo[1]  ?? null,
            ':mime'  => $imgInfo['mime'] ?? null,
            ':sha'   => hash_file('sha256', $signedImage),
        ]);
        echoStep("Thumbnail and preview generated successfully.", 'success');

        // Watermark thumbnail & preview with random text (or keep it simple)
        if (!empty($selectedWatermark)) {
            echoStep("Applying watermark to thumbnail and preview...", 'info');
            addWatermark($thumbnail, $selectedWatermark, $runDir);
            addWatermark($preview, $selectedWatermark, $runDir);
            echoStep("Watermark applied to thumbnail and preview.", 'success');
        }

        // New Code in process.php

        // Extract metadata using metadata_extractor.php
        echoStep("Extracting metadata from '" . fakePath($signedImage) . "'...", 'info');
        $metadataFile = $runDir . '/' . $fileBaseSanitised . '_metadata.md';

        // Path to metadata_extractor.php
        $metadataExtractor = __DIR__ . '/../core/metadata/metadata_extractor.php';

        // Ensure the metadata extractor script exists
        if (!file_exists($metadataExtractor)) {
            echoStep("Error: Metadata extractor script not found.", 'error');
            exit;
        }

        // Command to execute the metadata extractor script
        $cmdExtract = "php " . escapeshellarg($metadataExtractor)
            . " --input=" . escapeshellarg($signedImage)
            . " --output=" . escapeshellarg($metadataFile);

        // Execute the command
        exec($cmdExtract . " 2>&1", $outputEmbed, $retEmbed);

        // Check for success
        if ($retEmbed === 0) {
            echoStep("Metadata extracted to '" . fakePath($metadataFile) . "'.", 'success');
        } else {
            echoStep("Error: Failed to extract metadata.", 'error');
            foreach ($outputEmbed as $line) {
                echoStep($line, 'error');
            }
            exit;
        }

        // Create certificate (Markdown file)
        echoStep("Generating certificate of authenticity...", 'info');
        $certMd   = $runDir . '/' . $fileBaseSanitised . '_certificate.md';
        $mdContent = <<<EOF
# Digital Certificate of Authenticity

**Title of Artwork:** *{$title}*  
**Artist (By-line):** {$bylineName}  
**Creator:** {$creator}  
**Position:** {$position}  
**Creation Date:** {$creationDate}  
**File Type:** PNG  
**Keywords (Subject):** {$keywords}  
**Intellectual Genre:** {$genre}  

---

### Description

> *{$description}*

---

### Rights

*{$resolvedLicense}*  
 {$copyrightNotice}
---

### Additional Details

**Headline:** {$seoHeadline}  
**Web Statement:** {$webStatement}  
**Metadata ID (UUID):** uuid:{$imageHash}  
**Hash of Signed Image:** {$newHash}  

---

**Certified by:** {$bylineName}  
**Date of Issue:** {$creationDate}
EOF;
        file_put_contents($certMd, $mdContent);
        echoStep("Certificate generated: " . fakePath($certMd), 'success');

        // Add only final assets (exclude backups, etc.)
        $resultsToZip[] = $signedImage;
        $resultsToZip[] = $thumbnail;
        $resultsToZip[] = $preview;
        $resultsToZip[] = $metadataFile;
        $resultsToZip[] = $certMd;
    }

    // If files were processed, zip them
    if (!empty($resultsToZip)) {
        echoStep("Creating ZIP archive of processed files...", 'info');
        $zipFile = $runDir . "/{$zipBaseName}.zip";
        $zipPath = trim(shell_exec("which zip"));
        if (!empty($zipPath) && file_exists($zipPath)) {
            // We'll zip only the final result files
            $filesArg = '';
            foreach ($resultsToZip as $f) {
                $base = basename($f);
                $filesArg .= escapeshellarg($base) . ' ';
            }
            $cmdZip = "cd " . escapeshellarg($runDir) . " && zip -r " . escapeshellarg($zipFile) . " $filesArg";
            exec($cmdZip . " 2>&1", $outputZip, $retZip);

            if ($retZip === 0 && file_exists($zipFile)) {
                echoStep("ZIP archive created successfully: {$zipBaseName}.zip", 'success');
                // Provide link to download via a separate script
                $dlUrl = "download_zip.php?runId=" . urlencode($runId);

                echoStep(
                    "<button class='btn' onclick=\"window.location.href='{$dlUrl}'\">Download ZIP</button>"
                        . "<button class='btn' onclick=\"window.location.href='/index.php'\">Return to index</button>",
                    'download'
                );
            } else {
                echoStep("Error: Unable to create ZIP archive.", 'error');
            }
        } else {
            echoStep("Error: 'zip' command not available on server.", 'error');
        }
    } else {
        echoStep("No valid images were processed.", 'error');
    }

    echoStep("Processing completed.", 'success');

    /* -----------------------------------------------------------
       The ZIP build succeeded, so we shouldn’t keep counting this
       run against the user.  Reset the bucket to avoid locking
       well-behaved clients out after several large batches.
    ----------------------------------------------------------- */
    if (RATE_LIMITING_ENABLED) {
        clear_failed_attempts($rateKey);
    }

    // End of processing
    @ob_end_flush();
}
?>

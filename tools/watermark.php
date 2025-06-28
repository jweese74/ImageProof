<?php
/**
 * /tools/watermark.php
 *
 * A simple tool that applies a text watermark to an image.
 * Expects an image_id in the query string, e.g. watermark.php?image_id=123
 *
 * Steps:
 * 1. Show a form for watermark options.
 * 2. On submit, process the image (using GD), save a new file.
 * 3. Update the images table with the new file path.
 * 4. Redirect back to image_actions.php with the same image_id.
 */

session_start();

// 1. Check user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// 2. Load config / DB
$config = require __DIR__ . '/../config.php';  // Adjust path as needed
$pdo    = $config['pdo'];

// 3. Validate image_id
$imageId = isset($_GET['image_id']) ? (int)$_GET['image_id'] : 0;
if ($imageId < 1) {
    die('Invalid or missing image_id.');
}

// 4. Fetch the image record
$stmt = $pdo->prepare("
    SELECT image_id, file_path, file_name
      FROM images
     WHERE image_id = :imgid
       AND user_id = :uid
     LIMIT 1
");
$stmt->execute([
    'imgid' => $imageId,
    'uid'   => $_SESSION['user_id']
]);
$imageRow = $stmt->fetch();

if (!$imageRow) {
    die('Image not found or not authorised.');
}

$currentPath = $imageRow['file_path'];
$currentName = $imageRow['file_name'];
$errorMessage = '';
$successMessage = '';

/**
 * Optional: Define an array of available font paths.
 * Adjust these to match actual .ttf files on your server.
 */
$availableFonts = [
    'Arial'    => __DIR__ . '/fonts/Arial.ttf',        // E.g., project-root/tools/fonts/Arial.ttf
    'Verdana'  => __DIR__ . '/fonts/Verdana.ttf',
    'Times'    => __DIR__ . '/fonts/times.ttf',
    'Courier'  => __DIR__ . '/fonts/cour.ttf'
];
$defaultFontKey = 'Arial';  // default font

// 5. Handle POST (i.e. user clicked "Apply Watermark")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_watermark'])) {
    // Retrieve user selections
    $placement     = $_POST['placement'] ?? 'bottom_right';
    $watermarkText = trim($_POST['watermark_text'] ?? '');
    $fontChoiceKey = $_POST['font_choice'] ?? $defaultFontKey;
    $fontRatio     = floatval($_POST['font_ratio'] ?? 5.0);  // default 5%
    $colourInput   = $_POST['colour'] ?? '#FFFFFF';          // default white

    // Basic validations
    if (empty($watermarkText)) {
        $errorMessage = 'Please enter watermark text.';
    } elseif (!file_exists($currentPath)) {
        $errorMessage = 'Image file no longer exists on the server.';
    } elseif (!isset($availableFonts[$fontChoiceKey]) || !file_exists($availableFonts[$fontChoiceKey])) {
        $errorMessage = 'Selected font not available.';
    }

    if (empty($errorMessage)) {
        // Attempt to apply watermark
        try {
            // 5.1 Load image (GD)
            $imageInfo = getimagesize($currentPath);
            if (!$imageInfo) {
                throw new RuntimeException('Unsupported image type or invalid file.');
            }

            // Create GD image resource from file
            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    $im = imagecreatefromjpeg($currentPath);
                    break;
                case 'image/png':
                    $im = imagecreatefrompng($currentPath);
                    break;
                case 'image/gif':
                    $im = imagecreatefromgif($currentPath);
                    break;
                default:
                    throw new RuntimeException('Unsupported image type: ' . $imageInfo['mime']);
            }

            // 5.2 Compute font size based on image width
            $imgWidth  = imagesx($im);
            $imgHeight = imagesy($im);
            // e.g. 5% of the smaller dimension (or width, your choice)
            // You can adjust logic to your needs:
            $fontSize  = ($imgWidth < $imgHeight)
                ? ($imgWidth * ($fontRatio / 100.0))
                : ($imgHeight * ($fontRatio / 100.0));

            // 5.3 Parse colour
            // E.g. #RRGGBB
            if (preg_match('/^#?([a-f0-9]{6})$/i', $colourInput, $matches)) {
                $hex = $matches[1];
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
            } else {
                // default to white
                $r = $g = $b = 255;
            }
            
            // Let’s optionally add some alpha for watermark = ~50% transparency
            // But note: imagettftext doesn’t directly use alpha for text colour. 
            // A trick is to create a color with alpha if you use imagecolorallocatealpha
            // However, for simplicity, we'll just create a normal color:
            $textColor = imagecolorallocate($im, $r, $g, $b);

            // 5.4 Compute x,y based on placement
            $bbox  = imagettfbbox($fontSize, 0, $availableFonts[$fontChoiceKey], $watermarkText);
            $textW = abs($bbox[2] - $bbox[0]);
            $textH = abs($bbox[5] - $bbox[1]);

            $x = 10; // default padding
            $y = 10 + $textH;

            switch ($placement) {
                case 'top_left':
                    // $x, $y are fine as defaults
                    break;
                case 'top_right':
                    $x = $imgWidth - $textW - 10;
                    $y = 10 + $textH;
                    break;
                case 'middle':
                    $x = ($imgWidth - $textW) / 2;
                    $y = ($imgHeight - $textH) / 2 + $textH;
                    break;
                case 'bottom_left':
                    $x = 10;
                    $y = $imgHeight - 10;
                    break;
                case 'bottom_right':
                default:
                    $x = $imgWidth - $textW - 10;
                    $y = $imgHeight - 10;
                    break;
            }

            // 5.5 Draw the text
            imagettftext($im, $fontSize, 0, $x, $y, $textColor, $availableFonts[$fontChoiceKey], $watermarkText);

            // 5.6 Save the new image
            $pathInfo     = pathinfo($currentPath);
            $extension    = strtolower($pathInfo['extension']);
            $newFileName  = $pathInfo['filename'] . '_wm.' . $extension;  // e.g. cat.jpg -> cat_wm.jpg
            $newFilePath  = $pathInfo['dirname'] . '/' . $newFileName;

            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    imagejpeg($im, $newFilePath, 90);
                    break;
                case 'image/png':
                    imagepng($im, $newFilePath);
                    break;
                case 'image/gif':
                    imagegif($im, $newFilePath);
                    break;
            }

            // Clean up
            imagedestroy($im);

            // 5.7 Update DB with new file path
            $updateStmt = $pdo->prepare("
                UPDATE images
                   SET file_path = :new_path,
                       file_name = :new_name
                 WHERE image_id = :imgid
                   AND user_id   = :uid
            ");
            $updateStmt->execute([
                'new_path' => $newFilePath,
                'new_name' => $newFileName,
                'imgid'    => $imageRow['image_id'],
                'uid'      => $_SESSION['user_id']
            ]);

            // (Optional) Delete the old file if you don’t want to keep it
            // unlink($currentPath);

            // (Optional) Insert a log in image_actions_log
            $logStmt = $pdo->prepare("
                INSERT INTO image_actions_log (image_id, user_id, action_desc, message)
                VALUES (:imgid, :uid, 'Watermark', :msg)
            ");
            $logStmt->execute([
                'imgid' => $imageRow['image_id'],
                'uid'   => $_SESSION['user_id'],
                'msg'   => "Applied watermark: '{$watermarkText}' at {$placement}"
            ]);

            // 5.8 Redirect back to image_actions with success
            header('Location: ../image_actions.php?image_id=' . $imageId);
            exit;
        } catch (Exception $e) {
            $errorMessage = 'Error applying watermark: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Watermark Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        .error { color: red; }
        form { margin-top: 20px; padding: 15px; border: 1px solid #ccc; }
        label { display: block; margin: 10px 0 5px; }
        select, input[type="text"], input[type="number"], input[type="color"] {
            width: 100%; padding: 5px; box-sizing: border-box;
        }
        button { margin-top: 10px; padding: 8px 16px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Apply Watermark</h1>

    <p><strong>Current Image (ID <?= htmlspecialchars($imageId) ?>):</strong>  
       <?= htmlspecialchars($currentName) ?></p>
    <?php if (file_exists($currentPath)): ?>
        <img src="<?= htmlspecialchars(str_replace(dirname(__DIR__), '', $currentPath)) ?>"
             alt="Preview"
             style="max-width:300px; border:1px solid #aaa;">
    <?php else: ?>
        <p><em>Image file not found on server.</em></p>
    <?php endif; ?>

    <!-- Error message -->
    <?php if (!empty($errorMessage)): ?>
        <p class="error"><?= htmlspecialchars($errorMessage) ?></p>
    <?php endif; ?>

    <!-- Watermark Form -->
    <form method="POST">
        <!-- Placement -->
        <label for="placement">Placement:</label>
        <select name="placement" id="placement">
            <option value="top_left">Top Left</option>
            <option value="top_right">Top Right</option>
            <option value="middle">Middle</option>
            <option value="bottom_left">Bottom Left</option>
            <option value="bottom_right" selected>Bottom Right</option>
        </select>

        <!-- Watermark Text -->
        <label for="watermark_text">Watermark Text:</label>
        <input type="text" name="watermark_text" id="watermark_text" placeholder="Enter your text...">

        <!-- Font Choice -->
        <label for="font_choice">Font:</label>
        <select name="font_choice" id="font_choice">
            <?php foreach ($availableFonts as $fontKey => $fontPath): ?>
                <option value="<?= $fontKey ?>" 
                    <?= ($fontKey === $defaultFontKey) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fontKey) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Font Ratio -->
        <label for="font_ratio">Font Ratio (% of image dimension):</label>
        <input type="number" name="font_ratio" id="font_ratio" value="5" step="0.1" min="1" max="50">
        
        <!-- Colour Picker -->
        <label for="colour">Watermark Colour:</label>
        <input type="color" name="colour" id="colour" value="#FFFFFF">
        
        <!-- Apply Button -->
        <button type="submit" name="apply_watermark">Apply Watermark</button>
    </form>

    <p><a href="../image_actions.php?image_id=<?= $imageId ?>">Back to Image Actions</a></p>
</div>
</body>
</html>

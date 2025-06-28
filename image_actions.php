<?php
/**
 * image_actions.php
 *
 * Central hub after user login.
 * Uses a database approach to track uploaded images by ID.
 * Tools receive and return data by referencing the same image_id.
 */

session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Load config (which provides $pdo, etc.)
$config = require __DIR__ . '/config.php';
$pdo    = $config['pdo'];

// Create an uploads directory if needed
$uploadDir = __DIR__ . '/uploads';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// For demonstration, store up to 250MB
$maxFileSizeMB = 250;
$errorMessage   = '';
$successMessage = '';

// 1. Handle reset (delete both DB record & file from server)
if (isset($_POST['reset']) && !empty($_POST['image_id'])) {
    $imageId = (int) $_POST['image_id'];
    
    // Fetch the file path from DB
    $stmt = $pdo->prepare("SELECT file_path FROM images WHERE image_id = :imgid AND user_id = :uid");
    $stmt->execute([
        'imgid' => $imageId,
        'uid'   => $_SESSION['user_id']
    ]);
    $row = $stmt->fetch();

    if ($row) {
        $filePath = $row['file_path'];
        // Remove file from server if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        // Remove from DB
        $deleteStmt = $pdo->prepare("DELETE FROM images WHERE image_id = :imgid AND user_id = :uid");
        $deleteStmt->execute([
            'imgid' => $imageId,
            'uid'   => $_SESSION['user_id']
        ]);
    }

    // Redirect to self without image_id
    header('Location: image_actions.php');
    exit;
}

// 2. Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image_file'])) {
    $file = $_FILES['image_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'File upload error. Please try again.';
    } else {
        // Check file size
        $fileSizeMB = $file['size'] / (1024 * 1024);
        if ($fileSizeMB > $maxFileSizeMB) {
            $errorMessage = "File exceeds {$maxFileSizeMB} MB limit.";
        } else {
            // Create a unique filename
            $uniqueName = time() . '_' . uniqid() . '_' . $file['name'];
            $targetPath = $uploadDir . '/' . $uniqueName;

            // Attempt to move the uploaded file into your uploads directory
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                
                // 1. Get the current date/time for file_modification_dt
                $now = date('Y-m-d H:i:s');

                // 2. Insert record into 'images' table, including file_modification_dt
                $insertStmt = $pdo->prepare("
                    INSERT INTO images (
                        user_id,
                        file_path,
                        file_name,
                        directory,
                        file_size,
                        file_modification_dt,
                        created_at
                    ) VALUES (
                        :user_id,
                        :file_path,
                        :file_name,
                        :directory,
                        :file_size,
                        :file_modification_dt,
                        NOW()
                    )
                ");
                $inserted = $insertStmt->execute([
                    'user_id'               => $_SESSION['user_id'],
                    'file_path'             => $targetPath,
                    'file_name'             => $file['name'],
                    'directory'             => $uploadDir,  // Or whatever directory you want
                    'file_size'             => $fileSizeMB,
                    'file_modification_dt'  => $now
                ]);

                if ($inserted) {
                    $newImageId = $pdo->lastInsertId();
                    // Redirect with the new image_id so the hub can display it
                    header('Location: image_actions.php?image_id=' . $newImageId);
                    exit;
                } else {
                    // If DB insert fails
                    $errorMessage = 'Database error. Could not save file info.';
                }
            } else {
                $errorMessage = 'Unable to save the uploaded file to server.';
            }
        }
    }
}

// 3. Display Current Image (if any)
$imageId     = isset($_GET['image_id']) ? (int)$_GET['image_id'] : 0;
$currentFile = '';
$currentName = '';

if ($imageId > 0) {
    // Fetch from DB
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

    if ($imageRow) {
        $currentFile = $imageRow['file_path'];
        $currentName = $imageRow['file_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Image Actions - Database-Centric</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .messages {
            margin: 10px 0;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        form {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
        }
        .preview img {
            max-width: 100%;
            margin-top: 10px;
            display: block;
            border: 1px solid #999;
        }
        .tools button {
            margin-right: 10px;
        }
        .results-div {
            margin-top: 20px;
            border: 1px solid #ccc;
            min-height: 200px;
            padding: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Welcome to Infinite Image Tools (DB-Centric)</h1>

    <!-- Messages -->
    <div class="messages">
        <?php if (!empty($errorMessage)): ?>
            <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
    </div>

    <!-- Upload Form -->
    <form method="POST" enctype="multipart/form-data">
        <label for="image_file"><strong>Upload an image (Max 250MB):</strong></label><br>
        <input type="file" name="image_file" id="image_file" accept="image/*" required>
        <br><br>
        <button type="submit">Upload</button>
    </form>

    <?php if ($currentFile && file_exists($currentFile)): ?>
        <!-- Preview -->
        <div class="preview">
            <h3>Current Image Preview (ID: <?= $imageId ?>)</h3>
            <p>Filename: <?= htmlspecialchars($currentName) ?></p>
            <img src="<?= htmlspecialchars(str_replace(__DIR__, '', $currentFile)) ?>" alt="Uploaded Image Preview">
        </div>

        <!-- Reset Form -->
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="image_id" value="<?= $imageId ?>">
            <button type="submit" name="reset" value="1" style="color: red;">
                RESET (Delete File & Remove from DB)
            </button>
        </form>

        <!-- Tools Section -->
        <div class="tools" style="margin-top: 20px;">
            <h3>Tools (Reference image_id=<?= $imageId ?>)</h3>
            <!-- Example button for the Watermark tool -->
            <button onclick="window.location.href='tools/watermark.php?image_id=<?= $imageId ?>'">
                Watermark Tool
            </button>
            <button onclick="window.location.href='tool2.php?image_id=<?= $imageId ?>'">
                Tool 2
            </button>
            <button onclick="window.location.href='tool3.php?image_id=<?= $imageId ?>'">
                Tool 3
            </button>
            <button onclick="window.location.href='tool4.php?image_id=<?= $imageId ?>'">
                Tool 4
            </button>
        </div>

        <!-- Results or Logs Display Area -->
        <div class="results-div">
            <h3>Tool Results / Logs</h3>
            <p>Once a tool finishes processing (e.g., <code>tool1.php</code>), 
               it can redirect back here with updated image data or logs in the database.</p>
            <p>You could query a separate <code>image_actions_log</code> table to display processing history.</p>
        </div>
    <?php else: ?>
        <p>No image selected or the file no longer exists. Please upload an image.</p>
    <?php endif; ?>

</div>
</body>
</html>

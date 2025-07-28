<?php

/**
 * store_data.php — Maps processed artwork and metadata into database records
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Handles the final step in artwork processing by ingesting metadata, verifying user ownership,
 * and committing artwork, keyword, genre, image, certificate, and AI metadata records to the database.
 * Ensures integrity through authentication, CSRF protection, and transactional storage. 
 * Supports many-to-many relationships and prepares artwork for certificate issuance and provenance tracking.
 *
 * @package    PixlKey
 * @subpackage Core/Processing
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.3-alpha
 * @see        process.php, process_helpers.php, config.php, auth.php
 */

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../session/SessionBootstrap.php';
require_once __DIR__ . '/../security/CsrfToken.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../dao/UserDAO.php';

use PixlKey\DAO\UserDAO;

\PixlKey\Session\startSecureSession();

// Initialize DAO
$userDAO = new UserDAO($pdo);

require_login();

session_regenerate_id(true);
\PixlKey\Security\rotateToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \PixlKey\Security\validateToken();
}

require_once __DIR__ . '/process_helpers.php';

// 2. Function to retrieve UUIDs for existing entries or create new ones
function getOrCreateId(PDO $pdo, $table, $column, $value)
{
    // Prepare SELECT statement
    $selectStmt = $pdo->prepare("SELECT {$column}_id FROM {$table} WHERE {$column} = :value");
    $selectStmt->execute(['value' => $value]);
    $result = $selectStmt->fetch();

    if ($result) {
        return $result["{$column}_id"];
    } else {
        // Insert new entry
        $insertStmt = $pdo->prepare("INSERT INTO {$table} ({$column}_id, {$column}) VALUES (UUID(), :value)");
        $insertStmt->execute(['value' => $value]);
        return $pdo->lastInsertId(); // Assumes the database returns the last inserted UUID
    }
}

// 3. Receive and validate 'runId'
if (!isset($_GET['runId']) && !isset($_POST['runId'])) {
    die("Error: 'runId' parameter is required.");
}

$runId = $_GET['runId'] ?? $_POST['runId'];

$currentUser = $userDAO->findById($_SESSION['user_id']);
if (!$currentUser) {
    http_response_code(403);
    die("Error: User session invalid or user not found.");
}
$userId = $currentUser['user_id'];
$stmt = $pdo->prepare('SELECT 1 FROM processing_runs WHERE run_id = ? AND user_id = ?');
$stmt->execute([$runId, $userId]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    die("Error: You do not have permission to access this runId.");
}

$runDir = __DIR__ . "/../../processed/{$runId}";

if (!is_dir($runDir)) {
    die("Error: Processing directory for runId '{$runId}' does not exist.");
}

// 4. Begin Database Transaction
try {
    $pdo->beginTransaction();

    // 5. Parse process data
    // Assuming that process.php stores the initial form data in a JSON file or similar.
    // For this example, we'll assume a 'data.json' file exists in the runDir.
    $dataFile = "{$runDir}/data.json";
    if (!file_exists($dataFile)) {
        throw new Exception("Error: Data file 'data.json' not found in run directory.");
    }

    $dataJson = file_get_contents($dataFile);
    $data = json_decode($dataJson, true);

    if (!$data) {
        throw new Exception("Error: Failed to decode 'data.json'.");
    }

    // Extract form data
    $title           = $data['title'] ?? '';
    $description     = $data['description'] ?? '';
    $seoHeadline     = $data['seo_headline'] ?? '';
    $creationDate    = $data['creation_date'] ?? '';
    $bylineName      = $data['byline_name'] ?? '';
    $copyrightNotice = $data['copyright_notice'] ?? '';
    $creator         = $data['creator'] ?? '';
    $position        = $data['position'] ?? '';
    $webStatement    = $data['webstatement'] ?? '';
    $keywords        = $data['keywords'] ?? '';
    $genre           = $data['genre'] ?? '';
    $overlayText     = $data['overlay_text'] ?? '';

    // 6. Insert into Artworks table
    $artworkId = generateUUID($pdo, 'Artworks');

    $insertArtwork = $pdo->prepare("
        INSERT INTO Artworks (
            artwork_id, title, description, seo_headline, creation_date, keywords, genre,
            byline_name, copyright_notice, creator, position, webstatement,
            overlay_text, document_id, instance_id, hash_signed_image, date_of_issue,
            certified_by, status, blockchain_record, ai_metadata
        ) VALUES (
            :artwork_id, :title, :description, :seo_headline, :creation_date, :keywords, :genre,
            :byline_name, :copyright_notice, :creator, :position, :webstatement,
            :overlay_text, UUID(), UUID(), :hash_signed_image, NOW(),
            :certified_by, 'Processed', :blockchain_record, :ai_metadata
        )
    ");

    // Placeholder values for some fields; adjust as needed
    $hashSignedImage = ''; // To be updated after image processing
    $blockchainRecord = ''; // To be updated based on blockchain integration
    $aiMetadata = json_encode([]); // Populate with actual AI metadata if available

    $insertArtwork->execute([
        'artwork_id'         => $artworkId,
        'title'              => $title,
        'description'        => $description,
        'seo_headline'       => $seoHeadline,
        'creation_date'      => $creationDate,
        'keywords'           => $keywords,
        'genre'              => $genre,
        'byline_name'        => $bylineName,
        'copyright_notice'   => $copyrightNotice,
        'creator'            => $creator,
        'position'           => $position,
        'webstatement'       => $webStatement,
        'overlay_text'       => $overlayText,
        'hash_signed_image'  => $hashSignedImage,
        'certified_by'       => $bylineName, // Assuming certification by bylineName
        'blockchain_record'  => $blockchainRecord,
        'ai_metadata'        => $aiMetadata
    ]);

    // 7. Handle Keywords (Many-to-Many)
    $keywordList = array_map('trim', explode(',', $keywords));
    foreach ($keywordList as $keyword) {
        if (empty($keyword)) continue;
        // Get or create keyword ID
        $keywordId = getOrCreateId($pdo, 'Keywords', 'keyword', $keyword);
        // Insert into ArtworkKeywords
        $insertArtworkKeyword = $pdo->prepare("
            INSERT IGNORE INTO ArtworkKeywords (artwork_id, keyword_id)
            VALUES (:artwork_id, :keyword_id)
        ");
        $insertArtworkKeyword->execute([
            'artwork_id' => $artworkId,
            'keyword_id' => $keywordId
        ]);
    }

    // 8. Handle Genres (Many-to-Many)
    $genreList = array_map('trim', explode(',', $genre));
    foreach ($genreList as $gen) {
        if (empty($gen)) continue;
        // Get or create genre ID
        $genreId = getOrCreateId($pdo, 'Genres', 'genre', $gen);
        // Insert into ArtworkGenres
        $insertArtworkGenre = $pdo->prepare("
            INSERT IGNORE INTO ArtworkGenres (artwork_id, genre_id)
            VALUES (:artwork_id, :genre_id)
        ");
        $insertArtworkGenre->execute([
            'artwork_id' => $artworkId,
            'genre_id'   => $genreId
        ]);
    }

    // 9. Handle Creators (Many-to-Many)
    $creatorList = array_map('trim', explode(',', $creator));
    foreach ($creatorList as $cre) {
        if (empty($cre)) continue;
        // Get or create creator ID
        $creatorId = getOrCreateId($pdo, 'Creators', 'name', $cre);
        // Insert into ArtworkCreators
        $insertArtworkCreator = $pdo->prepare("
            INSERT IGNORE INTO ArtworkCreators (artwork_id, creator_id)
            VALUES (:artwork_id, :creator_id)
        ");
        $insertArtworkCreator->execute([
            'artwork_id' => $artworkId,
            'creator_id' => $creatorId
        ]);
    }

    // 10. Handle Bylines (Many-to-Many)
    $bylineList = array_map('trim', explode(',', $bylineName));
    foreach ($bylineList as $byl) {
        if (empty($byl)) continue;
        // Get or create byline ID
        $bylineId = getOrCreateId($pdo, 'Bylines', 'name', $byl);
        // Insert into ArtworkBylines
        $insertArtworkByline = $pdo->prepare("
            INSERT IGNORE INTO ArtworkBylines (artwork_id, byline_id)
            VALUES (:artwork_id, :byline_id)
        ");
        $insertArtworkByline->execute([
            'artwork_id' => $artworkId,
            'byline_id'  => $bylineId
        ]);
    }

    // 11. Handle Images
    // Iterate through all signed images in runDir
    $imageFiles = glob("{$runDir}/*_signed.png");
    foreach ($imageFiles as $signedImagePath) {
        $imageBaseName = basename($signedImagePath, '_signed.png');

        // Read metadata
        $metadataFile = "{$runDir}/{$imageBaseName}_metadata.txt";
        if (!file_exists($metadataFile)) {
            throw new Exception("Metadata file '{$metadataFile}' not found.");
        }
        $metadataContent = file_get_contents($metadataFile);
        // Parse metadata as needed. This example assumes key-value pairs.
        $metadata = [];
        $lines = explode("\n", $metadataContent);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $metadata[trim($key)] = trim($value);
            }
        }

        // Read certificate
        $certFile = "{$runDir}/{$imageBaseName}_certificate.md";
        if (!file_exists($certFile)) {
            throw new Exception("Certificate file '{$certFile}' not found.");
        }
        $certificateContent = file_get_contents($certFile);

        // Insert into Images table
        // Gather image properties
        $fileSize   = filesize($signedImagePath);
        $mimeType   = mime_content_type($signedImagePath);
        list($width, $height) = getimagesize($signedImagePath);
        // For simplicity, setting some fields to NULL or default values
        $imageType    = 'signed'; // or determine based on naming
        $filePath     = $signedImagePath;
        $bitDepth     = null; // Determine if needed
        $colorType    = null; // Determine if needed
        $compression   = null; // Determine if needed
        $interlace     = null; // Determine if needed
        $backgroundColor = null; // Determine if needed
        $hashValue     = hash_file('sha256', $signedImagePath);

        $imageId = generateUUID($pdo, 'Images');

        $insertImage = $pdo->prepare("
            INSERT INTO Images (
                image_id, artwork_id, image_type, file_path, file_size, mime_type,
                width, height, bit_depth, color_type, compression, interlace,
                background_color, hash_value
            ) VALUES (
                :image_id, :artwork_id, :image_type, :file_path, :file_size, :mime_type,
                :width, :height, :bit_depth, :color_type, :compression, :interlace,
                :background_color, :hash_value
            )
        ");

        $insertImage->execute([
            'image_id'         => $imageId,
            'artwork_id'       => $artworkId,
            'image_type'       => $imageType,
            'file_path'        => $filePath,
            'file_size'        => $fileSize,
            'mime_type'        => $mimeType,
            'width'            => $width,
            'height'           => $height,
            'bit_depth'        => $bitDepth,
            'color_type'       => $colorType,
            'compression'      => $compression,
            'interlace'        => $interlace,
            'background_color' => $backgroundColor,
            'hash_value'       => $hashValue
        ]);

        // 12. Insert into Certificates table
        $certificateId = generateUUID($pdo, 'Certificates');

        $insertCertificate = $pdo->prepare("
            INSERT INTO Certificates (
                certificate_id, artwork_id, certificate_content, file_path, generated_at
            ) VALUES (
                :certificate_id, :artwork_id, :certificate_content, :file_path, NOW()
            )
        ");

        $insertCertificate->execute([
            'certificate_id'      => $certificateId,
            'artwork_id'          => $artworkId,
            'certificate_content' => $certificateContent,
            'file_path'           => $certFile
        ]);

        // 13. Insert into AIMetadata table (if applicable)
        // Assuming AI metadata is stored in a JSON file, e.g., _ai_metadata.json
        $aiMetadataFile = "{$runDir}/{$imageBaseName}_ai_metadata.json";
        if (file_exists($aiMetadataFile)) {
            $aiMetadataContent = file_get_contents($aiMetadataFile);
            $aiMetadataJson = json_decode($aiMetadataContent, true) ?: [];

            $aiMetadataId = generateUUID($pdo, 'AIMetadata');

            $insertAIMetadata = $pdo->prepare("
                INSERT INTO AIMetadata (
                    ai_metadata_id, artwork_id, metadata, generated_at
                ) VALUES (
                    :ai_metadata_id, :artwork_id, :metadata, NOW()
                )
            ");

            $insertAIMetadata->execute([
                'ai_metadata_id' => $aiMetadataId,
                'artwork_id'     => $artworkId,
                'metadata'       => json_encode($aiMetadataJson)
            ]);
        }

        // 14. Optionally, handle BlockchainRecords, Logs, Analytics, etc.
        // This example focuses on core entities.
    }

    // 15. Insert into Submissions table
    // Assuming submission data is available, e.g., from a submission.json
    $submissionFile = "{$runDir}/submission.json";
    if (file_exists($submissionFile)) {
        $submissionJson = file_get_contents($submissionFile);
        $submissionData = json_decode($submissionJson, true);

        if ($submissionData) {
            $submissionId = generateUUID($pdo, 'Submissions');

            $insertSubmission = $pdo->prepare("
                INSERT INTO Submissions (
                    submission_id, artwork_id, ip_address, user_agent, submission_time,
                    additional_data, referral_source
                ) VALUES (
                    :submission_id, :artwork_id, :ip_address, :user_agent, :submission_time,
                    :additional_data, :referral_source
                )
            ");

            $insertSubmission->execute([
                'submission_id'   => $submissionId,
                'artwork_id'      => $artworkId,
                'ip_address'      => $submissionData['ip_address'] ?? '',
                'user_agent'      => $submissionData['user_agent'] ?? '',
                'submission_time' => $submissionData['submission_time'] ?? date('Y-m-d H:i:s'),
                'additional_data' => json_encode($submissionData['additional_data'] ?? []),
                'referral_source' => $submissionData['referral_source'] ?? ''
            ]);
        }
    }

    // 16. Commit Transaction
    $pdo->commit();

    echo "Data has been successfully stored in the database.";
} catch (Exception $e) {
    // Rollback Transaction on Error
    $pdo->rollBack();
    if (DB_DEBUG) {
        die("Transaction failed: " . $e->getMessage());
    } else {
        die("An error occurred while storing data. Please try again later.");
    }
}

/**
 * Helper function to generate UUIDs compatible with the database.
 * Assumes the database uses UUID() as the default value.
 * This function generates a UUID in PHP.
 * 
 * @param PDO $pdo
 * @param string $table
 * @return string
 */
function generateUUID(PDO $pdo, $table)
{
    // Use MySQL's UUID function via a query
    $stmt = $pdo->query("SELECT UUID() AS uuid");
    $result = $stmt->fetch();
    return $result['uuid'];
}

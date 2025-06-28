<?php
// metadata_extractor.php

/**
 * Metadata Extractor Script
 *
 * Usage:
 * php metadata_extractor.php --input=/path/to/signed_image.png --output=/path/to/metadata.md
 *
 * This script extracts metadata from a signed image using ExifTool,
 * filters out sensitive information, and formats the remaining data into a polished Markdown file.
 */

// Parse command-line arguments
$options = getopt("", ["input:", "output:"]);

if (!isset($options['input']) || !isset($options['output'])) {
    fwrite(STDERR, "Usage: php metadata_extractor.php --input=/path/to/signed_image.png --output=/path/to/metadata.md\n");
    exit(1);
}

$inputFile = $options['input'];
$outputFile = $options['output'];

// Validate input file
if (!file_exists($inputFile)) {
    fwrite(STDERR, "Error: Input file does not exist: {$inputFile}\n");
    exit(1);
}

// Ensure ExifTool is installed
$exiftoolPath = trim(shell_exec("which exiftool"));
if (empty($exiftoolPath)) {
    fwrite(STDERR, "Error: ExifTool is not installed or not found in PATH.\n");
    exit(1);
}

// Define fields to exclude for security reasons
$excludedFields = [
    'FileName',
    'Directory',
    'FileInode',
    'FilePermissions',
    'FileModificationDate',
    'FileAccessDate',
    'FileCreateDate',
    'FileTypeExtension',
    'FileSize',
    'FileType',
    'MIME Type',
    'ImageSize',
    'Megapixels',
    'SourceFile',               // Newly excluded field
    'FileInodeChangeDate',
	'Rights',// Newly excluded field
    // Add any other fields you wish to exclude
];

// Define a mapping of ExifTool fields to user-friendly labels
$fieldMappings = [
    'ExifToolVersionNumber' => 'ExifTool Version',
    'FileSize' => 'File Size',
    'FileType' => 'File Type',
    'MimeType' => 'MIME Type',
    'ImageWidth' => 'Image Width',
    'ImageHeight' => 'Image Height',
    'BitDepth' => 'Bit Depth',
    'ColorType' => 'Color Type',
    'Compression' => 'Compression',
    'Filter' => 'Filter',
    'Interlace' => 'Interlace',
    'WhitePointX' => 'White Point X',
    'WhitePointY' => 'White Point Y',
    'RedX' => 'Red X',
    'RedY' => 'Red Y',
    'GreenX' => 'Green X',
    'GreenY' => 'Green Y',
    'BlueX' => 'Blue X',
    'BlueY' => 'Blue Y',
    'BackgroundColor' => 'Background Color',
    'ModifyDate' => 'Modify Date',
    'By-line' => 'By-line',
    'CopyrightNotice' => 'Copyright Notice',
    'IntellectualGenre' => 'Intellectual Genre',
    'Creator' => 'Creator',
    'Date' => 'Creation Date',
    'Description' => 'Description',
    'Subject' => 'Subject',
    'Title' => 'Title',
    'AuthorsPosition' => 'Author\'s Position',
    'Headline' => 'Headline',
    'DocumentID' => 'Document ID',
    'InstanceID' => 'Instance ID',
    'Marked' => 'Marked',
    'WebStatement' => 'Web Statement',
    // Add more mappings as needed
];

// Construct the ExifTool command to output JSON
$cmdExtract = escapeshellcmd($exiftoolPath) . " -j " . escapeshellarg($inputFile);

// Execute the command and capture the JSON output
$jsonOutput = shell_exec($cmdExtract . " 2>&1");

if ($jsonOutput === null) {
    fwrite(STDERR, "Error: Failed to execute ExifTool.\n");
    exit(1);
}

// Decode the JSON output
$metadataArray = json_decode($jsonOutput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "Error: Failed to parse ExifTool JSON output.\n");
    exit(1);
}

// Assuming only one object in the JSON array
$metadata = $metadataArray[0];

// Filter out excluded fields
foreach ($excludedFields as $excludedField) {
    if (isset($metadata[$excludedField])) {
        unset($metadata[$excludedField]);
    }
}

// Apply field mappings and prepare filtered metadata
$filteredMetadata = [];
foreach ($metadata as $key => $value) {
    // Use mapped label if available
    $label = isset($fieldMappings[$key]) ? $fieldMappings[$key] : $key;

    // Handle multi-value fields (like arrays)
    if (is_array($value)) {
        $value = implode(', ', $value);
    }

    // Clean up the value (remove unnecessary quotes or escape characters)
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    $filteredMetadata[$label] = $value;
}

// Define sections for structured Markdown
$sections = [
    'Basic Information' => ['Title', 'Creator', 'Creation Date', 'Description', 'Intellectual Genre', 'Subject', 'Rights', 'Web Statement'],
    'Technical Details' => ['File Type', 'MIME Type', 'Image Width', 'Image Height', 'Bit Depth', 'Color Type', 'Compression', 'Filter', 'Interlace', 'White Point X', 'White Point Y', 'Red X', 'Red Y', 'Green X', 'Green Y', 'Blue X', 'Blue Y', 'Background Color', 'Modify Date'],
    'Metadata Identifiers' => ['Document ID', 'Instance ID', 'Marked'],
    'Additional Information' => ['By-line', 'Copyright Notice', 'Author\'s Position', 'Headline'],
    // Removed 'Other Information' as per the requirement
    // Add more sections as needed
];

// Generate Markdown content
$markdownContent = "# Digital Metadata for Artwork\n\n";

foreach ($sections as $sectionTitle => $fields) {
    $markdownContent .= "## {$sectionTitle}\n\n";
    $markdownContent .= "| **Field** | **Value** |\n";
    $markdownContent .= "|-----------|-----------|\n";
    foreach ($fields as $field) {
        if (isset($filteredMetadata[$field])) {
            // Replace newline characters with spaces for table formatting
            $value = str_replace(["\n", "\r"], ' ', $filteredMetadata[$field]);
            // Optionally, add Markdown formatting for certain fields
            if ($field === 'Description') {
                $value = "> " . $value;
            }
            $markdownContent .= "| {$field} | {$value} |\n";
            unset($filteredMetadata[$field]); // Remove to prevent duplication
        }
    }
    $markdownContent .= "\n";
}

// If there are any remaining fields not assigned to sections, they are ignored as per the new requirements.

// Save the Markdown content to the output file
if (file_put_contents($outputFile, $markdownContent) === false) {
    fwrite(STDERR, "Error: Failed to write metadata to {$outputFile}.\n");
    exit(1);
}

exit(0);
?>

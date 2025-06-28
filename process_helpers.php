<?php
// process_helpers.php
require_once 'config.php';
/**
 * Prints out a step/message during processing, flushes output buffer.
 * 
 * @param string $message
 */
if (!function_exists('echoStep')) {
    function echoStep(string $message): void {
        echo "<p>$message</p>";
        flush();
    }
}

/**
 * Adds a watermark to an image, scaled down to a certain percentage of the image’s width.
 * 
 * @param string $imagePath       The path to the image being watermarked
 * @param string $watermarkPath   The path to the watermark image
 * @param string $runDir          The working directory for temporary files
 */
if (!function_exists('addWatermark')) {
function addWatermark(string $imagePath, string $watermarkPath, string $runDir): void
{
    if (!file_exists($imagePath) || !file_exists($watermarkPath)) {
        return;
    }

    // Get width of the original image
    $identifyCmd = "identify -format '%w' " . escapeshellarg($imagePath);
    $width       = trim(shell_exec($identifyCmd));

    if (!is_numeric($width)) {
        return;
    }

    // Scale watermark to ~6% of the width (adjust as you like)
    $sigWidth  = floor($width * 0.06);
    $resizedWm = $runDir . '/signature_small_' . uniqid() . '.png';

    // Resize watermark
    $cmdResize = "convert " 
               . escapeshellarg($watermarkPath) 
               . " -resize {$sigWidth} " 
               . escapeshellarg($resizedWm);
    shell_exec($cmdResize . " 2>&1");

    // Composite it (place bottom-right with a little margin)
    $margin       = floor($width * 0.01);
    $cmdComposite = "convert " 
                  . escapeshellarg($imagePath) 
                  . " " 
                  . escapeshellarg($resizedWm)
                  . " -gravity southeast -geometry +{$margin}+{$margin} -composite "
                  . escapeshellarg($imagePath);
    shell_exec($cmdComposite . " 2>&1");

    @unlink($resizedWm);
}
}
/**
 * If you want, you can also centralize your config variables in here:
 */
$processedDir      = __DIR__ . '/processed'; // adjust as needed
$defaultWatermark  = __DIR__ . '/watermarks/muse_signature_black.png';
$allowedExtensions = ['jpg','jpeg','png','webp'];

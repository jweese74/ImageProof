<?php

/**
 * functions.php — Core helper utilities for image processing and watermarking
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Provides shared utility functions and configuration for image processing,
 * including watermark application, directory setup, logging steps, and file cleanup.
 * This module is called by controller scripts such as index.php and process.php.
 *
 * Key functions include:
 *  - echoStep() – real-time JavaScript feedback to browser
 *  - addWatermark() – overlays primary and randomised watermarks
 *  - clearProcessedFiles() – purges outdated output directories
 *
 * Security considerations:
 *  - Avoids HTML/script injection via message sanitisation
 *  - Uses filesystem isolation for user image operations
 *  - Employs shell command sanitisation via `escapeshellarg`
 *
 * @package    PixlKey
 * @subpackage Core\Helpers
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.1-alpha
 * @see        /core/config/config.php, /public/process.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session/SessionBootstrap.php';
\PixlKey\Session\startSecureSession();

$maxFileSizeMb     = 250;  // 250 MB
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'tiff', 'tif'];

$projectRoot       = dirname(__DIR__, 2);           // → project root containing /core, /public, etc.
$watermarkDir      = $projectRoot . '/watermarks';  // Storage for watermark images
$processedDir      = $projectRoot . '/processed';   // Where processed results go
$defaultWatermark = ''; // So no default watermark is used

// Ensure directories exist
if (!file_exists($watermarkDir)) {
    mkdir($watermarkDir, 0775, true);
}
if (!file_exists($processedDir)) {
    mkdir($processedDir, 0775, true);
}

/**
 * Minimal on-screen step messages
 */
/**
 * Enhanced echoStep function for real-time processing feedback
 */
if (!function_exists('echoStep')) {
    function echoStep($message, $type = 'info')
    {
        // Sanitize message for JavaScript
        $safeMessage = addslashes($message);

        // Determine the CSS class based on the type of message
        $class = 'step';
        if ($type === 'success') {
            $class .= ' success';
        } elseif ($type === 'error') {
            $class .= ' error';
        }

        // Output JavaScript to append the message to the steps container
        echo "<script>
            (function() {
                const stepsContainer = document.getElementById('steps');
                if (stepsContainer) {
                    const stepDiv = document.createElement('div');
                    stepDiv.className = '{$class}';
                    stepDiv.innerHTML = '{$safeMessage}';
                    stepsContainer.appendChild(stepDiv);
                    // Scroll to the latest step
                    stepsContainer.scrollTop = stepsContainer.scrollHeight;
                }
            })();
        </script>";

        // Flush the output buffer to ensure the message is sent to the browser immediately
        flush();
    }
}

/**
 * Silently clears older files, if needed, at midnight by cron or script (not shown to user).
 */
function clearProcessedFiles()
{
    global $processedDir;
    if (!is_dir($processedDir)) return;
    $files = scandir($processedDir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $processedDir . '/' . $f;
        if (is_dir($path)) {
            @system('rm -rf ' . escapeshellarg($path));
        } else {
            @unlink($path);
        }
    }
}

/**
 * Watermark function for additional random text overlay
 */
if (!function_exists('addWatermark')) {
    function addWatermark($imagePath, $mainWatermark, $runDir)
    {
        if (empty($mainWatermark) || !file_exists($mainWatermark)) {
            // User didn't upload a watermark or file doesn't exist -> skip
            return;
        }

        $dir      = dirname($imagePath);
        $filename = basename($imagePath);
        $tmpOut   = $dir . '/wm_' . $filename;

        if (!copy($imagePath, $tmpOut)) {
            echoStep("<span style='color:red;'>Error: Unable to copy for watermarking.</span>");
            return false;
        }

        // Identify dimensions
        $identify = "identify -format '%w %h' " . escapeshellarg($tmpOut);
        $dims     = trim(shell_exec($identify));
        if (!$dims) {
            echoStep("<span style='color:red;'>Error: Unable to get image dimensions.</span>");
            return false;
        }
        list($w, $h) = explode(' ', $dims);

        // Resize main watermark to 1/8 of the image height
        $wmHeight    = floor($h / 8);
        $resizedWm   = $runDir . '/resized_watermark.png';
        $cmdResizeWm = "convert " . escapeshellarg($mainWatermark) . " -resize x{$wmHeight} " . escapeshellarg($resizedWm);
        shell_exec($cmdResizeWm . " 2>&1");
        if (!file_exists($resizedWm)) {
            echoStep("<span style='color:red;'>Error: Unable to resize watermark.</span>");
            return false;
        }

        // Composite main watermark near bottom
        $wmW         = trim(shell_exec("identify -format '%w' " . escapeshellarg($resizedWm)));
        if (!is_numeric($wmW)) {
            echoStep("<span style='color:red;'>Error: Unable to get watermark width.</span>");
            @unlink($resizedWm);
            return false;
        }
        $offsetX     = floor($w / 2 - $wmW / 2);
        $offsetY     = $h - $wmHeight - 10;
        $cmdComposite = "convert " . escapeshellarg($tmpOut) . " " . escapeshellarg($resizedWm)
            . " -geometry +{$offsetX}+{$offsetY} -composite "
            . escapeshellarg($tmpOut);
        shell_exec($cmdComposite . " 2>&1");
        @unlink($resizedWm);

        if (!file_exists($tmpOut)) {
            echoStep("<span style='color:red;'>Error: Unable to apply main watermark.</span>");
            return false;
        }

        // Add random text watermarks (3 times)
        for ($i = 1; $i <= 5; $i++) {
            $angle     = rand(-45, 45);
            $x         = rand(0, $w);
            $y         = rand(0, $h);
            $pointSize = rand(6, 15);
            $r         = rand(0, 255);
            $g         = rand(0, 255);
            $b         = rand(0, 255);
            $a         = rand(1, 9) / 10.0;

            global $overlayText; // Ensure the variable is accessible if declared globally
            $overlayText = $overlayText ?? ''; // Default to empty if not set

            $cmdDraw = "convert " . escapeshellarg($tmpOut)
                . " -font 'DejaVu-Sans'"
                . " -pointsize $pointSize"
                . " -fill 'rgba($r,$g,$b,$a)'"
                . " -draw \"translate $x,$y rotate $angle text 0,0 '" . addslashes($overlayText) . "'\" "
                . escapeshellarg($tmpOut);
            shell_exec($cmdDraw . " 2>&1");
        }

        // Move final watermarked image back
        if (!rename($tmpOut, $imagePath)) {
            echoStep("<span style='color:red;'>Error: Unable to rename watermarked image.</span>");
            return false;
        }

        return true;
    }
}

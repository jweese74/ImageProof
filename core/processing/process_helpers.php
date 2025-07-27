<?php

/**
 * process_helpers.php — Utility functions for image processing and UI feedback
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Provides low-level helpers for real-time progress streaming and watermarking 
 * during artwork processing runs. This module supports image transformation 
 * using external tools (ImageMagick) and ensures secure shell execution via 
 * proper escaping. Also supports integration with the processing UI.
 *
 * @package    PixlKey
 * @subpackage Core\Processing
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.3-alpha
 * @see        /core/processing/process.php, /public/process.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session/SessionBootstrap.php';
require_once __DIR__ . '/../security/CsrfToken.php';

// Start secure session (idempotent)
\PixlKey\Session\startSecureSession();

// Expose CSRF helpers (for future POST actions or interactive processing scripts)
use function PixlKey\Security\generateToken as generate_csrf_token;
use function PixlKey\Security\validateToken as validate_csrf_token;
use function PixlKey\Security\rotateToken as rotate_csrf_token;

/**
 * Streams one progress line to the “Processing” page.
 *
 * @param string $message  Text to show
 * @param string $class    css class: info|success|error|download
 */
if (!function_exists('echoStep')) {
    function echoStep(string $message, string $class = 'info'): void
    {
        // Safely encode text for JavaScript/HTML
        $msgJs = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);

        echo "<script>
                (function(){
                    const box = document.getElementById('steps');
                    if (box) {
                        box.insertAdjacentHTML('beforeend',
                          '<div class=\"step {$class}\">' + {$msgJs} + '</div>');
                        box.scrollTop = box.scrollHeight;   // auto-scroll
                    }
                })();
              </script>";
        flush();   // critical: push chunk immediately
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
$defaultWatermark  = dirname(__DIR__, 2) . '/watermarks/muse_signature_black.png';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

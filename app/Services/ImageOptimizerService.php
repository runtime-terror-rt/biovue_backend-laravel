<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizerService
{
    /**
     * Store and optimize an uploaded image file preserving original aspect ratio and high fidelity.
     *
     * @param UploadedFile $file
     * @param string $folder e.g. 'profiles', 'products', 'partners'
     * @param int $maxWidth Max width to proportionally bound camera raw photos (default 1920px)
     * @param int $maxHeight Max height to proportionally bound camera raw photos (default 1920px)
     * @param int $quality JPEG/WebP quality (85 = visually lossless, ultra high quality)
     * @return string Stored relative storage path (e.g. 'profiles/xyz.jpg')
     */
    public static function storeOptimized(
        UploadedFile $file,
        string $folder = 'profiles',
        int $maxWidth = 1920,
        int $maxHeight = 1920,
        int $quality = 85
    ): string {
        // Fallback to normal Laravel store if GD is not available
        if (!extension_loaded('gd')) {
            return $file->store($folder, 'public');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $mime = $file->getMimeType();

        // Pass SVG and animated GIF directly without modification
        if ($extension === 'svg' || $mime === 'image/svg+xml' || $extension === 'gif' || $mime === 'image/gif') {
            return $file->store($folder, 'public');
        }

        $realPath = $file->getRealPath();
        $imageInfo = @getimagesize($realPath);
        if (!$imageInfo) {
            return $file->store($folder, 'public');
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];

        // Load image resource based on type
        $srcImage = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $srcImage = @imagecreatefromjpeg($realPath);
                break;
            case IMAGETYPE_PNG:
                $srcImage = @imagecreatefrompng($realPath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImage = @imagecreatefromwebp($realPath);
                }
                break;
        }

        if (!$srcImage) {
            return $file->store($folder, 'public');
        }

        // Auto-fix EXIF orientation for mobile camera photos
        if ($imageInfo[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $srcImage = imagerotate($srcImage, 180, 0);
                        break;
                    case 6:
                        $srcImage = imagerotate($srcImage, -90, 0);
                        $temp = $origWidth;
                        $origWidth = $origHeight;
                        $origHeight = $temp;
                        break;
                    case 8:
                        $srcImage = imagerotate($srcImage, 90, 0);
                        $temp = $origWidth;
                        $origWidth = $origHeight;
                        $origHeight = $temp;
                        break;
                }
            }
        }

        // Calculate proportional scale preserving exact aspect ratio (no distortion)
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
        $targetWidth = (int) round($origWidth * $ratio);
        $targetHeight = (int) round($origHeight * $ratio);

        $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for PNG and WebP
        if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_WEBP) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        // High quality bicubic resampling
        imagecopyresampled(
            $dstImage,
            $srcImage,
            0, 0, 0, 0,
            $targetWidth,
            $targetHeight,
            $origWidth,
            $origHeight
        );

        $filename = Str::random(40) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
        $relativeFolder = trim($folder, '/');
        $targetDir = storage_path('app/public/' . $relativeFolder);

        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $targetFullPath = $targetDir . '/' . $filename;

        // Save with high quality compression
        switch ($imageInfo[2]) {
            case IMAGETYPE_PNG:
                // PNG compression level 0-9 (8 is high compression, lossless)
                imagepng($dstImage, $targetFullPath, 8);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($dstImage, $targetFullPath, $quality);
                break;
            case IMAGETYPE_JPEG:
            default:
                imagejpeg($dstImage, $targetFullPath, $quality);
                break;
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $relativeFolder . '/' . $filename;
    }

    /**
     * Optimize an existing image file on disk in-place.
     */
    public static function optimizeInPlace(
        string $filePath,
        int $maxWidth = 1920,
        int $maxHeight = 1920,
        int $quality = 85
    ): bool {
        if (!extension_loaded('gd') || !file_exists($filePath)) {
            return false;
        }

        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $origSize = filesize($filePath);

        // Don't re-compress if it's already under 300KB and within bounds
        if ($origSize < 300 * 1024 && $origWidth <= $maxWidth && $origHeight <= $maxHeight) {
            return false;
        }

        $srcImage = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $srcImage = @imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $srcImage = @imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImage = @imagecreatefromwebp($filePath);
                }
                break;
        }

        if (!$srcImage) {
            return false;
        }

        // Auto-fix orientation if JPEG
        if ($imageInfo[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($filePath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $srcImage = imagerotate($srcImage, 180, 0);
                        break;
                    case 6:
                        $srcImage = imagerotate($srcImage, -90, 0);
                        $temp = $origWidth;
                        $origWidth = $origHeight;
                        $origHeight = $temp;
                        break;
                    case 8:
                        $srcImage = imagerotate($srcImage, 90, 0);
                        $temp = $origWidth;
                        $origWidth = $origHeight;
                        $origHeight = $temp;
                        break;
                }
            }
        }

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
        $targetWidth = (int) round($origWidth * $ratio);
        $targetHeight = (int) round($origHeight * $ratio);

        $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_WEBP) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $dstImage,
            $srcImage,
            0, 0, 0, 0,
            $targetWidth,
            $targetHeight,
            $origWidth,
            $origHeight
        );

        $tempPath = $filePath . '.tmp';

        switch ($imageInfo[2]) {
            case IMAGETYPE_PNG:
                imagepng($dstImage, $tempPath, 8);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($dstImage, $tempPath, $quality);
                break;
            case IMAGETYPE_JPEG:
            default:
                imagejpeg($dstImage, $tempPath, $quality);
                break;
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        if (file_exists($tempPath) && filesize($tempPath) < $origSize) {
            rename($tempPath, $filePath);
            return true;
        }

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        return false;
    }
}

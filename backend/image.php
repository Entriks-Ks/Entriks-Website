<?php
require "gridfs.php";

$id = $_GET["id"] ?? null;

if (!$id) {
    http_response_code(400);
    die("Missing image ID");
}

try {
    // Verify it exists in GridFS first
    $fileMetadata = getImageMetadataFromGridFS($id);
    if (!$fileMetadata) {
         http_response_code(404);
         die("Image not found");
    }

    $imageData = getImageFromGridFS($id);
    
    if (!$imageData) {
        http_response_code(404);
        die("Image content not found");
    }
    
    // Detect MIME type from the image data
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_buffer($finfo, $imageData);
    finfo_close($finfo);
    
    // Default to JPEG if detection fails
    if (!$mimeType || strpos($mimeType, 'image/') === false) {
        $mimeType = 'image/jpeg';
    }

    $filename = $fileMetadata['filename'] ?? 'image.jpg';

    // Handle resizing if GD is available and width/height is requested
    $width = isset($_GET['width']) ? (int)$_GET['width'] : 0;
    $height = isset($_GET['height']) ? (int)$_GET['height'] : 0;

    if (($width > 0 || $height > 0) && extension_loaded('gd')) {
        try {
            $srcImage = imagecreatefromstring($imageData);
            if ($srcImage) {
                // Get original dimensions
                $origWidth = imagesx($srcImage);
                $origHeight = imagesy($srcImage);

                // Calculate new dimensions ensuring valid integers
                if ($width > 0 && $height > 0) {
                    $newWidth = $width;
                    $newHeight = $height;
                } elseif ($width > 0) {
                    $newWidth = $width;
                    $newHeight = (int)(($origHeight / $origWidth) * $width);
                } else {
                    $newHeight = $height;
                    $newWidth = (int)(($origWidth / $origHeight) * $height);
                }
                
                // create new image resource
                $dstImage = imagecreatetruecolor($newWidth, $newHeight);
                
                // Preserve transparency for PNG/WEBP
                if ($mimeType == 'image/png' || $mimeType == 'image/webp') {
                    imagealphablending($dstImage, false);
                    imagesavealpha($dstImage, true);
                    $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
                    imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
                }

                // Resample
                imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                // Output buffer capture
                ob_start();
                switch ($mimeType) {
                    case 'image/png':
                        imagepng($dstImage, null, 9);
                        break;
                    case 'image/gif':
                        imagegif($dstImage);
                        break;
                    case 'image/webp':
                        imagewebp($dstImage, null, 80);
                        break;
                    case 'image/jpeg':
                    default:
                        imagejpeg($dstImage, null, 85); // 85% quality
                        break;
                }
                $imageData = ob_get_clean();
                
                // Cleanup
                imagedestroy($srcImage);
                imagedestroy($dstImage);

                // Update content length header
                header('Content-Type: ' . $mimeType);
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Cache-Control: public, max-age=3600');
                header('Content-Length: ' . strlen($imageData));
                echo $imageData;
                exit; // Stop execution after sending resized image
            }
        } catch (Exception $e) {
            // Fallback to original if resizing fails
            error_log("Resize failed: " . $e->getMessage());
        }
    }
    
    // Set proper headers (Original Image Fallback)
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . strlen($imageData));
    
    echo $imageData;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Image retrieval error: " . $e->getMessage());
    die("Error loading image");
}

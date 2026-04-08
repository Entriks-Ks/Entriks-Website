<?php
require __DIR__ . '/database.php';

$bucket = $db->selectGridFSBucket();

function uploadImageToGridFS($file)
{
    global $bucket;

    // Check if file was actually uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        error_log('Image upload: No tmp_name provided');
        return null;
    }

    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error: ' . $file['error']
        };
        error_log('Image upload error: ' . $errorMsg);
        return null;
    }

    // Check if file exists
    if (!file_exists($file['tmp_name'])) {
        error_log('Image upload: Temporary file does not exist: ' . $file['tmp_name']);
        return null;
    }

    // Limit file size to 20MB to prevent timeout
    $maxSize = 20971520;  // 20MB
    $fileSize = filesize($file['tmp_name']);
    if ($fileSize > $maxSize) {
        error_log('Image too large: ' . $fileSize . ' bytes (max: ' . $maxSize . ')');
        echo '<div style="color:red;text-align:center;margin:20px;">Image too large (' . round($fileSize / 1048576, 2) . ' MB). Maximum allowed is 20MB.</div>';
        return null;
    }

    try {
        // Set timeout for upload
        set_time_limit(30);

        $stream = fopen($file['tmp_name'], 'rb');
        if (!$stream) {
            error_log('Image upload: Failed to open temporary file: ' . $file['tmp_name']);
            return null;
        }

        $filename = $file['name'];
        error_log('Uploading image: ' . $filename . ' (size: ' . $fileSize . ' bytes)');

        $id = $bucket->uploadFromStream($filename, $stream);
        fclose($stream);

        error_log('Image successfully uploaded with ID: ' . (string) $id);
        return (string) $id;
    } catch (Exception $e) {
        error_log('GridFS upload error: ' . $e->getMessage());
        return null;
    }
}

function getImageFromGridFS($id)
{
    global $bucket;

    try {
        // Handle both string and ObjectId inputs
        if (is_string($id)) {
            $id = new MongoDB\BSON\ObjectId($id);
        }

        $stream = $bucket->openDownloadStream($id);
        $data = stream_get_contents($stream);

        if ($data === false) {
            error_log('Failed to read image stream for ID: ' . (string) $id);
            return null;
        }

        return $data;
    } catch (Exception $e) {
        error_log('GridFS retrieval error for ID ' . (string) $id . ': ' . $e->getMessage());
        return null;
    }
}

function getImageMetadataFromGridFS($id)
{
    global $bucket;

    try {
        if (is_string($id)) {
            $id = new MongoDB\BSON\ObjectId($id);
        }

        // Find file metadata
        $file = $bucket->findOne(['_id' => $id]);

        return $file;
    } catch (Exception $e) {
        error_log('GridFS metadata retrieval error for ID ' . (string) $id . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Upload raw image data to GridFS and return the string id.
 * $data: binary string of the image
 * $filename: original filename to store
 */
function uploadImageDataToGridFS($data, $filename = 'remote_image')
{
    global $bucket;
    try {
        // Basic validation
        if (empty($data)) {
            error_log('uploadImageDataToGridFS: empty data');
            return null;
        }

        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $data);
        finfo_close($finfo);
        if (!$mime || strpos($mime, 'image/') === false) {
            error_log('uploadImageDataToGridFS: not an image, mime=' . var_export($mime, true));
            return null;
        }

        // Create a stream from the data
        $stream = fopen('php://temp', 'rb+');
        if ($stream === false) {
            error_log('uploadImageDataToGridFS: failed to open temp stream');
            return null;
        }
        fwrite($stream, $data);
        rewind($stream);

        $id = $bucket->uploadFromStream($filename, $stream);
        fclose($stream);
        return (string) $id;
    } catch (Exception $e) {
        error_log('uploadImageDataToGridFS error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Download a remote URL (binary) using cURL with sensible defaults.
 * Returns binary string on success, or false on failure.
 */
function downloadRemoteUrl($url, $maxBytes = 5242880)
{
    // Basic validation
    if (!filter_var($url, FILTER_VALIDATE_URL))
        return false;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EntriksBot/1.0');
    // SSL: allow self-signed temporarily if server misconfigured (keeps previous behavior)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $data = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $data === '' || $code >= 400) {
        error_log('downloadRemoteUrl failed: code=' . $code . ' err=' . $err . ' url=' . $url);
        return false;
    }

    if ($maxBytes && strlen($data) > $maxBytes) {
        error_log('downloadRemoteUrl: downloaded size exceeds maxBytes for url=' . $url . ' size=' . strlen($data));
        return false;
    }

    return $data;
}

/**
 * Deletes an image from GridFS by its ObjectId string
 */
function deleteFileFromGridFS($idString)
{
    global $db;
    if (!$idString)
        return false;

    try {
        $bucket = $db->selectGridFSBucket();
        $id = ($idString instanceof MongoDB\BSON\ObjectId) ? $idString : new MongoDB\BSON\ObjectId($idString);
        $bucket->delete($id);
        return true;
    } catch (Exception $e) {
        error_log('GridFS Deletion Error: ' . $e->getMessage());
        return false;
    }
}

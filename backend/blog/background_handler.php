<?php
/**
 * Ultra-fast Non-Blocking Translation Trigger
 * Spawns a completely separate PHP process for translation
 * Page load returns IMMEDIATELY while translation happens in background
 */

function spawnBackgroundTranslation($postId) {
    $scriptPath = __DIR__ . '/background_translator.php';
    $postId = escapeshellarg($postId);
    
    // Windows
    if (php_uname('s') === 'Windows NT') {
        // Use Windows popen for non-blocking execution
        $cmd = "start /B php.exe \"$scriptPath\" $postId";
        popen($cmd, 'r');
    } 
    // Linux/Mac
    else {
        // Use nohup for non-blocking execution
        $cmd = "nohup php \"$scriptPath\" $postId > /dev/null 2>&1 &";
        shell_exec($cmd);
    }
}

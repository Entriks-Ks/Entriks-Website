<?php
// backend/refactor_replace.php

function refactorFile($filePath)
{
    echo "Refactoring $filePath...\n";
    $content = file_get_contents($filePath);

    $pattern = '/get_content\s*\(\s*([\'"].+?[\'"])\s*,\s*[\'"](?:[^\'"\\\\]|\\\\.)*[\'"]\s*\)/s';

    $newContent = preg_replace_callback($pattern, function ($matches) {
        return 'renderBlock(' . $matches[1] . ')';
    }, $content);

    if ($newContent === null) {
        echo "Error in regex replacement.\n";
        return;
    }

    if ($content === $newContent) {
        echo "No changes made (no matches found or already refactored).\n";
    } else {
        file_put_contents($filePath, $newContent);
        echo "File updated successfully.\n";
    }
}

refactorFile('../index.php');
refactorFile('../index-en.php');
?>

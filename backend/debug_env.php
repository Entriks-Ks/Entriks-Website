<?php
$envFile = __DIR__ . "/../.env";
echo "Looking for: $envFile\n";
echo "File exists: " . (file_exists($envFile) ? 'YES' : 'NO') . "\n";

if (file_exists($envFile)) {
    $contents = file_get_contents($envFile);
    echo "File size: " . strlen($contents) . " bytes\n";
    echo "Raw contents: " . var_export($contents, true) . "\n\n";
    
    $lines = explode("\n", $contents);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
            echo "Set: $key = $value\n";
        }
    }
}

echo "\nTesting getenv:\n";
$apiKey = getenv('GOOGLE_API_KEY');
echo "Result: " . var_export($apiKey, true) . "\n";
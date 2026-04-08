<?php
// Dotenv loader — reads .env file but does NOT overwrite vars already set by the host
// (e.g. Render, Railway, Docker env vars take priority over .env file)
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (!file_exists($envFile)) {
    $envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';
}

if (file_exists($envFile)) {
    $lines = explode("\n", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Strip surrounding quotes: KEY="value" or KEY='value'
            $value = preg_replace('/^(["\'])(.*)\1$/', '$2', $value);
            // Only set if NOT already defined by the host environment
            if (getenv($key) === false && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

foreach (['MONGO_URI', 'MONGO_DB', 'GOOGLE_API_KEY', 'GEMINI_API_KEY'] as $_envKey) {
    if (!isset($_ENV[$_envKey]) && getenv($_envKey) !== false) {
        $_ENV[$_envKey] = getenv($_envKey);
    }
}
unset($_envKey);

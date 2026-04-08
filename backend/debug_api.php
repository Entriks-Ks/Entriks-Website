<?php
require __DIR__ . "/vendor/autoload.php";

// Load environment variables
$envFile = __DIR__ . "/../.env";
if (file_exists($envFile)) {
    $lines = explode("\n", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$apiKey = $_ENV['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY');
echo "API Key: " . ($apiKey ? 'Loaded (' . strlen($apiKey) . ' chars)' : 'NOT FOUND') . "\n\n";

$text = "Warum digitale Sichtbarkeit heute über Erfolg entscheidet";
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$data = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => 'Translate this German text to English. IMPORTANT: Keep all HTML tags, attributes, and structure EXACTLY as they are. Only translate the text content inside the tags, NOT the HTML code itself.' . "\n\n" . $text
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.3,
        'maxOutputTokens' => 8192
    ]
];

echo "Sending request to Gemini API...\n";
echo "Input: $text\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n" . $response . "\n\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $translated = $result['candidates'][0]['content']['parts'][0]['text'];
        echo "Translated: $translated\n";
    } else {
        echo "ERROR: Could not find translation in response\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "ERROR: API returned non-200 status\n";
}
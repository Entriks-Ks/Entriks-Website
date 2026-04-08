<?php
function translateToEnglish($text, $debug = false)
{
    // Build raw text when input is array
    if (is_array($text)) {
        $prompt = '';
        if (!empty($text['title'])) {
            $prompt .= "Title:\n" . $text['title'] . "\n\n";
        }
        if (!empty($text['content'])) {
            $prompt .= "Content:\n" . $text['content'] . "\n\n";
        }
        $text = trim($prompt);
    }

    // Load API key (GOOGLE_API_KEY or GEMINI_API_KEY)
    $apiKey = $_ENV['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY') 
        ?? $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') 
        ?? null;
    if (!$apiKey) {
        return $text;
    }

    // Gemini endpoint
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    $headers = ['Content-Type: application/json'];

    $instruction =
        "Translate the text from German to English.\n"
        . "IMPORTANT RULES:\n"
        . "- Keep HTML exactly as it is.\n"
        . "- Do NOT change formatting.\n"
        . "- Do NOT add explanations.\n"
        . "- Do NOT rewrite style.\n"
        . "- Output ONLY the translated English text.\n\n";

    // Request body
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $instruction . $text]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => 16384,
            'topP' => 1,
            'topK' => 1
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($curlError) {
        $msg = date('Y-m-d H:i:s') . ' Translation API cURL error: ' . $curlError;
    }
    if ($httpCode !== 200 && $response) {
        $msg = date('Y-m-d H:i:s') . " Translation API error: HTTP $httpCode - " . $response;
    }

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($result['candidates'][0]['content']['parts'][0]['text']);
        }
    }

    return $text;
}

function translateMultipleToEnglish($texts, $debug = false)
{
    // Load API key (GOOGLE_API_KEY or GEMINI_API_KEY)
    $apiKey = $_ENV['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY') 
        ?? $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') 
        ?? null;

    if (!$apiKey) {
        if ($debug)
            echo "ERROR: API key not found\n";
        return $texts;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    $headers = ['Content-Type: application/json'];

    $multiHandle = curl_multi_init();
    $curlHandles = [];

    // Create a curl handle for each text
    foreach ($texts as $key => $text) {
        $data = [
            'contents' => [[
                'parts' => [[
                    'text' => "You are a professional translation agent. Translate the following German blog data to English as fast and accurately as possible.\n"
                        . "- Keep all HTML tags and formatting unchanged.\n"
                        . "- Translate the title and main content.\n"
                        . "- Do not translate names or user handles.\n"
                        . "\n" . $text
                ]]
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 16384,
                'topP' => 0.8,
                'topK' => 40
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$key] = $ch;
    }

    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    // Collect results
    $results = [];
    foreach ($curlHandles as $key => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $results[$key] = trim($result['candidates'][0]['content']['parts'][0]['text']);
            } else {
                $results[$key] = $texts[$key];
            }
        } else {
            error_log("Translation API error for key $key: HTTP $httpCode");
            $results[$key] = $texts[$key];
        }

        curl_multi_remove_handle($multiHandle, $ch);
    }

    curl_multi_close($multiHandle);
    return $results;
}

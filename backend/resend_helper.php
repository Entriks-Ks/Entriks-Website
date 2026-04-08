<?php

function sendResendEmail($to, $subject, $html)
{
    $apiKey = 're_JSzwEGXb_B5r6yEEby1b4wugNrLSJ9Ftb';
    $url = 'https://api.resend.com/emails';

    $data = [
        'from' => 'Entriks Dashboard <dashboard@web.entriks.com>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'response' => $response];
    } else {
        error_log('Resend API Error (HTTP ' . $httpCode . '): ' . $response);
        $decoded = json_decode($response, true);
        $errorMsg = $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';
        return ['success' => false, 'error' => $errorMsg, 'httpCode' => $httpCode];
    }
}

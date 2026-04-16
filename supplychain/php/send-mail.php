<?php
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Invalid request');
    }

    // Collect and sanitize all form fields
    $name        = strip_tags(trim($_POST['name']        ?? ''));
    $unternehmen = strip_tags(trim($_POST['unternehmen'] ?? ''));
    $rolle       = strip_tags(trim($_POST['rolle']       ?? ''));
    $raw_email   = trim($_POST['email'] ?? '');
    $telefon     = strip_tags(trim($_POST['telefon']     ?? ''));
    $suchanfrage = strip_tags(trim($_POST['suchanfrage'] ?? ''));
    $anrufzeit   = strip_tags(trim($_POST['anrufzeit']   ?? ''));

    // Validate required fields
    if (empty($name) || empty($unternehmen) || empty($raw_email)) {
        http_response_code(400);
        die('Bitte füllen Sie alle Pflichtfelder aus (Name, Unternehmen, E-Mail).');
    }

    $email = filter_var($raw_email, FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        http_response_code(400);
        die('Ungültige E-Mail-Adresse.');
    }

    $to           = 'kontakt@entriks.com';
    $subject_line = 'Neue Gesprächsanfrage von ' . $name . ' (' . $unternehmen . ')';

    $body  = "Neue Gesprächsanfrage über die ENTRIKS-Website\n";
    $body .= str_repeat('=', 50) . "\n\n";
    $body .= "Name:               " . $name                           . "\n";
    $body .= "Unternehmen:        " . $unternehmen                    . "\n";
    $body .= "Rolle:              " . ($rolle       ?: '–')           . "\n";
    $body .= "E-Mail:             " . $email                          . "\n";
    $body .= "Telefon:            " . ($telefon     ?: '–')           . "\n";
    $body .= "Suchanfrage:        " . ($suchanfrage ?: '–')           . "\n";
    $body .= "Anrufwunsch:        " . ($anrufzeit   ?: '–')           . "\n\n";
    $body .= str_repeat('=', 50) . "\n";
    $body .= "Diese Nachricht wurde automatisch über das Kontaktformular auf entriks.com generiert.\n";

    $host         = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'entriks.com');
    $from_address = 'no-reply@' . $host;

    $headers  = 'From: ENTRIKS Website <' . $from_address . ">\r\n";
    $headers .= 'Reply-To: ' . $email . "\r\n";
    $headers .= 'Sender: '   . $from_address . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (mail($to, $subject_line, $body, $headers)) {
        // Redirect back with success indicator
        header('Location: index.html?mail=sent');
        exit;
    } else {
        http_response_code(500);
        echo 'Fehler beim Senden. Bitte versuchen Sie es erneut oder kontaktieren Sie uns direkt.';
    }
?>
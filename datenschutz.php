<?php
require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/config.php';

// Site settings with defaults - loaded from DB if available
$siteName = 'ENTRIKS';
$contactEmail = 'info@entriks.com';
$contactPhone = '+383 43 889 344';
$contactAddress = 'Lot Vaku L2.1, 10000 Pristina, Kosovo';
$socialLinkedin = 'https://www.linkedin.com/company/entriks';
$socialFacebook = 'https://www.facebook.com/ENTRIKS/';
$socialInstagram = 'https://www.instagram.com/entriks_/';
$siteFaviconUrl = 'assets/img/favicon.png';
$logoUrl = 'assets/img/logo.png';
$footerLogoUrl = 'assets/img/logo.png';

// Load from database settings if available
if (isset($db)) {
    try {
        $settings = $db->settings->findOne(['type' => 'global_config']);
        if ($settings) {
            if (!empty($settings['site_name'])) $siteName = $settings['site_name'];
            if (!empty($settings['contact_email'])) $contactEmail = $settings['contact_email'];
            if (!empty($settings['contact_phone'])) $contactPhone = $settings['contact_phone'];
            if (!empty($settings['contact_address'])) $contactAddress = $settings['contact_address'];
            if (!empty($settings['social_linkedin'])) $socialLinkedin = $settings['social_linkedin'];
            if (!empty($settings['social_facebook'])) $socialFacebook = $settings['social_facebook'];
            if (!empty($settings['social_instagram'])) $socialInstagram = $settings['social_instagram'];
            if (!empty($settings['favicon_url'])) $siteFaviconUrl = $settings['favicon_url'];
            if (!empty($settings['logo_url'])) $logoUrl = $settings['logo_url'];
            if (!empty($settings['footer_logo_url'])) $footerLogoUrl = $settings['footer_logo_url'];
        }
    } catch (Exception $e) {
        // Use defaults on error
    }
}

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'de';
if ($lang !== 'de' && $lang !== 'en') $lang = 'de';

$content = [
    'de' => [
        'title' => 'Datenschutz | ENTRIKS Talent Hub - Datenschutzerklärung',
        'description' => 'Datenschutzerklärung von ENTRIKS Talent Hub. Ihre Daten sind sicher – erfahren Sie mehr über den Umgang mit personenbezogenen Daten bei ENTRIKS.',
        'page_title' => 'Datenschutzerklärung',
        'nav_links' => ['Nearshoring', 'Active Sourcing', 'Blog', 'Über uns', 'Kontakt'],
        'sections' => [
            ['title' => '1. Name und Kontaktdaten des für die Verarbeitung Verantwortlichen', 'content' => '<p>Diese Datenschutz-Information gilt für die Datenverarbeitung durch:</p><div class="info-block"><strong>Verantwortlicher:</strong><br>ENTRIKS L.L.C.<br>Lot Vaku, L 2.1<br>10000 Pristina<br>Kosovo<br><br>E-Mail: <a href="mailto:info@entriks.com">info@entriks.com</a><br>Website: <a href="https://talent.entriks.com">talent.entriks.com</a></div>'],
            ['title' => '2. Erhebung und Speicherung personenbezogener Daten', 'content' => '<p>Wir erheben personenbezogene Daten, wenn Sie uns diese im Rahmen einer Kontaktaufnahme oder bei der Nutzung unserer Dienstleistungen freiwillig mitteilen. Dies umfasst:</p><ul><li>Name, Vorname</li><li>E-Mail-Adresse</li><li>Telefonnummer</li><li>Firmenname (bei geschäftlicher Anfrage)</li><li>Nachrichteninhalt bei Kontaktformularen</li></ul><p>Die Verarbeitung dieser Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) bzw. Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse) oder auf Grundlage Ihrer Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).</p>'],
            ['title' => '3. Zweck der Datenverarbeitung', 'content' => '<p>Wir nutzen die erhobenen Daten für folgende Zwecke:</p><ul><li>Zur Beantwortung Ihrer Anfragen und zur Kommunikation mit Ihnen</li><li>Zur Bereitstellung unserer Dienstleistungen (Nearshoring, Active Sourcing)</li><li>Zur Vertragsanbahnung und -erfüllung</li><li>Zur Qualitätssicherung und Verbesserung unserer Angebote</li></ul>'],
            ['title' => '4. Weitergabe von Daten', 'content' => '<p>Eine Übermittlung Ihrer persönlichen Daten an Dritte zu anderen als den im Folgenden aufgeführten Zwecken findet nicht statt:</p><ul><li><strong>Im Rahmen der Dienstleistungserbringung:</strong> Bei Nearshoring-Dienstleistungen übermitteln wir Ihre Daten an potenzielle Kandidaten oder Teammitglieder, soweit dies für die Dienstleistungserbringung erforderlich ist.</li><li><strong>Aufgrund gesetzlicher Verpflichtungen:</strong> Wir können Ihre Daten offenlegen, wenn wir dazu gesetzlich verpflichtet sind oder zur Durchsetzung unserer Rechte.</li></ul><p>Alle Empfänger sind zur Einhaltung der Datenschutzbestimmungen verpflichtet.</p>'],
            ['title' => '5. Speicherdauer', 'content' => '<p>Wir speichern Ihre personenbezogenen Daten nur so lange, wie dies zur Erreichung der genannten Zwecke erforderlich ist oder wie es gesetzliche Aufbewahrungsfristen vorsehen. Sobald Ihre Daten nicht mehr benötigt werden, werden sie gelöscht oder anonymisiert.</p><p>Im Falle einer Einwilligung können Sie diese jederzeit mit Wirkung für die Zukunft widerrufen.</p>'],
            ['title' => '6. Ihre Rechte', 'content' => '<p>Sie haben das Recht:</p><ul><li>gemäß Art. 15 DSGVO Auskunft über Ihre von uns verarbeiteten personenbezogenen Daten zu verlangen</li><li>gemäß Art. 16 DSGVO unverzüglich die Berichtigung unrichtiger oder Vervollständigung Ihrer bei uns gespeicherten personenbezogenen Daten zu verlangen</li><li>gemäß Art. 17 DSGVO die Löschung Ihrer bei uns gespeicherten personenbezogenen Daten zu verlangen</li><li>gemäß Art. 18 DSGVO die Einschränkung der Verarbeitung Ihrer personenbezogenen Daten zu verlangen</li><li>gemäß Art. 20 DSGVO Ihre personenbezogenen Daten in einem strukturierten, gängigen und maschinenlesbaren Format zu erhalten</li><li>gemäß Art. 7 Abs. 3 DSGVO Ihre einmal erteilte Einwilligung jederzeit gegenüber uns zu widerrufen</li><li>gemäß Art. 77 DSGVO sich bei einer Aufsichtsbehörde zu beschweren</li></ul>'],
            ['title' => '7. Cookies und Tracking', 'content' => '<p>Unsere Website verwendet Cookies, um die Benutzerfreundlichkeit zu verbessern. Bei Cookies handelt es sich um kleine Textdateien, die auf Ihrem Endgerät gespeichert werden.</p><p>Einige Cookies sind technisch notwendig, um grundlegende Funktionen der Website bereitzustellen. Andere Cookies dienen anonymisierten Analysezwecken.</p><p>Sie können Ihren Browser so einstellen, dass er Sie über das Setzen von Cookies informiert und Cookies nur im Einzelfall erlaubt.</p>'],
            ['title' => '8. Datensicherheit', 'content' => '<p>Wir setzen technische und organisatorische Sicherheitsmaßnahmen ein, um Ihre Daten gegen Manipulation, Verlust, Zerstörung oder gegen den Zugriff unberechtigter Personen zu schützen. Unsere Sicherheitsmaßnahmen werden entsprechend der technologischen Entwicklung fortlaufend verbessert.</p><p>Die Übertragung sensibler Daten erfolgt verschlüsselt (SSL/TLS).</p>'],
            ['title' => '9. Änderungen der Datenschutzerklärung', 'content' => '<p>Wir behalten uns vor, diese Datenschutzerklärung anzupassen, um sie an geänderte Rechtslagen oder Änderungen unserer Dienstleistungen sowie der Datenverarbeitung anzupassen. Die jeweils aktuelle Version ist auf unserer Website verfügbar.</p>'],
            ['title' => '10. Kontakt Datenschutz', 'content' => '<p>Bei Fragen zur Verarbeitung Ihrer persönlichen Daten oder zur Wahrnehmung Ihrer oben genannten Rechte wenden Sie sich bitte an:</p><div class="info-block"><strong>Datenschutzbeauftragter:</strong><br>ENTRIKS L.L.C.<br>E-Mail: <a href="mailto:info@entriks.com">info@entriks.com</a></div>']
        ],
        'footer' => [
            'desc' => 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo – durch Nearshoring und Active Sourcing.',
            'contact' => 'Kontakt',
            'services' => 'Leistungen',
            'company' => 'Unternehmen',
            'copyright' => '© ' . date('Y') . ' ENTRIKS Talent Hub | Teil der ENTRIKS Group',
            'links' => ['Impressum', 'Datenschutz', 'AGB']
        ]
    ],
    'en' => [
        'title' => 'Privacy Policy | ENTRIKS Talent Hub - Data Protection Declaration',
        'description' => 'Privacy Policy of ENTRIKS Talent Hub. Your data is secure – learn more about the handling of personal data at ENTRIKS.',
        'page_title' => 'Privacy Policy',
        'nav_links' => ['Nearshoring', 'Active Sourcing', 'Blog', 'About Us', 'Contact'],
        'sections' => [
            ['title' => '1. Name and Contact Details of the Data Controller', 'content' => '<p>This privacy information applies to data processing by:</p><div class="info-block"><strong>Controller:</strong><br>ENTRIKS L.L.C.<br>Lot Vaku, L 2.1<br>10000 Pristina<br>Kosovo<br><br>Email: <a href="mailto:info@entriks.com">info@entriks.com</a><br>Website: <a href="https://talent.entriks.com">talent.entriks.com</a></div>'],
            ['title' => '2. Collection and Storage of Personal Data', 'content' => '<p>We collect personal data when you voluntarily provide it to us in the context of contacting us or using our services. This includes:</p><ul><li>First name, last name</li><li>Email address</li><li>Phone number</li><li>Company name (for business inquiries)</li><li>Message content from contact forms</li></ul><p>The processing of this data is based on Art. 6 para. 1 lit. b GDPR (contract fulfillment) or Art. 6 para. 1 lit. f GDPR (legitimate interest) or on the basis of your consent (Art. 6 para. 1 lit. a GDPR).</p>'],
            ['title' => '3. Purpose of Data Processing', 'content' => '<p>We use the collected data for the following purposes:</p><ul><li>To respond to your inquiries and communicate with you</li><li>To provide our services (Nearshoring, Active Sourcing)</li><li>For contract initiation and fulfillment</li><li>For quality assurance and improvement of our offerings</li></ul>'],
            ['title' => '4. Disclosure of Data', 'content' => '<p>We do not transfer your personal data to third parties for purposes other than those listed below:</p><ul><li><strong>In the context of service provision:</strong> For nearshoring services, we may share your data with potential candidates or team members to the extent necessary for service provision.</li><li><strong>Due to legal obligations:</strong> We may disclose your data if we are legally required to do so or to enforce our rights.</li></ul><p>All recipients are required to comply with data protection regulations.</p>'],
            ['title' => '5. Storage Duration', 'content' => '<p>We store your personal data only as long as necessary to achieve the stated purposes or as required by legal retention periods. Once your data is no longer needed, it will be deleted or anonymized.</p><p>In the case of consent, you can revoke it at any time with effect for the future.</p>'],
            ['title' => '6. Your Rights', 'content' => '<p>You have the right to:</p><ul><li>request information about your personal data processed by us according to Art. 15 GDPR</li><li>demand immediate correction of inaccurate or completion of your personal data stored by us according to Art. 16 GDPR</li><li>request deletion of your personal data stored by us according to Art. 17 GDPR</li><li>request restriction of processing of your personal data according to Art. 18 GDPR</li><li>receive your personal data in a structured, commonly used and machine-readable format according to Art. 20 GDPR</li><li>revoke your consent given to us at any time according to Art. 7 para. 3 GDPR</li><li>lodge a complaint with a supervisory authority according to Art. 77 GDPR</li></ul>'],
            ['title' => '7. Cookies and Tracking', 'content' => '<p>Our website uses cookies to improve user-friendliness. Cookies are small text files stored on your device.</p><p>Some cookies are technically necessary to provide basic website functions. Other cookies are used for anonymized analysis purposes.</p><p>You can set your browser to inform you about the setting of cookies and allow cookies only on a case-by-case basis.</p>'],
            ['title' => '8. Data Security', 'content' => '<p>We implement technical and organizational security measures to protect your data from manipulation, loss, destruction or access by unauthorized persons. Our security measures are continuously improved in line with technological developments.</p><p>The transmission of sensitive data is encrypted (SSL/TLS).</p>'],
            ['title' => '9. Changes to the Privacy Policy', 'content' => '<p>We reserve the right to adapt this privacy policy to adjust it to changes in legal requirements or changes to our services and data processing. The current version is available on our website.</p>'],
            ['title' => '10. Data Protection Contact', 'content' => '<p>If you have any questions about the processing of your personal data or about exercising your rights mentioned above, please contact:</p><div class="info-block"><strong>Data Protection Officer:</strong><br>ENTRIKS L.L.C.<br>Email: <a href="mailto:info@entriks.com">info@entriks.com</a></div>']
        ],
        'footer' => [
            'desc' => 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals from Kosovo through Nearshoring and Active Sourcing.',
            'contact' => 'Contact',
            'services' => 'Services',
            'company' => 'Company',
            'copyright' => '© 2026 ENTRIKS Talent Hub | Part of the ENTRIKS Group',
            'links' => ['Legal Notice', 'Privacy', 'Terms']
        ]
    ]
];

$c = $content[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $c['title']; ?></title>
    <meta name="description" content="<?php echo $c['description']; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFaviconUrl) ?>">
        <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        :root {
            --gold: #c9a227;
            --gold-hover: #b8911f;
            --cyan: #20c1f5;
            --bg-black: #000;
            --text-white: #fff;
            --text-muted: #888;
            --border: #2a2a2a
        }

        html {
            scroll-behavior: smooth
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-black);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden
        }

        a {
            text-decoration: none;
            color: inherit
        }

        button {
            cursor: pointer;
            border: none;
            outline: none;
            font-family: inherit
        }

        img {
            display: block
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem
        }

        .logo-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2rem;
            cursor: pointer
        }

        .logo-img {
            height: 28px;
            width: auto;
            display: block
        }

        .logo-sub {
            font-family: 'Orbitron', 'Inter', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.25em;
            line-height: 1;
            text-transform: uppercase;
            padding-left: 2px;
            margin-top: 2px
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            padding: 1.1rem 0;
            transition: all .3s;
            background: rgba(8, 8, 8, .95);
            transform: translateY(0)
        }

        .navbar.hidden {
            transform: translateY(-100%)
        }

        .navbar.scrolled {
            background: rgba(8, 8, 8, .95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, .06)
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .nav-links {
            display: flex;
            gap: 2.25rem
        }

        .nav-links a {
            font-size: .88rem;
            color: #bbb;
            font-weight: 500;
            transition: color .2s;
            white-space: nowrap
        }

        .nav-links a:hover {
            color: #fff
        }

        .lang-dropdown-wrap {
            position: relative
        }

        .lang-globe-btn {
            display: flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 2rem;
            padding: .35rem .75rem;
            cursor: pointer;
            color: #ccc;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .06em;
            transition: background .2s, border-color .2s
        }

        .lang-globe-btn:hover {
            background: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .25);
            color: #fff
        }

        .lang-globe-btn svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0
        }

        .lang-globe-btn .lang-caret {
            width: 10px;
            height: 10px;
            transition: transform .25s
        }

        .lang-dropdown-wrap.open .lang-caret {
            transform: rotate(180deg)
        }

        .lang-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: .65rem;
            overflow: hidden;
            min-width: 130px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, .45);
            z-index: 2000
        }

        .lang-dropdown-wrap.open .lang-dropdown {
            display: block
        }

        .lang-option {
            display: flex;
            align-items: center;
            gap: .6rem;
            width: 100%;
            padding: .7rem 1rem;
            background: none;
            border: none;
            color: #bbb;
            font-size: .85rem;
            font-weight: 500;
            cursor: pointer;
            text-align: left;
            transition: background .2s, color .2s;
            text-decoration: none
        }

        .lang-option:hover {
            background: rgba(255, 255, 255, .06);
            color: #fff
        }

        .lang-option.active {
            color: var(--gold);
            font-weight: 700
        }

        .lang-option .lang-flag {
            font-size: 1.1rem;
            line-height: 1
        }

        .lang-option .lang-check {
            margin-left: auto;
            color: var(--gold);
            display: none
        }

        .lang-option.active .lang-check {
            display: block
        }

        .lang-btn {
            background: none;
            border: none;
            color: #888;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            cursor: pointer;
            padding: .2rem .4rem;
            border-radius: 1rem;
            transition: color .2s;
            text-decoration: none
        }

        .lang-btn.active {
            color: var(--gold)
        }

        .mob-btn {
            display: none;
            background: none;
            color: #fff;
            padding: .25rem
        }

        .nav-mobile-group {
            display: none;
            align-items: center;
            gap: .75rem
        }

        .mob-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: #111;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            flex-direction: column;
            gap: 1.1rem
        }

        .mob-menu.open {
            display: flex
        }

        @media(max-width:999px) {
            .nav-links,
            .nav-cta {
                display: none
            }

            .mob-btn {
                display: block
            }

            .nav-mobile-group {
                display: flex;
                align-items: center;
                gap: .75rem
            }

            .nav-mobile-group .lang-globe-btn {
                display: flex
            }
        }

        @media(max-width:768px) {
            .legal-grid {
                grid-template-columns: 1fr
            }
        }

        .main {
            padding-top: 120px;
            padding-bottom: 4rem;
            background: #f5f4f1;
            flex: 1
        }

        .page-header {
            margin-bottom: 3rem
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4vw, 3rem);
            color: #1a1a1a;
            margin-bottom: 0.5rem
        }

        .content-section {
            background: #fff;
            border-radius: 1rem;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05)
        }

        .content-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #1a1a1a;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gold)
        }

        .content-section h3 {
            font-size: 1.1rem;
            color: #1a1a1a;
            margin: 1.5rem 0 0.75rem
        }

        .content-section p,
        .content-section li {
            margin-bottom: 1rem;
            color: #555
        }

        .content-section ul {
            margin-left: 1.5rem;
            margin-bottom: 1rem
        }

        .info-block {
            background: #f8f7f4;
            border-left: 4px solid var(--gold);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0 0.5rem 0.5rem 0
        }

        .info-block strong {
            color: #1a1a1a;
            display: block;
            margin-bottom: 0.5rem
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
            margin-top: 2rem
        }

        .back-link:hover {
            color: #1a1a1a
        }

        .footer {
            background: #070707;
            padding: 4rem 0 0;
            border-top: 1px solid var(--border)
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 0.8fr;
            gap: 2.5rem;
            padding-bottom: 3.5rem
        }

        .footer-desc {
            color: #888;
            font-size: .85rem;
            line-height: 1.75;
            margin: 1rem 0 1.1rem
        }

        .footer-note {
            font-size: .78rem;
            color: #C9A227;
            margin-bottom: 1rem
        }

        .footer-social {
            display: flex;
            gap: .65rem
        }

        .footer-social a {
            color: #888;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s
        }

        .footer-social a:hover {
            color: var(--cyan);
            border-color: var(--cyan)
        }

        .fcol h4 {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--cyan);
            margin-bottom: 1.25rem
        }

        .fcol ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: .55rem
        }

        .fcol ul li a {
            font-size: .85rem;
            color: #888;
            transition: color .2s
        }

        .fcol ul li a:hover {
            color: #fff
        }

        .footer-bottom {
            border-top: 1px solid var(--border);
            padding: 1.4rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .78rem;
            color: #888;
            flex-wrap: wrap;
            gap: .5rem
        }

        .fbl {
            display: flex;
            gap: 1.4rem
        }

        .fbl a {
            color: #888;
            font-size: .78rem;
            transition: color .2s
        }

        .fbl a:hover {
            color: #fff
        }

        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gold);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 999
        }

        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0)
        }

        .back-to-top:hover {
            transform: translateY(-4px)
        }

        .back-to-top svg {
            width: 24px;
            height: 24px
        }

        @media(max-width:1200px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr 1fr
            }
        }

        @media(max-width:999px) {
            .nav-links,
            .nav-cta {
                display: none
            }

            .mob-btn {
                display: block
            }

            .nav-mobile-group {
                display: flex;
                align-items: center;
                gap: .75rem
            }

            .nav-mobile-group .lang-globe-btn {
                display: flex
            }
        }

        @media(max-width:968px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:480px) {
            .container {
                padding: 0 20px
            }

            .footer-grid {
                grid-template-columns: 1fr
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
                gap: 1rem
            }

            .fbl {
                justify-content: center;
                flex-wrap: wrap
            }

            .content-section {
                padding: 1.5rem
            }

            .main {
                padding-top: 100px
            }
        }
    </style>
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="container nav-inner">
            <div class="logo-wrap">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?> Logo" class="logo-img">
                <div class="logo-sub">TALENT HUB</div>
            </div>
            <div class="nav-links">
                <a href="index.php#nearshoring"><?php echo $c['nav_links'][0]; ?></a>
                <a href="index.php#active-sourcing"><?php echo $c['nav_links'][1]; ?></a>
                <a href="index.php#blog"><?php echo $c['nav_links'][2]; ?></a>
                <a href="index.php#about"><?php echo $c['nav_links'][3]; ?></a>
                <a href="index.php#kontakt"><?php echo $c['nav_links'][4]; ?></a>
            </div>
            <div class="nav-cta">
                <div class="lang-dropdown-wrap" id="langDropdownWrap">
                    <button class="lang-globe-btn" id="langGlobeBtn" aria-haspopup="true" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                        </svg>
                        <span id="langCurrentLabel"><?php echo strtoupper($lang); ?></span>
                        <svg class="lang-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="langDropdown" role="menu">
                        <a href="?lang=de" class="lang-option <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" role="menuitem">
                            <span class="lang-flag">🇩🇪</span>
                            Deutsch
                            <?php if ($lang === 'de'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                        <a href="?lang=en" class="lang-option <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en" role="menuitem">
                            <span class="lang-flag">🇬🇧</span>
                            English
                            <?php if ($lang === 'en'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-mobile-group">
                <div class="lang-dropdown-wrap" id="langDropdownWrapMobile">
                    <button class="lang-globe-btn" id="langGlobeBtnMobile" aria-haspopup="true" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                        </svg>
                        <span id="langCurrentLabelMobile"><?php echo strtoupper($lang); ?></span>
                        <svg class="lang-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="langDropdownMobile" role="menu">
                        <a href="?lang=de" class="lang-option <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" role="menuitem">
                            <span class="lang-flag">🇩🇪</span>
                            Deutsch
                            <?php if ($lang === 'de'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                        <a href="?lang=en" class="lang-option <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en" role="menuitem">
                            <span class="lang-flag">🇬🇧</span>
                            English
                            <?php if ($lang === 'en'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <button class="mob-btn" id="mobBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:24px;height:24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="mob-menu" id="mobMenu">
            <a href="index.php#nearshoring"><?php echo $c['nav_links'][0]; ?></a>
            <a href="index.php#active-sourcing"><?php echo $c['nav_links'][1]; ?></a>
            <a href="index.php#blog"><?php echo $c['nav_links'][2]; ?></a>
            <a href="index.php#about"><?php echo $c['nav_links'][3]; ?></a>
            <a href="index.php#kontakt"><?php echo $c['nav_links'][4]; ?></a>
            <div style="display:flex;align-items:center;gap:.75rem;margin-top:.5rem;">
                <a href="?lang=de" class="lang-btn <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" style="color:<?php echo $lang === 'de' ? 'var(--gold)' : '#888'; ?>;font-weight:<?php echo $lang === 'de' ? '700' : '400'; ?>">DE</a>
                <span style="color:#555;">|</span>
                <a href="?lang=en" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en" style="color:<?php echo $lang === 'en' ? 'var(--gold)' : '#888'; ?>;font-weight:<?php echo $lang === 'en' ? '700' : '400'; ?>">EN</a>
            </div>
        </div>
    </nav>

    <main class="main">
        <div class="container">
            <div class="page-header">
                <h1><?php echo $c['page_title']; ?></h1>
            </div>

            <?php foreach ($c['sections'] as $section): ?>
            <section class="content-section">
                <h2><?php echo $section['title']; ?></h2>
                <?php echo $section['content']; ?>
            </section>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="logo-wrap" style="margin-bottom:1rem">
                        <img src="assets/img/logo.png" alt="ENTRIKS Logo" class="logo-img">
                        <div class="logo-sub">TALENT HUB</div>
                    </div>
                    <p class="footer-desc"><?php echo $c['footer']['desc']; ?></p>
                    <p class="footer-note"><?php echo $lang === 'de' ? 'Teil der ENTRIKS Group' : 'Part of the ENTRIKS Group'; ?></p>
                    <div class="footer-social">
                        <a href="https://www.facebook.com/ENTRIKS/" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/entriks_/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.linkedin.com/company/entriks" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="fcol">
                    <h4><?php echo $c['footer']['contact']; ?></h4>
                    <ul>
                        <li><a href="mailto:info@entriks.com">info@entriks.com</a></li>
                        <li><a href="tel:+38343889344">+383 43 889 344</a></li>
                        <li><a href="#">Lot Vaku, L 2.1<br>Prishtina, Kosovo</a></li>
                    </ul>
                </div>
                <div class="fcol">
                    <h4><?php echo $c['footer']['services']; ?></h4>
                    <ul>
                        <li><a href="index.php#nearshoring<?php echo $lang === 'en' ? '?lang=en' : ''; ?>">Nearshoring Dedicated</a></li>
                        <li><a href="index.php#nearshoring<?php echo $lang === 'en' ? '?lang=en' : ''; ?>">Nearshoring Team</a></li>
                        <li><a href="index.php#active-sourcing<?php echo $lang === 'en' ? '?lang=en' : ''; ?>">Active Sourcing</a></li>
                        <li><a href="index.php#kosovo<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $lang === 'de' ? 'Kosovo Standort' : 'Kosovo Location'; ?></a></li>
                    </ul>
                </div>
                <div class="fcol">
                    <h4><?php echo $c['footer']['company']; ?></h4>
                    <ul>
                        <li><a href="https://entriks.com">ENTRIKS Group</a></li>
                        <li><a href="https://entriks.com/karriere"><?php echo $lang === 'de' ? 'ENTRIKS Karriere' : 'ENTRIKS Career'; ?></a></li>
                        <li><a href="impressum.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $c['footer']['links'][0]; ?></a></li>
                        <li><a href="agb.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $c['footer']['links'][2]; ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span><?php echo $c['footer']['copyright']; ?></span>
                <div class="fbl">
                    <a href="impressum.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $c['footer']['links'][0]; ?></a>
                    <a href="datenschutz.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $c['footer']['links'][1]; ?></a>
                    <a href="agb.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $c['footer']['links'][2]; ?></a>
                </div>
            </div>
        </div>
    </footer>

    <button class="back-to-top" id="backToTop" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15" />
        </svg>
    </button>

    <script>
        (function(){const btn=document.getElementById('backToTop');if(!btn)return;window.addEventListener('scroll',function(){btn.classList.toggle('visible',window.scrollY>400)});})();
        (function(){const nav=document.getElementById('navbar');if(!nav)return;let lastScrollY=window.scrollY,ticking=false;function updateNavbar(){const currentScrollY=window.scrollY;if(currentScrollY>lastScrollY&&currentScrollY>100){nav.classList.add('hidden');}else{nav.classList.remove('hidden');}nav.classList.toggle('scrolled',currentScrollY>30);lastScrollY=currentScrollY;ticking=false;}window.addEventListener('scroll',()=>{if(!ticking){window.requestAnimationFrame(updateNavbar);ticking=true;}},{passive:true});})();
        const langWrap=document.getElementById('langDropdownWrap'),langGlobeBtn=document.getElementById('langGlobeBtn'),langDropdown=document.getElementById('langDropdown');
        if(langGlobeBtn&&langWrap){langGlobeBtn.addEventListener('click',(e)=>{e.stopPropagation();const isOpen=langWrap.classList.toggle('open');langGlobeBtn.setAttribute('aria-expanded',isOpen);});document.addEventListener('click',()=>{langWrap.classList.remove('open');langGlobeBtn.setAttribute('aria-expanded','false');});langDropdown&&langDropdown.addEventListener('click',(e)=>e.stopPropagation());}
        const langWrapMobile=document.getElementById('langDropdownWrapMobile'),langGlobeBtnMobile=document.getElementById('langGlobeBtnMobile'),langDropdownMobile=document.getElementById('langDropdownMobile');
        if(langGlobeBtnMobile&&langWrapMobile){langGlobeBtnMobile.addEventListener('click',(e)=>{e.stopPropagation();const isOpen=langWrapMobile.classList.toggle('open');langGlobeBtnMobile.setAttribute('aria-expanded',isOpen);});document.addEventListener('click',()=>{langWrapMobile.classList.remove('open');langGlobeBtnMobile.setAttribute('aria-expanded','false');});langDropdownMobile&&langDropdownMobile.addEventListener('click',(e)=>e.stopPropagation());}
        const mobBtn=document.getElementById('mobBtn'),mobMenu=document.getElementById('mobMenu');
        if(mobBtn&&mobMenu){mobBtn.addEventListener('click',()=>mobMenu.classList.toggle('open'));document.querySelectorAll('.mob-menu a').forEach(a=>{a.addEventListener('click',()=>mobMenu.classList.remove('open'));});}
    </script>
</body>
</html>

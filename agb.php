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
if ($lang !== 'de' && $lang !== 'en')
    $lang = 'de';

$content = [
    'de' => [
        'title' => 'AGB | ENTRIKS Talent Hub - Vertragsbedingungen für Nearshoring',
        'description' => 'Allgemeine Geschäftsbedingungen von ENTRIKS Talent Hub. Transparente Vertragsbedingungen für Nearshoring, Active Sourcing und Personalvermittlung.',
        'page_title' => 'Allgemeine Geschäftsbedingungen',
        'nav_links' => ['Nearshoring', 'Active Sourcing', 'Blog', 'Über uns', 'Kontakt'],
        'sections' => [
            ['title' => '§ 1 Geltungsbereich', 'content' => '<p>Diese Allgemeinen Geschäftsbedingungen (nachfolgend "AGB" genannt) gelten für alle Verträge, Leistungen und Lieferungen zwischen ENTRIKS Talent Hub (nachfolgend "Agentur" genannt) und ihren Kunden (nachfolgend "Auftraggeber" genannt).</p><p>Abweichende Bedingungen des Auftraggebers werden nicht anerkannt, es sei denn, die Agentur hat ihnen ausdrücklich schriftlich zugestimmt.</p>'],
            ['title' => '§ 2 Vertragsgegenstand', 'content' => '<p>Gegenstand des Vertrages ist die Erbringung von Dienstleistungen im Bereich Personalvermittlung, Nearshoring und Active Sourcing. Die konkrete Leistungsbeschreibung ergibt sich aus dem jeweiligen Angebot bzw. der Auftragsbestätigung.</p><p>Die Agentur erbringt ihre Leistungen mit der Sorgfalt eines ordentlichen Kaufmanns. Eine Erfolgsgarantie für die Vermittlung von Personal wird nicht übernommen.</p>'],
            ['title' => '§ 3 Pflichten der Agentur', 'content' => '<h3>3.1 Personalvermittlung</h3><p>Die Agentur sucht auf Grundlage der vom Auftraggeber übermittelten Anforderungsprofile geeignete Kandidaten aus. Sie führt Vorabgespräche und Qualifizierungsgespräche durch und übermittelt dem Auftraggeber aussagekräftige Kandidatenprofile.</p><h3>3.2 Nearshoring</h3><p>Bei Nearshoring-Dienstleistungen stellt die Agentur dem Auftraggeber qualifizierte Fachkräfte aus dem Kosovo zur Verfügung. Die Fachkräfte arbeiten dediziert für den Auftraggeber und werden in dessen Prozesse integriert.</p><h3>3.3 Active Sourcing</h3><p>Die Agentur identifiziert und qualifiziert passive Kandidaten, die nicht aktiv auf Jobsuche sind. Sie spricht diese Kandidaten direkt an und prüft deren Eignung für den Auftraggeber.</p>'],
            ['title' => '§ 4 Pflichten des Auftraggebers', 'content' => '<p>Der Auftraggeber verpflichtet sich:</p><ul><li>Der Agentur vollständige und wahrheitsgemäße Informationen über die zu besetzende Position zu geben</li><li>Rechtzeitig Feedback zu vorgestellten Kandidaten zu geben</li><li>Terminvereinbarungen mit Kandidaten einzuhalten oder rechtzeitig abzusagen</li><li>Die der Agentur übermittelten Kandidatendaten vertraulich zu behandeln</li><li>Nicht ohne Zustimmung der Agentur direkt mit vorgestellten Kandidaten in Verbindung zu treten</li></ul>'],
            ['title' => '§ 5 Vergütung', 'content' => '<h3>5.1 Personalvermittlung</h3><p>Die Vergütung für erfolgreiche Vermittlungen erfolgt in der Regel als Provision in Höhe eines vereinbarten Prozentsatzes des Jahresbruttogehalts des vermittelten Kandidaten. Die Provision wird mit Abschluss des Arbeitsvertrages zwischen dem Kandidaten und dem Auftraggeber fällig.</p><h3>5.2 Nearshoring</h3><p>Die Vergütung für Nearshoring-Dienstleistungen erfolgt auf Basis monatlicher Pauschalbeträge pro eingesetztem Mitarbeiter. Die genauen Konditionen ergeben sich aus dem jeweiligen Angebot.</p><h3>5.3 Zahlungsbedingungen</h3><p>Rechnungen der Agentur sind innerhalb von 14 Tagen nach Rechnungsstellung ohne Abzug zu zahlen. Bei Zahlungsverzug sind Verzugszinsen in Höhe von 8 Prozentpunkten über dem Basiszinssatz p.a. geschuldet.</p>'],
            ['title' => '§ 6 Rücktrittsrecht / Widerrufsbelehrung', 'content' => '<p><strong>Widerrufsrecht für Verbraucher</strong><br>Verbraucher haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p><p>Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses. Um Ihr Widerrufsrecht auszuüben, müssen Sie uns mittels einer eindeutigen Erklärung (z.B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p><p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>'],
            ['title' => '§ 7 Haftung', 'content' => '<p>Die Agentur haftet für Vorsatz und grobe Fahrlässigkeit uneingeschränkt. Für einfache Fahrlässigkeit haftet die Agentur nur bei der Verletzung wesentlicher Vertragspflichten (Kardinalpflichten), deren Erfüllung die ordnungsgemäße Durchführung des Vertrags überhaupt erst ermöglicht und auf deren Einhaltung der Auftraggeber regelmäßig vertrauen darf.</p><p>Die Haftung der Agentur für leichte Fahrlässigkeit ist auf den Betrag begrenzt, der bei Vertragsschluss als wahrscheinlicher Schaden vorhersehbar war. Diese Haftungsbeschränkung gilt nicht für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit.</p>'],
            ['title' => '§ 8 Vertraulichkeit', 'content' => '<p>Beide Vertragsparteien verpflichten sich, alle im Rahmen der Zusammenarbeit erlangten Informationen über die andere Partei vertraulich zu behandeln und nicht an Dritte weiterzugeben. Diese Verpflichtung gilt auch über das Ende des Vertragsverhältnisses hinaus.</p>'],
            ['title' => '§ 9 Datenschutz', 'content' => '<p>Die Verarbeitung personenbezogener Daten erfolgt in Übereinstimmung mit der Datenschutz-Grundverordnung (DSGVO) und den geltenden datenschutzrechtlichen Bestimmungen. Weitere Informationen entnehmen Sie bitte unserer <a href="datenschutz.php">Datenschutzerklärung</a>.</p>'],
            ['title' => '§ 10 Vertragslaufzeit und Kündigung', 'content' => '<p>Die Laufzeit des Vertrags richtet sich nach den vereinbarten Konditionen. Sofern keine feste Laufzeit vereinbart wurde, kann der Vertrag von beiden Parteien mit einer Frist von 30 Tagen zum Monatsende gekündigt werden.</p><p>Das Recht zur außerordentlichen Kündigung aus wichtigem Grund bleibt unberührt.</p>'],
            ['title' => '§ 11 Schlussbestimmungen', 'content' => '<p>Es gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts.</p><p>Sollten einzelne Bestimmungen dieser AGB unwirksam sein oder werden, bleibt die Wirksamkeit der übrigen Bestimmungen hiervon unberührt. Die unwirksame Bestimmung ist durch eine wirksame Bestimmung zu ersetzen, die dem wirtschaftlichen Zweck der unwirksamen Bestimmung am nächsten kommt.</p>']
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
        'title' => 'Terms & Conditions | ENTRIKS Talent Hub - Contract Terms for Nearshoring',
        'description' => 'General Terms and Conditions of ENTRIKS Talent Hub. Transparent contract terms for Nearshoring, Active Sourcing and Recruitment.',
        'page_title' => 'General Terms and Conditions',
        'nav_links' => ['Nearshoring', 'Active Sourcing', 'Blog', 'About Us', 'Contact'],
        'sections' => [
            ['title' => '§ 1 Scope of Application', 'content' => '<p>These General Terms and Conditions (hereinafter referred to as "GTC") apply to all contracts, services, and deliveries between ENTRIKS Talent Hub (hereinafter referred to as "Agency") and its clients (hereinafter referred to as "Client").</p><p>Deviating conditions of the Client will not be recognized unless the Agency has expressly agreed to them in writing.</p>'],
            ['title' => '§ 2 Subject Matter of the Contract', 'content' => '<p>The subject matter of the contract is the provision of services in the areas of recruitment, nearshoring, and active sourcing. The specific description of services results from the respective offer or order confirmation.</p><p>The Agency provides its services with the care of a prudent merchant. No guarantee of success for the placement of personnel is assumed.</p>'],
            ['title' => '§ 3 Obligations of the Agency', 'content' => '<h3>3.1 Recruitment</h3><p>The Agency searches for suitable candidates based on the requirement profiles provided by the Client. It conducts preliminary interviews and qualification discussions and transmits meaningful candidate profiles to the Client.</p><h3>3.2 Nearshoring</h3><p>For nearshoring services, the Agency provides the Client with qualified professionals from Kosovo. The professionals work exclusively for the Client and are integrated into their processes.</p><h3>3.3 Active Sourcing</h3><p>The Agency identifies and qualifies passive candidates who are not actively seeking employment. It approaches these candidates directly and checks their suitability for the Client.</p>'],
            ['title' => '§ 4 Obligations of the Client', 'content' => "<p>The Client undertakes to:</p><ul><li>Provide the Agency with complete and truthful information about the position to be filled</li><li>Give timely feedback on presented candidates</li><li>Comply with agreed appointments with candidates or cancel them in good time</li><li>Treat candidate data transmitted by the Agency confidentially</li><li>Not contact presented candidates directly without the Agency's consent</li></ul>"],
            ['title' => '§ 5 Remuneration', 'content' => "<h3>5.1 Recruitment</h3><p>Remuneration for successful placements is usually made as a commission at an agreed percentage of the candidate's annual gross salary. The commission becomes due upon conclusion of the employment contract between the candidate and the Client.</p><h3>5.2 Nearshoring</h3><p>Remuneration for nearshoring services is based on monthly flat rates per employee deployed. The exact conditions result from the respective offer.</p><h3>5.3 Payment Terms</h3><p>Invoices from the Agency are payable within 14 days of invoicing without deduction. In case of late payment, default interest of 8 percentage points above the base rate p.a. is due.</p>"],
            ['title' => '§ 6 Right of Withdrawal / Cancellation Policy', 'content' => '<p><strong>Right of Withdrawal for Consumers</strong><br>Consumers have the right to withdraw from this contract within fourteen days without giving any reason.</p><p>The withdrawal period is fourteen days from the day of contract conclusion. To exercise your right of withdrawal, you must inform us by means of a clear statement (e.g., a letter sent by post or email) of your decision to withdraw from this contract.</p><p>To meet the withdrawal deadline, it is sufficient that you send the communication concerning the exercise of the right of withdrawal before the withdrawal period has expired.</p>'],
            ['title' => '§ 7 Liability', 'content' => "<p>The Agency is liable without limitation for intent and gross negligence. For simple negligence, the Agency is liable only for the breach of essential contractual obligations (cardinal obligations), the fulfillment of which enables the proper execution of the contract in the first place and on whose compliance the Client may regularly rely.</p><p>The Agency's liability for slight negligence is limited to the amount that was foreseeable as probable damage at the time of contract conclusion. This limitation of liability does not apply to damages arising from injury to life, body, or health.</p>"],
            ['title' => '§ 8 Confidentiality', 'content' => '<p>Both contracting parties undertake to treat all information obtained in the course of cooperation about the other party confidentially and not to pass it on to third parties. This obligation also applies beyond the termination of the contractual relationship.</p>'],
            ['title' => '§ 9 Data Protection', 'content' => '<p>The processing of personal data is carried out in accordance with the General Data Protection Regulation (GDPR) and applicable data protection regulations. For more information, please refer to our <a href="datenschutz.php?lang=en">Privacy Policy</a>.</p>'],
            ['title' => '§ 10 Contract Duration and Termination', 'content' => '<p>The duration of the contract depends on the agreed conditions. Unless a fixed term has been agreed, the contract may be terminated by either party with a notice period of 30 days to the end of the month.</p><p>The right to extraordinary termination for good cause remains unaffected.</p>'],
            ['title' => '§ 11 Final Provisions', 'content' => '<p>The law of the Federal Republic of Germany applies, excluding the UN Convention on Contracts for the International Sale of Goods.</p><p>Should individual provisions of these GTC be or become invalid or unenforceable, this shall not affect the validity of the remaining provisions. The ineffective provision shall be replaced by a valid provision that comes closest to the economic purpose of the ineffective provision.</p>']
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
$otherLang = $lang === 'de' ? 'en' : 'de';
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
                        <li><a href="datenschutz.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $c['footer']['links'][1]; ?></a></li>
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

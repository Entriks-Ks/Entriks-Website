<?php
require_once __DIR__ . '/backend/session_config.php';
require_once 'backend/database.php';
require_once 'backend/config.php';

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
$footerTextDe = 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo – durch Nearshoring und Active Sourcing.';
$footerTextEn = 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals from Kosovo through Nearshoring and Active Sourcing.';
$copyrightYear = date('Y');

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
            if (!empty($settings['footer_text_de'])) $footerTextDe = $settings['footer_text_de'];
            if (!empty($settings['footer_text_en'])) $footerTextEn = $settings['footer_text_en'];
        }
    } catch (Exception $e) {
        // Use defaults on error
    }
}

$lang = isset($_GET['lang']) ? $_GET['lang'] : null;

// If no explicit lang parameter, detect from browser
if ($lang === null) {
  $lang = 'de';  // default
  if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    // Check if English is preferred
    if (preg_match('/^en/i', $acceptLang) || stripos($acceptLang, 'en') !== false) {
      $lang = 'en';
    }
  }
}

// Validate and fallback
if ($lang !== 'de' && $lang !== 'en')
  $lang = 'de';

$langParam = $lang === 'en' ? '?lang=en' : '';
$langSuffix = $lang === 'en' ? '?lang=en' : '';
$featuredPosts = [];
$cacheFile = 'backend/static/featured.json';

if (file_exists($cacheFile)) {
  $cacheData = json_decode(file_get_contents($cacheFile), true);
  if ($cacheData && !empty($cacheData['posts']) && isset($db)) {
    // Validate cached posts still exist in database
    $validPosts = [];
    foreach ($cacheData['posts'] as $p) {
      try {
        $postId = new MongoDB\BSON\ObjectId($p['id']);
        $exists = $db->blog->findOne(
          ['_id' => $postId, 'status' => 'published'],
          ['projection' => ['_id' => 1]]
        );
        if ($exists) {
          $validPosts[] = $p;
        }
      } catch (Exception $e) {
        // Invalid ID format, skip
      }
    }
    
    foreach ($validPosts as $p) {
      $title = $lang === 'en' ? ($p['title_en'] ?? $p['title'] ?? 'No Title') : ($p['title_de'] ?? $p['title'] ?? 'No Title');
      $excerpt = $lang === 'en' ? ($p['excerpt_en'] ?? '') : ($p['excerpt_de'] ?? $p['excerpt'] ?? '');
      $date = $lang === 'en' ? ($p['date_en'] ?? $p['date'] ?? '') : ($p['date_de'] ?? $p['date'] ?? '');
      $content = $p['content_de'] ?? $p['content'] ?? '';

      $wordCount = str_word_count(strip_tags($content));
      $readTimeCalc = max(4, min(15, ceil($wordCount / 150)));
      $readTime = ($p['read_time'] ?? $readTimeCalc . ' min');

      $featuredPosts[] = [
        'id' => $p['id'],
        'title' => $title,
        'author' => $p['author'] ?? 'Admin',
        'date' => $date,
        'image_url' => $p['image_url'] ?? '',
        'excerpt' => $excerpt,
        'category' => $p['category'] ?? 'Nearshoring',
        'read_time' => $readTime
      ];
    }
  }
}

if (empty($featuredPosts)) {
  try {
    if (isset($db) && $db) {
      $collection = $db->blog;
      $limit = 3;

      $cursor = $collection->find(
        ['featured' => true, 'status' => 'published'],
        [
          'limit' => $limit,
          'sort' => ['date' => -1]
        ]
      );

      foreach ($cursor as $doc) {
        $dateStr = '';
        if (isset($doc['date'])) {
          if ($doc['date'] instanceof MongoDB\BSON\UTCDateTime) {
            $dateStr = $doc['date']->toDateTime()->format('d.m.Y');
          } else {
            $dateStr = $doc['date'];
          }
        }

        $imgUrl = '';
        if (!empty($doc['image_id'])) {
          $imgUrl = 'backend/image.php?id=' . $doc['image_id'];
        } elseif (!empty($doc['image_url'])) {
          $imgUrl = $doc['image_url'];
        }

        $title = $lang === 'en' ? ($doc['title_en'] ?? $doc['title'] ?? '(No Title)') : ($doc['title_de'] ?? $doc['title'] ?? '(No Title)');
        $content = $lang === 'en' ? ($doc['content_en'] ?? $doc['content'] ?? '') : ($doc['content_de'] ?? $doc['content'] ?? '');
        $excerpt = $lang === 'en' ? ($doc['excerpt_en'] ?? '') : ($doc['excerpt_de'] ?? $doc['excerpt'] ?? '');

        // If no explicit excerpt, generate from content
        if (empty($excerpt) && !empty($content)) {
          $excerpt = mb_substr(strip_tags($content), 0, 120) . '...';
        }

        // Calculate read time based on content length (average reader: ~150 words/min)
        // Minimum 2 min for better appearance, max reasonable cap at 15 min
        $wordCount = str_word_count(strip_tags($content));
        $readTimeCalc = max(2, min(15, ceil($wordCount / 150)));

        $featuredPosts[] = [
          'id' => (string) $doc['_id'],
          'title' => $title,
          'author' => $doc['author'] ?? 'Admin',
          'date' => $dateStr,
          'image_url' => $imgUrl,
          'excerpt' => $excerpt,
          'category' => $doc['categories'][0] ?? ($doc['category'] ?? 'Nearshoring'),
          'read_time' => ($doc['read_time'] ?? $readTimeCalc . ' min')
        ];
      }
    }
  } catch (Exception $e) {
    $featuredPosts = [];
  }
}

// Form messages (keep as is)
$message = '';
$message_type = '';
if (isset($_GET['message'])) {
  switch ($_GET['message']) {
    case 'success':
      $message = $lang === 'de' ? 'Vielen Dank! Ihre Anfrage wurde erfolgreich gesendet.' : 'Thank you! Your request has been sent successfully.';
      $message_type = 'success';
      break;
    case 'error':
      $message = $lang === 'de' ? 'Fehler: Ihre Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.' : 'Error: Your request could not be sent. Please try again later.';
      $message_type = 'error';
      break;
    case 'missing':
      $message = $lang === 'de' ? 'Bitte füllen Sie alle Pflichtfelder aus.' : 'Please fill in all required fields.';
      $message_type = 'error';
      break;
  }
}

$langParam = $lang === 'en' ? '?lang=en' : '';
$langSuffix = $lang === 'en' ? '?lang=en' : '';

// Content is now loaded from static $content arrays below
// Default content arrays (primary content source)
$content = [
  'de' => [
    'nav' => ['Nearshoring', 'Active Sourcing', 'Blog', 'Über uns', 'Kontakt'],
    'hero' => [
      'eyebrow' => 'Teil der ENTRIKS Group',
      'title' => 'Europäische Qualität.<br><span style="color:var(--gold)">Strategische Talente.</span><br>Ihr Wettbewerbsvorteil.',
      'subtitle' => 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften – durch strategisches Nearshoring und gezieltes Active Sourcing. Schneller. Kosteneffizienter. Nachhaltiger.',
      'btn1' => 'Nearshoring-Potenzial entdecken',
      'btn2' => 'Kostenloses Erstgespräch',
      'trust1' => 'Kostenlos & unverbindlich',
      'trust2' => 'Antwort in 24h',
      'trust3' => 'Persönliche Beratung'
    ],
    'stats' => [
      'stat1' => ['value' => '500', 'suffix' => '+', 'label' => 'Vermittelte Fachkräfte im Kosovo'],
      'stat2' => ['value' => '98', 'suffix' => ' %', 'label' => 'Kundenzufriedenheit'],
      'stat3' => ['value' => '21', 'suffix' => ' Tage', 'label' => 'Ø Time-to-Hire'],
      'stat4' => ['value' => '60', 'suffix' => ' %', 'label' => 'Kostenersparnis vs. DACH'],
      'stat5' => ['value' => '100', 'suffix' => '+', 'label' => 'Partnerunternehmen'],
      'brand_strip' => 'Teil der ENTRIKS Group'
    ],
    'why' => [
      'eyebrow' => 'Warum ENTRIKS Talent Hub?',
      'title' => 'Der Fachkräftemangel im DACH-Raum ist real. Unsere Lösung auch.',
      'text1' => 'Deutsche, österreichische und schweizer Unternehmen verlieren jeden Tag wertvolle Zeit und Ressourcen in endlosen Recruiting-Zyklen – für Positionen, die der heimische Markt schlicht nicht mehr ausreichend bedienen kann.',
      'text2' => 'Wir bringen die richtigen Talente zu den richtigen Unternehmen im DACH-Raum – strukturiert, rechtssicher und nachhaltig.',
      'card1' => ['title' => 'Nearshoring aus dem Kosovo', 'text' => 'Wir vermitteln und integrieren Fachkräfte aus Prishtina in Ihre Unternehmensstruktur – remote, hybrid oder vor Ort. Volle Kontrolle. Minimales Risiko.', 'link' => 'Mehr erfahren'],
      'card2' => ['title' => 'Active Sourcing für Ihr Unternehmen', 'text' => 'Unser Team identifiziert, qualifiziert und präsentiert Ihnen gezielt die Kandidaten europaweit – bevor diese überhaupt aktiv suchen.', 'link' => 'Mehr erfahren']
    ],
    'nearshoring' => [
      'label' => 'Leistung 01',
      'title' => 'Nearshoring aus dem Kosovo – Ihr Remote-Team auf Knopfdruck.',
      'text1' => 'Nearshoring bedeutet nicht Outsourcing ins Ungewisse. Es bedeutet: Sie bekommen einen dedizierten Mitarbeiter oder ein dediziertes Team – vollständig in Ihre Prozesse integriert, von Prishtina aus für Sie tätig.',
      'text2' => 'ENTRIKS Talent Hub übernimmt den gesamten Prozess: von der Identifikation über die Qualifizierung bis zur rechtssicheren Integration in Ihre Unternehmensstruktur.',
      'features' => [
        'Vollständige Prozessübernahme – von der Suche bis zum Onboarding',
        'Dedizierte Fachkräfte, die ausschließlich für Sie arbeiten',
        'Rechtssichere Vertragsgestaltung & Compliance',
        'Sprachkenntnisse: Albanisch, Englisch, Deutsch (je nach Profil)',
        'Flexible Modelle: Remote, Hybrid, temporär oder dauerhaft',
        'Kontinuierliche Begleitung nach der Vermittlung',
        'Kulturelles Onboarding-Programm inklusive'
      ],
      'btn' => 'Nearshoring-Modelle kennenlernen',
      'process' => [
        ['num' => '01', 'title' => 'Briefing', 'desc' => 'Wir verstehen Ihren Bedarf, Ihre Kultur und Ihre Anforderungen in einem strukturierten Kick-off-Gespräch.', 'day' => 'Tag 1'],
        ['num' => '02', 'title' => 'Search & Match', 'desc' => 'Unser Team in Prishtina startet die gezielte Suche und qualifiziert Kandidaten nach Ihren Kriterien.', 'day' => 'Tag 2–7'],
        ['num' => '03', 'title' => 'Präsentation', 'desc' => 'Sie erhalten 3–5 vorqualifizierte Profile mit Video-Interviews und detailliertem Assessment.', 'day' => 'Tag 7–12'],
        ['num' => '04', 'title' => 'Integration', 'desc' => 'Wir begleiten das Onboarding und stellen die nahtlose Integration in Ihr Team sicher.', 'day' => 'Tag 17–21']
      ]
    ],
    'active_sourcing' => [
      'label' => 'Leistung 02',
      'title' => 'Active Sourcing – Wir finden, wen Sie suchen. Bevor andere es tun.',
      'text1' => 'Die besten Kandidaten sind nicht auf Jobportalen. Sie sind in ihren aktuellen Positionen – zufrieden, gefragt und nicht aktiv auf der Suche. Genau diese Talente erreichen wir.',
      'text2' => 'Unser spezialisiertes Active-Sourcing-Team nutzt professionelle Netzwerke, direkte Ansprache und ein gewachsenes Kandidatennetzwerk europaweit.',
      'features' => [
        'Proaktive Direktansprache qualifizierter Kandidaten',
        'Zugang zu passiven Talenten (nicht aktiv auf Jobsuche)',
        'Multi-Channel-Sourcing: LinkedIn, Fachforen, Uni-Netzwerke',
        'Strukturiertes Screening & Vorqualifizierung',
        'Video-Interviews & Skills-Assessment vor Präsentation',
        'Vollständige Kandidaten-Dossiers für Ihre Entscheidung',
        'Diskrete & professionelle Kandidatenansprache'
      ],
      'btn' => 'Active Sourcing anfragen',
      'feat_cards' => [
        ['title' => 'Präzision statt Masse', 'text' => 'Wir präsentieren keine Listen von 20 Kandidaten. Sie bekommen 3–5 Profile, die wirklich passen. Jedes einzeln geprüft und bewertet.'],
        ['title' => 'Speed-to-Hire', 'text' => 'Unser erfahrenes Team kennt die europäischen Märkte. Durchschnittliche Time-to-First-Candidate: 7 Werktage. Keine langen Wartezeiten.'],
        ['title' => 'Qualitätsgarantie', 'text' => 'Besetzungen, die nicht passen, ersetzen wir. Unsere Nachbesetzungsgarantie gibt Ihnen die Sicherheit für strategische Entscheidungen.']
      ]
    ],
    'kosovo' => [
      'eyebrow' => 'Standort Kosovo',
      'title' => 'Warum Kosovo?',
      'subtitle' => 'Strategisch positioniert zwischen Ost und West bietet der Kosovo einzigartige Vorteile für DACH-Unternehmen.',
      'cards' => [
        ['label' => 'Avg. 29 years', 'title' => 'Average age', 'desc' => 'One of the youngest populations in Europe'],
        ['label' => '40–60%', 'title' => 'Cost savings', 'desc' => 'Compared to DACH professionals at equal quality'],
        ['label' => 'CET +0h', 'title' => 'Time zone', 'desc' => 'Full synchrony with DACH region']
      ],
      'quote' => ['text' => 'Der Kosovo liefert Qualität, Motivation und Kosteneffizienz in einer einzigartigen Kombination.', 'author' => 'René Schirner', 'role' => 'Gründer & CEO, ENTRIKS Group']
    ],
    'angebot' => [
      'eyebrow' => 'Unser Angebot',
      'title' => 'Maßgeschneidert für Ihren Bedarf.',
      'subtitle' => 'Jedes Unternehmen ist anders. Deshalb bieten wir keine Standardlösungen – sondern Modelle, die zu Ihrer Situation passen.',
      'popular' => 'Beliebteste Option',
      'cards' => [
        [
          'title' => 'Nearshoring Dedicated',
          'text' => 'Ein oder mehrere dedizierte Fachkräfte aus dem Kosovo arbeiten vollständig für Ihr Unternehmen – integriert in Ihre Strukturen, Ihre Tools, Ihre Kultur.',
          'tags' => ['Remote', 'Hybrid', 'Vollzeit', 'Teilzeit'],
          'link' => 'Details anfragen'
        ],
        [
          'title' => 'Nearshoring Team',
          'text' => 'Wir stellen Ihnen ein komplettes Nearshoring-Team zusammen – aufgebaut, koordiniert und begleitet von ENTRIKS Talent Hub.',
          'tags' => ['2–20 Personen', 'Skalierbar', 'Managed'],
          'link' => 'Details anfragen'
        ],
        [
          'title' => 'Active Sourcing',
          'text' => 'Sie suchen eine spezifische Fachkraft für eine feste Position vor Ort. Unser Team übernimmt die gesamte Suche, Qualifizierung und Präsentation.',
          'tags' => ['Direktvermittlung', 'Festanstellung', 'DACH'],
          'link' => 'Details anfragen'
        ]
      ]
    ],
    'expertise' => [
      'eyebrow' => 'Unsere Expertise',
      'title' => 'Typisch besetzte Positionen',
      'subtitle' => 'In diesen Bereichen vermitteln wir täglich hochqualifizierte Fachkräfte',
      'tags' => ['IT & Software Development', 'Customer Service', 'Finance & Controlling', 'Marketing & Content', 'HR Administration', 'Data Analysis', 'Sales Support', 'Back-Office', 'Project Management', 'Graphic Design', 'E-Commerce', 'Accounting']
    ],
    'process' => [
      'eyebrow' => 'So arbeiten wir',
      'title' => 'Von der Anfrage zur besetzten Position – in 21 Tagen.',
      'subtitle' => 'Unser Prozess ist transparent, strukturiert und vollständig auf Ihre Bedürfnisse ausgerichtet.<br>Kein Durcheinander. Kein Warten im Dunkeln.',
      'steps' => [
        ['num' => '01', 'title' => 'Kick-off & Briefing', 'desc' => 'In einem 60-minütigen Gespräch erfassen wir Ihren genauen Bedarf: fachlich, kulturell und zeitlich.', 'day' => 'Tag 1'],
        ['num' => '02', 'title' => 'Sourcing & Screening', 'desc' => 'Unser Team in Prishtina startet sofort. Kandidaten werden identifiziert, kontaktiert und strukturiert gescreent.', 'day' => 'Tag 2–7'],
        ['num' => '03', 'title' => 'Assessment & Qualifizierung', 'desc' => 'Video-Interviews, Skills-Tests und Cultural-Fit-Analyse. Nur die besten 3–5 Kandidaten kommen weiter.', 'day' => 'Tag 7–12'],
        ['num' => '04', 'title' => 'Präsentation & Entscheidung', 'desc' => 'Sie erhalten vollständige Kandidaten-Dossiers mit Videointerviews. Sie treffen die Entscheidung.', 'day' => 'Tag 12–16'],
        ['num' => '05', 'title' => 'Onboarding & Integration', 'desc' => 'Wir begleiten das Onboarding aktiv und stellen sicher, dass Ihr neues Teammitglied vom ersten Tag performen kann.', 'day' => 'Tag 17–21']
      ]
    ],
    'success' => [
      'eyebrow' => 'Erfolgsgeschichten',
      'title' => 'Unternehmen, die bereits von Kosovo-Talenten profitieren.',
      'subtitle' => 'Reale Ergebnisse. Reale Unternehmen. Realer Unterschied.',
      'stories' => [
        [
          'icon' => 'code-bracket-square',
          'title' => 'Softwareentwickler-Team für Stuttgarter Tech-Firma',
          'text' => 'Ein mittelständisches Software-Unternehmen aus Stuttgart bräuchte dringend 8 Backend-Developer. ENTRIKS Talent Hub besetzte alle 8 Positionen innerhalb von 19 Tagen – bei 52 % Kostenersparnis.'
        ],
        [
          'icon' => 'users',
          'title' => 'Customer-Service-Team für Wiener E-Commerce',
          'text' => 'Ein Wiener E-Commerce-Scaleup brauchte ein mehrsprachiges Customer-Service-Team. ENTRIKS Talent Hub baute innerhalb von 25 Tagen ein 12-köpfiges Team auf – dreisprachig, vollständig remote.'
        ]
      ]
    ],
    'testimonials' => [
      'title' => 'Das sagen unsere Kunden',
      'reviews' => [
        ['text' => '„Das Rekrutierungsteam von Entriks ist hervorragend. Sie verstehen unsere technischen Anforderungen perfekt und liefern keine Listen, sondern eine kuratierte Auswahl hochqualifizierter Fachkräfte. Proaktive Kommunikation und transparente Abläufe vom ersten Treffen bis zur Einstellung.“', 'name' => 'Schmidt S.', 'role' => 'Leiter Technik', 'source' => 'Google'],
        ['text' => '„Was uns beeindruckt hat, war das Auswahlverfahren. Die Kandidaten waren bestens vorbereitet und hochqualifiziert. Die Recruiter schlagen die Brücke zwischen europäischen Geschäftsstandards und dem Talentpool im Kosovo mit absoluter Professionalität.“', 'name' => 'Krasniqi A.', 'role' => 'Personalleiter', 'source' => 'Google'],
        ['text' => '„Als Teil des Teams schätze ich besonders die professionelle Unterstützung beim Onboarding und die klaren Strukturen. Entriks bietet großartige Entwicklungschancen und eine Arbeitskultur, die auf Wertschätzung und Innovation basiert.“', 'name' => 'Müller H.', 'role' => 'Mitarbeiter', 'source' => 'LinkedIn']
      ]
    ],
    'clients' => [
      'subtitle' => 'Von Startups bis zu etablierten Unternehmen – gemeinsam schaffen wir Erfolge.'
    ],
    'about' => [
      'eyebrow' => 'Über ENTRIKS Talent Hub',
      'title' => 'Verankert in Prishtina. Verbunden mit Europa.',
      'text1' => 'ENTRIKS Talent Hub ist Teil der ENTRIKS Group – einer der am schnellsten wachsenden Unternehmensgruppen im Bereich Nearshoring, Sales und Business Services.',
      'text2' => 'Mit unserem Hauptsitz in Prishtina, Kosovo, sind wir dort, wo die Talente sind. Nicht irgendwo in einer fernen Verwaltungszentrale – sondern direkt im Markt, direkt bei den Menschen, direkt an der Quelle.',
      'text3' => 'Unser Team kennt den kosovarischen Arbeitsmarkt wie kein anderes. Wir haben Netzwerke aufgebaut, Vertrauen gewonnen und Strukturen geschaffen, die echte Ergebnisse liefern.',
      'location_label' => 'Unser Standort',
      'location_name' => 'ENTRIKS Talent Hub',
      'location_city' => 'Prishtina, Kosovo',
      'brand' => 'Teil der ENTRIKS Group',
      'brand_link' => 'Zur ENTRIKS Group Website'
    ],
    'blog' => [
      'eyebrow' => 'Insights & Wissen',
      'title' => 'Aktuelles aus der Welt des Nearshorings',
      'subtitle' => 'Expertenwissen, Marktanalysen und Best Practices für erfolgreiches Nearshoring im Kosovo.',
      'read_more' => 'Weiterlesen',
      'btn' => 'Alle Artikel ansehen',
      'articles' => [
        [
          'image' => 'https://images.unsplash.com/photo-1486325212027-8081e485255e?auto=format&fit=crop&q=80&w=800',
          'cat' => 'Nearshoring',
          'date' => '15. März 2026',
          'read_time' => '5 min',
          'title' => 'Nearshoring im Kosovo: 5 Gründe, warum jetzt der richtige Zeitpunkt ist',
          'text' => 'Der Kosovo entwickelt sich rasant zu einem der attraktivsten Nearshoring-Standorte für DACH-Unternehmen. Wir zeigen Ihnen, warum Sie jetzt handeln sollten.'
        ],
        [
          'image' => 'https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&q=80&w=800',
          'cat' => 'Recruiting',
          'date' => '10. März 2026',
          'read_time' => '7 min',
          'title' => 'Active Sourcing Strategien: So erreichen Sie passive Kandidaten im Kosovo',
          'text' => 'Die besten Talente sind nicht aktiv auf Jobsuche. Erfahren Sie, wie Sie durch gezieltes Active Sourcing die richtigen Kandidaten finden und ansprechen.'
        ],
        [
          'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=800',
          'cat' => 'Analyse',
          'date' => '5. März 2026',
          'read_time' => '6 min',
          'title' => 'Kostenvergleich: DACH vs. Kosovo – Was Nearshoring wirklich spart',
          'text' => 'Eine detaillierte Analyse der tatsächlichen Kostenersparnis – inklusive versteckter Kosten und langfristiger Vorteile durch Nearshoring im Kosovo.'
        ]
      ]
    ],
    'faq' => [
      'eyebrow' => 'Häufige Fragen',
      'title' => 'Alles, was Sie über Nearshoring aus dem Kosovo wissen müssen.',
      'items' => [
        ['q' => 'Für welche Positionen eignet sich Nearshoring aus dem Kosovo?', 'a' => 'Nearshoring aus dem Kosovo ist besonders geeignet für: IT & Software Development, Customer Service, Finance & Controlling, Marketing & Content Creation, HR-Administration, Back-Office-Tätigkeiten, Data Entry & Analysis und viele weitere Bereiche. Grundsätzlich gilt: Alle Tätigkeiten, die remote oder hybrid ausführbar sind, eignen sich für unser Nearshoring-Modell.'],
        ['q' => 'Wie gut sprechen kosovarische Fachkräfte Deutsch?', 'a' => 'Das hängt vom Profil ab. Englisch ist in der qualifizierten Bevölkerung sehr weit verbreitet (80 %+). Deutschkenntnisse wachsen stark – besonders in der jungen Generation. Bei Positionen, die Deutsch erfordern, screenen wir explizit nach Sprachkenntnissen und testen diese im Assessment-Prozess.'],
        ['q' => 'Wie viel kann ich durch Nearshoring wirklich sparen?', 'a' => 'Unsere Kunden berichten typischerweise von 40–60 % Kostenersparnis beim Bruttogehalt im Vergleich zu DACH-Fachkräften – bei vergleichbarer Qualifikation. Hinzu kommen Ersparnisse bei Bürofläche, Infrastruktur und Nebenkosten, wenn vollständig remote gearbeitet wird.'],
        ['q' => 'Wie funktioniert die rechtliche Absicherung?', 'a' => 'ENTRIKS Talent Hub begleitet Sie vollständig durch alle rechtlichen Aspekte der Zusammenarbeit mit kosovarischen Fachkräften. Wir arbeiten mit erfahrenen Anwälten und lokalen Experten zusammen, die alle Vertragsstrukturen und Compliance-Anforderungen abdecken.'],
        ['q' => 'Wie lange dauert es, bis ich meinen ersten Kandidaten sehe?', 'a' => 'Bei Active Sourcing und Nearshoring-Positionen sehen Sie erste qualifizierte Kandidatenprofile in der Regel innerhalb von 7–10 Werktagen nach Kick-off. Der gesamte Prozess bis zur Besetzung dauert im Durchschnitt 21 Tage.'],
        ['q' => 'Was passiert, wenn ein Kandidat nicht passt?', 'a' => 'Wir bieten eine Nachbesetzungsgarantie. Sollte eine Besetzung innerhalb der vereinbarten Garantiezeit aus fachlichen oder persönlichen Gründen nicht funktionieren, übernehmen wir die Neusuche ohne zusätzliche Grundkosten.'],
        ['q' => 'Kann ich das Nearshoring-Modell erst klein testen?', 'a' => 'Absolut. Viele unserer Kunden starten mit einem oder zwei dedizierten Fachkräften, um das Modell zu testen. Nach den ersten Erfahrungen skalieren die meisten. Kein Minimum-Commitment erforderlich.']
      ]
    ],
    'cta' => [
      'title' => 'Bereit, die besten Talente im Kosovo<br>für sich zu gewinnen?',
      'subtitle' => 'Jedes Gespräch beginnt mit Zuhören. Wir verstehen Ihren Bedarf – und liefern dann Ergebnisse, die für sich sprechen.',
      'btn1' => 'Kostenloses Erstgespräch buchen',
      'btn2' => 'Unser Leistungsangebot herunterladen',
      'trust' => ['Termin in 24h', 'Direkt erreichbar', '100% unverbindlich']
    ],
    'modal' => [
      'title' => 'Kostenloses Erstgespräch buchen',
      'company' => 'Unternehmen',
      'name' => 'Ihr Name',
      'email_label' => 'E-Mail',
      'email_address' => 'info@entriks.com',
      'phone' => '+38343889344',
      'phone_display' => '+383 43 889 344',
      'phone_label' => 'Telefon',
      'service_label' => 'Interesse an',
      'services' => [
        'Nearshoring Einzelplatz',
        'Nearshoring Team',
        'Active Sourcing',
        'Beratung',
        'Sonstiges'
      ],
      'message' => 'Nachricht (optional)',
      'privacy_label' => 'Ich stimme der Datenschutzerklärung zu und bin einverstanden, dass meine Daten zur Kontaktaufnahme verwendet werden. *',
      'cancel' => 'Abbrechen',
      'submit' => 'Termin anfordern'
    ],
    'footer' => [
      'desc' => 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo – durch Nearshoring und Active Sourcing.',
      'note' => 'Teil der ENTRIKS Group',
      'contact' => 'Kontakt',
      'services' => 'Leistungen',
      'company' => 'Unternehmen',
      'links' => [
        'Nearshoring Dedicated',
        'Nearshoring Team',
        'Active Sourcing',
        'Kosovo Standort'
      ],
      'company_links' => [
        'ENTRIKS Group',
        'ENTRIKS Karriere',
        'ENTRIKS Banking',
        'ENTRIKS Sales',
        'ENTRIKS Software'
      ],
      'legal' => ['Impressum', 'Datenschutz', 'AGB'],
      'copyright' => '© 2026 ENTRIKS Talent Hub'
    ]
  ],
  'en' => [
    'nav' => ['Nearshoring', 'Active Sourcing', 'Blog', 'About Us', 'Contact'],
    'hero' => [
      'eyebrow' => 'Part of the ENTRIKS Group',
      'title' => 'European Quality.<br><span style="color:var(--gold)">Strategic Talent.</span><br>Your Competitive Advantage.',
      'subtitle' => 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals – through strategic nearshoring and targeted active sourcing. Faster. More cost-efficient. Sustainable.',
      'btn1' => 'Discover Nearshoring Potential',
      'btn2' => 'Free Initial Consultation',
      'trust1' => 'Free & Non-binding',
      'trust2' => 'Response in 24h',
      'trust3' => 'Personal Consultation'
    ],
    'stats' => [
      'stat1' => ['value' => '500', 'suffix' => '+', 'label' => 'Professionals placed in Kosovo'],
      'stat2' => ['value' => '98', 'suffix' => '%', 'label' => 'Client satisfaction'],
      'stat3' => ['value' => '21', 'suffix' => ' days', 'label' => 'Avg. Time-to-Hire'],
      'stat4' => ['value' => '60', 'suffix' => '%', 'label' => 'Cost savings vs. DACH'],
      'stat5' => ['value' => '100', 'suffix' => '+', 'label' => 'Partner companies'],
      'brand_strip' => 'Part of the ENTRIKS Group'
    ],
    'why' => [
      'eyebrow' => 'Why ENTRIKS Talent Hub?',
      'title' => 'The skilled worker shortage in the DACH region is real. So is our solution.',
      'text1' => 'German, Austrian, and Swiss companies lose valuable time and resources every day in endless recruiting cycles – for positions that the domestic market simply can no longer adequately fill.',
      'text2' => 'We bring the right talent to the right companies in the DACH region – structured, legally secure, and sustainable.',
      'card1' => ['title' => 'Nearshoring from Kosovo', 'text' => 'We place and integrate professionals from Prishtina into your corporate structure – remote, hybrid, or on-site. Full control. Minimal risk.', 'link' => 'Learn more'],
      'card2' => ['title' => 'Active Sourcing for Your Company', 'text' => 'Our team identifies, qualifies, and presents candidates to you specifically across Europe – before they even actively search.', 'link' => 'Learn more']
    ],
    'nearshoring' => [
      'label' => 'Service 01',
      'title' => 'Nearshoring from Kosovo – Your Remote Team at the Push of a Button.',
      'text1' => 'Nearshoring does not mean outsourcing into the unknown. It means: You get a dedicated employee or a dedicated team – fully integrated into your processes, working for you from Prishtina.',
      'text2' => 'ENTRIKS Talent Hub takes over the entire process: from identification to qualification to legally secure integration into your corporate structure.',
      'features' => [
        'Complete process takeover – from search to onboarding',
        'Dedicated professionals working exclusively for you',
        'Legally secure contract design & compliance',
        'Language skills: Albanian, English, German (depending on profile)',
        'Flexible models: Remote, Hybrid, temporary or permanent',
        'Continuous support after placement',
        'Cultural onboarding program included'
      ],
      'btn' => 'Learn About Nearshoring Models',
      'process' => [
        ['num' => '01', 'title' => 'Briefing', 'desc' => 'We understand your needs, your culture, and your requirements in a structured kick-off meeting.', 'day' => 'Day 1'],
        ['num' => '02', 'title' => 'Search & Match', 'desc' => 'Our team in Prishtina starts the targeted search and qualifies candidates according to your criteria.', 'day' => 'Day 2–7'],
        ['num' => '03', 'title' => 'Presentation', 'desc' => 'You receive 3–5 pre-qualified profiles with video interviews and detailed assessment.', 'day' => 'Day 7–12'],
        ['num' => '04', 'title' => 'Integration', 'desc' => 'We accompany the onboarding process and ensure seamless integration into your team.', 'day' => 'Day 17–21']
      ]
    ],
    'active_sourcing' => [
      'label' => 'Service 02',
      'title' => "Active Sourcing – We Find Who You're Looking For. Before Others Do.",
      'text1' => 'The best candidates are not on job portals. They are in their current positions – satisfied, in demand, and not actively searching. These are exactly the talents we reach.',
      'text2' => 'Our specialized Active Sourcing team uses professional networks, direct outreach, and an established candidate network across Europe.',
      'features' => [
        'Proactive direct outreach to qualified candidates',
        'Access to passive talent (not actively job searching)',
        'Multi-channel sourcing: LinkedIn, specialist forums, university networks',
        'Structured screening & pre-qualification',
        'Video interviews & skills assessment before presentation',
        'Complete candidate dossiers for your decision',
        'Discrete & professional candidate approach'
      ],
      'btn' => 'Request Active Sourcing',
      'feat_cards' => [
        ['title' => 'Precision Over Quantity', 'text' => "We don't present lists of 20 candidates. You get 3–5 profiles that really fit. Each one individually checked and evaluated."],
        ['title' => 'Speed-to-Hire', 'text' => 'Our experienced team knows the European markets. Average time-to-first-candidate: 7 business days. No long waiting times.'],
        ['title' => 'Quality Guarantee', 'text' => "Placements that don't fit, we replace. Our replacement guarantee gives you security for strategic decisions."]
      ]
    ],
    'kosovo' => [
      'eyebrow' => 'Kosovo Location',
      'title' => 'Why Kosovo?',
      'subtitle' => 'Strategically positioned between East and West, Kosovo offers unique advantages for DACH companies.',
      'cards' => [
        ['label' => 'Avg. 29 years', 'title' => 'Average age', 'desc' => 'One of the youngest populations in Europe'],
        ['label' => '40–60%', 'title' => 'Cost savings', 'desc' => 'Compared to DACH professionals at equal quality'],
        ['label' => 'CET +0h', 'title' => 'Time zone', 'desc' => 'Full synchrony with DACH region']
      ],
      'quote' => ['text' => 'Kosovo delivers quality, motivation, and cost efficiency in a unique combination.', 'author' => 'René Schirner', 'role' => 'Founder & CEO, ENTRIKS Group']
    ],
    'angebot' => [
      'eyebrow' => 'Our Offer',
      'title' => 'Tailored to Your Needs.',
      'subtitle' => "Every company is different. That's why we don't offer standard solutions – but models that fit your situation.",
      'popular' => 'Most Popular Option',
      'cards' => [
        [
          'title' => 'Nearshoring Dedicated',
          'text' => 'One or more dedicated professionals from Kosovo work fully for your company – integrated into your structures, your tools, your culture.',
          'tags' => ['Remote', 'Hybrid', 'Full-time', 'Part-time'],
          'link' => 'Request Details'
        ],
        [
          'title' => 'Nearshoring Team',
          'text' => 'We put together a complete nearshoring team for you – built, coordinated, and supported by ENTRIKS Talent Hub.',
          'tags' => ['2–20 people', 'Scalable', 'Managed'],
          'link' => 'Request Details'
        ],
        [
          'title' => 'Active Sourcing',
          'text' => 'You are looking for a specific professional for a permanent position on-site. Our team takes over the entire search, qualification, and presentation.',
          'tags' => ['Direct placement', 'Permanent', 'DACH'],
          'link' => 'Request Details'
        ]
      ]
    ],
    'expertise' => [
      'eyebrow' => 'Our Expertise',
      'title' => 'Typically Placed Positions',
      'subtitle' => 'In these areas, we place highly qualified professionals every day',
      'tags' => ['IT & Software Development', 'Customer Service', 'Finance & Controlling', 'Marketing & Content', 'HR Administration', 'Data Analysis', 'Sales Support', 'Back-Office', 'Project Management', 'Graphic Design', 'E-Commerce', 'Accounting']
    ],
    'process' => [
      'eyebrow' => 'How We Work',
      'title' => 'From Request to Filled Position – in 21 Days.',
      'subtitle' => 'Our process is transparent, structured, and fully tailored to your needs.<br>No chaos. No waiting in the dark.',
      'steps' => [
        ['num' => '01', 'title' => 'Kick-off & Briefing', 'desc' => 'In a 60-minute conversation, we capture your exact needs: technical, cultural, and temporal.', 'day' => 'Day 1'],
        ['num' => '02', 'title' => 'Sourcing & Screening', 'desc' => 'Our team in Prishtina starts immediately. Candidates are identified, contacted, and structurally screened.', 'day' => 'Day 2–7'],
        ['num' => '03', 'title' => 'Assessment & Qualification', 'desc' => 'Video interviews, skills tests, and cultural fit analysis. Only the best 3–5 candidates move forward.', 'day' => 'Day 7–12'],
        ['num' => '04', 'title' => 'Presentation & Decision', 'desc' => 'You receive complete candidate dossiers with video interviews. You make the decision.', 'day' => 'Day 12–16'],
        ['num' => '05', 'title' => 'Onboarding & Integration', 'desc' => 'We actively accompany the onboarding and ensure your new team member can perform from day one.', 'day' => 'Day 17–21']
      ]
    ],
    'success' => [
      'eyebrow' => 'Success Stories',
      'title' => 'Companies Already Benefiting from Kosovo Talent.',
      'subtitle' => 'Real results. Real companies. Real difference.',
      'stories' => [
        [
          'icon' => 'code-bracket-square',
          'title' => 'Software Developer Team for Stuttgart Tech Company',
          'text' => 'A mid-sized software company from Stuttgart urgently needed 8 backend developers. ENTRIKS Talent Hub filled all 8 positions within 19 days – with 52% cost savings.'
        ],
        [
          'icon' => 'users',
          'title' => 'Customer Service Team for Vienna E-Commerce',
          'text' => 'A Vienna e-commerce scaleup needed a multilingual customer service team. ENTRIKS Talent Hub built a 12-person team within 25 days – trilingual, fully remote.'
        ]
      ]
    ],
    'testimonials' => [
      'title' => 'What Our Clients Say',
      'reviews' => [
        ['text' => '"The recruitment team at Entriks is excellent. They understand our technical requirements perfectly and don\'t deliver lists, but a curated selection of highly qualified professionals. Proactive communication and transparent processes from the first meeting to hiring."', 'name' => 'Schmidt S.', 'role' => 'Head of Technology', 'source' => 'Google'],
        ['text' => '“What impressed us was the selection process. The candidates were exceptionally well-prepared and highly qualified. The recruitment consultants bridged the gap between European business standards and the talent pool in Kosovo with absolute professionalism.”', 'name' => 'Krasniqi A.', 'role' => 'HR Manager', 'source' => 'Google'],
        ['text' => '“As part of the team, I especially appreciate the professional onboarding support and the clear structures. Entriks offers great development opportunities and a work culture based on appreciation and innovation.”', 'name' => 'Müller H.', 'role' => 'Employee', 'source' => 'LinkedIn']
      ]
    ],
    'clients' => [
      'subtitle' => 'From startups to established companies – together we create success.'
    ],
    'about' => [
      'eyebrow' => 'About ENTRIKS Talent Hub',
      'title' => 'Anchored in Prishtina. Connected to Europe.',
      'text1' => 'ENTRIKS Talent Hub is part of the ENTRIKS Group – one of the fastest-growing corporate groups in nearshoring, sales, and business services.',
      'text2' => 'With our headquarters in Prishtina, Kosovo, we are where the talent is. Not somewhere in a distant administrative center – but directly in the market, directly with the people, directly at the source.',
      'text3' => 'Our team knows the Kosovar labor market like no other. We have built networks, gained trust, and created structures that deliver real results.',
      'location_label' => 'Our Location',
      'location_name' => 'ENTRIKS Talent Hub',
      'location_city' => 'Prishtina, Kosovo',
      'brand' => 'Part of the ENTRIKS Group',
      'brand_link' => 'To ENTRIKS Group Website'
    ],
    'blog' => [
      'eyebrow' => 'Insights & Knowledge',
      'title' => 'Latest from the World of Nearshoring',
      'subtitle' => 'Expert knowledge, market analyses, and best practices for successful nearshoring in Kosovo.',
      'read_more' => 'Read more',
      'btn' => 'View All Articles',
      'articles' => [
        [
          'image' => 'https://images.unsplash.com/photo-1486325212027-8081e485255e?auto=format&fit=crop&q=80&w=800',
          'cat' => 'Nearshoring',
          'date' => 'March 15, 2026',
          'read_time' => '5 min',
          'title' => 'Nearshoring in Kosovo: 5 Reasons Why Now is the Right Time',
          'text' => 'Kosovo is rapidly becoming one of the most attractive nearshoring locations for DACH companies. We show you why you should act now.'
        ],
        [
          'image' => 'https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&q=80&w=800',
          'cat' => 'Recruiting',
          'date' => 'March 10, 2026',
          'read_time' => '7 min',
          'title' => 'Active Sourcing Strategies: How to Reach Passive Candidates in Kosovo',
          'text' => 'The best talents are not actively job hunting. Learn how to find and approach the right candidates through targeted Active Sourcing.'
        ],
        [
          'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=800',
          'cat' => 'Analysis',
          'date' => 'March 5, 2026',
          'read_time' => '6 min',
          'title' => 'Cost Comparison: DACH vs. Kosovo – What Nearshoring Really Saves',
          'text' => 'A detailed analysis of actual cost savings – including hidden costs and long-term benefits of nearshoring in Kosovo.'
        ]
      ]
    ],
    'faq' => [
      'eyebrow' => 'Frequently Asked Questions',
      'title' => 'Everything you need to know about nearshoring from Kosovo.',
      'items' => [
        ['q' => 'For which positions is nearshoring from Kosovo suitable?', 'a' => 'Nearshoring from Kosovo is particularly suitable for: IT & Software Development, Customer Service, Finance & Controlling, Marketing & Content Creation, HR Administration, Back-Office activities, Data Entry & Analysis, and many other areas. Basically: All activities that can be performed remotely or hybrid are suitable for our nearshoring model.'],
        ['q' => 'How well do Kosovar professionals speak German?', 'a' => 'It depends on the profile. English is very widely spoken in the qualified population (80%+). German skills are growing strongly – especially among the younger generation. For positions requiring German, we explicitly screen for language skills and test them in the assessment process.'],
        ['q' => 'How much can I really save through nearshoring?', 'a' => 'Our clients typically report 40–60% cost savings on gross salary compared to DACH professionals – at comparable qualification levels. Additional savings come from office space, infrastructure, and ancillary costs when working fully remote.'],
        ['q' => 'How does legal safeguarding work?', 'a' => 'ENTRIKS Talent Hub accompanies you completely through all legal aspects of working with Kosovar professionals. We work with experienced lawyers and local experts who cover all contract structures and compliance requirements.'],
        ['q' => 'How long does it take to see my first candidate?', 'a' => 'For Active Sourcing and Nearshoring positions, you typically see first qualified candidate profiles within 7–10 business days after kick-off. The entire process until placement takes an average of 21 days.'],
        ['q' => "What happens if a candidate doesn't fit?", 'a' => 'We offer a replacement guarantee. If a placement does not work out within the agreed guarantee period for professional or personal reasons, we take over the new search without additional base costs.'],
        ['q' => 'Can I test the nearshoring model on a small scale first?', 'a' => 'Absolutely. Many of our clients start with one or two dedicated professionals to test the model. After the first experiences, most scale up. No minimum commitment required.']
      ]
    ],
    'cta' => [
      'title' => 'Ready to Win the Best Talent in Kosovo<br>for Yourself?',
      'subtitle' => 'Every conversation starts with listening. We understand your needs – and then deliver results that speak for themselves.',
      'btn1' => 'Book Free Initial Consultation',
      'btn2' => 'Download Our Service Portfolio',
      'trust' => ['Appointment in 24h', 'Directly reachable', '100% non-binding']
    ],
    'modal' => [
      'title' => 'Book Free Initial Consultation',
      'company' => 'Company *',
      'name' => 'Your Name *',
      'email_label' => 'Email *',
      'email_address' => 'info@entriks.com',
      'phone' => '+38343889344',
      'phone_display' => '+383 43 889 344',
      'phone_label' => 'Phone',
      'service_label' => 'Interested in *',
      'services' => [
        'Nearshoring Dedicated',
        'Nearshoring Team',
        'Active Sourcing',
        'Beratung',
        'Sonstiges'
      ],
      'message' => 'Message (optional)',
      'privacy_label' => 'I agree to the privacy policy and consent to my data being used for contact purposes. *',
      'cancel' => 'Cancel',
      'submit' => 'Request Appointment'
    ],
    'footer' => [
      'desc' => 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals from Kosovo through Nearshoring and Active Sourcing.',
      'note' => 'Part of the ENTRIKS Group',
      'contact' => 'Contact',
      'services' => 'Services',
      'company' => 'Company',
      'links' => [
        'Nearshoring Dedicated',
        'Nearshoring Team',
        'Active Sourcing',
        'Kosovo Location'
      ],
      'company_links' => [
        'ENTRIKS Group',
        'ENTRIKS Career',
        'ENTRIKS Banking',
        'ENTRIKS Sales',
        'ENTRIKS Software'
      ],
      'legal' => ['Legal Notice', 'Privacy', 'Terms'],
      'copyright' => '© 2026 ENTRIKS Talent Hub'
    ]
  ]
];

$c = $content[$lang];

// Helper function to get content from static arrays (replaces CMS functionality)
function getCmsContent($key, $default = '') {
  global $c;
  $keys = explode('.', $key);
  $value = $c;
  foreach ($keys as $k) {
    if (is_array($value) && isset($value[$k])) {
      $value = $value[$k];
    } else {
      return $default;
    }
  }
  return is_array($value) ? $default : $value;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo. Nearshoring & Active Sourcing für IT, Finance, Customer Service & mehr.">
    <meta name="keywords" content="Nearshoring Kosovo, Active Sourcing, Fachkräfte Kosovo, Recruiting DACH, IT Talent Kosovo, Remote Teams Kosovo">
    <meta name="author" content="ENTRIKS Talent Hub">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="ENTRIKS Talent Hub - Europäische Qualität. Strategische Talente.">
    <meta property="og:description" content="Verbinden Sie sich mit hochqualifizierten Fachkräften aus dem Kosovo. Nearshoring & Active Sourcing für Ihr Unternehmen.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://talent.entriks.com">
    <meta property="og:image" content="https://talent.entriks.com/assets/img/og-image.jpg">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="https://talent.entriks.com/">
    <title>ENTRIKS Talent Hub | Nearshoring & Active Sourcing aus dem Kosovo</title>
    
    <!-- Schema.org Organization Markup -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "ENTRIKS Talent Hub",
        "alternateName": "ENTRIKS L.L.C.",
        "url": "https://talent.entriks.com",
        "logo": "https://talent.entriks.com/assets/img/logo.png",
        "description": "ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo durch Nearshoring und Active Sourcing.",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Lot Vaku, L 2.1",
            "addressLocality": "Pristina",
            "postalCode": "10000",
            "addressCountry": "XK"
        },
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+383-43-889-344",
            "contactType": "customer service",
            "email": "info@entriks.com",
            "availableLanguage": ["German", "English", "Albanian"]
        },
        "sameAs": [
            "https://www.facebook.com/ENTRIKS/",
            "https://www.instagram.com/entriks_/",
            "https://www.linkedin.com/company/entriks"
        ],
        "parentOrganization": {
            "@type": "Organization",
            "name": "ENTRIKS Group",
            "url": "https://entriks.com"
        }
    }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Orbitron:wght@400;500;600;700;800;900&display=swap"
      rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
      *,
      *::before,
      *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        --gold: #c9a227;
        --gold-hover: #b8911f;
        --cyan: #20c1f5;
        --cyan-dark: #20c1f5;
        --bg-black: #000;
        --bg-light: #f2f2ee;
        --text-white: #fff;
        --text-muted: #888;
        --text-dark: #1a1a1a;
        --border: #2a2a2a;
        --border-light: #e0e0e0;
      }

      html {
        scroll-behavior: smooth;
      }

      body {
        font-family: 'Inter', sans-serif;
        background: var(--bg-black);
        color: var(--text-white);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        overflow-x: hidden;
      }

      a {
        text-decoration: none;
        color: inherit;
      }

      button {
        cursor: pointer;
        border: none;
        outline: none;
        font-family: inherit;
      }

      img {
        display: block;
      }

      .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 2rem;
      }

      /* ── LOGO ── */
      .logo-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        cursor: pointer;
      }

      .logo-img {
        height: 28px;
        width: auto;
        display: block;
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
        margin-top: 2px;
      }

      /* ── NAVBAR ── */
      .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        padding: 1.1rem 0;
        transition: all .3s;
        transform: translateY(0);
      }

      .navbar.hidden {
        transform: translateY(-100%);
      }

      .navbar.scrolled {
        background: rgba(8, 8, 8, .95);
        backdrop-filter: blur(16px);
        border-bottom: 1px solid rgba(255, 255, 255, .06);
      }

      .nav-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      .nav-links {
        display: flex;
        gap: 2.25rem;
      }

      .nav-links a {
        font-size: .88rem;
        color: #bbb;
        font-weight: 500;
        transition: color .2s;
        white-space: nowrap;
      }

      .nav-links a:hover {
        color: #fff;
      }

      .btn-nav {
        background: var(--gold);
        color: #000;
        font-weight: 700;
        font-size: .85rem;
        padding: .6rem 1.35rem;
        border-radius: .4rem;
        transition: all .25s;
        white-space: nowrap;
      }

      .btn-nav:hover {
        background: var(--gold-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(201, 162, 39, .35);
      }

      /* ── Language dropdown ── */
      .lang-dropdown-wrap {
        position: relative;
      }

      .lang-globe-btn {
        display: flex;
        align-items: center;
        gap: .4rem;
        background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 2rem;
        padding: .35rem .75rem;
        cursor: pointer;
        color: #ccc;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .06em;
        transition: background .2s, border-color .2s;
      }

      .lang-globe-btn:hover {
        background: rgba(255,255,255,.1);
        border-color: rgba(255,255,255,.25);
        color: #fff;
      }

      .lang-globe-btn svg {
        width: 15px;
        height: 15px;
        flex-shrink: 0;
      }

      .lang-globe-btn .lang-caret {
        width: 10px;
        height: 10px;
        transition: transform .25s;
      }

      .lang-dropdown-wrap.open .lang-caret {
        transform: rotate(180deg);
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
        box-shadow: 0 12px 32px rgba(0,0,0,.45);
        z-index: 2000;
      }

      .lang-dropdown-wrap.open .lang-dropdown {
        display: block;
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
      }

      .lang-option:hover {
        background: rgba(255,255,255,.06);
        color: #fff;
      }

      .lang-option.active {
        color: var(--gold);
        font-weight: 700;
      }

      .lang-option .lang-flag {
        font-size: 1.1rem;
        line-height: 1;
      }

      .lang-option .lang-check {
        margin-left: auto;
        color: var(--gold);
        display: none;
      }

      .lang-option.active .lang-check {
        display: block;
      }

      /* keep old lang-btn/sep for mobile fallback */
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
        transition: color .2s, background .2s;
      }

      .lang-btn.active {
        color: var(--gold);
      }

      .lang-sep {
        color: rgba(255,255,255,.2);
        font-size: .75rem;
      }

      .mob-btn {
        display: none;
        background: none;
        color: #fff;
        padding: .25rem;
      }

      .nav-mobile-group {
        display: none;
        align-items: center;
        gap: .75rem;
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
        gap: 1.1rem;
      }

      .mob-menu.open {
        display: flex;
      }

      @media(max-width:999px) {
        .nav-links,
        .nav-cta {
          display: none;
        }

        .mob-btn {
          display: block;
        }

        .nav-mobile-group {
          display: flex;
          align-items: center;
          gap: .75rem;
        }

        .nav-mobile-group .lang-globe-btn {
          display: flex;
        }
      }

      /* ── HERO ── */
      .hero {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding-top: 72px;
        text-align: center;
        overflow: hidden;
      }

      .hero-bg {
        position: absolute;
        inset: 0;
        background: url('assets/img/Prishtina_skyline.jpg') center/cover no-repeat;
        filter: brightness(.55) saturate(.9);
      }

      .hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0, 0, 0, .3) 0%, rgba(0, 0, 0, .65) 100%);
      }

      .hero-content {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 1160px;
        padding: 0 2rem;
        box-sizing: border-box;
      }

      .hero-eyebrow {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .2em;
        color: var(--gold);
        text-transform: uppercase;
        margin-bottom: 1.75rem;
        opacity: 0;
        animation: fadeUp .8s .2s forwards;
      }

      .hero-title {
        font-size: clamp(2.4rem, 5vw, 4.2rem);
        font-weight: 900;
        line-height: 1.12;
        margin-bottom: 1.5rem;
        letter-spacing: -.01em;
        opacity: 0;
        animation: fadeUp .8s .4s forwards;
      }

      .hero-sub {
        font-size: 1rem;
        color: #ccc;
        max-width: 560px;
        margin: 0 auto 2.5rem;
        line-height: 1.75;
        opacity: 0;
        animation: fadeUp .8s .6s forwards;
      }

      .hero-btns {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        opacity: 0;
        animation: fadeUp .8s .8s forwards;
      }

      @media(max-width:640px) {
        .hero-btns {
          flex-direction: column;
          align-items: center;
        }
        .hero-btns .btn-gold,
        .hero-btns .btn-outline-white {
          width: 100%;
          max-width: 280px;
          text-align: center;
          justify-content: center;
        }
      }

      .btn-gold {
        background: var(--gold);
        color: #000;
        font-weight: 700;
        padding: .875rem 1.75rem;
        border-radius: .4rem;
        font-size: .95rem;
        transition: all .25s;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
      }

      .btn-outline-white {
        background: transparent;
        color: var(--text-white);
        border: 2px solid var(--text-white);
        font-weight: 600;
        padding: .875rem 1.75rem;
        border-radius: .4rem;
        font-size: .95rem;
        transition: all .25s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }

      .hero-trust {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        font-size: .78rem;
        color: #999;
        flex-wrap: wrap;
        opacity: 0;
        animation: fadeUp .8s 1s forwards;
      }

      .hero-trust span {
        display: flex;
        align-items: center;
        gap: .4rem;
      }

      .h-icon {
        width: 1.15rem;
        height: 1.15rem;
        flex-shrink: 0;
      }

      .h-icon.gold {
        color: var(--gold);
      }

      .h-icon.cyan {
        color: var(--cyan);
      }

      .h-arrow {
        width: 1rem;
        height: 1rem;
        display: inline-block;
        vertical-align: middle;
        margin-left: .3rem;
        transition: transform .2s;
        color: inherit;
      }

      a:hover .h-arrow,
      button:hover .h-arrow {
        transform: translateX(3px);
      }

      .h-check-small {
        width: .85rem;
        height: .85rem;
        color: inherit;
        margin-left: .25rem;
        display: inline-block;
        vertical-align: middle;
      }

      @keyframes fadeUp {
        from {
          opacity: 0;
          transform: translateY(22px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      /* ── STATS ── */
      .stats-section {
        background: #0d0d0d;
        padding: 3.5rem 0 0;
      }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 1rem;
        text-align: center;
        padding-bottom: 3rem;
      }

      .stat-val {
        font-size: clamp(2rem, 3.5vw, 2.75rem);
        font-weight: 800;
        color: #fff;
        margin-bottom: .3rem;
      }

      .stat-lbl {
        font-size: .72rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .1em;
        line-height: 1.5;
      }

      .brand-strip {
        padding: 1.5rem 0;
        border-top: 1px solid var(--border);
        text-align: center;
      }

      .brand-strip p {
        font-size: .72rem;
        color: #444;
        letter-spacing: .18em;
      }

      @media(max-width:768px) {
        .stats-grid {
          grid-template-columns: repeat(2, 1fr);
        }

        .stats-grid .stat-item:last-child {
          grid-column: 1/-1;
        }
      }

      /* ── SCROLL REVEAL ── */
      .sr {
        opacity: 0;
        transform: translateY(28px);
        transition: opacity .7s ease, transform .7s ease;
      }

      .sr.on {
        opacity: 1;
        transform: translateY(0);
      }

      /* ── SHARED ── */
      .eyebrow {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .2em;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: .85rem;
      }

      .eyebrow.cyan {
        color: var(--cyan);
      }

      .sec-ey {
        text-align: center;
      }

      .sec-title {
        font-size: clamp(1.75rem, 3.5vw, 2.65rem);
        font-weight: 800;
        text-align: center;
        margin-bottom: .75rem;
      }

      .sec-sub {
        color: #888;
        text-align: center;
        font-size: .925rem;
        margin-bottom: 3.5rem;
        line-height: 1.7;
      }

      .sec-sub.dk {
        color: #888;
      }

      /* ── WHY (white) ── */
      .why-section {
        background: #fff;
        color: var(--text-dark);
        padding: 6rem 0;
      }

      .why-inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
      }

      .why-left {
        text-align: left;
      }

      .why-left .eyebrow {
        color: var(--gold);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 1.2rem;
      }

      .why-title {
        font-size: clamp(1.8rem, 3vw, 2.6rem);
        font-weight: 800;
        line-height: 1.15;
        margin-bottom: 1.4rem;
        color: #0d0d0d;
      }

      .why-text {
        color: #555;
        font-size: .95rem;
        line-height: 1.8;
        margin: 0;
      }

      .divider {
        width: 48px;
        height: 3px;
        background: var(--gold);
        margin: 1.8rem 0;
      }

      .why-cards {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
      }

      .why-card {
        background: #1c1c1c;
        border-radius: 14px;
        padding: 1.6rem 1.75rem;
        display: flex;
        align-items: flex-start;
        gap: 1.25rem;
        transition: border-color .25s;
        border: 1px solid transparent;
      }

      .why-card:hover {
        border-color: rgba(180,140,60,.45);
      }

      .why-card-icon {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        background: rgba(180,140,60,.18);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold);
      }

      .why-card-icon svg {
        width: 22px;
        height: 22px;
      }

      .why-card-body {
        flex: 1;
      }

      .why-card h3 {
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: .5rem;
        color: #fff;
      }

      .why-card p {
        color: #aaa;
        font-size: .875rem;
        line-height: 1.7;
        margin-bottom: .75rem;
      }

      .why-card a {
        color: var(--gold);
        font-size: .85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        text-decoration: none;
      }

      .why-card a:hover {
        opacity: .85;
      }

      .h-arrow {
        width: 14px;
        height: 14px;
      }

      @media(max-width:900px) {
        .why-inner {
          grid-template-columns: 1fr;
          gap: 2.5rem;
        }
      }

      /* ── LEISTUNG ── */
      .dark-sec {
        background: var(--bg-black);
        padding: 6rem 0;
      }

      .light-sec {
        background: var(--bg-light);
        padding: 6rem 0;
        color: var(--text-dark);
      }

      .leistung-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5rem;
        align-items: center;
      }

      .leistung-label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .2em;
        color: var(--gold);
        text-transform: uppercase;
        margin-bottom: .85rem;
      }

      .leistung-title {
        font-size: clamp(1.9rem, 3.5vw, 2.65rem);
        font-weight: 800;
        line-height: 1.15;
        margin-bottom: 1.25rem;
      }

      .leistung-text {
        color: #999;
        font-size: .925rem;
        line-height: 1.8;
        margin-bottom: 1.1rem;
      }

      .leistung-text.dk {
        color: #555;
      }

      .check-list {
        list-style: none;
        margin-bottom: 2rem;
      }

      .check-list li {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        color: #ccc;
        font-size: .875rem;
        margin-bottom: .7rem;
        opacity: 0;
        transform: translateX(-12px);
        transition: opacity .45s ease, transform .45s ease;
      }

      .check-list li.dk {
        color: #555;
      }

      .check-list li.on {
        opacity: 1;
        transform: translateX(0);
      }

      .h-icon-check {
        width: 1.1rem;
        height: 1.1rem;
        color: var(--gold);
        flex-shrink: 0;
        margin-top: .1rem;
      }

      .areas-text {
        font-size: .78rem;
        color: #555;
        margin-top: 1.25rem;
        line-height: 1.6;
      }

      .leistung-img {
        border-radius: .75rem;
        overflow: hidden;
        height: 420px;
        flex-shrink: 0;
      }

      .leistung-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: grayscale(.2);
        transition: transform .6s;
      }

      .leistung-img:hover img {
        transform: scale(1.04);
      }

      .proc4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-top: 4rem;
      }

      .step-num {
        font-size: 2.25rem;
        font-weight: 800;
        color: var(--gold);
        line-height: 1;
        margin-bottom: .4rem;
      }

      .step-line {
        width: 28px;
        height: 2px;
        background: var(--border);
        margin-bottom: .7rem;
      }

      .step-title {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .07em;
        margin-bottom: .45rem;
      }

      .step-desc {
        font-size: .78rem;
        color: #888;
        line-height: 1.6;
      }

      @media(max-width:968px) {
        .leistung-grid {
          grid-template-columns: 1fr;
          gap: 3rem;
        }

        .proc4 {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      /* ── FEAT CARDS ── */
      .feat3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
        margin-top: 3rem;
      }

      .feat-card {
        background: #1c1c1c;
        color: #fff;
        padding: 2rem;
        border-radius: .75rem;
        border: 1px solid #2a2a2a;
        transition: border-color .3s, transform .3s;
      }

      .feat-card:hover {
        border-color: var(--gold);
        transform: translateY(-4px);
      }

      .feat-icon {
        font-size: 1.75rem;
        margin-bottom: .875rem;
      }

      .feat-card h4 {
        font-size: .975rem;
        font-weight: 700;
        margin-bottom: .6rem;
      }

      .feat-card p {
        font-size: .825rem;
        color: #999;
        line-height: 1.65;
      }

      @media(max-width:768px) {
        .feat3 {
          grid-template-columns: 1fr;
        }
      }

      /* ── KOSOVO ── */
      .kosovo-sec {
        background: linear-gradient(135deg, var(--bg-black) 0%, #0a0a0a 100%);
        padding: 6rem 0;
        text-align: center;
        position: relative;
      }

      .kosovo-sec .sec-ey {
        color: var(--gold);
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .2em;
        text-transform: uppercase;
        margin-bottom: 1rem;
      }

      .kosovo-sec .sec-title {
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 800;
        color: #fff;
        margin-bottom: 1rem;
      }

      .kosovo-sec .sec-sub {
        color: #888;
        font-size: 1rem;
        max-width: 600px;
        margin: 0 auto 3rem;
        line-height: 1.7;
      }
      
      .kosovo-sec::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 70% 30%, rgba(32,193,245,0.08) 0%, transparent 50%);
        pointer-events: none;
      }

      .kos-cards {
        display: flex;
        justify-content: center;
        align-items: stretch;
        gap: 60px;
        margin-top: 60px;
        max-width: 1400px;
        margin-left: auto;
        margin-right: auto;
      }

      .kos-card {
        flex: 1;
        max-width: 320px;
        text-align: center;
        background: none;
        border: none;
        border-radius: 0;
        padding: 0;
        position: relative;
        z-index: 1;
      }
      
      .kos-card:hover {
        transform: none;
        border-color: none;
      }
      
      .kos-card::before {
        display: none;
      }

      .kos-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        color: #d4af37;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .kos-icon svg {
        width: 100%;
        height: 100%;
      }

      .kos-label {
        font-size: 14px;
        font-weight: 500;
        color: #aaaaaa;
        margin-bottom: 4px;
        line-height: 1.3;
      }

      .kos-title {
        font-size: 18px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 8px;
        line-height: 1.3;
      }

      .kos-desc {
        font-size: 14px;
        color: #888888;
        line-height: 1.6;
        max-width: 260px;
        margin: 0 auto;
      }

      .ceo-q {
        margin-top: 80px;
        text-align: center;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
        padding: 0;
        background: none;
        border: none;
        position: relative;
      }

      .ceo-q::before {
        content: "";
        display: block;
        width: 60px;
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 0 auto 30px;
      }

      .q-mark {
        display: none;
      }

      .q-text {
        font-size: 18px;
        color: #cccccc;
        font-style: italic;
        line-height: 1.6;
        text-align: center;
        margin-bottom: 1.5rem;
        font-weight: 400;
      }

      .q-author {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
      }

      .q-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--gold);
        flex-shrink: 0;
      }

      .q-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #eeeeee;
        text-align: left;
      }

      .q-role {
        font-size: 0.78rem;
        color: #888888;
        text-align: left;
      }

      @media(max-width:768px) {
        .kos-cards {
          flex-direction: column;
          gap: 40px;
          margin-top: 40px;
        }
        
        .kos-card {
          max-width: 100%;
          padding: 0;
        }

        .kos-icon {
          width: 56px;
          height: 56px;
        }

        .ceo-q {
          margin: 60px 1rem 0;
          padding: 0;
        }

        .ceo-q::before {
          margin: 0 auto 20px;
        }
      }

      /* ── ANGEBOT ── */
      .angebot-sec {
        background: #f5f4f1;
        padding: 6rem 0;
        color: var(--text-dark);
        text-align: center;
      }

      .angebot-title {
        font-size: clamp(1.9rem, 3.5vw, 2.65rem);
        font-weight: 800;
        margin-bottom: .75rem;
        color: var(--text-dark);
      }

      .angebot-sub {
        color: #777;
        font-size: .95rem;
        max-width: 780px;
        margin: 0 auto 2.5rem;
        line-height: 1.7;
      }

      /* Outer dark rounded wrapper — matches image 1 */
      .angebot-wrap {
        background: transparent;
        border-radius: 1.25rem;
        padding: 0;
      }

      .angebot-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
      }

      .angebot-card {
        background: #222;
        color: #fff;
        padding: 2rem;
        border-radius: .75rem;
        position: relative;
        text-align: left;
        border: 1px solid #333;
        transition: border-color .3s, transform .3s;
      }

      .angebot-card:hover {
        border-color: var(--gold);
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(201,162,39,.12);
      }

      .angebot-card.popular::before {
        content: 'Beliebteste Option';
        position: absolute;
        top: -13px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--gold);
        color: #000;
        font-size: .68rem;
        font-weight: 800;
        padding: .25rem 1rem;
        border-radius: 100px;
        white-space: nowrap;
      }

      /* Icon: small gold square with icon inside */
      .angebot-icon {
        width: 2.5rem;
        height: 2.5rem;
        background: rgba(201,162,39,.15);
        border-radius: .45rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
      }

      .angebot-card h3 {
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: .75rem;
        color: #fff;
      }

      .angebot-card p {
        font-size: .82rem;
        color: #999;
        line-height: 1.75;
        margin-bottom: 1.1rem;
      }

      .angebot-tags {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-bottom: 1.4rem;
        align-items: center;
      }

      .angebot-tag {
        background: none;
        border: none;
        font-size: .8rem;
        color: var(--gold);
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-weight: 500;
      }

      .angebot-tag .h-check-small {
        color: var(--gold);
        font-size: 0.8rem;
      }

      .angebot-link {
        font-size: .85rem;
        font-weight: 700;
        color: var(--gold);
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        transition: gap .2s;
      }

      .angebot-link:hover {
        gap: .55rem;
      }

      @media(max-width:768px) {
        .angebot-grid {
          grid-template-columns: 1fr;
        }
        .angebot-wrap {
          padding: 1.25rem;
        }
      }

      /* ── EXPERTISE ── */
      .expert-sec {
        background: #f5f4f1;
        padding: 3rem 0;
      }

      .expert-box {
        background: #1a1a1a;
        border-radius: 1rem;
        padding: 3rem;
        max-width: 960px;
        margin: 0 auto;
        color: #fff;
        border: 1px solid #252525;
        text-align: center;
      }

      .expert-ey {
        font-size: .68rem;
        letter-spacing: .2em;
        color: var(--gold);
        font-weight: 700;
        text-align: center;
        margin-bottom: .75rem;
      }

      .expert-title {
        font-size: clamp(1.75rem, 3vw, 2.5rem);
        font-weight: 800;
        text-align: center;
        margin-bottom: .5rem;
        color: #fff;
      }

      .expert-sub {
        font-size: .9rem;
        color: #999;
        text-align: center;
        margin-bottom: 2rem;
      }

      .expert-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .65rem;
      }

      /* Expertise tags — image 2 style */
      .exp-tag {
        background: #1a1a1a;
        border: 1px solid #2a2a2a;
        border-radius: .65rem;
        padding: .75rem 1rem;
        font-size: .83rem;
        font-weight: 500;
        text-align: left;
        cursor: pointer;
        transition: all .22s;
        color: #fff;
        display: flex;
        align-items: center;
        gap: .55rem;
        white-space: nowrap;
        min-height: 48px;
      }

      .exp-tag::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #888;
        flex-shrink: 0;
        transition: background .22s;
      }

      .exp-tag:hover {
        background: #222;
        color: #fff;
        border-color: #3a3a3a;
      }

      .exp-tag:hover::before {
        background: var(--gold);
      }

      .exp-tag.selected {
        background: #222;
        color: var(--gold);
        border-color: rgba(201,162,39,.4);
        font-weight: 600;
      }

      .exp-tag.selected::before {
        background: var(--gold);
      }

      @media(max-width:1024px) {
        .expert-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      @media(max-width:768px) {
        .expert-grid {
          grid-template-columns: repeat(2, 1fr);
        }
        .exp-tag {
          white-space: normal;
        }
      }

      @media(max-width:480px) {
        .expert-grid {
          grid-template-columns: 1fr;
        }
        .expert-box {
          padding: 2rem 1.25rem;
        }
      }

      /* ── PROCESS ── */
      .process-sec {
        background: var(--bg-black);
        padding: 6rem 0;
      }

      .proc5 {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 1rem;
        position: relative;
        align-items: stretch;
      }

      /* dashed connector between badges */
      .proc5::before {
        content: '';
        position: absolute;
        top: 26px;
        left: 26px;
        right: 26px;
        height: 1px;
        border-top: 1px dashed rgba(201,162,39,.4);
        z-index: 0;
      }

      .proc-card {
        background: transparent;
        border: none;
        padding: 0;
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
      }

      .proc-card:hover .proc-num {
        transform: scale(1.08);
        box-shadow: 0 0 0 6px rgba(201,162,39,.25);
      }

      .proc-num {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: var(--gold);
        color: #000;
        font-size: .9rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.75rem;
        flex-shrink: 0;
        box-shadow: 0 0 0 4px rgba(201,162,39,.18);
        transition: transform .3s, box-shadow .3s;
      }

      .proc-line {
        display: none;
      }

      .proc-title {
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
        margin-bottom: .75rem;
        color: var(--gold);
        line-height: 1.45;
      }

      .proc-desc {
        font-size: .775rem;
        color: #aaa;
        line-height: 1.7;
        margin-bottom: 1.25rem;
        flex: 1;
      }

      .proc-day {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        background: rgba(201,162,39,.10);
        border: 1px solid rgba(201,162,39,.35);
        color: var(--gold);
        font-size: .68rem;
        padding: .35rem .85rem;
        border-radius: .4rem;
        font-weight: 700;
        width: fit-content;
      }

      .proc-day svg {
        width: .85rem;
        height: .85rem;
      }

      @media(max-width:968px) {
        .proc5 {
          grid-template-columns: repeat(2, 1fr);
        }
        .proc5::before { display: none; }
      }

      /* ── SUCCESS ── */
      .success-sec {
        background: #fff;
        padding: 6rem 0;
        color: var(--text-dark);
      }

      .success-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
        max-width: 1100px;
        margin: 0 auto;
      }

      .success-card {
        border: 1px solid var(--border-light);
        border-radius: 1.25rem;
        overflow: hidden;
        transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
        background: #fff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
      }
      
      .success-card:hover {
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        transform: translateY(-6px);
        border-color: rgba(201, 162, 39, 0.3);
      }

      .simg {
        height: 200px;
        overflow: hidden;
        position: relative;
        background: linear-gradient(135deg, var(--gold) 0%, #d4a832 100%);
        display: flex;
        align-items: center;
        justify-content: center;
      }
      
      .simg svg {
        width: 80px;
        height: 80px;
        color: #000;
      }

      .sbody {
        padding: 1.75rem;
      }

      .sbody h3 {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--text-dark);
        line-height: 1.4;
      }

      .sbody p {
        font-size: 0.9rem;
        color: #666;
        line-height: 1.7;
        margin: 0;
      }

      @media(max-width:768px) {
        .success-grid {
          grid-template-columns: 1fr;
          gap: 1.5rem;
        }
        
        .simg {
          height: 180px;
        }
        
        .simg svg {
          width: 64px;
          height: 64px;
        }
      }

      /* ── TESTIMONIALS ── */
      .testi-sec {
        background: #fff;
        padding: 2rem 0 6rem;
        color: var(--text-dark);
      }

      .testi-title {
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 2.5rem;
      }

      .testi-card {
        padding: 3rem;
        max-width: 600px;
        margin: 0 auto;
        color: #141414;
      }

      .stars {
        color: var(--gold);
        font-size: 1.2rem;
        text-align: center;
        margin-bottom: 1.1rem;
        letter-spacing: .1em;
      }

      .testi-text {
        font-size: 1rem;
        color: #555;
        font-weight: 500;
        text-align: center;
        line-height: 1.8;
        margin-bottom: 1.75rem;
        transition: opacity .25s;
        font-style: italic;
      }

      .testi-author {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
      }

      .testi-av {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid var(--gold);
      }

      .testi-av-initial {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--gold);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.25rem;
        color: #000;
        flex-shrink: 0;
        border: 2px solid var(--gold);
      }

      .testi-name {
        font-size: .875rem;
        font-weight: 600;
        color: #1a1a1a;
      }

      .testi-role-t {
        font-size: .775rem;
        color: #777777;
      }

      .testi-dots {
        display: flex;
        gap: .5rem;
        justify-content: center;
        margin-top: 1.5rem;
      }

      .tdot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #444;
        cursor: pointer;
        transition: all .3s;
      }

      .tdot.active {
        background: var(--gold);
        transform: scale(1.2);
      }

      /* ── CLIENT LOGOS ── */
      .client-logos-sec {
        background: #fff;
        padding: 4rem 0;
      }

      .client-logos-subtitle {
        text-align: center;
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 2.5rem;
      }

      .client-logos-subtitle strong {
        color: #000;
        font-weight: 600;
      }

      .client-logos-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 3rem;
        flex-wrap: wrap;
      }

      .client-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        opacity: 0.7;
        transition: opacity 0.3s;
      }

      .client-logo:hover {
        opacity: 1;
      }

      .client-logo img {
        height: 100%;
        width: auto;
        max-width: 140px;
        object-fit: contain;
      }

      @media(max-width:768px) {
        .client-logos-row {
          gap: 2rem;
        }
        .client-logo svg {
          height: 22px;
        }
      }

      /* ── ABOUT ── */
      .about-sec {
        background: var(--bg-black);
        padding: 6rem 0;
      }

      .about-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5rem;
        align-items: center;
      }

      .about-ey {
        font-size: .68rem;
        letter-spacing: .2em;
        color: var(--gold);
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: .7rem;
      }

      .about-title {
        font-size: clamp(1.7rem, 3vw, 2.35rem);
        font-weight: 800;
        margin-bottom: 1.4rem;
      }

      .about-text {
        color: #999;
        font-size: .925rem;
        line-height: 1.8;
        margin-bottom: 1.1rem;
      }

      .about-loc {
        border: 1px solid var(--border);
        border-radius: .75rem;
        padding: 1rem 1.25rem;
        margin: 1.5rem 0;
        display: flex;
        align-items: center;
        gap: .85rem;
        background: rgba(255, 255, 255, .02);
      }

      .about-brand {
        border: 1px solid var(--border);
        border-radius: .75rem;
        padding: 1rem 1.25rem;
        font-size: .875rem;
        color: #aaa;
        background: rgba(255, 255, 255, .02);
      }

      .about-brand a {
        color: var(--cyan);
        font-size: .85rem;
      }

      .about-imgs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .85rem;
      }

      .aimg {
        border-radius: .75rem;
        overflow: hidden;
        aspect-ratio: 4/3;
      }

      .aimg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .5s;
      }

      .aimg:hover img {
        transform: scale(1.05);
      }

      @media(max-width:768px) {
        .about-grid {
          grid-template-columns: 1fr;
          gap: 3rem;
        }
      }

      /* ── BLOG ── */
      .blog-sec {
        background: #fff;
        padding: 6rem 0;
        color: var(--text-dark);
      }

      .blog-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        max-width: 1200px;
        margin: 0 auto;
      }

      .blog-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
      }

      .blog-card:hover {
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        transform: translateY(-4px);
      }

      .bimg {
        height: 200px;
        overflow: hidden;
        position: relative;
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
      }

      .bimg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
      }

      .blog-card:hover .bimg img {
        transform: scale(1.05);
      }

      .bcat {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: #0ea5e9;
        color: #fff;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.35rem 0.9rem;
        border-radius: 100px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
      }

      .bbody {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        flex: 1;
      }

      .bmeta {
        display: flex;
        gap: 1rem;
        font-size: 0.8rem;
        color: #9ca3af;
        margin-bottom: 0.75rem;
        align-items: center;
      }

      .bmeta span {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
      }

      .bmeta svg {
        width: 1rem;
        height: 1rem;
        color: #9ca3af;
      }

      .bbody h3 {
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        line-height: 1.4;
        color: #111827;
      }

      .bbody h3 a {
        color: #111827;
        text-decoration: none;
        transition: color 0.2s ease;
      }

      .bbody h3 a:hover {
        color: #0ea5e9;
      }

      .bbody p {
        font-size: 0.875rem;
        color: #6b7280;
        line-height: 1.6;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
      }

      .blink {
        font-size: 0.875rem;
        font-weight: 600;
        color: #0ea5e9;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        text-decoration: none;
        transition: all 0.2s ease;
      }

      .blink:hover {
        gap: 0.6rem;
        color: #0284c7;
      }

      .blink svg {
        width: 1rem;
        height: 1rem;
      }

      .blog-cta {
        text-align: center;
        margin-top: 3rem;
      }

      .btn-cyan {
        background: #0ea5e9;
        color: #fff;
        font-weight: 600;
        padding: 0.875rem 2rem;
        border-radius: 0.5rem;
        font-size: 0.95rem;
        border: none;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }

      .btn-cyan:hover {
        background: #0284c7;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
      }

      .btn-cyan svg {
        width: 1rem;
        height: 1rem;
      }

      @media(max-width:968px) {
        .blog-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      @media(max-width:640px) {
        .blog-grid {
          grid-template-columns: 1fr;
        }
        .bimg {
          height: 180px;
        }
      }

      /* ── FAQ — all closed by default ── */
      .faq-sec {
        background: white;
        padding: 3rem 0;
        color: var(--text-dark);
      }

      .faq-title {
        font-size: clamp(1.7rem, 3vw, 2.25rem);
        font-weight: 800;
        text-align: center;
        margin-bottom: 3rem;
        line-height: 1.2;
        color: var(--text-dark);
        position: relative;
      }

      .faq-list {
        max-width: 900px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: .75rem;
      }

      .faq-item {
        background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        border: 1px solid var(--border-light);
        border-radius: 1rem;
        overflow: hidden;
        position: relative;
      }
      

      .faq-item.open {
        border-color: var(--gold);
      }

      .faq-q {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 1.8rem;
        background: none;
        font-size: .95rem;
        font-weight: 600;
        color: var(--text-dark);
        text-align: left;
        cursor: pointer;
        gap: 1rem;
        transition: all .3s;
        position: relative;
      }
      
      .faq-q:hover {
        color: var(--gold);
      }
      
      .faq-q svg {
        transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
        color: #555;
        flex-shrink: 0;
      }

      .faq-item.open .faq-q svg {
        transform: rotate(180deg);
        color: var(--gold);
      }

      /* height 0 = closed by default */
      .faq-a {
        max-height: 0;
        overflow: hidden;
        transition: max-height .6s cubic-bezier(0.25, 0.46, 0.45, 0.94), padding .6s ease;
        padding: 0 1.4rem;
        font-size: .875rem;
        color: #666;
        line-height: 1.8;
      }

      .faq-item.open .faq-a {
        max-height: 600px;
        padding: 0rem 1.4rem 1.25rem;
      }

      /* ── FINAL CTA ── */
      .cta-final {
        position: relative;
        padding: 8rem 0;
        text-align: center;
        overflow: hidden;
      }

      .cta-bg {
        position: absolute;
        inset: 0;
        background: url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&q=80&w=1800') center/cover no-repeat;
        filter: brightness(.2) saturate(.6);
      }

      .cta-ov {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0, 0, 0, .5), rgba(0, 0, 0, .8));
      }

      .cta-content {
        position: relative;
        z-index: 2;
      }

      .cta-title {
        font-size: clamp(2rem, 5vw, 3.5rem);
        font-weight: 800;
        margin-bottom: 1.25rem;
        line-height: 1.15;
      }

      .cta-sub {
        font-size: .975rem;
        color: #bbb;
        margin-bottom: 2.25rem;
        max-width: 520px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.75;
      }

      .cta-btns {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
      }

      .btn-outline-w {
        background: #fff;
        color: #111;
        border: 2px solid #fff;
        font-weight: 700;
        padding: .875rem 1.75rem;
        border-radius: .4rem;
        font-size: .95rem;
        transition: all .25s;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
      }

      .btn-outline-w:hover {
        background: #f0f0f0;
        border-color: #f0f0f0;
        transform: translateY(-2px);
      }

      .btn-cta-outline {
        background: transparent;
        color: #fff;
        border: 2px solid rgba(255,255,255,.7);
        font-weight: 700;
        padding: .875rem 1.75rem;
        border-radius: .4rem;
        font-size: .95rem;
        transition: all .25s;
      }

      .btn-cta-outline:hover {
        background: transparent;
        color: #fff;
        transform: translateY(-2px);
      }

      .cta-trust {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        font-size: .78rem;
        color: #aaa;
        flex-wrap: wrap;
        margin-bottom: 1.75rem;
      }

      .cta-trust span {
        position: relative;
        padding-left: 1.2rem;
      }

      .cta-trust span::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: var(--gold);
        font-weight: 600;
      }

      .cta-contacts {
        display: flex;
        gap: 2rem;
        justify-content: center;
        font-size: .85rem;
        color: #bbb;
        flex-wrap: wrap;
      }

      .cta-contacts a {
        color: #bbb;
        display: flex;
        align-items: center;
        gap: .4rem;
        transition: color .2s;
      }

      .cta-contacts a:hover {
        color: #fff;
      }

      /* ── FOOTER ── */
      .footer {
        background: #070707;
        padding: 4rem 0 0;
        border-top: 1px solid var(--border);
      }

      .footer-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 0.8fr;
        gap: 2.5rem;
        padding-bottom: 3.5rem;
      }

      .footer-desc {
        color: #888;
        font-size: .85rem;
        line-height: 1.75;
        margin: 1rem 0 1.1rem;
      }

      .footer-note {
        font-size: .78rem;
        color: #C9A227;
        margin-bottom: 1rem;
      }

      .footer-social {
        display: flex;
        gap: .65rem;
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
        transition: all .2s;
      }

      .footer-social a:hover {
        color: var(--cyan);
        border-color: var(--cyan);
      }

      .fcol h4 {
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: var(--cyan);
        margin-bottom: 1.25rem;
      }

      .fcol ul {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: .55rem;
      }

      .fcol ul li a {
        font-size: .85rem;
        color: #888;
        transition: color .2s;
      }

      .fcol ul li a:hover {
        color: #fff;
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
        gap: .5rem;
      }

      .fbl {
        display: flex;
        gap: 1.4rem;
      }

      .fbl a {
        color: #888;
        font-size: .78rem;
        transition: color .2s;
      }

      .fbl a:hover {
        color: #fff;
      }

      @media(max-width:1200px) {
        .footer-grid {
          grid-template-columns: 1fr 1fr 1fr;
        }
      }

      @media(max-width:968px) {
        .footer-grid {
          grid-template-columns: 1fr 1fr;
        }
      }

      @media(max-width:480px) {
        .footer-grid {
          grid-template-columns: 1fr;
        }

        .footer-bottom {
          flex-direction: column;
          text-align: center;
          gap: 1rem;
        }

        .fbl {
          justify-content: center;
          flex-wrap: wrap;
        }
      }

      /* ── BACK TO TOP ── */
      .back-to-top {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 48px;
        height: 48px;
        background: #C9A227;
        color: #000;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s, transform .3s, visibility .3s;
        z-index: 999;
        box-shadow: 0 4px 16px rgba(201,162,39,.45);
      }
      .back-to-top.visible {
        opacity: 1;
        visibility: visible;
      }
      .back-to-top:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(201,162,39,.55);
      }
      .back-to-top svg {
        width: 20px;
        height: 20px;
      }

      /* ── MODAL STYLES ── */
      
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
      }

      .modal-overlay.show {
        opacity: 1;
        visibility: visible;
      }

      .modal-content {
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 1.25rem;
        max-width: 820px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        transform: scale(0.9) translateY(20px);
        transition: transform 0.3s ease;
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6);
      }

      .modal-overlay.show .modal-content {
        transform: scale(1) translateY(0);
      }

      .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.75rem 2rem 1.25rem;
        border-bottom: 1px solid #333;
      }

      .modal-header h3 {
        font-size: 1.3rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
      }

      .modal-close {
        background: none;
        border: none;
        color: #888;
        font-size: 2rem;
        font-weight: 300;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
        line-height: 1;
      }

      .modal-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
      }

      .modal-body {
        padding: 2rem;
      }

      .modal-intro {
        color: #ccc;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1.5rem;
      }

      .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .form-group {
        margin-bottom: 1rem;
      }

      .form-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
        margin-bottom: 0.5rem;
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        width: 100%;
        padding: 1rem 1.25rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        color: #fff;
        font-size: 0.95rem;
        font-family: inherit;
        transition: all 0.3s ease;
      }

      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: var(--gold);
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.1);
      }

      .form-group textarea {
        resize: vertical;
        min-height: 80px;
      }

      .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
      }

      .input-wrapper.service-dropdown::after {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1.25rem;
        height: 1.25rem;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23ffffff' stroke-width='1.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5' /%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        pointer-events: none;
        z-index: 2;
      }

      .input-icon {
        position: absolute;
        left: 1rem;
        width: 1.25rem;
        height: 1.25rem;
        color: rgba(255, 255, 255, 0.4);
        z-index: 1;
        pointer-events: none;
      }

      .input-wrapper input,
      .input-wrapper select,
      .input-wrapper textarea {
        width: 100%;
        padding: 1rem 1.25rem 1rem 3rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        color: #fff;
        font-size: 0.95rem;
        font-family: inherit;
        transition: all 0.3s ease;
      }

      .input-wrapper select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 2.5rem;
        color: #fff;
      }

      .input-wrapper select option {
        background: #1a1a1a;
        color: #fff;
      }

      .input-wrapper input:focus,
      .input-wrapper select:focus,
      .input-wrapper textarea:focus {
        outline: none;
        border-color: var(--gold);
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.1);
      }

      .input-wrapper input:focus ~ .input-icon,
      .input-wrapper select:focus ~ .input-icon,
      .input-wrapper textarea:focus ~ .input-icon {
        color: var(--gold);
      }

      .form-checkbox {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
      }

      .form-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-top: 2px;
        flex-shrink: 0;
        accent-color: var(--gold);
      }

      .form-checkbox label {
        font-size: 0.875rem;
        color: #ccc;
        line-height: 1.5;
        cursor: pointer;
      }

      .form-checkbox a {
        color: var(--gold);
        text-decoration: none;
        font-weight: 600;
      }

      .form-checkbox a:hover {
        text-decoration: underline;
      }

      .modal-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.25rem;
      }

      .btn-secondary {
        background: transparent;
        color: #ccc;
        border: 2px solid #444;
        padding: 0.75rem 1.75rem;
        border-radius: 0.75rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
      }

      .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: #666;
        color: #fff;
      }

      .btn-primary {
        background: var(--gold);
        color: #000;
        border: none;
        padding: 0.75rem 1.75rem;
        border-radius: 0.75rem;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
      }

      .btn-primary:hover {
        background: var(--gold-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(201, 162, 39, 0.35);
      }

      /* Success message styles */
      .success-message {
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
        border: 1px solid var(--gold);
        border-radius: 0.75rem;
        padding: 1rem;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 8px 24px rgba(201, 162, 39, 0.25);
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        transform: translateX(100%);
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .success-message.show {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
      }

      .toast-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
      }

      .toast-icon {
        width: 2rem;
        height: 2rem;
        background: linear-gradient(135deg, var(--gold) 0%, var(--gold-hover) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .toast-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--gold);
        margin: 0;
      }

      .toast-message {
        color: #ccc;
        font-size: 0.85rem;
        line-height: 1.4;
        margin-bottom: 0.75rem;
      }

      .toast-close {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        background: none;
        border: none;
        color: #888;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 0.25rem;
        transition: all 0.2s ease;
      }

      .toast-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
      }

      @media (max-width: 768px) {
        .modal-overlay {
          padding: 1rem;
        }

        .modal-content {
          max-height: 95vh;
        }

        .modal-header,
        .modal-body {
          padding: 1.5rem;
        }

        .form-row {
          grid-template-columns: 1fr;
          gap: 0;
        }

        .modal-actions {
          flex-direction: column;
        }

        .modal-actions button {
          width: 100%;
        }
      }

      /* Toast notification styles */
      .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        min-width: 300px;
        max-width: 500px;
        padding: 16px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        transform: translateX(400px);
        transition: transform 0.3s ease;
      }

      .toast.show {
        transform: translateX(0);
      }

      .toast.success {
        background: linear-gradient(135deg, #28a745, #20c997);
      }

      .toast.error {
        background: #141414;
      }

      .toast.error .toast-icon svg {
        fill: #dc3545;
      }

      .toast-icon {
        width: 24px;
        height: 24px;
        flex-shrink: 0;
        background: none;
      }

      .toast-close {
        margin-left: auto;
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
      }

      .toast-close:hover {
        background: rgba(255, 255, 255, 0.2);
      }
    </style>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "ENTRIKS Talent Hub",
      "description": "ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo durch Nearshoring und Active Sourcing.",
      "url": "https://talent.entriks.com",
      "telephone": "+383-43-889-344",
      "email": "info@entriks-talenthub.com",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Prishtina",
        "addressCountry": "XK"
      },
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": "42.6629",
        "longitude": "21.1655"
      },
      "openingHoursSpecification": {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
        "opens": "09:00",
        "closes": "18:00"
      },
      "priceRange": "$$",
      "areaServed": ["DE", "AT", "CH"],
      "serviceType": ["Nearshoring", "Active Sourcing", "Recruiting"],
      "knowsAbout": ["IT Recruitment", "Finance Recruitment", "Customer Service", "HR Services", "Remote Teams"]
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Für welche Positionen eignet sich Nearshoring aus dem Kosovo?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Nearshoring aus dem Kosovo ist besonders geeignet für: IT & Software Development, Customer Service, Finance & Controlling, Marketing & Content Creation, HR-Administration, Back-Office-Tätigkeiten, Data Entry & Analysis und viele weitere Bereiche."
          }
        },
        {
          "@type": "Question",
          "name": "Wie viel kann ich durch Nearshoring wirklich sparen?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Unsere Kunden berichten typischerweise von 40–60 % Kostenersparnis beim Bruttogehalt im Vergleich zu DACH-Fachkräften – bei vergleichbarer Qualifikation."
          }
        },
        {
          "@type": "Question",
          "name": "Wie lange dauert es, bis ich meinen ersten Kandidaten sehe?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Bei Active Sourcing und Nearshoring-Positionen sehen Sie erste qualifizierte Kandidatenprofile in der Regel innerhalb von 7–10 Werktagen nach Kick-off."
          }
        }
      ]
    }
    </script>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFaviconUrl) ?>">
  </head>
  <body>
    <!-- ══ NAVBAR ══ -->
    <nav class="navbar" id="navbar">
      <div class="container nav-inner">
        <div class="logo-wrap">
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?> Logo" class="logo-img">
          <div class="logo-sub">TALENT HUB</div>
        </div>
        <div class="nav-links">
          <a href="#nearshoring"><?php echo $lang === 'de' ? 'Nearshoring' : 'Nearshoring'; ?></a>
          <a href="#active-sourcing"><?php echo $lang === 'de' ? 'Active Sourcing' : 'Active Sourcing'; ?></a>
          <a href="#blog">Blog</a>
          <a href="#about"><?php echo $lang === 'de' ? 'Über uns' : 'About Us'; ?></a>
          <a href="#kontakt"><?php echo $lang === 'de' ? 'Kontakt' : 'Contact'; ?></a>
        </div>
        <div class="nav-cta">
          <div class="lang-dropdown-wrap" id="langDropdownWrap">
            <button class="lang-globe-btn" id="langGlobeBtn" aria-haspopup="true" aria-expanded="false">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
              </svg>
              <span id="langCurrentLabel"><?php echo strtoupper($lang); ?></span>
              <svg class="lang-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
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
                <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
              </svg>
              <span id="langCurrentLabelMobile"><?php echo strtoupper($lang); ?></span>
              <svg class="lang-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
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
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
            style="width:24px;height:24px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
      </div>
      <div class="mob-menu" id="mobMenu">
        <a href="#nearshoring"><?php echo $lang === 'de' ? 'Nearshoring' : 'Nearshoring'; ?></a>
        <a href="#active-sourcing"><?php echo $lang === 'de' ? 'Active Sourcing' : 'Active Sourcing'; ?></a>
        <a href="#blog">Blog</a>
        <a href="#about"><?php echo $lang === 'de' ? 'Über uns' : 'About Us'; ?></a>
        <a href="#kontakt"><?php echo $lang === 'de' ? 'Kontakt' : 'Contact'; ?></a>
      </div>
    </nav>
    
    <!-- ══ HERO ══ -->
    <section class="hero">
      <div class="hero-bg"></div>
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <div class="hero-eyebrow" id="hero-eyebrow"><?php echo getCmsContent('hero_eyebrow', $c['hero']['eyebrow']); ?></div>
        <h1 class="hero-title"><?php echo getCmsContent('hero_title', $c['hero']['title']); ?></h1>
        <p class="hero-sub"><?php echo getCmsContent('hero_subtitle', $c['hero']['subtitle']); ?></p>
        <div class="hero-btns">
          <button class="btn-gold"><?php echo getCmsContent('hero_btn1', $c['hero']['btn1']); ?> <svg xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24" fill="currentColor" class="h-arrow" style="color:#000;">
              <path fill-rule="evenodd"
                d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z"
                clip-rule="evenodd" />
            </svg></button>
          <button class="btn-outline-white"><?php echo getCmsContent('hero_btn2', $c['hero']['btn2']); ?></button>
        </div>
        <div class="hero-trust">
          <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
              <path fill-rule="evenodd"
                d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                clip-rule="evenodd" />
            </svg> <?php echo getCmsContent('hero_trust1', $c['hero']['trust1']); ?></span>
          <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
              <path fill-rule="evenodd"
                d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                clip-rule="evenodd" />
            </svg> <?php echo getCmsContent('hero_trust2', $c['hero']['trust2']); ?></span>
          <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
              <path fill-rule="evenodd"
                d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                clip-rule="evenodd" />
            </svg> <?php echo getCmsContent('hero_trust3', $c['hero']['trust3']); ?></span>
        </div>
      </div>
    </section>

    <!-- ══ STATS ══ -->
    <section class="stats-section page-section" id="stats-section">
      <div class="container">
        <div class="stats-grid">
          <div class="stat-item">
            <div class="stat-val" data-target="<?php echo getCmsContent('stat1_value', $c['stats']['stat1']['value']); ?>" data-suffix="<?php echo getCmsContent('stat1_suffix', $c['stats']['stat1']['suffix']); ?>">0<?php echo getCmsContent('stat1_suffix', $c['stats']['stat1']['suffix']); ?></div>
            <div class="stat-lbl"><?php echo getCmsContent('stat1_label', $c['stats']['stat1']['label']); ?></div>
          </div>
          <div class="stat-item">
            <div class="stat-val" data-target="<?php echo getCmsContent('stat2_value', $c['stats']['stat2']['value']); ?>" data-suffix="<?php echo getCmsContent('stat2_suffix', $c['stats']['stat2']['suffix']); ?>">0<?php echo getCmsContent('stat2_suffix', $c['stats']['stat2']['suffix']); ?></div>
            <div class="stat-lbl"><?php echo getCmsContent('stat2_label', $c['stats']['stat2']['label']); ?></div>
          </div>
          <div class="stat-item">
            <div class="stat-val" data-target="<?php echo getCmsContent('stat3_value', $c['stats']['stat3']['value']); ?>" data-suffix="<?php echo $lang === 'de' ? ' Tage' : ' days'; ?>">0<?php echo $lang === 'de' ? ' Tage' : ' days'; ?></div>
            <div class="stat-lbl"><?php echo getCmsContent('stat3_label', $c['stats']['stat3']['label']); ?></div>
          </div>
          <div class="stat-item">
            <div class="stat-val" data-target="<?php echo getCmsContent('stat4_value', $c['stats']['stat4']['value']); ?>" data-suffix="<?php echo getCmsContent('stat4_suffix', $c['stats']['stat4']['suffix']); ?>">0<?php echo getCmsContent('stat4_suffix', $c['stats']['stat4']['suffix']); ?></div>
            <div class="stat-lbl"><?php echo getCmsContent('stat4_label', $c['stats']['stat4']['label']); ?></div>
          </div>
          <div class="stat-item">
            <div class="stat-val" data-target="<?php echo getCmsContent('stat5_value', $c['stats']['stat5']['value']); ?>" data-suffix="<?php echo getCmsContent('stat5_suffix', $c['stats']['stat5']['suffix']); ?>">0<?php echo getCmsContent('stat5_suffix', $c['stats']['stat5']['suffix']); ?></div>
            <div class="stat-lbl"><?php echo getCmsContent('stat5_label', $c['stats']['stat5']['label']); ?></div>
          </div>
        </div>
        <div class="brand-strip">
          <p><?php echo getCmsContent('brand_strip', $c['stats']['brand_strip']); ?></p>
        </div>
      </div>
    </section>

    <!-- ══ WHY ══ -->
    <section class="why-section sr page-section" id="why-section">
      <div class="container">
        <div class="why-inner">
          <div class="why-left">
            <div class="eyebrow" id="why-eyebrow"><?php echo getCmsContent('why_eyebrow', $c['why']['eyebrow']); ?></div>
            <h2 class="why-title" id="why-title"><?php echo getCmsContent('why_title', $c['why']['title']); ?></h2>
            <p class="why-text" id="why-text-1"><?php echo getCmsContent('why_text1', $c['why']['text1']); ?></p>
            <div class="divider"></div>
            <p class="why-text" id="why-text-2"><?php echo getCmsContent('why_text2', $c['why']['text2']); ?></p>
          </div>
          <div class="why-cards">
            <div class="why-card">
              <div class="why-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.5 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5h-.75V3.75a.75.75 0 0 0 0-1.5h-15ZM9 6a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm-.75 3.75A.75.75 0 0 1 9 9h1.5a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM9 12a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm3.75-5.25A.75.75 0 0 1 13.5 6H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM13.5 9a.75.75 0 0 0 0 1.5H15A.75.75 0 0 0 15 9h-1.5Zm-.75 3.75a.75.75 0 0 1 .75-.75H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM9 19.5v-2.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-.75.75h-4.5A.75.75 0 0 1 9 19.5Z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="why-card-body">
                <h3 id="why-card1-title"><?php echo $c['why']['card1']['title']; ?></h3>
                <p id="why-card1-text"><?php echo $c['why']['card1']['text']; ?></p>
                <a href="#nearshoring">
                  <span id="why-card1-link"><?php echo $c['why']['card1']['link']; ?></span>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-arrow">
                    <path fill-rule="evenodd" d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                  </svg>
                </a>
              </div>
            </div>
            <div class="why-card">
              <div class="why-card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M8.25 10.875a2.625 2.625 0 1 1 5.25 0 2.625 2.625 0 0 1-5.25 0Z"/>
                  <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.125 4.5a4.125 4.125 0 1 0 2.338 7.524l2.007 2.006a.75.75 0 1 0 1.06-1.06l-2.006-2.007a4.125 4.125 0 0 0-3.399-6.463Z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="why-card-body">
                <h3 id="why-card2-title"><?php echo $c['why']['card2']['title']; ?></h3>
                <p id="why-card2-text"><?php echo $c['why']['card2']['text']; ?></p>
                <a href="#active-sourcing">
                  <span id="why-card2-link"><?php echo $c['why']['card2']['link']; ?></span>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-arrow">
                    <path fill-rule="evenodd" d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- ══ LEISTUNG 01 ══ -->
    <section id="nearshoring" class="dark-sec">
      <div class="container">
        <div class="leistung-grid sr">
          <div>
            <div class="leistung-label"><?php echo $c['nearshoring']['label']; ?></div>
            <h2 class="leistung-title"><?php echo $c['nearshoring']['title']; ?></h2>
            <p class="leistung-text"><?php echo $c['nearshoring']['text1']; ?></p>
            <p class="leistung-text"><?php echo $c['nearshoring']['text2']; ?></p>
            <ul class="check-list" id="cl1">
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][0]; ?></li>
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][1]; ?></li>
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][2]; ?></li>
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][3]; ?></li>
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][4]; ?></li>
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][5]; ?></li>
              <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['nearshoring']['features'][6]; ?></li>
            </ul>
            <button class="btn-gold"><?php echo $c['nearshoring']['btn']; ?> <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-arrow" style="color:#000;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
              </svg></button>
          </div>
          <div class="leistung-img"><img
              src="assets/img/img-1.png"
              alt="Team" /></div>
        </div>
        <div class="proc4 sr">
          <div>
            <div class="step-num"><?php echo $c['nearshoring']['process'][0]['num']; ?></div>
            <div class="step-line"></div>
            <div class="step-title"><?php echo $c['nearshoring']['process'][0]['title']; ?></div>
            <p class="step-desc"><?php echo $c['nearshoring']['process'][0]['desc']; ?></p>
          </div>
          <div>
            <div class="step-num"><?php echo $c['nearshoring']['process'][1]['num']; ?></div>
            <div class="step-line"></div>
            <div class="step-title"><?php echo $c['nearshoring']['process'][1]['title']; ?></div>
            <p class="step-desc"><?php echo $c['nearshoring']['process'][1]['desc']; ?></p>
          </div>
          <div>
            <div class="step-num"><?php echo $c['nearshoring']['process'][2]['num']; ?></div>
            <div class="step-line"></div>
            <div class="step-title"><?php echo $c['nearshoring']['process'][2]['title']; ?></div>
            <p class="step-desc"><?php echo $c['nearshoring']['process'][2]['desc']; ?></p>
          </div>
          <div>
            <div class="step-num"><?php echo $c['nearshoring']['process'][3]['num']; ?></div>
            <div class="step-line"></div>
            <div class="step-title"><?php echo $c['nearshoring']['process'][3]['title']; ?></div>
            <p class="step-desc"><?php echo $c['nearshoring']['process'][3]['desc']; ?></p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ LEISTUNG 02 ══ -->
    <section id="active-sourcing" class="light-sec page-section">
      <div class="container">
        <div class="leistung-grid sr">
          <div class="leistung-img"><img
              src="assets/img/img-2.png"
              alt="Sourcing" /></div>
          <div>
            <div class="leistung-label"><?php echo $c['active_sourcing']['label']; ?></div>
            <h2 class="leistung-title" style="color:var(--text-dark)"><?php echo $c['active_sourcing']['title']; ?></h2>
            <p class="leistung-text dk"><?php echo $c['active_sourcing']['text1']; ?></p>
            <p class="leistung-text dk"><?php echo $c['active_sourcing']['text2']; ?></p>
            <ul class="check-list" id="cl2">
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][0]; ?></li>
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][1]; ?></li>
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][2]; ?></li>
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][3]; ?></li>
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][4]; ?></li>
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][5]; ?></li>
              <li class="dk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                  class="h-icon gold">
                  <path fill-rule="evenodd"
                    d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
                </svg> <?php echo $c['active_sourcing']['features'][6]; ?></li>
            </ul>
            <button class="btn-gold"><?php echo $c['active_sourcing']['btn']; ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                fill="currentColor" class="h-arrow" style="color:#000;">
                <path fill-rule="evenodd"
                  d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z"
                  clip-rule="evenodd" />
              </svg></button>
          </div>
        </div>
        <div class="feat3 sr">
          <div class="feat-card">
            <div class="feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                style="width:1.75rem;height:1.75rem;color:var(--gold);">
                <path fill-rule="evenodd"
                  d="M12 1.5a.75.75 0 0 1 .75.75V4.5a.75.75 0 0 1-1.5 0V2.25A.75.75 0 0 1 12 1.5ZM5.636 4.136a.75.75 0 0 1 1.06 0l1.592 1.591a.75.75 0 0 1-1.061 1.06l-1.591-1.59a.75.75 0 0 1 0-1.061Zm12.728 0a.75.75 0 0 1 0 1.06l-1.591 1.592a.75.75 0 0 1-1.06-1.061l1.59-1.591a.75.75 0 0 1 1.061 0Zm-6.816 4.496a.75.75 0 0 1 .82.311l5.228 7.917a.75.75 0 0 1-.777 1.148l-2.097-.43 1.045 3.9a.75.75 0 0 1-1.45.388l-1.044-3.899-1.601 1.42a.75.75 0 0 1-1.247-.606l.569-9.47a.75.75 0 0 1 .554-.68ZM3 10.5a.75.75 0 0 1 .75-.75H6a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 10.5Zm14.25 0a.75.75 0 0 1 .75-.75h2.25a.75.75 0 0 1 0 1.5H18a.75.75 0 0 1-.75-.75Zm-8.962 3.712a.75.75 0 0 1 0 1.061l-1.591 1.591a.75.75 0 1 1-1.061-1.06l1.591-1.592a.75.75 0 0 1 1.06 0Z"
                  clip-rule="evenodd" />
              </svg></div>
            <h4><?php echo $c['active_sourcing']['feat_cards'][0]['title']; ?></h4>
            <p><?php echo $c['active_sourcing']['feat_cards'][0]['text']; ?></p>
          </div>
          <div class="feat-card">
            <div class="feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                style="width:1.75rem;height:1.75rem;color:var(--gold);">
                <path fill-rule="evenodd"
                  d="M14.615 1.595a.75.75 0 0 1 .359.852L12.982 9.75h7.268a.75.75 0 0 1 .548 1.262l-10.5 11.25a.75.75 0 0 1-1.272-.71l1.992-7.302H3.75a.75.75 0 0 1-.548-1.262l10.5-11.25a.75.75 0 0 1 .913-.143Z"
                  clip-rule="evenodd" />
              </svg></div>
            <h4><?php echo $c['active_sourcing']['feat_cards'][1]['title']; ?></h4>
            <p><?php echo $c['active_sourcing']['feat_cards'][1]['text']; ?></p>
          </div>
          <div class="feat-card">
            <div class="feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                style="width:1.75rem;height:1.75rem;color:var(--gold);">
                <path fill-rule="evenodd"
                  d="M12.516 2.17a.75.75 0 0 0-1.032 0 11.209 11.209 0 0 1-7.877 3.08.75.75 0 0 0-.722.515A12.74 12.74 0 0 0 2.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.749.749 0 0 0 .374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 0 0-.722-.516l-.143.001c-2.996 0-5.717-1.17-7.734-3.08Zm3.094 8.016a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                  clip-rule="evenodd" />
              </svg></div>
            <h4><?php echo $c['active_sourcing']['feat_cards'][2]['title']; ?></h4>
            <p><?php echo $c['active_sourcing']['feat_cards'][2]['text']; ?></p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ KOSOVO ══ -->
    <section id="kosovo" class="kosovo-sec sr page-section">
      <div class="container">
        <div class="eyebrow sec-ey"><?php echo getCmsContent('kosovo_eyebrow', $c['kosovo']['eyebrow']); ?></div>
        <h2 class="sec-title"><?php echo getCmsContent('kosovo_title', $c['kosovo']['title']); ?></h2>
        <p class="sec-sub" style="max-width:580px;margin-left:auto;margin-right:auto"><?php echo getCmsContent('kosovo_subtitle', $c['kosovo']['subtitle']); ?></p>
        <div class="kos-cards">
          <div class="kos-card">
            <div class="kos-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path fill-rule="evenodd" d="M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15.75 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM2.25 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6.31 15.117A6.745 6.745 0 0 1 12 12a6.745 6.745 0 0 1 6.709 7.498.75.75 0 0 1-.372.568A12.696 12.696 0 0 1 12 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 0 1-.372-.568 6.787 6.787 0 0 1 1.019-4.38Z" clip-rule="evenodd" />
                <path d="M5.082 14.254a8.287 8.287 0 0 0-1.308 5.135 9.687 9.687 0 0 1-1.764-.44l-.115-.04a.563.563 0 0 1-.373-.487l-.01-.121a3.75 3.75 0 0 1 3.57-4.047ZM20.226 19.389a8.287 8.287 0 0 0-1.308-5.135 3.75 3.75 0 0 1 3.57 4.047l-.01.121a.563.563 0 0 1-.373.486l-.115.04c-.567.2-1.156.349-1.764.441Z" />
              </svg>
            </div>
            <div class="kos-label"><?php echo getCmsContent('kosovo_card_0_label', $c['kosovo']['cards'][0]['label']); ?></div>
            <div class="kos-title"><?php echo getCmsContent('kosovo_card_0_title', $c['kosovo']['cards'][0]['title']); ?></div>
            <div class="kos-desc"><?php echo getCmsContent('kosovo_card_0_desc', $c['kosovo']['cards'][0]['desc']); ?></div>
          </div>
          <div class="kos-card">
            <div class="kos-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 7.5a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z" />
                <path fill-rule="evenodd" d="M1.5 4.875C1.5 3.839 2.34 3 3.375 3h17.25c1.035 0 1.875.84 1.875 1.875v9.75c0 1.036-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 0 1 1.5 14.625v-9.75ZM8.25 9.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM18.75 9a.75.75 0 0 0-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75V9.75a.75.75 0 0 0-.75-.75h-.008ZM4.5 9.75A.75.75 0 0 1 5.25 9h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75V9.75Z" clip-rule="evenodd" />
                <path d="M2.25 18a.75.75 0 0 0 0 1.5c5.4 0 10.63.722 15.6 2.075 1.19.324 2.4-.558 2.4-1.82V18.75a.75.75 0 0 0-.75-.75H2.25Z" />
              </svg>
            </div>
            <div class="kos-label"><?php echo getCmsContent('kosovo_card_1_label', $c['kosovo']['cards'][1]['label']); ?></div>
            <div class="kos-title"><?php echo getCmsContent('kosovo_card_1_title', $c['kosovo']['cards'][1]['title']); ?></div>
            <div class="kos-desc"><?php echo getCmsContent('kosovo_card_1_desc', $c['kosovo']['cards'][1]['desc']); ?></div>
          </div>
          <div class="kos-card">
            <div class="kos-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z" />
              </svg>
            </div>
            <div class="kos-label"><?php echo getCmsContent('kosovo_card_2_label', $c['kosovo']['cards'][2]['label']); ?></div>
            <div class="kos-title"><?php echo getCmsContent('kosovo_card_2_title', $c['kosovo']['cards'][2]['title']); ?></div>
            <div class="kos-desc"><?php echo getCmsContent('kosovo_card_2_desc', $c['kosovo']['cards'][2]['desc']); ?></div>
          </div>
        </div>
        <div class="ceo-q">
          <div class="q-mark">"</div>
          <p class="q-text"><?php echo getCmsContent('kosovo_quote_text', $c['kosovo']['quote']['text']); ?></p>
          <div class="q-author">
            <div class="q-avatar">R</div>
            <div>
              <div class="q-name"><?php echo getCmsContent('kosovo_quote_author', $c['kosovo']['quote']['author']); ?></div>
              <div class="q-role"><?php echo getCmsContent('kosovo_quote_role', $c['kosovo']['quote']['role']); ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ ANGEBOT ══ -->
    <section id="angebot" class="angebot-sec sr page-section">
      <div class="container">
        <div class="eyebrow sec-ey"><?php echo getCmsContent('angebot_eyebrow', $c['angebot']['eyebrow']); ?></div>
        <h2 class="angebot-title"><?php echo getCmsContent('angebot_title', $c['angebot']['title']); ?></h2>
        <p class="angebot-sub"><?php echo getCmsContent('angebot_subtitle', $c['angebot']['subtitle']); ?></p>
        <div class="angebot-wrap"><div class="angebot-grid">
          <div class="angebot-card">
            <div class="angebot-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" fill="currentColor"
                style="width:1.75rem;height:1.75rem;color:var(--gold);">
                <path fill-rule="evenodd"
                  d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"
                  clip-rule="evenodd" />
              </svg>
            </div>
            <h3><?php echo getCmsContent('angebot_card_0_title', $c['angebot']['cards'][0]['title']); ?></h3>
            <p><?php echo getCmsContent('angebot_card_0_text', $c['angebot']['cards'][0]['text']); ?></p>
            <div class="angebot-tags"><span class="angebot-tag"><?php echo getCmsContent('angebot_card_0_tag_0', $c['angebot']['cards'][0]['tags'][0]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_0_tag_1', $c['angebot']['cards'][0]['tags'][1]); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                  fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_0_tag_2', $c['angebot']['cards'][0]['tags'][2]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_0_tag_3', $c['angebot']['cards'][0]['tags'][3]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span></div>
            <a href="#" class="angebot-link"><?php echo getCmsContent('angebot_card_0_link', $c['angebot']['cards'][0]['link']); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                fill="currentColor" class="h-arrow">
                <path fill-rule="evenodd"
                  d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z"
                  clip-rule="evenodd" />
              </svg></a>
          </div>
          <div class="angebot-card popular">
            <div class="angebot-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" fill="currentColor"
                style="width:1.75rem;height:1.75rem;color:var(--gold);">
                <path fill-rule="evenodd"
                  d="M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15.75 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM2.25 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6.31 15.117A6.745 6.745 0 0 1 12 12a6.745 6.745 0 0 1 6.709 7.498.75.75 0 0 1-.372.568A12.696 12.696 0 0 1 12 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 0 1-.372-.568 6.787 6.787 0 0 1 1.019-4.38Z"
                  clip-rule="evenodd" />
                <path
                  d="M5.082 14.254a8.287 8.287 0 0 0-1.308 5.135 9.687 9.687 0 0 1-1.764-.44l-.115-.04a.563.563 0 0 1-.373-.487l-.01-.121a3.75 3.75 0 0 1 3.57-4.047ZM20.226 19.389a8.287 8.287 0 0 0-1.308-5.135 3.75 3.75 0 0 1 3.57 4.047l-.01.121a.563.563 0 0 1-.373.486l-.115.04c-.567.2-1.156.349-1.764.441Z" />
              </svg>
            </div>
            <h3><?php echo getCmsContent('angebot_card_1_title', $c['angebot']['cards'][1]['title']); ?></h3>
            <p><?php echo getCmsContent('angebot_card_1_text', $c['angebot']['cards'][1]['text']); ?></p>
            <div class="angebot-tags"><span class="angebot-tag"><?php echo getCmsContent('angebot_card_1_tag_0', $c['angebot']['cards'][1]['tags'][0]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_1_tag_1', $c['angebot']['cards'][1]['tags'][1]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_1_tag_2', $c['angebot']['cards'][1]['tags'][2]); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                  fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span></div>
            <a href="#" class="angebot-link"><?php echo getCmsContent('angebot_card_1_link', $c['angebot']['cards'][1]['link']); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                fill="currentColor" class="h-arrow">
                <path fill-rule="evenodd"
                  d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z"
                  clip-rule="evenodd" />
              </svg></a>
          </div>
          <div class="angebot-card">
            <div class="angebot-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" fill="currentColor"
                style="width:1.75rem;height:1.75rem;color:var(--gold);">
                <path fill-rule="evenodd"
                  d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z"
                  clip-rule="evenodd" />
              </svg>

            </div>
            <h3><?php echo getCmsContent('angebot_card_2_title', $c['angebot']['cards'][2]['title']); ?></h3>
            <p><?php echo getCmsContent('angebot_card_2_text', $c['angebot']['cards'][2]['text']); ?></p>
            <div class="angebot-tags"><span class="angebot-tag"><?php echo getCmsContent('angebot_card_2_tag_0', $c['angebot']['cards'][2]['tags'][0]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_2_tag_1', $c['angebot']['cards'][2]['tags'][1]); ?> <svg xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24" fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span><span class="angebot-tag"><?php echo getCmsContent('angebot_card_2_tag_2', $c['angebot']['cards'][2]['tags'][2]); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                  fill="currentColor" class="h-check-small">
                  <path fill-rule="evenodd"
                    d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z"
                    clip-rule="evenodd" />
                </svg></span></div>
            <a href="#" class="angebot-link"><?php echo getCmsContent('angebot_card_2_link', $c['angebot']['cards'][2]['link']); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                fill="currentColor" class="h-arrow">
                <path fill-rule="evenodd"
                  d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z"
                  clip-rule="evenodd" />
              </svg></a>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ EXPERTISE ══ -->
    <section id="expertise" class="expert-sec page-section">
      <div class="container">
        <div class="expert-box sr">
          <div class="expert-ey"><?php echo getCmsContent('expertise_eyebrow', $c['expertise']['eyebrow']); ?></div>
          <h2 class="expert-title"><?php echo getCmsContent('expertise_title', $c['expertise']['title']); ?></h2>
          <p class="expert-sub"><?php echo getCmsContent('expertise_subtitle', $c['expertise']['subtitle']); ?></p>
          <div class="expert-grid">
            <!-- NO .selected class — all start neutral, hover turns cyan -->
            <?php foreach ($c['expertise']['tags'] as $i => $tag): ?>
            <div class="exp-tag"><?php echo getCmsContent('expertise_tag_' . $i, $tag); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ PROCESS ══ -->
    <section id="process" class="process-sec page-section">
      <div class="container">
        <div class="eyebrow sec-ey" id="proc-eyebrow"><?php echo getCmsContent('process_eyebrow', $c['process']['eyebrow']); ?></div>
        <h2 class="sec-title" id="proc-title"><?php echo getCmsContent('process_title', $c['process']['title']); ?></h2>
        <p class="sec-sub"><?php echo getCmsContent('process_subtitle', $c['process']['subtitle']); ?></p>
        <div class="proc5">
          <?php foreach ($c['process']['steps'] as $i => $step): ?>
          <div class="proc-card">
            <div class="proc-num"><?php echo getCmsContent('process_step_' . $i . '_num', $step['num']); ?></div>
            <div class="proc-title" id="proc-<?php echo $i + 1; ?>-title"><?php echo getCmsContent('process_step_' . $i . '_title', $step['title']); ?></div>
            <p class="proc-desc"><?php echo getCmsContent('process_step_' . $i . '_desc', $step['desc']); ?></p>
            <span class="proc-day" id="proc-<?php echo $i + 1; ?>-day"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-18 0h18" /></svg> <span><?php echo getCmsContent('process_step_' . $i . '_day', $step['day']); ?></span></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    
    <!-- ══ SUCCESS ══ -->
    <section id="success" class="success-sec sr page-section">
      <div class="container">
        <div class="eyebrow sec-ey"><?php echo getCmsContent('success_eyebrow', $c['success']['eyebrow']); ?></div>
        <h2 class="sec-title" style="color:var(--text-dark);text-align:center;margin-bottom:0.5rem;"><?php echo getCmsContent('success_title', $c['success']['title']); ?></h2>
        <p class="sec-sub dk" style="text-align:center;margin-bottom:3rem;"><?php echo getCmsContent('success_subtitle', $c['success']['subtitle']); ?></p>
        <div class="success-grid">
          <?php foreach ($c['success']['stories'] as $i => $story): ?>
          <div class="success-card">
            <div class="simg">
              <?php if ($story['icon'] === 'code-bracket-square'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                  <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm14.25 6a.75.75 0 0 1-.22.53l-2.25 2.25a.75.75 0 1 1-1.06-1.06L15.44 12l-1.72-1.72a.75.75 0 1 1 1.06-1.06l2.25 2.25c.141.14.22.331.22.53Zm-10.28-.53a.75.75 0 0 0 0 1.06l2.25 2.25a.75.75 0 1 0 1.06-1.06L8.56 12l1.72-1.72a.75.75 0 0 0-1.06-1.06l-2.25 2.25Z" clip-rule="evenodd"/>
                  <path d="M12.5 7.5a.75.75 0 0 0-1.5 0v9a.75.75 0 0 0 1.5 0v-9Z"/>
                </svg>
              <?php elseif ($story['icon'] === 'users'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.442 6.884 6.884 0 0 1 1.75 3.59Z"/>
                </svg>
              <?php endif; ?>
            </div>
            <div class="sbody">
              <h3><?php echo getCmsContent('success_story_' . $i . '_title', $story['title']); ?></h3>
              <p><?php echo getCmsContent('success_story_' . $i . '_text', $story['text']); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    
    <!-- ══ TESTIMONIALS ══ -->
    <section id="testimonials" class="testi-sec page-section">
      <div class="container">
        <h2 class="testi-title"><?php echo getCmsContent('testimonials_title', $c['testimonials']['title']); ?></h2>
        <div class="testi-card">
          <div class="stars" style="display:flex;justify-content:center;gap:2px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.2rem;height:1.2rem;color:var(--gold);"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.2rem;height:1.2rem;color:var(--gold);"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.2rem;height:1.2rem;color:var(--gold);"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.2rem;height:1.2rem;color:var(--gold);"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.2rem;height:1.2rem;color:var(--gold);"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>
          </div>
          <p class="testi-text" id="tText"><?php echo getCmsContent('testimonials_review_0_text', $c['testimonials']['reviews'][0]['text']); ?></p>
          <div class="testi-author" style="justify-content:center;">
            <div class="testi-av-initial" id="tAv">S</div>
            <div>
              <div class="testi-name" id="tName"><?php echo getCmsContent('testimonials_review_0_name', $c['testimonials']['reviews'][0]['name']); ?></div>
              <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                <div class="testi-role-t" id="tRole"><?php echo getCmsContent('testimonials_review_0_role', $c['testimonials']['reviews'][0]['role']); ?></div>
                <span style="color:#666;font-size:0.7rem;">|</span>
                <div style="display:flex;align-items:center;gap:0.25rem;font-size:0.7rem;color:#888;"><span>Google</span></div>
              </div>
            </div>
          </div>
          <div class="testi-dots">
            <div class="tdot active" onclick="handleDotClick(0)"></div>
            <div class="tdot" onclick="handleDotClick(1)"></div>
            <div class="tdot" onclick="handleDotClick(2)"></div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- ══ ABOUT ══ -->
    <section id="about" class="about-sec sr page-section">
      <div class="container">
        <div class="about-grid">
          <div>
            <div class="about-ey"><?php echo getCmsContent('about_eyebrow', $c['about']['eyebrow']); ?></div>
            <h2 class="about-title"><?php echo getCmsContent('about_title', $c['about']['title']); ?></h2>
            <p class="about-text"><?php echo getCmsContent('about_text1', $c['about']['text1']); ?></p>
            <p class="about-text"><?php echo getCmsContent('about_text2', $c['about']['text2']); ?></p>
            <p class="about-text"><?php echo getCmsContent('about_text3', $c['about']['text3']); ?></p>
            <div class="about-loc">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.5rem;height:1.5rem;color:var(--gold);flex-shrink:0;">
                <path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.145.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" />
              </svg>
              <div>
                <div style="font-size:.68rem;font-weight:700;color:var(--gold);margin-bottom:.2rem;text-transform:uppercase;letter-spacing:.1em"><?php echo getCmsContent('about_location_label', $c['about']['location_label']); ?></div>
                <div style="font-size:.875rem"><?php echo getCmsContent('about_location_name', $c['about']['location_name']); ?></div>
                <div style="font-size:.875rem;color:#888"><?php echo getCmsContent('about_location_city', $c['about']['location_city']); ?></div>
              </div>
            </div>
            <div class="about-brand">
              <div style="margin-bottom:.4rem;font-size:.85rem"><?php echo getCmsContent('about_brand', $c['about']['brand']); ?></div>
              <a href="#" style="color:var(--cyan);font-size:.85rem"><?php echo getCmsContent('about_brand_link', $c['about']['brand_link']); ?> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-arrow"><path fill-rule="evenodd" d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></a>
            </div>
          </div>
          <div class="about-imgs">
            <div class="aimg"><img src="assets/img/TalentHub/1.jpg" alt="ENTRIKS Talent Hub Team" /></div>
            <div class="aimg"><img src="assets/img/TalentHub/2.jpg" alt="ENTRIKS Office Prishtina" /></div>
            <div class="aimg"><img src="assets/img/TalentHub/3.png" alt="ENTRIKS Teamarbeit" /></div>
            <div class="aimg"><img src="assets/img/TalentHub/4.jpg" alt="ENTRIKS Talent Hub Standort" /></div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- ══ BLOG ══ -->
    <section id="blog" class="blog-sec sr page-section">
      <div class="container">
        <div class="eyebrow cyan sec-ey"><?php echo getCmsContent('blog_eyebrow', $c['blog']['eyebrow']); ?></div>
        <h2 class="sec-title" style="color:var(--text-dark)"><?php echo getCmsContent('blog_title', $c['blog']['title']); ?></h2>
        <p class="sec-sub dk"><?php echo getCmsContent('blog_subtitle', $c['blog']['subtitle']); ?></p>
        <div class="blog-grid">
          <?php if (!empty($featuredPosts)): ?>
            <?php foreach ($featuredPosts as $post): ?>
              <?php
              $displayTitle = $post['title'];
              $imgSrc = !empty($post['image_url']) ? $post['image_url'] : 'assets/img/logo.png';
              $langParam = $lang === 'en' ? '&lang=en' : '';
              $category = $post['category'] ?? 'Nearshoring';
              $readTime = $post['read_time'] ?? '5 min';
              ?>
              <div class="blog-card">
                <div class="bimg">
                  <a href="blog.php?id=<?= $post['id'] ?><?= $langParam ?>">
                    <?php if (!empty($post['image_url'])): ?>
                      <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($displayTitle) ?>" />
                    <?php else: ?>
                      <div style="width:100%; height:200px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#9ca3af;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:48px;height:48px;opacity:0.5;">
                          <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-5.715-5.436a.75.75 0 0 0-1.07.01l-3.154 3.135-5.093-5.41a.75.75 0 0 0-1.06-.008L3 16.06Zm5.845-3.974a.75.75 0 0 0-1.06 0l-3.25 3.25a.75.75 0 1 0 1.06 1.06l2.72-2.72 2.72 2.72a.75.75 0 1 0 1.06-1.06l-2.72-2.72 2.72-2.72a.75.75 0 1 0-1.06-1.06l-2.72 2.72-2.72-2.72Z" clip-rule="evenodd"/>
                        </svg>
                      </div>
                    <?php endif; ?>
                  </a>
                  <span class="bcat"><?= htmlspecialchars($category) ?></span>
                </div>
                <div class="bbody">
                  <div class="bmeta">
                    <span>
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd"/>
                      </svg>
                      <?= htmlspecialchars($post['date']) ?>
                    </span>
                    <span>
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd"/>
                      </svg>
                      <?= htmlspecialchars($readTime) ?>
                    </span>
                  </div>
                  <h3><a href="blog.php?id=<?= $post['id'] ?><?= $langParam ?>"><?= htmlspecialchars($displayTitle ?: 'No Title') ?></a></h3>
                  <?php if (!empty($post['excerpt'])): ?>
                    <p><?= htmlspecialchars($post['excerpt']) ?></p>
                  <?php else: ?>
                    <p style="color:#ccc;font-style:italic;">No excerpt available...</p>
                  <?php endif; ?>
                  <a href="blog.php?id=<?= $post['id'] ?><?= $langParam ?>" class="blink">
                    <?php echo $lang === 'de' ? 'Weiterlesen' : 'Read more'; ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align: center; grid-column: 1 / -1; padding: 3rem; color: #9ca3af;">
              <p><?php echo $lang === 'de' ? 'Keine Blog-Beiträge gefunden.' : 'No blog posts found.'; ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ══ FAQ — all closed by default ══ -->
    <section id="faq" class="faq-sec sr page-section">
      <div class="container">
        <div class="eyebrow sec-ey"><?php echo getCmsContent('faq_eyebrow', $c['faq']['eyebrow']); ?></div>
        <h2 class="faq-title"><?php echo getCmsContent('faq_title', $c['faq']['title']); ?></h2>
        <div class="faq-list">
          <?php foreach ($c['faq']['items'] as $i => $faq): ?>
          <div class="faq-item"><button class="faq-q" onclick="toggleFaq(this)"><span><?php echo getCmsContent('faq_item_' . $i . '_q', $faq['q']); ?></span><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9" /></svg></button>
            <div class="faq-a"><?php echo getCmsContent('faq_item_' . $i . '_a', $faq['a']); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    
    <!-- ══ FINAL CTA ══ -->
    <section id="kontakt" class="cta-final page-section">
      <div class="cta-bg"></div>
      <div class="cta-ov"></div>
      <div class="container cta-content sr">
        <h2 class="cta-title" id="cta-title"><?php echo getCmsContent('cta_title', $c['cta']['title']); ?></h2>
        <p class="cta-sub" id="cta-sub"><?php echo getCmsContent('cta_subtitle', $c['cta']['subtitle']); ?></p>
        <div class="cta-btns">
          <button class="btn-outline-w" onclick="openBookingModal()"><span id="btn-cta-1"><?php echo getCmsContent('cta_btn1', $c['cta']['btn1']); ?></span> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-arrow" style="color:#111;"><path fill-rule="evenodd" d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg></button>
          <button class="btn-cta-outline" onclick="handleButtonClick(this, 'download')"><span id="btn-cta-2"><?php echo getCmsContent('cta_btn2', $c['cta']['btn2']); ?></span></button>
        </div>
        <div class="cta-trust">
          <?php foreach ($c['cta']['trust'] as $i => $trust): ?>
          <span><?php echo getCmsContent('cta_trust_' . $i, $trust); ?></span>
          <?php endforeach; ?>
        </div>
        <div class="cta-contacts">
          <a href="mailto:<?php echo $c['modal']['email_address']; ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold"><path d="M1.5 8.67v8.58a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V8.67l-8.928 5.493a3 3 0 0 1-3.144 0L1.5 8.67Z" /><path d="M22.5 6.908V6.75a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3v.158l9.714 5.978a1.5 1.5 0 0 0 1.572 0L22.5 6.908Z" /></svg><?php echo $c['modal']['email_address']; ?></a>
          <a href="tel:<?php echo $c['modal']['phone']; ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg> <?php echo $c['modal']['phone_display']; ?></a>
        </div>
      </div>
    </section>

    <!-- ══ BOOKING MODAL ══ -->
    <div id="bookingModal" class="modal-overlay" style="display: none;">
      <div class="modal-content">
        <div class="modal-header">
          <h3><?php echo $c['modal']['title']; ?></h3>
          <button class="modal-close" onclick="closeBookingModal()">&times;</button>
        </div>
        <div class="modal-body">
          <form id="bookingForm" onsubmit="submitBookingForm(event)">
            <div class="form-row">
              <div class="form-group">
                <div class="input-wrapper">
                  <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10v11M20 10v11M8 10v11M16 10v11"/>
                  </svg>
                  <input type="text" id="company" name="company" placeholder="<?php echo $c['modal']['company']; ?> *" required>
                </div>
              </div>
              <div class="form-group">
                <div class="input-wrapper">
                  <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
                  </svg>
                  <input type="text" id="name" name="name" placeholder="<?php echo $c['modal']['name']; ?> *" required>
                </div>
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <div class="input-wrapper">
                  <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="m22 7-10 5L2 7"/>
                  </svg>
                  <input type="email" id="email" name="email" placeholder="<?php echo $c['modal']['email_label']; ?> *" required>
                </div>
              </div>
              <div class="form-group">
                <div class="input-wrapper">
                  <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                  </svg>
                  <input type="tel" id="phone" name="phone" placeholder="<?php echo $c['modal']['phone_label']; ?>">
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <div class="input-wrapper service-dropdown">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M9 11l3 3L22 4"/>
                  <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
                <select id="service" name="service" required>
                  <option value="" disabled selected hidden><?php echo $c['modal']['service_label']; ?> *</option>
                  <option value="nearshoring-dedicated"><?php echo $c['modal']['services'][0]; ?></option>
                  <option value="nearshoring-team"><?php echo $c['modal']['services'][1]; ?></option>
                  <option value="active-sourcing"><?php echo $c['modal']['services'][2]; ?></option>
                  <option value="beratung"><?php echo $c['modal']['services'][3]; ?></option>
                  <option value="sonstiges"><?php echo $c['modal']['services'][4]; ?></option>
                </select>
              </div>
            </div>
            
            <div class="form-group">
              <div class="input-wrapper">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <textarea id="message" name="message" placeholder="<?php echo $c['modal']['message']; ?>"></textarea>
              </div>
            </div>
            <div class="form-checkbox">
              <input type="checkbox" id="privacy" name="privacy" required>
              <label for="privacy"><?php echo $c['modal']['privacy_label']; ?></label>
            </div>
            
            <div class="modal-actions">
              <button type="button" class="btn-secondary" onclick="closeBookingModal()"><?php echo $c['modal']['cancel']; ?></button>
              <button type="submit" class="btn-primary"><?php echo $c['modal']['submit']; ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ FOOTER ══ -->
    <footer class="footer">
      <div class="container">
        <div class="footer-grid">
          <div>
            <div class="logo-wrap" style="margin-bottom:1rem">
              <img src="<?= htmlspecialchars($footerLogoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?> Logo" class="logo-img">
              <div class="logo-sub">TALENT HUB</div>
            </div>
            <p class="footer-desc"><?= ($lang === 'de' ? $footerTextDe : $footerTextEn) ?></p>
            <p class="footer-note"><?php echo $c['footer']['note']; ?></p>
            <div class="footer-social">
              <a href="<?= htmlspecialchars($socialFacebook) ?>" aria-label="Facebook">
                <i class="fab fa-facebook-f"></i>
              </a>
              <a href="<?= htmlspecialchars($socialInstagram) ?>" aria-label="Instagram">
                <i class="fab fa-instagram"></i>
              </a>
              <a href="<?= htmlspecialchars($socialLinkedin) ?>" aria-label="LinkedIn">
                <i class="fab fa-linkedin-in"></i>
              </a>
            </div>
          </div>
          <div class="fcol">
            <h4><?php echo $c['footer']['contact']; ?></h4>
            <ul>
              <li><a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a></li>
              <li><a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactPhone) ?>"><?= htmlspecialchars($contactPhone) ?></a></li>
              <li><a href="#"><?= nl2br(htmlspecialchars($contactAddress)) ?></a></li>
            </ul>
          </div>
          <div class="fcol">
            <h4><?php echo $c['footer']['services']; ?></h4>
            <ul>
              <?php foreach ($c['footer']['links'] as $i => $link): ?>
              <li><a href="#<?php echo $i < 2 ? 'nearshoring' : ($i == 2 ? 'active-sourcing' : 'kosovo'); ?><?php echo $langSuffix; ?>"><?php echo $link; ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="fcol">
            <h4><?php echo $c['footer']['company']; ?></h4>
            <ul>
              <?php foreach ($c['footer']['company_links'] as $link): ?>
              <li><a href="#"><?php echo $link; ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <div class="footer-bottom">
          <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> | <?php echo $lang === 'de' ? 'Alle Rechte vorbehalten' : 'All rights reserved'; ?></span>
          <div class="fbl"><a href="impressum.php<?php echo $langSuffix; ?>"><?php echo $lang === 'de' ? 'Impressum' : 'Legal Notice'; ?></a><a href="datenschutz.php<?php echo $langSuffix; ?>"><?php echo $lang === 'de' ? 'Datenschutz' : 'Privacy'; ?></a><a href="agb.php<?php echo $langSuffix; ?>"><?php echo $lang === 'de' ? 'AGB' : 'Terms'; ?></a></div>
        </div>
      </div>
    </footer>

    <script>
      /* Smart Navbar - hide on scroll down, show on scroll up */
      (function() {
        const nav = document.getElementById('navbar');
        if (!nav) return;

        let lastScrollY = window.scrollY;
        let ticking = false;

        function updateNavbar() {
          const currentScrollY = window.scrollY;

          if (currentScrollY > lastScrollY && currentScrollY > 100) {
            // Scrolling down and past threshold - hide navbar
            nav.classList.add('hidden');
          } else {
            // Scrolling up - show navbar
            nav.classList.remove('hidden');
          }

          // Toggle scrolled class for styling
          nav.classList.toggle('scrolled', currentScrollY > 30);

          lastScrollY = currentScrollY;
          ticking = false;
        }

        window.addEventListener('scroll', () => {
          if (!ticking) {
            window.requestAnimationFrame(updateNavbar);
            ticking = true;
          }
        }, { passive: true });
      })();

      /* ── Language translation with full content ── */
      const translations = {
        de: {
          navNearshoring: 'Nearshoring',
          navActiveSourcing: 'Active Sourcing',
          navBlog: 'Blog',
          navAbout: 'Über uns',
          navContact: 'Kontakt',
          heroEyebrow: 'Teil der ENTRIKS Group',
          heroTitle: 'Europäische Qualität.<br><span style="color:var(--gold)">Strategische Talente.</span><br>Ihr Wettbewerbsvorteil.',
          heroSub: 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften – durch strategisches Nearshoring und gezieltes Active Sourcing. Schneller. Kosteneffizienter. Nachhaltiger.',
          heroBtn1: 'Nearshoring-Potenzial entdecken',
          heroBtn2: 'Kostenloses Erstgespräch',
          heroTrust1: 'Kostenlos & unverbindlich',
          heroTrust2: 'Antwort in 24h',
          heroTrust3: 'Persönliche Beratung',
          whyEyebrow: 'Warum ENTRIKS Talent Hub?',
          whyTitle: 'Der Fachkräftemangel im DACH-Raum ist real. Unsere Lösung auch.',
          whyText1: 'Deutsche, österreichische und schweizer Unternehmen verlieren jeden Tag wertvolle Zeit und Ressourcen in endlosen Recruiting-Zyklen – für Positionen, die der heimische Markt schlicht nicht mehr ausreichend bedienen kann.',
          whyText2: 'Wir bringen die richtigen Talente zu den richtigen Unternehmen im DACH-Raum – strukturiert, rechtssicher und nachhaltig.',
          whyCard1Title: 'Nearshoring aus dem Kosovo',
          whyCard1Text: 'Wir vermitteln und integrieren Fachkräfte aus Prishtina in Ihre Unternehmensstruktur – remote, hybrid oder vor Ort. Volle Kontrolle. Minimales Risiko.',
          whyCard1Link: 'Mehr erfahren',
          whyCard2Title: 'Active Sourcing für Ihr Unternehmen',
          whyCard2Text: 'Unser Team identifiziert, qualifiziert und präsentiert Ihnen gezielt die Kandidaten europaweit – bevor diese überhaupt aktiv suchen.',
          whyCard2Link: 'Mehr erfahren',
          modalTitle: 'Kostenloses Erstgespräch buchen',
          formCompany: 'Unternehmen *',
          formName: 'Ihr Name *',
          formEmail: 'E-Mail *',
          formPhone: 'Telefon',
          formService: 'Interesse an *',
          formMessage: 'Nachricht (optional)',
          formPrivacy: 'Ich stimme der Datenschutzerklärung zu und bin einverstanden, dass meine Daten zur Kontaktaufnahme verwendet werden. *',
          btnCancel: 'Abbrechen',
          btnSubmit: 'Termin anfordern'
        },
        en: {
          navNearshoring: 'Nearshoring',
          navActiveSourcing: 'Active Sourcing',
          navBlog: 'Blog',
          navAbout: 'About Us',
          navContact: 'Contact',
          heroEyebrow: 'Part of the ENTRIKS Group',
          heroTitle: 'European Quality.<br><span style="color:var(--gold)">Strategic Talent.</span><br>Your Competitive Advantage.',
          heroSub: 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals – through strategic nearshoring and targeted active sourcing. Faster. More cost-efficient. Sustainable.',
          heroBtn1: 'Discover Nearshoring Potential',
          heroBtn2: 'Free Initial Consultation',
          heroTrust1: 'Free & Non-binding',
          heroTrust2: 'Response in 24h',
          heroTrust3: 'Personal Consultation',
          whyEyebrow: 'Why ENTRIKS Talent Hub?',
          whyTitle: 'The skilled worker shortage in the DACH region is real. So is our solution.',
          whyText1: 'German, Austrian, and Swiss companies lose valuable time and resources every day in endless recruiting cycles – for positions that the domestic market simply can no longer adequately fill.',
          whyText2: 'We bring the right talent to the right companies in the DACH region – structured, legally secure, and sustainable.',
          whyCard1Title: 'Nearshoring from Kosovo',
          whyCard1Text: 'We place and integrate professionals from Prishtina into your corporate structure – remote, hybrid, or on-site. Full control. Minimal risk.',
          whyCard1Link: 'Learn more',
          whyCard2Title: 'Active Sourcing for Your Company',
          whyCard2Text: 'Our team identifies, qualifies, and presents candidates to you specifically across Europe – before they even actively search.',
          whyCard2Link: 'Learn more',
          modalTitle: 'Book Free Initial Consultation',
          formCompany: 'Company *',
          formName: 'Your Name *',
          formEmail: 'Email *',
          formPhone: 'Phone',
          formService: 'Interested in *',
          formMessage: 'Message (optional)',
          formPrivacy: 'I agree to the privacy policy and consent to my data being used for contact purposes. *',
          btnCancel: 'Cancel',
          btnSubmit: 'Request Appointment'
        }
      };

      let currentLang = '<?php echo $lang; ?>';

      function applyTranslation(lang) {
        currentLang = lang;
        const t = translations[lang];
        if (!t) return;

        // Update HTML lang attribute
        document.documentElement.lang = lang;

        // Update navigation
        const navLinks = document.querySelectorAll('.nav-links a');
        if (navLinks[0]) navLinks[0].textContent = t.navNearshoring;
        if (navLinks[1]) navLinks[1].textContent = t.navActiveSourcing;
        if (navLinks[2]) navLinks[2].textContent = t.navBlog;
        if (navLinks[3]) navLinks[3].textContent = t.navAbout;
        if (navLinks[4]) navLinks[4].textContent = t.navContact;

        // Update mobile nav
        const mobLinks = document.querySelectorAll('.mob-menu a');
        if (mobLinks[0]) mobLinks[0].textContent = t.navNearshoring;
        if (mobLinks[1]) mobLinks[1].textContent = t.navActiveSourcing;
        if (mobLinks[2]) mobLinks[2].textContent = t.navBlog;
        if (mobLinks[3]) mobLinks[3].textContent = t.navAbout;
        if (mobLinks[4]) mobLinks[4].textContent = t.navContact;

        // Update hero section
        const heroEyebrow = document.getElementById('hero-eyebrow');
        if (heroEyebrow) heroEyebrow.textContent = t.heroEyebrow;

        const heroTitle = document.querySelector('.hero-title');
        if (heroTitle) heroTitle.innerHTML = t.heroTitle;

        const heroSub = document.querySelector('.hero-sub');
        if (heroSub) heroSub.textContent = t.heroSub;

        // Update hero buttons
        const heroBtns = document.querySelectorAll('.hero-btns button');
        if (heroBtns[0]) {
          heroBtns[0].innerHTML = t.heroBtn1 + ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-arrow" style="color:#000;"><path fill-rule="evenodd" d="M12.97 3.97a.75.75 0 0 1 1.06 0l7.5 7.5a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 1 1-1.06-1.06l6.22-6.22H3a.75.75 0 0 1 0-1.5h16.19l-6.22-6.22a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>';
        }
        if (heroBtns[1]) heroBtns[1].textContent = t.heroBtn2;

        // Update hero trust items
        const trustSpans = document.querySelectorAll('.hero-trust span');
        if (trustSpans[0]) trustSpans[0].innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg> ' + t.heroTrust1;
        if (trustSpans[1]) trustSpans[1].innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg> ' + t.heroTrust2;
        if (trustSpans[2]) trustSpans[2].innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-icon gold"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg> ' + t.heroTrust3;

        // Update why section
        const whyEyebrow = document.getElementById('why-eyebrow');
        if (whyEyebrow) whyEyebrow.textContent = t.whyEyebrow;

        const whyTitle = document.getElementById('why-title');
        if (whyTitle) whyTitle.textContent = t.whyTitle;

        const whyText1 = document.getElementById('why-text-1');
        if (whyText1) whyText1.textContent = t.whyText1;

        const whyText2 = document.getElementById('why-text-2');
        if (whyText2) whyText2.textContent = t.whyText2;

        const whyCard1Title = document.getElementById('why-card1-title');
        if (whyCard1Title) whyCard1Title.textContent = t.whyCard1Title;

        const whyCard1Text = document.getElementById('why-card1-text');
        if (whyCard1Text) whyCard1Text.textContent = t.whyCard1Text;

        const whyCard1Link = document.getElementById('why-card1-link');
        if (whyCard1Link) whyCard1Link.textContent = t.whyCard1Link;

        const whyCard2Title = document.getElementById('why-card2-title');
        if (whyCard2Title) whyCard2Title.textContent = t.whyCard2Title;

        const whyCard2Text = document.getElementById('why-card2-text');
        if (whyCard2Text) whyCard2Text.textContent = t.whyCard2Text;

        const whyCard2Link = document.getElementById('why-card2-link');
        if (whyCard2Link) whyCard2Link.textContent = t.whyCard2Link;

        // Update modal
        const modalHeader = document.querySelector('.modal-header h3');
        if (modalHeader) modalHeader.textContent = t.modalTitle;

        // Update form placeholders
        const companyInput = document.getElementById('company');
        if (companyInput) companyInput.placeholder = t.formCompany;

        const nameInput = document.getElementById('name');
        if (nameInput) nameInput.placeholder = t.formName;

        const emailInput = document.getElementById('email');
        if (emailInput) emailInput.placeholder = t.formEmail;

        const phoneInput = document.getElementById('phone');
        if (phoneInput) phoneInput.placeholder = t.formPhone;

        const serviceSelect = document.getElementById('service');
        if (serviceSelect && serviceSelect.options[0]) serviceSelect.options[0].textContent = t.formService;

        const messageInput = document.getElementById('message');
        if (messageInput) messageInput.placeholder = t.formMessage;

        // Update privacy checkbox label
        const privacyLabel = document.querySelector('.form-checkbox label');
        if (privacyLabel) {
          privacyLabel.innerHTML = t.formPrivacy.replace('Datenschutzerklärung', '<a href="#">Datenschutzerklärung</a>').replace('privacy policy', '<a href="#">privacy policy</a>');
        }

        // Update modal buttons
        const cancelBtn = document.querySelector('.modal-actions .btn-secondary');
        if (cancelBtn) cancelBtn.textContent = t.btnCancel;

        const submitBtn = document.querySelector('.modal-actions .btn-primary');
        if (submitBtn) submitBtn.textContent = t.btnSubmit;

        // Store language preference
        localStorage.setItem('preferredLang', lang);
      }

      // Load saved language preference on page load
      const savedLang = localStorage.getItem('preferredLang');
      if (savedLang && savedLang !== 'de') {
        applyTranslation(savedLang);
        document.getElementById('langCurrentLabel').textContent = savedLang.toUpperCase();
        const mobileLabel = document.getElementById('langCurrentLabelMobile');
        if (mobileLabel) mobileLabel.textContent = savedLang.toUpperCase();
        document.querySelectorAll('.lang-option').forEach(b => {
          b.classList.toggle('active', b.dataset.lang === savedLang);
        });
        document.querySelectorAll('.mob-menu .lang-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.lang === savedLang);
          b.style.color = b.dataset.lang === savedLang ? '#fff' : '#888';
          b.style.fontWeight = b.dataset.lang === savedLang ? '700' : '400';
        });
      }

      /* Language dropdown - Desktop */
      const langWrap = document.getElementById('langDropdownWrap');
      const langGlobeBtn = document.getElementById('langGlobeBtn');
      const langDropdown = document.getElementById('langDropdown');

      if (langGlobeBtn && langWrap) {
        langGlobeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const isOpen = langWrap.classList.toggle('open');
          langGlobeBtn.setAttribute('aria-expanded', isOpen);
        });

        document.addEventListener('click', () => {
          langWrap.classList.remove('open');
          langGlobeBtn.setAttribute('aria-expanded', 'false');
        });

        langDropdown.addEventListener('click', (e) => e.stopPropagation());
      }

      /* Language dropdown - Mobile */
      const langWrapMobile = document.getElementById('langDropdownWrapMobile');
      const langGlobeBtnMobile = document.getElementById('langGlobeBtnMobile');
      const langDropdownMobile = document.getElementById('langDropdownMobile');

      if (langGlobeBtnMobile && langWrapMobile) {
        langGlobeBtnMobile.addEventListener('click', (e) => {
          e.stopPropagation();
          const isOpen = langWrapMobile.classList.toggle('open');
          langGlobeBtnMobile.setAttribute('aria-expanded', isOpen);
        });

        document.addEventListener('click', () => {
          langWrapMobile.classList.remove('open');
          langGlobeBtnMobile.setAttribute('aria-expanded', 'false');
        });

        langDropdownMobile.addEventListener('click', (e) => e.stopPropagation());

        document.querySelectorAll('.lang-option').forEach(btn => {
          btn.addEventListener('click', () => {
            const lang = btn.dataset.lang;
            if (lang && lang !== currentLang) {
              applyTranslation(lang);
              
              // Update active states in dropdown
              document.querySelectorAll('.lang-option').forEach(b => {
                b.classList.toggle('active', b.dataset.lang === lang);
              });
              
              // Update mobile buttons
              document.querySelectorAll('.lang-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.lang === lang);
                b.style.color = b.dataset.lang === lang ? 'var(--gold)' : '#888';
                b.style.fontWeight = b.dataset.lang === lang ? '700' : 'normal';
              });
              
              // Update labels
              const label = document.getElementById('langCurrentLabel');
              if (label) label.textContent = lang.toUpperCase();
              const mobileLabel = document.getElementById('langCurrentLabelMobile');
              if (mobileLabel) mobileLabel.textContent = lang.toUpperCase();
            }
          });
        });
      }

      // Mobile language buttons
      document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const lang = btn.dataset.lang;
          if (lang && lang !== currentLang) {
            applyTranslation(lang);
            
            // Update active states
            document.querySelectorAll('.lang-btn').forEach(b => {
              b.classList.toggle('active', b.dataset.lang === lang);
              b.style.color = b.dataset.lang === lang ? 'var(--gold)' : '#888';
              b.style.fontWeight = b.dataset.lang === lang ? '700' : 'normal';
            });
            
            // Update dropdown buttons
            document.querySelectorAll('.lang-option').forEach(b => {
              b.classList.toggle('active', b.dataset.lang === lang);
            });
            
            // Update labels
            const label = document.getElementById('langCurrentLabel');
            if (label) label.textContent = lang.toUpperCase();
            const mobileLabel = document.getElementById('langCurrentLabelMobile');
            if (mobileLabel) mobileLabel.textContent = lang.toUpperCase();
          }
        });
      });

      /* ── Mobile menu ── */
      const mobBtn = document.getElementById('mobBtn');
      const mobMenu = document.getElementById('mobMenu');
      if (mobBtn && mobMenu) {
        mobBtn.addEventListener('click', () => mobMenu.classList.toggle('open'));
        document.querySelectorAll('.mob-menu a').forEach(a => {
          a.addEventListener('click', () => mobMenu.classList.remove('open'));
        });
      }

      /* ── FAQ ── */
      function toggleFaq(btn) {
        const item = btn.closest('.faq-item');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
        
        // Add haptic feedback simulation
        if (navigator.vibrate) {
          navigator.vibrate(50);
        }
      }

      /* ── Button interactions ── */
      function handleButtonClick(button, action) {
        // Visual feedback
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
          button.style.transform = 'scale(1)';
        }, 150);
        
        // Haptic feedback
        if (navigator.vibrate) {
          navigator.vibrate(50);
        }
        
        // Execute action
        if (action === 'contact') {
          // Open contact modal or scroll to contact
          document.getElementById('kontakt').scrollIntoView({ behavior: 'smooth' });
        } else if (action === 'download') {
          // Trigger download
          alert('Leistungsangebot wird heruntergeladen...');
        } else if (action === 'case-study') {
          // Open case study modal or navigate
          alert('Case Study wird geöffnet...');
        }
      }

      /* ── Scroll reveal ── */
      const srObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            e.target.classList.add('on');
            srObs.unobserve(e.target);
          }
        });
      }, { threshold: 0.1 });
      document.querySelectorAll('.sr').forEach(el => srObs.observe(el));

      /* ── Count-up stats ── */
      function countUp(el) {
        const target = +el.dataset.target;
        const suffix = el.dataset.suffix || '';
        const dur = 1800;
        const step = 16;
        const inc = target / (dur / step);
        let curCount = 0;
        const t = setInterval(() => {
          curCount += inc;
          if (curCount >= target) {
            curCount = target;
            clearInterval(t);
          }
          el.textContent = Math.floor(curCount) + suffix;
        }, step);
      }

      const statsObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            e.target.querySelectorAll('[data-target]').forEach(el => countUp(el));
            statsObs.unobserve(e.target);
          }
        });
      }, { threshold: 0.3 });
      const ss = document.querySelector('.stats-section');
      if (ss) statsObs.observe(ss);

      /* ── Checklist stagger ── */
      const clObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            e.target.querySelectorAll('li').forEach((li, i) => {
              setTimeout(() => li.classList.add('on'), i * 75);
            });
            clObs.unobserve(e.target);
          }
        });
      }, { threshold: 0.15 });
      document.querySelectorAll('.check-list').forEach(el => clObs.observe(el));

      /* ── Expertise tags ── */
      document.querySelectorAll('.exp-tag').forEach(tag => {
        tag.addEventListener('click', () => {
          document.querySelectorAll('.exp-tag').forEach(t => t.classList.remove('selected'));
          tag.classList.add('selected');
        });
      });

      const ts = [
        { text: <?php echo json_encode($c['testimonials']['reviews'][0]['text']); ?>, initial: <?php echo json_encode(substr($c['testimonials']['reviews'][0]['name'], 0, 1)); ?>, name: <?php echo json_encode($c['testimonials']['reviews'][0]['name']); ?>, role: <?php echo json_encode($c['testimonials']['reviews'][0]['role']); ?> },
        { text: <?php echo json_encode($c['testimonials']['reviews'][1]['text']); ?>, initial: <?php echo json_encode(substr($c['testimonials']['reviews'][1]['name'], 0, 1)); ?>, name: <?php echo json_encode($c['testimonials']['reviews'][1]['name']); ?>, role: <?php echo json_encode($c['testimonials']['reviews'][1]['role']); ?> },
        { text: <?php echo json_encode($c['testimonials']['reviews'][2]['text']); ?>, initial: <?php echo json_encode(substr($c['testimonials']['reviews'][2]['name'], 0, 1)); ?>, name: <?php echo json_encode($c['testimonials']['reviews'][2]['name']); ?>, role: <?php echo json_encode($c['testimonials']['reviews'][2]['role']); ?> }
      ];

      let curIdx = 0;
      let testimonialInterval;

      function setT(i) {
        const tx = document.getElementById('tText');
        const tAv = document.getElementById('tAv');
        if (!tx || !tAv) return;

        // Fade out
        tx.style.opacity = '0';

        setTimeout(() => {
          curIdx = i;
          tx.textContent = ts[i].text;
          document.getElementById('tName').textContent = ts[i].name;
          document.getElementById('tRole').textContent = ts[i].role;
          tAv.textContent = ts[i].initial;

          document.querySelectorAll('.tdot').forEach((d, j) => {
            d.classList.toggle('active', j === i);
          });

          // Fade in
          tx.style.opacity = '1';
        }, 220);
      }

      // Function to start/restart timer
      function startTestimonialTimer() {
        clearInterval(testimonialInterval);
        testimonialInterval = setInterval(() => {
          setT((curIdx + 1) % ts.length);
        }, 5000);
      }

      // Initialize
      startTestimonialTimer();

      // Function for manual click (prevents logic clash with auto-timer)
      function handleDotClick(i) {
        setT(i);
        startTestimonialTimer(); // Reset timer so it doesn't flip immediately after click
      }

      /* ── BOOKING MODAL FUNCTIONS ── */
      function openBookingModal() {
        const modal = document.getElementById('bookingModal');
        modal.style.display = 'flex';
        setTimeout(() => {
          modal.classList.add('show');
        }, 10);
        document.body.style.overflow = 'hidden';
      }

      function closeBookingModal() {
        const modal = document.getElementById('bookingModal');
        modal.classList.remove('show');
        setTimeout(() => {
          modal.style.display = 'none';
        }, 300);
        document.body.style.overflow = '';
      }

      // Form now submits via AJAX
      function submitBookingForm(event) {
        event.preventDefault();
        
        const form = document.getElementById('bookingForm');
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Wird gesendet...';
        submitBtn.disabled = true;
        
        // Send via AJAX
        fetch('php/sendemail.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            showToast(data.message, 'success');
            closeBookingModal();
            form.reset();
          } else {
            showToast(data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('Fehler: Ihre Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.', 'error');
        })
        .finally(() => {
          // Reset button state
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
        });
      }

      // Close modal when clicking outside
      document.addEventListener('click', (event) => {
        const modal = document.getElementById('bookingModal');
        if (event.target === modal) {
          closeBookingModal();
        }
      });

      // Close modal with Escape key
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeBookingModal();
        }
      });
    </script>
  
  <button class="back-to-top" id="backToTop" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="18 15 12 9 6 15"/>
    </svg>
  </button>

  <script>
    (function(){
      const btn = document.getElementById('backToTop');
      if(!btn) return;
      window.addEventListener('scroll', function(){
        btn.classList.toggle('visible', window.scrollY > 400);
      });
    })();
  </script>
  
  <!-- Toast notification function - always available -->
  <script>
    function showToast(message, type) {
      // Create toast element
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      
      // Create icon
      const icon = document.createElement('div');
      icon.className = 'toast-icon';
      icon.innerHTML = type === 'success' 
        ? '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" /></svg>';
      
      // Create message
      const messageEl = document.createElement('div');
      messageEl.textContent = message;
      
      // Create close button
      const closeBtn = document.createElement('button');
      closeBtn.className = 'toast-close';
      closeBtn.innerHTML = '&times;';
      closeBtn.onclick = function() {
        hideToast(toast);
      };
      
      // Assemble toast
      toast.appendChild(icon);
      toast.appendChild(messageEl);
      toast.appendChild(closeBtn);
      
      // Add to page
      document.body.appendChild(toast);
      
      // Show toast
      setTimeout(() => {
        toast.classList.add('show');
      }, 100);
      
      // Auto hide after 5 seconds
      setTimeout(() => {
        hideToast(toast);
      }, 5000);
    }
    
    function hideToast(toast) {
      toast.classList.remove('show');
      setTimeout(() => {
        if (toast.parentElement) {
          toast.remove();
        }
      }, 300);
    }
  </script>

  <?php if ($message): ?>
  <script>
    // Show toast notification
    document.addEventListener('DOMContentLoaded', function() {
      showToast('<?php echo htmlspecialchars($message); ?>', '<?php echo $message_type; ?>');
    });
  </script>
  <?php endif; ?>

  <!-- Cookie Consent -->
  <link rel="stylesheet" href="assets/css/cookie-consent.css?v=1">
  <script>
    window.cookieConsentEnabled = true;
  </script>
  <script src="assets/js/cookie-consent.js?v=1" defer></script>

  <!-- View Counter -->
  <script>
    (function() {
      const cookieName = 'last_main_view_tracked';
      function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
      }
      if (getCookie(cookieName)) return;

      const fd = new FormData();
      fd.append('title', 'Homepage - ENTRIKS Talent Hub');

      fetch('backend/sync_data.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      }).then(r => r.json()).then(data => {
        if (data.success) {
          document.cookie = `${cookieName}=1; max-age=1800; path=/`;
        }
      }).catch(() => { });
    })();
  </script>

  <?php if (isset($_SESSION['admin'])): ?>
  <!-- Admin Notifications -->
  <script>
    (function() {
      const lang = '<?php echo $lang; ?>';

      if ("Notification" in window) {
        if (Notification.permission !== "granted" && Notification.permission !== "denied") {
          Notification.requestPermission();
        }
      }

      let lastPollTime = <?php echo class_exists('MongoDB\BSON\UTCDateTime') ? (string) new MongoDB\BSON\UTCDateTime() : 'Date.now()'; ?>;
      const basePrefix = 'backend/';

      function showNotification(notification) {
        let title = '';
        let body = '';
        let iconUrl = 'assets/img/favicon.png';

        switch (notification.type) {
          case 'blog_view':
            title = (lang === 'de') ? 'Neuer Blog-Besuch' : 'New Blog View';
            body = notification.item_title || (lang === 'de' ? 'Blog-Beitrag' : 'Blog Post');
            break;
          case 'main_view':
            title = (lang === 'de') ? 'Neuer Website-Besuch' : 'New Website Visit';
            body = notification.item_title || (lang === 'de' ? 'Homepage' : 'Homepage');
            break;
          case 'new_comment':
            title = (lang === 'de') ? 'Neuer Kommentar' : 'New Comment';
            body = notification.item_title || (lang === 'de' ? 'Blog-Beitrag' : 'Blog Post');
            break;
          case 'watched_comments':
            title = (lang === 'de') ? 'Kommentare angesehen' : 'Comments Watched';
            body = notification.item_title || (lang === 'de' ? 'Blog-Beitrag' : 'Blog Post');
            break;
          default:
            title = (lang === 'de') ? 'Benachrichtigung' : 'Notification';
            body = notification.item_title || (lang === 'de' ? 'Neue Aktivität' : 'New Activity');
        }

        if ("Notification" in window && Notification.permission === "granted") {
          try {
            new Notification(title, {
              body: body,
              icon: iconUrl,
              tag: notification.id
            });
          } catch (e) { console.error('Notification error:', e); }
        }
      }

      function pollNotifications() {
        fetch(`${basePrefix}get_notifications.php?since=${lastPollTime}`)
          .then(res => res.json())
          .then(data => {
            if (data.success && data.notifications.length > 0) {
              data.notifications.forEach(n => {
                showNotification(n);
              });
              lastPollTime = data.server_time;
            }
          })
          .catch(err => console.error('Poll error:', err));
      }

      setInterval(pollNotifications, 5000);
      setTimeout(pollNotifications, 1000);
    })();
  </script>
  <?php endif; ?>
</body>
</html>
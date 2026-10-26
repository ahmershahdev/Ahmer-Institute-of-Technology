<?php

declare(strict_types=1);

require_once __DIR__ . '/pdo.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cache.php';

const AIT_NAME = 'Ahmer Institute of Technology';
const AIT_SHORT = 'AIT';

function ait_site_content(): array
{
    return ait_cache_remember('site_content', 600, static function (): array {
        try {
            return ait_pdo()->query('SELECT content_key, content_value FROM site_content')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    });
}

function ait_site_data(string $key, string $fallback = ''): string
{
    $value = ait_site_content()[$key] ?? '';
    return trim((string) $value) !== '' ? (string) $value : $fallback;
}

function ait_contact(): array
{
    return [
        'email' => (string) ait_env('CONTACT_EMAIL', 'admissions@ait.ahmershah.dev'),
        'phone' => (string) ait_env('CONTACT_PHONE', ''),
        'street' => (string) ait_env('CONTACT_STREET', 'Indus Highway'),
        'city' => (string) ait_env('CONTACT_CITY', 'Jamshoro'),
        'region' => (string) ait_env('CONTACT_REGION', 'Sindh'),
        'postal' => (string) ait_env('CONTACT_POSTAL_CODE', '76062'),
        'country' => 'PK',
        'lat' => (float) ait_env('CONTACT_LAT', 25.4304),
        'lng' => (float) ait_env('CONTACT_LNG', 68.2811),
        'hours' => 'Monday to Friday, 08:30 to 16:30',
    ];
}

function ait_intake(): string
{
    return ait_site_data('admissions_intake', 'Spring 2027');
}

const AIT_PROGRAM_DETAILS = [
    'AI' => ['credits' => 136, 'seats' => 60, 'focus' => 'Machine learning, computer vision, and responsible AI with a final-year applied project.'],
    'CS' => ['credits' => 133, 'seats' => 120, 'focus' => 'Algorithms, systems, databases, and software construction with two industry projects.'],
    'EE' => ['credits' => 136, 'seats' => 90, 'focus' => 'Power, electronics, and control systems, with lab work in every semester.'],
    'ME' => ['credits' => 136, 'seats' => 90, 'focus' => 'Thermodynamics, design, and manufacturing, finishing with a capstone build.'],
    'SE' => ['credits' => 133, 'seats' => 60, 'focus' => 'Requirements, architecture, testing, and delivery practices used by real teams.'],
    'CE' => ['credits' => 136, 'seats' => 90, 'focus' => 'Structures, geotechnics, and water resources for the infrastructure Sindh needs.'],
    'MATH' => ['credits' => 130, 'seats' => 40, 'focus' => 'Pure and applied mathematics with strong computational methods.'],
    'ENG' => ['credits' => 130, 'seats' => 40, 'focus' => 'Linguistics, literature, and professional writing for technical fields.'],
    'PHY' => ['credits' => 130, 'seats' => 40, 'focus' => 'Classical and modern physics, instrumentation, and experimental method.'],
    'CYBER' => ['credits' => 133, 'seats' => 60, 'focus' => 'Network defence, secure coding, digital forensics, and security operations.'],
    'DS' => ['credits' => 133, 'seats' => 60, 'focus' => 'Statistics, data engineering, and visual analysis for real decisions.'],
    'ARCH' => ['credits' => 170, 'seats' => 40, 'focus' => 'Design studios every term, climate-aware building, and a thesis project.'],
    'ENV' => ['credits' => 136, 'seats' => 40, 'focus' => 'Water and air quality, waste, and environmental impact assessment.'],
    'ECO' => ['credits' => 130, 'seats' => 40, 'focus' => 'Micro and macroeconomics, econometrics, and development policy.'],
    'BBA' => ['credits' => 132, 'seats' => 80, 'focus' => 'Accounting, marketing, operations, and entrepreneurship with an internship.'],
];

function ait_catalog_programs(): array
{
    return ait_cache_remember('catalog_programs', 900, static function (): array {
        $fallback = [];
        foreach ([
            ['Artificial Intelligence', 'AI', 'Computing & Engineering'], ['Computer Science', 'CS', 'Computing & Engineering'],
            ['Electrical Engineering', 'EE', 'Engineering'], ['Mechanical Engineering', 'ME', 'Engineering'],
            ['Software Engineering', 'SE', 'Computing & Engineering'], ['Civil Engineering', 'CE', 'Civil & Architecture'],
            ['Mathematics', 'MATH', 'Sciences & Humanities'], ['English', 'ENG', 'Sciences & Humanities'],
            ['Physics', 'PHY', 'Sciences & Humanities'], ['Cyber Security', 'CYBER', 'Computing & Engineering'],
            ['Data Science', 'DS', 'Computing & Engineering'], ['Architecture', 'ARCH', 'Civil & Architecture'],
            ['Environmental Engineering', 'ENV', 'Civil & Architecture'], ['Economics', 'ECO', 'Sciences & Humanities'],
            ['Business Administration', 'BBA', 'Management Sciences'],
        ] as [$name, $code, $faculty]) {
            $fallback[] = ['name' => $name, 'code' => $code, 'degree_level' => 'BS', 'duration_years' => $code === 'ARCH' ? '5.0' : '4.0', 'faculty' => $faculty];
        }
        try {
            $rows = ait_pdo()->query("SELECT p.name, p.code, p.degree_level, p.duration_years, COALESCE(f.name, 'AIT Academic School') AS faculty FROM programs p LEFT JOIN faculties f ON f.id = p.faculty_id WHERE p.is_active = 1 ORDER BY p.name")->fetchAll();
            if ($rows) {
                $existing = array_map(static fn(array $row): string => strtolower($row['name']), $rows);
                foreach ($fallback as $program) {
                    if (!in_array(strtolower($program['name']), $existing, true)) {
                        $rows[] = $program;
                    }
                }
                usort($rows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
                return $rows;
            }
        } catch (Throwable $e) {
        }
        return $fallback;
    });
}

function ait_program_detail(string $code): array
{
    return AIT_PROGRAM_DETAILS[strtoupper($code)] ?? ['credits' => 130, 'seats' => 40, 'focus' => 'A practical four-year degree with lab, project, and internship components.'];
}

function ait_pages(): array
{
    $intake = ait_intake();
    return [
        'home' => [
            'title' => 'Ahmer Institute of Technology | Jamshoro, Sindh',
            'description' => "Degrees in engineering, computing, science and business in Jamshoro, Sindh. See programs, fees and how to apply for the {$intake} intake.",
            'nav' => 'Home', 'type' => 'WebPage', 'priority' => '1.0', 'changefreq' => 'weekly',
        ],
        'about' => [
            'title' => 'About AIT | Ahmer Institute of Technology',
            'description' => 'How AIT teaches: small cohorts, lab work in every semester, and faculty who know students by name. Our mission, values and campus.',
            'nav' => 'About', 'type' => 'AboutPage', 'priority' => '0.8', 'changefreq' => 'monthly',
            'eyebrow' => 'About the institute', 'h1' => 'A technical institute that teaches by doing.',
            'lede' => 'AIT was set up around a simple idea: people learn engineering, computing, and science best when they build things, get honest feedback, and build again.',
            'image' => ['src' => 'assets/images/campus/main-gate-960.webp', 'alt' => 'Students walking through the AIT main gate on a weekday morning', 'w' => 960, 'h' => 640],
        ],
        'programs' => [
            'title' => 'Undergraduate Programs | AIT Jamshoro',
            'description' => 'Fifteen four and five year bachelor programs in AI, computing, engineering, science, architecture and business, with credit hours and seats.',
            'nav' => 'Programs', 'type' => 'CollectionPage', 'priority' => '0.9', 'changefreq' => 'monthly',
            'eyebrow' => 'Programs', 'h1' => 'Undergraduate programs at AIT.',
            'lede' => 'Every program combines classroom theory with lab or studio work each semester, a supervised internship, and a final-year project you can show an employer.',
        ],
        'admissions' => [
            'title' => "Admissions {$intake} | AIT Jamshoro",
            'description' => "Eligibility, merit formula, fees and key dates for the {$intake} intake at AIT, plus a step-by-step guide to applying online.",
            'nav' => 'Admissions', 'type' => 'WebPage', 'priority' => '0.9', 'changefreq' => 'weekly',
            'eyebrow' => "{$intake} intake", 'h1' => 'Admissions: how to apply to AIT.',
            'lede' => 'Applications are submitted online. You create an account, fill in your academic record, upload your documents, and track every decision from the same portal.',
        ],
        'departments' => [
            'title' => 'Departments and Faculties | AIT Jamshoro',
            'description' => 'AIT is organised into five faculties covering computing, engineering, civil and architecture, sciences and humanities, and management.',
            'nav' => 'Departments', 'type' => 'CollectionPage', 'priority' => '0.7', 'changefreq' => 'monthly',
            'eyebrow' => 'Faculties and departments', 'h1' => 'Departments and faculties.',
            'lede' => 'Fifteen departments sit inside five faculties. Students take shared first-year courses, so a civil engineer and a data scientist often meet in the same lab.',
        ],
        'faculty' => [
            'title' => 'Faculty and Teaching Staff | AIT Jamshoro',
            'description' => 'Meet the teachers behind AIT programs: their departments, designations and public profiles, kept current by the academic office.',
            'nav' => 'Faculty', 'type' => 'CollectionPage', 'priority' => '0.6', 'changefreq' => 'monthly',
            'eyebrow' => 'Faculty', 'h1' => 'The people who teach at AIT.',
            'lede' => 'Our teachers combine academic qualifications with practice. Each profile below links to the subjects they teach this semester.',
        ],
        'campus-life' => [
            'title' => 'Campus Life, Labs and Societies | AIT',
            'description' => 'Labs that stay open late, a library, sports grounds, hostels and student societies. What a normal week on the AIT campus looks like.',
            'nav' => 'Campus life', 'type' => 'WebPage', 'priority' => '0.6', 'changefreq' => 'monthly',
            'eyebrow' => 'Campus life', 'h1' => 'Life on the AIT campus.',
            'lede' => 'Classes end at four, but the labs, library, and sports grounds stay busy well after that. Here is what you can expect outside the timetable.',
        ],
        'students' => [
            'title' => 'Student Services and Support | AIT',
            'description' => 'Advising, counselling, scholarships, transport and the student portal. Where AIT students go for help and how to reach each office.',
            'nav' => 'Students', 'type' => 'WebPage', 'priority' => '0.6', 'changefreq' => 'monthly',
            'eyebrow' => 'For current students', 'h1' => 'Student services and support.',
            'lede' => 'From your first registration to your final transcript, these are the offices and tools that keep your semester on track.',
        ],
        'alumini' => [
            'title' => 'Alumni Network | Ahmer Institute of Technology',
            'description' => 'Stay connected after graduation: mentor current students, hire AIT graduates, request transcripts, and hear about reunions and events.',
            'nav' => 'Alumni', 'type' => 'WebPage', 'priority' => '0.5', 'changefreq' => 'monthly',
            'eyebrow' => 'Alumni', 'h1' => 'Staying connected after AIT.',
            'lede' => 'Graduation is not the end of your relationship with AIT. Alumni mentor students, recruit from our labs, and come back to teach.',
        ],
        'career' => [
            'title' => 'Careers and Placements | AIT Jamshoro',
            'description' => 'Internships, the annual job fair, CV clinics and open positions at AIT. How the placement office helps students find their first job.',
            'nav' => 'Careers', 'type' => 'WebPage', 'priority' => '0.5', 'changefreq' => 'monthly',
            'eyebrow' => 'Careers and placements', 'h1' => 'Careers, internships, and jobs.',
            'lede' => 'The placement office works with students from the second year onward, so the first job search does not start in the final semester.',
        ],
        'faq' => [
            'title' => 'Admissions FAQ | Ahmer Institute of Technology',
            'description' => 'Straight answers on eligibility, documents, fees, entry tests, hostels and scholarships for students applying to AIT.',
            'nav' => 'FAQ', 'type' => 'FAQPage', 'priority' => '0.5', 'changefreq' => 'monthly',
            'eyebrow' => 'Frequently asked questions', 'h1' => 'Questions applicants ask us most.',
            'lede' => 'If your question is not answered here, write to the admissions office and a member of the team will reply within two working days.',
        ],
        'contact' => [
            'title' => 'Contact AIT | Admissions Office, Jamshoro',
            'description' => 'Email the AIT admissions office, find the campus on the map, or send a message through the contact form. We reply within two working days.',
            'nav' => 'Contact', 'type' => 'ContactPage', 'priority' => '0.6', 'changefreq' => 'monthly',
            'eyebrow' => 'Contact', 'h1' => 'Contact the admissions office.',
            'lede' => 'Questions about programs, eligibility, fees, or a campus visit? Send a message and the right office will answer.',
        ],
        'privacy-policy' => [
            'title' => 'Privacy Policy | Ahmer Institute of Technology',
            'description' => 'What personal data AIT collects through its website and portals, why we keep it, who can see it, and how to ask for a copy or a correction.',
            'nav' => 'Privacy policy', 'type' => 'WebPage', 'priority' => '0.3', 'changefreq' => 'yearly',
            'eyebrow' => 'Privacy policy', 'h1' => 'Privacy policy.',
            'lede' => 'This policy explains how AIT handles the information you give us through this website and the student, teacher, and staff portals.',
        ],
        'terms-of-service' => [
            'title' => 'Terms of Use | Ahmer Institute of Technology',
            'description' => 'The rules for using the AIT website and portals: your account, acceptable use, uploaded documents, and how admission decisions are made.',
            'nav' => 'Terms of use', 'type' => 'WebPage', 'priority' => '0.3', 'changefreq' => 'yearly',
            'eyebrow' => 'Terms of use', 'h1' => 'Terms of use.',
            'lede' => 'By creating an account or using the AIT portals you agree to the terms below. They are written to be read, not skimmed.',
        ],
    ];
}

function ait_site_url(): string
{
    $configured = rtrim((string) ait_env('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . ait_base_path();
}

function ait_public_canonical_url(string $page): string
{
    return ait_site_url() . '/' . ($page === 'home' || $page === '' ? '' : rawurlencode($page));
}

function ait_page_cache_key(string $page): string
{
    $version = (int) (ait_cache_get('page_cache_version', $found) ?? 0);
    return 'page:' . $version . ':' . hash('sha256', ait_site_url() . '|' . $page);
}

function ait_page_cache_flush(): void
{
    $version = (int) (ait_cache_get('page_cache_version', $found) ?? 0);
    ait_cache_set('page_cache_version', $version + 1, 86400 * 30);
}

function ait_page_cacheable(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && empty($_GET)
        && empty($_SESSION['student_id']) && empty($_SESSION['admin_id'])
        && empty($_SESSION['teacher_id']) && empty($_SESSION['staff_id'])
        && (string) ait_env('PAGE_CACHE', '1') === '1';
}

function ait_page_cache_serve(string $page): bool
{
    if (!ait_page_cacheable()) {
        return false;
    }
    $html = ait_cache_get(ait_page_cache_key($page), $found);
    if (!$found || !is_string($html)) {
        return false;
    }
    $nonce = ait_bootstrap_security();
    header('X-Cache: HIT');
    echo str_replace(['%%AIT_NONCE%%', '%%AIT_CSRF%%'], [$nonce, ait_e(ait_init_csrf_token())], $html);
    return true;
}

function ait_page_cache_store(string $page, string $html): void
{
    if (!ait_page_cacheable()) {
        return;
    }
    $nonce = ait_csp_nonce();
    $csrf = ait_e(ait_init_csrf_token());
    ait_cache_set(ait_page_cache_key($page), str_replace([$nonce, $csrf], ['%%AIT_NONCE%%', '%%AIT_CSRF%%'], $html), 300);
}

function ait_organization_schema(): array
{
    $c = ait_contact();
    $org = [
        '@type' => ['CollegeOrUniversity', 'EducationalOrganization'],
        '@id' => ait_site_url() . '/#organization',
        'name' => AIT_NAME,
        'alternateName' => AIT_SHORT,
        'url' => ait_site_url() . '/',
        'logo' => ['@type' => 'ImageObject', 'url' => ait_site_url() . '/assets/images/icons/icon-512.png', 'width' => 512, 'height' => 512],
        'image' => ait_site_url() . '/assets/images/og/ait-campus-og.jpg',
        'email' => $c['email'],
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $c['street'], 'addressLocality' => $c['city'], 'addressRegion' => $c['region'], 'postalCode' => $c['postal'], 'addressCountry' => $c['country']],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $c['lat'], 'longitude' => $c['lng']],
        'sameAs' => ['https://github.com/ahmershahdev/Ahmer-Institute-of-Technology', 'https://www.linkedin.com/in/syedahmershah', 'https://www.facebook.com/ahmershahdev'],
    ];
    if ($c['phone'] !== '') {
        $org['telephone'] = $c['phone'];
    }
    return $org;
}

function ait_page_schema(string $page, array $meta, array $extra = []): string
{
    $url = ait_public_canonical_url($page);
    $graph = [
        ait_organization_schema(),
        ['@type' => 'WebSite', '@id' => ait_site_url() . '/#website', 'url' => ait_site_url() . '/', 'name' => AIT_NAME, 'inLanguage' => 'en', 'publisher' => ['@id' => ait_site_url() . '/#organization']],
        [
            '@type' => $meta['type'],
            '@id' => $url . '#webpage',
            'url' => $url,
            'name' => $meta['title'],
            'description' => $meta['description'],
            'inLanguage' => 'en',
            'isPartOf' => ['@id' => ait_site_url() . '/#website'],
            'about' => ['@id' => ait_site_url() . '/#organization'],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => ait_site_url() . '/assets/images/og/ait-campus-og.jpg'],
            'breadcrumb' => ['@id' => $url . '#breadcrumb'],
        ],
        [
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => array_values(array_filter([
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ait_public_canonical_url('home')],
                $page !== 'home' ? ['@type' => 'ListItem', 'position' => 2, 'name' => $meta['nav'], 'item' => $url] : null,
            ])),
        ],
    ];
    foreach ($extra as $node) {
        if ($meta['type'] === 'FAQPage' && ($node['@type'] ?? '') === 'FAQPage') {
            $graph[2] = array_merge($graph[2], ['mainEntity' => $node['mainEntity']]);
            continue;
        }
        $graph[] = $node;
    }
    return json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function ait_public_header(string $page, array $extraSchema = [], bool $indexable = true): void
{
    $pages = ait_pages();
    $label = ucwords(str_replace('-', ' ', $page));
    $meta = $pages[$page] ?? [
        'title' => $label . ' | ' . AIT_NAME, 'description' => $label . ' at the ' . AIT_NAME . '.', 'nav' => $label, 'type' => 'WebPage',
    ];
    ait_start_secure_session();
    $nonce = ait_bootstrap_security();
    $GLOBALS['ait_public_nonce'] = $nonce;
    $canonical = ait_public_canonical_url($page);
    $og = ait_site_url() . '/assets/images/og/ait-campus-og.jpg';
    $asset = static fn(string $path): string => ait_e(ait_url($path));

    $head = [
        '<!doctype html><html lang="en-PK" dir="ltr"><head>',
        '<meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1">',
        '<title>' . ait_e($meta['title']) . '</title>',
        '<meta name="description" content="' . ait_e($meta['description']) . '">',
        '<meta name="robots" content="' . ($indexable ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow') . '">',
        '<link rel="canonical" href="' . ait_e($canonical) . '">',
        '<link rel="alternate" hreflang="en-PK" href="' . ait_e($canonical) . '">',
        '<link rel="alternate" hreflang="x-default" href="' . ait_e($canonical) . '">',
        '<meta name="theme-color" content="#0b7772">',
        '<meta name="csrf-token" content="' . ait_e(ait_init_csrf_token()) . '">',
        '<meta property="og:type" content="website">',
        '<meta property="og:locale" content="en_PK">',
        '<meta property="og:site_name" content="' . AIT_NAME . '">',
        '<meta property="og:title" content="' . ait_e($meta['title']) . '">',
        '<meta property="og:description" content="' . ait_e($meta['description']) . '">',
        '<meta property="og:url" content="' . ait_e($canonical) . '">',
        '<meta property="og:image" content="' . ait_e($og) . '">',
        '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">',
        '<meta property="og:image:alt" content="The main gate of the Ahmer Institute of Technology campus in Jamshoro">',
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="' . ait_e($meta['title']) . '">',
        '<meta name="twitter:description" content="' . ait_e($meta['description']) . '">',
        '<meta name="twitter:image" content="' . ait_e($og) . '">',
        '<link rel="icon" href="' . $asset('assets/images/favicon/ait.ico') . '" sizes="any">',
        '<link rel="icon" type="image/png" sizes="32x32" href="' . $asset('assets/images/icons/favicon-32.png') . '">',
        '<link rel="apple-touch-icon" href="' . $asset('assets/images/icons/apple-touch-icon.png') . '">',
        '<link rel="manifest" href="' . $asset('site.webmanifest') . '">',
        '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link rel="stylesheet" href="' . $asset('assets/css/bootstrap-icons.min.css') . '">',
        '<link rel="stylesheet" href="' . $asset('assets/css/public.css') . '">',
        $indexable ? '<script type="application/ld+json" nonce="' . ait_e($nonce) . '">' . ait_page_schema($page, $meta, $extraSchema) . '</script>' : '',
        '<script src="' . $asset('assets/js/theme.js') . '"></script>',
    ];
    if (in_array($page, ['home', 'student-dashboard'], true)) {
        $head[] = '<script src="' . $asset('assets/vendor/chart.umd.min.js') . '" defer></script>';
    }
    if ($page === 'contact') {
        $v3 = (string) ait_env('RECAPTCHA_V3_SITE_KEY', '');
        $v2 = (string) ait_env('RECAPTCHA_V2_SITE_KEY', '');
        if ($v3 !== '') {
            $head[] = '<script src="https://www.google.com/recaptcha/api.js?render=' . rawurlencode($v3) . '" defer></script>';
        } elseif ($v2 !== '') {
            $head[] = '<script src="https://www.google.com/recaptcha/api.js" defer></script>';
        }
    }
    $head[] = '</head><body>';
    echo implode('', $head);

    $nav = ['about' => 'About', 'programs' => 'Programs', 'admissions' => 'Admissions', 'departments' => 'Departments', 'campus-life' => 'Campus life', 'contact' => 'Contact'];
    $ribbon = ait_site_data('admissions_ribbon', ait_intake() . ' admissions are open');
    echo '<a class="skip-link" href="#main">Skip to content</a>';
    echo '<div class="announcement" role="region" aria-label="Admissions notice"><span class="pulse" aria-hidden="true"></span> ' . ait_e($ribbon) . ' <a href="' . ait_e(ait_url('admissions')) . '">See dates and fees <span aria-hidden="true">↗</span></a></div>';
    echo '<header class="site-header"><a class="brand" href="' . ait_e(ait_url('')) . '" aria-label="' . AIT_NAME . ' home"><img class="brand-logo" src="' . $asset('assets/images/logo/ait-logo-240.webp') . '" width="40" height="40" alt=""><span>Ahmer Institute <b>of Technology</b></span></a>';
    echo '<button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="site-nav">☰</button><nav class="site-nav" id="site-nav" aria-label="Main">';
    foreach ($nav as $key => $label) {
        echo '<a' . ($page === $key ? ' class="active" aria-current="page"' : '') . ' href="' . ait_e(ait_url($key)) . '">' . ait_e($label) . '</a>';
    }
    echo '</nav><button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle colour theme"><span data-theme-icon aria-hidden="true">◐</span><span data-theme-label>Dark mode</span></button><a class="nav-apply" href="' . ait_e(ait_url('login')) . '">Apply online <span aria-hidden="true">↗</span></a></header>';
}

function ait_breadcrumbs(string $page): string
{
    $meta = ait_pages()[$page] ?? null;
    if ($meta === null || $page === 'home') {
        return '';
    }
    return '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="' . ait_e(ait_url('')) . '">Home</a></li><li aria-current="page">' . ait_e($meta['nav']) . '</li></ol></nav>';
}

function ait_public_footer(): void
{
    $nonce = ait_e((string) ($GLOBALS['ait_public_nonce'] ?? ''));
    $c = ait_contact();
    $u = static fn(string $p): string => ait_e(ait_url($p));
    echo '<footer class="site-footer"><div class="footer-intro"><a class="brand footer-brand" href="' . $u('') . '"><img class="brand-logo" src="' . $u('assets/images/logo/ait-logo-240.webp') . '" width="40" height="40" alt="" loading="lazy"><span>Ahmer Institute <b>of Technology</b></span></a>';
    echo '<p>' . ait_e($c['street'] . ', ' . $c['city'] . ', ' . $c['region'] . ' ' . $c['postal']) . '<br><a href="mailto:' . ait_e($c['email']) . '">' . ait_e($c['email']) . '</a>' . ($c['phone'] !== '' ? '<br><a href="tel:' . ait_e(preg_replace('/[^\d+]/', '', $c['phone'])) . '">' . ait_e($c['phone']) . '</a>' : '') . '</p>';
    echo '<div class="footer-status"><span class="pulse" aria-hidden="true"></span> ' . ait_e(ait_intake()) . ' admissions are open</div>';
    echo '<div class="social-links" aria-label="AIT online"><a href="https://ahmershah.dev/" target="_blank" rel="noopener noreferrer" aria-label="Project website"><i class="bi bi-globe2" aria-hidden="true"></i></a><a href="https://www.facebook.com/ahmershahdev" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a><a href="https://github.com/ahmershahdev/Ahmer-Institute-of-Technology" target="_blank" rel="noopener noreferrer" aria-label="Source code on GitHub"><i class="bi bi-github" aria-hidden="true"></i></a><a href="https://linkedin.com/in/syedahmershah" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="bi bi-linkedin" aria-hidden="true"></i></a></div></div>';
    $columns = [
        'Study' => ['programs' => 'Programs', 'admissions' => 'Admissions', 'departments' => 'Departments', 'faq' => 'Admissions FAQ'],
        'Campus' => ['about' => 'About AIT', 'campus-life' => 'Campus life', 'faculty' => 'Faculty', 'students' => 'Student services'],
        'Connect' => ['alumini' => 'Alumni', 'career' => 'Careers', 'contact' => 'Contact', 'privacy-policy' => 'Privacy policy', 'terms-of-service' => 'Terms of use'],
    ];
    foreach ($columns as $heading => $links) {
        echo '<div class="footer-column"><h2>' . ait_e($heading) . '</h2>';
        foreach ($links as $slug => $label) {
            echo '<a href="' . $u($slug) . '">' . ait_e($label) . '</a>';
        }
        echo '</div>';
    }
    echo '<div class="footer-bottom"><span>© ' . date('Y') . ' Ahmer Institute of Technology · ' . ait_e($c['city']) . ', ' . ait_e($c['region']) . '. A portfolio project: programs, people and figures are illustrative.</span>';
    echo '<span class="footer-portals"><a href="' . $u('student-login') . '">Student portal</a><a href="' . $u('teachers/login.php') . '">Teacher portal</a><a href="' . $u('staff/login.php') . '">Staff portal</a></span></div></footer>';
    echo '<script src="' . $u('assets/js/public.js') . '" nonce="' . $nonce . '"></script></body></html>';
}

function ait_section_heading(string $eyebrow, string $title, string $intro = ''): string
{
    return '<div class="section-heading"><div><p class="eyebrow">' . ait_e($eyebrow) . '</p><h2>' . ait_e($title) . '</h2></div>' . ($intro !== '' ? '<p>' . ait_e($intro) . '</p>' : '') . '</div>';
}

function ait_cards(array $cards, string $class = 'feature-grid'): string
{
    $html = '<div class="' . $class . '">';
    foreach ($cards as $i => $card) {
        $html .= '<article class="feature-card reveal">' . (isset($card['icon']) ? '<span class="card-icon" aria-hidden="true"><i class="bi bi-' . ait_e($card['icon']) . '"></i></span>' : '<span class="card-number">' . sprintf('%02d', $i + 1) . '</span>')
            . '<h3>' . ait_e($card['title']) . '</h3><p>' . nl2br(ait_e($card['text'])) . '</p>'
            . (isset($card['link']) ? '<a class="card-link" href="' . ait_e(ait_url($card['link'][0])) . '">' . ait_e($card['link'][1]) . ' <span aria-hidden="true">↗</span></a>' : '') . '</article>';
    }
    return $html . '</div>';
}

function ait_figure(string $src, string $alt, int $w, int $h, string $caption = '', bool $eager = false): string
{
    $srcset = '';
    if (str_contains($src, 'main-gate-')) {
        $srcset = ' srcset="' . ait_e(ait_url('assets/images/campus/main-gate-480.webp')) . ' 480w, ' . ait_e(ait_url('assets/images/campus/main-gate-960.webp')) . ' 960w, ' . ait_e(ait_url('assets/images/campus/main-gate-1536.webp')) . ' 1536w" sizes="(max-width: 860px) 100vw, 50vw"';
    }
    return '<figure class="media-figure"><img src="' . ait_e(ait_url($src)) . '"' . $srcset . ' width="' . $w . '" height="' . $h . '" alt="' . ait_e($alt) . '" ' . ($eager ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"') . '>' . ($caption !== '' ? '<figcaption>' . ait_e($caption) . '</figcaption>' : '') . '</figure>';
}

function ait_table(array $head, array $rows, string $caption): string
{
    $html = '<div class="table-wrap"><table class="data-table"><caption>' . ait_e($caption) . '</caption><thead><tr>';
    foreach ($head as $cell) {
        $html .= '<th scope="col">' . ait_e($cell) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach (array_values($row) as $i => $cell) {
            $html .= $i === 0 ? '<th scope="row">' . ait_e($cell) . '</th>' : '<td>' . ait_e($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function ait_prose(array $blocks): string
{
    $html = '<div class="prose">';
    foreach ($blocks as $heading => $paragraphs) {
        $html .= '<h2>' . ait_e($heading) . '</h2>';
        foreach ((array) $paragraphs as $paragraph) {
            $html .= is_array($paragraph)
                ? '<ul>' . implode('', array_map(static fn(string $li): string => '<li>' . ait_e($li) . '</li>', $paragraph)) . '</ul>'
                : '<p>' . ait_e($paragraph) . '</p>';
        }
    }
    return $html . '</div>';
}

function ait_inner_hero(string $page, string $actions = '', string $media = ''): string
{
    $meta = ait_pages()[$page];
    return '<section class="inner-hero' . ($media !== '' ? ' has-media' : '') . '"><div class="inner-copy">' . ait_breadcrumbs($page)
        . '<p class="eyebrow">' . ait_e($meta['eyebrow']) . '</p><h1>' . ait_e($meta['h1']) . '</h1><p class="lede">' . ait_e($meta['lede']) . '</p>'
        . ($actions !== '' ? '<div class="hero-actions">' . $actions . '</div>' : '') . '</div>' . $media . '</section>';
}

function ait_button(string $href, string $label, bool $primary = true): string
{
    return $primary
        ? '<a class="button button-coral" href="' . ait_e(ait_url($href)) . '">' . ait_e($label) . ' <span aria-hidden="true">↗</span></a>'
        : '<a class="text-link" href="' . ait_e(ait_url($href)) . '">' . ait_e($label) . ' <span aria-hidden="true">↗</span></a>';
}

function ait_related(array $links, string $title = 'Keep exploring'): string
{
    $html = '<section class="content-band related-band" aria-labelledby="related-title"><p class="eyebrow">Next steps</p><h2 id="related-title">' . ait_e($title) . '</h2><div class="related-links">';
    foreach ($links as $slug => $label) {
        $html .= '<a href="' . ait_e(ait_url($slug)) . '">' . ait_e($label) . ' <span aria-hidden="true">↗</span></a>';
    }
    return $html . '</div></section>';
}

function ait_render_public_page(string $page): void
{
    ait_start_secure_session();
    if (ait_page_cache_serve($page)) {
        return;
    }
    $renderers = [
        'home' => 'ait_body_home', 'about' => 'ait_body_about', 'programs' => 'ait_body_programs',
        'admissions' => 'ait_body_admissions', 'departments' => 'ait_body_departments', 'faculty' => 'ait_body_faculty',
        'campus-life' => 'ait_body_campus_life', 'students' => 'ait_body_students', 'alumini' => 'ait_body_alumni',
        'career' => 'ait_body_career', 'faq' => 'ait_body_faq', 'privacy-policy' => 'ait_body_privacy', 'terms-of-service' => 'ait_body_terms',
    ];
    $renderer = $renderers[$page] ?? null;
    if ($renderer === null) {
        http_response_code(404);
        require dirname(__DIR__) . '/errors/404.php';
        return;
    }
    ob_start();
    [$body, $schema] = $renderer();
    ait_public_header($page, $schema);
    echo '<main id="main">' . $body . '</main>';
    ait_public_footer();
    $html = (string) ob_get_clean();
    header('X-Cache: MISS');
    ait_page_cache_store($page, $html);
    echo $html;
}

function ait_render_inner_page(string $page): void
{
    ait_render_public_page($page);
}

function ait_render_programs(): void
{
    ait_render_public_page('programs');
}

function ait_render_admissions(): void
{
    ait_render_public_page('admissions');
}

function ait_render_faculty(): void
{
    ait_render_public_page('faculty');
}

function ait_render_departments(): void
{
    ait_render_public_page('departments');
}

function ait_render_campus_life(): void
{
    ait_render_public_page('campus-life');
}

function ait_body_home(): array
{
    $programs = ait_catalog_programs();
    $intake = ait_intake();
    $eyebrow = ait_site_data('home_eyebrow', 'Ahmer Institute of Technology, Jamshoro');
    $headline = ait_site_data('home_headline', 'Learn engineering by building things that');
    $intro = ait_site_data('home_intro', 'Fifteen bachelor programs in computing, engineering, science, architecture and business, taught in small cohorts with lab work in every semester.');

    $html = '<section class="hero"><div class="hero-copy"><p class="eyebrow">' . ait_e($eyebrow) . '</p><h1>' . ait_e($headline) . ' <em data-typing=\'["work.","matter.","last."]\'>work.</em></h1><p class="lede">' . ait_e($intro) . '</p>'
        . '<div class="hero-actions">' . ait_button('login', 'Apply for ' . $intake) . ait_button('programs', 'Browse programs', false) . '</div></div>'
        . '<div class="hero-photo">' . ait_figure('assets/images/campus/main-gate-960.webp', 'Students arriving at the AIT main gate in Jamshoro', 960, 640, '', true) . '<div class="art-label"><strong>' . ait_e($intake) . ' intake</strong><br>Online applications are open.</div></div></section>';

    $html .= '<section class="section" aria-labelledby="glance-title"><div class="section-heading"><div><p class="eyebrow">AIT at a glance</p><h2 id="glance-title">A focused campus with room to grow.</h2></div><p>Numbers from the current academic year.</p></div><div class="kpi-grid">'
        . '<div class="kpi"><strong>15</strong><span>bachelor programs across five faculties</span></div>'
        . '<div class="kpi"><strong>1:18</strong><span>teacher-to-student ratio in core courses</span></div>'
        . '<div class="kpi"><strong>42</strong><span>teaching and research labs on campus</span></div>'
        . '<div class="kpi"><strong>8</strong><span>semesters with a lab or studio component</span></div></div></section>';

    $html .= '<section class="split-band"><div><p class="eyebrow">Applications by year</p><h2>More students choose AIT every year.</h2><p>Applications have grown steadily since the first intake. Admission stays merit based: entry test, intermediate, and matric results decide every seat.</p>' . ait_button('admissions', 'See the merit formula') . '</div>'
        . '<div class="chart"><div class="chart-canvas"><canvas id="admissionsChart" role="img" aria-label="Bar chart of applications received from 2022 to 2026, rising every year"></canvas></div><div class="chart-label"><span>Applications received</span><span>2022 to 2026</span></div></div></section>';

    $html .= '<section class="section">' . ait_section_heading('Programs', 'Start with a program that fits.', 'A few of our most requested degrees. All fifteen are listed on the programs page.') . '<div class="home-cards">';
    foreach (array_slice($programs, 0, 3) as $program) {
        $detail = ait_program_detail($program['code']);
        $html .= '<article class="feature-card reveal"><span class="card-number">' . ait_e($program['code']) . '</span><h3>' . ait_e($program['degree_level'] . ' ' . $program['name']) . '</h3><p>' . ait_e($detail['focus']) . '</p><p class="card-meta">' . ait_e($program['faculty']) . ' · ' . ait_e((string) (float) ($program['duration_years'] ?: 4)) . ' years · ' . $detail['credits'] . ' credit hours</p></article>';
    }
    $html .= '</div><p class="section-more">' . ait_button('programs', 'View all 15 programs', false) . '</p></section>';

    $html .= '<section class="section">' . ait_section_heading('Campus', 'Where the work happens.', 'Labs, studios, a library that opens early, and grounds for cricket and football.') . '<div class="gallery-grid">';
    foreach ([['labs', 'Computing labs', 'Open until 9 pm on weekdays for project work.'], ['studio', 'Design studios', 'Architecture and civil students draft and model here.'], ['research', 'Applied research', 'Solar, water, and data projects with local partners.']] as [$img, $title, $text]) {
        $html .= '<figure class="gallery-card"><img src="' . ait_e(ait_url('assets/images/illustrations/' . $img . '.svg')) . '" width="640" height="400" alt="" loading="lazy" decoding="async"><figcaption><strong>' . ait_e($title) . '</strong><span>' . ait_e($text) . '</span></figcaption></figure>';
    }
    $html .= '</div></section>';

    $html .= '<section class="section news-section">' . ait_section_heading('News', 'Recent updates from campus.') . '<div class="news-grid">'
        . '<article class="news-card"><time class="news-date" datetime="2026-10-12">12 Oct 2026 · Admissions</time><h3>' . ait_e($intake) . ' applications open on 1 November.</h3><p>The online form, eligibility rules, and fee schedule for the next intake are now published.</p><a href="' . ait_e(ait_url('admissions')) . '">Read the admissions guide <span aria-hidden="true">↗</span></a></article>'
        . '<article class="news-card"><time class="news-date" datetime="2026-09-28">28 Sep 2026 · Research</time><h3>Solar monitoring project goes live in two Jamshoro schools.</h3><p>Electrical and data science students installed sensors that report energy use to an open dashboard.</p><a href="' . ait_e(ait_url('departments')) . '">Meet the departments <span aria-hidden="true">↗</span></a></article>'
        . '<article class="news-card"><time class="news-date" datetime="2026-09-04">4 Sep 2026 · Campus</time><h3>Twenty-two societies sign up members at the fair.</h3><p>Robotics, debating, photography, and the new climate action society drew the longest queues.</p><a href="' . ait_e(ait_url('campus-life')) . '">See campus life <span aria-hidden="true">↗</span></a></article>'
        . '</div></section>';

    $html .= '<section class="section cta-band"><div><p class="eyebrow">Ready to apply?</p><h2>Your application starts with one account.</h2><p>Create an account, save your progress, and submit when your documents are ready.</p></div>' . ait_button('registration', 'Create an applicant account') . '</section>';

    return [$html, []];
}

function ait_body_about(): array
{
    $meta = ait_pages()['about'];
    $html = ait_inner_hero('about', ait_button('programs', 'See our programs') . ait_button('contact', 'Plan a visit', false), ait_figure($meta['image']['src'], $meta['image']['alt'], 960, 640, '', true));
    $html .= '<section class="content-band">' . ait_section_heading('Mission', 'What we are here to do.') . ait_cards([
        ['icon' => 'tools', 'title' => 'Teach through practice', 'text' => 'Every semester includes lab, studio, or field work. Students graduate with a portfolio of things they have built, tested, and explained.'],
        ['icon' => 'people', 'title' => 'Keep classes small', 'text' => 'Core courses run with about eighteen students per teacher, so feedback is specific and nobody disappears at the back of the room.'],
        ['icon' => 'geo-alt', 'title' => 'Work on local problems', 'text' => 'Final-year projects often start with a real need in Sindh: water quality, energy use, traffic, or small business software.'],
    ]) . '</section>';
    $html .= '<section class="content-band split-content">' . ait_figure('assets/images/illustrations/library.svg', 'Reading hall in the AIT library with tall shelves and study desks', 640, 400)
        . '<div>' . ait_prose([
            'How AIT is organised' => [
                'The institute has five faculties and fifteen departments. A Board of Governors approves budgets and policy, an Academic Council approves curricula and examination rules, and each department runs its own board of studies with industry members.',
                'Quality is monitored by an internal Quality Enhancement Cell that surveys students every semester and reviews each program against its stated learning outcomes.',
            ],
            'Our values' => [[
                'Honesty in assessment: marks are published with the rubric used to award them.',
                'Access: need-based fee support for students who qualify on merit.',
                'Safety: a campus where everyone can study and speak without fear.',
                'Care for the environment in how we build, travel, and teach.',
            ]],
        ]) . '</div></section>';
    $html .= '<section class="metrics-band"><div><span class="metric-value">5</span><span>faculties</span></div><div><span class="metric-value">15</span><span>departments</span></div><div><span class="metric-value">42</span><span>labs and studios</span></div></section>';
    $html .= ait_related(['programs' => 'Compare programs', 'departments' => 'Browse departments', 'admissions' => 'Read the admissions guide']);
    return [$html, []];
}

function ait_body_programs(): array
{
    $programs = ait_catalog_programs();
    $html = ait_inner_hero('programs', ait_button('admissions', 'How to apply') . ait_button('faq', 'Admissions FAQ', false));
    $html .= '<section class="content-band">' . ait_section_heading('Catalogue', 'All undergraduate programs.', 'Seats are for the ' . ait_intake() . ' intake. Credit hours follow HEC guidelines for each discipline.') . '<div class="program-grid">';
    $items = [];
    foreach ($programs as $i => $program) {
        $detail = ait_program_detail($program['code']);
        $years = (string) (float) ($program['duration_years'] ?: 4);
        $html .= '<article class="program-card reveal" id="program-' . ait_e(strtolower($program['code'])) . '"><div class="program-top"><span>' . ait_e($program['code']) . '</span><span>' . ait_e($program['degree_level']) . '</span></div><h3>' . ait_e($program['degree_level'] . ' ' . $program['name']) . '</h3><p>' . ait_e($detail['focus']) . '</p>'
            . '<dl class="program-facts"><div><dt>Faculty</dt><dd>' . ait_e($program['faculty']) . '</dd></div><div><dt>Duration</dt><dd>' . ait_e($years) . ' years</dd></div><div><dt>Credit hours</dt><dd>' . $detail['credits'] . '</dd></div><div><dt>Seats</dt><dd>' . $detail['seats'] . '</dd></div></dl>'
            . '<footer><span>Morning and evening</span><a href="' . ait_e(ait_url('login')) . '">Apply <span aria-hidden="true">↗</span></a></footer></article>';
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => [
            '@type' => 'EducationalOccupationalProgram',
            'name' => $program['degree_level'] . ' ' . $program['name'],
            'description' => $detail['focus'],
            'url' => ait_public_canonical_url('programs') . '#program-' . strtolower($program['code']),
            'educationalCredentialAwarded' => 'Bachelor of Science',
            'timeToComplete' => 'P' . (int) round((float) $years) . 'Y',
            'numberOfCredits' => $detail['credits'],
            'occupationalCategory' => $program['faculty'],
            'provider' => ['@id' => ait_site_url() . '/#organization'],
        ]];
    }
    $html .= '</div></section>';
    $html .= ait_related(['admissions' => 'Check eligibility', 'departments' => 'Meet the departments', 'contact' => 'Ask the admissions office'], 'Found a program you like?');
    return [$html, [['@type' => 'ItemList', 'name' => 'Undergraduate programs at AIT', 'numberOfItems' => count($items), 'itemListElement' => $items]]];
}

function ait_body_admissions(): array
{
    $intake = ait_intake();
    $html = ait_inner_hero('admissions', ait_button('registration', 'Create an account') . ait_button('login', 'I already have an account', false));
    $html .= '<section class="content-band">' . ait_section_heading('Process', 'Applying in five steps.', 'You can save your progress and come back. Nothing is final until you press submit.') . '<ol class="process-grid">'
        . '<li><span>01</span><h3>Create an account</h3><p>Register with your own email and CNIC. You will use this account until you graduate.</p></li>'
        . '<li><span>02</span><h3>Fill in the form</h3><p>Enter your matric and intermediate results and rank up to three programs.</p></li>'
        . '<li><span>03</span><h3>Upload documents</h3><p>Clear scans or photos of your marksheets, CNIC or B-Form, domicile, and a passport photo.</p></li>'
        . '<li><span>04</span><h3>Pay the processing fee</h3><p>Once approved, download the bank challan, pay at any HBL branch, and upload the stamped copy.</p></li>'
        . '<li><span>05</span><h3>Sit the entry test</h3><p>Your admit card appears in the portal with the test centre, date, and seat number.</p></li></ol></section>';
    $html .= '<section class="content-band">' . ait_section_heading('Key dates', $intake . ' admission schedule.') . ait_table(['Milestone', 'Date'], [
        ['Online applications open', '1 November 2026'], ['Last date to apply', '15 December 2026'], ['Admit cards issued', '5 January 2027'],
        ['Entry test', '12 January 2027'], ['First merit list', '26 January 2027'], ['Classes begin', '15 February 2027'],
    ], 'Dates may move if the provincial calendar changes; the portal always shows the current schedule.') . '</section>';
    $html .= '<section class="content-band two-col">' . '<div>' . ait_section_heading('Eligibility', 'Who can apply.') . ait_prose(['Minimum requirements' => [[
        'Engineering and computing: intermediate (Pre-Engineering or ICS) with at least 60% marks.',
        'Sciences, economics, and English: intermediate in any group with at least 50% marks.',
        'Business Administration: intermediate in any group with at least 50% marks.',
        'Architecture: intermediate with at least 60% marks and a pass in the drawing aptitude test.',
        'Students awaiting results may apply on the basis of their first-year (Part I) marks.',
    ]]]) . '</div><div>' . ait_section_heading('Merit', 'How merit is calculated.') . ait_table(['Component', 'Weight'], [['AIT entry test', '50%'], ['Intermediate (HSC)', '40%'], ['Matric (SSC)', '10%']], 'Merit is computed to two decimal places; ties go to the higher entry test score.') . '</div></section>';
    $html .= '<section class="content-band">' . ait_section_heading('Fees', 'What it costs.', 'Fees are charged per semester. Need-based support and merit scholarships are explained on the FAQ page.') . ait_table(['Item', 'Amount (PKR)', 'When'], [
        ['Application processing fee', '3,500', 'Once, after approval'], ['Tuition, engineering and computing', '50,000', 'Each semester'],
        ['Tuition, sciences and business', '42,000', 'Each semester'], ['Examination fee', '3,500', 'Each semester'], ['Hostel (optional)', '18,000', 'Each semester'],
    ], 'Fee schedule for the ' . $intake . ' intake.') . '</section>';
    $html .= ait_related(['programs' => 'Choose your programs', 'faq' => 'Read the admissions FAQ', 'contact' => 'Talk to the admissions office'], 'Ready to start your application?');
    return [$html, []];
}

function ait_body_departments(): array
{
    $html = ait_inner_hero('departments', ait_button('programs', 'See all programs'));
    $faculties = [
        ['Computing & Engineering', 'cpu', 'Computer Science, Software Engineering, Artificial Intelligence, Data Science, and Cyber Security.', 'labs', 'Twelve computing labs, a GPU cluster for AI coursework, and a security operations lab.'],
        ['Engineering', 'lightning-charge', 'Electrical Engineering and Mechanical Engineering.', 'research', 'Power, control, and machine shops, plus the solar test bed on the engineering block roof.'],
        ['Civil & Architecture', 'buildings', 'Civil Engineering, Environmental Engineering, and Architecture.', 'studio', 'Materials testing, surveying, water quality, and two architecture studios.'],
        ['Sciences & Humanities', 'calculator', 'Mathematics, Physics, English, and Economics.', 'library', 'Physics labs, a language lab, and the quantitative methods centre.'],
        ['Management Sciences', 'briefcase', 'Business Administration.', 'societies', 'Case rooms, a trading simulation lab, and the student enterprise incubator.'],
    ];
    $html .= '<section class="content-band">' . ait_section_heading('Faculties', 'Five faculties, fifteen departments.') . '<div class="department-grid">';
    foreach ($faculties as [$name, $icon, $departments, $img, $facilities]) {
        $html .= '<article class="department-card reveal"><img src="' . ait_e(ait_url('assets/images/illustrations/' . $img . '.svg')) . '" width="640" height="400" alt="" loading="lazy" decoding="async"><div class="department-card-body"><span class="department-icon" aria-hidden="true"><i class="bi bi-' . $icon . '"></i></span><h3>' . ait_e($name) . '</h3><p><strong>Departments:</strong> ' . ait_e($departments) . '</p><p><strong>Facilities:</strong> ' . ait_e($facilities) . '</p><a href="' . ait_e(ait_url('programs')) . '">View programs <span aria-hidden="true">↗</span></a></div></article>';
    }
    $html .= '</div></section>' . ait_related(['faculty' => 'Meet the faculty', 'programs' => 'Compare programs', 'contact' => 'Ask about a department']);
    return [$html, []];
}

function ait_body_faculty(): array
{
    $people = ait_cache_remember('public_faculty', 900, static function (): array {
        try {
            return ait_pdo()->query("SELECT t.teacher_code, t.name, t.designation, d.name AS department FROM teachers t LEFT JOIN departments d ON d.code = t.department_code WHERE t.is_active = 1 AND t.teacher_code IS NOT NULL ORDER BY FIELD(t.designation, 'Professor', 'Associate Professor', 'Assistant Professor', 'Lecturer'), t.name LIMIT 24")->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    });
    $html = ait_inner_hero('faculty', ait_button('departments', 'Browse departments'));
    $html .= '<section class="content-band">' . ait_section_heading('Teaching staff', 'Selected faculty profiles.', 'Profiles are published from the academic office records. Select a name to see the subjects that person teaches.') . '<div class="person-grid">';
    if ($people === []) {
        $html .= '<p>Faculty profiles will appear here once the academic office publishes them.</p>';
    }
    foreach ($people as $person) {
        $initials = implode('', array_map(static fn(string $part): string => mb_substr($part, 0, 1), array_slice(array_values(array_filter(explode(' ', preg_replace('/^(Dr|Engr|Prof|Mr|Ms|Mrs)\.?\s+/i', '', (string) $person['name'])))), 0, 2)));
        $html .= '<article class="person-card reveal"><span class="person-avatar" aria-hidden="true">' . ait_e(strtoupper($initials)) . '</span><div><h3><a href="' . ait_e(ait_url('teachers/' . $person['teacher_code'])) . '">' . ait_e($person['name']) . '</a></h3><p>' . ait_e(($person['designation'] ?: 'Lecturer') . ', ' . ($person['department'] ?: 'AIT')) . '</p></div></article>';
    }
    $html .= '</div></section><section class="metrics-band"><div><span class="metric-value">1:18</span><span>teacher-to-student ratio in core courses</span></div><div><span class="metric-value">6</span><span>office hours per teacher each week</span></div><div><span class="metric-value">5</span><span>faculties with research groups</span></div></section>';
    return [$html, []];
}

function ait_body_campus_life(): array
{
    $html = ait_inner_hero('campus-life', ait_button('contact', 'Book a campus visit') . ait_button('students', 'Student services', false), ait_figure('assets/images/illustrations/societies.svg', 'Students at the societies fair, with a robotics stall and a club banner', 640, 400, '', true));
    $html .= '<section class="content-band"><div class="gallery-grid">';
    foreach ([
        ['labs', 'Labs after hours', 'Computing and electronics labs stay open until 9 pm on weekdays with a lab assistant on duty.'],
        ['library', 'Central library', 'Around 40,000 print titles, digital journal access through the HEC library, and silent study rooms.'],
        ['sports', 'Sports grounds', 'A cricket ground, two football pitches, badminton courts, and an inter-department league each spring.'],
        ['studio', 'Studios', 'Architecture and design studios with drafting tables, a model shop, and a laser cutter.'],
        ['societies', 'Societies', 'Twenty-two student societies, from robotics and debating to photography and community service.'],
        ['research', 'Research projects', 'Undergraduates can join faculty projects on energy, water, and data from their third year.'],
    ] as [$img, $title, $text]) {
        $html .= '<figure class="gallery-card"><img src="' . ait_e(ait_url('assets/images/illustrations/' . $img . '.svg')) . '" width="640" height="400" alt="" loading="lazy" decoding="async"><figcaption><strong>' . ait_e($title) . '</strong><span>' . ait_e($text) . '</span></figcaption></figure>';
    }
    $html .= '</div></section>';
    $html .= '<section class="content-band">' . ait_section_heading('Living here', 'Practical things to know.') . ait_cards([
        ['icon' => 'house-door', 'title' => 'Hostels', 'text' => 'Separate hostels for men and women, 600 places in total, allotted on distance from home and merit. Rooms are shared by two or three students.'],
        ['icon' => 'bus-front', 'title' => 'Transport', 'text' => 'Morning and evening buses run on fixed routes from Hyderabad, Kotri, and Jamshoro town. Passes are bought each semester.'],
        ['icon' => 'heart-pulse', 'title' => 'Health and counselling', 'text' => 'A medical centre with a doctor on campus during class hours, and a confidential counselling service you can book online.'],
        ['icon' => 'cup-hot', 'title' => 'Food', 'text' => 'Two cafeterias and a tuck shop with fixed, subsidised prices for the main meals.'],
    ]) . '</section>';
    $html .= '<section class="campus-quote"><p class="eyebrow">From a student</p><blockquote>“I came for the degree. I stayed late for the robotics lab and the people in it.”</blockquote><span>Third-year Electrical Engineering student</span></section>';
    $html .= ait_related(['admissions' => 'Apply to AIT', 'students' => 'See student services', 'contact' => 'Plan a visit']);
    return [$html, []];
}

function ait_body_students(): array
{
    $html = ait_inner_hero('students', ait_button('student-login', 'Open the student portal'));
    $html .= '<section class="content-band">' . ait_section_heading('Support', 'Offices that can help.') . ait_cards([
        ['icon' => 'person-badge', 'title' => 'Academic advising', 'text' => 'Every student has a named faculty advisor for course selection, credit transfer, and repeat or improvement exams.'],
        ['icon' => 'cash-coin', 'title' => 'Scholarships and fee support', 'text' => 'Need-based support covers up to the full semester fee. Merit scholarships go to the top three students in each department every year.'],
        ['icon' => 'chat-heart', 'title' => 'Counselling', 'text' => 'Confidential one-to-one sessions with trained counsellors. Bookings are made privately through the student portal.'],
        ['icon' => 'universal-access', 'title' => 'Disability support', 'text' => 'Extra exam time, accessible seating, and assistive software, arranged with the student affairs office before each exam period.'],
    ]) . '</section>';
    $html .= '<section class="content-band">' . ait_section_heading('Student portal', 'What you can do online.') . ait_prose(['In the portal' => [[
        'See your subjects, timetable, and the teachers assigned to each class.',
        'Check attendance by subject; anything under 75% is flagged early.',
        'Read published marks and the rubric behind them.',
        'Download and upload semester fee and exam challans.',
        'Print your exam slip once your exam fee is verified.',
        'Report a problem directly to student services.',
    ]]]) . '</section>';
    $html .= ait_related(['student-login' => 'Sign in to the portal', 'campus-life' => 'Campus life', 'contact' => 'Contact student affairs']);
    return [$html, []];
}

function ait_body_alumni(): array
{
    $html = ait_inner_hero('alumini', ait_button('contact', 'Update your details'));
    $html .= '<section class="content-band">' . ait_section_heading('Get involved', 'Ways to stay connected.') . ait_cards([
        ['icon' => 'person-workspace', 'title' => 'Mentor a student', 'text' => 'Spend an hour a month with a final-year student who wants to enter your field. We match mentors by department and city.'],
        ['icon' => 'megaphone', 'title' => 'Share a job', 'text' => 'Send openings to the placement office and they reach every final-year student in the relevant department within a week.'],
        ['icon' => 'file-earmark-text', 'title' => 'Transcripts and verification', 'text' => 'Request transcripts, degree verification, or a character certificate through the registrar. Most requests take five working days.'],
        ['icon' => 'calendar-event', 'title' => 'Reunions and talks', 'text' => 'Each department hosts an alumni evening every winter, and many graduates come back to give guest lectures.'],
    ]) . '</section>' . ait_related(['career' => 'Hire AIT graduates', 'contact' => 'Contact the alumni office']);
    return [$html, []];
}

function ait_body_career(): array
{
    $html = ait_inner_hero('career', ait_button('contact', 'Contact the placement office'));
    $html .= '<section class="content-band">' . ait_section_heading('For students', 'How the placement office helps.') . ait_cards([
        ['icon' => 'file-person', 'title' => 'CV and interview clinics', 'text' => 'Weekly drop-in sessions to review CVs, cover letters, and LinkedIn profiles, plus mock interviews before the job fair.'],
        ['icon' => 'building', 'title' => 'Internships', 'text' => 'Every program includes a summer internship of at least six weeks. The office keeps a list of host companies and helps with applications.'],
        ['icon' => 'shop', 'title' => 'Annual job fair', 'text' => 'Held every March. Employers from software, construction, energy, banking, and the public sector interview on campus.'],
    ]) . '</section>';
    $html .= '<section class="content-band">' . ait_section_heading('Work at AIT', 'Open positions.') . ait_prose(['Faculty and staff vacancies' => [
        'Teaching and administrative positions are advertised in national newspapers and on this page. Applications are accepted by email with a CV, cover letter, and copies of degrees.',
        'AIT is an equal opportunity employer. Women and persons with disabilities are encouraged to apply.',
    ]]) . '</section>' . ait_related(['contact' => 'Send your CV', 'alumini' => 'Alumni network']);
    return [$html, []];
}

function ait_faq_items(): array
{
    $contact = ait_contact();
    return [
        ['How do I apply to AIT?', 'Create an applicant account, fill in the online form with your academic record, rank up to three programs, and upload your documents. You can save and return before you submit.'],
        ['Can I apply before my intermediate result is announced?', 'Yes. Apply with your first-year (Part I) marks. Your admission is confirmed only after you upload the final result.'],
        ['Which documents do I need?', 'A passport-size photo, your CNIC or B-Form, your father\'s CNIC, matric and intermediate marksheets, and your domicile. Images (JPG, PNG, WebP) up to 5 MB or PDFs up to 10 MB are accepted.'],
        ['How is merit calculated?', 'The entry test counts for 50%, intermediate marks for 40%, and matric marks for 10%. Ties are decided by the higher entry test score.'],
        ['How much is the fee?', 'The application processing fee is PKR 3,500. Tuition is PKR 50,000 per semester for engineering and computing, and PKR 42,000 for sciences and business.'],
        ['Is there financial support?', 'Yes. Need-based support can cover up to the full semester fee, and merit scholarships go to the top three students in each department each year.'],
        ['Are hostels available?', 'Yes, for both men and women. Places are allotted on distance from home and merit, and the hostel fee is PKR 18,000 per semester.'],
        ['How will I know my result?', 'Your application status, review notes, challan, and admit card all appear in the applicant portal. Merit lists are also posted on the admissions page.'],
        ['Who do I contact with a question?', 'Email ' . $contact['email'] . ' and include your CNIC number. The admissions office replies within two working days.'],
    ];
}

function ait_body_faq(): array
{
    $html = ait_inner_hero('faq', ait_button('admissions', 'Read the admissions guide') . ait_button('contact', 'Ask a question', false));
    $html .= '<section class="content-band"><div class="faq-list">';
    $entities = [];
    foreach (ait_faq_items() as $i => [$question, $answer]) {
        $html .= '<details class="faq-item"' . ($i === 0 ? ' open' : '') . '><summary><h2 class="faq-question">' . ait_e($question) . '</h2></summary><p>' . ait_e($answer) . '</p></details>';
        $entities[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
    }
    $html .= '</div></section>' . ait_related(['admissions' => 'Admissions guide', 'programs' => 'Programs', 'contact' => 'Contact admissions']);
    return [$html, [['@type' => 'FAQPage', 'mainEntity' => $entities]]];
}

function ait_body_privacy(): array
{
    $c = ait_contact();
    $html = ait_inner_hero('privacy-policy');
    $html .= '<section class="content-band">' . ait_prose([
        'What we collect' => [
            'When you apply: your name, father\'s name, CNIC, date of birth, contact details, academic results, program choices, and the documents you upload.',
            'When you use a portal: your sign-in history, the actions you take, and the IP address they came from. We record these to keep accounts secure.',
            'When you use the contact form: your name, email, and message.',
        ],
        'Why we use it' => [[
            'To assess your application and run the merit process.',
            'To create and manage your student record after admission.',
            'To reply to your messages.',
            'To protect the portals against misuse and investigate security incidents.',
        ]],
        'Who can see it' => [
            'Only AIT staff whose role requires it. Admission reviewers see applications; teachers see the students in their own classes; finance staff see fee records. Access is logged.',
            'We do not sell personal data. We share it only when the law requires, for example with the Higher Education Commission for degree verification.',
        ],
        'How long we keep it' => [
            'Applications that do not lead to admission are deleted two years after the intake closes. Student records are kept permanently because degrees must remain verifiable.',
        ],
        'Cookies' => [
            'The site uses one session cookie to keep you signed in and protect forms. It expires when you close the browser. We do not use advertising or tracking cookies. Your light or dark theme choice is stored in your browser only.',
        ],
        'Your rights' => [
            'You can ask for a copy of your data, ask us to correct it, or ask us to delete an application you no longer want considered. Email ' . $c['email'] . ' from the address on your account.',
        ],
    ]) . '<p class="updated">Last updated: 26 October 2026</p></section>';
    return [$html, []];
}

function ait_body_terms(): array
{
    $html = ait_inner_hero('terms-of-service');
    $html .= '<section class="content-band">' . ait_prose([
        'Your account' => [
            'Use your own name, email, and CNIC. One person may hold one applicant account. Keep your password private; you are responsible for anything done with your account.',
        ],
        'Accurate information' => [
            'Everything you submit must be true and complete. Admission is cancelled, at any stage, if documents are found to be false or altered.',
        ],
        'Uploaded documents' => [
            'Upload only your own documents, as images (JPG, PNG, WebP) or PDF. Files are virus scanned, and images are converted on our servers. Files that fail the checks are rejected.',
        ],
        'Acceptable use' => [[
            'Do not try to access another person\'s account or data.',
            'Do not probe, scan, or overload the website or portals.',
            'Do not upload malicious files or content that is unlawful or abusive.',
            'Report security issues responsibly using the process in our security policy.',
        ]],
        'Admission decisions' => [
            'Decisions follow the published eligibility rules and merit formula. Applicants may request a review of their merit calculation within seven days of a merit list being published.',
        ],
        'Changes' => [
            'We may update these terms. Material changes are announced on this page and in the portals before they take effect.',
        ],
    ]) . '<p class="updated">Last updated: 26 October 2026</p></section>';
    return [$html, []];
}

function ait_contact_submission(): array
{
    $state = ['success' => false, 'message' => ''];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $state;
    }
    ait_validate_csrf_post();
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    ait_rate_limit('public-contact-form', 5, 900, is_string($email) ? $email : null);

    $name = trim((string) ($_POST['name'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $botCheck = ait_captcha_verify('contact_form');
    if ($botCheck !== null) {
        return ['success' => false, 'message' => $botCheck];
    }
    if ($name === '' || !$email || $subject === '' || $message === '') {
        return ['success' => false, 'message' => 'Please fill in your name, a valid email, a subject, and your message.'];
    }
    if (mb_strlen($name) > 120 || mb_strlen($subject) > 180 || mb_strlen($message) > 4000 || mb_strlen($message) < 10) {
        return ['success' => false, 'message' => 'Please keep the message between 10 and 4,000 characters.'];
    }
    if (ait_is_disposable_email((string) $email)) {
        return ['success' => false, 'message' => 'Please use a permanent email address so we can reply.'];
    }

    try {
        $stmt = ait_pdo()->prepare('INSERT INTO contact_messages (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, $subject, $message, ait_client_ip()]);
        return ['success' => true, 'message' => 'Thank you, ' . $name . '. Your message has reached the admissions office and we will reply within two working days.'];
    } catch (Throwable $exception) {
        error_log('Contact form save failed: ' . $exception->getMessage());
        return ['success' => false, 'message' => 'We could not save your message right now. Please email ' . ait_contact()['email'] . ' instead.'];
    }
}

function ait_render_contact(): void
{
    ait_start_secure_session();
    $state = ait_contact_submission();
    $c = ait_contact();
    ait_public_header('contact');
    $mapBox = sprintf('%.4f%%2C%.4f%%2C%.4f%%2C%.4f', $c['lng'] - 0.03, $c['lat'] - 0.02, $c['lng'] + 0.03, $c['lat'] + 0.02);
    $marker = sprintf('%.4f%%2C%.4f', $c['lat'], $c['lng']);
    echo '<main id="main">' . ait_inner_hero('contact');
    echo '<section class="content-band contact-grid"><div class="contact-form-panel"><p class="eyebrow">Send a message</p><h2>Write to the admissions office.</h2>';
    if ($state['message'] !== '') {
        echo '<div class="form-alert ' . ($state['success'] ? 'is-success' : 'is-error') . '" role="' . ($state['success'] ? 'status' : 'alert') . '">' . ait_e($state['message']) . '</div>';
    }
    $old = static fn(string $key): string => $state['success'] ? '' : ait_e((string) ($_POST[$key] ?? ''));
    echo '<form method="post" action="' . ait_e(ait_url('contact')) . '" novalidate>' . ait_csrf_field()
        . '<div class="form-row"><label>Your name<input type="text" name="name" maxlength="120" required autocomplete="name" placeholder="e.g. Ayesha Memon" value="' . $old('name') . '"></label>'
        . '<label>Email address<input type="email" name="email" maxlength="190" required autocomplete="email" placeholder="you@example.com" value="' . $old('email') . '"></label></div>'
        . '<label>Subject<input type="text" name="subject" maxlength="180" required placeholder="e.g. Eligibility for BS Computer Science" value="' . $old('subject') . '"></label>'
        . '<label>Message<textarea name="message" rows="6" minlength="10" maxlength="4000" required placeholder="Include your CNIC number if you have already applied.">' . $old('message') . '</textarea><small class="field-hint">10 to 4,000 characters. We reply within two working days.</small></label>'
        . ait_captcha_field('contact_form')
        . '<button class="button button-coral" type="submit">Send message <span aria-hidden="true">↗</span></button></form></div>';
    echo '<div class="contact-map-panel"><div class="map-heading"><h2>Visit the campus</h2><address>' . AIT_NAME . '<br>' . ait_e($c['street']) . ', ' . ait_e($c['city']) . '<br>' . ait_e($c['region']) . ' ' . ait_e($c['postal']) . ', Pakistan</address>'
        . '<p><strong>Office hours:</strong> ' . ait_e($c['hours']) . '<br><strong>Email:</strong> <a href="mailto:' . ait_e($c['email']) . '">' . ait_e($c['email']) . '</a>' . ($c['phone'] !== '' ? '<br><strong>Phone:</strong> <a href="tel:' . ait_e(preg_replace('/[^\d+]/', '', $c['phone'])) . '">' . ait_e($c['phone']) . '</a>' : '') . '</p>'
        . '<a class="map-directions-link" href="https://www.openstreetmap.org/?mlat=' . $c['lat'] . '&amp;mlon=' . $c['lng'] . '#map=15/' . $c['lat'] . '/' . $c['lng'] . '" target="_blank" rel="noopener noreferrer">Open in OpenStreetMap <span aria-hidden="true">↗</span></a></div>'
        . '<div class="map-frame"><iframe title="Map showing the AIT campus in ' . ait_e($c['city']) . '" src="https://www.openstreetmap.org/export/embed.html?bbox=' . $mapBox . '&amp;layer=mapnik&amp;marker=' . $marker . '" loading="lazy" referrerpolicy="no-referrer"></iframe></div></div></section></main>';
    ait_public_footer();
}

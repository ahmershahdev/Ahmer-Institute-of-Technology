<?php

declare(strict_types=1);

require_once __DIR__ . '/pdo.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';

function ait_site_data(string $key, string $fallback = ''): string
{
    try {
        $stmt = ait_pdo()->prepare('SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $fallback : (string) $value;
    } catch (Throwable $exception) {
        return $fallback;
    }
}

function ait_catalog_programs(): array
{
    $fallback = [
        ['name' => 'Artificial Intelligence', 'code' => 'AI', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Computing & Engineering'],
        ['name' => 'Computer Science', 'code' => 'CS', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Computing & Engineering'],
        ['name' => 'Electrical Engineering', 'code' => 'EE', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Engineering'],
        ['name' => 'Mechanical Engineering', 'code' => 'ME', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Engineering'],
        ['name' => 'Software Engineering', 'code' => 'SE', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Computing & Engineering'],
        ['name' => 'Civil Engineering', 'code' => 'CE', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Civil & Architecture'],
        ['name' => 'Mathematics', 'code' => 'MATH', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Sciences & Humanities'],
        ['name' => 'English', 'code' => 'ENG', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Sciences & Humanities'],
        ['name' => 'Physics', 'code' => 'PHY', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Sciences & Humanities'],
        ['name' => 'Cyber Security', 'code' => 'CYBER', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Computing & Engineering'],
        ['name' => 'Data Science', 'code' => 'DS', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Computing & Engineering'],
        ['name' => 'Architecture', 'code' => 'ARCH', 'degree_level' => 'BS', 'duration_years' => '5.0', 'faculty' => 'Civil & Architecture'],
        ['name' => 'Environmental Engineering', 'code' => 'ENV', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Civil & Architecture'],
        ['name' => 'Economics', 'code' => 'ECO', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Sciences & Humanities'],
        ['name' => 'Business Administration', 'code' => 'BBA', 'degree_level' => 'BS', 'duration_years' => '4.0', 'faculty' => 'Management Sciences'],
    ];
    try {
        $rows = ait_pdo()->query("SELECT p.name, p.code, p.degree_level, p.duration_years, COALESCE(f.name, 'AIT Academic School') AS faculty FROM programs p LEFT JOIN faculties f ON f.id = p.faculty_id WHERE p.is_active = 1 ORDER BY p.name")->fetchAll();
        if ($rows) {
            $existing = array_map(static fn(array $row): string => strtolower($row['name']), $rows);
            foreach ($fallback as $program) {
                if (!in_array(strtolower($program['name']), $existing, true)) $rows[] = $program;
            }
            return $rows;
        }
    } catch (Throwable $exception) { /* Demo catalog keeps public pages available before import. */
    }
    return $fallback;
}

function ait_page_config(string $page): array
{
    $configs = [
        'about' => ['eyebrow' => 'The AIT difference', 'title' => 'A university built for useful ambition.', 'intro' => 'Ahmer Institute for Technology brings rigorous learning, human mentorship, and industry-grade practice into one energetic academic community.', 'image' => 'assets/images/home/home-slide-2.svg', 'label' => 'About AIT', 'cards' => [['title' => 'Learn by making', 'text' => 'Studios, labs, fieldwork, and capstones turn curiosity into a body of work you can take into the world.'], ['title' => 'Known by name', 'text' => 'Small cohorts and accessible faculty create a campus where feedback is specific and progress is visible.'], ['title' => 'Rooted here, ready anywhere', 'text' => 'Our programs pair local relevance with the technical fluency expected by a connected global economy.']]],
        'contact' => ['eyebrow' => 'Come say hello', 'title' => 'Your next conversation starts here.', 'intro' => 'Ask about programs, visit the campus, or connect with the team that will guide your application.', 'image' => 'assets/images/home/home-slide-3.svg', 'label' => 'Contact AIT', 'cards' => [['title' => 'Admissions desk', 'text' => "admissions@ait.edu.pk\n+92 300 555 0110"], ['title' => 'Visit the campus', 'text' => "Main Campus, University Road\nJamshoro, Sindh"], ['title' => 'Office hours', 'text' => "Monday - Friday\n08:30 - 16:30"]]],
        'faq' => ['eyebrow' => 'Answers, without the runaround', 'title' => 'Frequently asked questions.', 'intro' => 'Everything you need to move from first question to submitted application with confidence.', 'image' => 'assets/images/home/home-slide-1.svg', 'label' => 'Help centre', 'cards' => [['title' => 'How do I apply?', 'text' => 'Create a student account, choose your program, complete the online form, and upload the required documents.'], ['title' => 'Can I choose more than one program?', 'text' => 'Yes. The application lets you rank up to three program preferences for the current admission cycle.'], ['title' => 'How do I track my application?', 'text' => 'Sign in to the student portal at any time to view review notes, challan status, and test-slip availability.'], ['title' => 'Where can I get help?', 'text' => 'Email admissions@ait.edu.pk or call +92 300 555 0110 during office hours.']]],
        'admissions' => ['eyebrow' => 'Fall 2026 intake', 'title' => 'Make your first move count.', 'intro' => 'Applications are open for the next cohort. A focused process, clear milestones, and a real person at every turn.', 'image' => 'assets/images/home/home-slide-1.svg', 'label' => 'Admissions open', 'cards' => [['title' => '01 / Create', 'text' => 'Register your student account with your personal and contact information.'], ['title' => '02 / Apply', 'text' => 'Complete your academic record and rank the programs that fit your future.'], ['title' => '03 / Verify', 'text' => 'Upload clear documents, submit your challan, and follow your portal timeline.']]],
        'campus-life' => ['eyebrow' => 'Beyond the timetable', 'title' => 'A campus with a pulse.', 'intro' => 'Find your people in studios, societies, competitions, community projects, and the everyday rituals that make AIT yours.', 'image' => 'assets/images/home/home-slide-2.svg', 'label' => 'Campus life', 'cards' => [['title' => 'Clubs & societies', 'text' => 'From robotics to debate, there is a room for every interest and a team for every brave first attempt.'], ['title' => 'Wellbeing first', 'text' => 'Counselling, sports, green spaces, and peer support keep achievement connected to a healthy life.'], ['title' => 'Make an impact', 'text' => 'Students partner with local schools, communities, and organisations to build work with a visible purpose.']]],
        'privacy-policy' => ['eyebrow' => 'Your trust matters', 'title' => 'Privacy, written plainly.', 'intro' => 'AIT collects only the information needed to support admissions, learning, communication, and a safe campus.', 'image' => 'assets/images/home/home-slide-3.svg', 'label' => 'Privacy policy', 'cards' => [['title' => 'What we collect', 'text' => 'Application details, contact information, academic records, and files you choose to submit through the portal.'], ['title' => 'How we use it', 'text' => 'To process applications, communicate decisions, provide services, and meet legal or safety obligations.'], ['title' => 'Your choices', 'text' => 'You may request access, correction, or clarification about your personal information by contacting the registrar.']]],
        'terms-of-service' => ['eyebrow' => 'The fine print, made human', 'title' => 'Terms for a fair digital campus.', 'intro' => 'These terms describe responsible use of AIT websites, accounts, applications, and student services.', 'image' => 'assets/images/home/home-slide-1.svg', 'label' => 'Terms of service', 'cards' => [['title' => 'Use your own account', 'text' => 'Keep credentials private and provide truthful, current information throughout the admissions process.'], ['title' => 'Respect the platform', 'text' => 'Do not probe, disrupt, upload malicious files, or attempt to access another person’s information.'], ['title' => 'A clear process', 'text' => 'Admissions decisions follow published requirements and may be subject to verification and review.']]],
        'departments' => ['eyebrow' => 'Ideas in conversation', 'title' => 'Schools that refuse silos.', 'intro' => 'Our academic departments share studios, questions, and ambitious problems. That is where unexpected collaborations begin.', 'image' => 'assets/images/home/home-slide-2.svg', 'label' => 'Academic departments', 'cards' => [['title' => 'Computing & Engineering', 'text' => 'AI, software, cyber security, data, electrical, and mechanical systems.'], ['title' => 'Civil & Architecture', 'text' => 'Design resilient places, infrastructure, and sustainable environments.'], ['title' => 'Sciences & Humanities', 'text' => 'Mathematics, physics, English, and the critical thinking behind every discipline.']]],
        'alumini' => ['eyebrow' => 'The AIT network', 'title' => 'Graduates who keep building.', 'intro' => 'Our alumni carry the AIT habit of thoughtful action into companies, labs, schools, startups, and public service.', 'image' => 'assets/images/home/home-slide-3.svg', 'label' => 'Alumni stories', 'cards' => [['title' => 'Stay connected', 'text' => 'Mentor a student, share an opportunity, or return to campus for a conversation that starts something new.'], ['title' => 'A growing network', 'text' => 'Join graduates working across technology, engineering, education, design, and entrepreneurship.'], ['title' => 'Give back with intent', 'text' => 'Support scholarships, student projects, and the next generation of practical problem-solvers.']]],
        'students' => ['eyebrow' => 'Student experience', 'title' => 'Bring your whole self to learning.', 'intro' => 'At AIT, a student is more than a transcript: you are a maker, teammate, question-asker, and future colleague.', 'image' => 'assets/images/home/home-slide-1.svg', 'label' => 'For students', 'cards' => [['title' => 'A portal that keeps you moving', 'text' => 'Track your application, documents, review notes, challan, and test slip from one secure dashboard.'], ['title' => 'Practice with purpose', 'text' => 'Turn coursework into prototypes, portfolios, research questions, and work people can use.'], ['title' => 'Support when it matters', 'text' => 'Faculty advisors and student services are close enough to notice when you need a hand.']]],
        'faculty' => ['eyebrow' => 'People who teach with purpose', 'title' => 'Faculty who open doors.', 'intro' => 'Our teachers bring scholarship, professional practice, and a generous appetite for better questions into every classroom.', 'image' => 'assets/images/home/home-slide-2.svg', 'label' => 'Faculty', 'cards' => [['title' => 'Research that travels', 'text' => 'Faculty and students investigate local challenges with methods that stand up to global conversation.'], ['title' => 'Mentorship in the work', 'text' => 'Office hours, studio critiques, and project supervision make feedback part of the learning rhythm.'], ['title' => 'Industry aware', 'text' => 'Curriculum stays connected to tools, ethics, and practices changing the world beyond campus.']]],
        'career' => ['eyebrow' => 'Build what comes next', 'title' => 'A career is a body of work.', 'intro' => 'Start with the skills, confidence, and connections to turn an AIT education into meaningful momentum.', 'image' => 'assets/images/home/home-slide-3.svg', 'label' => 'Careers at AIT', 'cards' => [['title' => 'For future colleagues', 'text' => 'Meet graduates who can reason clearly, learn quickly, collaborate generously, and ship useful work.'], ['title' => 'For our people', 'text' => 'Join a curious academic community where teaching, research, and service have room to grow.'], ['title' => 'For partners', 'text' => 'Bring a real problem to our classrooms, labs, and student talent network.']]],
    ];
    return $configs[$page] ?? $configs['about'];
}

function ait_page_ticker(string $page): array
{
    return [
        'home' => ['Fall 2026 admissions are open', 'Build your next chapter at AIT', '15 programs, one ambitious community'],
        'about' => ['AIT is built around useful ambition', 'Learn by making', 'A close community with a wide horizon'],
        'programs' => ['Explore 15 undergraduate pathways', 'Choose a question worth answering', 'Degrees with direction'],
        'admissions' => ['Applications are being reviewed', 'Your future starts with one clear step', 'Need help? admissions@ait.edu.pk'],
        'departments' => ['Ideas in conversation', 'Cross-disciplinary work starts here', 'Find your academic home'],
        'campus-life' => ['Clubs, studios, teams, and people', 'There is a place for your next idea', 'Campus life at AIT'],
        'faculty' => ['Meet the people behind the practice', 'Mentorship is part of the method', 'Research that travels'],
        'students' => ['Your portal, your progress, your pace', 'Support when it matters', 'Student life at AIT'],
        'alumini' => ['The AIT network keeps building', 'Stay connected to what comes next', 'Alumni stories and opportunities'],
        'career' => ['Build what comes next', 'Bring your problem to our people', 'Careers at AIT'],
        'contact' => ['We are ready to help', 'Visit, call, or write to AIT', 'Start a useful conversation'],
    ][$page] ?? ['Ahmer Institute for Technology', 'Learn boldly. Build usefully.', 'Admissions are open'];
}

function ait_public_canonical_url(string $active): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $base = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/AIT/pages/home.php')), '/\\');
    if (str_ends_with($base, '/pages')) $base = substr($base, 0, -6);
    return $https . '://' . $host . ($base ?: '') . '/' . ($active === '' ? 'home' : rawurlencode($active));
}

function ait_public_description(string $title, string $active): string
{
    $descriptions = [
        'home' => 'Ahmer Institute for Technology helps curious people build useful futures through practical programs, research, and a close academic community.',
        'admissions' => 'Explore admissions at Ahmer Institute for Technology: programs, scholarships, application steps, campus life, and student support.',
        'programs' => 'Explore 15 undergraduate programs in artificial intelligence, computing, engineering, sciences, humanities, architecture, and business at AIT.',
    ];
    return $descriptions[$active] ?? $title . ' at Ahmer Institute for Technology. Learn boldly, build usefully, and find your next direction.';
}

function ait_verify_recaptcha(string $token, string $secret, ?string $expectedAction = null): bool
{
    if ($token === '' || $secret === '' || !function_exists('curl_init')) return false;
    $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $result = is_string($response) ? json_decode($response, true) : null;
    if (!is_array($result) || empty($result['success'])) return false;
    if ($expectedAction !== null && ($result['action'] ?? '') !== $expectedAction) return false;
    return $expectedAction === null || (float) ($result['score'] ?? 0) >= 0.5;
}

function ait_contact_submission(): array
{
    $state = ['success' => false, 'message' => ''];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $state;
    ait_validate_csrf_post();
    ait_rate_limit('public-contact-form', 5, 900);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    if ($honeypot !== '') return ['success' => true, 'message' => 'Thanks. Your message has been received.'];
    if ($name === '' || !$email || $subject === '' || $message === '') return ['success' => false, 'message' => 'Please complete every field with valid information.'];
    if (mb_strlen($name) > 120 || mb_strlen($subject) > 180 || mb_strlen($message) > 4000) return ['success' => false, 'message' => 'Please keep your message within the allowed length.'];

    $v2Secret = (string) ait_env('RECAPTCHA_V2_SECRET_KEY', '');
    $v3Secret = (string) ait_env('RECAPTCHA_V3_SECRET_KEY', '');
    $v2Token = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
    $v3Token = trim((string) ($_POST['recaptcha_v3_token'] ?? ''));
    $captchaPassed = ($v3Secret !== '' && ait_verify_recaptcha($v3Token, $v3Secret, 'contact_form')) || ($v2Secret !== '' && ait_verify_recaptcha($v2Token, $v2Secret));
    if (!$captchaPassed) return ['success' => false, 'message' => 'Please complete the spam protection check and try again.'];

    try {
        $stmt = ait_pdo()->prepare('INSERT INTO contact_messages (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, $subject, $message, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
        $state = ['success' => true, 'message' => 'Thanks, ' . $name . '. Your message has been sent to the AIT team.'];
    } catch (Throwable $exception) {
        error_log('Contact form save failed: ' . $exception->getMessage());
        $state['message'] = 'We could not save your message right now. Please email admissions@ait.edu.pk.';
    }
    return $state;
}

function ait_public_header(string $title, string $active = ''): void
{
    ait_start_secure_session();
    $nonce = ait_bootstrap_security();
    $GLOBALS['ait_public_nonce'] = $nonce;
    $nav = ['about' => 'About', 'programs' => 'Programs', 'departments' => 'Departments', 'campus-life' => 'Campus life', 'faculty' => 'Faculty', 'contact' => 'Contact'];
    $canonical = ait_public_canonical_url($active);
    $description = ait_public_description($title, $active);
    $csrf = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title . ' | Ahmer Institute for Technology', ENT_QUOTES, 'UTF-8');
    $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $safeCanonical = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
    $schema = json_encode(['@context' => 'https://schema.org', '@type' => $active === 'home' ? 'WebSite' : 'WebPage', 'name' => $title . ' | Ahmer Institute for Technology', 'url' => $canonical, 'description' => $description, 'publisher' => ['@type' => 'Organization', 'name' => 'Ahmer Institute for Technology', 'url' => 'https://ahmershah.dev/']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $captchaSiteKey = (string) ait_env('RECAPTCHA_V3_SITE_KEY', '');
    $captchaV2SiteKey = (string) ait_env('RECAPTCHA_V2_SITE_KEY', '');
    $captchaScript = '';
    if ($active === 'contact' && ($captchaSiteKey !== '' || $captchaV2SiteKey !== '')) {
        $captchaScript = $captchaSiteKey !== ''
            ? '<script src="https://www.google.com/recaptcha/api.js?render=' . rawurlencode($captchaSiteKey) . '" defer></script>'
            : '<script src="https://www.google.com/recaptcha/api.js" defer></script>';
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="index, follow, max-image-preview:large"><meta name="description" content="' . $safeDescription . '"><meta name="csrf-token" content="' . $csrf . '"><link rel="canonical" href="' . $safeCanonical . '"><meta property="og:type" content="website"><meta property="og:title" content="' . $safeTitle . '"><meta property="og:description" content="' . $safeDescription . '"><meta property="og:url" content="' . $safeCanonical . '"><meta property="og:site_name" content="Ahmer Institute for Technology"><meta name="twitter:card" content="summary"><meta name="twitter:title" content="' . $safeTitle . '"><meta name="twitter:description" content="' . $safeDescription . '"><title>' . $safeTitle . '</title><link rel="icon" type="image/x-icon" href="assets/images/favicon/ait.ico"><link rel="stylesheet" href="assets/css/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/css/public.css"><script type="application/ld+json" nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '">' . $schema . '</script><script src="assets/js/theme.js"></script><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>' . $captchaScript . '</head><body>';
    $ribbon = ait_site_data('admissions_ribbon', 'Fall 2026 admissions are open');
    $ticker = ait_page_ticker($active);
    echo '<div class="announcement"><span class="pulse"></span> ' . htmlspecialchars($ticker[0] ?: $ribbon) . ' <a href="admissions">Explore the intake <span aria-hidden="true">↗</span></a></div><header class="site-header"><a class="brand" href="home"><span class="brand-mark">AIT</span><span>Ahmer Institute <b>for Technology</b></span></a><button class="nav-toggle" aria-label="Open navigation">☰</button><nav class="site-nav">';
    foreach ($nav as $key => $label) echo '<a class="' . ($active === $key ? 'active' : '') . '" href="' . $key . '">' . htmlspecialchars($label) . '</a>';
    echo '</nav><button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle color theme"><span data-theme-icon>◐</span><span data-theme-label>Dark mode</span></button><a class="nav-apply" href="admissions">Apply now <span>↗</span></a></header><div class="page-ticker" aria-label="AIT updates"><div class="page-ticker-track">';
    foreach (array_merge($ticker, $ticker) as $item) echo '<span>' . htmlspecialchars($item) . '</span>';
    echo '</div></div>';
}

function ait_public_footer(): void
{
    $nonce = htmlspecialchars((string) ($GLOBALS['ait_public_nonce'] ?? ''), ENT_QUOTES, 'UTF-8');
    echo '<footer class="site-footer"><div class="footer-intro"><a class="brand footer-brand" href="home"><span class="brand-mark">AIT</span><span>Ahmer Institute <b>for Technology</b></span></a><p>Learn boldly. Build usefully. Leave the world better equipped.</p><div class="footer-status"><span class="pulse"></span> Fall 2026 admissions are open</div><div class="social-links" aria-label="Social links"><a href="https://ahmershah.dev/" target="_blank" rel="noopener noreferrer" aria-label="Personal website"><i class="bi bi-globe2"></i></a><a href="https://www.facebook.com/ahmershahdev" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bi bi-facebook"></i></a><a href="https://github.com/ahmershahdev" target="_blank" rel="noopener noreferrer" aria-label="GitHub"><i class="bi bi-github"></i></a><a href="https://linkedin.com/in/syedahmershah" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a></div></div><div class="footer-column"><h3>Explore</h3><a href="about">About AIT</a><a href="programs">Programs</a><a href="departments">Departments</a><a href="campus-life">Campus life</a></div><div class="footer-column"><h3>Community</h3><a href="students">Students</a><a href="faculty">Faculty</a><a href="alumini">Alumni</a><a href="career">Careers</a></div><div class="footer-column"><h3>Support</h3><a href="contact">Contact</a><a href="faq">FAQ</a><a href="privacy-policy">Privacy policy</a><a href="terms-of-service">Terms of service</a></div><div class="footer-bottom"><span>© 2026 AIT · Jamshoro, Sindh</span><a href="student-login">Enrolled student portal ↗</a></div></footer><script src="assets/js/public.js" nonce="' . $nonce . '"></script></body></html>';
}

function ait_render_inner_page(string $page): void
{
    if ($page === 'campus-life') {
        ait_render_campus_life();
        return;
    }
    $config = ait_page_config($page);
    ait_public_header($config['label'], str_replace('_', '-', $page));
    echo '<main class="inner-page"><section class="inner-hero"><div class="inner-copy"><p class="eyebrow">' . htmlspecialchars($config['eyebrow']) . '</p><h1>' . htmlspecialchars($config['title']) . '</h1><p class="lede">' . htmlspecialchars($config['intro']) . '</p><a class="button button-coral" href="admissions">Start your AIT journey <span>↗</span></a></div></section><section class="content-band"><div class="section-heading"><p class="eyebrow">Designed around people</p><h2>Useful context. Clear next steps.</h2></div><div class="feature-grid">';
    foreach ($config['cards'] as $index => $card) echo '<article class="feature-card reveal"><span class="card-number">0' . ($index + 1) . '</span><h3>' . htmlspecialchars($card['title']) . '</h3><p>' . nl2br(htmlspecialchars($card['text'])) . '</p></article>';
    echo '</div></section><section class="metrics-band"><div><span class="metric-value">15</span><span>programs with room to explore</span></div><div><span class="metric-value">1:18</span><span>faculty mentorship ratio</span></div><div><span class="metric-value">92%</span><span>career momentum after graduation</span></div></section><section class="content-band related-band"><p class="eyebrow">Keep exploring</p><h2>There is more to AIT than one page.</h2><div class="related-links"><a href="programs">Choose a program <span>↗</span></a><a href="admissions">See admissions steps <span>↗</span></a><a href="contact">Talk to our team <span>↗</span></a></div></section></main>';
    ait_public_footer();
}

function ait_render_campus_life(): void
{
    ait_public_header('Campus Life', 'campus-life');
    echo '<main class="campus-page"><section class="campus-hero"><div class="campus-hero-copy"><p class="eyebrow">Beyond the timetable</p><h1>Find your people. Make your place.</h1><p class="lede">AIT campus life is where ideas become friendships, confidence, and work that reaches beyond the classroom.</p><div class="hero-actions"><a class="button button-coral" href="admissions">Start your AIT journey <span>↗</span></a><a class="text-link" href="contact">Plan a campus visit <span>↗</span></a></div></div></section><section class="campus-intro content-band"><div><p class="eyebrow">A day at AIT</p><h2>Life here has more than one shape.</h2></div><p class="campus-intro-copy">Spend the morning in a lab, the afternoon turning a question into a prototype, and the evening with the society that feels like home. Our campus gives students space to focus, experiment, recharge, and show up for one another.</p></section><section class="campus-photo-grid content-band"><figure class="campus-photo campus-photo-wide"><span class="photo-icon"><i class="bi bi-easel2"></i></span><figcaption><strong>Studios that stay open</strong><span>Build, test, present, repeat.</span></figcaption></figure><figure class="campus-photo"><span class="photo-icon"><i class="bi bi-people"></i></span><figcaption><strong>People make the place</strong><span>Find collaborators across disciplines.</span></figcaption></figure><figure class="campus-photo"><span class="photo-icon"><i class="bi bi-bicycle"></i></span><figcaption><strong>Room to move</strong><span>Sport, wellbeing, and green pauses.</span></figcaption></figure></section><section class="content-band campus-pillars"><div class="section-heading"><div><p class="eyebrow">Your campus, your rhythm</p><h2>Designed for a fuller student life.</h2></div><p>There is no single way to belong here. Choose a club, join a team, ask for support, or start something new.</p></div><div class="campus-pillar-grid"><article><span class="pillar-icon"><i class="bi bi-lightbulb"></i></span><h3>Clubs & societies</h3><p>Robotics, debate, media, entrepreneurship, arts, and community groups give every interest a place to grow.</p><a href="contact">Find your people ↗</a></article><article><span class="pillar-icon"><i class="bi bi-heart-pulse"></i></span><h3>Wellbeing in the routine</h3><p>Peer support, counselling, sport, and quiet spaces help achievement stay connected to a healthy life.</p><a href="contact">Talk to student services ↗</a></article><article><span class="pillar-icon"><i class="bi bi-globe2"></i></span><h3>Impact beyond campus</h3><p>Work with local schools and communities on projects that make your learning visible and useful.</p><a href="admissions">Explore AIT ↗</a></article></div></section><section class="campus-quote"><p class="eyebrow">The AIT habit</p><blockquote>“Come with a question. Leave with something you can share.”</blockquote><span>Student life, AIT</span></section><section class="content-band related-band"><p class="eyebrow">Keep exploring</p><h2>Bring your next chapter into focus.</h2><div class="related-links"><a href="programs">Choose a program <span>↗</span></a><a href="students">See student support <span>↗</span></a><a href="contact">Visit the campus <span>↗</span></a></div></section></main>';
    ait_public_footer();
}

function ait_render_programs(): void
{
    $programs = ait_catalog_programs();
    ait_public_header('Programs', 'programs');
    echo '<main class="inner-page"><section class="inner-hero programs-hero"><div class="inner-copy"><p class="eyebrow">A degree with direction</p><h1>Find the question you want to spend four years answering.</h1><p class="lede">Explore fifteen practical programs built around curiosity, capability, and the courage to make something real.</p><a class="button button-coral" href="admissions">Apply to a program <span>↗</span></a></div></section><section class="content-band"><div class="section-heading"><p class="eyebrow">The AIT catalogue</p><h2>Programs that meet the moment.</h2></div><div class="program-grid">';
    foreach ($programs as $program) echo '<article class="program-card reveal"><div class="program-top"><span>' . htmlspecialchars($program['code']) . '</span><span>' . htmlspecialchars($program['degree_level']) . '</span></div><h3>' . htmlspecialchars($program['name']) . '</h3><p>' . htmlspecialchars($program['faculty']) . '</p><footer><span>' . htmlspecialchars((string) ($program['duration_years'] ?: '4')) . ' years</span><a href="admissions">View path ↗</a></footer></article>';
    echo '</div></section></main>';
    ait_public_footer();
}

function ait_render_admissions(): void
{
    $programs = ait_catalog_programs();
    ait_public_header('Admissions', 'admissions');
    echo '<main class="admissions-page"><section class="inner-hero admissions-hero"><div class="inner-copy"><p class="eyebrow">Fall 2026 intake</p><h1>Shape your future with AIT.</h1><p class="lede">Choose a program, join a community of makers, and begin an education designed to turn ability into useful work.</p><div class="hero-actions"><a class="button button-coral" href="login">Start application <span>↗</span></a><a class="text-link" href="programs">Browse programs <span>↗</span></a></div></div></section><section class="content-band"><div class="section-heading"><div><p class="eyebrow">Explore your options</p><h2>One institute. Several ways to begin.</h2></div><p>Our admissions pathways are designed to make the next decision clear, whether you are starting a degree or building a new skill.</p></div><div class="admission-track-grid"><article class="admission-track"><span class="card-number">01 / UNDERGRADUATE</span><h3>Undergraduate programs</h3><p>Choose from computing, engineering, sciences, humanities, architecture, economics, and business pathways.</p><a href="programs">View 15 programs ↗</a></article><article class="admission-track"><span class="card-number">02 / SUPPORT</span><h3>Financial aid & scholarships</h3><p>Merit awards, need-based support, and focused assistance help talented students stay focused on their education.</p><a href="contact">Ask about support ↗</a></article><article class="admission-track"><span class="card-number">03 / COMMUNITY</span><h3>Campus life</h3><p>Societies, studios, sports, mentoring, and community projects make an AIT education bigger than the timetable.</p><a href="campus-life">Discover campus life ↗</a></article></div></section><section class="split-band admissions-why"><div><p class="eyebrow">Why choose AIT</p><h2>Capability, community, and a clear runway.</h2><p>AIT combines accessible faculty, practical facilities, industry-aware curriculum, and a student portal that keeps your application visible from registration to decision.</p></div><div class="check-list"><div><i class="bi bi-check2-circle"></i><span>Hands-on labs and project studios</span></div><div><i class="bi bi-check2-circle"></i><span>Faculty mentorship and small cohorts</span></div><div><i class="bi bi-check2-circle"></i><span>Career, internship, and alumni connections</span></div><div><i class="bi bi-check2-circle"></i><span>Secure online application tracking</span></div></div></section><section class="content-band"><div class="section-heading"><div><p class="eyebrow">The application journey</p><h2>Four steps, no mystery.</h2></div></div><div class="process-grid"><article><span>01</span><h3>Create your account</h3><p>Register with your contact details and keep your login credentials private.</p></article><article><span>02</span><h3>Choose your path</h3><p>Review program requirements and rank up to three preferences.</p></article><article><span>03</span><h3>Submit documents</h3><p>Complete your academic record and upload clear supporting documents.</p></article><article><span>04</span><h3>Track the decision</h3><p>Follow review notes, challan status, and test-slip availability in your portal.</p></article></div></section><section class="content-band admissions-catalog"><div class="section-heading"><div><p class="eyebrow">Current catalogue</p><h2>Popular starting points.</h2></div><a class="text-link" href="programs">See the full catalogue ↗</a></div><div class="program-grid">';
    foreach (array_slice($programs, 0, 6) as $program) echo '<article class="program-card reveal"><div class="program-top"><span>' . htmlspecialchars($program['code']) . '</span><span>' . htmlspecialchars($program['degree_level']) . '</span></div><h3>' . htmlspecialchars($program['name']) . '</h3><p>' . htmlspecialchars($program['faculty']) . '</p><footer><span>' . htmlspecialchars((string) ($program['duration_years'] ?: '4')) . ' years</span><a href="login">Apply ↗</a></footer></article>';
    echo '</div></section><section class="section admission-cta"><div><p class="eyebrow">Your place could start here</p><h2>Ready to make the first move?</h2><p>Applications are open. Create your student account and let us help you find the right direction.</p></div><a class="button button-coral" href="login">Open student portal <span>↗</span></a></section></main>';
    ait_public_footer();
}

function ait_render_contact(): void
{
    $state = ait_contact_submission();
    ait_public_header('Contact', 'contact');
    $v2SiteKey = htmlspecialchars((string) ait_env('RECAPTCHA_V2_SITE_KEY', ''), ENT_QUOTES, 'UTF-8');
    $v3SiteKey = htmlspecialchars((string) ait_env('RECAPTCHA_V3_SITE_KEY', ''), ENT_QUOTES, 'UTF-8');
    echo '<main class="contact-page"><section class="inner-hero"><div class="inner-copy"><p class="eyebrow">Come say hello</p><h1>Your next conversation starts here.</h1><p class="lede">Ask about programs, visit the campus, or connect with the team that will guide your application.</p><div class="contact-detail-grid"><div><i class="bi bi-envelope"></i><strong>Admissions desk</strong><span>admissions@ait.edu.pk</span></div><div><i class="bi bi-telephone"></i><strong>Call the team</strong><span>+92 300 555 0110</span></div></div></div></section><section class="content-band contact-grid"><div class="contact-form-panel"><p class="eyebrow">Send a message</p><h2>Tell us what you need.</h2>';
    if ($state['message'] !== '') echo '<div class="form-alert ' . ($state['success'] ? 'is-success' : 'is-error') . '">' . htmlspecialchars($state['message']) . '</div>';
    echo '<form method="post" action="contact" data-recaptcha-site-key="' . $v3SiteKey . '">' . ait_csrf_field() . '<div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><div class="form-row"><label>Name<input type="text" name="name" maxlength="120" required autocomplete="name"></label><label>Email<input type="email" name="email" maxlength="190" required autocomplete="email"></label></div><label>Subject<input type="text" name="subject" maxlength="180" required></label><label>Message<textarea name="message" rows="6" maxlength="4000" required></textarea></label>';
    if ($v2SiteKey !== '') echo '<div class="g-recaptcha" data-sitekey="' . $v2SiteKey . '"></div>';
    if ($v2SiteKey === '' && $v3SiteKey === '') echo '<p class="captcha-notice">Spam protection is not configured yet. Add the reCAPTCHA keys to your environment file before enabling submissions.</p>';
    echo '<input type="hidden" name="recaptcha_v3_token" id="recaptcha-v3-token"><button class="button button-coral" type="submit">Send message <span>↗</span></button></form></div><div class="contact-map-panel"><div class="map-heading"><p class="eyebrow">Find AIT</p><h2>Visit the campus.</h2><p>Ahmer Institute for Technology<br>Hyderabad, Sindh, Pakistan</p><a class="map-directions-link" href="https://www.google.com/maps/search/?api=1&amp;query=Hyderabad%2C+Sindh%2C+Pakistan" target="_blank" rel="noopener">Get directions <span>↗</span></a></div><div class="map-frame"><span class="map-pin" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span><iframe title="AIT campus map — Hyderabad, Sindh, Pakistan" src="https://www.openstreetmap.org/export/embed.html?bbox=68.2978%2C25.3388%2C68.4178%2C25.4388&amp;layer=mapnik&amp;marker=25.3888%2C68.3578" loading="lazy"></iframe></div></div></section></main>';
    ait_public_footer();
}

function ait_render_faculty(): void
{
    $people = [['name' => 'Dr. Ayesha Khan', 'role' => 'Dean, Computing & Engineering', 'image' => 'assets/images/dashboard_sample/sample_1.png'], ['name' => 'Engr. Hamza Raza', 'role' => 'Associate Professor, Electrical Engineering', 'image' => 'assets/images/dashboard_sample/sample_2.png'], ['name' => 'Dr. Sara Ahmed', 'role' => 'Assistant Professor, Basic Sciences', 'image' => 'assets/images/dashboard_sample/sample_3.png'], ['name' => 'Prof. Usman Malik', 'role' => 'Director, Innovation Studio', 'image' => 'assets/images/dashboard_sample/sample_4.png']];
    ait_public_header('Faculty', 'faculty');
    echo '<main class="inner-page"><section class="inner-hero"><div class="inner-copy"><p class="eyebrow">People who teach with purpose</p><h1>Faculty who open doors.</h1><p class="lede">Our teachers bring scholarship, professional practice, and a generous appetite for better questions into every classroom.</p><a class="button button-coral" href="contact">Connect with AIT <span>↗</span></a></div></section><section class="content-band"><div class="section-heading"><div><p class="eyebrow">Meet the community</p><h2>Mentors for the work ahead.</h2></div><p>Faculty guidance at AIT moves between classroom concepts, lab practice, research questions, and the decisions that shape a career.</p></div><div class="person-grid">';
    foreach ($people as $person) echo '<article class="person-card reveal"><span class="person-avatar">' . htmlspecialchars(strtoupper(substr($person['name'], 0, 1))) . '</span><div><span class="card-number">AIT FACULTY</span><h3>' . htmlspecialchars($person['name']) . '</h3><p>' . htmlspecialchars($person['role']) . '</p></div></article>';
    echo '</div></section><section class="metrics-band"><div><span class="metric-value">126</span><span>faculty and visiting mentors</span></div><div><span class="metric-value">18</span><span>research and practice areas</span></div><div><span class="metric-value">1:18</span><span>mentor-to-student ratio</span></div></section></main>';
    ait_public_footer();
}

function ait_render_departments(): void
{
    $departments = [['name' => 'Computing & Engineering', 'text' => 'AI, software, cyber security, data, electrical, and mechanical systems.', 'image' => 'assets/images/dashboard_sample/sample_1.png'], ['name' => 'Civil & Architecture', 'text' => 'Design resilient places, infrastructure, and sustainable environments.', 'image' => 'assets/images/dashboard_sample/sample_2.png'], ['name' => 'Sciences & Humanities', 'text' => 'Mathematics, physics, English, and the critical thinking behind every discipline.', 'image' => 'assets/images/dashboard_sample/sample_3.png'], ['name' => 'Management Sciences', 'text' => 'Build organisations, ventures, and decisions that create durable value.', 'image' => 'assets/images/dashboard_sample/sample_4.png']];
    ait_public_header('Departments', 'departments');
    echo '<main class="inner-page"><section class="inner-hero"><div class="inner-copy"><p class="eyebrow">Ideas in conversation</p><h1>Schools that refuse silos.</h1><p class="lede">Our departments share studios, questions, and ambitious problems. That is where unexpected collaborations begin.</p><a class="button button-coral" href="programs">Explore programs <span>↗</span></a></div></section><section class="content-band"><div class="section-heading"><div><p class="eyebrow">Academic homes</p><h2>Find the people and questions that fit.</h2></div></div><div class="department-grid">';
    foreach ($departments as $department) echo '<article class="department-card reveal"><span class="department-icon"><i class="bi bi-mortarboard"></i></span><div class="department-card-body"><span class="card-number">ACADEMIC SCHOOL</span><h3>' . htmlspecialchars($department['name']) . '</h3><p>' . htmlspecialchars($department['text']) . '</p><a href="programs">View programs ↗</a></div></article>';
    echo '</div></section><section class="content-band related-band"><p class="eyebrow">Work across boundaries</p><h2>One question can belong to more than one department.</h2><div class="related-links"><a href="faculty">Meet the faculty <span>↗</span></a><a href="programs">Compare programs <span>↗</span></a><a href="contact">Talk to admissions <span>↗</span></a></div></section></main>';
    ait_public_footer();
}

<?php

require_once __DIR__ . '/env.php';

if (!function_exists('ait_error_page')) {
    function ait_error_page(int $status, array $options = []): never
    {
        $defaults = [
            403 => ['Not available', 'This page is not available.', 'You may need to sign in with a different account, or the link may be out of date.'],
            404 => ['Page not found', 'We could not find that page.', 'The link may be broken, or the page may have moved. These are the places people usually look for:'],
            419 => ['Session expired', 'Your form has expired.', 'For your security, forms expire after a while. Go back, reload the page, and submit again.'],
            422 => ['Please check the form', 'Something in the form needs another look.', 'Go back to the form, correct the highlighted details, and try again.'],
            429 => ['Slow down', 'Too many attempts in a short time.', 'Please wait a few minutes before trying again. This protects your account from guessing attacks.'],
            500 => ['Something went wrong', 'That one is on us.', 'An unexpected error stopped this page. It has been logged; please try again in a moment.'],
            503 => ['Back shortly', 'We will be right back.', 'The portal is unavailable for a moment, usually during maintenance. Your saved applications and records are safe.'],
        ];
        [$eyebrow, $title, $message] = $defaults[$status] ?? ['Something went wrong', 'We could not complete that request.', 'Please try again in a moment.'];
        $eyebrow = $options['eyebrow'] ?? $eyebrow;
        $title = $options['title'] ?? $title;
        $message = $options['message'] ?? $message;
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $u = static fn(string $p): string => $esc(ait_url($p));

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            header('X-Robots-Tag: noindex');
            if ($status === 503) {
                header('Retry-After: 120');
            }
            foreach ($options['headers'] ?? [] as $name => $value) {
                header($name . ': ' . $value);
            }
            if (!function_exists('ait_send_security_headers')) {
                header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
            }
        }

        $primary = $options['primary'] ?? ($status === 503 || !empty($options['retry']) ? null : ['', 'Go to the home page']);
        $actions = $primary !== null
            ? '<a class="button button-coral" href="' . $u($primary[0]) . '">' . $esc($primary[1]) . ' <span aria-hidden="true">↗</span></a>'
            : '<a class="button button-coral" href="" data-reload>Try again <span aria-hidden="true">↻</span></a>';
        if ($status !== 404) {
            $actions .= '<a class="text-link" href="' . $u('') . '" data-back>Go back <span aria-hidden="true">←</span></a>';
        }

        $links = [
            ['programs', 'mortarboard', 'Programs', 'All fifteen degrees'],
            ['admissions', 'calendar-check', 'Admissions', 'Dates, fees and steps'],
            ['student-login', 'person-badge', 'Student portal', 'Sign in with your ID'],
            ['contact', 'envelope', 'Contact us', 'Reply in two working days'],
        ];
        $grid = '';
        foreach ($links as [$path, $icon, $label, $hint]) {
            $grid .= '<a class="error-link" href="' . $u($path) . '"><i class="bi bi-' . $icon . '" aria-hidden="true"></i><span><strong>' . $esc($label) . '</strong><small>' . $esc($hint) . '</small></span></a>';
        }

        echo '<!doctype html><html lang="en-PK"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><meta name="theme-color" content="#0b7772">'
            . '<title>' . $esc($eyebrow) . ' | Ahmer Institute of Technology</title>'
            . '<link rel="icon" href="' . $u('assets/images/favicon/ait.ico') . '">'
            . '<link rel="stylesheet" href="' . $u('assets/css/bootstrap-icons.min.css') . '">'
            . '<link rel="stylesheet" href="' . $u('assets/css/public.css') . '">'
            . '<script src="' . $u('assets/js/theme.js') . '"></script></head>'
            . '<body class="error-body"><header class="error-top"><a class="brand" href="' . $u('') . '"><img class="brand-logo" src="' . $u('assets/images/logo/ait-logo-240.webp') . '" width="40" height="40" alt=""><span>Ahmer Institute <b>of Technology</b></span></a>'
            . '<button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle colour theme"><span data-theme-icon aria-hidden="true">◐</span><span data-theme-label>Dark mode</span></button></header>'
            . '<main id="main" class="error-shell"><section class="error-card" aria-labelledby="error-title">'
            . '<div class="error-art" aria-hidden="true"><span class="error-code">' . $status . '</span><img src="' . $u('assets/images/illustrations/' . ($status === 503 ? 'research' : 'library') . '.svg') . '" alt="" width="640" height="400"></div>'
            . '<div class="error-copy"><p class="eyebrow">' . $esc($eyebrow) . '</p><h1 id="error-title">' . $esc($title) . '</h1><p class="lede' . ($status >= 400 && $status !== 404 ? ' alert alert-danger alert-custom' : '') . '" role="alert">' . $esc($message) . '</p>'
            . '<div class="hero-actions">' . $actions . '</div></div></section>'
            . ($status === 404 || $status === 403 ? '<nav class="error-links" aria-label="Popular pages">' . $grid . '</nav>' : '')
            . '</main><footer class="error-foot">© ' . date('Y') . ' Ahmer Institute of Technology · <a href="' . $u('contact') . '">Need help?</a></footer>'
            . '<script src="' . $u('assets/js/error-page.js') . '"></script></body></html>';
        exit;
    }
}

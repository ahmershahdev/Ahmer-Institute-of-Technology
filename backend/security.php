<?php
/**
 * Request-level security services: sessions, CSRF, CSP + security headers,
 * rate limiting, login lockout, bot protection, password hashing.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/uploads.php';

ait_load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

if (!function_exists('ait_start_session')) {
    function ait_start_session(): void
    {
        ait_start_secure_session();
    }
}

if (!function_exists('ait_client_ip')) {
    /**
     * REMOTE_ADDR only. Behind a reverse proxy, configure mod_remoteip with the
     * proxy's address instead of trusting X-Forwarded-For here.
     */
    function ait_client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}

if (!function_exists('ait_init_csrf_token')) {
    function ait_init_csrf_token(): string
    {
        ait_start_session();

        if (empty($_SESSION['csrf_token']) || strlen((string) $_SESSION['csrf_token']) < 64) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('ait_csp_nonce')) {
    function ait_csp_nonce(): string
    {
        static $nonce = null;
        return $nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
}

if (!function_exists('ait_send_security_headers')) {
    function ait_send_security_headers(string $nonce): void
    {
        if (headers_sent()) {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $recaptcha = (string) ait_env('RECAPTCHA_V3_SITE_KEY', '') !== '' || (string) ait_env('RECAPTCHA_V2_SITE_KEY', '') !== '';
        $recaptchaScript = $recaptcha ? ' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/' : '';
        $recaptchaFrame = $recaptcha ? ' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/' : '';

        $policy = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$recaptchaScript}",
            "script-src-attr 'none'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com",
            // Style attributes cannot execute script; they stay allowed for layout tweaks.
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self'" . ($recaptcha ? ' https://www.google.com/recaptcha/' : ''),
            "frame-src https://www.openstreetmap.org{$recaptchaFrame}",
            "worker-src 'self'",
            "manifest-src 'self'",
        ];
        if ($https) {
            $policy[] = 'upgrade-insecure-requests';
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
        }

        header('Content-Security-Policy: ' . implode('; ', $policy));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header_remove('X-Powered-By');
    }
}

if (!function_exists('ait_bootstrap_security')) {
    /** Starts the session, issues the CSRF token, sends headers; returns the CSP nonce. */
    function ait_bootstrap_security(): string
    {
        ait_start_session();
        ait_init_csrf_token();
        $nonce = ait_csp_nonce();
        ait_send_security_headers($nonce);
        return $nonce;
    }
}

if (!function_exists('ait_rate_limit_storage_dir')) {
    function ait_rate_limit_storage_dir(): string
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratelimits';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create rate limit storage directory.');
        }

        return $dir;
    }
}

if (!function_exists('ait_rate_limit_hit')) {
    /**
     * Fixed-window counter. Returns [count, secondsUntilReset]. Redis INCR+EXPIRE
     * run atomically in one MULTI block; the file fallback uses an exclusive lock,
     * so concurrent requests can never both read the same count.
     */
    function ait_rate_limit_hit(string $key, int $windowSeconds): array
    {
        $redis = ait_redis();
        if ($redis !== null) {
            try {
                $redisKey = 'rl:' . hash('sha256', $key);
                $result = $redis->multi()->incr($redisKey)->expire($redisKey, $windowSeconds, 'NX')->ttl($redisKey)->exec();
                if (is_array($result)) {
                    return [(int) $result[0], max(1, (int) $result[2])];
                }
            } catch (Throwable $e) {
                error_log('Rate limit Redis error: ' . $e->getMessage());
            }
        }

        $now = time();
        $file = ait_rate_limit_storage_dir() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return [0, $windowSeconds];
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $entry = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($entry) || !isset($entry['started'], $entry['count']) || ($now - (int) $entry['started']) >= $windowSeconds) {
                $entry = ['started' => $now, 'count' => 0];
            }
            $entry['count'] = (int) $entry['count'] + 1;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($entry));
            fflush($handle);
            return [$entry['count'], max(1, $windowSeconds - ($now - (int) $entry['started']))];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

if (!function_exists('ait_rate_limit')) {
    /**
     * Throttles by client IP, and additionally by identifier (e.g. the email a
     * login targets) when one is given, so a single account cannot be sprayed
     * from many addresses either. RATE_LIMIT_MULTIPLIER scales limits for load tests.
     */
    function ait_rate_limit(string $bucket, int $limit, int $windowSeconds, ?string $identifier = null): void
    {
        $limit = max(1, (int) round($limit * max(0.1, (float) ait_env('RATE_LIMIT_MULTIPLIER', 1))));
        $hasIdentifier = $identifier !== null && $identifier !== '';
        // With an identifier, the account gets $limit and the shared address four times that,
        // so one campus NAT or mobile carrier IP does not lock out every user behind it.
        $keys = [$bucket . '|ip|' . ait_client_ip() => $hasIdentifier ? $limit * 4 : $limit];
        if ($hasIdentifier) {
            $keys[$bucket . '|id|' . strtolower(trim($identifier))] = $limit;
        }
        foreach ($keys as $key => $max) {
            [$count, $retryAfter] = ait_rate_limit_hit($key, $windowSeconds);
            if ($count > $max) {
                ait_abort(429, 'Too many requests. Please wait ' . (int) ceil($retryAfter / 60) . ' minute(s) and try again.', ['Retry-After' => (string) $retryAfter]);
            }
        }
    }
}

if (!function_exists('ait_abort')) {
    function ait_abort(int $status, string $message, array $headers = []): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Request stopped | AIT</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 20px;line-height:1.6;color:#1f2937">'
            . '<h1 style="font-size:1.4rem">We could not complete that request</h1><p>' . $safe . '</p>'
            . '<p><a href="/" rel="nofollow">Return to the home page</a></p></body></html>';
        exit;
    }
}

if (!function_exists('ait_assert_lockout_table')) {
    function ait_assert_lockout_table(string $table): void
    {
        if (!in_array($table, ['admins', 'students', 'teachers', 'staff'], true)) {
            throw new InvalidArgumentException('Invalid lockout table.');
        }
    }
}

if (!function_exists('ait_register_failed_login')) {
    /** Atomic increment; the account locks for $lockoutSeconds once it reaches $maxAttempts. */
    function ait_register_failed_login(PDO|mysqli $db, string $table, int $id, int $maxAttempts = 5, int $lockoutSeconds = 900): void
    {
        ait_assert_lockout_table($table);
        $sql = "UPDATE `{$table}` SET locked_until = IF(failed_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), locked_until), failed_attempts = IF(failed_attempts + 1 >= ?, 0, failed_attempts + 1) WHERE id = ?";

        if ($db instanceof PDO) {
            $db->prepare($sql)->execute([$maxAttempts, $lockoutSeconds, $maxAttempts, $id]);
            return;
        }

        $stmt = $db->prepare($sql);
        $stmt->bind_param('iiii', $maxAttempts, $lockoutSeconds, $maxAttempts, $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ait_clear_failed_login')) {
    function ait_clear_failed_login(PDO|mysqli $db, string $table, int $id): void
    {
        ait_assert_lockout_table($table);
        $sql = "UPDATE `{$table}` SET failed_attempts = 0, locked_until = NULL WHERE id = ?";

        if ($db instanceof PDO) {
            $db->prepare($sql)->execute([$id]);
            return;
        }

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ait_is_locked_out')) {
    function ait_is_locked_out(?string $lockedUntil): bool
    {
        return $lockedUntil !== null && strtotime($lockedUntil) > time();
    }
}

if (!function_exists('ait_password_hash')) {
    function ait_password_hash(string $password): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
            : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

if (!function_exists('ait_password_verify')) {
    /**
     * Constant-work verification: a missing account still costs one hash check,
     * so response timing does not reveal which emails are registered.
     */
    function ait_password_verify(string $password, ?string $hash): bool
    {
        static $dummy = '$2y$12$apzV.0nbMjEDvIBuxYVhMOO1R71/4yZefb0uJZYE4M9X87rdXZ7xS';
        $ok = password_verify($password, $hash ?? $dummy);
        return $hash !== null && $ok;
    }
}

if (!function_exists('ait_password_rehash_if_needed')) {
    function ait_password_rehash_if_needed(PDO|mysqli $db, string $table, int $id, string $password, string $hash): void
    {
        ait_assert_lockout_table($table);
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $options = $algo === PASSWORD_BCRYPT ? ['cost' => 12] : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
        if (!password_needs_rehash($hash, $algo, $options)) {
            return;
        }
        $new = ait_password_hash($password);
        if ($db instanceof PDO) {
            $db->prepare("UPDATE `{$table}` SET password = ? WHERE id = ?")->execute([$new, $id]);
            return;
        }
        $stmt = $db->prepare("UPDATE `{$table}` SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $new, $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ait_password_policy_error')) {
    /** Returns a human-readable reason the password is too weak, or null when it is acceptable. */
    function ait_password_policy_error(string $password, array $context = []): ?string
    {
        $length = mb_strlen($password);
        if ($length < 10 || $length > 72) {
            return 'Use between 10 and 72 characters.';
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Include at least one lowercase letter, one uppercase letter, and one number.';
        }
        $lower = strtolower($password);
        foreach (['password', '12345678', 'qwerty', 'letmein', 'welcome', 'ait12345', 'admin123', 'iloveyou'] as $common) {
            if (str_contains($lower, $common)) {
                return 'That password is too easy to guess. Avoid common words and sequences.';
            }
        }
        foreach ($context as $value) {
            $value = strtolower(trim((string) $value));
            if (strlen($value) >= 4 && str_contains($lower, $value)) {
                return 'Do not include your name or email in your password.';
            }
        }
        return null;
    }
}

if (!function_exists('ait_is_disposable_email')) {
    function ait_is_disposable_email(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        $blockedDomains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'tempmail.com', 'temp-mail.org',
            'yopmail.com', 'sharklasers.com', 'getnada.com', 'dispostable.com', 'moakt.com',
            'emailondeck.com', 'maildrop.cc', 'trashmail.com', 'throwawaymail.com', 'fakeinbox.com',
        ];

        return $domain === '' || in_array($domain, $blockedDomains, true);
    }
}

if (!function_exists('ait_csrf_field')) {
    function ait_csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(ait_init_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('ait_validate_csrf_post')) {
    function ait_validate_csrf_post(): void
    {
        ait_start_session();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // A cross-site POST is refused even before the token is compared.
        $site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            ait_abort(403, 'This form must be submitted from the AIT website itself.');
        }

        $postedToken = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

        if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
            ait_abort(403, 'Your session expired or the form was opened in another tab. Please reload the page and try again.');
        }
    }
}

/* ---------------------------------------------------------------------------
 * Bot protection. Every protected form carries a honeypot field and a signed
 * render timestamp. reCAPTCHA is used when keys are configured; otherwise a
 * small arithmetic challenge stored in the session takes its place.
 * ------------------------------------------------------------------------- */

if (!function_exists('ait_form_secret')) {
    function ait_form_secret(): string
    {
        ait_start_session();
        return $_SESSION['ait_form_secret'] ??= bin2hex(random_bytes(32));
    }
}

if (!function_exists('ait_captcha_field')) {
    function ait_captcha_field(string $form): string
    {
        $issued = time();
        $signature = hash_hmac('sha256', $form . '|' . $issued, ait_form_secret());
        $html = '<div class="ait-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
            . '<input type="hidden" name="form_ts" value="' . $issued . '.' . $signature . '">';

        $v3 = (string) ait_env('RECAPTCHA_V3_SITE_KEY', '');
        $v2 = (string) ait_env('RECAPTCHA_V2_SITE_KEY', '');
        if ($v3 !== '') {
            return $html . '<input type="hidden" name="recaptcha_v3_token" data-recaptcha-action="' . htmlspecialchars($form, ENT_QUOTES, 'UTF-8') . '" data-recaptcha-key="' . htmlspecialchars($v3, ENT_QUOTES, 'UTF-8') . '">';
        }
        if ($v2 !== '') {
            return $html . '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($v2, ENT_QUOTES, 'UTF-8') . '"></div>';
        }

        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $_SESSION['ait_captcha'][$form] = ['answer' => $a + $b, 'tries' => 0];
        return $html . '<label class="ait-captcha">Quick check: what is ' . $a . ' + ' . $b . '?'
            . '<input type="text" name="captcha_answer" inputmode="numeric" pattern="[0-9]*" maxlength="2" required autocomplete="off" placeholder="Type the answer, e.g. 7"></label>';
    }
}

if (!function_exists('ait_verify_recaptcha')) {
    function ait_verify_recaptcha(string $token, string $secret, ?string $expectedAction = null): bool
    {
        if ($token === '' || $secret === '' || !function_exists('curl_init')) {
            return false;
        }
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => ait_client_ip()]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $result = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($result) || empty($result['success'])) {
            return false;
        }
        if ($expectedAction !== null && ($result['action'] ?? '') !== $expectedAction) {
            return false;
        }
        return $expectedAction === null || (float) ($result['score'] ?? 0) >= 0.5;
    }
}

if (!function_exists('ait_captcha_verify')) {
    /** Returns null when the submission looks human, or a message to show the user. */
    function ait_captcha_verify(string $form, int $minSeconds = 3): ?string
    {
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            return 'Your submission could not be verified.';
        }

        [$issued, $signature] = array_pad(explode('.', (string) ($_POST['form_ts'] ?? ''), 2), 2, '');
        $expected = hash_hmac('sha256', $form . '|' . $issued, ait_form_secret());
        if (!ctype_digit($issued) || !hash_equals($expected, $signature)) {
            return 'The form expired. Please reload the page and try again.';
        }
        $age = time() - (int) $issued;
        if ($age < $minSeconds) {
            return 'That was a little too quick. Please review the form and submit again.';
        }
        if ($age > 7200) {
            return 'The form expired. Please reload the page and try again.';
        }

        $v3Secret = (string) ait_env('RECAPTCHA_V3_SECRET_KEY', '');
        $v2Secret = (string) ait_env('RECAPTCHA_V2_SECRET_KEY', '');
        if ($v3Secret !== '' && (string) ait_env('RECAPTCHA_V3_SITE_KEY', '') !== '') {
            return ait_verify_recaptcha(trim((string) ($_POST['recaptcha_v3_token'] ?? '')), $v3Secret, $form) ? null : 'Spam protection could not confirm the request. Please try again.';
        }
        if ($v2Secret !== '' && (string) ait_env('RECAPTCHA_V2_SITE_KEY', '') !== '') {
            return ait_verify_recaptcha(trim((string) ($_POST['g-recaptcha-response'] ?? '')), $v2Secret) ? null : 'Please tick the "I am not a robot" box.';
        }

        $challenge = $_SESSION['ait_captcha'][$form] ?? null;
        unset($_SESSION['ait_captcha'][$form]);
        if (!is_array($challenge)) {
            return 'The form expired. Please reload the page and try again.';
        }
        $answer = trim((string) ($_POST['captcha_answer'] ?? ''));
        if (!ctype_digit($answer) || (int) $answer !== (int) $challenge['answer']) {
            return 'The answer to the quick check was not right. Please try the new question.';
        }
        return null;
    }
}

if (!function_exists('ait_login_needs_captcha')) {
    /** Logins show the challenge after three failed attempts in the same session. */
    function ait_login_needs_captcha(string $portal): bool
    {
        ait_start_session();
        return (int) ($_SESSION['ait_login_failures'][$portal] ?? 0) >= 3;
    }
}

if (!function_exists('ait_login_failure')) {
    function ait_login_failure(string $portal): void
    {
        $_SESSION['ait_login_failures'][$portal] = (int) ($_SESSION['ait_login_failures'][$portal] ?? 0) + 1;
    }
}

if (!function_exists('ait_login_success')) {
    function ait_login_success(string $portal): void
    {
        unset($_SESSION['ait_login_failures'][$portal]);
    }
}

if (!function_exists('ait_e')) {
    function ait_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!class_exists('AitUserFacingError')) {
    /** An exception whose message is safe and useful to show to the person who caused it. */
    class AitUserFacingError extends RuntimeException
    {
    }
}

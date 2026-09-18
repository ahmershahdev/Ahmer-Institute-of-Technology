<?php

if (!function_exists('ait_start_session')) {
    function ait_start_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('ait_init_csrf_token')) {
    function ait_init_csrf_token(): string
    {
        ait_start_session();

        if (empty($_SESSION['csrf_token']) || strlen((string) $_SESSION['csrf_token']) < 32) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('ait_bootstrap_security')) {
    function ait_bootstrap_security(): string
    {
        ait_start_session();
        $now = time();
        if (!empty($_SESSION['ait_last_activity']) && ($now - (int) $_SESSION['ait_last_activity']) > 1800) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['ait_last_activity'] = $now;
        $csrfToken = ait_init_csrf_token();
        $nonce = base64_encode(random_bytes(32));

        if (!headers_sent()) {
            $policy = implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "object-src 'none'",
                "img-src 'self' data: blob: https:",
                "font-src 'self' https://fonts.gstatic.com https://fonts.googleapis.com https://cdn.jsdelivr.net data:",
                "connect-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://www.google.com/recaptcha/",
                "frame-src 'self' https://www.openstreetmap.org https://www.openstreetmap.org/ https://www.google.com/recaptcha/",
                "worker-src 'self' blob:",
                "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
                "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://code.jquery.com https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/",
            ]) . ';';

            header('Content-Security-Policy: ' . $policy);
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            header('Cross-Origin-Opener-Policy: same-origin');
            header('Cross-Origin-Resource-Policy: same-origin');
        }

        return $nonce;
    }
}

if (!function_exists('ait_rate_limit')) {
    function ait_rate_limit(string $bucket, int $limit, int $windowSeconds): void
    {
        ait_start_session();
        $now = time();
        $key = 'ait_rate_' . hash('sha256', $bucket . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $entry = $_SESSION[$key] ?? ['started' => $now, 'count' => 0];

        if (($now - (int) $entry['started']) >= $windowSeconds) {
            $entry = ['started' => $now, 'count' => 0];
        }

        $entry['count']++;
        $_SESSION[$key] = $entry;

        if ($entry['count'] > $limit) {
            http_response_code(429);
            die('Too many requests. Please wait a few minutes and try again.');
        }
    }
}

if (!function_exists('ait_is_disposable_email')) {
    function ait_is_disposable_email(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        $blockedDomains = [
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'tempmail.com',
            'temp-mail.org',
            'yopmail.com',
            'sharklasers.com',
            'getnada.com',
            'dispostable.com',
            'moakt.com',
            'emailondeck.com',
            'maildrop.cc'
        ];

        return $domain === '' || in_array($domain, $blockedDomains, true);
    }
}

if (!function_exists('ait_csrf_field')) {
    function ait_csrf_field(): string
    {
        ait_init_csrf_token();

        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('ait_validate_csrf_post')) {
    function ait_validate_csrf_post(): void
    {
        ait_start_session();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $postedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if ($postedToken === '' || $sessionToken === '' || !hash_equals((string) $sessionToken, (string) $postedToken)) {
            http_response_code(403);
            die('CSRF token validation failed. Unauthorized request.');
        }
    }
}

if (!function_exists('ait_store_uploaded_asset')) {
    function ait_store_uploaded_asset(array $file, string $destinationDir, string $baseName, array $options = []): string
    {
        $maxImageSize = $options['max_image_size'] ?? (5 * 1024 * 1024);
        $maxPdfSize = $options['max_pdf_size'] ?? (20 * 1024 * 1024);
        $webpThreshold = $options['webp_threshold'] ?? (1 * 1024 * 1024);
        $allowedExtensions = $options['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed for ' . ($file['name'] ?? 'file') . '.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid upload source.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > max($maxImageSize, $maxPdfSize)) {
            throw new RuntimeException('The uploaded file is empty or exceeds the permitted size.');
        }

        $originalExtension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($originalExtension, $allowedExtensions, true)) {
            throw new RuntimeException('Invalid file extension.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        $isImage = in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true);
        $isPdf = $mimeType === 'application/pdf';

        if (!$isImage && !$isPdf) {
            throw new RuntimeException('Only image and PDF files are allowed.');
        }

        if ($isImage && $size > $maxImageSize) {
            throw new RuntimeException('Each image must be 5 MB or smaller.');
        }

        if ($isImage) {
            $dimensions = @getimagesize($file['tmp_name']);
            if ($dimensions === false || ($dimensions[0] ?? 0) < 1 || ($dimensions[1] ?? 0) < 1 || ($dimensions[0] ?? 0) > 10000 || ($dimensions[1] ?? 0) > 10000) {
                throw new RuntimeException('The image is corrupt or has unsafe dimensions.');
            }
        }

        if ($isPdf && ($size > $maxPdfSize || substr((string) file_get_contents($file['tmp_name'], false, null, 0, 5), 0, 5) !== '%PDF-')) {
            throw new RuntimeException('The PDF is invalid or exceeds the 20 MB limit.');
        }

        $destinationDir = rtrim($destinationDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        if ($isImage) {
            if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
                throw new RuntimeException('Image compression is unavailable on this server.');
            }

            $destinationPath = $destinationDir . $baseName . '.webp';
            $imageData = file_get_contents($file['tmp_name']);
            $image = imagecreatefromstring($imageData);

            if ($image === false) {
                throw new RuntimeException('The uploaded image could not be processed.');
            }

            $qualities = $size > $webpThreshold ? [88, 84, 80, 76, 72, 68] : [90, 84];
            foreach ($qualities as $quality) {
                if (imagewebp($image, $destinationPath, $quality) && filesize($destinationPath) <= $maxImageSize) {
                    imagedestroy($image);
                    return $destinationPath;
                }

                if (file_exists($destinationPath)) {
                    unlink($destinationPath);
                }
            }

            imagedestroy($image);
            throw new RuntimeException('Unable to compress the image below the required size.');
        }

        $destinationPath = $destinationDir . $baseName . '.' . ($originalExtension === 'jpeg' ? 'jpg' : $originalExtension);
        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            throw new RuntimeException('Failed to save the uploaded file.');
        }

        return $destinationPath;
    }
}

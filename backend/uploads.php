<?php
/**
 * Upload pipeline shared by every portal.
 *
 * Each file passes, in order:
 *   1. transport checks (PHP upload error, is_uploaded_file, single file per field)
 *   2. size limits per file class
 *   3. extension allow-list
 *   4. magic-byte signature check, cross-checked against finfo's MIME type
 *   5. content inspection (PDF active-content markers, encrypted PDFs, sane image dimensions)
 *   6. antivirus scan through clamd (INSTREAM over TCP)
 *   7. content disarm & reconstruction: images are decoded and re-encoded to WebP
 *      (drops EXIF/ICC/trailing payloads), PDFs are rebuilt by Ghostscript pdfwrite
 *   8. storage under a random name in UPLOAD_ROOT, which should sit outside the
 *      web root; the original filename is only kept, sanitised, for display
 *
 * Files are referenced in the database by a logical key such as
 * "uploads/documents/3f9a....webp" and resolved with ait_storage_path(), so the
 * physical location can move without a data migration.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

const AIT_UPLOAD_SIGNATURES = [
    'image/jpeg'      => ["\xFF\xD8\xFF"],
    'image/png'       => ["\x89PNG\r\n\x1A\n"],
    'image/webp'      => ['RIFF'],          // plus "WEBP" at offset 8, checked below
    'application/pdf' => ['%PDF-'],
];

const AIT_UPLOAD_EXTENSIONS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];

if (!function_exists('ait_upload_root')) {
    function ait_upload_root(): string
    {
        $configured = trim((string) ait_env('UPLOAD_ROOT', ''));
        $root = $configured !== '' ? $configured : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
        $root = rtrim($root, '/\\');
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Upload storage is unavailable.');
        }
        return $root;
    }
}

if (!function_exists('ait_storage_key_normalize')) {
    /** Accepts legacy "./uploads/x" and "uploads/x" forms; returns "uploads/x" or null. */
    function ait_storage_key_normalize(string $key): ?string
    {
        $key = str_replace('\\', '/', trim($key));
        $key = preg_replace('#^(\./)+#', '', $key) ?? '';
        if (!str_starts_with($key, 'uploads/')) {
            return null;
        }
        $relative = substr($key, 8);
        if ($relative === '' || str_contains($relative, '..') || !preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_.-]+)*$#', $relative)) {
            return null;
        }
        return 'uploads/' . $relative;
    }
}

if (!function_exists('ait_storage_path')) {
    /** Resolves a logical key to an absolute path inside the upload root, or null. */
    function ait_storage_path(string $key): ?string
    {
        $normalized = ait_storage_key_normalize($key);
        if ($normalized === null) {
            return null;
        }
        $root = realpath(ait_upload_root());
        if ($root === false) {
            return null;
        }
        $path = realpath($root . DIRECTORY_SEPARATOR . substr($normalized, 8));
        if ($path === false || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $path;
    }
}

if (!function_exists('ait_storage_delete')) {
    function ait_storage_delete(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }
        $path = ait_storage_path($key);
        if ($path !== null) {
            @unlink($path);
        }
    }
}

if (!function_exists('ait_sanitize_filename')) {
    /** Display-only name: no path, no control or shell characters, bounded length. */
    function ait_sanitize_filename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = preg_replace('/[^\p{L}\p{N} ._()-]+/u', '_', $name) ?? '';
        $name = preg_replace('/\.{2,}/', '.', $name) ?? '';
        $name = trim($name, ' .');
        if (mb_strlen($name) > 120) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 110) . ($ext !== '' ? '.' . $ext : '');
        }
        return $name !== '' ? $name : 'document';
    }
}

if (!function_exists('ait_detect_signature')) {
    function ait_detect_signature(string $head): ?string
    {
        foreach (AIT_UPLOAD_SIGNATURES as $mime => $signatures) {
            foreach ($signatures as $signature) {
                if (str_starts_with($head, $signature)) {
                    if ($mime === 'image/webp' && substr($head, 8, 4) !== 'WEBP') {
                        continue;
                    }
                    return $mime;
                }
            }
        }
        return null;
    }
}

if (!function_exists('ait_clamav_scan')) {
    /**
     * Streams the file to clamd. Returns true when clean. Throws when the scanner
     * is required but unreachable, so an outage never silently disables scanning.
     */
    function ait_clamav_scan(string $path): bool
    {
        $host = (string) ait_env('CLAMAV_HOST', '');
        $required = (string) ait_env('UPLOAD_AV_REQUIRED', '0') === '1';
        if ($host === '') {
            if ($required) {
                throw new RuntimeException('Virus scanning is not configured, so uploads are disabled.');
            }
            error_log('AIT upload: CLAMAV_HOST not set, antivirus scan skipped.');
            return true;
        }

        $socket = @stream_socket_client('tcp://' . $host . ':' . (int) ait_env('CLAMAV_PORT', 3310), $errno, $errstr, 5);
        if ($socket === false) {
            if ($required) {
                throw new RuntimeException('The virus scanner is unavailable. Please try again shortly.');
            }
            error_log("AIT upload: clamd unreachable ({$errstr}), scan skipped.");
            return true;
        }
        stream_set_timeout($socket, 30);
        fwrite($socket, "zINSTREAM\0");
        $handle = fopen($path, 'rb');
        while ($handle !== false && !feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($socket, pack('N', strlen($chunk)) . $chunk);
        }
        if ($handle !== false) {
            fclose($handle);
        }
        fwrite($socket, pack('N', 0));
        $reply = trim((string) stream_get_contents($socket), "\0\r\n ");
        fclose($socket);

        if (str_ends_with($reply, 'OK')) {
            return true;
        }
        if (str_contains($reply, 'FOUND')) {
            error_log('AIT upload: malware rejected: ' . $reply);
            return false;
        }
        throw new RuntimeException('The virus scanner returned an unexpected result.');
    }
}

if (!function_exists('ait_pdf_has_active_content')) {
    function ait_pdf_has_active_content(string $path): bool
    {
        $raw = (string) file_get_contents($path);
        // Names can be hex-escaped (/J#61vaScript); decode those before matching.
        $decoded = preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn(array $m): string => chr((int) hexdec($m[1])), $raw) ?? $raw;
        return (bool) preg_match('#/(JavaScript|JS|Launch|EmbeddedFiles?|RichMedia|XFA|SubmitForm|ImportData|Encrypt)\b#', $decoded);
    }
}

if (!function_exists('ait_pdf_reconstruct')) {
    /** Rebuilds the PDF with Ghostscript; returns the new path or null when gs is missing. */
    function ait_pdf_reconstruct(string $source, string $target): ?string
    {
        $gs = (string) ait_env('GHOSTSCRIPT_BIN', 'gs');
        if (!function_exists('proc_open')) {
            return null;
        }
        $command = [$gs, '-q', '-dSAFER', '-dNOPAUSE', '-dBATCH', '-dNOINTERPOLATE', '-sDEVICE=pdfwrite',
            '-dPDFSETTINGS=/ebook', '-dCompatibilityLevel=1.6', '-sOutputFile=' . $target, $source];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code === 127) {
            return null;
        }
        if ($code !== 0 || !is_file($target) || (string) file_get_contents($target, false, null, 0, 5) !== '%PDF-') {
            @unlink($target);
            error_log('AIT upload: ghostscript rebuild failed: ' . trim((string) $stderr));
            throw new RuntimeException('The PDF could not be processed. Please export it again and retry.');
        }
        return $target;
    }
}

if (!function_exists('ait_store_uploaded_asset')) {
    /**
     * Validates, scans, disarms, and stores one uploaded file.
     *
     * @param array  $file    one entry from $_FILES
     * @param string $subdir  storage sub-folder, e.g. "documents" or "challans"
     * @param array  $options allowed_extensions, max_image_size, max_pdf_size, max_dimension
     * @return array{key:string, mime:string, size:int, sha256:string, original_name:string}
     */
    function ait_store_uploaded_asset(array $file, string $subdir, array $options = []): array
    {
        $maxImageSize = (int) ($options['max_image_size'] ?? 5 * 1024 * 1024);
        $maxPdfSize = (int) ($options['max_pdf_size'] ?? 10 * 1024 * 1024);
        $maxDimension = (int) ($options['max_dimension'] ?? 2400);
        $allowedExtensions = $options['allowed_extensions'] ?? array_keys(AIT_UPLOAD_EXTENSIONS);

        if (is_array($file['name'] ?? null) || is_array($file['tmp_name'] ?? null)) {
            throw new RuntimeException('Only one file may be uploaded per field.');
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('The file is larger than the permitted size.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The upload did not complete. Please try again.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        // CLI test runs cannot create real uploads; nothing on the web SAPI can set AIT_TESTING.
        $testing = PHP_SAPI === 'cli' && defined('AIT_TESTING');
        if ($tmp === '' || !($testing ? is_file($tmp) : is_uploaded_file($tmp))) {
            throw new RuntimeException('Invalid upload source.');
        }
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $subdir)) {
            throw new InvalidArgumentException('Invalid storage folder.');
        }

        $size = (int) filesize($tmp);
        if ($size < 64) {
            throw new RuntimeException('The file is empty or too small to be a real document.');
        }

        $originalName = ait_sanitize_filename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true) || !isset(AIT_UPLOAD_EXTENSIONS[$extension])) {
            throw new RuntimeException('That file type is not accepted. Allowed: ' . strtoupper(implode(', ', $allowedExtensions)) . '.');
        }
        $expectedMime = AIT_UPLOAD_EXTENSIONS[$extension];

        $head = (string) file_get_contents($tmp, false, null, 0, 16);
        $signatureMime = ait_detect_signature($head);
        $finfoMime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if ($signatureMime === null || $signatureMime !== $expectedMime || $finfoMime !== $expectedMime) {
            throw new RuntimeException('The file contents do not match its extension.');
        }

        $isPdf = $expectedMime === 'application/pdf';
        if ($isPdf && $size > $maxPdfSize) {
            throw new RuntimeException('PDFs must be ' . (int) round($maxPdfSize / 1048576) . ' MB or smaller.');
        }
        if (!$isPdf && $size > $maxImageSize) {
            throw new RuntimeException('Images must be ' . (int) round($maxImageSize / 1048576) . ' MB or smaller.');
        }

        if ($isPdf && ait_pdf_has_active_content($tmp)) {
            throw new RuntimeException('PDFs with scripts, attachments, forms, or encryption are not accepted.');
        }
        if (!$isPdf) {
            $dimensions = @getimagesize($tmp);
            if ($dimensions === false || $dimensions[0] < 32 || $dimensions[1] < 32 || $dimensions[0] > 10000 || $dimensions[1] > 10000) {
                throw new RuntimeException('The image is corrupt or has unsupported dimensions.');
            }
        }

        if (!ait_clamav_scan($tmp)) {
            throw new RuntimeException('The file failed the security scan and was rejected.');
        }

        $directory = ait_upload_root() . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload storage is unavailable.');
        }
        $name = bin2hex(random_bytes(16));

        if ($isPdf) {
            $target = $directory . DIRECTORY_SEPARATOR . $name . '.pdf';
            $staging = $directory . DIRECTORY_SEPARATOR . $name . '.incoming';
            if (!($testing ? rename($tmp, $staging) : move_uploaded_file($tmp, $staging))) {
                throw new RuntimeException('Failed to save the uploaded file.');
            }
            try {
                $rebuilt = ait_pdf_reconstruct($staging, $target);
                if ($rebuilt === null) {
                    if ((string) ait_env('UPLOAD_CDR_REQUIRED', '0') === '1') {
                        throw new RuntimeException('PDF processing is unavailable right now. Please upload an image instead.');
                    }
                    rename($staging, $target);
                } elseif (ait_pdf_has_active_content($target)) {
                    @unlink($target);
                    throw new RuntimeException('PDFs with scripts, attachments, forms, or encryption are not accepted.');
                }
            } finally {
                if (is_file($staging)) {
                    @unlink($staging);
                }
            }
            if (filesize($target) > $maxPdfSize) {
                @unlink($target);
                throw new RuntimeException('The PDF is too large after processing.');
            }
            $mime = 'application/pdf';
        } else {
            if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
                throw new RuntimeException('Image processing is unavailable on this server.');
            }
            $image = @imagecreatefromstring((string) file_get_contents($tmp));
            if ($image === false) {
                throw new RuntimeException('The uploaded image could not be processed.');
            }
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, $maxDimension / max($width, $height));
            if ($scale < 1) {
                $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);
                imagedestroy($image);
                $image = $resized;
            }
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            $target = $directory . DIRECTORY_SEPARATOR . $name . '.webp';
            $saved = false;
            foreach ([86, 80, 74, 68] as $quality) {
                if (imagewebp($image, $target, $quality) && filesize($target) <= $maxImageSize) {
                    $saved = true;
                    break;
                }
            }
            imagedestroy($image);
            if (!$saved) {
                @unlink($target);
                throw new RuntimeException('Unable to compress the image below the permitted size.');
            }
            $mime = 'image/webp';
        }

        @chmod($target, 0640);
        clearstatcache(true, $target);

        return [
            'key' => 'uploads/' . $subdir . '/' . basename($target),
            'mime' => $mime,
            'size' => (int) filesize($target),
            'sha256' => (string) hash_file('sha256', $target),
            'original_name' => $originalName,
        ];
    }
}

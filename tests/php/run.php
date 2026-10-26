<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('AIT_TESTING', true);
require_once __DIR__ . '/../../backend/security.php';
require_once __DIR__ . '/../../backend/rbac.php';
ait_start_secure_session();

$passed = 0;
$failed = [];

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  \033[32mPASS\033[0m {$name}\n";
    } catch (Throwable $e) {
        $failed[] = $name;
        echo "  \033[31mFAIL {$name}\033[0m\n      " . $e->getMessage() . "\n";
    }
}

function check(bool $condition, string $message = 'assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function throws(callable $fn, string $contains = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($contains === '' || stripos($e->getMessage(), $contains) !== false, 'unexpected message: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('expected an exception');
}

function fake_upload(string $name, string $bytes): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'aitup');
    file_put_contents($tmp, $bytes);
    return ['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}

function png_bytes(int $w = 64, int $h = 64): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 11, 119, 114));
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

function pdf_bytes(string $extra = ''): string
{
    $body = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R {$extra} >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    return $body . str_repeat(' ', 80);
}

function store(array $file, string $subdir = 'tests', array $options = []): array
{
    return ait_store_uploaded_asset($file, $subdir, $options);
}

echo "\nUpload pipeline\n";
test('accepts a real PNG and re-encodes it to WebP under a random name', function () {
    $r = store(fake_upload('My Photo (1).png', png_bytes()));
    check($r['mime'] === 'image/webp', 'mime ' . $r['mime']);
    check((bool) preg_match('#^uploads/tests/[a-f0-9]{32}\.webp$#', $r['key']), 'key ' . $r['key']);
    check($r['original_name'] === 'My Photo (1).png', 'name ' . $r['original_name']);
    check(ait_storage_path($r['key']) !== null, 'stored file missing');
    ait_storage_delete($r['key']);
    check(ait_storage_path($r['key']) === null, 'file not deleted');
});
test('rejects a PHP script renamed to .jpg (magic bytes)', fn() => throws(fn() => store(fake_upload('shell.jpg', "<?php system(\$_GET['c']); ?>" . str_repeat('A', 100))), 'do not match'));
test('rejects a PNG uploaded with a .pdf extension', fn() => throws(fn() => store(fake_upload('scan.pdf', png_bytes())), 'do not match'));
test('rejects disallowed extensions (.svg .html .exe .php .phtml .jpg.php)', function () {
    foreach (['x.svg', 'x.html', 'x.exe', 'x.php', 'x.phtml', 'x.jpg.php'] as $name) {
        throws(fn() => store(fake_upload($name, png_bytes())), 'not accepted');
    }
});
test('rejects a GIF/PHP polyglot under an image extension', fn() => throws(fn() => store(fake_upload('a.png', 'GIF89a<?php echo 1; ?>' . str_repeat('A', 100))), 'do not match'));
test('re-encoding strips payloads appended to a valid PNG', function () {
    $r = store(fake_upload('trail.png', png_bytes() . '<?php echo "pwned"; ?>'));
    $bytes = (string) file_get_contents((string) ait_storage_path($r['key']));
    check(!str_contains($bytes, '<?php'), 'payload survived');
    ait_storage_delete($r['key']);
});
test('rejects images with unsupported pixel dimensions', fn() => throws(fn() => store(fake_upload('tiny.png', png_bytes(8, 8))), 'dimensions'));
test('enforces the image size limit', fn() => throws(fn() => store(fake_upload('big.png', png_bytes()), 'tests', ['max_image_size' => 100]), 'MB or smaller'));
test('rejects empty files', fn() => throws(fn() => store(fake_upload('empty.png', '')), 'empty'));
test('rejects PDFs with JavaScript, launch actions, or attachments (incl. hex-escaped names)', function () {
    throws(fn() => store(fake_upload('a.pdf', pdf_bytes('/OpenAction << /S /JavaScript /JS (app.alert(1)) >>'))), 'scripts');
    throws(fn() => store(fake_upload('b.pdf', pdf_bytes('/AA << /O << /S /J#61vaScript >> >>'))), 'scripts');
    throws(fn() => store(fake_upload('c.pdf', pdf_bytes('/Names << /EmbeddedFiles 9 0 R >>'))), 'scripts');
    throws(fn() => store(fake_upload('d.pdf', pdf_bytes('/Launch << /F (cmd.exe) >>'))), 'scripts');
});
test('accepts a clean PDF and rebuilds it through Ghostscript (CDR)', function () {
    $r = store(fake_upload('result.pdf', pdf_bytes()));
    check($r['mime'] === 'application/pdf');
    $bytes = (string) file_get_contents((string) ait_storage_path($r['key']));
    check(str_starts_with($bytes, '%PDF-'), 'not a PDF');
    check(str_contains($bytes, 'Ghostscript'), 'PDF was not reconstructed by Ghostscript');
    ait_storage_delete($r['key']);
});
test('ClamAV flags the EICAR test file and passes a clean one', function () {
    if ((string) ait_env('CLAMAV_HOST', '') === '') {
        throw new RuntimeException('CLAMAV_HOST not set; run inside the Docker stack');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'eicar');
    file_put_contents($tmp, 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');
    check(ait_clamav_scan($tmp) === false, 'EICAR not detected');
    file_put_contents($tmp, png_bytes());
    check(ait_clamav_scan($tmp) === true, 'clean file flagged');
    unlink($tmp);
});
test('sanitises display filenames', function () {
    check(ait_sanitize_filename('../../etc/passwd') === 'passwd');
    check(!str_contains(ait_sanitize_filename("evil\x00name<script>.pdf"), '<'));
    check(ait_sanitize_filename('...') === 'document');
    check(mb_strlen(ait_sanitize_filename(str_repeat('a', 300) . '.png')) <= 120);
});
test('storage keys cannot escape the upload root', function () {
    foreach (['uploads/../.env', '../uploads/x', '/etc/passwd', 'uploads/a/../../b', 'uploads/', 'uploads/x%00.png', 'assets/x.png'] as $key) {
        check(ait_storage_key_normalize($key) === null, 'accepted ' . $key);
    }
    check(ait_storage_key_normalize('./uploads/challans/ab.webp') === 'uploads/challans/ab.webp');
});

echo "\nPasswords\n";
test('policy rejects short, single-class, common, and personal passwords', function () {
    check(ait_password_policy_error('Short1A') !== null);
    check(ait_password_policy_error('alllowercase123') !== null);
    check(ait_password_policy_error('Password12345') !== null);
    check(ait_password_policy_error('AyeshaKhan2026', ['Ayesha Khan', 'ayesha']) !== null);
    check(ait_password_policy_error('Lantern-Orbit-58') === null);
});
test('hashes with Argon2id (or bcrypt cost 12) and verifies', function () {
    $hash = ait_password_hash('Lantern-Orbit-58');
    check(str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$2y$12$'), $hash);
    check(ait_password_verify('Lantern-Orbit-58', $hash));
    check(!ait_password_verify('wrong', $hash));
    check(!ait_password_verify('anything', null));
});
test('a missing account still costs a full hash check (no timing oracle)', function () {
    $t = microtime(true);
    ait_password_verify('x', null);
    check(microtime(true) - $t > 0.05, 'dummy verify returned too fast');
});
test('temporary passwords are long, unique, and policy compliant', function () {
    $seen = [];
    for ($i = 0; $i < 50; $i++) {
        $p = ait_generate_temp_password();
        check(strlen($p) >= 14 && ait_password_policy_error($p) === null, $p);
        $seen[$p] = true;
    }
    check(count($seen) === 50, 'duplicates generated');
});

echo "\nRate limiting, CSRF, captcha\n";
test('rate limiter counts per key and resets after the window', function () {
    $key = 'test|' . bin2hex(random_bytes(6));
    [$c1] = ait_rate_limit_hit($key, 2);
    [$c2] = ait_rate_limit_hit($key, 2);
    check($c1 === 1 && $c2 === 2, "counts {$c1},{$c2}");
    sleep(3);
    [$c3] = ait_rate_limit_hit($key, 2);
    check($c3 === 1, "window did not reset ({$c3})");
});
test('rate limiter never loses an increment across 25 hits', function () {
    $key = 'seq|' . bin2hex(random_bytes(6));
    for ($i = 0; $i < 25; $i++) {
        ait_rate_limit_hit($key, 60);
    }
    [$n] = ait_rate_limit_hit($key, 60);
    check($n === 26, "got {$n}");
});
test('CSRF tokens are 256-bit and stable within a session', function () {
    $_SESSION = [];
    $a = ait_init_csrf_token();
    check(strlen($a) === 64 && ctype_xdigit($a));
    check(ait_init_csrf_token() === $a);
});
test('captcha enforces honeypot, signature, timing, and answer', function () {
    $_SESSION = [];
    $html = ait_captcha_field('t');
    preg_match('/name="form_ts" value="([^"]+)"/', $html, $ts);
    preg_match('/What is <strong>(\d) \+ (\d)<\/strong>/', $html, $q);
    $_POST = ['form_ts' => $ts[1], 'captcha_answer' => (string) ($q[1] + $q[2]), 'website' => ''];
    check(ait_captcha_verify('t', 0) === null, 'valid submission rejected');

    ait_captcha_field('t');
    $_POST['website'] = 'http://spam.example';
    check(ait_captcha_verify('t', 0) !== null, 'honeypot ignored');

    $html = ait_captcha_field('t');
    preg_match('/name="form_ts" value="([^"]+)"/', $html, $ts);
    $_POST = ['form_ts' => $ts[1], 'captcha_answer' => '99', 'website' => ''];
    check(ait_captcha_verify('t', 0) !== null, 'wrong answer accepted');

    $_POST = ['form_ts' => time() . '.forged', 'captcha_answer' => '1', 'website' => ''];
    check(ait_captcha_verify('t', 0) !== null, 'forged timestamp accepted');

    ait_captcha_field('t');
    $_POST = ['form_ts' => $ts[1], 'captcha_answer' => '1', 'website' => ''];
    check(ait_captcha_verify('t', 30) !== null, 'too-fast submission accepted');
});

echo "\nCache\n";
test('cache layer round-trips values and forgets them', function () {
    $k = 'unit:' . bin2hex(random_bytes(4));
    $calls = 0;
    $producer = function () use (&$calls) {
        $calls++;
        return ['v' => 42];
    };
    check(ait_cache_remember($k, 60, $producer) === ['v' => 42]);
    check(ait_cache_remember($k, 60, $producer) === ['v' => 42]);
    check($calls === 1, "producer ran {$calls} times");
    ait_cache_forget($k);
    ait_cache_remember($k, 60, $producer);
    check($calls === 2, 'forget did not clear');
    echo '      cache backend: ' . ait_cache_backend() . "\n";
});

echo "\n" . $passed . ' passed, ' . count($failed) . " failed\n";
exit($failed === [] ? 0 : 1);

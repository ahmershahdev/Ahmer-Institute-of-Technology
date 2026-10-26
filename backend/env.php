<?php

if (!function_exists('ait_load_env')) {
    function ait_load_env(string $path): array
    {
        static $loaded = null;

        if ($loaded !== null) {
            return $loaded;
        }

        $loaded = [];

        if (!is_file($path)) {
            return $loaded;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $loaded;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }

            if (str_contains($trimmed, '=')) {
                [$key, $value] = explode('=', $trimmed, 2);
                $key = trim($key);
                $value = trim($value);

                if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $value = substr($value, 1, -1);
                }

                $loaded[$key] = $value;
                if (getenv($key) === false) {
                    putenv($key . '=' . $value);
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return $loaded;
    }
}

if (!function_exists('ait_env')) {
    function ait_env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (!function_exists('ait_base_path')) {
    /** Web path of the application root ("/AIT" on XAMPP, "" on a dedicated vhost). */
    function ait_base_path(): string
    {
        $configured = ait_env('APP_BASE_PATH', null);
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured === '/' ? '' : $configured, '/');
        }
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        $dir = preg_replace('#/(pages|student|admin|teachers|staff|errors)(/partials)?$#', '', $dir) ?? '';
        return rtrim($dir, '/');
    }
}

if (!function_exists('ait_url')) {
    /** App-relative URL. Static assets get a content fingerprint so they can be cached for a year. */
    function ait_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $url = ait_base_path() . '/' . $path;
        if (str_starts_with($path, 'assets/') && !str_contains($path, '?')) {
            $file = dirname(__DIR__) . '/' . $path;
            $mtime = is_file($file) ? (int) filemtime($file) : 0;
            if ($mtime > 0) {
                $url .= '?v=' . substr(hash('crc32b', $path . $mtime), 0, 8);
            }
        }
        return $url;
    }
}

if (!function_exists('ait_redirect')) {
    /** Absolute, app-relative redirect, so it resolves the same from clean URLs and real file paths. */
    function ait_redirect(string $path, int $status = 302): never
    {
        header('Location: ' . ait_url($path), true, $status);
        exit;
    }
}

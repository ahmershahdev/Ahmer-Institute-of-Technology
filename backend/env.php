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

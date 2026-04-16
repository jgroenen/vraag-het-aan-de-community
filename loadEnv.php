<?php

/**
 * Simple .env file loader
 * Loads environment variables from .env file into $_ENV for CLI usage
 */
function loadEnv(string $path = __DIR__ . '/.env'): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set in $_ENV and putenv for CLI usage
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            putenv("$key=$value");
        }
    }
}

// Auto-load when this file is included
loadEnv();

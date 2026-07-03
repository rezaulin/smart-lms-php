<?php

namespace App\Middleware;

use App\Utils\Response;

class RateLimiter
{
    private static array $requests = [];

    public static function check(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Clean old requests
        if (isset(self::$requests[$key])) {
            self::$requests[$key] = array_filter(
                self::$requests[$key],
                fn($timestamp) => $timestamp > $windowStart
            );
        } else {
            self::$requests[$key] = [];
        }

        // Check limit
        if (count(self::$requests[$key]) >= $maxRequests) {
            return false;
        }

        // Add current request
        self::$requests[$key][] = $now;
        return true;
    }

    public static function handleGlobal(): void
    {
        $ip = self::getClientIP();
        $maxRequests = (int) ($_ENV['RATE_LIMIT_GLOBAL'] ?? 200);

        if (!self::check("global:{$ip}", $maxRequests, 60)) {
            Response::rateLimitExceeded('60s');
        }
    }

    public static function handleAuth(): void
    {
        $ip = self::getClientIP();
        $maxRequests = (int) ($_ENV['RATE_LIMIT_AUTH'] ?? 5);

        if (!self::check("auth:{$ip}", $maxRequests, 60)) {
            Response::error('Terlalu banyak percobaan login. Tunggu 1 menit.', 429, 'AUTH_RATE_LIMIT');
        }
    }

    private static function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

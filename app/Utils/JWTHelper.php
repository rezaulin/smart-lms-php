<?php

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper
{
    private static function getSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this';
    }

    private static function getExpiry(): int
    {
        return (int) ($_ENV['JWT_EXPIRY'] ?? 86400); // 24 hours default
    }

    public static function encode(array $payload): string
    {
        $now = time();
        $expiry = $now + self::getExpiry();

        $token = [
            'iat' => $now,
            'exp' => $expiry,
            'data' => $payload,
        ];

        return JWT::encode($token, self::getSecret(), 'HS256');
    }

    public static function decode(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecret(), 'HS256'));
            return $decoded->data ?? null;
        } catch (\Exception $e) {
            error_log("JWT decode failed: " . $e->getMessage());
            return null;
        }
    }

    public static function verify(string $token): bool
    {
        try {
            JWT::decode($token, new Key(self::getSecret(), 'HS256'));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            return null;
        }

        // Bearer token format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

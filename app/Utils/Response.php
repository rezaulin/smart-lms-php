<?php

namespace App\Utils;

class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, string $message = 'Success', int $status = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, ?string $code = null): void
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($code) {
            $response['code'] = $code;
        }

        self::json($response, $status);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403, 'FORBIDDEN');
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404, 'NOT_FOUND');
    }

    public static function rateLimitExceeded(string $retryIn = '60s'): void
    {
        self::json([
            'success' => false,
            'error' => 'Terlalu banyak request. Coba lagi nanti.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retryIn' => $retryIn,
        ], 429);
    }
}

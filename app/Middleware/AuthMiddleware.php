<?php

namespace App\Middleware;

use App\Utils\JWTHelper;
use App\Utils\Response;

class AuthMiddleware
{
    public static function handle(): ?object
    {
        $token = JWTHelper::getTokenFromHeader();

        if (!$token) {
            Response::unauthorized('Token tidak ditemukan');
            return null;
        }

        $userData = JWTHelper::decode($token);

        if (!$userData) {
            Response::unauthorized('Token tidak valid atau sudah kadaluarsa');
            return null;
        }

        return $userData;
    }

    public static function requireRole(array $allowedRoles): ?object
    {
        $user = self::handle();

        if (!$user) {
            return null;
        }

        if (!in_array($user->role, $allowedRoles)) {
            Response::forbidden('Anda tidak memiliki akses ke resource ini');
            return null;
        }

        return $user;
    }
}

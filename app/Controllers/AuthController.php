<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Utils\JWTHelper;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimiter;

class AuthController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function login(): void
    {
        RateLimiter::handleAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            Response::error('Email dan password harus diisi', 400);
        }

        // Get user from database
        $stmt = $this->db->prepare("
            SELECT u.*, s.name as school_name
            FROM users u
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE u.email = :email AND u.deleted_at IS NULL AND u.active = 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Email atau password salah', 401);
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            Response::error('Email atau password salah', 401);
        }

        // Generate JWT token
        $token = JWTHelper::encode([
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'school_id' => $user['school_id'],
            'name' => $user['name'],
        ]);

        // Remove password from response
        unset($user['password']);

        Response::success([
            'token' => $token,
            'user' => $user,
        ], 'Login berhasil');
    }

    public function parentLogin(): void
    {
        RateLimiter::handleAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        $accessCode = $input['access_code'] ?? '';

        if (!$accessCode) {
            Response::error('Kode akses harus diisi', 400);
        }

        // Get parent access
        $stmt = $this->db->prepare("
            SELECT pa.*, s.name as student_name, s.nis, s.nisn,
                   c.name as class_name, sc.name as school_name
            FROM parent_access pa
            JOIN students s ON pa.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            JOIN schools sc ON pa.school_id = sc.id
            WHERE pa.access_code = :code AND pa.deleted_at IS NULL
        ");
        $stmt->execute(['code' => $accessCode]);
        $parentAccess = $stmt->fetch();

        if (!$parentAccess) {
            Response::error('Kode akses tidak valid', 401);
        }

        // Generate JWT token
        $token = JWTHelper::encode([
            'id' => $parentAccess['id'],
            'role' => 'orang_tua',
            'student_id' => $parentAccess['student_id'],
            'school_id' => $parentAccess['school_id'],
            'name' => $parentAccess['parent_name'],
        ]);

        Response::success([
            'token' => $token,
            'parent' => $parentAccess,
        ], 'Login berhasil');
    }

    public function getProfile(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Get full user data
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email, u.role, u.phone, u.avatar, 
                   u.school_id, u.student_id, u.active, u.created_at,
                   s.name as school_name
            FROM users u
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE u.id = :id AND u.deleted_at IS NULL
        ");
        $stmt->execute(['id' => $user->id]);
        $userData = $stmt->fetch();

        if (!$userData) {
            Response::notFound('User tidak ditemukan');
        }

        Response::success($userData);
    }

    public function updateProfile(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $input = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = ['id' => $user->id];

        if (isset($input['name'])) {
            $updateFields[] = 'name = :name';
            $params['name'] = $input['name'];
        }

        if (isset($input['phone'])) {
            $updateFields[] = 'phone = :phone';
            $params['phone'] = $input['phone'];
        }

        if (isset($input['avatar'])) {
            $updateFields[] = 'avatar = :avatar';
            $params['avatar'] = $input['avatar'];
        }

        if (empty($updateFields)) {
            Response::error('Tidak ada data yang diupdate', 400);
        }

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        Response::success(null, 'Profile berhasil diupdate');
    }

    public function changePassword(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $input = json_decode(file_get_contents('php://input'), true);
        $oldPassword = $input['old_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if (!$oldPassword || !$newPassword) {
            Response::error('Password lama dan baru harus diisi', 400);
        }

        if (strlen($newPassword) < 6) {
            Response::error('Password baru minimal 6 karakter', 400);
        }

        // Verify old password
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute(['id' => $user->id]);
        $userData = $stmt->fetch();

        if (!password_verify($oldPassword, $userData['password'])) {
            Response::error('Password lama salah', 400);
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password = :password, 
                must_change_password = 0,
                password_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'password' => $hashedPassword,
            'id' => $user->id,
        ]);

        Response::success(null, 'Password berhasil diubah');
    }
}

<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

class SemesterController
{
    /**
     * Get all semesters
     * GET /api/semesters
     */
    public static function index(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        try {
            $stmt = $db->prepare("
                SELECT 
                    id,
                    name,
                    start_date,
                    end_date,
                    is_active,
                    created_at,
                    updated_at
                FROM semesters
                WHERE school_id = ? AND deleted_at IS NULL
                ORDER BY start_date DESC
            ");
            $stmt->execute([$schoolId]);
            $semesters = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $semesters
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active semester
     * GET /api/semesters/active
     */
    public static function active(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        try {
            $stmt = $db->prepare("
                SELECT * 
                FROM semesters
                WHERE school_id = ? AND is_active = 1 AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$schoolId]);
            $semester = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$semester) {
                Response::json(['success' => false, 'message' => 'No active semester'], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $semester
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new semester
     * POST /api/semesters
     * Body: {name, start_date, end_date, is_active}
     */
    public static function store(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Role check
        if (!in_array($user->role, ['admin_pusat', 'admin_cabang'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Parse JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        // Validation
        $required = ['name', 'start_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $db->beginTransaction();

            // If set as active, deactivate other semesters
            if (!empty($input['is_active'])) {
                $stmt = $db->prepare("
                    UPDATE semesters 
                    SET is_active = 0 
                    WHERE school_id = ?
                ");
                $stmt->execute([$schoolId]);
            }

            // Create semester
            $stmt = $db->prepare("
                INSERT INTO semesters (name, start_date, end_date, is_active, school_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['start_date'],
                $input['end_date'],
                !empty($input['is_active']) ? 1 : 0,
                $schoolId
            ]);
            $semesterId = $db->lastInsertId();

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Semester created successfully',
                'data' => ['id' => $semesterId]
            ], 201);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update semester
     * PUT /api/semesters/:id
     * Body: {name, start_date, end_date, is_active}
     */
    public static function update(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Role check
        if (!in_array($user->role, ['admin_pusat', 'admin_cabang'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Get ID from path
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $id = end($segments);

        if (!is_numeric($id)) {
            Response::json(['success' => false, 'message' => 'Invalid ID'], 400);
            return;
        }

        // Parse JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $db->beginTransaction();

            // Check semester exists
            $stmt = $db->prepare("
                SELECT id 
                FROM semesters 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Semester not found'], 404);
                return;
            }

            // If set as active, deactivate other semesters
            if (!empty($input['is_active'])) {
                $stmt = $db->prepare("
                    UPDATE semesters 
                    SET is_active = 0 
                    WHERE school_id = ? AND id != ?
                ");
                $stmt->execute([$schoolId, $id]);
            }

            // Update semester
            $stmt = $db->prepare("
                UPDATE semesters 
                SET name = COALESCE(?, name),
                    start_date = COALESCE(?, start_date),
                    end_date = COALESCE(?, end_date),
                    is_active = COALESCE(?, is_active),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['name'] ?? null,
                $input['start_date'] ?? null,
                $input['end_date'] ?? null,
                isset($input['is_active']) ? (int)$input['is_active'] : null,
                $id
            ]);

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Semester updated successfully'
            ]);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete semester (soft delete)
     * DELETE /api/semesters/:id
     */
    public static function destroy(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Role check
        if (!in_array($user->role, ['admin_pusat', 'admin_cabang'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Get ID from path
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $id = end($segments);

        if (!is_numeric($id)) {
            Response::json(['success' => false, 'message' => 'Invalid ID'], 400);
            return;
        }

        try {
            // Check semester exists
            $stmt = $db->prepare("
                SELECT id 
                FROM semesters 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Semester not found'], 404);
                return;
            }

            // Soft delete semester
            $stmt = $db->prepare("
                UPDATE semesters 
                SET deleted_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            Response::json([
                'success' => true,
                'message' => 'Semester deleted successfully'
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}

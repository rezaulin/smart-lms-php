<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

class SubjectController
{
    /**
     * Get all subjects
     * GET /api/subjects
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
                    code,
                    name,
                    description,
                    created_at,
                    updated_at
                FROM subjects
                WHERE school_id = ? AND deleted_at IS NULL
                ORDER BY name
            ");
            $stmt->execute([$schoolId]);
            $subjects = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $subjects
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new subject
     * POST /api/subjects
     * Body: {code, name, description}
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
        $required = ['code', 'name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO subjects (code, name, description, school_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['code'],
                $input['name'],
                $input['description'] ?? null,
                $schoolId
            ]);
            $subjectId = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Subject created successfully',
                'data' => ['id' => $subjectId]
            ], 201);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update subject
     * PUT /api/subjects/:id
     * Body: {code, name, description}
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
            // Check subject exists
            $stmt = $db->prepare("
                SELECT id 
                FROM subjects 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Subject not found'], 404);
                return;
            }

            // Update subject
            $stmt = $db->prepare("
                UPDATE subjects 
                SET code = COALESCE(?, code),
                    name = COALESCE(?, name),
                    description = COALESCE(?, description),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['code'] ?? null,
                $input['name'] ?? null,
                $input['description'] ?? null,
                $id
            ]);

            Response::json([
                'success' => true,
                'message' => 'Subject updated successfully'
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete subject (soft delete)
     * DELETE /api/subjects/:id
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
            // Check subject exists
            $stmt = $db->prepare("
                SELECT id 
                FROM subjects 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Subject not found'], 404);
                return;
            }

            // Soft delete subject
            $stmt = $db->prepare("
                UPDATE subjects 
                SET deleted_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            Response::json([
                'success' => true,
                'message' => 'Subject deleted successfully'
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}

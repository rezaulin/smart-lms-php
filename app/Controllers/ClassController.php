<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

class ClassController
{
    /**
     * Get all classes
     * GET /api/classes
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
                    c.id,
                    c.name,
                    c.level,
                    c.major,
                    c.capacity,
                    c.teacher_id,
                    u.name as teacher_name,
                    (SELECT COUNT(*) FROM students WHERE class_id = c.id AND deleted_at IS NULL) as student_count,
                    c.created_at,
                    c.updated_at
                FROM classes c
                LEFT JOIN teachers t ON c.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE c.school_id = ? AND c.deleted_at IS NULL
                ORDER BY c.name
            ");
            $stmt->execute([$schoolId]);
            $classes = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $classes
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single class by ID
     * GET /api/classes/:id
     */
    public static function show(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

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
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    u.name as teacher_name,
                    t.nip as teacher_nip
                FROM classes c
                LEFT JOIN teachers t ON c.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE c.id = ? AND c.school_id = ? AND c.deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $class = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$class) {
                Response::json(['success' => false, 'message' => 'Class not found'], 404);
                return;
            }

            // Get students count
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM students 
                WHERE class_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id]);
            $class->student_count = (int)$stmt->fetchColumn();

            Response::json([
                'success' => true,
                'data' => $class
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new class
     * POST /api/classes
     * Body: {name, level, major, capacity, teacher_id}
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
        if (empty($input['name'])) {
            Response::json(['success' => false, 'message' => 'Field name is required'], 400);
            return;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO classes (name, level, major, capacity, teacher_id, school_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['level'] ?? null,
                $input['major'] ?? null,
                $input['capacity'] ?? 36,
                $input['teacher_id'] ?? null,
                $schoolId
            ]);
            $classId = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Class created successfully',
                'data' => ['id' => $classId]
            ], 201);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update class
     * PUT /api/classes/:id
     * Body: {name, level, major, capacity, teacher_id}
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
            // Check class exists
            $stmt = $db->prepare("
                SELECT id 
                FROM classes 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Class not found'], 404);
                return;
            }

            // Update class
            $stmt = $db->prepare("
                UPDATE classes 
                SET name = COALESCE(?, name),
                    level = COALESCE(?, level),
                    major = COALESCE(?, major),
                    capacity = COALESCE(?, capacity),
                    teacher_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['name'] ?? null,
                $input['level'] ?? null,
                $input['major'] ?? null,
                $input['capacity'] ?? null,
                $input['teacher_id'] ?? null,
                $id
            ]);

            Response::json([
                'success' => true,
                'message' => 'Class updated successfully'
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete class (soft delete)
     * DELETE /api/classes/:id
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
            // Check class exists
            $stmt = $db->prepare("
                SELECT id 
                FROM classes 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Class not found'], 404);
                return;
            }

            // Check if class has students
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM students 
                WHERE class_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id]);
            $studentCount = (int)$stmt->fetchColumn();

            if ($studentCount > 0) {
                Response::json([
                    'success' => false,
                    'message' => "Cannot delete class with {$studentCount} students. Remove students first."
                ], 400);
                return;
            }

            // Soft delete class
            $stmt = $db->prepare("
                UPDATE classes 
                SET deleted_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            Response::json([
                'success' => true,
                'message' => 'Class deleted successfully'
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}

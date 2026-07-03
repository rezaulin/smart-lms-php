<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

class TeacherController
{
    /**
     * Get all teachers (with filters)
     * GET /api/teachers?search=nama
     */
    public static function index(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Filters
        $search = $_GET['search'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;

        try {
            // Build query
            $where = ["t.school_id = ?"];
            $params = [$schoolId];

            if ($search) {
                $where[] = "(u.name LIKE ? OR t.nip LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $where[] = "t.deleted_at IS NULL";
            $whereSQL = implode(' AND ', $where);

            // Count total
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                WHERE {$whereSQL}
            ");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            // Fetch teachers
            $stmt = $db->prepare("
                SELECT 
                    t.id,
                    t.user_id,
                    t.nip,
                    u.name,
                    u.email,
                    u.phone,
                    u.avatar,
                    u.active,
                    t.created_at,
                    t.updated_at
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                WHERE {$whereSQL}
                ORDER BY u.name
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $teachers = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Get subjects for each teacher
            foreach ($teachers as $teacher) {
                $stmt = $db->prepare("
                    SELECT s.id, s.code, s.name
                    FROM subjects s
                    INNER JOIN teacher_subjects ts ON s.id = ts.subject_id
                    WHERE ts.teacher_id = ?
                ");
                $stmt->execute([$teacher->id]);
                $teacher->subjects = $stmt->fetchAll(\PDO::FETCH_OBJ);
            }

            Response::json([
                'success' => true,
                'data' => $teachers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single teacher by ID
     * GET /api/teachers/:id
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
                    t.*,
                    u.name,
                    u.email,
                    u.phone,
                    u.avatar,
                    u.active
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                WHERE t.id = ? AND t.school_id = ? AND t.deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $teacher = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$teacher) {
                Response::json(['success' => false, 'message' => 'Teacher not found'], 404);
                return;
            }

            // Get subjects
            $stmt = $db->prepare("
                SELECT s.id, s.code, s.name
                FROM subjects s
                INNER JOIN teacher_subjects ts ON s.id = ts.subject_id
                WHERE ts.teacher_id = ?
            ");
            $stmt->execute([$id]);
            $teacher->subjects = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $teacher
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new teacher
     * POST /api/teachers
     * Body: {name, email, nip, phone, subject_ids: [1,2,3]}
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
        $required = ['name', 'nip'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $db->beginTransaction();

            // Default password: nip
            $password = password_hash($input['nip'], PASSWORD_BCRYPT);

            // Create user
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, role, phone, active, school_id)
                VALUES (?, ?, ?, 'guru', ?, 1, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['email'] ?? null,
                $password,
                $input['phone'] ?? null,
                $schoolId
            ]);
            $userId = $db->lastInsertId();

            // Create teacher
            $stmt = $db->prepare("
                INSERT INTO teachers (user_id, nip, school_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $input['nip'],
                $schoolId
            ]);
            $teacherId = $db->lastInsertId();

            // Assign subjects
            if (!empty($input['subject_ids']) && is_array($input['subject_ids'])) {
                $stmt = $db->prepare("
                    INSERT INTO teacher_subjects (teacher_id, subject_id)
                    VALUES (?, ?)
                ");
                foreach ($input['subject_ids'] as $subjectId) {
                    $stmt->execute([$teacherId, $subjectId]);
                }
            }

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Teacher created successfully',
                'data' => [
                    'id' => $teacherId,
                    'user_id' => $userId,
                    'default_password' => $input['nip']
                ]
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
     * Update teacher
     * PUT /api/teachers/:id
     * Body: {name, email, nip, phone, subject_ids: [1,2,3]}
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

            // Check teacher exists
            $stmt = $db->prepare("
                SELECT id, user_id 
                FROM teachers 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $teacher = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$teacher) {
                Response::json(['success' => false, 'message' => 'Teacher not found'], 404);
                return;
            }

            // Update user
            if (!empty($input['name']) || !empty($input['email']) || !empty($input['phone'])) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = COALESCE(?, name), 
                        email = COALESCE(?, email),
                        phone = COALESCE(?, phone),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $input['name'] ?? null,
                    $input['email'] ?? null,
                    $input['phone'] ?? null,
                    $teacher->user_id
                ]);
            }

            // Update teacher
            if (!empty($input['nip'])) {
                $stmt = $db->prepare("
                    UPDATE teachers 
                    SET nip = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$input['nip'], $id]);
            }

            // Update subjects (clear + re-assign)
            if (isset($input['subject_ids']) && is_array($input['subject_ids'])) {
                // Clear existing
                $stmt = $db->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
                $stmt->execute([$id]);

                // Re-assign
                if (!empty($input['subject_ids'])) {
                    $stmt = $db->prepare("
                        INSERT INTO teacher_subjects (teacher_id, subject_id)
                        VALUES (?, ?)
                    ");
                    foreach ($input['subject_ids'] as $subjectId) {
                        $stmt->execute([$id, $subjectId]);
                    }
                }
            }

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Teacher updated successfully'
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
     * Delete teacher (soft delete)
     * DELETE /api/teachers/:id
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
            $db->beginTransaction();

            // Check teacher exists
            $stmt = $db->prepare("
                SELECT id, user_id 
                FROM teachers 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $teacher = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$teacher) {
                Response::json(['success' => false, 'message' => 'Teacher not found'], 404);
                return;
            }

            // Soft delete teacher
            $stmt = $db->prepare("
                UPDATE teachers 
                SET deleted_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            // Soft delete user
            $stmt = $db->prepare("
                UPDATE users 
                SET deleted_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$teacher->user_id]);

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Teacher deleted successfully'
            ]);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}

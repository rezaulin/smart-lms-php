<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

class StudentController
{
    /**
     * Get all students (with filters)
     * GET /api/students?class_id=1&search=nama
     */
    public static function index(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Filters
        $classId = $_GET['class_id'] ?? null;
        $search = $_GET['search'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;

        try {
            // Build query
            $where = ["s.school_id = ?"];
            $params = [$schoolId];

            if ($classId) {
                $where[] = "s.class_id = ?";
                $params[] = $classId;
            }

            if ($search) {
                $where[] = "(u.name LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $where[] = "s.deleted_at IS NULL";
            $whereSQL = implode(' AND ', $where);

            // Count total
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM students s
                INNER JOIN users u ON s.user_id = u.id
                WHERE {$whereSQL}
            ");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            // Fetch students
            $stmt = $db->prepare("
                SELECT 
                    s.id,
                    s.user_id,
                    u.name,
                    u.email,
                    u.phone,
                    u.avatar,
                    u.active,
                    s.nis,
                    s.nisn,
                    s.gender,
                    s.birth_date,
                    s.address,
                    s.class_id,
                    c.name as class_name,
                    s.created_at,
                    s.updated_at
                FROM students s
                INNER JOIN users u ON s.user_id = u.id
                LEFT JOIN classes c ON s.class_id = c.id
                WHERE {$whereSQL}
                ORDER BY u.name
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $students = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $students,
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
     * Get single student by ID
     * GET /api/students/:id
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
                    s.*,
                    u.name,
                    u.email,
                    u.phone,
                    u.avatar,
                    u.active,
                    u.student_id as user_student_id,
                    c.name as class_name,
                    c.level as class_level
                FROM students s
                INNER JOIN users u ON s.user_id = u.id
                LEFT JOIN classes c ON s.class_id = c.id
                WHERE s.id = ? AND s.school_id = ? AND s.deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $student = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$student) {
                Response::json(['success' => false, 'message' => 'Student not found'], 404);
                return;
            }

            // Get parent access codes
            $stmt = $db->prepare("
                SELECT id, parent_name, phone, relation, access_code
                FROM parent_access
                WHERE student_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id]);
            $student->parent_access = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $student
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new student
     * POST /api/students
     * Body: {name, email, nis, nisn, class_id, gender, birth_date, address, phone}
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
        $required = ['name', 'nisn'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $db->beginTransaction();

            // Generate student_id (6 digits)
            $stmt = $db->prepare("
                SELECT MAX(CAST(student_id AS UNSIGNED)) as max_id 
                FROM users 
                WHERE school_id = ? AND role = 'siswa'
            ");
            $stmt->execute([$schoolId]);
            $maxId = $stmt->fetchColumn();
            $studentId = str_pad(($maxId ?? 0) + 1, 6, '0', STR_PAD_LEFT);

            // Default password: nisn
            $password = password_hash($input['nisn'], PASSWORD_BCRYPT);

            // Create user
            $stmt = $db->prepare("
                INSERT INTO users (name, email, student_id, password, role, phone, active, school_id)
                VALUES (?, ?, ?, ?, 'siswa', ?, 1, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['email'] ?? null,
                $studentId,
                $password,
                $input['phone'] ?? null,
                $schoolId
            ]);
            $userId = $db->lastInsertId();

            // Create student
            $stmt = $db->prepare("
                INSERT INTO students (user_id, nis, nisn, class_id, school_id, gender, birth_date, address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $input['nis'] ?? null,
                $input['nisn'],
                $input['class_id'] ?? null,
                $schoolId,
                $input['gender'] ?? null,
                $input['birth_date'] ?? null,
                $input['address'] ?? null
            ]);
            $newStudentId = $db->lastInsertId();

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => [
                    'id' => $newStudentId,
                    'user_id' => $userId,
                    'student_id' => $studentId,
                    'default_password' => $input['nisn']
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
     * Update student
     * PUT /api/students/:id
     * Body: {name, nis, nisn, class_id, gender, birth_date, address, phone}
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

            // Check student exists
            $stmt = $db->prepare("
                SELECT id, user_id 
                FROM students 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $student = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$student) {
                Response::json(['success' => false, 'message' => 'Student not found'], 404);
                return;
            }

            // Update user
            if (!empty($input['name']) || !empty($input['phone'])) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = COALESCE(?, name), 
                        phone = COALESCE(?, phone),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $input['name'] ?? null,
                    $input['phone'] ?? null,
                    $student->user_id
                ]);
            }

            // Update student
            $stmt = $db->prepare("
                UPDATE students 
                SET nis = COALESCE(?, nis),
                    nisn = COALESCE(?, nisn),
                    class_id = ?,
                    gender = COALESCE(?, gender),
                    birth_date = COALESCE(?, birth_date),
                    address = COALESCE(?, address),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['nis'] ?? null,
                $input['nisn'] ?? null,
                $input['class_id'] ?? null,
                $input['gender'] ?? null,
                $input['birth_date'] ?? null,
                $input['address'] ?? null,
                $id
            ]);

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Student updated successfully'
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
     * Delete student (soft delete)
     * DELETE /api/students/:id
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

            // Check student exists
            $stmt = $db->prepare("
                SELECT id, user_id 
                FROM students 
                WHERE id = ? AND school_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $schoolId]);
            $student = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$student) {
                Response::json(['success' => false, 'message' => 'Student not found'], 404);
                return;
            }

            // Soft delete student
            $stmt = $db->prepare("
                UPDATE students 
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
            $stmt->execute([$student->user_id]);

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Student deleted successfully'
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

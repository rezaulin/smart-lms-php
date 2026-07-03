<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

/**
 * RaportController - Part 1: Raport & Components CRUD
 * Handles raport management, components, and structure
 */
class RaportController
{
    /**
     * Get all raports with filters
     * GET /api/raports?student_id=1&semester_id=1&class_id=1
     */
    public static function index(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $studentId = $_GET['student_id'] ?? null;
        $semesterId = $_GET['semester_id'] ?? null;
        $classId = $_GET['class_id'] ?? null;

        try {
            $where = ["r.school_id = ?"];
            $params = [$schoolId];

            if ($studentId) {
                $where[] = "r.student_id = ?";
                $params[] = $studentId;
            }
            if ($semesterId) {
                $where[] = "r.semester_id = ?";
                $params[] = $semesterId;
            }
            if ($classId) {
                $where[] = "r.class_id = ?";
                $params[] = $classId;
            }

            $whereSQL = implode(' AND ', $where);

            $stmt = $db->prepare("
                SELECT 
                    r.*,
                    s.name as student_name,
                    s.nis as student_nis,
                    sem.name as semester_name,
                    c.name as class_name
                FROM raports r
                LEFT JOIN students s ON r.student_id = s.id
                LEFT JOIN semesters sem ON r.semester_id = sem.id
                LEFT JOIN classes c ON r.class_id = c.id
                WHERE {$whereSQL}
                ORDER BY r.created_at DESC
            ");
            $stmt->execute($params);
            $raports = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $raports
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single raport with items
     * GET /api/raports/:id
     */
    public static function show(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

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
                    r.*,
                    s.name as student_name,
                    s.nis as student_nis,
                    sem.name as semester_name,
                    c.name as class_name
                FROM raports r
                LEFT JOIN students s ON r.student_id = s.id
                LEFT JOIN semesters sem ON r.semester_id = sem.id
                LEFT JOIN classes c ON r.class_id = c.id
                WHERE r.id = ? AND r.school_id = ?
            ");
            $stmt->execute([$id, $schoolId]);
            $raport = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$raport) {
                Response::json(['success' => false, 'message' => 'Raport not found'], 404);
                return;
            }

            // Get raport items
            $stmt = $db->prepare("
                SELECT 
                    ri.*,
                    rc.name as component_name,
                    sub.name as subject_name
                FROM raport_items ri
                LEFT JOIN report_components rc ON ri.component_id = rc.id
                LEFT JOIN subjects sub ON ri.subject_id = sub.id
                WHERE ri.raport_id = ?
                ORDER BY sub.name ASC, rc.name ASC
            ");
            $stmt->execute([$id]);
            $raport->items = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $raport
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create raport
     * POST /api/raports
     * Body: {student_id, semester_id, class_id}
     */
    public static function store(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        if (!in_array($user->role, ['admin_pusat', 'admin_cabang', 'guru'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $input = json_decode(file_get_contents('php://input'), true);

        $required = ['student_id', 'semester_id', 'class_id'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            // Check if raport exists
            $stmt = $db->prepare("
                SELECT id FROM raports 
                WHERE school_id = ? AND student_id = ? AND semester_id = ?
            ");
            $stmt->execute([$schoolId, $input['student_id'], $input['semester_id']]);
            if ($stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Raport already exists for this student and semester'], 400);
                return;
            }

            $stmt = $db->prepare("
                INSERT INTO raports 
                (school_id, student_id, semester_id, class_id, status)
                VALUES (?, ?, ?, ?, 'draft')
            ");
            $stmt->execute([
                $schoolId,
                $input['student_id'],
                $input['semester_id'],
                $input['class_id']
            ]);
            $id = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Raport created successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get report components
     * GET /api/raports/components
     */
    public static function getComponents(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        try {
            $stmt = $db->prepare("
                SELECT * FROM report_components 
                WHERE school_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$schoolId]);
            $components = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $components
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create report component
     * POST /api/raports/components
     * Body: {code, name, description, weight}
     */
    public static function createComponent(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        if (!in_array($user->role, ['admin_pusat', 'admin_cabang'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $input = json_decode(file_get_contents('php://input'), true);

        $required = ['code', 'name', 'weight'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO report_components 
                (school_id, code, name, description, weight)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['code'],
                $input['name'],
                $input['description'] ?? null,
                $input['weight']
            ]);
            $id = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Component created successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Input raport scores (bulk)
     * POST /api/raports/:id/scores
     * Body: {items: [{subject_id, component_id, score, notes}]}
     */
    public static function inputScores(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        if (!in_array($user->role, ['admin_pusat', 'admin_cabang', 'guru'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $raportId = $segments[count($segments) - 2]; // /api/raports/:id/scores

        if (!is_numeric($raportId)) {
            Response::json(['success' => false, 'message' => 'Invalid raport ID'], 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['items']) || !is_array($input['items'])) {
            Response::json(['success' => false, 'message' => 'items array is required'], 400);
            return;
        }

        try {
            // Verify raport exists
            $stmt = $db->prepare("SELECT id FROM raports WHERE id = ? AND school_id = ?");
            $stmt->execute([$raportId, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Raport not found'], 404);
                return;
            }

            $db->beginTransaction();

            foreach ($input['items'] as $item) {
                if (empty($item['subject_id']) || empty($item['component_id'])) continue;

                $subjectId = $item['subject_id'];
                $componentId = $item['component_id'];
                $score = $item['score'] ?? null;
                $notes = $item['notes'] ?? null;

                // Check if item exists
                $stmt = $db->prepare("
                    SELECT id FROM raport_items 
                    WHERE raport_id = ? AND subject_id = ? AND component_id = ?
                ");
                $stmt->execute([$raportId, $subjectId, $componentId]);
                $existing = $stmt->fetch(\PDO::FETCH_OBJ);

                if ($existing) {
                    // Update
                    $stmt = $db->prepare("
                        UPDATE raport_items 
                        SET score = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$score, $notes, $existing->id]);
                } else {
                    // Insert
                    $stmt = $db->prepare("
                        INSERT INTO raport_items 
                        (raport_id, subject_id, component_id, score, notes)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$raportId, $subjectId, $componentId, $score, $notes]);
                }
            }

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Scores saved successfully'
            ]);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get student scores by subject
     * GET /api/raports/student/:student_id/scores?semester_id=1
     */
    public static function getStudentScores(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $studentId = $segments[count($segments) - 2]; // /api/raports/student/:id/scores

        if (!is_numeric($studentId)) {
            Response::json(['success' => false, 'message' => 'Invalid student ID'], 400);
            return;
        }

        $semesterId = $_GET['semester_id'] ?? null;

        try {
            $stmt = $db->prepare("
                SELECT 
                    ss.*,
                    sub.name as subject_name,
                    rc.name as component_name,
                    rc.weight as component_weight
                FROM student_scores ss
                LEFT JOIN subjects sub ON ss.subject_id = sub.id
                LEFT JOIN report_components rc ON ss.component_id = rc.id
                WHERE ss.student_id = ? AND ss.school_id = ? AND ss.semester_id = ?
                ORDER BY sub.name ASC, rc.name ASC
            ");
            $stmt->execute([$studentId, $schoolId, $semesterId]);
            $scores = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $scores
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update raport status
     * PUT /api/raports/:id/status
     * Body: {status: "draft"|"finalized"|"published"}
     */
    public static function updateStatus(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        if (!in_array($user->role, ['admin_pusat', 'admin_cabang', 'guru'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $raportId = $segments[count($segments) - 2]; // /api/raports/:id/status

        if (!is_numeric($raportId)) {
            Response::json(['success' => false, 'message' => 'Invalid raport ID'], 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['status'])) {
            Response::json(['success' => false, 'message' => 'status is required'], 400);
            return;
        }

        try {
            $stmt = $db->prepare("SELECT id FROM raports WHERE id = ? AND school_id = ?");
            $stmt->execute([$raportId, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Raport not found'], 404);
                return;
            }

            $stmt = $db->prepare("
                UPDATE raports 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$input['status'], $raportId]);

            Response::json([
                'success' => true,
                'message' => 'Raport status updated'
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate raport summary (placeholder for PDF generation)
     * GET /api/raports/:id/summary
     */
    public static function getSummary(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $raportId = end($segments);

        if (!is_numeric($raportId)) {
            Response::json(['success' => false, 'message' => 'Invalid raport ID'], 400);
            return;
        }

        try {
            // Get raport with student info
            $stmt = $db->prepare("
                SELECT 
                    r.*,
                    s.name as student_name,
                    s.nis as student_nis,
                    c.name as class_name,
                    sem.name as semester_name
                FROM raports r
                LEFT JOIN students s ON r.student_id = s.id
                LEFT JOIN classes c ON r.class_id = c.id
                LEFT JOIN semesters sem ON r.semester_id = sem.id
                WHERE r.id = ? AND r.school_id = ?
            ");
            $stmt->execute([$raportId, $schoolId]);
            $raport = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$raport) {
                Response::json(['success' => false, 'message' => 'Raport not found'], 404);
                return;
            }

            // Get scores grouped by subject
            $stmt = $db->prepare("
                SELECT 
                    ri.*,
                    sub.name as subject_name,
                    rc.name as component_name,
                    rc.weight as component_weight
                FROM raport_items ri
                LEFT JOIN subjects sub ON ri.subject_id = sub.id
                LEFT JOIN report_components rc ON ri.component_id = rc.id
                WHERE ri.raport_id = ?
                ORDER BY sub.name ASC, rc.name ASC
            ");
            $stmt->execute([$raportId]);
            $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

            $raport->items = $items;

            Response::json([
                'success' => true,
                'data' => $raport,
                'message' => 'Raport summary retrieved (PDF generation not implemented yet)'
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

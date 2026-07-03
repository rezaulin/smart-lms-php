<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

/**
 * ExamController - Part 1: Exam CRUD
 * Handles exam creation, questions, and management
 */
class ExamController
{
    /**
     * Get all exams with filters
     * GET /api/exams?class_id=1&subject_id=2&status=active
     */
    public static function index(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Filters
        $classId = $_GET['class_id'] ?? null;
        $subjectId = $_GET['subject_id'] ?? null;
        $status = $_GET['status'] ?? null;

        try {
            $where = ["e.school_id = ?"];
            $params = [$schoolId];

            if ($classId) {
                $where[] = "e.class_id = ?";
                $params[] = $classId;
            }
            if ($subjectId) {
                $where[] = "e.subject_id = ?";
                $params[] = $subjectId;
            }
            if ($status) {
                $where[] = "e.status = ?";
                $params[] = $status;
            }

            $whereSQL = implode(' AND ', $where);

            $stmt = $db->prepare("
                SELECT 
                    e.*,
                    c.name as class_name,
                    sub.name as subject_name,
                    u.name as created_by_name
                FROM exams e
                LEFT JOIN classes c ON e.class_id = c.id
                LEFT JOIN subjects sub ON e.subject_id = sub.id
                LEFT JOIN users u ON e.created_by = u.id
                WHERE {$whereSQL}
                ORDER BY e.start_time DESC
            ");
            $stmt->execute($params);
            $exams = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $exams
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single exam with questions
     * GET /api/exams/:id
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
                    e.*,
                    c.name as class_name,
                    sub.name as subject_name
                FROM exams e
                LEFT JOIN classes c ON e.class_id = c.id
                LEFT JOIN subjects sub ON e.subject_id = sub.id
                WHERE e.id = ? AND e.school_id = ?
            ");
            $stmt->execute([$id, $schoolId]);
            $exam = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$exam) {
                Response::json(['success' => false, 'message' => 'Exam not found'], 404);
                return;
            }

            // Get questions (if user is guru/admin)
            if (in_array($user->role, ['admin_pusat', 'admin_cabang', 'guru'])) {
                $stmt = $db->prepare("
                    SELECT 
                        q.*,
                        qb.title as question_bank_title
                    FROM questions q
                    LEFT JOIN question_bank_items qbi ON q.id = qbi.question_id
                    LEFT JOIN question_banks qb ON qbi.question_bank_id = qb.id
                    WHERE qbi.question_bank_id IN (
                        SELECT question_bank_id FROM exams WHERE id = ?
                    )
                    ORDER BY q.id ASC
                ");
                $stmt->execute([$id]);
                $exam->questions = $stmt->fetchAll(\PDO::FETCH_OBJ);
            }

            Response::json([
                'success' => true,
                'data' => $exam
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create exam
     * POST /api/exams
     * Body: {title, description, class_id, subject_id, question_bank_id, start_time, end_time, duration_minutes, pass_score}
     */
    public static function store(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Role check
        if (!in_array($user->role, ['admin_pusat', 'admin_cabang', 'guru'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $input = json_decode(file_get_contents('php://input'), true);

        // Validation
        $required = ['title', 'class_id', 'subject_id', 'question_bank_id', 'start_time', 'end_time', 'duration_minutes'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            // Verify question bank exists
            $stmt = $db->prepare("SELECT id FROM question_banks WHERE id = ? AND school_id = ?");
            $stmt->execute([$input['question_bank_id'], $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Question bank not found'], 404);
                return;
            }

            // Create exam
            $stmt = $db->prepare("
                INSERT INTO exams 
                (school_id, title, description, class_id, subject_id, question_bank_id, 
                 start_time, end_time, duration_minutes, pass_score, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['title'],
                $input['description'] ?? null,
                $input['class_id'],
                $input['subject_id'],
                $input['question_bank_id'],
                $input['start_time'],
                $input['end_time'],
                $input['duration_minutes'],
                $input['pass_score'] ?? 60,
                $user->id
            ]);
            $examId = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Exam created successfully',
                'data' => ['id' => $examId]
            ], 201);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update exam
     * PUT /api/exams/:id
     */
    public static function update(): void
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
        $id = end($segments);

        if (!is_numeric($id)) {
            Response::json(['success' => false, 'message' => 'Invalid ID'], 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $stmt = $db->prepare("SELECT id FROM exams WHERE id = ? AND school_id = ?");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Exam not found'], 404);
                return;
            }

            $stmt = $db->prepare("
                UPDATE exams 
                SET title = COALESCE(?, title),
                    description = COALESCE(?, description),
                    class_id = COALESCE(?, class_id),
                    subject_id = COALESCE(?, subject_id),
                    start_time = COALESCE(?, start_time),
                    end_time = COALESCE(?, end_time),
                    duration_minutes = COALESCE(?, duration_minutes),
                    pass_score = COALESCE(?, pass_score),
                    status = COALESCE(?, status),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['title'] ?? null,
                $input['description'] ?? null,
                $input['class_id'] ?? null,
                $input['subject_id'] ?? null,
                $input['start_time'] ?? null,
                $input['end_time'] ?? null,
                $input['duration_minutes'] ?? null,
                $input['pass_score'] ?? null,
                $input['status'] ?? null,
                $id
            ]);

            Response::json(['success' => true, 'message' => 'Exam updated successfully']);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete exam
     * DELETE /api/exams/:id
     */
    public static function destroy(): void
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
        $id = end($segments);

        if (!is_numeric($id)) {
            Response::json(['success' => false, 'message' => 'Invalid ID'], 400);
            return;
        }

        try {
            $stmt = $db->prepare("DELETE FROM exams WHERE id = ? AND school_id = ?");
            $stmt->execute([$id, $schoolId]);

            Response::json(['success' => true, 'message' => 'Exam deleted successfully']);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Start exam attempt (student)
     * POST /api/exams/:id/start
     */
    public static function startAttempt(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Role check - siswa only
        if ($user->role !== 'siswa') {
            Response::json(['success' => false, 'message' => 'Unauthorized - siswa only'], 403);
            return;
        }

        $db = Database::connect();

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $examId = $segments[count($segments) - 2]; // /api/exams/:id/start

        if (!is_numeric($examId)) {
            Response::json(['success' => false, 'message' => 'Invalid exam ID'], 400);
            return;
        }

        try {
            // Get student
            $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$user->id]);
            $student = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$student) {
                Response::json(['success' => false, 'message' => 'Student not found'], 404);
                return;
            }

            // Get exam
            $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
            $stmt->execute([$examId]);
            $exam = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$exam) {
                Response::json(['success' => false, 'message' => 'Exam not found'], 404);
                return;
            }

            // Check exam status
            if ($exam->status !== 'active') {
                Response::json(['success' => false, 'message' => 'Exam is not active'], 400);
                return;
            }

            // Check time window
            $now = time();
            if (strtotime($exam->start_time) > $now) {
                Response::json(['success' => false, 'message' => 'Exam has not started yet'], 400);
                return;
            }
            if (strtotime($exam->end_time) < $now) {
                Response::json(['success' => false, 'message' => 'Exam has ended'], 400);
                return;
            }

            // Check existing attempt
            $stmt = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ?");
            $stmt->execute([$examId, $student->id]);
            $existing = $stmt->fetch(\PDO::FETCH_OBJ);

            if ($existing) {
                Response::json([
                    'success' => true,
                    'message' => 'Attempt already exists',
                    'data' => $existing
                ]);
                return;
            }

            // Create attempt
            $startedAt = date('Y-m-d H:i:s');
            $endsAt = date('Y-m-d H:i:s', strtotime("+{$exam->duration_minutes} minutes"));

            $stmt = $db->prepare("
                INSERT INTO exam_attempts 
                (exam_id, student_id, started_at, ends_at, status)
                VALUES (?, ?, ?, ?, 'in_progress')
            ");
            $stmt->execute([$examId, $student->id, $startedAt, $endsAt]);
            $attemptId = $db->lastInsertId();

            // Fetch attempt
            $stmt = $db->prepare("SELECT * FROM exam_attempts WHERE id = ?");
            $stmt->execute([$attemptId]);
            $attempt = $stmt->fetch(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'message' => 'Exam attempt started',
                'data' => $attempt
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Submit exam answers
     * POST /api/exams/attempts/:id/submit
     * Body: {answers: [{question_id, answer_text}]}
     */
    public static function submitAttempt(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        if ($user->role !== 'siswa') {
            Response::json(['success' => false, 'message' => 'Unauthorized - siswa only'], 403);
            return;
        }

        $db = Database::connect();

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $attemptId = $segments[count($segments) - 2]; // /api/exams/attempts/:id/submit

        if (!is_numeric($attemptId)) {
            Response::json(['success' => false, 'message' => 'Invalid attempt ID'], 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['answers']) || !is_array($input['answers'])) {
            Response::json(['success' => false, 'message' => 'answers array is required'], 400);
            return;
        }

        try {
            // Get attempt
            $stmt = $db->prepare("SELECT * FROM exam_attempts WHERE id = ?");
            $stmt->execute([$attemptId]);
            $attempt = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$attempt) {
                Response::json(['success' => false, 'message' => 'Attempt not found'], 404);
                return;
            }

            // Check ownership
            $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$user->id]);
            $student = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$student || $attempt->student_id != $student->id) {
                Response::json(['success' => false, 'message' => 'Unauthorized - not your attempt'], 403);
                return;
            }

            // Check if already submitted
            if ($attempt->status === 'submitted') {
                Response::json(['success' => false, 'message' => 'Attempt already submitted'], 400);
                return;
            }

            // Save answers
            $db->beginTransaction();

            foreach ($input['answers'] as $ans) {
                if (empty($ans['question_id'])) continue;

                $questionId = $ans['question_id'];
                $answerText = $ans['answer_text'] ?? '';

                // Check if answer exists
                $stmt = $db->prepare("
                    SELECT id FROM exam_answers 
                    WHERE attempt_id = ? AND question_id = ?
                ");
                $stmt->execute([$attemptId, $questionId]);
                $existing = $stmt->fetch(\PDO::FETCH_OBJ);

                if ($existing) {
                    // Update
                    $stmt = $db->prepare("
                        UPDATE exam_answers 
                        SET answer_text = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$answerText, $existing->id]);
                } else {
                    // Insert
                    $stmt = $db->prepare("
                        INSERT INTO exam_answers 
                        (attempt_id, question_id, answer_text)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$attemptId, $questionId, $answerText]);
                }
            }

            // Mark attempt as submitted
            $stmt = $db->prepare("
                UPDATE exam_attempts 
                SET status = 'submitted', submitted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$attemptId]);

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Exam submitted successfully'
            ]);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

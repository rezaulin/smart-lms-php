<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

/**
 * AttendanceController - Part 1: Schedule Management
 * Handles jadwal pelajaran (schedule) CRUD operations
 */
class AttendanceController
{
    /**
     * Get schedules with filters
     * GET /api/schedules?class_id=1&teacher_id=2&day=1&semester_id=1
     */
    public static function getSchedules(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        // Filters
        $classId = $_GET['class_id'] ?? null;
        $teacherId = $_GET['teacher_id'] ?? null;
        $day = $_GET['day'] ?? null;
        $semesterId = $_GET['semester_id'] ?? null;

        try {
            $where = ["s.school_id = ?"];
            $params = [$schoolId];

            if ($classId) {
                $where[] = "s.class_id = ?";
                $params[] = $classId;
            }
            if ($teacherId) {
                $where[] = "s.teacher_id = ?";
                $params[] = $teacherId;
            }
            if ($day) {
                $where[] = "s.day_of_week = ?";
                $params[] = $day;
            }
            if ($semesterId) {
                $where[] = "s.semester_id = ?";
                $params[] = $semesterId;
            }

            $whereSQL = implode(' AND ', $where);

            $stmt = $db->prepare("
                SELECT 
                    s.*,
                    c.name as class_name,
                    sub.name as subject_name,
                    sub.code as subject_code,
                    u.name as teacher_name
                FROM schedules s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN subjects sub ON s.subject_id = sub.id
                LEFT JOIN teachers t ON s.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE {$whereSQL}
                ORDER BY s.day_of_week ASC, s.start_time ASC
            ");
            $stmt->execute($params);
            $schedules = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $schedules
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my today schedules (for teacher)
     * GET /api/schedules/my-today
     */
    public static function getMyTodaySchedules(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        // Role check - guru only
        if ($user->role !== 'guru') {
            Response::json(['success' => false, 'message' => 'Unauthorized - guru only'], 403);
            return;
        }

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        try {
            // Get teacher
            $stmt = $db->prepare("SELECT id FROM teachers WHERE user_id = ?");
            $stmt->execute([$user->id]);
            $teacher = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$teacher) {
                Response::json(['success' => false, 'message' => 'Teacher not found'], 404);
                return;
            }

            // Get today's day_of_week (1=Monday, 7=Sunday)
            $dow = (int)date('N');

            // Get schedules
            $stmt = $db->prepare("
                SELECT 
                    s.*,
                    c.name as class_name,
                    sub.name as subject_name,
                    sub.code as subject_code
                FROM schedules s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN subjects sub ON s.subject_id = sub.id
                WHERE s.school_id = ? 
                  AND s.teacher_id = ? 
                  AND s.day_of_week = ?
                ORDER BY s.start_time ASC
            ");
            $stmt->execute([$schoolId, $teacher->id, $dow]);
            $schedules = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Attach session info (if session opened today)
            $dateStr = date('Y-m-d');
            foreach ($schedules as $schedule) {
                $stmt = $db->prepare("
                    SELECT * 
                    FROM attendance_sessions 
                    WHERE schedule_id = ? AND date = ?
                ");
                $stmt->execute([$schedule->id, $dateStr]);
                $schedule->session = $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
            }

            Response::json([
                'success' => true,
                'data' => $schedules
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create schedule
     * POST /api/schedules
     * Body: {semester_id, class_id, subject_id, teacher_id, day_of_week, start_time, end_time}
     */
    public static function createSchedule(): void
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
        $required = ['semester_id', 'class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        if ($input['day_of_week'] < 1 || $input['day_of_week'] > 7) {
            Response::json(['success' => false, 'message' => 'day_of_week must be 1-7'], 400);
            return;
        }

        try {
            // Check conflict: same teacher, overlapping time, same day, same semester
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM schedules 
                WHERE school_id = ? 
                  AND semester_id = ? 
                  AND day_of_week = ? 
                  AND teacher_id = ?
                  AND NOT (end_time <= ? OR start_time >= ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['semester_id'],
                $input['day_of_week'],
                $input['teacher_id'],
                $input['start_time'],
                $input['end_time']
            ]);
            $conflict = (int)$stmt->fetchColumn();

            if ($conflict > 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Jadwal guru bentrok dengan yang ada'
                ], 409);
                return;
            }

            // Create schedule
            $stmt = $db->prepare("
                INSERT INTO schedules 
                (semester_id, class_id, subject_id, teacher_id, day_of_week, start_time, end_time, school_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['semester_id'],
                $input['class_id'],
                $input['subject_id'],
                $input['teacher_id'],
                $input['day_of_week'],
                $input['start_time'],
                $input['end_time'],
                $schoolId
            ]);
            $scheduleId = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Schedule created successfully',
                'data' => ['id' => $scheduleId]
            ], 201);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update schedule
     * PUT /api/schedules/:id
     */
    public static function updateSchedule(): void
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

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $id = end($segments);

        if (!is_numeric($id)) {
            Response::json(['success' => false, 'message' => 'Invalid ID'], 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $stmt = $db->prepare("SELECT id FROM schedules WHERE id = ? AND school_id = ?");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Schedule not found'], 404);
                return;
            }

            $stmt = $db->prepare("
                UPDATE schedules 
                SET semester_id = COALESCE(?, semester_id),
                    class_id = COALESCE(?, class_id),
                    subject_id = COALESCE(?, subject_id),
                    teacher_id = COALESCE(?, teacher_id),
                    day_of_week = COALESCE(?, day_of_week),
                    start_time = COALESCE(?, start_time),
                    end_time = COALESCE(?, end_time),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['semester_id'] ?? null,
                $input['class_id'] ?? null,
                $input['subject_id'] ?? null,
                $input['teacher_id'] ?? null,
                $input['day_of_week'] ?? null,
                $input['start_time'] ?? null,
                $input['end_time'] ?? null,
                $id
            ]);

            Response::json(['success' => true, 'message' => 'Schedule updated successfully']);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete schedule
     * DELETE /api/schedules/:id
     */
    public static function deleteSchedule(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        if (!in_array($user->role, ['admin_pusat', 'admin_cabang'])) {
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
            $stmt = $db->prepare("DELETE FROM schedules WHERE id = ? AND school_id = ?");
            $stmt->execute([$id, $schoolId]);

            Response::json(['success' => true, 'message' => 'Schedule deleted successfully']);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Open attendance session
     * POST /api/attendance/open
     * Body: {schedule_id, date, method: "manual"|"qr", qr_duration_minutes, gps: {lat, lng, accuracy, timestamp}}
     */
    public static function openSession(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $input = json_decode(file_get_contents('php://input'), true);

        // Validation
        if (empty($input['schedule_id'])) {
            Response::json(['success' => false, 'message' => 'schedule_id is required'], 400);
            return;
        }

        $scheduleId = $input['schedule_id'];
        $date = $input['date'] ?? date('Y-m-d');
        $method = $input['method'] ?? 'manual';
        $qrDuration = $input['qr_duration_minutes'] ?? 15;

        try {
            // Validate schedule exists
            $stmt = $db->prepare("SELECT * FROM schedules WHERE id = ? AND school_id = ?");
            $stmt->execute([$scheduleId, $schoolId]);
            $schedule = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$schedule) {
                Response::json(['success' => false, 'message' => 'Schedule not found'], 404);
                return;
            }

            // Guru check: only owner can open
            if ($user->role === 'guru') {
                $stmt = $db->prepare("SELECT id FROM teachers WHERE user_id = ?");
                $stmt->execute([$user->id]);
                $teacher = $stmt->fetch(\PDO::FETCH_OBJ);

                if (!$teacher || $schedule->teacher_id != $teacher->id) {
                    Response::json(['success' => false, 'message' => 'Unauthorized - not your schedule'], 403);
                    return;
                }
            }

            // Check if session already exists
            $stmt = $db->prepare("SELECT * FROM attendance_sessions WHERE schedule_id = ? AND date = ?");
            $stmt->execute([$scheduleId, $date]);
            $existing = $stmt->fetch(\PDO::FETCH_OBJ);

            if ($existing) {
                Response::json([
                    'success' => true,
                    'message' => 'Session already opened',
                    'data' => $existing
                ]);
                return;
            }

            // Generate QR token
            $qrToken = bin2hex(random_bytes(16));
            $qrExpiresAt = null;
            if ($method === 'qr') {
                $qrExpiresAt = date('Y-m-d H:i:s', strtotime("+{$qrDuration} minutes"));
            }

            // Create session
            $stmt = $db->prepare("
                INSERT INTO attendance_sessions 
                (school_id, schedule_id, date, opened_by, method, qr_token, qr_expires_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $schoolId,
                $scheduleId,
                $date,
                $user->id,
                $method,
                $qrToken,
                $qrExpiresAt
            ]);
            $sessionId = $db->lastInsertId();

            // Fetch created session
            $stmt = $db->prepare("SELECT * FROM attendance_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'message' => 'Attendance session opened',
                'data' => $session
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Close attendance session
     * POST /api/attendance/close/:id
     */
    public static function closeSession(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $id = $segments[count($segments) - 1];

        if (!is_numeric($id)) {
            Response::json(['success' => false, 'message' => 'Invalid session ID'], 400);
            return;
        }

        try {
            $stmt = $db->prepare("SELECT * FROM attendance_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $session = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$session) {
                Response::json(['success' => false, 'message' => 'Session not found'], 404);
                return;
            }

            // Close session
            $stmt = $db->prepare("
                UPDATE attendance_sessions 
                SET status = 'closed', closed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            Response::json([
                'success' => true,
                'message' => 'Attendance session closed'
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Student check-in via QR code
     * POST /api/attendance/checkin
     * Body: {qr_token, student_id, lat, lng, accuracy}
     */
    public static function checkIn(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $input = json_decode(file_get_contents('php://input'), true);

        // Validation
        if (empty($input['qr_token'])) {
            Response::json(['success' => false, 'message' => 'qr_token is required'], 400);
            return;
        }

        $qrToken = $input['qr_token'];
        $studentId = $input['student_id'] ?? null;

        // If student_id not provided, try to get from current user
        if (!$studentId && $user->role === 'siswa') {
            $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$user->id]);
            $student = $stmt->fetch(\PDO::FETCH_OBJ);
            $studentId = $student->id ?? null;
        }

        if (!$studentId) {
            Response::json(['success' => false, 'message' => 'student_id is required'], 400);
            return;
        }

        try {
            // Find session by QR token
            $stmt = $db->prepare("
                SELECT * FROM attendance_sessions 
                WHERE qr_token = ? AND status = 'open'
            ");
            $stmt->execute([$qrToken]);
            $session = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$session) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid or expired QR code'
                ], 404);
                return;
            }

            // Check QR expiry
            if ($session->qr_expires_at && strtotime($session->qr_expires_at) < time()) {
                Response::json([
                    'success' => false,
                    'message' => 'QR code has expired'
                ], 400);
                return;
            }

            // Check if presence already exists
            $stmt = $db->prepare("
                SELECT * FROM presences 
                WHERE session_id = ? AND student_id = ?
            ");
            $stmt->execute([$session->id, $studentId]);
            $existing = $stmt->fetch(\PDO::FETCH_OBJ);

            if ($existing) {
                // Update existing presence
                $stmt = $db->prepare("
                    UPDATE presences 
                    SET status = 'hadir', 
                        marked_by = ?, 
                        marked_at = NOW(),
                        latitude = ?,
                        longitude = ?,
                        accuracy_m = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $user->id,
                    $input['lat'] ?? null,
                    $input['lng'] ?? null,
                    $input['accuracy'] ?? null,
                    $existing->id
                ]);

                Response::json([
                    'success' => true,
                    'message' => 'Attendance recorded (updated)'
                ]);
            } else {
                // Create new presence
                $stmt = $db->prepare("
                    INSERT INTO presences 
                    (session_id, student_id, status, marked_by, marked_at, latitude, longitude, accuracy_m)
                    VALUES (?, ?, 'hadir', ?, NOW(), ?, ?, ?)
                ");
                $stmt->execute([
                    $session->id,
                    $studentId,
                    $user->id,
                    $input['lat'] ?? null,
                    $input['lng'] ?? null,
                    $input['accuracy'] ?? null
                ]);

                Response::json([
                    'success' => true,
                    'message' => 'Attendance recorded'
                ], 201);
            }

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark presence manually (bulk)
     * POST /api/attendance/mark/:session_id
     * Body: {presences: [{student_id, status, notes}]}
     */
    public static function markPresence(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        $sessionId = $segments[count($segments) - 1];

        if (!is_numeric($sessionId)) {
            Response::json(['success' => false, 'message' => 'Invalid session ID'], 400);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['presences']) || !is_array($input['presences'])) {
            Response::json(['success' => false, 'message' => 'presences array is required'], 400);
            return;
        }

        try {
            $db->beginTransaction();

            foreach ($input['presences'] as $p) {
                if (empty($p['student_id'])) continue;

                $studentId = $p['student_id'];
                $status = $p['status'] ?? 'hadir';
                $notes = $p['notes'] ?? null;

                // Check if presence exists
                $stmt = $db->prepare("
                    SELECT id FROM presences 
                    WHERE session_id = ? AND student_id = ?
                ");
                $stmt->execute([$sessionId, $studentId]);
                $existing = $stmt->fetch(\PDO::FETCH_OBJ);

                if ($existing) {
                    // Update
                    $stmt = $db->prepare("
                        UPDATE presences 
                        SET status = ?, notes = ?, marked_by = ?, marked_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, $notes, $user->id, $existing->id]);
                } else {
                    // Insert
                    $stmt = $db->prepare("
                        INSERT INTO presences 
                        (session_id, student_id, status, notes, marked_by, marked_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$sessionId, $studentId, $status, $notes, $user->id]);
                }
            }

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Presences marked successfully'
            ]);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

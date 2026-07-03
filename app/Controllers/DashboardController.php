<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

class DashboardController
{
    /**
     * Get dashboard statistics
     * GET /api/dashboard
     */
    public static function index(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;
        $role = $user->role ?? 'siswa';

        try {
            $stats = [];

            // ─── Admin Dashboard ──────────────────────────────
            if (in_array($role, ['admin_pusat', 'admin_cabang'])) {
                // Total students
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total 
                    FROM students 
                    WHERE school_id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$schoolId]);
                $stats['total_students'] = (int)$stmt->fetchColumn();

                // Total teachers
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total 
                    FROM teachers 
                    WHERE school_id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$schoolId]);
                $stats['total_teachers'] = (int)$stmt->fetchColumn();

                // Total classes
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total 
                    FROM classes 
                    WHERE school_id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$schoolId]);
                $stats['total_classes'] = (int)$stmt->fetchColumn();

                // Active semester
                $stmt = $db->prepare("
                    SELECT id, name, year, period, start_date, end_date
                    FROM semesters 
                    WHERE school_id = ? AND active = 1 AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$schoolId]);
                $stats['active_semester'] = $stmt->fetch(\PDO::FETCH_OBJ);

                // Today's attendance sessions
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total 
                    FROM attendance_sessions 
                    WHERE school_id = ? AND date = CURDATE() AND deleted_at IS NULL
                ");
                $stmt->execute([$schoolId]);
                $stats['today_attendance_sessions'] = (int)$stmt->fetchColumn();

                // Today's attendance rate
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(DISTINCT p.student_id) as present_count,
                        (SELECT COUNT(*) FROM students WHERE school_id = ? AND deleted_at IS NULL) as total_students
                    FROM presences p
                    INNER JOIN attendance_sessions s ON p.session_id = s.id
                    WHERE s.school_id = ? AND s.date = CURDATE() AND p.status = 'hadir'
                ");
                $stmt->execute([$schoolId, $schoolId]);
                $attendance = $stmt->fetch(\PDO::FETCH_OBJ);
                $stats['today_attendance_rate'] = $attendance->total_students > 0 
                    ? round(($attendance->present_count / $attendance->total_students) * 100, 2) 
                    : 0;

                // Pending billing (unpaid)
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_unpaid,
                        COALESCE(SUM(nominal - terbayar), 0) as total_amount
                    FROM tagihan
                    WHERE school_id = ? AND status != 'lunas' AND deleted_at IS NULL
                ");
                $stmt->execute([$schoolId]);
                $billing = $stmt->fetch(\PDO::FETCH_OBJ);
                $stats['billing_unpaid_count'] = (int)$billing->total_unpaid;
                $stats['billing_unpaid_amount'] = (float)$billing->total_amount;

                // Active exams
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total 
                    FROM exams 
                    WHERE school_id = ? 
                      AND status = 'active' 
                      AND start_time <= NOW() 
                      AND end_time >= NOW()
                      AND deleted_at IS NULL
                ");
                $stmt->execute([$schoolId]);
                $stats['active_exams'] = (int)$stmt->fetchColumn();
            }

            // ─── Teacher Dashboard ────────────────────────────
            if ($role === 'guru') {
                $teacherId = $user->teacher_id ?? null;

                // My classes today
                $stmt = $db->prepare("
                    SELECT 
                        sc.id as schedule_id,
                        sc.start_time,
                        sc.end_time,
                        sc.room,
                        c.name as class_name,
                        sub.name as subject_name
                    FROM schedules sc
                    INNER JOIN classes c ON sc.class_id = c.id
                    INNER JOIN subjects sub ON sc.subject_id = sub.id
                    WHERE sc.teacher_id = ? 
                      AND sc.day_of_week = DAYOFWEEK(CURDATE())
                      AND sc.deleted_at IS NULL
                    ORDER BY sc.start_time
                ");
                $stmt->execute([$teacherId]);
                $stats['my_schedules_today'] = $stmt->fetchAll(\PDO::FETCH_OBJ);

                // My attendance sessions (open)
                $stmt = $db->prepare("
                    SELECT 
                        a.id,
                        a.date,
                        a.opened_at,
                        a.status,
                        c.name as class_name,
                        sub.name as subject_name
                    FROM attendance_sessions a
                    INNER JOIN schedules sc ON a.schedule_id = sc.id
                    INNER JOIN classes c ON sc.class_id = c.id
                    INNER JOIN subjects sub ON sc.subject_id = sub.id
                    WHERE a.opened_by = ? 
                      AND a.status = 'open'
                      AND a.deleted_at IS NULL
                    ORDER BY a.opened_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$user->id]);
                $stats['open_attendance_sessions'] = $stmt->fetchAll(\PDO::FETCH_OBJ);

                // My exams (active/upcoming)
                $stmt = $db->prepare("
                    SELECT 
                        e.id,
                        e.title,
                        e.start_time,
                        e.end_time,
                        e.status,
                        c.name as class_name,
                        sub.name as subject_name
                    FROM exams e
                    INNER JOIN classes c ON e.class_id = c.id
                    INNER JOIN subjects sub ON e.subject_id = sub.id
                    WHERE e.teacher_id = ? 
                      AND e.end_time >= NOW()
                      AND e.deleted_at IS NULL
                    ORDER BY e.start_time
                    LIMIT 5
                ");
                $stmt->execute([$teacherId]);
                $stats['my_exams'] = $stmt->fetchAll(\PDO::FETCH_OBJ);
            }

            // ─── Student Dashboard ────────────────────────────
            if ($role === 'siswa') {
                $studentId = $user->student_id ?? null;

                // My attendance summary (this month)
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_sessions,
                        SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as sick,
                        SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as permission,
                        SUM(CASE WHEN p.status = 'alfa' THEN 1 ELSE 0 END) as absent
                    FROM presences p
                    INNER JOIN attendance_sessions s ON p.session_id = s.id
                    WHERE p.student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1)
                      AND MONTH(s.date) = MONTH(CURDATE())
                      AND YEAR(s.date) = YEAR(CURDATE())
                ");
                $stmt->execute([$user->id]);
                $stats['my_attendance_summary'] = $stmt->fetch(\PDO::FETCH_OBJ);

                // My exams (upcoming/active)
                $stmt = $db->prepare("
                    SELECT 
                        e.id,
                        e.title,
                        e.start_time,
                        e.end_time,
                        e.duration,
                        sub.name as subject_name,
                        (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1)) as attempt_count
                    FROM exams e
                    INNER JOIN subjects sub ON e.subject_id = sub.id
                    INNER JOIN students st ON e.class_id = st.class_id
                    WHERE st.user_id = ? 
                      AND e.start_time <= NOW() 
                      AND e.end_time >= NOW()
                      AND e.status = 'active'
                      AND e.deleted_at IS NULL
                    ORDER BY e.start_time
                ");
                $stmt->execute([$user->id, $user->id]);
                $stats['my_active_exams'] = $stmt->fetchAll(\PDO::FETCH_OBJ);

                // My billing (unpaid)
                $stmt = $db->prepare("
                    SELECT 
                        t.id,
                        jt.nama as jenis_tagihan,
                        t.periode,
                        t.nominal,
                        t.keringanan,
                        t.terbayar,
                        (t.nominal - t.keringanan - t.terbayar) as sisa,
                        t.jatuh_tempo,
                        t.status
                    FROM tagihan t
                    INNER JOIN jenis_tagihan jt ON t.jenis_tagihan_id = jt.id
                    WHERE t.student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1)
                      AND t.status != 'lunas'
                      AND t.deleted_at IS NULL
                    ORDER BY t.jatuh_tempo
                    LIMIT 10
                ");
                $stmt->execute([$user->id]);
                $stats['my_unpaid_bills'] = $stmt->fetchAll(\PDO::FETCH_OBJ);
            }

            // ─── Parent Dashboard ─────────────────────────────
            if ($role === 'orang_tua') {
                // Get child info
                $stmt = $db->prepare("
                    SELECT s.id, u.name, c.name as class_name
                    FROM students s
                    INNER JOIN users u ON s.user_id = u.id
                    INNER JOIN classes c ON s.class_id = c.id
                    INNER JOIN parents p ON s.id = p.student_id
                    WHERE p.user_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$user->id]);
                $child = $stmt->fetch(\PDO::FETCH_OBJ);
                $stats['child'] = $child;

                if ($child) {
                    // Child attendance (this month)
                    $stmt = $db->prepare("
                        SELECT 
                            COUNT(*) as total_sessions,
                            SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as sick,
                            SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as permission,
                            SUM(CASE WHEN p.status = 'alfa' THEN 1 ELSE 0 END) as absent
                        FROM presences p
                        INNER JOIN attendance_sessions s ON p.session_id = s.id
                        WHERE p.student_id = ?
                          AND MONTH(s.date) = MONTH(CURDATE())
                          AND YEAR(s.date) = YEAR(CURDATE())
                    ");
                    $stmt->execute([$child->id]);
                    $stats['child_attendance_summary'] = $stmt->fetch(\PDO::FETCH_OBJ);

                    // Child unpaid bills
                    $stmt = $db->prepare("
                        SELECT 
                            t.id,
                            jt.nama as jenis_tagihan,
                            t.periode,
                            t.nominal,
                            t.keringanan,
                            t.terbayar,
                            (t.nominal - t.keringanan - t.terbayar) as sisa,
                            t.jatuh_tempo,
                            t.status
                        FROM tagihan t
                        INNER JOIN jenis_tagihan jt ON t.jenis_tagihan_id = jt.id
                        WHERE t.student_id = ?
                          AND t.status != 'lunas'
                          AND t.deleted_at IS NULL
                        ORDER BY t.jatuh_tempo
                    ");
                    $stmt->execute([$child->id]);
                    $stats['child_unpaid_bills'] = $stmt->fetchAll(\PDO::FETCH_OBJ);
                }
            }

            Response::json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}

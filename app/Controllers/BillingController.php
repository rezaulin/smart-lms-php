<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Response;
use App\Middleware\AuthMiddleware;

/**
 * BillingController - Part 1: Tagihan & Jenis Tagihan
 * Handles SPP billing, jenis tagihan, and tagihan CRUD
 */
class BillingController
{
    /**
     * Get all jenis tagihan
     * GET /api/billing/jenis
     */
    public static function getJenisTagihan(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        try {
            $stmt = $db->prepare("
                SELECT * FROM jenis_tagihan 
                WHERE school_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$schoolId]);
            $jenis = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $jenis
            ]);

        } catch (\PDOException $e) {
            Response::json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create jenis tagihan
     * POST /api/billing/jenis
     * Body: {code, name, description, amount, recurrence}
     */
    public static function createJenisTagihan(): void
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

        $required = ['code', 'name', 'amount'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO jenis_tagihan 
                (school_id, code, name, description, amount, recurrence)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['code'],
                $input['name'],
                $input['description'] ?? null,
                $input['amount'],
                $input['recurrence'] ?? 'once'
            ]);
            $id = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Jenis tagihan created successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all tagihan with filters
     * GET /api/billing/tagihan?student_id=1&status=unpaid&semester_id=1
     */
    public static function getTagihan(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $studentId = $_GET['student_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $semesterId = $_GET['semester_id'] ?? null;

        try {
            $where = ["t.school_id = ?"];
            $params = [$schoolId];

            if ($studentId) {
                $where[] = "t.student_id = ?";
                $params[] = $studentId;
            }
            if ($status) {
                $where[] = "t.status = ?";
                $params[] = $status;
            }
            if ($semesterId) {
                $where[] = "t.semester_id = ?";
                $params[] = $semesterId;
            }

            $whereSQL = implode(' AND ', $where);

            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    jt.name as jenis_name,
                    jt.code as jenis_code,
                    s.name as student_name,
                    s.nis as student_nis
                FROM tagihan t
                LEFT JOIN jenis_tagihan jt ON t.jenis_tagihan_id = jt.id
                LEFT JOIN students s ON t.student_id = s.id
                WHERE {$whereSQL}
                ORDER BY t.due_date ASC
            ");
            $stmt->execute($params);
            $tagihan = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $tagihan
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create tagihan
     * POST /api/billing/tagihan
     * Body: {jenis_tagihan_id, student_id, semester_id, amount, due_date, description}
     */
    public static function createTagihan(): void
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

        $required = ['jenis_tagihan_id', 'student_id', 'semester_id', 'amount', 'due_date'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO tagihan 
                (school_id, jenis_tagihan_id, student_id, semester_id, amount, due_date, description, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid')
            ");
            $stmt->execute([
                $schoolId,
                $input['jenis_tagihan_id'],
                $input['student_id'],
                $input['semester_id'],
                $input['amount'],
                $input['due_date'],
                $input['description'] ?? null
            ]);
            $id = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Tagihan created successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update tagihan
     * PUT /api/billing/tagihan/:id
     */
    public static function updateTagihan(): void
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

        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $stmt = $db->prepare("SELECT id FROM tagihan WHERE id = ? AND school_id = ?");
            $stmt->execute([$id, $schoolId]);
            if (!$stmt->fetch()) {
                Response::json(['success' => false, 'message' => 'Tagihan not found'], 404);
                return;
            }

            $stmt = $db->prepare("
                UPDATE tagihan 
                SET amount = COALESCE(?, amount),
                    due_date = COALESCE(?, due_date),
                    description = COALESCE(?, description),
                    status = COALESCE(?, status),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $input['amount'] ?? null,
                $input['due_date'] ?? null,
                $input['description'] ?? null,
                $input['status'] ?? null,
                $id
            ]);

            Response::json(['success' => true, 'message' => 'Tagihan updated successfully']);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create pembayaran (payment)
     * POST /api/billing/pembayaran
     * Body: {tagihan_id, amount, payment_method, payment_date, receipt_number}
     */
    public static function createPembayaran(): void
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

        $required = ['tagihan_id', 'amount', 'payment_method'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            // Get tagihan
            $stmt = $db->prepare("SELECT * FROM tagihan WHERE id = ? AND school_id = ?");
            $stmt->execute([$input['tagihan_id'], $schoolId]);
            $tagihan = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$tagihan) {
                Response::json(['success' => false, 'message' => 'Tagihan not found'], 404);
                return;
            }

            $db->beginTransaction();

            // Create payment
            $paymentDate = $input['payment_date'] ?? date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                INSERT INTO pembayaran 
                (school_id, tagihan_id, amount, payment_method, payment_date, receipt_number, received_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['tagihan_id'],
                $input['amount'],
                $input['payment_method'],
                $paymentDate,
                $input['receipt_number'] ?? null,
                $user->id
            ]);
            $paymentId = $db->lastInsertId();

            // Calculate total paid
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM pembayaran WHERE tagihan_id = ?");
            $stmt->execute([$input['tagihan_id']]);
            $totalPaid = $stmt->fetchColumn();

            // Update tagihan status
            $status = 'unpaid';
            if ($totalPaid >= $tagihan->amount) {
                $status = 'paid';
            } elseif ($totalPaid > 0) {
                $status = 'partial';
            }

            $stmt = $db->prepare("UPDATE tagihan SET status = ?, paid_amount = ? WHERE id = ?");
            $stmt->execute([$status, $totalPaid, $input['tagihan_id']]);

            $db->commit();

            Response::json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => ['id' => $paymentId, 'tagihan_status' => $status]
            ], 201);

        } catch (\PDOException $e) {
            $db->rollBack();
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all pembayaran
     * GET /api/billing/pembayaran?tagihan_id=1&student_id=1
     */
    public static function getPembayaran(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        $tagihanId = $_GET['tagihan_id'] ?? null;
        $studentId = $_GET['student_id'] ?? null;

        try {
            $where = ["p.school_id = ?"];
            $params = [$schoolId];

            if ($tagihanId) {
                $where[] = "p.tagihan_id = ?";
                $params[] = $tagihanId;
            }
            if ($studentId) {
                $where[] = "t.student_id = ?";
                $params[] = $studentId;
            }

            $whereSQL = implode(' AND ', $where);

            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    t.amount as tagihan_amount,
                    s.name as student_name,
                    u.name as received_by_name
                FROM pembayaran p
                LEFT JOIN tagihan t ON p.tagihan_id = t.id
                LEFT JOIN students s ON t.student_id = s.id
                LEFT JOIN users u ON p.received_by = u.id
                WHERE {$whereSQL}
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute($params);
            $payments = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all potongan (discounts)
     * GET /api/billing/potongan
     */
    public static function getPotongan(): void
    {
        $user = AuthMiddleware::handle();
        if (!$user) return;

        $db = Database::connect();
        $schoolId = $user->school_id ?? null;

        try {
            $stmt = $db->prepare("
                SELECT * FROM potongan 
                WHERE school_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$schoolId]);
            $potongan = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Response::json([
                'success' => true,
                'data' => $potongan
            ]);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create potongan
     * POST /api/billing/potongan
     * Body: {code, name, type: "percentage"|"fixed", amount}
     */
    public static function createPotongan(): void
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

        $required = ['code', 'name', 'type', 'amount'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO potongan 
                (school_id, code, name, type, amount)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['code'],
                $input['name'],
                $input['type'],
                $input['amount']
            ]);
            $id = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Potongan created successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Assign potongan to student
     * POST /api/billing/student-potongan
     * Body: {student_id, potongan_id, semester_id}
     */
    public static function assignPotongan(): void
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

        $required = ['student_id', 'potongan_id', 'semester_id'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                Response::json(['success' => false, 'message' => "Field {$field} is required"], 400);
                return;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO student_potongan 
                (school_id, student_id, potongan_id, semester_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $schoolId,
                $input['student_id'],
                $input['potongan_id'],
                $input['semester_id']
            ]);
            $id = $db->lastInsertId();

            Response::json([
                'success' => true,
                'message' => 'Potongan assigned to student successfully',
                'data' => ['id' => $id]
            ], 201);

        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}

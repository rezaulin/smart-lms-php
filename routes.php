<?php

/**
 * Smart LMS - API Routes
 * All routes mapped to controllers
 */

require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';
require_once __DIR__ . '/app/Controllers/StudentController.php';
require_once __DIR__ . '/app/Controllers/TeacherController.php';
require_once __DIR__ . '/app/Controllers/ClassController.php';
require_once __DIR__ . '/app/Controllers/SubjectController.php';
require_once __DIR__ . '/app/Controllers/SemesterController.php';
require_once __DIR__ . '/app/Controllers/AttendanceController.php';
require_once __DIR__ . '/app/Controllers/ExamController.php';
require_once __DIR__ . '/app/Controllers/BillingController.php';
require_once __DIR__ . '/app/Controllers/RaportController.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\StudentController;
use App\Controllers\TeacherController;
use App\Controllers\ClassController;
use App\Controllers\SubjectController;
use App\Controllers\SemesterController;
use App\Controllers\AttendanceController;
use App\Controllers\ExamController;
use App\Controllers\BillingController;
use App\Controllers\RaportController;

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

// Remove /api prefix if exists
$path = preg_replace('#^/api#', '', $path);

// Simple router
$routes = [
    // ==========================================
    // AUTH ROUTES (Public)
    // ==========================================
    'POST /auth/login' => [AuthController::class, 'login'],
    'POST /auth/register' => [AuthController::class, 'register'],
    'POST /auth/refresh' => [AuthController::class, 'refreshToken'],
    'GET /auth/profile' => [AuthController::class, 'profile'],
    'PUT /auth/password' => [AuthController::class, 'changePassword'],

    // ==========================================
    // DASHBOARD ROUTES (Protected)
    // ==========================================
    'GET /dashboard' => [DashboardController::class, 'index'],

    // ==========================================
    // STUDENT ROUTES (Protected)
    // ==========================================
    'GET /students' => [StudentController::class, 'index'],
    'GET /students/:id' => [StudentController::class, 'show'],
    'POST /students' => [StudentController::class, 'store'],
    'PUT /students/:id' => [StudentController::class, 'update'],
    'DELETE /students/:id' => [StudentController::class, 'destroy'],

    // ==========================================
    // TEACHER ROUTES (Protected)
    // ==========================================
    'GET /teachers' => [TeacherController::class, 'index'],
    'GET /teachers/:id' => [TeacherController::class, 'show'],
    'POST /teachers' => [TeacherController::class, 'store'],
    'PUT /teachers/:id' => [TeacherController::class, 'update'],
    'DELETE /teachers/:id' => [TeacherController::class, 'destroy'],

    // ==========================================
    // CLASS ROUTES (Protected)
    // ==========================================
    'GET /classes' => [ClassController::class, 'index'],
    'GET /classes/:id' => [ClassController::class, 'show'],
    'POST /classes' => [ClassController::class, 'store'],
    'PUT /classes/:id' => [ClassController::class, 'update'],
    'DELETE /classes/:id' => [ClassController::class, 'destroy'],

    // ==========================================
    // SUBJECT ROUTES (Protected)
    // ==========================================
    'GET /subjects' => [SubjectController::class, 'index'],
    'GET /subjects/:id' => [SubjectController::class, 'show'],
    'POST /subjects' => [SubjectController::class, 'store'],
    'PUT /subjects/:id' => [SubjectController::class, 'update'],
    'DELETE /subjects/:id' => [SubjectController::class, 'destroy'],

    // ==========================================
    // SEMESTER ROUTES (Protected)
    // ==========================================
    'GET /semesters' => [SemesterController::class, 'index'],
    'GET /semesters/:id' => [SemesterController::class, 'show'],
    'POST /semesters' => [SemesterController::class, 'store'],
    'PUT /semesters/:id' => [SemesterController::class, 'update'],
    'DELETE /semesters/:id' => [SemesterController::class, 'destroy'],
    'POST /semesters/:id/activate' => [SemesterController::class, 'activate'],

    // ==========================================
    // ATTENDANCE ROUTES (Protected)
    // ==========================================
    // Schedules
    'GET /schedules' => [AttendanceController::class, 'getSchedules'],
    'GET /schedules/:id' => [AttendanceController::class, 'getSchedule'],
    'POST /schedules' => [AttendanceController::class, 'createSchedule'],
    'PUT /schedules/:id' => [AttendanceController::class, 'updateSchedule'],
    'DELETE /schedules/:id' => [AttendanceController::class, 'deleteSchedule'],

    // Attendance Sessions
    'GET /attendance/sessions' => [AttendanceController::class, 'getSessions'],
    'GET /attendance/sessions/:id' => [AttendanceController::class, 'getSession'],
    'POST /attendance/sessions' => [AttendanceController::class, 'openSession'],
    'POST /attendance/sessions/:id/close' => [AttendanceController::class, 'closeSession'],

    // Student Check-in
    'POST /attendance/checkin' => [AttendanceController::class, 'checkIn'],
    'POST /attendance/sessions/:id/mark' => [AttendanceController::class, 'markPresence'],

    // ==========================================
    // EXAM ROUTES (Protected)
    // ==========================================
    'GET /exams' => [ExamController::class, 'index'],
    'GET /exams/:id' => [ExamController::class, 'show'],
    'POST /exams' => [ExamController::class, 'store'],
    'PUT /exams/:id' => [ExamController::class, 'update'],
    'DELETE /exams/:id' => [ExamController::class, 'destroy'],

    // Exam Attempts (Student)
    'POST /exams/:id/start' => [ExamController::class, 'startAttempt'],
    'POST /exams/:id/submit' => [ExamController::class, 'submitAttempt'],

    // ==========================================
    // BILLING ROUTES (Protected)
    // ==========================================
    // Jenis Tagihan
    'GET /billing/jenis' => [BillingController::class, 'getJenisTagihan'],
    'POST /billing/jenis' => [BillingController::class, 'createJenisTagihan'],

    // Tagihan
    'GET /billing/tagihan' => [BillingController::class, 'getTagihan'],
    'GET /billing/tagihan/:id' => [BillingController::class, 'getTagihanDetail'],
    'POST /billing/tagihan' => [BillingController::class, 'createTagihan'],
    'PUT /billing/tagihan/:id' => [BillingController::class, 'updateTagihan'],
    'DELETE /billing/tagihan/:id' => [BillingController::class, 'deleteTagihan'],

    // Pembayaran
    'POST /billing/pembayaran' => [BillingController::class, 'createPembayaran'],
    'GET /billing/pembayaran/:id' => [BillingController::class, 'getPembayaran'],

    // Potongan
    'POST /billing/potongan' => [BillingController::class, 'createPotongan'],
    'GET /billing/student/:student_id/tagihan' => [BillingController::class, 'getStudentTagihan'],

    // ==========================================
    // RAPORT ROUTES (Protected)
    // ==========================================
    'GET /raports' => [RaportController::class, 'index'],
    'GET /raports/:id' => [RaportController::class, 'show'],
    'POST /raports' => [RaportController::class, 'store'],
    'DELETE /raports/:id' => [RaportController::class, 'destroy'],

    // Report Components
    'GET /raports/components' => [RaportController::class, 'getComponents'],
    'POST /raports/components' => [RaportController::class, 'createComponent'],

    // Scores
    'POST /raports/:id/scores' => [RaportController::class, 'inputScores'],
    'GET /raports/student/:student_id/scores' => [RaportController::class, 'getStudentScores'],
    'PUT /raports/:id/status' => [RaportController::class, 'updateStatus'],
    'GET /raports/:id/summary' => [RaportController::class, 'getSummary'],
];

// Match route
$routeKey = "$method $path";
$matched = false;

foreach ($routes as $route => $handler) {
    // Convert :id, :student_id, etc to regex patterns
    $pattern = preg_replace('#:([\w]+)#', '(?P<$1>[\w-]+)', $route);
    $pattern = "#^$pattern$#";

    if (preg_match($pattern, $routeKey, $matches)) {
        $matched = true;

        // Extract named parameters
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        // Store params in $_GET for controller access
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }

        // Call controller
        [$controller, $method] = $handler;
        call_user_func([$controller, $method]);
        break;
    }
}

// 404 if no match
if (!$matched) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Route not found',
        'path' => $path,
        'method' => $method
    ]);
}

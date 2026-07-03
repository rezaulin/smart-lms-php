<?php

return [
    // ─── Auth (Public) ──────────────────────────────────
    ['method' => 'POST', 'path' => '/api/auth/login', 'handler' => 'AuthController@login'],
    ['method' => 'POST', 'path' => '/api/auth/parent-login', 'handler' => 'AuthController@parentLogin'],
    
    // ─── Auth (Protected) ──────────────────────────────
    ['method' => 'GET', 'path' => '/api/auth/profile', 'handler' => 'AuthController@getProfile'],
    ['method' => 'PUT', 'path' => '/api/auth/profile', 'handler' => 'AuthController@updateProfile'],
    ['method' => 'PUT', 'path' => '/api/auth/password', 'handler' => 'AuthController@changePassword'],
    
    // ─── Dashboard ─────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/dashboard', 'handler' => 'DashboardController@getDashboard'],
    
    // ─── Users ─────────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController@getUsers'],
    ['method' => 'POST', 'path' => '/api/users', 'handler' => 'UserController@createUser'],
    ['method' => 'PUT', 'path' => '/api/users/{id}', 'handler' => 'UserController@updateUser'],
    ['method' => 'DELETE', 'path' => '/api/users/{id}', 'handler' => 'UserController@deleteUser'],
    
    // ─── Students ──────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/students', 'handler' => 'StudentController@getStudents'],
    ['method' => 'GET', 'path' => '/api/students/{id}', 'handler' => 'StudentController@getStudent'],
    ['method' => 'POST', 'path' => '/api/students', 'handler' => 'StudentController@createStudent'],
    ['method' => 'PUT', 'path' => '/api/students/{id}', 'handler' => 'StudentController@updateStudent'],
    ['method' => 'DELETE', 'path' => '/api/students/{id}', 'handler' => 'StudentController@deleteStudent'],
    
    // ─── Teachers ──────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/teachers', 'handler' => 'TeacherController@getTeachers'],
    ['method' => 'GET', 'path' => '/api/teachers/{id}', 'handler' => 'TeacherController@getTeacher'],
    ['method' => 'POST', 'path' => '/api/teachers', 'handler' => 'TeacherController@createTeacher'],
    ['method' => 'PUT', 'path' => '/api/teachers/{id}', 'handler' => 'TeacherController@updateTeacher'],
    ['method' => 'DELETE', 'path' => '/api/teachers/{id}', 'handler' => 'TeacherController@deleteTeacher'],
    
    // ─── Classes ───────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/classes', 'handler' => 'ClassController@getClasses'],
    ['method' => 'GET', 'path' => '/api/classes/{id}', 'handler' => 'ClassController@getClass'],
    ['method' => 'POST', 'path' => '/api/classes', 'handler' => 'ClassController@createClass'],
    ['method' => 'PUT', 'path' => '/api/classes/{id}', 'handler' => 'ClassController@updateClass'],
    ['method' => 'DELETE', 'path' => '/api/classes/{id}', 'handler' => 'ClassController@deleteClass'],
    
    // ─── Attendance ────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/attendance/sessions', 'handler' => 'AttendanceController@listSessions'],
    ['method' => 'POST', 'path' => '/api/attendance/sessions/open', 'handler' => 'AttendanceController@openSession'],
    ['method' => 'POST', 'path' => '/api/attendance/scan', 'handler' => 'AttendanceController@scanQR'],
    
    // ─── Exams ─────────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/exams', 'handler' => 'ExamController@getExams'],
    ['method' => 'GET', 'path' => '/api/exams/{id}', 'handler' => 'ExamController@getExam'],
    ['method' => 'POST', 'path' => '/api/exams', 'handler' => 'ExamController@createExam'],
    ['method' => 'POST', 'path' => '/api/exams/{id}/submit', 'handler' => 'ExamController@submitExam'],
    
    // ─── Billing ───────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/billing/tagihan', 'handler' => 'BillingController@getTagihan'],
    ['method' => 'POST', 'path' => '/api/billing/pembayaran', 'handler' => 'BillingController@createPembayaran'],
    
    // ─── School ────────────────────────────────────────
    ['method' => 'GET', 'path' => '/api/school', 'handler' => 'SchoolController@getSchool'],
    ['method' => 'PUT', 'path' => '/api/school', 'handler' => 'SchoolController@updateSchool'],
];

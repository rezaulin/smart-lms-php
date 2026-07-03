<?php

// Seed demo data for development/testing

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Env;
use App\Config\Database;

Env::load(__DIR__ . '/../.env');

$db = Database::connect();

echo "🌱 Seeding demo data...\n\n";

try {
    $db->beginTransaction();

    // 1. School
    echo "Creating demo school...\n";
    $stmt = $db->prepare("
        INSERT INTO schools (name, address, phone, email, npsn, level, yayasan_name, kabupaten, kepala_name, kepala_nip)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'SMA Negeri 1 Demo',
        'Jl. Pendidikan No. 123, Demo City',
        '021-12345678',
        'info@sman1demo.sch.id',
        '12345678',
        'SMA',
        'Yayasan Pendidikan Demo',
        'Kota Demo',
        'Drs. Ahmad Sudrajat, M.Pd',
        '196501011990031001'
    ]);
    $schoolId = $db->lastInsertId();

    // 2. Admin User
    echo "Creating admin user...\n";
    $stmt = $db->prepare("
        INSERT INTO users (name, email, password, role, school_id, active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Admin Demo',
        'admin@demo.test',
        password_hash('admin123', PASSWORD_BCRYPT),
        'admin_pusat',
        $schoolId,
        1
    ]);
    $adminId = $db->lastInsertId();

    // 3. Semester
    echo "Creating semester...\n";
    $stmt = $db->prepare("
        INSERT INTO semesters (name, year, period, start_date, end_date, active, school_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Ganjil 2025/2026',
        '2025/2026',
        'ganjil',
        '2025-07-01',
        '2025-12-31',
        1,
        $schoolId
    ]);
    $semesterId = $db->lastInsertId();

    // 4. Subjects
    echo "Creating subjects...\n";
    $subjects = [
        ['MTK', 'Matematika', 'X'],
        ['FIS', 'Fisika', 'X'],
        ['BIO', 'Biologi', 'X'],
        ['KIM', 'Kimia', 'X'],
        ['ING', 'Bahasa Inggris', 'X'],
        ['IND', 'Bahasa Indonesia', 'X'],
    ];

    $subjectIds = [];
    foreach ($subjects as $subject) {
        $stmt = $db->prepare("
            INSERT INTO subjects (code, name, level, school_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$subject[0], $subject[1], $subject[2], $schoolId]);
        $subjectIds[] = $db->lastInsertId();
    }

    // 5. Teachers
    echo "Creating teachers...\n";
    for ($i = 1; $i <= 3; $i++) {
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, school_id, active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            "Guru Demo {$i}",
            "guru{$i}@demo.test",
            password_hash('guru123', PASSWORD_BCRYPT),
            'guru',
            $schoolId,
            1
        ]);
        $userId = $db->lastInsertId();

        $stmt = $db->prepare("
            INSERT INTO teachers (user_id, nip, school_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, "19800101{$i}", $schoolId]);
    }

    // 6. Class
    echo "Creating class...\n";
    $stmt = $db->prepare("
        INSERT INTO classes (name, level, major, capacity, school_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute(['X IPA 1', 'X', 'IPA', 36, $schoolId]);
    $classId = $db->lastInsertId();

    // 7. Students
    echo "Creating students...\n";
    for ($i = 1; $i <= 10; $i++) {
        $stmt = $db->prepare("
            INSERT INTO users (name, email, student_id, password, role, school_id, active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $nisn = 2025000 + $i;
        $stmt->execute([
            "Siswa Demo {$i}",
            "siswa{$i}@demo.test",
            str_pad($i, 6, '0', STR_PAD_LEFT),
            password_hash('siswa123', PASSWORD_BCRYPT),
            'siswa',
            $schoolId,
            1
        ]);
        $userId = $db->lastInsertId();

        $stmt = $db->prepare("
            INSERT INTO students (user_id, nis, nisn, class_id, school_id, gender)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            "2025{$i}",
            (string)$nisn,
            $classId,
            $schoolId,
            $i % 2 == 0 ? 'P' : 'L'
        ]);
    }

    // 8. Jenis Tagihan (SPP)
    echo "Creating billing types...\n";
    $stmt = $db->prepare("
        INSERT INTO jenis_tagihan (school_id, nama, kode, nominal_default, periode, apply_potongan, aktif)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$schoolId, 'SPP', 'SPP', 500000, 'bulanan', true, true]);

    $db->commit();
    echo "\n✅ Demo data seeded successfully!\n\n";
    echo "Login credentials:\n";
    echo "Admin: admin@demo.test / admin123\n";
    echo "Guru: guru1@demo.test / guru123\n";
    echo "Siswa: siswa1@demo.test / siswa123\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth.php';

requireAuth();

$query = trim($_GET['q'] ?? '');
$results = [];
$user = currentUser();
$role = $user['role_name'] ?? 'student';

if ($query !== '') {
    $conn = getDbConnection();
    $like = '%' . $query . '%';

    if ($role === 'admin') {
        $stmt = $conn->prepare('SELECT student_id AS result_id, full_name AS result_name, "Student" AS result_type FROM students WHERE student_id LIKE ? OR full_name LIKE ? UNION ALL SELECT teacher_code, full_name, "Teacher" FROM teachers WHERE teacher_code LIKE ? OR full_name LIKE ? UNION ALL SELECT code, name_en, "Subject" FROM subjects WHERE code LIKE ? OR name_en LIKE ? ORDER BY result_name LIMIT 50');
        $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
    } elseif ($role === 'teacher') {
        $teacherId = 0;
        $teacherStmt = $conn->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
        $userId = (int) $user['id'];
        $teacherStmt->bind_param('i', $userId);
        $teacherStmt->execute();
        $teacherId = (int) ($teacherStmt->get_result()->fetch_assoc()['id'] ?? 0);
        $teacherStmt->close();

        $stmt = $conn->prepare('SELECT s.student_id AS result_id, s.full_name AS result_name, "Student" AS result_type FROM students s INNER JOIN teacher_subject_class tsc ON tsc.class_id = s.class_id WHERE tsc.teacher_id = ? AND (s.student_id LIKE ? OR s.full_name LIKE ?) GROUP BY s.id ORDER BY s.full_name LIMIT 50');
        $stmt->bind_param('iss', $teacherId, $like, $like);
    } else {
        $studentId = 0;
        $studentStmt = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
        $userId = (int) $user['id'];
        $studentStmt->bind_param('i', $userId);
        $studentStmt->execute();
        $studentId = (int) ($studentStmt->get_result()->fetch_assoc()['id'] ?? 0);
        $studentStmt->close();

        $stmt = $conn->prepare('SELECT student_id AS result_id, full_name AS result_name, "Student" AS result_type FROM students WHERE id = ? AND (student_id LIKE ? OR full_name LIKE ?) LIMIT 1');
        $stmt->bind_param('iss', $studentId, $like, $like);
    }

    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card-panel">
    <?php if ($query === ''): ?>
        <p class="text-muted mb-0"><?= e(translate('search_for')) ?></p>
    <?php elseif (!$results): ?>
        <p class="text-muted mb-0"><?= e(translate('no_results')) ?></p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th><?= e(translate('student_id')) ?></th>
                        <th><?= e(translate('name')) ?></th>
                        <th><?= e(translate('role')) ?></th>
                        <th>کردار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?= e($result['result_id']) ?></td>
                            <td><?= e($result['result_name']) ?></td>
                            <td><?= e($result['result_type']) ?></td>
                            <td>
                                <?php
                                $targetUrl = '#';
                                if ($result['result_type'] === 'Student') {
                                    $targetUrl = BASE_URL . '/admin/students.php?student_id=' . urlencode((string) $result['result_id']);
                                } elseif ($result['result_type'] === 'Teacher') {
                                    $targetUrl = BASE_URL . '/admin/teachers.php?teacher_code=' . urlencode((string) $result['result_id']);
                                } elseif ($result['result_type'] === 'Subject') {
                                    $targetUrl = BASE_URL . '/admin/subjects.php?subject_code=' . urlencode((string) $result['result_id']);
                                }
                                ?>
                                <a href="<?= e($targetUrl) ?>" class="btn btn-sm btn-primary">چونە پێشەوە</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
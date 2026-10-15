<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

requireAuth();
$user = currentUser();

$conn = getDbConnection();
$message = '';
$roleInfo = [];

if (($user['role_name'] ?? '') === 'teacher') {
    $stmt = $conn->prepare('SELECT teacher_code, specialty, phone, hire_date FROM teachers WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $roleInfo = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
} elseif (in_array($user['role_name'] ?? '', ['student', 'class_monitor'], true)) {
    $stmt = $conn->prepare('SELECT s.student_id, s.phone, c.name AS class_name, sec.name AS section_name FROM students s LEFT JOIN classes c ON c.id = s.class_id LEFT JOIN sections sec ON sec.id = s.section_id WHERE s.user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $roleInfo = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
} elseif (($user['role_name'] ?? '') === 'admin') {
    $roleInfo = [
        'students' => (string) ($conn->query('SELECT COUNT(*) AS total FROM students')->fetch_assoc()['total'] ?? 0),
        'teachers' => (string) ($conn->query('SELECT COUNT(*) AS total FROM teachers')->fetch_assoc()['total'] ?? 0),
        'classes' => (string) ($conn->query('SELECT COUNT(*) AS total FROM classes')->fetch_assoc()['total'] ?? 0),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ?');
    $userId = (int) $_SESSION['user_id'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();

    if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
        $message = '<div class="alert alert-danger">' . e(translate('invalid_credentials')) . '</div>';
    } elseif ($newPassword !== $confirmPassword) {
        $message = '<div class="alert alert-danger">' . e(translate('passwords_mismatch')) . '</div>';
    } elseif (strlen($newPassword) < 6) {
        $message = '<div class="alert alert-danger">Password must be at least 6 characters.</div>';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $userId);
        $stmt->execute();
        $stmt->close();
        $message = '<div class="alert alert-success">' . e(translate('password_changed')) . '</div>';
    }
}

$conn->close();
?>

<div class="page-titlebar">
    <div>
        <p class="eyebrow"><?= e(APP_NAME) ?></p>
        <h1><?= e(translate('profile')) ?></h1>
    </div>
    <div class="page-location"><i class="bi bi-geo-alt"></i> <?= e(SCHOOL_LOCATION) ?></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-panel">
            <h4 class="mb-3"><?= e(translate('personal_info')) ?></h4>
            <div class="row g-3">
                <div class="col-md-6"><strong><?= e(translate('full_name')) ?>:</strong> <?= e($user['full_name']) ?></div>
                <div class="col-md-6"><strong><?= e(translate('email')) ?>:</strong> <?= e($user['email']) ?></div>
                <div class="col-md-6"><strong><?= e(translate('role')) ?>:</strong> <?= e(translate($user['role_name'])) ?></div>
                <div class="col-md-6"><strong><?= e(translate('status')) ?>:</strong> <span class="badge-status success"><?= e($user['status']) ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-panel">
            <h4 class="mb-3"><?= e(translate('change_password')) ?></h4>
            <?= $message ?>
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-12"><input type="password" class="form-control" name="current_password" placeholder="<?= e(translate('current_password')) ?>" required></div>
                    <div class="col-md-12"><input type="password" class="form-control" name="new_password" placeholder="<?= e(translate('new_password')) ?>" required></div>
                    <div class="col-md-12"><input type="password" class="form-control" name="confirm_password" placeholder="<?= e(translate('confirm_password')) ?>" required></div>
                    <div class="col-md-12"><button type="submit" name="change_password" class="btn btn-primary"><?= e(translate('save')) ?></button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-12">
        <div class="card-panel">
            <h4 class="mb-3"><?= e(translate('personal_info')) ?></h4>
            <?php if ($roleInfo): ?>
                <div class="row g-3">
                    <?php foreach ($roleInfo as $field => $value): ?>
                        <?php $label = match ($field) { 'teacher_code' => translate('code'), 'specialty' => translate('specialty'), 'phone' => translate('phone'), 'hire_date' => translate('hire_date'), 'student_id' => translate('student_id'), 'class_name' => translate('classes'), 'section_name' => translate('section'), default => translate($field) }; ?>
                        <div class="col-sm-6 col-lg-4"><div class="profile-detail"><span><?= e($label) ?></span><strong><?= e($value ?: '—') ?></strong></div></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No additional profile information is available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

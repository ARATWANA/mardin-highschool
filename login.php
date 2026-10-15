<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = translate('email_required');
    } else {
        $conn = getDbConnection();
        $stmt = $conn->prepare(
            'SELECT u.*, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['user_name'] = $user['full_name'];
            redirect(BASE_URL . '/dashboard.php');
        }

        $errors[] = translate('invalid_credentials');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en'; ?>" dir="<?= isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('login') ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=20260915-3">
</head>
<body class="auth-body">
    <div class="container-fluid">
        <div class="row min-vh-100 align-items-center justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-10">
                <div class="auth-card card shadow-lg border-0">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <div class="auth-logo mx-auto mb-3">
                                <img src="<?= BASE_URL ?>/assets/images/school-logo.jpg" alt="<?= e(APP_NAME) ?> logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <span class="brand-fallback">MHS</span>
                            </div>
                            <h2 class="fw-bold mb-1"><?= APP_NAME ?></h2>
                            <p class="text-muted mb-0"><?= SCHOOL_LOCATION ?></p>
                        </div>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= e($success) ?></div>
                        <?php endif; ?>

                        <form method="post" action="<?= BASE_URL ?>/login.php">
                            <div class="mb-3">
                                <label class="form-label"><?= translate('email') ?></label>
                                <input type="email" name="email" class="form-control form-control-lg" placeholder="<?= e(translate('email')) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= translate('password') ?></label>
                                <input type="password" name="password" class="form-control form-control-lg" placeholder="<?= e(translate('password')) ?>" required>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember">
                                    <label class="form-check-label" for="remember"><?= translate('remember_me') ?></label>
                                </div>
                                <a href="#" class="text-decoration-none small"><?= translate('forgot_password') ?></a>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100"><?= translate('login') ?></button>
                        </form>

                        <div class="mt-4 pt-3 border-top text-center small text-muted">
                            Create the first administrator securely using the setup page, then sign in with that account.
                        </div>
                        <div class="mt-2 text-center">
                            <a href="<?= BASE_URL ?>/setup_admin.php" class="btn btn-sm btn-outline-secondary">Create admin account</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

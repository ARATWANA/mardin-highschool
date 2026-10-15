<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        $errors[] = 'Full name, email, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $conn = getDbConnection();
        $roleName = 'admin';
        $stmt = $conn->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$role) {
            $errors[] = 'The admin role does not exist yet. Please import the database schema first.';
        } else {
            $roleId = (int) $role['id'];
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $existingUser = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($existingUser) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare('UPDATE users SET full_name = ?, password_hash = ?, status = ? WHERE id = ?');
                $status = 'active';
                $userId = (int) $existingUser['id'];
                $updateStmt->bind_param('sssi', $fullName, $hash, $status, $userId);
                $updateStmt->execute();
                $updateStmt->close();
                $success = 'Admin account updated successfully. You can now log in.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $conn->prepare('INSERT INTO users (full_name, email, username, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?, ?)');
                $username = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fullName));
                $username = trim((string) $username, '-');
                if ($username === '') {
                    $username = 'admin';
                }
                $status = 'active';
                $insertStmt->bind_param('ssssis', $fullName, $email, $username, $hash, $roleId, $status);
                $insertStmt->execute();
                $insertStmt->close();
                $success = 'Admin account created successfully. You can now log in.';
            }

            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f8fafc, #e2e8f0); min-height: 100vh; }
        .card { border: 0; border-radius: 18px; box-shadow: 0 16px 35px rgba(15, 23, 42, 0.12); }
        .brand { font-size: 1.6rem; font-weight: 700; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-10">
                <div class="card p-4">
                    <div class="text-center mb-4">
                        <div class="brand">Mardin High School</div>
                        <p class="text-muted mb-0">Secure admin bootstrap</p>
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
                        <div class="text-center">
                            <a href="<?= e(BASE_URL) ?>/login.php" class="btn btn-primary">Go to login</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                        <form method="post" novalidate>
                            <div class="mb-3">
                                <label class="form-label">Full name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Create admin account</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

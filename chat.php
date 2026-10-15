<?php
echo "START<br>";
require_once __DIR__ . '/includes/auth.php';

requireAuth();

echo "TEST<br>";

$conn = getDbConnection();
$user = currentUser();
$currentUserId = (int) ($user['id'] ?? 0);
$role = $user['role_name'] ?? 'student';

$message = '';
$hasGlobalAccess = ($role === 'admin');
if (!$hasGlobalAccess) {
    $chk = $conn->prepare('SELECT id FROM chat_requests WHERE user_id = ? AND status = "approved" LIMIT 1');
    $chk->bind_param('i', $currentUserId);
    $chk->execute();
    $hasGlobalAccess = (bool) $chk->get_result()->fetch_assoc();
    $chk->close();
}

$hasPendingRequest = false;
if (!$hasGlobalAccess) {
    $pend = $conn->prepare('SELECT id FROM chat_requests WHERE user_id = ? AND status = "pending" LIMIT 1');
    $pend->bind_param('i', $currentUserId);
    $pend->execute();
    $hasPendingRequest = (bool) $pend->get_result()->fetch_assoc();
    $pend->close();
}

$conversations = [];
if ($currentUserId > 0) {
    $convStmt = $conn->prepare('
        SELECT c.id, c.is_group, c.group_name, c.created_at,
                u.full_name AS other_name, NULL AS other_photo, r.name AS other_role,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count,
                (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_msg,
                (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_msg_time,
                (SELECT u2.full_name FROM messages m2 INNER JOIN users u2 ON u2.id = m2.sender_id WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) AS last_sender
        FROM conversations c
        INNER JOIN conversation_members cm ON cm.conversation_id = c.id
        LEFT JOIN users u ON (c.is_group = 0 AND ((cm.user_id = ? AND c.created_by != ? AND u.id = c.created_by) OR (c.created_by = ? AND u.id = cm.user_id AND cm.user_id != c.created_by)))
        LEFT JOIN users u2 ON u2.id = cm.user_id
        LEFT JOIN roles r ON r.id = (SELECT role_id FROM users WHERE id = u2.id LIMIT 1)
        WHERE cm.user_id = ?
        GROUP BY c.id
        ORDER BY last_msg_time DESC
    ');
    $convStmt->bind_param('iiiii', $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
    $convStmt->execute();
    $conversations = $convStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $convStmt->close();
}

$allUsers = [];
$allClasses = [];
if ($role === 'admin') {
    $uRes = $conn->query('SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" ORDER BY r.name, u.full_name');
    while ($ur = $uRes->fetch_assoc()) {
        if ((int) $ur['id'] !== $currentUserId) {
            $allUsers[] = $ur;
        }
    }
    $allClasses = $conn->query('SELECT * FROM classes WHERE status = "active" ORDER BY name')->fetch_all(MYSQLI_ASSOC);
} elseif ($role === 'teacher') {
    $tId = 0;
    $ts = $conn->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
    $ts->bind_param('i', $currentUserId);
    $ts->execute();
    $tr = $ts->get_result()->fetch_assoc();
    $tId = (int) ($tr['id'] ?? 0);
    $ts->close();
    if ($tId > 0) {
        $allUsers = $conn->query("SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' AND u.id != {$currentUserId} ORDER BY r.name, u.full_name")->fetch_all(MYSQLI_ASSOC);
        $allClasses = $cn->query("SELECT DISTINCT c.* FROM classes c INNER JOIN teacher_subject_class tsc ON tsc.class_id = c.id WHERE tsc.teacher_id = {$tId} AND c.status = 'active' ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
    }
} elseif ($role === 'student') {
    $sId = 0;
    $ss = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
    $ss->bind_param('i', $currentUserId);
    $ss->execute();
    $sr = $ss->get_result()->fetch_assoc();
    $sId = (int) ($sr['id'] ?? 0);
    $ss->close();
    if ($sId > 0) {
        $allUsers = $cn->query("SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' AND u.id != {$currentUserId} ORDER BY r.name, u.full_name")->fetch_all(MYSQLI_ASSOC);
        $scRes = $cn->query("SELECT class_id FROM students WHERE id = {$sId} LIMIT 1");
        $scRow = $scRes->fetch_assoc();
        $myClass = (int) ($scRow['class_id'] ?? 0);
        if ($myClass > 0) {
            $allClasses = $cn->query("SELECT * FROM classes WHERE id = {$myClass} AND status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
        }
    }
} else {
    $mId = 0;
    $ms = $conn->prepare('SELECT class_id FROM class_monitors WHERE student_id IN (SELECT id FROM students WHERE user_id = ?) AND status = "active" LIMIT 1');
    $ms->bind_param('i', $currentUserId);
    $ms->execute();
    $mr = $ms->get_result()->fetch_assoc();
    $mId = (int) ($mr['class_id'] ?? 0);
    $ms->close();
    if ($mId > 0) {
        $allUsers = $cn->query("SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' AND u.id != {$currentUserId} ORDER BY r.name, u.full_name")->fetch_all(MYSQLI_ASSOC);
        $allClasses = $cn->query("SELECT * FROM classes WHERE id = {$mId} AND status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    }
}

// If a conversation ID is provided, fetch its data, mark messages as read, and get messages and members
if (isset($_GET['c']) && $_GET['c'] !== '') {
    $convId = (int) $_GET['c'];
    $convInfo = $conn->prepare('SELECT * FROM conversations WHERE id = ?');
    $convInfo->bind_param('i', $convId);
    $convInfo->execute();
    $convData = $convInfo->get_result()->fetch_assoc();
    $convInfo->close();
    if ($convData) {
        $markRead = $conn->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?');
        $markRead->bind_param('ii', $convId, $currentUserId);
        $markRead->execute();
        $markRead->close();
        $msgs = $conn->prepare('SELECT m.*, u.full_name AS sender_name, u.photo AS sender_photo FROM messages m INNER JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ? ORDER BY m.created_at ASC');
        $msgs->bind_param('i', $convId);
        $msgs->execute();
        $messages = $msgs->get_result();
        $getMembers = $conn->prepare('SELECT cm.user_id, u.full_name, u.photo FROM conversation_members cm INNER JOIN users u ON u.id = cm.user_id WHERE cm.conversation_id = ?');
        $getMembers->bind_param('i', $convId);
        $getMembers->execute();
        $members = $getMembers->get_result()->fetch_all(MYSQLI_ASSOC);
        $getMembers->close();
    }
}

// Handle sending a chat message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_chat'])) {
    $convId = (int) ($_POST['conversation_id'] ?? 0);
    if ($convId < 1) {
        http_response_code(400);
        exit;
    }
    // Check if user can access the conversation
    $participantCheck = $conn->prepare('SELECT id FROM conversation_members WHERE conversation_id = ? AND user_id = ? LIMIT 1');
    $participantCheck->bind_param('ii', $convId, $currentUserId);
    $participantCheck->execute();
    $isParticipant = (bool) $participantCheck->get_result()->fetch_assoc();
    $participantCheck->close();
    if (!$isParticipant) {
        http_response_code(403);
        exit;
    }
    $messageText = trim((string) ($_POST['message'] ?? ''));
    $hasFiles = !empty($_FILES['attachments']['name'][0]);
    if ($messageText === '' && !$hasFiles) {
        // We'll not send an empty message with no files
        http_response_code(400);
        exit;
    }
    // We'll insert the message
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO messages (conversation_id, sender_id, message_text, is_read) VALUES (?, ?, ?, 0)');
        $stmt->bind_param('iis', $convId, $currentUserId, $messageText);
        $stmt->execute();
        $messageId = $stmt->insert_id;
        $stmt->close();
        // Get sender's full name for notifications
        $senderStmt = $conn->prepare('SELECT full_name FROM users WHERE id = ?');
        $senderStmt->bind_param('i', $currentUserId);
        $senderStmt->execute();
        $sender = $senderStmt->get_result()->fetch_assoc();
        $senderStmt->close();
        $senderName = $sender['full_name'] ?? 'Unknown';
        // Insert notifications for other participants
        $participantStmt = $cn->prepare('SELECT user_id FROM conversation_members WHERE conversation_id = ? AND user_id != ?');
        $participantStmt->bind_param('ii', $convId, $currentUserId);
        $participantStmt->execute();
        $participantResult = $participantStmt->get_result();
        while ($participant = $participantResult->fetch_assoc()) {
            $notifStmt = $cn->prepare('INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)');
            $notifStmt->bind_param('iss', $participant['user_id'], 'Messenger: New message', 'New message from ' . $senderName);
            $notifStmt->execute();
            $notifStmt->close();
        }
        $participantStmt->close();
        // Process attachments
        if ($hasFiles) {
            $uploadedPaths = [];
            $uploadDir = realpath(__DIR__ . '/assets/uploads/chat');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $totalFiles = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                $name = $_FILES['attachments']['name'][$i];
                $type = $_FILES['attachments']['type'][$i];
                $tmpName = $_FILES['attachments']['tmp_name'][$i];
                $error = $_FILES['attachments']['error'][$i];
                $size = $_FILES['attachments']['size'][$i];
                if ($error !== UPLOAD_ERR_OK) {
                    continue;
                }
                // Validate file type: we'll allow images, audio, video, and common document types
                $allowedTypes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'audio/mpeg', 'audio/wav', 'audio/webm',
                    'video/mp4', 'video/quicktime', 'video/webm',
                    'application/pdf',
                    'application/msword', // .doc
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
                ];
                if (!in_array($type, $allowedTypes, true)) {
                    continue;
                }
                // Generate a unique filename
                $ext = '';
                if (strpos($name, '.') !== false) {
                    $ext = '.' . pathinfo($name, PATHINFO_EXTENSION);
                }
                $storedName = bin2hex(random_bytes(12)) . $ext;
                $dest = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
                if (move_uploaded_file($tmpName, $dest)) {
                    $uploadedPaths[] = $dest;
                    // Save attachment record
                    $attachmentStmt = $cn->prepare('INSERT INTO chat_attachments (message_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)');
                    $attachmentStmt->bind_param('isssi', $messageId, $name, $storedName, $type, $size);
                    $attachmentStmt->execute();
                    $attachmentStmt->close();
                }
            }
            // We'll not delete uploaded files on error because we are in a transaction and will rollback? We'll rollback the transaction and delete files.
        }
        // Update conversation's updated_at? We don't have an updated_at in conversations table. We'll not worry.
        $conn->commit();
        // Redirect back to the same conversation to avoid resubmission
        header('Location: ' . BASE_URL . '/chat.php?c=' . $convId, true, 303);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        // Delete uploaded files
        if (isset($uploadedPaths)) {
            foreach ($uploadedPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        http_response_code(500);
        exit;
    }
}

$conn->close();
?>
<div class="dashboard-heading">
    <div>
        <p class="eyebrow"><?= e(APP_NAME) ?></p>
        <h1><?= e(translate('conversations')) ?></h1>
    </div>
    <div class="dashboard-date"><i class="bi bi-calendar3"></i> <?= date('d M Y') ?></div>
</div>

<?php if (isset($_GET['c']) && $_GET['c'] !== '' && isset($convData)): ?>
<div class="row g-0 chat-room">
    <div class="col-lg-4 col-xl-3 chat-list-col border-end">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <h5 class="mb-0"><?= e(translate('conversations')) ?></h5>
            <a href="<?= e(BASE_URL) ?>/chat.php" class="btn btn-sm btn-outline-primary"><?= e(translate('back')) ?></a>
        </div>
        <div class="chat-list" style="max-height: calc(100vh - 140px); overflow-y: auto;">
            <?php foreach ($conversations as $cv): ?>
                <a href="?c=<?= (int) $cv['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-start py-3 <?= (isset($_GET['c']) && (int) $_GET['c'] === (int) $cv['id']) ? 'bg-light' : '' ?>">
                    <div class="chat-list-avatar me-3">
                        <?php if ($cv['is_group']): ?>
                            <div class="avatar-group">G</div>
                        <?php elseif ($cv['other_photo']): ?>
                            <img src="<?= BASE_URL ?>/assets/uploads/chat/<?= e($cv['other_photo']) ?>" alt="" class="chat-avatar">
                        <?php else: ?>
                            <div class="chat-avatar"><?= e(mb_substr($cv['other_name'] ?? '?', 0, 1, 'UTF-8')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between">
                            <strong class="truncate"><?= e($cv['is_group'] ? ($cv['group_name'] ?? 'Group') : ($cv['other_name'] ?? '?')) ?></strong>
                            <small class="text-muted flex-shrink-0"><?= e(date('H:i', strtotime($cv['last_msg_time'] ?? 'now'))) ?></small
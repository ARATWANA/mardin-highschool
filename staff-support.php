<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support.php';
requireAuth();
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$role = (string) ($user['role_name'] ?? '');
$conn = getDbConnection();
$csrfToken = supportCsrfToken();
$ticketId = (int) ($_GET['ticket'] ?? 0);
$successMessage = (string) ($_SESSION['support_flash'] ?? '');
$errorMessage = (string) ($_SESSION['support_error'] ?? '');
unset($_SESSION['support_flash'], $_SESSION['support_error']);
$statuses = ['new', 'received', 'reviewing', 'waiting_student', 'waiting_teacher', 'in_progress', 'resolved', 'closed'];
$canManageInbox = in_array($role, ['admin', 'teacher'], true);
$transactionOpen = false;
$teacherClassOptions = [];
$relatedStudentOptions = [];
if ($role === 'teacher') {
    $classOptionsStmt = $conn->prepare('SELECT DISTINCT c.id, c.name FROM teachers t INNER JOIN teacher_subject_class tsc ON tsc.teacher_id = t.id INNER JOIN classes c ON c.id = tsc.class_id WHERE t.user_id = ? AND c.status = "active" ORDER BY c.name');
    $classOptionsStmt->bind_param('i', $userId);
    $classOptionsStmt->execute();
    $teacherClassOptions = $classOptionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $classOptionsStmt->close();
    $studentsStmt = $conn->prepare('SELECT DISTINCT s.id, s.full_name, s.class_id FROM students s INNER JOIN teacher_subject_class tsc ON tsc.class_id = s.class_id INNER JOIN teachers t ON t.id = tsc.teacher_id WHERE t.user_id = ? AND s.status = "active" ORDER BY s.full_name');
    $studentsStmt->bind_param('i', $userId);
    $studentsStmt->execute();
    $relatedStudentOptions = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $studentsStmt->close();
}
$canOpenRequestedTicket = $ticketId > 0 && supportCanAccessConversation($conn, 'staff', $ticketId, $userId, $role);
if (!$canManageInbox && !$canOpenRequestedTicket) {
    $conn->close();
    redirect(BASE_URL . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!supportVerifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create_staff_ticket' && $role === 'teacher') {
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $category = trim((string) ($_POST['category'] ?? ''));
                $classId = (int) ($_POST['class_id'] ?? 0);
                $relatedStudentId = (int) ($_POST['related_student_id'] ?? 0);
                if ($title === '' || mb_strlen($title) > 255 || $description === '' || mb_strlen($description) > 10000) {
                    throw new InvalidArgumentException('Enter a title and request details.');
                }
                if ($relatedStudentId > 0) {
                    $studentAccess = $conn->prepare('SELECT s.class_id FROM students s INNER JOIN teacher_subject_class tsc ON tsc.class_id = s.class_id INNER JOIN teachers t ON t.id = tsc.teacher_id WHERE s.id = ? AND t.user_id = ? AND s.status = "active" AND (? = 0 OR s.class_id = ?) LIMIT 1');
                    $studentAccess->bind_param('iiii', $relatedStudentId, $userId, $classId, $classId);
                    $studentAccess->execute();
                    $studentContext = $studentAccess->get_result()->fetch_assoc();
                    $studentAccess->close();
                    if (!$studentContext) {
                        throw new InvalidArgumentException('Choose a student from one of your assigned classes.');
                    }
                    $classId = (int) $studentContext['class_id'];
                } elseif ($classId > 0) {
                    $classAccess = $conn->prepare('SELECT c.id FROM classes c INNER JOIN teacher_subject_class tsc ON tsc.class_id = c.id INNER JOIN teachers t ON t.id = tsc.teacher_id WHERE c.id = ? AND t.user_id = ? LIMIT 1');
                    $classAccess->bind_param('ii', $classId, $userId);
                    $classAccess->execute();
                    $authorizedClass = (bool) $classAccess->get_result()->fetch_assoc();
                    $classAccess->close();
                    if (!$authorizedClass) {
                        throw new InvalidArgumentException('Choose one of your assigned classes.');
                    }
                }
                $conn->begin_transaction();
                $transactionOpen = true;
                $temporaryNumber = 'TMP-' . bin2hex(random_bytes(12));
                $create = $conn->prepare('INSERT INTO staff_support_tickets (ticket_number, created_by, title, description, category, related_student_id, class_id) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0))');
                $categoryValue = $category !== '' ? $category : null;
                $create->bind_param('sisssii', $temporaryNumber, $userId, $title, $description, $categoryValue, $relatedStudentId, $classId);
                $create->execute();
                $ticketId = $create->insert_id;
                $create->close();
                $ticketNumber = 'MHS-S-' . date('Y') . '-' . str_pad((string) $ticketId, 6, '0', STR_PAD_LEFT);
                $updateNumber = $conn->prepare('UPDATE staff_support_tickets SET ticket_number = ? WHERE id = ?');
                $updateNumber->bind_param('si', $ticketNumber, $ticketId);
                $updateNumber->execute();
                $updateNumber->close();
                supportAddParticipant($conn, 'staff', $ticketId, $userId, $role);
                $message = $conn->prepare('INSERT INTO staff_support_messages (ticket_id, sender_id, message_text) VALUES (?, ?, ?)');
                $message->bind_param('iis', $ticketId, $userId, $description);
                $message->execute();
                $messageId = $message->insert_id;
                $message->close();
                supportStoreAttachments($conn, $ticketId, $messageId, $_FILES['attachments'] ?? [], 'staff');
                $initialStatus = $conn->prepare('INSERT INTO staff_support_status_history (ticket_id, status, changed_by, notes) VALUES (?, "new", ?, "Staff request created")');
                $initialStatus->bind_param('ii', $ticketId, $userId);
                $initialStatus->execute();
                $initialStatus->close();
                $admins = $conn->query('SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.name = "admin" AND u.status = "active"')->fetch_all(MYSQLI_ASSOC);
                foreach ($admins as $admin) {
                    supportCreateNotification($conn, (int) $admin['id'], 'Staff Support: New request', $ticketNumber . ': ' . $title);
                }
                $conn->commit();
                $transactionOpen = false;
                $_SESSION['support_flash'] = 'Staff request created: ' . $ticketNumber;
                header('Location: ' . BASE_URL . '/staff-support.php?ticket=' . $ticketId, true, 303);
                exit;
            }
            if ($action === 'staff_reply') {
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                if (!supportCanAccessConversation($conn, 'staff', $ticketId, $userId, $role)) {
                    throw new RuntimeException('You cannot access this staff conversation.');
                }
                $replyText = trim((string) ($_POST['message_text'] ?? ''));
                $hasFiles = !empty($_FILES['attachments']['name'][0]);
                $ticketStatusStmt = $conn->prepare('SELECT status FROM staff_support_tickets WHERE id = ?');
                $ticketStatusStmt->bind_param('i', $ticketId);
                $ticketStatusStmt->execute();
                $currentStatus = $ticketStatusStmt->get_result()->fetch_assoc();
                $ticketStatusStmt->close();
                if (!$currentStatus || $currentStatus['status'] === 'closed' || ($replyText === '' && !$hasFiles) || mb_strlen($replyText) > 10000) {
                    throw new InvalidArgumentException('This conversation is closed or the reply is empty.');
                }
                $messageText = $replyText !== '' ? $replyText : 'Shared an attachment.';
                $reply = $conn->prepare('INSERT INTO staff_support_messages (ticket_id, sender_id, message_text) VALUES (?, ?, ?)');
                $reply->bind_param('iis', $ticketId, $userId, $messageText);
                $reply->execute();
                $messageId = $reply->insert_id;
                $reply->close();
                supportStoreAttachments($conn, $ticketId, $messageId, $_FILES['attachments'] ?? [], 'staff');
                $touch = $conn->prepare('UPDATE staff_support_tickets SET updated_at = NOW() WHERE id = ?');
                $touch->bind_param('i', $ticketId);
                $touch->execute();
                $touch->close();
                supportNotifyConversation($conn, 'staff', $ticketId, $userId, 'New reply', 'A new message was added to a staff request.');
                header('Location: ' . BASE_URL . '/staff-support.php?ticket=' . $ticketId, true, 303);
                exit;
            }
            if ($action === 'staff_status' && in_array($role, ['admin', 'teacher'], true) && supportConversationOwnerAccess($conn, 'staff', (int) ($_POST['ticket_id'] ?? 0), $userId, $role)) {
                $ticketId = (int) $_POST['ticket_id'];
                $newStatus = (string) ($_POST['status'] ?? '');
                if (!in_array($newStatus, $statuses, true)) {
                    throw new InvalidArgumentException('Choose a valid status.');
                }
                $statusUpdate = $conn->prepare('UPDATE staff_support_tickets SET status = ?, updated_at = NOW() WHERE id = ?');
                $statusUpdate->bind_param('si', $newStatus, $ticketId);
                $statusUpdate->execute();
                $changed = $statusUpdate->affected_rows;
                $statusUpdate->close();
                if ($changed) {
                    $note = trim((string) ($_POST['status_note'] ?? ''));
                    $history = $conn->prepare('INSERT INTO staff_support_status_history (ticket_id, status, changed_by, notes) VALUES (?, ?, ?, ?)');
                    $history->bind_param('isis', $ticketId, $newStatus, $userId, $note);
                    $history->execute();
                    $history->close();
                    supportNotifyConversation($conn, 'staff', $ticketId, $userId, 'Status changed', 'Staff request status changed to ' . str_replace('_', ' ', $newStatus) . '.');
                }
                header('Location: ' . BASE_URL . '/staff-support.php?ticket=' . $ticketId, true, 303);
                exit;
            }
            if ($action === 'staff_assign' && $role === 'admin' && supportConversationOwnerAccess($conn, 'staff', (int) ($_POST['ticket_id'] ?? 0), $userId, $role)) {
                $ticketId = (int) $_POST['ticket_id'];
                $assigneeId = (int) ($_POST['assigned_to'] ?? 0);
                $allowedAssignee = $conn->prepare('SELECT u.id, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active" AND r.name IN ("admin", "teacher") LIMIT 1');
                $allowedAssignee->bind_param('i', $assigneeId);
                $allowedAssignee->execute();
                $assignee = $allowedAssignee->get_result()->fetch_assoc();
                $allowedAssignee->close();
                if (!$assignee) {
                    throw new InvalidArgumentException('Choose an active school staff member.');
                }
                $assign = $conn->prepare('UPDATE staff_support_tickets SET assigned_to = ?, assigned_at = NOW(), updated_at = NOW() WHERE id = ?');
                $assign->bind_param('ii', $assigneeId, $ticketId);
                $assign->execute();
                $assign->close();
                supportAddParticipant($conn, 'staff', $ticketId, $assigneeId, (string) $assignee['role_name'], $userId);
                supportAudit($conn, 'staff', $ticketId, $userId, 'assigned', null, $assigneeId);
                supportCreateNotification($conn, $assigneeId, 'Staff Support: Request assigned', supportConversationTicketNumber($conn, 'staff', $ticketId) . ': A staff request was assigned to you.');
                supportNotifyConversation($conn, 'staff', $ticketId, $userId, 'Request assigned', 'The request was assigned to a staff member.');
                header('Location: ' . BASE_URL . '/staff-support.php?ticket=' . $ticketId, true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            if ($transactionOpen) {
                $conn->rollback();
                $transactionOpen = false;
            }
            $errorMessage = $exception instanceof RuntimeException ? $exception->getMessage() : 'The staff support request could not be saved.';
        }
    }
}

$filter = trim((string) ($_GET['q'] ?? ''));
$list = [];
if ($canManageInbox) {
    $listSql = 'SELECT t.*, creator.full_name AS creator_name, assignee.full_name AS assignee_name, (SELECT m.message_text FROM staff_support_messages m WHERE m.ticket_id = t.id AND m.id > COALESCE((SELECT p.history_floor_id FROM support_conversation_participants p WHERE p.conversation_type = "staff" AND p.conversation_id = t.id AND p.user_id = ? AND p.left_at IS NULL LIMIT 1), 0) ORDER BY m.id DESC LIMIT 1) AS last_message, (SELECT COUNT(*) FROM staff_support_messages m WHERE m.ticket_id = t.id AND m.sender_id <> ? AND m.is_read = 0 AND m.id > COALESCE((SELECT p.history_floor_id FROM support_conversation_participants p WHERE p.conversation_type = "staff" AND p.conversation_id = t.id AND p.user_id = ? AND p.left_at IS NULL LIMIT 1), 0)) AS unread_count FROM staff_support_tickets t INNER JOIN users creator ON creator.id = t.created_by LEFT JOIN users assignee ON assignee.id = t.assigned_to WHERE (? = "admin" OR t.created_by = ? OR t.assigned_to = ? OR EXISTS (SELECT 1 FROM support_conversation_participants p WHERE p.conversation_type = "staff" AND p.conversation_id = t.id AND p.user_id = ? AND p.left_at IS NULL)) AND (? = "" OR t.ticket_number LIKE CONCAT("%", ?, "%") OR t.title LIKE CONCAT("%", ?, "%") OR creator.full_name LIKE CONCAT("%", ?, "%")) ORDER BY t.updated_at DESC LIMIT 200';
    $listStmt = $conn->prepare($listSql);
    $listStmt->bind_param('iiisiiissss', $userId, $userId, $userId, $role, $userId, $userId, $userId, $filter, $filter, $filter, $filter);
    $listStmt->execute();
    $list = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();
}

$ticket = null;
$messages = [];
$attachments = [];
$history = [];
$participants = [];
$conversationInvitations = [];
$historyRequests = [];
$eligibleInvitees = [];
$assigneeOptions = [];
$canViewOriginalRequest = false;
if ($ticketId > 0 && supportCanAccessConversation($conn, 'staff', $ticketId, $userId, $role)) {
    $participantCheck = $conn->prepare('SELECT id FROM support_conversation_participants WHERE conversation_type = "staff" AND conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1');
    $participantCheck->bind_param('ii', $ticketId, $userId);
    $participantCheck->execute();
    $isParticipant = (bool) $participantCheck->get_result()->fetch_assoc();
    $participantCheck->close();
    if (!$isParticipant && supportConversationOwnerAccess($conn, 'staff', $ticketId, $userId, $role)) {
        supportAddParticipant($conn, 'staff', $ticketId, $userId, $role);
        if ($role === 'admin') {
            supportAudit($conn, 'staff', $ticketId, $userId, 'admin_access', 'Admin opened staff support conversation.');
        }
    }
    $visibleFrom = supportVisibleFrom($conn, 'staff', $ticketId, $userId);
    $visibleFloor = supportVisibleMessageFloor($conn, 'staff', $ticketId, $userId);
    $ticketStmt = $conn->prepare('SELECT t.*, creator.full_name AS creator_name, creator.photo AS creator_photo, assignee.full_name AS assignee_name FROM staff_support_tickets t INNER JOIN users creator ON creator.id = t.created_by LEFT JOIN users assignee ON assignee.id = t.assigned_to WHERE t.id = ? LIMIT 1');
    $ticketStmt->bind_param('i', $ticketId);
    $ticketStmt->execute();
    $ticket = $ticketStmt->get_result()->fetch_assoc();
    $ticketStmt->close();
    $originalMessageStmt = $conn->prepare('SELECT id FROM staff_support_messages WHERE ticket_id = ? ORDER BY id ASC LIMIT 1');
    $originalMessageStmt->bind_param('i', $ticketId);
    $originalMessageStmt->execute();
    $originalMessageId = (int) ($originalMessageStmt->get_result()->fetch_assoc()['id'] ?? 0);
    $originalMessageStmt->close();
    $canViewOriginalRequest = $originalMessageId > 0 && supportCanViewMessage($conn, 'staff', $ticketId, $userId, $originalMessageId);
    $msgStmt = $conn->prepare('SELECT m.*, u.full_name AS sender_name, u.photo AS sender_photo FROM staff_support_messages m INNER JOIN users u ON u.id = m.sender_id WHERE m.ticket_id = ? AND (m.id > ? OR EXISTS (SELECT 1 FROM support_history_requests h WHERE h.conversation_type = "staff" AND h.conversation_id = ? AND h.requester_id = ? AND h.status = "approved" AND (h.scope_type = "all" OR (h.scope_type = "date_range" AND m.created_at BETWEEN h.scope_start AND h.scope_end) OR (h.scope_type = "message_range" AND m.id BETWEEN h.start_message_id AND h.end_message_id)))) ORDER BY m.created_at, m.id');
    $msgStmt->bind_param('iiii', $ticketId, $visibleFloor, $ticketId, $userId);
    $msgStmt->execute();
    $messages = $msgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $msgStmt->close();
    $ids = array_map(static fn($item) => (int) $item['id'], $messages);
    if ($ids) {
        $attachmentStmt = $conn->prepare('SELECT * FROM staff_support_attachments WHERE ticket_id = ? AND message_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
        $values = array_merge([$ticketId], $ids);
        supportBindParams($attachmentStmt, 'i' . str_repeat('i', count($ids)), $values);
        $attachmentStmt->execute();
        $attachments = $attachmentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $attachmentStmt->close();
    }
    $readStmt = $conn->prepare('UPDATE staff_support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_id <> ? AND id > ?');
    $readStmt->bind_param('iii', $ticketId, $userId, $visibleFloor);
    $readStmt->execute();
    $readStmt->close();
    $historyStmt = $conn->prepare('SELECT h.*, u.full_name AS changed_by_name FROM staff_support_status_history h INNER JOIN users u ON u.id = h.changed_by WHERE h.ticket_id = ? ORDER BY h.changed_at DESC');
    $historyStmt->bind_param('i', $ticketId);
    $historyStmt->execute();
    $history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $historyStmt->close();
    $participantStmt = $conn->prepare('SELECT p.*, u.full_name, r.name AS role_name FROM support_conversation_participants p INNER JOIN users u ON u.id = p.user_id INNER JOIN roles r ON r.id = u.role_id WHERE p.conversation_type = "staff" AND p.conversation_id = ? AND p.left_at IS NULL');
    $participantStmt->bind_param('i', $ticketId);
    $participantStmt->execute();
    $participants = $participantStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $participantStmt->close();
    $inviteStmt = $conn->prepare('SELECT i.*, u.full_name AS recipient_name, inviter.full_name AS inviter_name FROM support_invitations i INNER JOIN users u ON u.id = i.recipient_id INNER JOIN users inviter ON inviter.id = i.invited_by WHERE i.conversation_type = "staff" AND i.conversation_id = ? ORDER BY i.created_at DESC');
    $inviteStmt->bind_param('i', $ticketId);
    $inviteStmt->execute();
    $conversationInvitations = $inviteStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $inviteStmt->close();
    $historyRequestStmt = $conn->prepare('SELECT h.*, u.full_name AS requester_name FROM support_history_requests h INNER JOIN users u ON u.id = h.requester_id WHERE h.conversation_type = "staff" AND h.conversation_id = ? ORDER BY h.created_at DESC');
    $historyRequestStmt->bind_param('i', $ticketId);
    $historyRequestStmt->execute();
    $historyRequests = $historyRequestStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $historyRequestStmt->close();
}
if ($canManageInbox) {
    if ($ticketId > 0) {
        $eligibleStmt = $conn->prepare('SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND (r.name IN ("admin", "teacher") OR (r.name = "student" AND EXISTS (SELECT 1 FROM staff_support_tickets st INNER JOIN students s ON s.id = st.related_student_id WHERE st.id = ? AND s.user_id = u.id)) OR (r.name = "class_monitor" AND EXISTS (SELECT 1 FROM staff_support_tickets st INNER JOIN class_monitors cm ON cm.class_id = st.class_id INNER JOIN students s ON s.id = cm.student_id WHERE st.id = ? AND s.user_id = u.id AND cm.status = "active"))) ORDER BY u.full_name');
        $eligibleStmt->bind_param('ii', $ticketId, $ticketId);
        $eligibleStmt->execute();
        $eligibleInvitees = $eligibleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $eligibleStmt->close();
    }
    if ($role === 'admin') {
        $assigneeOptions = $conn->query('SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND r.name IN ("admin", "teacher") ORDER BY r.name, u.full_name')->fetch_all(MYSQLI_ASSOC);
    }
}
$notificationsStmt = $conn->prepare('SELECT title, message, is_read, created_at FROM notifications WHERE user_id = ? AND (title LIKE "Support:%" OR title LIKE "Staff Support:%") ORDER BY created_at DESC LIMIT 15');
$notificationsStmt->bind_param('i', $userId);
$notificationsStmt->execute();
$supportNotifications = $notificationsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notificationsStmt->close();
$unreadSupportCount = count(array_filter($supportNotifications, static fn($notification) => !(int) $notification['is_read']));
$conn->close();
require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading"><div><p class="eyebrow"><?= e(translate('staff_support')) ?></p><h1><?= e(translate('staff_support')) ?></h1></div><div class="dashboard-date"><i class="bi bi-people"></i><?php if ($unreadSupportCount): ?> <span class="badge bg-danger"><?= $unreadSupportCount ?></span><?php endif; ?></div></div>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= e($errorMessage) ?></div><?php endif; ?><?php if ($successMessage): ?><div class="alert alert-success"><?= e($successMessage) ?></div><?php endif; ?>
<?php if ($ticket): ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h2 class="h4 mb-1"><?= e($ticket['title']) ?></h2><span class="text-muted"><?= e($ticket['ticket_number']) ?> · <?= e(translate('status_' . $ticket['status'])) ?></span></div><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/staff-support.php"><?= e(translate('staff_requests')) ?></a></div>
<div class="row g-3"><div class="col-lg-8"><section class="card-panel mb-3"><div class="d-flex align-items-center gap-2"><strong><?= e($ticket['creator_name']) ?></strong><?php if ($ticket['category']): ?><span class="text-muted">· <?= e($ticket['category']) ?></span><?php endif; ?></div><?php if ($canViewOriginalRequest): ?><p class="mt-2 mb-0" style="white-space:pre-wrap"><?= e($ticket['description']) ?></p><?php else: ?><p class="mt-2 mb-0 text-muted"><?= e(translate('earlier_message_restricted')) ?></p><?php endif; ?></section>
<section class="card-panel"><h3 class="h5"><?= e(translate('conversation')) ?></h3><div class="support-thread"><?php foreach ($messages as $message): ?><article class="support-message <?= (int) $message['sender_id'] === $userId ? 'is-mine' : '' ?>"><div class="support-message-meta"><strong><?= e($message['sender_name']) ?></strong><span><?= e(date('d/m/Y H:i', strtotime($message['created_at']))) ?></span></div><div class="support-message-body"><?= nl2br(e($message['message_text'])) ?></div><div class="support-attachments"><?php foreach ($attachments as $attachment): if ((int) $attachment['message_id'] !== (int) $message['id']) continue; $url = BASE_URL . '/api/support-attachment.php?type=staff&id=' . (int) $attachment['id']; ?><?php if (str_starts_with($attachment['file_type'], 'image/')): ?><img class="support-attachment-image" src="<?= e($url) ?>" alt="<?= e($attachment['file_name']) ?>"><?php elseif (str_starts_with($attachment['file_type'], 'video/')): ?><video class="support-attachment-video" controls src="<?= e($url) ?>"></video><?php elseif (str_starts_with($attachment['file_type'], 'audio/')): ?><audio controls src="<?= e($url) ?>"></audio><?php else: ?><a href="<?= e($url) ?>"><?= e($attachment['file_name']) ?></a><?php endif; ?><?php endforeach; ?></div></article><?php endforeach; ?></div>
<?php if ($ticket['status'] !== 'closed'): ?><form method="post" enctype="multipart/form-data" class="mt-3"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="staff_reply"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><textarea class="form-control mb-2" name="message_text" rows="3" maxlength="10000" placeholder="<?= e(translate('write_reply')) ?>"></textarea><div class="d-flex gap-2"><input class="form-control" type="file" name="attachments[]" multiple accept="image/*,.pdf,.txt,.doc,.docx,video/*,audio/*"><button class="btn btn-primary" type="submit"><?= e(translate('send_reply')) ?></button></div></form><?php endif; ?></section></div>
<aside class="col-lg-4"><section class="card-panel mb-3"><h3 class="h6"><?= e(translate('conversation_participants')) ?></h3><?php foreach ($participants as $participant): ?><div class="d-flex justify-content-between small"><span><?= e($participant['full_name']) ?> · <?= e(translate($participant['role_name'])) ?></span><?php if ($role === 'admin' && $participant['added_by'] !== null): ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="staff"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="remove_participant"><input type="hidden" name="participant_id" value="<?= (int) $participant['user_id'] ?>"><button class="btn btn-sm btn-outline-danger"><?= e(translate('remove')) ?></button></form><?php endif; ?></div><?php endforeach; ?></section><?php if (in_array($role, ['admin', 'teacher'], true)): ?><section class="card-panel mb-3"><h3 class="h6"><?= e(translate('change_status')) ?></h3><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="staff_status"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><select name="status" class="form-select mb-2"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= e(translate('status_' . $status)) ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="status_note" placeholder="<?= e(translate('status_note_optional')) ?>"><button class="btn btn-outline-primary w-100"><?= e(translate('save_status')) ?></button></form></section><?php endif; ?><?php if ($role === 'admin'): ?><section class="card-panel mb-3"><h3 class="h6"><?= e(translate('assign_request')) ?></h3><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="staff_assign"><input type="hidden" name="ticket_id" value="<?= $ticketId ?>"><select class="form-select mb-2" name="assigned_to" required><?php foreach ($assigneeOptions as $assignee): ?><option value="<?= (int) $assignee['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $assignee['id'] ? 'selected' : '' ?>><?= e($assignee['full_name']) ?> · <?= e(translate($assignee['role_name'])) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary w-100"><?= e(translate('assign')) ?></button></form></section><?php endif; ?><section class="card-panel"><h3 class="h6"><?= e(translate('status_history')) ?></h3><?php foreach ($history as $entry): ?><div class="small mb-2"><?= e(translate('status_' . $entry['status'])) ?> · <?= e($entry['changed_by_name']) ?> · <?= e(date('d/m/Y H:i', strtotime($entry['changed_at']))) ?></div><?php endforeach; ?></section></aside></div>
<?php if (in_array($role, ['admin', 'teacher'], true)): ?><section class="card-panel mt-3"><h3 class="h6"><?= e(translate('invite_person')) ?></h3><form method="post" action="<?= BASE_URL ?>/support-actions.php" class="row g-2"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="staff"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="send_invitation"><div class="col-md-4"><input class="form-control" type="search" id="staffInviteSearch" placeholder="<?= e(translate('search_people')) ?>"></div><div class="col-md-4"><select class="form-select" id="staffInviteRecipient" name="recipient_id" required><option value=""><?= e(translate('select_person')) ?></option><?php foreach ($eligibleInvitees as $eligible): ?><option value="<?= (int) $eligible['id'] ?>"><?= e($eligible['full_name']) ?> · <?= e(translate($eligible['role_name'])) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><select class="form-select" name="invitation_type"><option value="normal"><?= e(translate('normal_invitation')) ?></option><?php if ($role === 'admin'): ?><option value="mandatory"><?= e(translate('mandatory_invitation')) ?></option><?php endif; ?></select></div><div class="col-12"><input class="form-control" name="reason" maxlength="1000" placeholder="<?= e(translate('invitation_reason')) ?>" required></div><div class="col-12"><button class="btn btn-outline-primary"><?= e(translate('send_invitation')) ?></button></div></form></section><?php endif; ?>
<section class="card-panel mt-3"><h3 class="h6"><?= e(translate('support_invitations')) ?></h3><?php foreach ($conversationInvitations as $invite): ?><div class="border-top py-2 small"><strong><?= e($invite['recipient_name']) ?></strong> · <?= e(translate($invite['invitation_type'] . '_invitation')) ?> · <?= e(translate('invitation_' . $invite['status'])) ?><div class="text-muted"><?= e($invite['reason']) ?> · <?= e($invite['inviter_name']) ?></div><div class="d-flex gap-2 mt-1"><?php if ((int) $invite['recipient_id'] === $userId && $invite['status'] === 'pending'): ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="staff"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="invitation_id" value="<?= (int) $invite['id'] ?>"><input type="hidden" name="action" value="accept_invitation"><button class="btn btn-sm btn-outline-primary"><?= e(translate('accept')) ?></button></form><?php if ($invite['invitation_type'] !== 'mandatory'): ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="staff"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="invitation_id" value="<?= (int) $invite['id'] ?>"><input type="hidden" name="action" value="decline_invitation"><button class="btn btn-sm btn-outline-secondary"><?= e(translate('decline')) ?></button></form><?php endif; ?><?php elseif (($role === 'admin' || (int) $invite['invited_by'] === $userId) && $invite['status'] === 'pending'): ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="staff"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="invitation_id" value="<?= (int) $invite['id'] ?>"><input type="hidden" name="action" value="revoke_invitation"><button class="btn btn-sm btn-outline-danger"><?= e(translate('revoke')) ?></button></form><?php endif; ?></div></div><?php endforeach; ?></section>
<section class="card-panel mt-3"><h3 class="h6"><?= e(translate('request_earlier_messages')) ?></h3><form method="post" action="<?= BASE_URL ?>/support-actions.php" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="staff"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="request_history"><input class="form-control" name="reason" maxlength="1000" placeholder="<?= e(translate('history_request_reason')) ?>" required><button class="btn btn-outline-secondary"><?= e(translate('request')) ?></button></form></section>
<?php else: ?>
<div class="row g-3"><?php if ($role === 'teacher'): ?><div class="col-lg-5"><section class="card-panel"><h2 class="h5"><?= e(translate('new_staff_request')) ?></h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="create_staff_ticket"><label class="form-label"><?= e(translate('title')) ?></label><input class="form-control mb-2" name="title" maxlength="255" required><label class="form-label"><?= e(translate('category')) ?></label><select class="form-select mb-2" name="category"><option value="academic">Academic support</option><option value="grades">Grades</option><option value="schedule">Schedule</option><option value="technical">Technical issue</option><option value="permission">Permission issue</option><option value="other">Other</option></select><label class="form-label"><?= e(translate('class')) ?></label><select class="form-select mb-2" id="staffRequestClass" name="class_id"><option value="0"><?= e(translate('select_class')) ?></option><?php foreach ($teacherClassOptions as $classOption): ?><option value="<?= (int) $classOption['id'] ?>"><?= e($classOption['name']) ?></option><?php endforeach; ?></select><label class="form-label"><?= e(translate('related_student_optional')) ?></label><select class="form-select mb-2" id="staffRequestStudent" name="related_student_id"><option value="0"><?= e(translate('no_student')) ?></option><?php foreach ($relatedStudentOptions as $studentOption): ?><option value="<?= (int) $studentOption['id'] ?>" data-class="<?= (int) $studentOption['class_id'] ?>"><?= e($studentOption['full_name']) ?></option><?php endforeach; ?></select><label class="form-label"><?= e(translate('detailed_description')) ?></label><textarea class="form-control mb-2" name="description" maxlength="10000" rows="4" required></textarea><input class="form-control mb-3" type="file" name="attachments[]" multiple accept="image/*,.pdf,.txt,.doc,.docx,video/*,audio/*"><button class="btn btn-primary w-100"><?= e(translate('send_request')) ?></button></form></section></div><?php endif; ?><div class="<?= $role === 'teacher' ? 'col-lg-7' : 'col-12' ?>"><section class="card-panel"><h2 class="h5"><?= e(translate('staff_requests')) ?></h2><form method="get" class="d-flex gap-2 mb-3"><input class="form-control" name="q" value="<?= e($filter) ?>" placeholder="<?= e(translate('search_ticket_student_recipient')) ?>"><button class="btn btn-outline-primary"><i class="bi bi-search"></i></button></form><?php if (!$list): ?><p class="text-muted"><?= e(translate('no_support_requests')) ?></p><?php else: ?><div class="table-responsive"><table class="table"><thead><tr><th><?= e(translate('ticket_number')) ?></th><th><?= e(translate('title')) ?></th><th><?= e(translate('created_by')) ?></th><th><?= e(translate('last_message')) ?></th><th><?= e(translate('status')) ?></th></tr></thead><tbody><?php foreach ($list as $row): ?><tr><td><a href="?ticket=<?= (int) $row['id'] ?>"><?= e($row['ticket_number']) ?></a></td><td><?= e($row['title']) ?></td><td><?= e($row['creator_name']) ?></td><td><?= e(mb_substr($row['last_message'] ?? '', 0, 60, 'UTF-8')) ?><?php if ((int) $row['unread_count']): ?> <span class="badge bg-danger"><?= (int) $row['unread_count'] ?></span><?php endif; ?></td><td><?= e(translate('status_' . $row['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div></div>
<?php endif; ?>
<section class="card-panel mt-3"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0"><?= e(translate('support_notification_history')) ?></h2><a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/support.php"><?= e(translate('student_support')) ?></a></div><?php if (!$supportNotifications): ?><p class="text-muted small mb-0">-</p><?php else: ?><ul class="list-unstyled mb-0"><?php foreach ($supportNotifications as $notification): ?><li class="border-top py-2 <?= !(int) $notification['is_read'] ? 'fw-semibold' : '' ?>"><?= e($notification['title']) ?><div class="small text-muted"><?= e($notification['message']) ?> · <?= e(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></div></li><?php endforeach; ?></ul><?php endif; ?></section>
<script>
const inviteSearch = document.getElementById('staffInviteSearch');
const inviteSelect = document.getElementById('staffInviteRecipient');
if (inviteSearch && inviteSelect) inviteSearch.addEventListener('input', () => { const query = inviteSearch.value.toLocaleLowerCase(); for (const option of inviteSelect.options) if (option.value) option.hidden = !option.text.toLocaleLowerCase().includes(query); });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

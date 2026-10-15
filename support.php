<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support.php';

requireAuth();
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$role = (string) ($user['role_name'] ?? '');
if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
    redirect(BASE_URL . '/dashboard.php');
}

$conn = getDbConnection();
$csrfToken = supportCsrfToken();
$errorMessage = '';
$successMessage = (string) ($_SESSION['support_flash'] ?? '');
$errorMessage = (string) ($_SESSION['support_error'] ?? '');
unset($_SESSION['support_flash']);
unset($_SESSION['support_error']);
$studentData = null;
$studentId = 0;
if ($role === 'student') {
    $studentStmt = $conn->prepare('SELECT id, class_id, section_id FROM students WHERE user_id = ? AND status = "active" LIMIT 1');
    $studentStmt->bind_param('i', $userId);
    $studentStmt->execute();
    $studentData = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();
    $studentId = (int) ($studentData['id'] ?? 0);
}

$ticketTypes = ['question', 'problem', 'complaint'];
$ticketStatuses = ['new', 'received', 'reviewing', 'waiting_student', 'waiting_teacher', 'in_progress', 'resolved', 'closed'];
$problemCategories = [translate('category_classroom_problem'), translate('category_teacher_problem'), translate('category_student_problem'), translate('category_homework_problem'), translate('category_quiz_problem'), translate('category_grade_problem'), translate('category_attendance_problem'), translate('category_technical_problem'), translate('category_website_problem'), translate('category_other')];
$complaintCategories = [translate('category_academic'), translate('category_conduct'), translate('category_bullying'), translate('category_safety'), translate('category_facilities'), translate('category_other')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!supportVerifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create_ticket' && $role === 'student' && $studentData) {
                $requestType = (string) ($_POST['request_type'] ?? '');
                $recipientRole = (string) ($_POST['recipient_role'] ?? '');
                $recipientField = $recipientRole === 'teacher' ? 'teacher_user_id' : 'admin_user_id';
                $recipientId = (int) ($_POST[$recipientField] ?? 0);
                $subjectId = (int) ($_POST['subject_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $category = trim((string) ($_POST['category'] ?? ''));
                $subjectUserId = (int) ($_POST['subject_user_id'] ?? 0);
                $allowedCategories = $requestType === 'problem' ? $problemCategories : ($requestType === 'complaint' ? $complaintCategories : []);
                $classId = (int) $studentData['class_id'];
                $sectionId = (int) ($studentData['section_id'] ?? 0);

                if (!in_array($requestType, $ticketTypes, true) || !in_array($recipientRole, ['teacher', 'admin'], true) || $recipientId < 1 || $title === '' || mb_strlen($title) > 255 || $description === '' || mb_strlen($description) > 10000 || ($allowedCategories && !in_array($category, $allowedCategories, true))) {
                    throw new InvalidArgumentException('Complete the required request details and select a valid recipient.');
                }

                if ($recipientRole === 'teacher') {
                    $recipientCheck = $conn->prepare('SELECT u.id FROM users u INNER JOIN teachers t ON t.user_id = u.id INNER JOIN teacher_subject_class tsc ON tsc.teacher_id = t.id INNER JOIN curriculum_subjects cs ON cs.id = tsc.curriculum_subject_id WHERE u.id = ? AND u.status = "active" AND t.status = "active" AND tsc.class_id = ? AND (tsc.section_id IS NULL OR tsc.section_id = ?) AND (? = 0 OR cs.subject_id = ?) LIMIT 1');
                    $recipientCheck->bind_param('iiiii', $recipientId, $classId, $sectionId, $subjectId, $subjectId);
                } else {
                    $recipientCheck = $conn->prepare('SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active" AND r.name = "admin" LIMIT 1');
                    $recipientCheck->bind_param('i', $recipientId);
                }
                $recipientCheck->execute();
                $validRecipient = (bool) $recipientCheck->get_result()->fetch_assoc();
                $recipientCheck->close();
                if (!$validRecipient) {
                    throw new InvalidArgumentException('That recipient is not available for your class or selected subject.');
                }
                if ($requestType === 'complaint' && $recipientRole !== 'admin') {
                    throw new InvalidArgumentException('Complaints must be sent to an administrator.');
                }

                if ($subjectUserId > 0) {
                    $subjectUserCheck = $conn->prepare('SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active" AND r.name IN ("teacher", "admin", "class_monitor") LIMIT 1');
                    $subjectUserCheck->bind_param('i', $subjectUserId);
                    $subjectUserCheck->execute();
                    $subjectUserExists = (bool) $subjectUserCheck->get_result()->fetch_assoc();
                    $subjectUserCheck->close();
                    if ($requestType !== 'complaint' || !$subjectUserExists || $recipientRole !== 'admin' || $recipientId === $subjectUserId || $subjectUserId === $userId) {
                        throw new InvalidArgumentException('Sensitive complaints must go to an administrator who is not the person involved.');
                    }
                }

                if ($subjectId > 0) {
                    $subjectCheck = $conn->prepare('SELECT id FROM teacher_subject_class tsc INNER JOIN curriculum_subjects cs ON cs.id = tsc.curriculum_subject_id WHERE tsc.class_id = ? AND (tsc.section_id IS NULL OR tsc.section_id = ?) AND cs.subject_id = ? LIMIT 1');
                    $subjectCheck->bind_param('iii', $classId, $sectionId, $subjectId);
                    $subjectCheck->execute();
                    $subjectExists = (bool) $subjectCheck->get_result()->fetch_assoc();
                    $subjectCheck->close();
                    if (!$subjectExists) {
                        throw new InvalidArgumentException('Select a subject assigned to your class.');
                    }
                } else {
                    $subjectId = 0;
                }

                $temporaryTicketNumber = 'TMP-' . bin2hex(random_bytes(12));
                $ticketNumber = '';
                $uploadedPaths = [];
                $conn->begin_transaction();
                try {
                    $ticketInsert = $conn->prepare('INSERT INTO support_tickets (ticket_number, student_id, recipient_user_id, recipient_role, subject_id, class_id, section_id, request_type, category, title, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "new")');
                    $subjectValue = $subjectId > 0 ? $subjectId : null;
                    $sectionValue = $sectionId > 0 ? $sectionId : null;
                    $categoryValue = $category !== '' ? $category : null;
                    $ticketInsert->bind_param('siisiiissss', $temporaryTicketNumber, $studentId, $recipientId, $recipientRole, $subjectValue, $classId, $sectionValue, $requestType, $categoryValue, $title, $description);
                    $ticketInsert->execute();
                    $ticketId = $ticketInsert->insert_id;
                    $ticketInsert->close();
                    $ticketNumber = 'MHS-' . date('Y') . '-' . str_pad((string) $ticketId, 6, '0', STR_PAD_LEFT);
                    $ticketUpdate = $conn->prepare('UPDATE support_tickets SET ticket_number = ? WHERE id = ?');
                    $ticketUpdate->bind_param('si', $ticketNumber, $ticketId);
                    $ticketUpdate->execute();
                    $ticketUpdate->close();

                    supportAddParticipant($conn, 'student', $ticketId, $userId, 'student');
                    supportAddParticipant($conn, 'student', $ticketId, $recipientId, $recipientRole);
                    if ($requestType === 'complaint') {
                        $sensitive = $conn->prepare('INSERT INTO support_sensitive_cases (ticket_id, subject_user_id, restricted) VALUES (?, NULLIF(?, 0), 1)');
                        $sensitive->bind_param('ii', $ticketId, $subjectUserId);
                        $sensitive->execute();
                        $sensitive->close();
                    }

                    $initialMessage = $conn->prepare('INSERT INTO support_messages (ticket_id, sender_id, message_text, is_read) VALUES (?, ?, ?, 0)');
                    $initialMessage->bind_param('iis', $ticketId, $userId, $description);
                    $initialMessage->execute();
                    $initialMessageId = $initialMessage->insert_id;
                    $initialMessage->close();
                    $uploadedPaths = supportStoreAttachments($conn, $ticketId, $initialMessageId, $_FILES['attachments'] ?? []);

                    $history = $conn->prepare('INSERT INTO support_status_history (ticket_id, status, changed_by, notes) VALUES (?, "new", ?, "Request created")');
                    $history->bind_param('ii', $ticketId, $userId);
                    $history->execute();
                    $history->close();
                    supportNotifyConversation($conn, 'student', $ticketId, $userId, 'New request', 'A new support request was submitted.');
                    $conn->commit();
                    $_SESSION['support_flash'] = 'Request created. Ticket ' . $ticketNumber . '.';
                    header('Location: ' . BASE_URL . '/support.php?ticket=' . $ticketId, true, 303);
                    exit;
                } catch (Throwable $exception) {
                    $conn->rollback();
                    foreach ($uploadedPaths as $uploadedPath) {
                        if (is_file($uploadedPath)) {
                            unlink($uploadedPath);
                        }
                    }
                    throw $exception;
                }
            } elseif ($action === 'send_reply') {
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                if (!supportCanAccessTicket($conn, $ticketId, $userId, $role)) {
                    throw new RuntimeException('You cannot access this support request.');
                }
                $ticketStatusStmt = $conn->prepare('SELECT status FROM support_tickets WHERE id = ?');
                $ticketStatusStmt->bind_param('i', $ticketId);
                $ticketStatusStmt->execute();
                $ticketStatus = $ticketStatusStmt->get_result()->fetch_assoc();
                $ticketStatusStmt->close();
                $replyText = trim((string) ($_POST['message_text'] ?? ''));
                $hasFiles = !empty($_FILES['attachments']['name'][0]);
                if ($ticketStatus['status'] === 'closed' || ($replyText === '' && !$hasFiles) || mb_strlen($replyText) > 10000) {
                    throw new InvalidArgumentException($ticketStatus['status'] === 'closed' ? 'This request is closed. Reopen it before replying.' : 'Write a message or attach a file.');
                }
                $uploadedPaths = [];
                $conn->begin_transaction();
                try {
                    $reply = $conn->prepare('INSERT INTO support_messages (ticket_id, sender_id, message_text, is_read) VALUES (?, ?, ?, 0)');
                    $messageText = $replyText !== '' ? $replyText : 'Shared an attachment.';
                    $reply->bind_param('iis', $ticketId, $userId, $messageText);
                    $reply->execute();
                    $messageId = $reply->insert_id;
                    $reply->close();
                    $uploadedPaths = supportStoreAttachments($conn, $ticketId, $messageId, $_FILES['attachments'] ?? []);
                    $touch = $conn->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?');
                    $touch->bind_param('i', $ticketId);
                    $touch->execute();
                    $touch->close();
                    supportNotifyTicketParticipants($conn, $ticketId, $userId, 'New reply', 'A new message was added to the request.');
                    $conn->commit();
                    $_SESSION['support_flash'] = 'Reply sent.';
                    header('Location: ' . BASE_URL . '/support.php?ticket=' . $ticketId, true, 303);
                    exit;
                } catch (Throwable $exception) {
                    $conn->rollback();
                    foreach ($uploadedPaths as $uploadedPath) {
                        if (is_file($uploadedPath)) {
                            unlink($uploadedPath);
                        }
                    }
                    throw $exception;
                }
            } elseif ($action === 'change_status' && in_array($role, ['teacher', 'admin'], true)) {
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                $newStatus = (string) ($_POST['status'] ?? '');
                if (!supportConversationOwnerAccess($conn, 'student', $ticketId, $userId, $role) || !in_array($newStatus, $ticketStatuses, true)) {
                    throw new RuntimeException('You cannot change the status of this request.');
                }
                $oldStatusStmt = $conn->prepare('SELECT status, ticket_number FROM support_tickets WHERE id = ?');
                $oldStatusStmt->bind_param('i', $ticketId);
                $oldStatusStmt->execute();
                $oldStatus = $oldStatusStmt->get_result()->fetch_assoc();
                $oldStatusStmt->close();
                if ($oldStatus && $oldStatus['status'] !== $newStatus) {
                    $note = trim((string) ($_POST['status_note'] ?? ''));
                    $conn->begin_transaction();
                    $statusUpdate = $conn->prepare('UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?');
                    $statusUpdate->bind_param('si', $newStatus, $ticketId);
                    $statusUpdate->execute();
                    $statusUpdate->close();
                    $history = $conn->prepare('INSERT INTO support_status_history (ticket_id, status, changed_by, notes) VALUES (?, ?, ?, ?)');
                    $history->bind_param('isis', $ticketId, $newStatus, $userId, $note);
                    $history->execute();
                    $history->close();
                    supportNotifyTicketParticipants($conn, $ticketId, $userId, 'Status changed', 'Request status changed to ' . str_replace('_', ' ', $newStatus) . '.');
                    $conn->commit();
                    $_SESSION['support_flash'] = 'Request status updated.';
                    header('Location: ' . BASE_URL . '/support.php?ticket=' . $ticketId, true, 303);
                    exit;
                }
            } elseif ($action === 'assign_ticket' && $role === 'admin') {
                $ticketId = (int) ($_POST['ticket_id'] ?? 0);
                $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
                if (!supportCanAccessTicket($conn, $ticketId, $userId, $role)) {
                    throw new RuntimeException('Support request not found.');
                }
                $assigneeCheck = $conn->prepare('SELECT u.id, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active" AND r.name IN ("teacher", "admin") LIMIT 1');
                $assigneeCheck->bind_param('i', $assignedTo);
                $assigneeCheck->execute();
                $assignee = $assigneeCheck->get_result()->fetch_assoc();
                $assigneeCheck->close();
                if (!$assignee) {
                    throw new InvalidArgumentException('Choose an active teacher or admin.');
                }
                $ticketTypeStmt = $conn->prepare('SELECT request_type FROM support_tickets WHERE id = ?');
                $ticketTypeStmt->bind_param('i', $ticketId);
                $ticketTypeStmt->execute();
                $ticketRequestType = (string) ($ticketTypeStmt->get_result()->fetch_assoc()['request_type'] ?? '');
                $ticketTypeStmt->close();
                if ($ticketRequestType === 'complaint' && $assignee['role_name'] !== 'admin') {
                    throw new InvalidArgumentException('Complaints must remain assigned to authorized administrators.');
                }
                $assignment = $conn->prepare('INSERT INTO support_assignments (ticket_id, assigned_to, assigned_by) VALUES (?, ?, ?)');
                $assignment->bind_param('iii', $ticketId, $assignedTo, $userId);
                $assignment->execute();
                $assignment->close();
                supportAddParticipant($conn, 'student', $ticketId, $assignedTo, (string) $assignee['role_name'], $userId);
                supportAudit($conn, 'student', $ticketId, $userId, 'assigned', 'Support request reassigned.', $assignedTo);
                supportCreateNotification($conn, $assignedTo, 'Support: Request assigned', supportConversationTicketNumber($conn, 'student', $ticketId) . ': A support request was assigned to you.');
                supportNotifyConversation($conn, 'student', $ticketId, $userId, 'Request assigned', 'A support request was assigned to a staff member.');
                $_SESSION['support_flash'] = 'Request assigned.';
                header('Location: ' . BASE_URL . '/support.php?ticket=' . $ticketId, true, 303);
                exit;
            } elseif ($action === 'mark_notifications_read') {
                $mark = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND (title LIKE "Support:%" OR title LIKE "Staff Support:%")');
                $mark->bind_param('i', $userId);
                $mark->execute();
                $mark->close();
                $_SESSION['support_flash'] = 'Support notifications marked as read.';
                header('Location: ' . BASE_URL . '/support.php', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            $errorMessage = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The request could not be saved. Please try again.';
        }
    }
}

$ticketId = (int) ($_GET['ticket'] ?? 0);
$ticket = null;
$messages = [];
$statusHistory = [];
$assignmentHistory = [];
$ticketAttachments = [];
$participants = [];
$conversationInvitations = [];
$historyRequests = [];
$eligibleInvitees = [];
$canViewOriginalRequest = false;
if ($ticketId > 0 && supportCanAccessTicket($conn, $ticketId, $userId, $role)) {
    $memberStmt = $conn->prepare('SELECT id FROM support_conversation_participants WHERE conversation_type = "student" AND conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1');
    $memberStmt->bind_param('ii', $ticketId, $userId);
    $memberStmt->execute();
    $alreadyMember = (bool) $memberStmt->get_result()->fetch_assoc();
    $memberStmt->close();
    if (!$alreadyMember && supportConversationOwnerAccess($conn, 'student', $ticketId, $userId, $role)) {
        supportAddParticipant($conn, 'student', $ticketId, $userId, $role);
        if ($role === 'admin') {
            supportAudit($conn, 'student', $ticketId, $userId, 'admin_access', 'Admin opened support conversation.');
        }
    }
    $visibleFrom = supportVisibleFrom($conn, 'student', $ticketId, $userId);
    $visibleFloor = supportVisibleMessageFloor($conn, 'student', $ticketId, $userId);
    $markRead = $conn->prepare('UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_id <> ? AND id > ?');
    $markRead->bind_param('iii', $ticketId, $userId, $visibleFloor);
    $markRead->execute();
    $markRead->close();

    $ticketStmt = $conn->prepare('SELECT t.*, st.full_name AS student_name, st.user_id AS student_user_id, ru.full_name AS recipient_name, ru.photo AS recipient_photo, rr.name AS recipient_role_name, s.name_en AS subject_name, c.name AS class_name, sec.name AS section_name, (SELECT u.full_name FROM support_assignments sa INNER JOIN users u ON u.id = sa.assigned_to WHERE sa.ticket_id = t.id ORDER BY sa.id DESC LIMIT 1) AS assigned_name, (SELECT sa.assigned_to FROM support_assignments sa WHERE sa.ticket_id = t.id ORDER BY sa.id DESC LIMIT 1) AS assigned_to FROM support_tickets t INNER JOIN students st ON st.id = t.student_id INNER JOIN users ru ON ru.id = t.recipient_user_id INNER JOIN roles rr ON rr.id = ru.role_id LEFT JOIN subjects s ON s.id = t.subject_id LEFT JOIN classes c ON c.id = t.class_id LEFT JOIN sections sec ON sec.id = t.section_id WHERE t.id = ? LIMIT 1');
    $ticketStmt->bind_param('i', $ticketId);
    $ticketStmt->execute();
    $ticket = $ticketStmt->get_result()->fetch_assoc();
    $ticketStmt->close();

    if ($ticket) {
        $originalMessageStmt = $conn->prepare('SELECT id FROM support_messages WHERE ticket_id = ? ORDER BY id ASC LIMIT 1');
        $originalMessageStmt->bind_param('i', $ticketId);
        $originalMessageStmt->execute();
        $originalMessageId = (int) ($originalMessageStmt->get_result()->fetch_assoc()['id'] ?? 0);
        $originalMessageStmt->close();
        $canViewOriginalRequest = $originalMessageId > 0 && supportCanViewMessage($conn, 'student', $ticketId, $userId, $originalMessageId);
        $messageStmt = $conn->prepare('SELECT m.*, u.full_name AS sender_name, u.photo AS sender_photo FROM support_messages m INNER JOIN users u ON u.id = m.sender_id WHERE m.ticket_id = ? AND (m.id > ? OR EXISTS (SELECT 1 FROM support_history_requests h WHERE h.conversation_type = "student" AND h.conversation_id = ? AND h.requester_id = ? AND h.status = "approved" AND (h.scope_type = "all" OR (h.scope_type = "date_range" AND m.created_at BETWEEN h.scope_start AND h.scope_end) OR (h.scope_type = "message_range" AND m.id BETWEEN h.start_message_id AND h.end_message_id)))) ORDER BY m.created_at ASC, m.id ASC');
        $messageStmt->bind_param('iiii', $ticketId, $visibleFloor, $ticketId, $userId);
        $messageStmt->execute();
        $messages = $messageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $messageStmt->close();
        $visibleMessageIds = array_map(static fn($message) => (int) $message['id'], $messages);
        if ($visibleMessageIds) {
            $attachmentPlaceholders = implode(',', array_fill(0, count($visibleMessageIds), '?'));
            $attachmentStmt = $conn->prepare('SELECT * FROM support_attachments WHERE ticket_id = ? AND message_id IN (' . $attachmentPlaceholders . ') ORDER BY id');
            $attachmentValues = array_merge([$ticketId], $visibleMessageIds);
            supportBindParams($attachmentStmt, 'i' . str_repeat('i', count($visibleMessageIds)), $attachmentValues);
            $attachmentStmt->execute();
            $ticketAttachments = $attachmentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $attachmentStmt->close();
        }
        $historyStmt = $conn->prepare('SELECT h.*, u.full_name AS changed_by_name FROM support_status_history h INNER JOIN users u ON u.id = h.changed_by WHERE h.ticket_id = ? ORDER BY h.changed_at DESC');
        $historyStmt->bind_param('i', $ticketId);
        $historyStmt->execute();
        $statusHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $historyStmt->close();
        if ($role === 'admin') {
            $assignmentStmt = $conn->prepare('SELECT sa.*, assigned.full_name AS assigned_name, actor.full_name AS assigned_by_name FROM support_assignments sa INNER JOIN users assigned ON assigned.id = sa.assigned_to INNER JOIN users actor ON actor.id = sa.assigned_by WHERE sa.ticket_id = ? ORDER BY sa.assigned_at DESC');
            $assignmentStmt->bind_param('i', $ticketId);
            $assignmentStmt->execute();
            $assignmentHistory = $assignmentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $assignmentStmt->close();
        }
        $participantStmt = $conn->prepare('SELECT p.*, u.full_name, r.name AS role_name FROM support_conversation_participants p INNER JOIN users u ON u.id = p.user_id INNER JOIN roles r ON r.id = u.role_id WHERE p.conversation_type = "student" AND p.conversation_id = ? AND p.left_at IS NULL ORDER BY p.joined_at');
        $participantStmt->bind_param('i', $ticketId);
        $participantStmt->execute();
        $participants = $participantStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $participantStmt->close();
        $inviteStmt = $conn->prepare('SELECT i.*, u.full_name AS recipient_name, inviter.full_name AS inviter_name FROM support_invitations i INNER JOIN users u ON u.id = i.recipient_id INNER JOIN users inviter ON inviter.id = i.invited_by WHERE i.conversation_type = "student" AND i.conversation_id = ? ORDER BY i.created_at DESC');
        $inviteStmt->bind_param('i', $ticketId);
        $inviteStmt->execute();
        $conversationInvitations = $inviteStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $inviteStmt->close();
        $historyRequestStmt = $conn->prepare('SELECT h.*, u.full_name AS requester_name FROM support_history_requests h INNER JOIN users u ON u.id = h.requester_id WHERE h.conversation_type = "student" AND h.conversation_id = ? ORDER BY h.created_at DESC');
        $historyRequestStmt->bind_param('i', $ticketId);
        $historyRequestStmt->execute();
        $historyRequests = $historyRequestStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $historyRequestStmt->close();
        $eligibleStmt = $conn->prepare('SELECT DISTINCT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND (r.name = "admin" OR (r.name = "teacher" AND EXISTS (SELECT 1 FROM teachers t INNER JOIN teacher_subject_class tsc ON tsc.teacher_id = t.id INNER JOIN curriculum_subjects cs ON cs.id = tsc.curriculum_subject_id WHERE t.user_id = u.id AND tsc.class_id = ? AND (tsc.section_id IS NULL OR tsc.section_id = ?) AND ((SELECT subject_id FROM support_tickets WHERE id = ?) = 0 OR cs.subject_id = (SELECT subject_id FROM support_tickets WHERE id = ?))))) ORDER BY u.full_name');
        $eligibleStmt->bind_param('iiii', $ticket['class_id'], $ticket['section_id'], $ticketId, $ticketId);
        $eligibleStmt->execute();
        $eligibleInvitees = $eligibleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $eligibleStmt->close();
        if ($role === 'admin') {
            $sensitiveAccess = $conn->prepare('SELECT ticket_id FROM support_sensitive_cases WHERE ticket_id = ? AND restricted = 1');
            $sensitiveAccess->bind_param('i', $ticketId);
            $sensitiveAccess->execute();
            if ($sensitiveAccess->get_result()->fetch_assoc()) {
                supportAudit($conn, 'student', $ticketId, $userId, 'sensitive_admin_access', 'Admin reviewed sensitive support conversation.');
            }
            $sensitiveAccess->close();
        }
    }
}

$subjectOptions = [];
$teacherOptions = [];
$adminOptions = [];
$complaintSubjectOptions = [];
$assigneeOptions = [];
$classFilterOptions = [];
$subjectFilterOptions = [];
if ($role === 'student' && $studentData) {
    $subjectStmt = $conn->prepare('SELECT DISTINCT s.id, s.name_en FROM teacher_subject_class tsc INNER JOIN curriculum_subjects cs ON cs.id = tsc.curriculum_subject_id INNER JOIN subjects s ON s.id = cs.subject_id WHERE tsc.class_id = ? AND (tsc.section_id IS NULL OR tsc.section_id = ?) AND s.status = "active" ORDER BY s.name_en');
    $classId = (int) $studentData['class_id'];
    $sectionId = (int) ($studentData['section_id'] ?? 0);
    $subjectStmt->bind_param('ii', $classId, $sectionId);
    $subjectStmt->execute();
    $subjectOptions = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subjectStmt->close();

    $teacherStmt = $conn->prepare('SELECT u.id, u.full_name, u.photo, GROUP_CONCAT(DISTINCT s.name_en ORDER BY s.name_en SEPARATOR ", ") AS subjects, GROUP_CONCAT(DISTINCT s.id ORDER BY s.id SEPARATOR "|") AS subject_ids FROM users u INNER JOIN teachers t ON t.user_id = u.id INNER JOIN teacher_subject_class tsc ON tsc.teacher_id = t.id INNER JOIN curriculum_subjects cs ON cs.id = tsc.curriculum_subject_id INNER JOIN subjects s ON s.id = cs.subject_id WHERE u.status = "active" AND t.status = "active" AND tsc.class_id = ? AND (tsc.section_id IS NULL OR tsc.section_id = ?) GROUP BY u.id, u.full_name, u.photo ORDER BY u.full_name');
    $teacherStmt->bind_param('ii', $classId, $sectionId);
    $teacherStmt->execute();
    $teacherOptions = $teacherStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $teacherStmt->close();
    $adminOptions = $conn->query('SELECT u.id, u.full_name, u.photo FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND r.name = "admin" ORDER BY u.full_name')->fetch_all(MYSQLI_ASSOC);
    $complaintSubjectOptions = $conn->query('SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND r.name IN ("teacher", "admin", "class_monitor") ORDER BY u.full_name')->fetch_all(MYSQLI_ASSOC);
    $subjectFilterOptions = $subjectOptions;
}
if ($role === 'admin') {
    $assigneeOptions = $conn->query('SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND r.name IN ("admin", "teacher") ORDER BY r.name, u.full_name')->fetch_all(MYSQLI_ASSOC);
    $classFilterOptions = $conn->query('SELECT id, name FROM classes ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $subjectFilterOptions = $conn->query('SELECT id, name_en FROM subjects WHERE status = "active" ORDER BY name_en')->fetch_all(MYSQLI_ASSOC);
} elseif ($role === 'teacher') {
    $classFilterStmt = $conn->prepare('SELECT DISTINCT c.id, c.name FROM teachers t INNER JOIN teacher_subject_class tsc ON tsc.teacher_id = t.id INNER JOIN classes c ON c.id = tsc.class_id WHERE t.user_id = ? ORDER BY c.name');
    $classFilterStmt->bind_param('i', $userId);
    $classFilterStmt->execute();
    $classFilterOptions = $classFilterStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $classFilterStmt->close();
    $subjectFilterStmt = $conn->prepare('SELECT DISTINCT s.id, s.name_en FROM teachers t INNER JOIN teacher_subject_class tsc ON tsc.teacher_id = t.id INNER JOIN curriculum_subjects cs ON cs.id = tsc.curriculum_subject_id INNER JOIN subjects s ON s.id = cs.subject_id WHERE t.user_id = ? ORDER BY s.name_en');
    $subjectFilterStmt->bind_param('i', $userId);
    $subjectFilterStmt->execute();
    $subjectFilterOptions = $subjectFilterStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subjectFilterStmt->close();
}

$filterType = in_array($_GET['type'] ?? '', $ticketTypes, true) ? (string) $_GET['type'] : '';
$filterStatus = in_array($_GET['status'] ?? '', $ticketStatuses, true) ? (string) $_GET['status'] : '';
$searchText = trim((string) ($_GET['q'] ?? ''));
$filterSubject = max(0, (int) ($_GET['subject_id'] ?? 0));
$filterClass = max(0, (int) ($_GET['class_id'] ?? 0));
$filterRecipient = $role === 'admin' ? max(0, (int) ($_GET['recipient_user_id'] ?? 0)) : 0;
$allCategories = array_values(array_unique(array_merge($problemCategories, $complaintCategories)));
$filterCategory = in_array($_GET['category'] ?? '', $allCategories, true) ? (string) $_GET['category'] : '';
$supportStats = ['new_count' => 0, 'open_questions' => 0, 'complaints' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
if ($role === 'admin') {
    $statsRow = $conn->query('SELECT SUM(status = "new") AS new_count, SUM(request_type = "question" AND status NOT IN ("resolved", "closed")) AS open_questions, SUM(request_type = "complaint") AS complaints, SUM(status = "in_progress") AS in_progress, SUM(status = "resolved") AS resolved, SUM(status = "closed") AS closed FROM support_tickets')->fetch_assoc();
    foreach ($supportStats as $statKey => $_) {
        $supportStats[$statKey] = (int) ($statsRow[$statKey] ?? 0);
    }
}
$where = ['NOT EXISTS (SELECT 1 FROM support_sensitive_cases sc WHERE sc.ticket_id = t.id AND sc.restricted = 1 AND sc.subject_user_id = ?)'];
$bindTypes = 'i';
$bindValues = [$userId];
if ($role === 'student') {
    $where[] = 't.student_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = $studentId;
} elseif ($role === 'teacher') {
    $where[] = 't.request_type <> "complaint" AND (t.recipient_user_id = ? OR EXISTS (SELECT 1 FROM support_assignments sa WHERE sa.ticket_id = t.id AND sa.assigned_to = ? AND sa.id = (SELECT MAX(sa2.id) FROM support_assignments sa2 WHERE sa2.ticket_id = t.id)) OR EXISTS (SELECT 1 FROM support_conversation_participants p WHERE p.conversation_type = "student" AND p.conversation_id = t.id AND p.user_id = ? AND p.left_at IS NULL))';
    $bindTypes .= 'iii';
    $bindValues[] = $userId;
    $bindValues[] = $userId;
    $bindValues[] = $userId;
} else {
    $where[] = '1 = 1';
}
if ($filterType !== '') {
    $where[] = 't.request_type = ?';
    $bindTypes .= 's';
    $bindValues[] = $filterType;
}
if ($filterStatus !== '') {
    $where[] = 't.status = ?';
    $bindTypes .= 's';
    $bindValues[] = $filterStatus;
}
if ($searchText !== '') {
    $where[] = '(t.ticket_number LIKE ? OR t.title LIKE ? OR t.category LIKE ? OR st.full_name LIKE ? OR ru.full_name LIKE ? OR EXISTS (SELECT 1 FROM support_assignments sa INNER JOIN users assigned ON assigned.id = sa.assigned_to WHERE sa.ticket_id = t.id AND assigned.full_name LIKE ?))';
    $like = '%' . $searchText . '%';
    $bindTypes .= 'ssssss';
    array_push($bindValues, $like, $like, $like, $like, $like, $like);
}
if ($filterSubject > 0) {
    $where[] = 't.subject_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = $filterSubject;
}
if ($filterCategory !== '') {
    $where[] = 't.category = ?';
    $bindTypes .= 's';
    $bindValues[] = $filterCategory;
}
if ($filterClass > 0 && in_array($role, ['teacher', 'admin'], true)) {
    $where[] = 't.class_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = $filterClass;
}
if ($filterRecipient > 0 && $role === 'admin') {
    $where[] = 't.recipient_user_id = ?';
    $bindTypes .= 'i';
    $bindValues[] = $filterRecipient;
}
$listSql = 'SELECT t.*, st.full_name AS student_name, ru.full_name AS recipient_name, rr.name AS recipient_role_name, s.name_en AS subject_name, c.name AS class_name, (SELECT m.message_text FROM support_messages m WHERE m.ticket_id = t.id AND m.id > COALESCE((SELECT p.history_floor_id FROM support_conversation_participants p WHERE p.conversation_type = "student" AND p.conversation_id = t.id AND p.user_id = ? AND p.left_at IS NULL LIMIT 1), 0) ORDER BY m.id DESC LIMIT 1) AS last_message, (SELECT COUNT(*) FROM support_messages um WHERE um.ticket_id = t.id AND um.sender_id <> ? AND um.is_read = 0 AND um.id > COALESCE((SELECT p.history_floor_id FROM support_conversation_participants p WHERE p.conversation_type = "student" AND p.conversation_id = t.id AND p.user_id = ? AND p.left_at IS NULL LIMIT 1), 0)) AS unread_count FROM support_tickets t INNER JOIN students st ON st.id = t.student_id INNER JOIN users ru ON ru.id = t.recipient_user_id INNER JOIN roles rr ON rr.id = ru.role_id LEFT JOIN subjects s ON s.id = t.subject_id LEFT JOIN classes c ON c.id = t.class_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.updated_at DESC LIMIT 200';
$listStmt = $conn->prepare($listSql);
$bindValues = array_merge([$userId, $userId, $userId], $bindValues);
$bindTypes = 'iii' . $bindTypes;
supportBindParams($listStmt, $bindTypes, $bindValues);
$listStmt->execute();
$tickets = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

$unreadSupportCount = 0;
$notificationStmt = $conn->prepare('SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? AND (title LIKE "Support:%" OR title LIKE "Staff Support:%") ORDER BY created_at DESC LIMIT 15');
$notificationStmt->bind_param('i', $userId);
$notificationStmt->execute();
$supportNotifications = $notificationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notificationStmt->close();
foreach ($supportNotifications as $notification) {
    if (!(int) $notification['is_read']) {
        $unreadSupportCount++;
    }
}
$conn->close();
require_once __DIR__ . '/includes/header.php';

$statusLabels = [
    'new' => translate('status_new'), 'received' => translate('status_received'), 'reviewing' => translate('status_reviewing'),
    'waiting_student' => translate('status_waiting_student'), 'waiting_teacher' => translate('status_waiting_teacher'),
    'in_progress' => translate('status_in_progress'), 'resolved' => translate('status_resolved'), 'closed' => translate('status_closed'),
];
$typeLabels = ['question' => translate('type_question'), 'problem' => translate('type_problem'), 'complaint' => translate('type_complaint')];
?>

<div class="dashboard-heading">
    <div>
        <p class="eyebrow"><?= e(translate($role)) ?></p>
        <h1><?= e(translate('student_support')) ?></h1>
    </div>
    <div class="dashboard-date"><i class="bi bi-life-preserver"></i> <?= e(translate('my_requests')) ?><?php if ($unreadSupportCount): ?> <span class="badge bg-danger"><?= $unreadSupportCount ?></span><?php endif; ?></div>
</div>

<?php if ($role === 'admin'): ?>
<div class="stats-grid">
    <div class="stat-card"><div class="label"><?= e(translate('status_new')) ?></div><div class="value"><?= $supportStats['new_count'] ?></div></div>
    <div class="stat-card"><div class="label"><?= e(translate('open_questions')) ?></div><div class="value"><?= $supportStats['open_questions'] ?></div></div>
    <div class="stat-card"><div class="label"><?= e(translate('type_complaint')) ?></div><div class="value"><?= $supportStats['complaints'] ?></div></div>
    <div class="stat-card"><div class="label"><?= e(translate('status_in_progress')) ?></div><div class="value"><?= $supportStats['in_progress'] ?></div></div>
    <div class="stat-card"><div class="label"><?= e(translate('status_resolved')) ?></div><div class="value"><?= $supportStats['resolved'] ?></div></div>
    <div class="stat-card"><div class="label"><?= e(translate('status_closed')) ?></div><div class="value"><?= $supportStats['closed'] ?></div></div>
</div>
<?php endif; ?>

<?php if ($errorMessage !== ''): ?><div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div><?php endif; ?>
<?php if ($successMessage !== ''): ?><div class="alert alert-success" role="status"><?= e($successMessage) ?></div><?php endif; ?>

<?php if ($ticket): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div><h2 class="h4 mb-1"><?= e($ticket['title']) ?></h2><span class="text-muted"><?= e($ticket['ticket_number']) ?> · <?= e($typeLabels[$ticket['request_type']] ?? $ticket['request_type']) ?></span></div>
    <a href="<?= BASE_URL ?>/support.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= e(translate('my_requests')) ?></a>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <section class="card-panel mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="d-flex gap-2 flex-wrap mb-2"><span class="badge text-bg-primary"><?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?></span><span class="badge text-bg-light"><?= e($ticket['recipient_role_name']) ?></span></div>
                    <div class="text-muted small"><?= e($ticket['student_name']) ?> · <?= e($ticket['class_name'] ?? '') ?><?= $ticket['subject_name'] ? ' · ' . e($ticket['subject_name']) : '' ?></div>
                    <?php if ($ticket['category']): ?><div class="text-muted small"><?= e($ticket['category']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($ticket['recipient_photo'])): ?><img src="<?= BASE_URL ?>/assets/uploads/chat/<?= e($ticket['recipient_photo']) ?>" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover"><?php else: ?><span class="support-avatar"><?= e(mb_substr($ticket['recipient_name'], 0, 1, 'UTF-8')) ?></span><?php endif; ?>
                    <div><strong><?= e($ticket['recipient_name']) ?></strong><div class="text-muted small"><?= e(translate($ticket['recipient_role_name'])) ?><?php if ($ticket['assigned_name']): ?> · <?= e(translate('assigned_to')) ?> <?= e($ticket['assigned_name']) ?><?php endif; ?></div></div>
                </div>
            </div>
            <hr>
            <p class="mb-1 fw-semibold"><?= e(translate('original_request')) ?></p>
            <?php if ($canViewOriginalRequest): ?><p class="mb-0" style="white-space:pre-wrap"><?= e($ticket['description']) ?></p><?php else: ?><p class="mb-0 text-muted"><?= e(translate('earlier_message_restricted')) ?></p><?php endif; ?>
        </section>

        <section class="card-panel mb-3">
            <h3 class="h5 mb-3"><?= e(translate('conversation')) ?></h3>
            <div class="support-thread" aria-live="polite">
                <?php foreach ($messages as $supportMessage): ?>
                    <article class="support-message <?= (int) $supportMessage['sender_id'] === $userId ? 'is-mine' : '' ?>">
                        <div class="support-message-meta"><strong><?= e($supportMessage['sender_name']) ?></strong><span><?= e(date('d/m/Y H:i', strtotime($supportMessage['created_at']))) ?></span><?php if ((int) $supportMessage['sender_id'] === $userId): ?><span><?= (int) $supportMessage['is_read'] ? e(translate('read')) : e(translate('sent')) ?></span><?php endif; ?></div>
                        <div class="support-message-body"><?= nl2br(e($supportMessage['message_text'])) ?></div>
                        <div class="support-attachments">
                            <?php foreach ($ticketAttachments as $attachment): if ((int) $attachment['message_id'] !== (int) $supportMessage['id']) continue; $attachmentUrl = BASE_URL . '/api/support-attachment.php?id=' . (int) $attachment['id']; ?>
                                <?php if (str_starts_with($attachment['file_type'], 'image/')): ?><a href="<?= e($attachmentUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($attachmentUrl) ?>" alt="<?= e($attachment['file_name']) ?>" class="support-attachment-image"></a>
                                <?php elseif (str_starts_with($attachment['file_type'], 'video/')): ?><video controls preload="metadata" class="support-attachment-video"><source src="<?= e($attachmentUrl) ?>" type="<?= e($attachment['file_type']) ?>"></video>
                                <?php elseif (str_starts_with($attachment['file_type'], 'audio/')): ?><audio controls preload="metadata" src="<?= e($attachmentUrl) ?>"></audio>
                                <?php else: ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($attachmentUrl) ?>"><i class="bi bi-paperclip"></i> <?= e($attachment['file_name']) ?></a><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($ticket['status'] !== 'closed'): ?>
                <form method="post" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="send_reply"><input type="hidden" name="ticket_id" value="<?= (int) $ticketId ?>">
                    <label class="form-label" for="messageText"><?= e(translate('write_reply')) ?></label>
                    <textarea id="messageText" name="message_text" class="form-control" rows="3" maxlength="10000"></textarea>
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-2 flex-wrap"><input type="file" class="form-control" name="attachments[]" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,video/mp4,video/quicktime,video/webm,audio/*,.doc,.docx" multiple><button class="btn btn-primary" type="submit"><?= e(translate('send_reply')) ?></button></div>
                </form>
            <?php else: ?><div class="alert alert-secondary mt-3 mb-0"><?= e(translate('ticket_closed')) ?></div><?php endif; ?>
        </section>
    </div>
    <aside class="col-lg-4">
        <?php if (in_array($role, ['teacher', 'admin'], true)): ?>
            <section class="card-panel mb-3">
                <h3 class="h6"><?= e(translate('change_status')) ?></h3>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="change_status"><input type="hidden" name="ticket_id" value="<?= (int) $ticketId ?>">
                    <select class="form-select mb-2" name="status"><?php foreach ($ticketStatuses as $status): ?><option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= e($statusLabels[$status]) ?></option><?php endforeach; ?></select>
                    <input class="form-control mb-2" name="status_note" maxlength="500" placeholder="<?= e(translate('status_note_optional')) ?>"><button type="submit" class="btn btn-outline-primary w-100"><?= e(translate('save_status')) ?></button>
                </form>
            </section>
        <?php endif; ?>
        <section class="card-panel mb-3">
            <h3 class="h6"><?= e(translate('conversation_participants')) ?></h3>
            <?php foreach ($participants as $participant): ?>
                <div class="d-flex justify-content-between align-items-center small py-1"><span><?= e($participant['full_name']) ?> · <?= e(translate($participant['role_name'])) ?></span>
                    <?php if ($role === 'admin' && (int) $participant['user_id'] !== $userId): ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="student"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="remove_participant"><input type="hidden" name="participant_id" value="<?= (int) $participant['user_id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><?= e(translate('remove')) ?></button></form><?php elseif ((int) $participant['user_id'] === $userId && $participant['added_by'] !== null): ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="student"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="leave_conversation"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(translate('leave_conversation')) ?></button></form><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (in_array($role, ['student', 'teacher', 'admin'], true)): ?>
                <form method="post" action="<?= BASE_URL ?>/support-actions.php" class="border-top mt-2 pt-2">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="student"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="send_invitation">
                    <label class="form-label small" for="inviteRecipient"><?= e(translate('invite_person')) ?></label>
                    <input type="search" id="supportInviteSearch" class="form-control form-control-sm mb-2" placeholder="<?= e(translate('search_people')) ?>"><select id="inviteRecipient" class="form-select form-select-sm mb-2" name="recipient_id" required><option value=""><?= e(translate('select_person')) ?></option><?php foreach ($eligibleInvitees as $invitee): ?><option value="<?= (int) $invitee['id'] ?>"><?= e($invitee['full_name']) ?> · <?= e(translate($invitee['role_name'])) ?></option><?php endforeach; ?></select>
                    <select class="form-select form-select-sm mb-2" name="invitation_type"><option value="normal"><?= e(translate('normal_invitation')) ?></option><?php if ($role === 'admin'): ?><option value="mandatory"><?= e(translate('mandatory_invitation')) ?></option><?php endif; ?></select>
                    <textarea class="form-control form-control-sm mb-2" name="reason" maxlength="1000" placeholder="<?= e(translate('invitation_reason')) ?>" required></textarea><button class="btn btn-sm btn-outline-primary w-100" type="submit"><?= e(translate('send_invitation')) ?></button>
                    <?php if (!empty($ticket['category']) && $ticket['request_type'] === 'complaint'): ?><small class="text-muted d-block mt-2"><?= e(translate('sensitive_invite_restriction')) ?></small><?php endif; ?>
                </form>
            <?php endif; ?>
            <?php foreach ($conversationInvitations as $invitation): ?>
                <div class="border-top mt-2 pt-2 small"><strong><?= e($invitation['recipient_name']) ?></strong> · <?= e(translate($invitation['invitation_type'] . '_invitation')) ?> · <?= e(translate('invitation_' . $invitation['status'])) ?><div class="text-muted"><?= e($invitation['reason']) ?> · <?= e(date('d/m/Y H:i', strtotime($invitation['created_at']))) ?></div>
                    <?php if ((int) $invitation['recipient_id'] === $userId && $invitation['status'] === 'pending'): ?><div class="d-flex gap-2 mt-1"><?php foreach (['accept_invitation' => 'accept', 'decline_invitation' => 'decline'] as $inviteAction => $inviteLabel): if ($inviteAction === 'decline_invitation' && $invitation['invitation_type'] === 'mandatory') continue; ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="student"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="invitation_id" value="<?= (int) $invitation['id'] ?>"><input type="hidden" name="action" value="<?= e($inviteAction) ?>"><button class="btn btn-sm btn-outline-primary" type="submit"><?= e(translate($inviteLabel)) ?></button></form><?php endforeach; ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php if (in_array($role, ['student', 'teacher', 'admin'], true)): ?>
            <section class="card-panel mb-3"><h3 class="h6"><?= e(translate('request_earlier_messages')) ?></h3><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="student"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="request_history"><textarea name="reason" class="form-control form-control-sm mb-2" maxlength="1000" placeholder="<?= e(translate('history_request_reason')) ?>" required></textarea><button type="submit" class="btn btn-sm btn-outline-secondary w-100"><?= e(translate('request_earlier_messages')) ?></button></form></section>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <?php foreach ($historyRequests as $historyRequest): if ($historyRequest['status'] !== 'pending') continue; ?>
                <section class="card-panel mb-3"><h3 class="h6"><?= e(translate('history_access_request')) ?> · <?= e($historyRequest['requester_name']) ?></h3><p class="small"><?= e($historyRequest['reason']) ?></p><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="student"><input type="hidden" name="conversation_id" value="<?= $ticketId ?>"><input type="hidden" name="action" value="decide_history"><input type="hidden" name="history_request_id" value="<?= (int) $historyRequest['id'] ?>"><select class="form-select form-select-sm mb-2" name="decision"><option value="approved"><?= e(translate('approve')) ?></option><option value="rejected"><?= e(translate('reject')) ?></option></select><select class="form-select form-select-sm mb-2" name="scope_type"><option value="all"><?= e(translate('all_history')) ?></option><option value="date_range"><?= e(translate('date_range')) ?></option><option value="message_range"><?= e(translate('message_range')) ?></option></select><input class="form-control form-control-sm mb-2" type="datetime-local" name="scope_start"><input class="form-control form-control-sm mb-2" type="datetime-local" name="scope_end"><div class="row g-2"><div class="col"><input class="form-control form-control-sm" type="number" min="1" name="start_message_id" placeholder="<?= e(translate('first_message_id')) ?>"></div><div class="col"><input class="form-control form-control-sm" type="number" min="1" name="end_message_id" placeholder="<?= e(translate('last_message_id')) ?>"></div></div><input class="form-control form-control-sm my-2" name="decision_note" maxlength="1000" placeholder="<?= e(translate('decision_note')) ?>"><button type="submit" class="btn btn-sm btn-primary w-100"><?= e(translate('save')) ?></button></form></section>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <section class="card-panel mb-3"><h3 class="h6"><?= e(translate('assign_request')) ?></h3>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="assign_ticket"><input type="hidden" name="ticket_id" value="<?= (int) $ticketId ?>"><select name="assigned_to" class="form-select mb-2" required><?php foreach ($assigneeOptions as $assignee): ?><option value="<?= (int) $assignee['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $assignee['id'] ? 'selected' : '' ?>><?= e($assignee['full_name']) ?> · <?= e($assignee['role_name']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary w-100" type="submit"><?= e(translate('assign')) ?></button></form>
                <?php if ($assignmentHistory): ?><hr><h4 class="small fw-semibold"><?= e(translate('assignment_history')) ?></h4><?php foreach ($assignmentHistory as $assignment): ?><div class="small text-muted"><?= e($assignment['assigned_name']) ?> · <?= e(date('d/m/Y H:i', strtotime($assignment['assigned_at']))) ?> · <?= e($assignment['assigned_by_name']) ?></div><?php endforeach; ?><?php endif; ?>
            </section>
        <?php endif; ?>
        <section class="card-panel"><h3 class="h6"><?= e(translate('status_history')) ?></h3><?php if (!$statusHistory): ?><p class="text-muted mb-0">-</p><?php else: ?><ul class="list-unstyled mb-0"><?php foreach ($statusHistory as $entry): ?><li class="mb-2"><strong><?= e($statusLabels[$entry['status']] ?? $entry['status']) ?></strong><div class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($entry['changed_at']))) ?> · <?= e($entry['changed_by_name']) ?></div><?php if ($entry['notes']): ?><div class="small"><?= e($entry['notes']) ?></div><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></section>
    </aside>
</div>
<?php else: ?>
<div class="row g-4">
    <?php if ($role === 'student'): ?>
    <div class="col-xl-5">
        <section class="card-panel">
            <h2 class="h5 mb-3"><?= e(translate('create_support_request')) ?></h2>
            <?php if (!$studentData): ?><div class="alert alert-warning mb-0"><?= e(translate('support_student_profile_missing')) ?></div><?php else: ?>
            <form method="post" enctype="multipart/form-data" id="supportCreateForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="create_ticket">
                <div class="mb-3"><label class="form-label" for="requestType"><?= e(translate('request_type')) ?></label><select class="form-select" name="request_type" id="requestType" required><option value="question"><?= e($typeLabels['question']) ?></option><option value="problem"><?= e($typeLabels['problem']) ?></option><option value="complaint"><?= e($typeLabels['complaint']) ?></option></select></div>
                <div class="mb-3"><label class="form-label" for="recipientRole"><?= e(translate('send_to')) ?></label><select class="form-select" name="recipient_role" id="recipientRole" required><option value="teacher"><?= e(translate('teacher')) ?></option><option value="admin"><?= e(translate('admin')) ?></option></select></div>
                <div class="mb-3"><label class="form-label" for="supportSubject"><?= e(translate('subject_optional')) ?></label><select class="form-select" name="subject_id" id="supportSubject"><option value="0"><?= e(translate('no_subject')) ?></option><?php foreach ($subjectOptions as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= e($subject['name_en']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3" id="teacherRecipientGroup"><label class="form-label" for="teacherSearch"><?= e(translate('select_teacher')) ?></label><input type="search" id="teacherSearch" class="form-control mb-2" placeholder="<?= e(translate('search_teachers')) ?>"><div class="support-teacher-options" id="teacherOptions"><?php foreach ($teacherOptions as $teacherOption): ?><label class="support-teacher-option" data-name="<?= e(mb_strtolower($teacherOption['full_name'], 'UTF-8')) ?>" data-subjects="|<?= e($teacherOption['subject_ids']) ?>|"><input type="radio" name="teacher_user_id" value="<?= (int) $teacherOption['id'] ?>" required><?php if (!empty($teacherOption['photo'])): ?><img src="<?= BASE_URL ?>/assets/uploads/chat/<?= e($teacherOption['photo']) ?>" alt=""><?php else: ?><span class="support-avatar"><?= e(mb_substr($teacherOption['full_name'], 0, 1, 'UTF-8')) ?></span><?php endif; ?><span class="support-teacher-name"><?= e($teacherOption['full_name']) ?><small><?= e($teacherOption['subjects'] ?? '') ?></small></span></label><?php endforeach; ?></div></div>
                <div class="mb-3" id="adminRecipientGroup" hidden><label class="form-label" for="adminRecipient"><?= e(translate('select_admin')) ?></label><select class="form-select" name="admin_user_id" id="adminRecipient" disabled required><?php foreach ($adminOptions as $adminOption): ?><option value="<?= (int) $adminOption['id'] ?>" data-photo="<?= e($adminOption['photo'] ?? '') ?>"><?= e($adminOption['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3" id="categoryGroup" hidden><label class="form-label" for="supportCategory"><?= e(translate('category')) ?></label><select class="form-select" name="category" id="supportCategory"><option value=""><?= e(translate('select_category')) ?></option><?php foreach (array_unique(array_merge($problemCategories, $complaintCategories)) as $category): ?><option value="<?= e($category) ?>" data-kind="<?= in_array($category, $complaintCategories, true) ? 'complaint' : 'problem' ?>"><?= e($category) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3" id="complaintSubjectGroup" hidden><label class="form-label" for="complaintSubject"><?= e(translate('person_involved_optional')) ?></label><select class="form-select" id="complaintSubject" name="subject_user_id" disabled><option value="0"><?= e(translate('select_person')) ?></option><?php foreach ($complaintSubjectOptions as $person): ?><option value="<?= (int) $person['id'] ?>"><?= e($person['full_name']) ?> · <?= e(translate($person['role_name'])) ?></option><?php endforeach; ?></select><div class="form-text"><?= e(translate('sensitive_complaint_routing')) ?></div></div>
                <div class="mb-3"><label class="form-label" for="supportTitle"><?= e(translate('title')) ?></label><input type="text" class="form-control" id="supportTitle" name="title" maxlength="255" required></div>
                <div class="mb-3"><label class="form-label" for="supportDescription"><?= e(translate('detailed_description')) ?></label><textarea class="form-control" id="supportDescription" name="description" rows="4" maxlength="10000" required></textarea></div>
                <div class="mb-3"><label class="form-label" for="supportAttachments"><?= e(translate('attachments_optional')) ?></label><input type="file" class="form-control" id="supportAttachments" name="attachments[]" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,video/mp4,video/quicktime,video/webm,audio/*,.doc,.docx" multiple><div class="form-text"><?= e(translate('support_attachment_limits')) ?></div></div>
                <button class="btn btn-primary w-100" type="submit"><?= e(translate('send_request')) ?></button>
            </form>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
    <div class="<?= $role === 'student' ? 'col-xl-7' : 'col-12' ?>">
        <section class="card-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3"><div><h2 class="h5 mb-1"><?= e($role === 'student' ? translate('my_requests') : translate('student_support')) ?></h2><p class="text-muted small mb-0"><?= e($role === 'admin' ? translate('support_admin_scope') : ($role === 'teacher' ? translate('support_teacher_scope') : translate('support_student_scope'))) ?></p></div><div class="text-muted small"><?= count($tickets) ?> <?= e(translate('requests')) ?></div></div>
            <form method="get" class="row g-2 mb-3">
                <div class="col-md"><input type="search" class="form-control" name="q" value="<?= e($searchText) ?>" placeholder="<?= e(translate('search_ticket_student_recipient')) ?>"></div>
                <div class="col-sm-6 col-md-3"><select name="type" class="form-select"><option value=""><?= e(translate('all_types')) ?></option><?php foreach ($ticketTypes as $type): ?><option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e($typeLabels[$type]) ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6 col-md-3"><select name="status" class="form-select"><option value=""><?= e(translate('all_statuses')) ?></option><?php foreach ($ticketStatuses as $status): ?><option value="<?= e($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= e($statusLabels[$status]) ?></option><?php endforeach; ?></select></div>
                <?php if (in_array($role, ['teacher', 'admin'], true)): ?><div class="col-sm-6 col-md-3"><select name="class_id" class="form-select"><option value="0"><?= e(translate('all_classes')) ?></option><?php foreach ($classFilterOptions as $classOption): ?><option value="<?= (int) $classOption['id'] ?>" <?= $filterClass === (int) $classOption['id'] ? 'selected' : '' ?>><?= e($classOption['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <div class="col-sm-6 col-md-3"><select name="subject_id" class="form-select"><option value="0"><?= e(translate('all_subjects')) ?></option><?php foreach ($subjectFilterOptions as $subjectOption): ?><option value="<?= (int) $subjectOption['id'] ?>" <?= $filterSubject === (int) $subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['name_en']) ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6 col-md-3"><select name="category" class="form-select"><option value=""><?= e(translate('all_categories')) ?></option><?php foreach ($allCategories as $categoryOption): ?><option value="<?= e($categoryOption) ?>" <?= $filterCategory === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option><?php endforeach; ?></select></div>
                <?php if ($role === 'admin'): ?><div class="col-sm-6 col-md-3"><select name="recipient_user_id" class="form-select"><option value="0"><?= e(translate('all_recipients')) ?></option><?php foreach ($assigneeOptions as $assignee): ?><option value="<?= (int) $assignee['id'] ?>" <?= $filterRecipient === (int) $assignee['id'] ? 'selected' : '' ?>><?= e($assignee['full_name']) ?> · <?= e($assignee['role_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <div class="col-auto"><button class="btn btn-outline-primary" type="submit" aria-label="<?= e(translate('search')) ?>"><i class="bi bi-search"></i></button></div>
            </form>
            <?php if (!$tickets): ?><p class="text-muted mb-0"><?= e(translate('no_support_requests')) ?></p><?php else: ?>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th><?= e(translate('ticket_number')) ?></th><th><?= e(translate('request')) ?></th><th><?= e(translate('recipient')) ?></th><?php if ($role !== 'student'): ?><th><?= e(translate('student')) ?></th><?php endif; ?><th><?= e(translate('subject_category')) ?></th><th><?= e(translate('created')) ?></th><th><?= e(translate('last_message')) ?></th><th><?= e(translate('status')) ?></th></tr></thead><tbody>
                <?php foreach ($tickets as $row): ?><tr><td><a href="?ticket=<?= (int) $row['id'] ?>"><strong><?= e($row['ticket_number']) ?></strong></a></td><td><?= e($typeLabels[$row['request_type']] ?? $row['request_type']) ?><div class="small fw-semibold"><?= e($row['title']) ?></div></td><td><?= e($row['recipient_name']) ?><div class="small text-muted"><?= e($row['recipient_role_name']) ?></div></td><?php if ($role !== 'student'): ?><td><?= e($row['student_name']) ?></td><?php endif; ?><td><?= e($row['subject_name'] ?: $row['category'] ?: '-') ?></td><td><?= e(date('d/m/Y', strtotime($row['created_at']))) ?></td><td><span class="small text-muted"><?= e(mb_substr($row['last_message'] ?? '', 0, 48, 'UTF-8')) ?></span><?php if ((int) $row['unread_count'] > 0): ?><span class="badge bg-danger ms-1"><?= (int) $row['unread_count'] ?> <?= e(translate('unread')) ?></span><?php endif; ?></td><td><span class="badge text-bg-<?= in_array($row['status'], ['resolved', 'closed'], true) ? 'success' : 'warning' ?>"><?= e($statusLabels[$row['status']] ?? $row['status']) ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
    </div>
</div>
<?php endif; ?>

<section class="card-panel mt-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0"><?= e(translate('support_notification_history')) ?></h2><?php if ($unreadSupportCount): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="mark_notifications_read"><button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(translate('mark_all_read')) ?></button></form><?php endif; ?></div>
    <?php if (!$supportNotifications): ?><p class="text-muted small mb-0">-</p><?php else: ?><ul class="list-unstyled mb-0"><?php foreach ($supportNotifications as $notification): ?><li class="border-top py-2 <?= !(int) $notification['is_read'] ? 'fw-semibold' : '' ?>"><span><?= e($notification['title']) ?></span><div class="small text-muted"><?= e($notification['message']) ?> · <?= e(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></div></li><?php endforeach; ?></ul><?php endif; ?>
</section>

<script>
(function () {
    const recipientRole = document.getElementById('recipientRole');
    if (!recipientRole) return;
    const teacherGroup = document.getElementById('teacherRecipientGroup');
    const adminGroup = document.getElementById('adminRecipientGroup');
    const teacherOptions = document.getElementById('teacherOptions');
    const adminSelect = document.getElementById('adminRecipient');
    const subjectSelect = document.getElementById('supportSubject');
    const teacherSearch = document.getElementById('teacherSearch');
    const typeSelect = document.getElementById('requestType');
    const categoryGroup = document.getElementById('categoryGroup');
    const categorySelect = document.getElementById('supportCategory');
    const complaintSubjectGroup = document.getElementById('complaintSubjectGroup');
    const complaintSubject = document.getElementById('complaintSubject');

    function filterTeachers() {
        const query = teacherSearch.value.trim().toLocaleLowerCase();
        const subjectId = subjectSelect.value;
        for (const card of teacherOptions.querySelectorAll('.support-teacher-option')) {
            const names = `${card.dataset.subjects || ''}`;
            const subjectMatch = !subjectId || names.includes(`|${subjectId}|`);
            const nameMatch = !query || card.dataset.name.includes(query);
            const radio = card.querySelector('input[type="radio"]');
            card.hidden = !subjectMatch || !nameMatch;
            radio.disabled = card.hidden || recipientRole.value !== 'teacher';
            radio.required = !card.hidden && recipientRole.value === 'teacher';
            if (card.hidden && radio.checked) radio.checked = false;
        }
    }
    function refreshRecipient() {
        const teacherSelected = recipientRole.value === 'teacher';
        teacherGroup.hidden = !teacherSelected;
        adminGroup.hidden = teacherSelected;
        adminSelect.disabled = teacherSelected;
        adminSelect.required = !teacherSelected;
        filterTeachers();
    }
    function refreshCategory() {
        const type = typeSelect.value;
        if (type === 'complaint' && recipientRole.value !== 'admin') {
            recipientRole.value = 'admin';
            refreshRecipient();
        }
        categoryGroup.hidden = type === 'question';
        categorySelect.required = type !== 'question';
        complaintSubjectGroup.hidden = type !== 'complaint';
        complaintSubject.disabled = type !== 'complaint';
        for (const option of categorySelect.options) {
            option.hidden = option.dataset.kind && option.dataset.kind !== type;
            if (option.hidden && option.selected) option.selected = false;
        }
    }
    recipientRole.addEventListener('change', refreshRecipient);
    subjectSelect.addEventListener('change', filterTeachers);
    teacherSearch.addEventListener('input', filterTeachers);
    typeSelect.addEventListener('change', refreshCategory);
    refreshRecipient();
    refreshCategory();
})();
const supportInviteSearch = document.getElementById('supportInviteSearch');
const supportInviteRecipient = document.getElementById('inviteRecipient');
if (supportInviteSearch && supportInviteRecipient) supportInviteSearch.addEventListener('input', () => { const query = supportInviteSearch.value.toLocaleLowerCase(); for (const option of supportInviteRecipient.options) if (option.value) option.hidden = !option.text.toLocaleLowerCase().includes(query); });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

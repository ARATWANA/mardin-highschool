<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support.php';

requireAuth();
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$role = (string) ($user['role_name'] ?? '');
$conversationType = (string) ($_POST['conversation_type'] ?? '');
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$redirectPage = $conversationType === 'staff' ? '/staff-support.php' : '/support.php';

if (!supportVerifyCsrf((string) ($_POST['csrf_token'] ?? '')) || !in_array($conversationType, ['student', 'staff'], true) || $conversationId < 1) {
    http_response_code(400);
    exit('Invalid support action.');
}

$conn = getDbConnection();
$action = (string) ($_POST['action'] ?? '');
$message = '';

$conn->begin_transaction();
$transactionOpen = true;
try {
    if ($action === 'send_invitation') {
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $invitationType = (string) ($_POST['invitation_type'] ?? 'normal');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 1000 || !supportCanInvite($conn, $conversationType, $conversationId, $userId, $role, $invitationType, $recipientId)) {
            throw new RuntimeException('You are not authorized to invite this person to this conversation.');
        }
        $memberCheck = $conn->prepare('SELECT id FROM support_conversation_participants WHERE conversation_type = ? AND conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1');
        $memberCheck->bind_param('sii', $conversationType, $conversationId, $recipientId);
        $memberCheck->execute();
        $alreadyMember = (bool) $memberCheck->get_result()->fetch_assoc();
        $memberCheck->close();
        if ($alreadyMember) {
            throw new RuntimeException('That person is already a participant.');
        }
        $pending = $conn->prepare('SELECT id FROM support_invitations WHERE conversation_type = ? AND conversation_id = ? AND recipient_id = ? AND status = "pending" LIMIT 1');
        $pending->bind_param('sii', $conversationType, $conversationId, $recipientId);
        $pending->execute();
        $hasPending = (bool) $pending->get_result()->fetch_assoc();
        $pending->close();
        if ($hasPending) {
            throw new RuntimeException('This person already has a pending invitation.');
        }
        $invite = $conn->prepare('INSERT INTO support_invitations (conversation_type, conversation_id, recipient_id, invited_by, invitation_type, reason) VALUES (?, ?, ?, ?, ?, ?)');
        $invite->bind_param('siiiss', $conversationType, $conversationId, $recipientId, $userId, $invitationType, $reason);
        $invite->execute();
        $invitationId = $invite->insert_id;
        $invite->close();
        if ($invitationType === 'mandatory') {
            $recipientRoleStmt = $conn->prepare('SELECT r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1');
            $recipientRoleStmt->bind_param('i', $recipientId);
            $recipientRoleStmt->execute();
            $recipientRoleName = (string) ($recipientRoleStmt->get_result()->fetch_assoc()['role_name'] ?? '');
            $recipientRoleStmt->close();
            supportAddParticipant($conn, $conversationType, $conversationId, $recipientId, $recipientRoleName, $userId);
            $accepted = $conn->prepare('UPDATE support_invitations SET status = "accepted", responded_at = NOW(), joined_at = NOW() WHERE id = ?');
            $accepted->bind_param('i', $invitationId);
            $accepted->execute();
            $accepted->close();
            supportAudit($conn, $conversationType, $conversationId, $userId, 'mandatory_join', $reason, $recipientId, $invitationId);
            supportNotifyConversation($conn, $conversationType, $conversationId, $userId, 'Participant joined', 'A mandatory participant joined the conversation.');
        }
        supportAudit($conn, $conversationType, $conversationId, $userId, 'invitation_sent', $reason, $recipientId, $invitationId);
        $ticketNumber = supportConversationTicketNumber($conn, $conversationType, $conversationId);
        supportCreateNotification($conn, $recipientId, 'Support: Invitation', $ticketNumber . ': ' . ($invitationType === 'mandatory' ? 'Mandatory invitation' : 'Invitation') . ' from ' . ($user['full_name'] ?? 'school staff') . '. Reason: ' . $reason);
        $message = 'Invitation sent.';
    } elseif (in_array($action, ['accept_invitation', 'decline_invitation'], true)) {
        $invitationId = (int) ($_POST['invitation_id'] ?? 0);
        $inviteStmt = $conn->prepare('SELECT * FROM support_invitations WHERE id = ? AND conversation_type = ? AND conversation_id = ? AND recipient_id = ? AND status = "pending" LIMIT 1');
        $inviteStmt->bind_param('isii', $invitationId, $conversationType, $conversationId, $userId);
        $inviteStmt->execute();
        $invitation = $inviteStmt->get_result()->fetch_assoc();
        $inviteStmt->close();
        if (!$invitation || ($action === 'decline_invitation' && $invitation['invitation_type'] === 'mandatory')) {
            throw new RuntimeException('This invitation cannot be accepted or declined.');
        }
        if ($action === 'accept_invitation') {
            $inviterRoleStmt = $conn->prepare('SELECT r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active" LIMIT 1');
            $inviterId = (int) $invitation['invited_by'];
            $inviterRoleStmt->bind_param('i', $inviterId);
            $inviterRoleStmt->execute();
            $inviterRole = (string) ($inviterRoleStmt->get_result()->fetch_assoc()['role_name'] ?? '');
            $inviterRoleStmt->close();
            if (!supportCanInvite($conn, $conversationType, $conversationId, $inviterId, $inviterRole, $invitation['invitation_type'], $userId)) {
                throw new RuntimeException('The original invitation is no longer authorized.');
            }
        }
        if ($action === 'accept_invitation' && $conversationType === 'student') {
            $privacyCheck = $conn->prepare('SELECT t.request_type, sc.subject_user_id FROM support_tickets t LEFT JOIN support_sensitive_cases sc ON sc.ticket_id = t.id AND sc.restricted = 1 WHERE t.id = ? LIMIT 1');
            $privacyCheck->bind_param('i', $conversationId);
            $privacyCheck->execute();
            $privacyRow = $privacyCheck->get_result()->fetch_assoc();
            $privacyCheck->close();
            if (!$privacyRow || ($privacyRow['request_type'] === 'complaint' && $role !== 'admin') || (int) ($privacyRow['subject_user_id'] ?? 0) === $userId) {
                throw new RuntimeException('This sensitive conversation is restricted for your account.');
            }
        }
        $newStatus = $action === 'accept_invitation' ? 'accepted' : 'declined';
        $respond = $conn->prepare('UPDATE support_invitations SET status = ?, responded_at = NOW(), joined_at = IF(? = "accepted", NOW(), NULL) WHERE id = ?');
        $respond->bind_param('ssi', $newStatus, $newStatus, $invitationId);
        $respond->execute();
        $respond->close();
        if ($newStatus === 'accepted') {
            supportAddParticipant($conn, $conversationType, $conversationId, $userId, $role, (int) $invitation['invited_by']);
        }
        supportAudit($conn, $conversationType, $conversationId, $userId, 'invitation_' . $newStatus, null, $userId, $invitationId);
        supportNotifyConversation($conn, $conversationType, $conversationId, $userId, 'Participant update', ($user['full_name'] ?? 'A user') . ' ' . ($newStatus === 'accepted' ? 'joined' : 'declined') . ' the conversation.');
        $message = $newStatus === 'accepted' ? 'Invitation accepted.' : 'Invitation declined.';
    } elseif ($action === 'revoke_invitation') {
        $invitationId = (int) ($_POST['invitation_id'] ?? 0);
        if (!supportCanAccessConversation($conn, $conversationType, $conversationId, $userId, $role)) {
            throw new RuntimeException('You cannot revoke an invitation for this conversation.');
        }
        $revoke = $conn->prepare('UPDATE support_invitations SET status = "revoked", responded_at = NOW() WHERE id = ? AND conversation_type = ? AND conversation_id = ? AND status = "pending" AND (invited_by = ? OR ? = "admin")');
        $revoke->bind_param('isiis', $invitationId, $conversationType, $conversationId, $userId, $role);
        $revoke->execute();
        if ($revoke->affected_rows !== 1) {
            $revoke->close();
            throw new RuntimeException('You cannot revoke this invitation.');
        }
        $revoke->close();
        supportAudit($conn, $conversationType, $conversationId, $userId, 'invitation_revoked', null, null, $invitationId);
        $message = 'Invitation revoked.';
    } elseif ($action === 'remove_participant' || $action === 'leave_conversation') {
        $participantId = (int) ($_POST['participant_id'] ?? $userId);
        $canManage = $role === 'admin' || ($action === 'leave_conversation' && $participantId === $userId);
        if (!$canManage || !supportCanAccessConversation($conn, $conversationType, $conversationId, $userId, $role)) {
            throw new RuntimeException('You are not authorized to change conversation participation.');
        }
        if ($action === 'leave_conversation') {
            $mandatoryStmt = $conn->prepare('SELECT id FROM support_invitations WHERE conversation_type = ? AND conversation_id = ? AND recipient_id = ? AND invitation_type = "mandatory" AND status = "accepted" LIMIT 1');
            $mandatoryStmt->bind_param('sii', $conversationType, $conversationId, $userId);
            $mandatoryStmt->execute();
            $mandatoryParticipation = (bool) $mandatoryStmt->get_result()->fetch_assoc();
            $mandatoryStmt->close();
            if ($mandatoryParticipation) {
                throw new RuntimeException('Mandatory participation cannot be left by the participant. Ask an administrator to remove you.');
            }
        }
        $remove = $conn->prepare('UPDATE support_conversation_participants SET left_at = NOW() WHERE conversation_type = ? AND conversation_id = ? AND user_id = ? AND left_at IS NULL');
        $remove->bind_param('sii', $conversationType, $conversationId, $participantId);
        $remove->execute();
        $removed = $remove->affected_rows === 1;
        $remove->close();
        if (!$removed) {
            throw new RuntimeException('That participant is not currently in this conversation.');
        }
        supportAudit($conn, $conversationType, $conversationId, $userId, $action, null, $participantId);
        supportNotifyConversation($conn, $conversationType, $conversationId, $userId, 'Participant update', 'A participant was removed from the conversation.');
        $message = 'Conversation participation updated.';
    } elseif ($action === 'request_history') {
        if (!supportCanAccessConversation($conn, $conversationType, $conversationId, $userId, $role)) {
            throw new RuntimeException('You cannot request history for this conversation.');
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('Enter a reason for requesting earlier messages.');
        }
        $request = $conn->prepare('INSERT INTO support_history_requests (conversation_type, conversation_id, requester_id, reason) VALUES (?, ?, ?, ?)');
        $request->bind_param('siis', $conversationType, $conversationId, $userId, $reason);
        $request->execute();
        $historyRequestId = $request->insert_id;
        $request->close();
        supportAudit($conn, $conversationType, $conversationId, $userId, 'history_requested', $reason);
        $adminsStmt = $conn->prepare('SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.name = "admin" AND u.status = "active" AND NOT EXISTS (SELECT 1 FROM support_sensitive_cases sc WHERE ? = "student" AND sc.ticket_id = ? AND sc.restricted = 1 AND sc.subject_user_id = u.id)');
        $adminsStmt->bind_param('si', $conversationType, $conversationId);
        $adminsStmt->execute();
        $admins = $adminsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $adminsStmt->close();
        $ticketNumber = supportConversationTicketNumber($conn, $conversationType, $conversationId);
        foreach ($admins as $admin) {
            supportCreateNotification($conn, (int) $admin['id'], 'Support: History access request', $ticketNumber . ': ' . $reason);
        }
        $message = 'Earlier-message access request sent to admins.';
    } elseif ($action === 'decide_history' && $role === 'admin') {
        $requestId = (int) ($_POST['history_request_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $scopeType = (string) ($_POST['scope_type'] ?? '');
        $scopeStart = trim((string) ($_POST['scope_start'] ?? ''));
        $scopeEnd = trim((string) ($_POST['scope_end'] ?? ''));
        $startMessageId = (int) ($_POST['start_message_id'] ?? 0);
        $endMessageId = (int) ($_POST['end_message_id'] ?? 0);
        $decisionNote = trim((string) ($_POST['decision_note'] ?? ''));
        if ($decision === 'rejected') {
            $scopeType = 'all';
        }
        if ($scopeType === 'date_range') {
            $scopeStartDate = DateTime::createFromFormat('Y-m-d\\TH:i', $scopeStart);
            $scopeEndDate = DateTime::createFromFormat('Y-m-d\\TH:i', $scopeEnd);
            $scopeStart = $scopeStartDate ? $scopeStartDate->format('Y-m-d H:i:s') : '';
            $scopeEnd = $scopeEndDate ? $scopeEndDate->format('Y-m-d H:i:s') : '';
        }
        $validDecision = in_array($decision, ['approved', 'rejected'], true);
        $validScope = $decision === 'rejected' || $scopeType === 'all' || ($scopeType === 'date_range' && $scopeStart !== '' && $scopeEnd !== '' && $scopeEnd >= $scopeStart) || ($scopeType === 'message_range' && $startMessageId > 0 && $endMessageId >= $startMessageId);
        if (!$validDecision || !$validScope) {
            throw new RuntimeException('Choose a valid decision and history scope.');
        }
        if ($conversationType === 'student') {
            $subjectCheck = $conn->prepare('SELECT subject_user_id FROM support_sensitive_cases WHERE ticket_id = ? AND restricted = 1 AND subject_user_id = ? LIMIT 1');
            $subjectCheck->bind_param('ii', $conversationId, $userId);
            $subjectCheck->execute();
            $isComplaintSubject = (bool) $subjectCheck->get_result()->fetch_assoc();
            $subjectCheck->close();
            if ($isComplaintSubject) {
                throw new RuntimeException('An admin named in a sensitive complaint cannot decide its history access.');
            }
        }
        if ($decision === 'approved' && $scopeType === 'message_range') {
            $messageTable = supportConversationTables($conversationType)['messages'];
            $rangeCheck = $conn->prepare('SELECT COUNT(*) AS total FROM ' . $messageTable . ' WHERE ticket_id = ? AND id IN (?, ?)');
            $rangeCheck->bind_param('iii', $conversationId, $startMessageId, $endMessageId);
            $rangeCheck->execute();
            $rangeCount = (int) ($rangeCheck->get_result()->fetch_assoc()['total'] ?? 0);
            $rangeCheck->close();
            if ($rangeCount !== 2 && $startMessageId !== $endMessageId) {
                throw new RuntimeException('Both message-range boundaries must belong to this conversation.');
            }
            if ($rangeCount !== 1 && $startMessageId === $endMessageId) {
                throw new RuntimeException('The message-range boundary must belong to this conversation.');
            }
        }
        $decisionStmt = $conn->prepare('UPDATE support_history_requests SET status = ?, scope_type = ?, scope_start = NULLIF(?, ""), scope_end = NULLIF(?, ""), start_message_id = NULLIF(?, 0), end_message_id = NULLIF(?, 0), decided_by = ?, decision_note = ?, decided_at = NOW() WHERE id = ? AND conversation_type = ? AND conversation_id = ? AND status = "pending" AND requester_id <> ?');
        $decisionStmt->bind_param('ssssiiisisii', $decision, $scopeType, $scopeStart, $scopeEnd, $startMessageId, $endMessageId, $userId, $decisionNote, $requestId, $conversationType, $conversationId, $userId);
        $decisionStmt->execute();
        if ($decisionStmt->affected_rows !== 1) {
            $decisionStmt->close();
            throw new RuntimeException('History request not found or already decided.');
        }
        $decisionStmt->close();
        supportAudit($conn, $conversationType, $conversationId, $userId, 'history_' . $decision, $decisionNote, null);
        $requesterStmt = $conn->prepare('SELECT requester_id FROM support_history_requests WHERE id = ?');
        $requesterStmt->bind_param('i', $requestId);
        $requesterStmt->execute();
        $requesterId = (int) ($requesterStmt->get_result()->fetch_assoc()['requester_id'] ?? 0);
        $requesterStmt->close();
        supportCreateNotification($conn, $requesterId, 'Support: History request ' . $decision, supportConversationTicketNumber($conn, $conversationType, $conversationId) . ': Your earlier-message access request was ' . $decision . '.');
        $message = 'History request reviewed.';
    } else {
        throw new RuntimeException('Unknown support action.');
    }
    $conn->commit();
    $transactionOpen = false;
    $_SESSION['support_flash'] = $message;
} catch (Throwable $exception) {
    if ($transactionOpen) {
        $conn->rollback();
        $transactionOpen = false;
    }
    $_SESSION['support_error'] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The support action could not be completed.';
}
$conn->close();
$query = (string) ($_POST['return_query'] ?? '');
header('Location: ' . BASE_URL . $redirectPage . ($conversationId > 0 ? '?ticket=' . $conversationId : '') . ($query !== '' ? '&' . $query : ''), true, 303);
exit;

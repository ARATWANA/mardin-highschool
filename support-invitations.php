<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support.php';
requireAuth();
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$conn = getDbConnection();
$stmt = $conn->prepare('SELECT i.*, inviter.full_name AS inviter_name, CASE WHEN i.conversation_type = "student" THEN st.title ELSE ft.title END AS conversation_title, CASE WHEN i.conversation_type = "student" THEN st.ticket_number ELSE ft.ticket_number END AS ticket_number FROM support_invitations i INNER JOIN users inviter ON inviter.id = i.invited_by LEFT JOIN support_tickets st ON i.conversation_type = "student" AND st.id = i.conversation_id LEFT JOIN staff_support_tickets ft ON i.conversation_type = "staff" AND ft.id = i.conversation_id WHERE i.recipient_id = ? AND NOT EXISTS (SELECT 1 FROM support_sensitive_cases sc WHERE i.conversation_type = "student" AND sc.ticket_id = i.conversation_id AND sc.restricted = 1 AND sc.subject_user_id = i.recipient_id) ORDER BY (i.status = "pending") DESC, i.created_at DESC LIMIT 100');
$stmt->bind_param('i', $userId);
$stmt->execute();
$invitations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$csrfToken = supportCsrfToken();
$conn->close();
require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading"><div><p class="eyebrow"><?= e(translate('student_support')) ?></p><h1><?= e(translate('support_invitations')) ?></h1></div><div class="dashboard-date"><i class="bi bi-envelope"></i></div></div>
<div class="card-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th><?= e(translate('ticket_number')) ?></th><th><?= e(translate('conversation')) ?></th><th><?= e(translate('invited_by')) ?></th><th><?= e(translate('invitation_type')) ?></th><th><?= e(translate('invitation_status')) ?></th><th><?= e(translate('date')) ?></th><th><?= e(translate('action')) ?></th></tr></thead><tbody>
<?php if (!$invitations): ?><tr><td colspan="7" class="text-center text-muted"><?= e(translate('no_invitations')) ?></td></tr><?php else: foreach ($invitations as $invite): ?><tr><td><?= e($invite['ticket_number'] ?? '-') ?></td><td><?= e($invite['conversation_title'] ?? '-') ?></td><td><?= e($invite['inviter_name']) ?><div class="small text-muted"><?= e($invite['reason']) ?></div></td><td><span class="badge text-bg-<?= $invite['invitation_type'] === 'mandatory' ? 'danger' : 'secondary' ?>"><?= e(translate($invite['invitation_type'] . '_invitation')) ?></span></td><td><?= e(translate('invitation_' . $invite['status'])) ?></td><td><?= e(date('d/m/Y H:i', strtotime($invite['created_at']))) ?></td><td><?php if ($invite['status'] === 'pending'): ?><div class="d-flex gap-1"><?php foreach (['accept_invitation' => 'accept', 'decline_invitation' => 'decline'] as $action => $label): if ($action === 'decline_invitation' && $invite['invitation_type'] === 'mandatory') continue; ?><form method="post" action="<?= BASE_URL ?>/support-actions.php"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="<?= e($invite['conversation_type']) ?>"><input type="hidden" name="conversation_id" value="<?= (int) $invite['conversation_id'] ?>"><input type="hidden" name="invitation_id" value="<?= (int) $invite['id'] ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><button type="submit" class="btn btn-sm <?= $action === 'accept_invitation' ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= e(translate($label)) ?></button></form><?php endforeach; ?></div><?php elseif ($invite['status'] === 'accepted'): ?><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/<?= $invite['conversation_type'] === 'staff' ? 'staff-support.php' : 'support.php' ?>?ticket=<?= (int) $invite['conversation_id'] ?>"><?= e(translate('open_conversation')) ?></a><?php endif; ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

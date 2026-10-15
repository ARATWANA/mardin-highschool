<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support.php';
requireAuth();
$user = currentUser();
if (($user['role_name'] ?? '') !== 'admin') {
    redirect(BASE_URL . '/dashboard.php');
}
$conn = getDbConnection();
$requestsStmt = $conn->prepare('SELECT h.*, requester.full_name AS requester_name, CASE WHEN h.conversation_type = "student" THEN st.ticket_number ELSE ft.ticket_number END AS ticket_number, CASE WHEN h.conversation_type = "student" THEN st.title ELSE ft.title END AS conversation_title FROM support_history_requests h INNER JOIN users requester ON requester.id = h.requester_id LEFT JOIN support_tickets st ON h.conversation_type = "student" AND st.id = h.conversation_id LEFT JOIN staff_support_tickets ft ON h.conversation_type = "staff" AND ft.id = h.conversation_id WHERE NOT EXISTS (SELECT 1 FROM support_sensitive_cases sc WHERE h.conversation_type = "student" AND sc.ticket_id = h.conversation_id AND sc.restricted = 1 AND sc.subject_user_id = ?) ORDER BY (h.status = "pending") DESC, h.created_at DESC LIMIT 200');
$requestsStmt->bind_param('i', $userId);
$requestsStmt->execute();
$requests = $requestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$requestsStmt->close();
$csrfToken = supportCsrfToken();
$conn->close();
require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading"><div><p class="eyebrow"><?= e(translate('staff_support')) ?></p><h1><?= e(translate('history_access_requests')) ?></h1></div><div class="dashboard-date"><i class="bi bi-shield-lock"></i></div></div>
<div class="row g-3">
<?php if (!$requests): ?><div class="col-12"><section class="card-panel text-muted"><?= e(translate('no_history_requests')) ?></section></div><?php endif; ?>
<?php foreach ($requests as $request): ?>
<div class="col-12"><section class="card-panel"><div class="d-flex justify-content-between flex-wrap gap-2"><div><h2 class="h5 mb-1"><?= e($request['ticket_number'] ?? '-') ?> · <?= e($request['conversation_title'] ?? '-') ?></h2><div class="text-muted small"><?= e($request['conversation_type'] === 'student' ? translate('student_support') : translate('staff_support')) ?> · <?= e($request['requester_name']) ?> · <?= e(date('d/m/Y H:i', strtotime($request['created_at']))) ?></div></div><span class="badge text-bg-<?= $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'approved' ? 'success' : 'secondary') ?>"><?= e(translate('history_' . $request['status'])) ?></span></div><p class="mt-3 mb-2"><?= e($request['reason']) ?></p>
<?php if ($request['status'] === 'pending'): ?>
<form method="post" action="<?= BASE_URL ?>/support-actions.php" class="row g-2"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="conversation_type" value="<?= e($request['conversation_type']) ?>"><input type="hidden" name="conversation_id" value="<?= (int) $request['conversation_id'] ?>"><input type="hidden" name="history_request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="decide_history"><div class="col-md-3"><select class="form-select" name="decision"><option value="approved"><?= e(translate('approve')) ?></option><option value="rejected"><?= e(translate('reject')) ?></option></select></div><div class="col-md-3"><select class="form-select" name="scope_type"><option value="all"><?= e(translate('all_history')) ?></option><option value="date_range"><?= e(translate('date_range')) ?></option><option value="message_range"><?= e(translate('message_range')) ?></option></select></div><div class="col-md-3"><input class="form-control" type="datetime-local" name="scope_start" aria-label="<?= e(translate('scope_start')) ?>"></div><div class="col-md-3"><input class="form-control" type="datetime-local" name="scope_end" aria-label="<?= e(translate('scope_end')) ?>"></div><div class="col-md-3"><input class="form-control" type="number" min="1" name="start_message_id" placeholder="<?= e(translate('first_message_id')) ?>"></div><div class="col-md-3"><input class="form-control" type="number" min="1" name="end_message_id" placeholder="<?= e(translate('last_message_id')) ?>"></div><div class="col-md-4"><input class="form-control" name="decision_note" maxlength="1000" placeholder="<?= e(translate('decision_note')) ?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><?= e(translate('save')) ?></button></div></form>
<?php else: ?><div class="small text-muted"><?= e($request['scope_type'] ? translate($request['scope_type']) : '') ?> · <?= e($request['decision_note'] ?? '') ?> · <?= e($request['decided_at'] ? date('d/m/Y H:i', strtotime($request['decided_at'])) : '') ?></div><?php endif; ?>
</section></div>
<?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

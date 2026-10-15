-- Additive Student Support collaboration, Staff Support, invitation, history-scope, and audit schema.
-- Requires support_system.sql or database.sql to have created support_tickets first.

CREATE TABLE IF NOT EXISTS staff_support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(32) NOT NULL UNIQUE,
  created_by INT NOT NULL,
  assigned_to INT DEFAULT NULL,
  assigned_at DATETIME DEFAULT NULL,
  related_student_id INT DEFAULT NULL,
  class_id INT DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  status ENUM('new','received','reviewing','waiting_student','waiting_teacher','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (related_student_id) REFERENCES students(id) ON DELETE SET NULL,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
  KEY idx_staff_support_creator (created_by, updated_at),
  KEY idx_staff_support_assignee (assigned_to, status, updated_at),
  KEY idx_staff_support_status (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_support_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  sender_id INT NOT NULL,
  message_text TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES staff_support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_staff_support_messages (ticket_id, created_at),
  KEY idx_staff_support_unread (ticket_id, sender_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_support_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  message_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES staff_support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (message_id) REFERENCES staff_support_messages(id) ON DELETE CASCADE,
  KEY idx_staff_support_attachment_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_support_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  status ENUM('new','received','reviewing','waiting_student','waiting_teacher','in_progress','resolved','closed') NOT NULL,
  changed_by INT NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(500) DEFAULT NULL,
  FOREIGN KEY (ticket_id) REFERENCES staff_support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY idx_staff_support_history (ticket_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_conversation_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_type ENUM('student','staff') NOT NULL,
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  participant_role VARCHAR(32) NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  history_floor_id BIGINT NOT NULL DEFAULT 0,
  left_at DATETIME DEFAULT NULL,
  added_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_support_participant (conversation_type, conversation_id, user_id),
  KEY idx_support_participant_user (user_id, left_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_invitations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_type ENUM('student','staff') NOT NULL,
  conversation_id INT NOT NULL,
  recipient_id INT NOT NULL,
  invited_by INT NOT NULL,
  invitation_type ENUM('normal','mandatory') NOT NULL DEFAULT 'normal',
  status ENUM('pending','accepted','declined','revoked','expired') NOT NULL DEFAULT 'pending',
  reason VARCHAR(1000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME DEFAULT NULL,
  joined_at DATETIME DEFAULT NULL,
  FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY idx_support_invitation_recipient (recipient_id, status, created_at),
  KEY idx_support_invitation_conversation (conversation_type, conversation_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_history_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_type ENUM('student','staff') NOT NULL,
  conversation_id INT NOT NULL,
  requester_id INT NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  scope_type ENUM('all','date_range','message_range') DEFAULT NULL,
  scope_start DATETIME DEFAULT NULL,
  scope_end DATETIME DEFAULT NULL,
  start_message_id INT DEFAULT NULL,
  end_message_id INT DEFAULT NULL,
  decided_by INT DEFAULT NULL,
  decision_note VARCHAR(1000) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME DEFAULT NULL,
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_support_history_request (conversation_type, conversation_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_access_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_type ENUM('student','staff') NOT NULL,
  conversation_id INT NOT NULL,
  actor_id INT NOT NULL,
  subject_user_id INT DEFAULT NULL,
  action VARCHAR(40) NOT NULL,
  reason VARCHAR(1000) DEFAULT NULL,
  invitation_id INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (invitation_id) REFERENCES support_invitations(id) ON DELETE SET NULL,
  KEY idx_support_audit_conversation (conversation_type, conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_sensitive_cases (
  ticket_id INT PRIMARY KEY,
  subject_user_id INT DEFAULT NULL,
  restricted TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safe upgrades for databases that installed an earlier collaboration draft.
ALTER TABLE staff_support_tickets ADD COLUMN IF NOT EXISTS related_student_id INT DEFAULT NULL;
ALTER TABLE staff_support_tickets ADD COLUMN IF NOT EXISTS class_id INT DEFAULT NULL;
ALTER TABLE staff_support_tickets ADD COLUMN IF NOT EXISTS assigned_at DATETIME DEFAULT NULL;
ALTER TABLE support_conversation_participants ADD COLUMN IF NOT EXISTS history_floor_id BIGINT NOT NULL DEFAULT 0;

INSERT IGNORE INTO support_conversation_participants (conversation_type, conversation_id, user_id, participant_role, joined_at, history_floor_id)
SELECT 'student', t.id, s.user_id, 'student', t.created_at, 0 FROM support_tickets t INNER JOIN students s ON s.id = t.student_id WHERE s.user_id IS NOT NULL
UNION ALL
SELECT 'student', t.id, t.recipient_user_id, t.recipient_role, t.created_at, 0 FROM support_tickets t
UNION ALL
SELECT 'student', sa.ticket_id, sa.assigned_to, r.name, sa.assigned_at, COALESCE((SELECT MAX(m.id) FROM support_messages m WHERE m.ticket_id = sa.ticket_id AND m.created_at < sa.assigned_at), 0) FROM support_assignments sa INNER JOIN users u ON u.id = sa.assigned_to INNER JOIN roles r ON r.id = u.role_id WHERE sa.id = (SELECT MAX(sa2.id) FROM support_assignments sa2 WHERE sa2.ticket_id = sa.ticket_id);

INSERT IGNORE INTO support_conversation_participants (conversation_type, conversation_id, user_id, participant_role, joined_at, history_floor_id)
SELECT 'staff', t.id, t.created_by, r.name, t.created_at, 0 FROM staff_support_tickets t INNER JOIN users u ON u.id = t.created_by INNER JOIN roles r ON r.id = u.role_id
UNION ALL
SELECT 'staff', t.id, t.assigned_to, r.name, COALESCE(t.assigned_at, t.updated_at), COALESCE((SELECT MAX(m.id) FROM staff_support_messages m WHERE m.ticket_id = t.id AND m.created_at < COALESCE(t.assigned_at, t.updated_at)), 0) FROM staff_support_tickets t INNER JOIN users u ON u.id = t.assigned_to INNER JOIN roles r ON r.id = u.role_id WHERE t.assigned_to IS NOT NULL;

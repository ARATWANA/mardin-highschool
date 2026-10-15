-- ============================================================
-- Mardin High School Database
-- Import this file into an empty or existing database
-- If tables already exist, they will be preserved
-- ============================================================

USE `mardin-highschool`;

SET FOREIGN_KEY_CHECKS = 0;

-- Remove old views if they exist
DROP VIEW IF EXISTS `student_subject_totals`;

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  username VARCHAR(100) UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  photo VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_name VARCHAR(150) NOT NULL,
  school_code VARCHAR(50),
  address VARCHAR(255),
  phone VARCHAR(50),
  email VARCHAR(150),
  logo_path VARCHAR(255) DEFAULT 'assets/images/school-logo.jpg',
  default_language VARCHAR(10) DEFAULT 'en',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  grade_level TINYINT NOT NULL,
  section_id INT NOT NULL,
  branch ENUM('Scientific','Literary') NOT NULL,
  class_teacher_id INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES sections(id),
  KEY idx_class_teacher (class_teacher_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE,
  teacher_code VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('Male','Female') NOT NULL,
  phone VARCHAR(50),
  specialty VARCHAR(150),
  hire_date DATE,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50) NOT NULL UNIQUE,
  user_id INT UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('Male','Female') NOT NULL,
  dob DATE,
  class_id INT NOT NULL,
  section_id INT NOT NULL,
  branch ENUM('Scientific','Literary') NOT NULL,
  phone VARCHAR(50),
  guardian_name VARCHAR(150),
  guardian_phone VARCHAR(50),
  address TEXT,
  enrollment_date DATE,
  photo VARCHAR(255),
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (section_id) REFERENCES sections(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name_en VARCHAR(150) NOT NULL,
  name_ku VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  description TEXT,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS curriculum_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  grade_level TINYINT NOT NULL,
  branch ENUM('Scientific','Literary') NOT NULL,
  academic_year VARCHAR(20) NOT NULL DEFAULT '2025-2026',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_curriculum (subject_id, grade_level, branch, academic_year),
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teacher_subject_class (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  curriculum_subject_id INT NOT NULL,
  class_id INT NOT NULL,
  section_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment (teacher_id, curriculum_subject_id, class_id, section_id),
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (curriculum_subject_id) REFERENCES curriculum_subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_monitors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  section_id INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_monitor_class (student_id, class_id, section_id, status),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  date DATE NOT NULL,
  status ENUM('Present','Absent','Late','Excused') NOT NULL,
  marked_by INT,
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_daily_attendance (student_id, date),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grade_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  curriculum_subject_id INT NOT NULL,
  subject_id INT NOT NULL,
  teacher_id INT NOT NULL,
  class_id INT NOT NULL,
  semester ENUM('first','second') NOT NULL,
  component ENUM('daily_1','midterm','daily_2','final') NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  max_score DECIMAL(5,2) NOT NULL,
  remarks VARCHAR(255),
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grade_component (student_id, curriculum_subject_id, semester, component),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (curriculum_subject_id) REFERENCES curriculum_subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE VIEW IF NOT EXISTS student_subject_totals AS
SELECT student_id, curriculum_subject_id, subject_id, semester,
       SUM(score) AS total_score, SUM(max_score) AS max_score,
       ROUND((SUM(score) / NULLIF(SUM(max_score), 0)) * 100, 2) AS percentage
FROM grade_entries GROUP BY student_id, curriculum_subject_id, subject_id, semester;

CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  teacher_id INT NOT NULL,
  note_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT,
  actor_role VARCHAR(50),
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NULL,
  room_type ENUM('global','class','direct') NOT NULL DEFAULT 'global',
  class_id INT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_message_reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  user_id INT NOT NULL,
  reaction VARCHAR(16) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_chat_reaction (message_id, user_id, reaction),
  FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(32) NOT NULL UNIQUE,
  student_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  recipient_role ENUM('teacher','admin') NOT NULL,
  subject_id INT DEFAULT NULL,
  class_id INT DEFAULT NULL,
  section_id INT DEFAULT NULL,
  request_type ENUM('question','problem','complaint') NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('new','received','reviewing','waiting_student','waiting_teacher','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
  KEY idx_support_student (student_id, updated_at),
  KEY idx_support_recipient (recipient_user_id, status, updated_at),
  KEY idx_support_status (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  sender_id INT NOT NULL,
  message_text TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_support_messages_ticket (ticket_id, created_at),
  KEY idx_support_messages_unread (ticket_id, is_read, sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  message_id INT DEFAULT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (message_id) REFERENCES support_messages(id) ON DELETE CASCADE,
  KEY idx_support_attachments_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  status ENUM('new','received','reviewing','waiting_student','waiting_teacher','in_progress','resolved','closed') NOT NULL,
  changed_by INT NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(500) DEFAULT NULL,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY idx_support_history_ticket (ticket_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  assigned_to INT NOT NULL,
  assigned_by INT NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
  KEY idx_support_assignment_ticket (ticket_id, assigned_at),
  KEY idx_support_assignment_user (assigned_to, assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

INSERT IGNORE INTO roles (name, description) VALUES
('admin','Full platform administrator'),
('teacher','Teacher account'),
('student','Student account'),
('class_monitor','Class monitor account');

INSERT IGNORE INTO users (full_name,email,username,password_hash,role_id) VALUES
('System Administrator','admin@mardin.edu','admin','$2y$10$2uMjaEvVHqj1lfue54hQOOI0HlJM72x7Qn6XpgZQIsfPQNElAfQAu',1),
('Teacher One','teacher1@mardin.edu','teacher1','$2y$10$d9hOlezuqGu2ZP85MILenOnzZTbILKJSO7wiXK1ZKJeWcmNIju.cS',2),
('Teacher Two','teacher2@mardin.edu','teacher2','$2y$10$d9hOlezuqGu2ZP85MILenOnzZTbILKJSO7wiXK1ZKJeWcmNIju.cS',2),
('Student One','student1@mardin.edu','student1','$2y$10$na3P4D2NOElZfPn6JT8VmuDCx.A/R1t4OHSfAQ6i5qw6WW.VLXB/y',3),
('Student Two','student2@mardin.edu','student2','$2y$10$na3P4D2NOElZfPn6JT8VmuDCx.A/R1t4OHSfAQ6i5qw6WW.VLXB/y',3),
('Student Three','student3@mardin.edu','student3','$2y$10$na3P4D2NOElZfPn6JT8VmuDCx.A/R1t4OHSfAQ6i5qw6WW.VLXB/y',3),
('Monitor One','monitor1@mardin.edu','monitor1','$2y$10$TVsrKuNGQmR52AqYAPVGx.nGERfnehA7NnA2k6PHU.NAAyk9rwJ6.',4);

INSERT IGNORE INTO school_settings (school_name,school_code,address,phone,email,logo_path,default_language) VALUES
('Mardin High School','MHS-001','Sulaymaniyah, Chwar Chra','+964 750 000 0000','info@mardin.edu','assets/images/school-logo.jpg','en');

INSERT IGNORE INTO sections (name) VALUES ('A'),('B'),('C'),('D'),('E'),('F'),('G'),('H');

INSERT IGNORE INTO classes (name,grade_level,section_id,branch,status) VALUES
('Grade 10-A',10,1,'Scientific','active'),
('Grade 10-B',10,2,'Scientific','active'),
('Grade 11-A',11,1,'Scientific','active'),
('Grade 11-B',11,2,'Scientific','active'),
('Grade 12-A',12,1,'Scientific','active'),
('Grade 12-B',12,2,'Scientific','active'),
('Grade 10-C',10,3,'Literary','active'),
('Grade 11-C',11,3,'Literary','active'),
('Grade 12-C',12,3,'Literary','active');

INSERT IGNORE INTO teachers (user_id,teacher_code,full_name,gender,phone,specialty,hire_date) VALUES
(2,'TCH-001','Abdullah Karim','Male','+964 770 111 2222','Mathematics','2020-09-01'),
(3,'TCH-002','Sara Hassan','Female','+964 770 222 3333','Physics','2019-09-01'),
(4,'TCH-003','Omar Ali','Male','+964 770 333 4444','Chemistry','2021-09-01');

INSERT IGNORE INTO subjects (code,name_en,name_ku,name_ar,description) VALUES
('KUR','Kurdish Language','کوردی','اللغة الكردية','Kurdish language and literature'),
('ARB','Arabic Language','عەرەبی','اللغة العربية','Arabic language'),
('ENG','English Language','ئینگلیزی','اللغة الإنجليزية','English language'),
('REL','Religion','ئایین','التربية الدينية','Religion'),
('MAT','Mathematics','بیرکاری','الرياضيات','Mathematics'),
('PHY','Physics','فیزیا','الفيزياء','Physics'),
('CHE','Chemistry','کیمیا','الكيمياء','Chemistry'),
('BIO','Biology','زیندەزانی','الأحياء','Biology'),
('HIS','History','مێژوو','التاريخ','History'),
('GEO','Geography','جوگرافیا','الجغرافيا','Geography'),
('ECO','Economics','ئابووری','الاقتصاد','Economics'),
('PHI','Philosophy','فەلسەفە','الفلسفة','Philosophy'),
('ICT','Computer Science','کۆمپیوتەر','علوم الحاسوب','Computer science'),
('PE','Physical Education','پەروەردە','التربية الرياضية','Physical education');

INSERT IGNORE INTO curriculum_subjects (subject_id,grade_level,branch) SELECT id,10,'Scientific' FROM subjects WHERE code IN ('KUR','ARB','ENG','REL','MAT','PHY','CHE','BIO','ICT','PE');
INSERT IGNORE INTO curriculum_subjects (subject_id,grade_level,branch) SELECT id,11,'Scientific' FROM subjects WHERE code IN ('KUR','ARB','ENG','REL','MAT','PHY','CHE','BIO','ICT','PE');
INSERT IGNORE INTO curriculum_subjects (subject_id,grade_level,branch) SELECT id,12,'Scientific' FROM subjects WHERE code IN ('KUR','ARB','ENG','REL','MAT','PHY','CHE','BIO','ICT','PE');
INSERT IGNORE INTO curriculum_subjects (subject_id,grade_level,branch) SELECT id,10,'Literary' FROM subjects WHERE code IN ('KUR','ARB','ENG','REL','MAT','HIS','GEO','ECO','PHI','ICT','PE');
INSERT IGNORE INTO curriculum_subjects (subject_id,grade_level,branch) SELECT id,11,'Literary' FROM subjects WHERE code IN ('KUR','ARB','ENG','REL','MAT','HIS','GEO','ECO','PHI','ICT','PE');
INSERT IGNORE INTO curriculum_subjects (subject_id,grade_level,branch) SELECT id,12,'Literary' FROM subjects WHERE code IN ('KUR','ARB','ENG','REL','MAT','HIS','GEO','ECO','PHI','ICT','PE');

INSERT IGNORE INTO teacher_subject_class (teacher_id,curriculum_subject_id,class_id,section_id)
SELECT 1, cs.id, c.id, s.id FROM curriculum_subjects cs CROSS JOIN classes c CROSS JOIN sections s WHERE c.grade_level=10 AND c.branch='Scientific' AND cs.grade_level=10 AND cs.branch='Scientific' LIMIT 20;

INSERT IGNORE INTO students (student_id,user_id,full_name,gender,dob,class_id,section_id,branch,phone,guardian_name,guardian_phone,address,enrollment_date,status) VALUES
('STU-2024-001',5,'Rami Saleh','Male','2009-05-15',1,1,'Scientific','+964 750 333 4444','Saleh Hassan','+964 750 111 2222','Sulaymaniyah, Chwar Chra','2024-09-01','active'),
('STU-2024-002',NULL,'Dara Rashid','Male','2010-01-08',1,1,'Scientific','+964 750 555 6666','Rashid Saber','+964 750 777 8888','Sulaymaniyah','2024-09-01','active'),
('STU-2024-003',6,'Sara Ali','Female','2008-12-18',2,2,'Scientific','+964 750 999 0000','Ali Faris','+964 750 212 3434','Sulaymaniyah','2023-09-01','active'),
('STU-2024-004',NULL,'Ahmed Karim','Male','2009-03-22',3,1,'Scientific','+964 750 123 4567','Karim Ahmed','+964 750 234 5678','Sulaymaniyah','2024-09-01','active'),
('STU-2024-005',NULL,'Fatima Hassan','Female','2010-07-10',4,1,'Scientific','+964 750 234 5678','Hassan Fatima','+964 750 345 6789','Chwar Chra','2024-09-01','active'),
('STU-2024-006',7,'Omar Omar','Male','2009-11-05',5,1,'Scientific','+964 750 345 6789','Omar Said','+964 750 456 7890','Sulaymaniyah','2023-09-01','active'),
('STU-2024-007',NULL,'Nadia Ahmed','Female','2010-02-28',6,1,'Scientific','+964 750 456 7890','Ahmed Nadia','+964 750 567 8901','Chwar Chra','2024-09-01','active'),
('STU-2024-008',NULL,'Hussein Karim','Male','2009-06-15',7,3,'Literary','+964 750 567 8901','Karim Hussein','+964 750 678 9012','Sulaymaniyah','2024-09-01','active'),
('STU-2024-009',NULL,'Zara Ahmed','Female','2010-09-20',8,3,'Literary','+964 750 678 9012','Ahmed Zara','+964 750 789 0123','Sulaymaniyah','2024-09-01','active'),
('STU-2024-010',NULL,'Bilal Hassan','Male','2009-04-12',9,3,'Literary','+964 750 789 0123','Hassan Bilal','+964 750 890 1234','Chwar Chra','2023-09-01','active');

INSERT IGNORE INTO class_monitors (student_id,class_id,section_id) VALUES (1,1,1);

INSERT IGNORE INTO attendance (student_id,class_id,date,status,marked_by,notes) VALUES
(1,1,CURDATE(),'Present',2,'On time'),
(2,1,CURDATE(),'Late',2,'20 minutes late'),
(3,2,CURDATE(),'Excused',2,'Family emergency'),
(4,3,CURDATE(),'Present',2,'On time'),
(5,4,CURDATE(),'Absent',2,'Sick leave'),
(1,1,'2026-09-14','Present',2,'On time'),
(2,1,'2026-09-14','Present',2,'On time'),
(3,2,'2026-09-14','Present',2,'On time'),
(4,3,'2026-09-14','Present',2,'On time'),
(5,4,'2026-09-14','Late',2,'10 min late');

INSERT IGNORE INTO grade_entries (student_id,curriculum_subject_id,subject_id,teacher_id,class_id,semester,component,score,max_score,remarks) VALUES
(1,1,1,1,1,'first','daily_1',8,10,'Good work'),
(1,1,1,1,1,'first','midterm',16,20,'Good'),
(1,1,1,1,1,'first','daily_2',9,10,'Excellent'),
(1,1,1,1,1,'first','final',45,60,'Very good'),
(1,2,2,1,1,'first','daily_1',7,10,'OK'),
(1,2,2,1,1,'first','midterm',14,20,'Good'),
(1,2,2,1,1,'first','daily_2',8,10,'Good'),
(1,2,2,1,1,'first','final',42,60,'Good'),
(2,1,1,1,1,'first','daily_1',9,10,'Excellent'),
(2,1,1,1,1,'first','midterm',18,20,'Excellent'),
(2,1,1,1,1,'first','daily_2',9,10,'Good'),
(2,1,1,1,1,'first','final',50,60,'Excellent'),
(1,1,1,1,1,'second','daily_1',7,10,'Good'),
(1,1,1,1,1,'second','midterm',15,20,'Good'),
(1,1,1,1,1,'second','daily_2',8,10,'OK'),
(1,1,1,1,1,'second','final',40,60,'Good'),
(1,2,2,1,1,'second','daily_1',8,10,'Good'),
(1,2,2,1,1,'second','midterm',17,20,'Very good'),
(1,2,2,1,1,'second','daily_2',9,10,'Good'),
(1,2,2,1,1,'second','final',48,60,'Very good');

INSERT IGNORE INTO announcements (title,content,created_by) VALUES
('School Opening','Welcome to the new academic year 2025-2026. All students should be in their classrooms by 8:00 AM.',1),
('Exam Schedule','Midterm exams will begin next week. Please check your exam schedule posted on the bulletin board.',1),
('Holiday Notice','The school will be closed on Friday for religious observance.',1);

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

INSERT IGNORE INTO activity_log (actor_id,actor_role,description) VALUES
(1,'admin','System initialized and setup completed'),
(2,'teacher','Teacher assigned to classes'),
(5,'student','Student enrolled in Grade 10-A'),
(1,'admin','Announcement created: School Opening');

INSERT IGNORE INTO chat_messages (sender_id, receiver_id, room_type, class_id, message, created_at) VALUES
(2, 5, 'direct', NULL, 'Hello Rami, how are you?', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 2, 'direct', NULL, 'I am fine, thank you Mr. Karim!', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- ============================================================
-- HOMEWORK SYSTEM TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS homeworks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  class_id INT NOT NULL,
  subject_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  due_date DATETIME NOT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS homework_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  homework_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(50) NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS homework_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  homework_id INT NOT NULL,
  student_id INT NOT NULL,
  submission_text TEXT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','graded','late') NOT NULL DEFAULT 'pending',
  teacher_notes TEXT,
  FOREIGN KEY (homework_id) REFERENCES homeworks(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uq_submission (homework_id, student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(50) NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES homework_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- MESSENGER CHAT SYSTEM TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  is_group TINYINT(1) NOT NULL DEFAULT 0,
  group_name VARCHAR(255) DEFAULT NULL,
  group_image VARCHAR(500) DEFAULT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversation_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  role ENUM('admin','member') NOT NULL DEFAULT 'member',
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_member (conversation_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  message_text TEXT,
  message_type ENUM('text','voice','photo','video','file') NOT NULL DEFAULT 'text',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS message_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(50) NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voice_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  duration_seconds INT NOT NULL DEFAULT 0,
  file_path VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- QUIZ SYSTEM TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  subject_id INT NOT NULL,
  class_id INT NOT NULL,
  section_id INT DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  time_limit INT NOT NULL DEFAULT 0 COMMENT 'minutes',
  total_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
  passing_mark DECIMAL(8,2) NOT NULL DEFAULT 50,
  max_attempts INT NOT NULL DEFAULT 1,
  randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
  randomize_options TINYINT(1) NOT NULL DEFAULT 0,
  show_results_immediately TINYINT(1) NOT NULL DEFAULT 1,
  show_correct_answers TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question_text TEXT NOT NULL,
  question_image VARCHAR(500) DEFAULT NULL,
  question_type ENUM('mcq','tf','short') NOT NULL DEFAULT 'mcq',
  marks DECIMAL(8,2) NOT NULL DEFAULT 1,
  correct_answer TEXT,
  expected_answer TEXT,
  allow_manual_grade TINYINT(1) NOT NULL DEFAULT 0,
  order_index INT NOT NULL DEFAULT 0,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  option_text VARCHAR(500) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  order_index INT NOT NULL DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  student_id INT NOT NULL,
  attempt_number INT NOT NULL DEFAULT 1,
  start_time DATETIME NOT NULL,
  end_time DATETIME DEFAULT NULL,
  total_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
  obtained_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
  percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  time_used INT NOT NULL DEFAULT 0 COMMENT 'seconds',
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uq_attempt (quiz_id, student_id, attempt_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT DEFAULT NULL,
  answer_text TEXT,
  is_correct TINYINT(1) DEFAULT NULL,
  marks_earned DECIMAL(8,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
  FOREIGN KEY (selected_option_id) REFERENCES quiz_options(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  quiz_id INT NOT NULL,
  student_id INT NOT NULL,
  score DECIMAL(8,2) NOT NULL DEFAULT 0,
  percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  correct_count INT NOT NULL DEFAULT 0,
  wrong_count INT NOT NULL DEFAULT 0,
  pass_fail ENUM('pass','fail') NOT NULL DEFAULT 'fail',
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  reviewed_by INT DEFAULT NULL,
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

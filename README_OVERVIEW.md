# Mardin High School - Complete School Management Platform

## Overview

A complete PHP + MySQL + Bootstrap school management platform designed for **Mardin High School** in **Sulaymaniyah, Chwar Chra**, Kurdistan. The system supports three languages (English, Kurdish Sorani, Arabic), four user roles, and all core school management features.

## Project Structure

```
mardin-highschool/
├── admin/                     # Admin panel pages
│   ├── dashboard.php          # Admin dashboard with statistics
│   ├── students.php           # Student CRUD management
│   ├── teachers.php           # Teacher CRUD management
│   ├── classes.php            # Class/section management + assignments
│   ├── subjects.php           # Subject management + curriculum
│   ├── attendance.php         # Admin attendance records
│   ├── grades.php             # Grade entry and management
│   ├── announcements.php      # School announcements (NEW)
│   ├── users.php              # User account management
│   └── settings.php           # School settings + profile links
├── teacher/                   # Teacher portal
│   ├── dashboard.php          # Teacher dashboard
│   ├── students.php           # View assigned students
│   ├── attendance.php         # Record + view attendance
│   └── grades.php             # Enter + edit grades
├── student/                   # Student portal
│   ├── dashboard.php          # Student dashboard
│   ├── grades.php             # View grades & history
│   ├── attendance.php         # View attendance
│   ├── subjects.php           # View subjects
│   └── teachers.php           # View teachers
├── monitor/                   # Class monitor portal
│   ├── dashboard.php          # Monitor dashboard
│   ├── class.php              # Class info + members
│   └── attendance.php         # View class attendance
├── includes/                  # Core system files
│   ├── config.php             # Config, sessions, language system
│   ├── db.php                 # Database connection
│   ├── auth.php               # Auth & authorization
│   ├── functions.php          # Translations, helpers, DB queries
│   ├── header.php             # Sidebar + topbar layout
│   └── footer.php             # Page footer + localization
├── assets/
│   ├── css/style.css          # Complete stylesheet (responsive, RTL)
│   ├── js/app.js              # Language, sidebar, mobile toggle
│   └── images/school-logo.jpg # School logo (103KB)
├── chat.php                   # Chat system (all roles)
├── search.php                 # Search system (all roles)
├── profile.php                # Profile + password change (NEW)
├── login.php                  # Login page
├── logout.php                 # Logout handler
├── index.php                  # Redirect to login/dashboard
├── database.sql               # Complete database schema + data
├── XAMPP_SETUP.md             # Setup instructions
├── TEST_REPORT.md             # Testing checklist
└── README.md                  # This file
```

## Default Accounts

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@mardin.edu | Admin@123 |
| **Teacher 1** | teacher1@mardin.edu | Teacher@123 |
| **Teacher 2** | teacher2@mardin.edu | Teacher@123 |
| **Student 1** | student1@mardin.edu | Student@123 |
| **Student 2** | student2@mardin.edu | Student@123 |
| **Monitor 1** | monitor1@mardin.edu | Monitor@123 |

## Features by Role

### 👨‍💼 Admin
- Full CRUD: Students, Teachers, Classes, Subjects
- Attendance recording & viewing
- Grade entry, editing & management
- Class monitor & teacher assignments
- Announcements management
- User management
- School settings (name, address, logo, default language)
- Profile & password change
- Dashboard with statistics
- Search (students, teachers, subjects)
- Chat (all rooms)

### 👨‍🏫 Teacher
- Dashboard showing assigned classes, students, subjects, attendance
- View assigned students
- Record attendance (Present/Absent/Late/Excused)
- Enter grades (daily/midterm/final by semester)
- Edit own grades
- View grade history
- Chat (direct with students/teachers, class groups, global)
- Profile & password change

### 👨‍🎓 Student
- Dashboard with personal info, latest grades, attendance
- View grades & grade history (with auto-calculated totals)
- View attendance history
- View assigned subjects (by grade/branch)
- View teachers
- Chat (direct with teacher, class groups, global)
- Profile & password change

### 🧑‍💼 Class Monitor
- Dashboard with class info & class members
- View class attendance
- Class information page (students, details)
- Chat (direct with teacher/admin, class groups, global)
- Profile & password change
- Cannot access admin/teacher panels

## Technology Stack

- **PHP 7.4+** with MySQLi extension
- **MySQL 5.7+** / MariaDB
- **Bootstrap 5.3** (CDN)
- **Bootstrap Icons** (CDN)
- **HTML5/CSS3/JavaScript** (vanilla)
- **XAMPP** for local development

## Database Schema

24 tables with proper foreign keys and relationships:

- `roles`, `users`, `school_settings`, `sections`, `classes`
- `teachers`, `students`, `subjects`, `curriculum_subjects`
- `teacher_subject_class`, `class_monitors`, `attendance`
- `grade_entries` (with `student_subject_totals` view)
- `notes`, `announcements`, `activity_log`, `chat_messages`

## Key Design Decisions

1. **Language System**: Session + cookie persistence. Supports ENG (LTR), KU (RTL), AR (RTL). The `localizePageHtml()` function in footer.php translates hardcoded English text across all pages.
2. **Marks System**: Each grade has 4 components per semester (daily_1=10, midterm=20, daily_2=10, final=60) = 100 marks per semester. Both semesters stored separately.
3. **Attendance**: Daily tracking with unique constraint per student per date. Supports Present/Absent/Late/Excused statuses.
4. **Security**: All queries use prepared statements. Passwords hashed with bcrypt. Sessions managed server-side. Output escaped with `htmlspecialchars()`.
5. **Responsive**: Bootstrap 5 grid with custom breakpoints at 992px (laptop/tablet) and 575px (mobile). Mobile sidebar with hamburger toggle.
6. **Branding**: School logo in sidebar (`assets/images/school-logo.jpg`), consistent across all pages.

## XAMPP Setup (Quick)

```
1. Copy mardin-highschool/ to C:\xampp\htdocs\
2. Start Apache & MySQL in XAMPP
3. Import database.sql in phpMyAdmin
4. Open http://localhost/mardin-highschool/
5. Login with default accounts above
6. Change all passwords immediately!
```

See `XAMPP_SETUP.md` for complete instructions.

## Files Changed in Latest Update

- `includes/config.php` — Added English and Arabic to supported languages
- `includes/functions.php` — Complete translations for EN/AR/KU, fixed bind_param bug
- `includes/header.php` — Added announcements nav, mobile toggle, 3-language switcher
- `includes/footer.php` — Proper localization output
- `includes/auth.php` — Added role-checking improvements
- `login.php` — Full multilingual login page
- `logout.php` — Proper session cleanup
- `profile.php` — Profile display + password change (NEW)
- `admin/announcements.php` — Announcement CRUD (NEW)
- `admin/dashboard.php` — Full translations, quick actions, profile links
- `admin/settings.php` — Profile/password links
- `admin/students.php` — Fixed bind_param types
- `admin/teachers.php` — Fixed delete query (prepared statement)
- `admin/grades.php` — Minor improvements
- `teacher/dashboard.php` — Full dashboard with statistics
- `teacher/grades.php` — Fixed bind_param bug (ssddsi → ssddsii)
- `student/dashboard.php` — Translated labels
- `student/grades.php` — Language-aware subject names, fixed structure
- `student/subjects.php` — Translated labels, language-aware subject display
- `student/attendance.php` — Translated labels, badge styling
- `monitor/dashboard.php` — Full dashboard with class data
- `monitor/class.php` — Translated labels
- `monitor/attendance.php` — Translated labels, badge styling
- `assets/css/style.css` — Responsive redesign, mobile sidebar, fixed overlaps, RTL fixes, animations
- `assets/js/app.js` — Mobile toggle, improved language persistence
- `database.sql` — Complete schema with comprehensive sample data (30+ records)
- `XAMPP_SETUP.md` — Complete setup instructions (NEW)
- `TEST_REPORT.md` — Testing checklist (NEW)
- `index.php` — Proper redirect

## Known Limitations

- Some admin pages still have mixed Kurdish/English labels in form placeholders (the system uses `localizePageHtml()` in footer.php which translates English hardcoded text; pure Kurdish labels are source text and remain as-is for the Kurdish language setting)
- Announcement emails are not sent (information stored only in database)
- Password reset via email not implemented (manual password change only)

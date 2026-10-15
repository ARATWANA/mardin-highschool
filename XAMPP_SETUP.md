# Mardin High School - School Management Platform

## Complete XAMPP Setup Instructions

### Prerequisites
- XAMPP installed (Apache + MySQL)
- PHP 7.4+ with MySQLi extension enabled
- MySQL/MariaDB running

### Step 1: Place Project Files
1. Copy the entire `mardin-highschool` folder to `C:\xampp\htdocs\mardin-highschool\`
2. Ensure `assets/images/school-logo.jpg` exists (already included)

### Step 2: Start Apache and MySQL
1. Open XAMPP Control Panel
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**
4. Verify both services are running (green indicators)

### Step 3: Import Database
1. Open your browser and go to `http://localhost/phpmyadmin`
2. In phpMyAdmin:
   - Click **Import** tab at the top
   - Click **Choose File** and select `C:\xampp\htdocs\mardin-highschool\database.sql`
   - Make sure **SQL** format is selected
   - Click **Go**
3. Alternatively, use MySQL command:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\mardin-highschool\database.sql
   ```
4. Verify database `MARDIN_HIGHSCHOOL` exists with all tables

### Step 4: Verify Configuration
1. Open `C:\xampp\htdocs\mardin-highschool\includes\config.php`
2. Verify these settings match your XAMPP setup:
   ```php
   const DB_HOST = 'localhost';
   const DB_USER = 'root';
   const DB_PASS = '';
   const DB_NAME = 'MARDIN_HIGHSCHOOL';
   ```
3. If you set a MySQL password in XAMPP, update `DB_PASS` accordingly

### Step 5: Open the Website
1. Open browser and navigate to `http://localhost/mardin-highschool/`
2. You will be redirected to the login page

### Step 6: Login with Default Accounts

**Admin:**
- Email: `admin@mardin.edu`
- Password: `Admin@123`

**Teacher:**
- Email: `teacher1@mardin.edu`
- Password: `Teacher@123`

**Student:**
- Email: `student1@mardin.edu`
- Password: `Student@123`

**Class Monitor:**
- Email: `monitor1@mardin.edu`
- Password: `Monitor@123`

### Step 7: Change Default Passwords (IMPORTANT)
After logging in, change all default passwords immediately:
1. Go to **Admin** → **Users**
2. Click **Edit** on each user
3. Set a strong, unique password
4. Save

### Step 8: Test Language Switching
Click **ENG**, **KU**, or **AR** in the top bar to switch languages:
- **English**: Left-to-Right (LTR) layout
- **Kurdish Sorani**: Right-to-Left (RTL) layout
- **Arabic**: Right-to-Left (RTL) layout

### Troubleshooting

**"Database connection failed"**
- Make sure MySQL is running in XAMPP
- Check `DB_HOST`, `DB_USER`, `DB_PASS` in `config.php`
- Verify database `MARDIN_HIGHSCHOOL` exists in phpMyAdmin

**Blank page**
- Check Apache error logs in XAMPP
- Enable PHP error reporting in `config.php`:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```

**Login doesn't work**
- Verify the database was imported correctly
- Check that `users` table has data
- Verify password hashes exist (should start with `$2y$`)

**Styles not loading**
- Hard refresh: `Ctrl + F5`
- Check file paths in `header.php` match your setup

## Database Structure

The `MARDIN_HIGHSCHOOL` database includes:

| Table | Purpose |
|-------|---------|
| `roles` | User roles (admin, teacher, student, class_monitor) |
| `users` | All user accounts with hashed passwords |
| `school_settings` | School name, address, logo, language |
| `sections` | Class sections (A-Z) |
| `classes` | Grade 10-12 classes with branch (Scientific/Literary) |
| `teachers` | Teacher records linked to users |
| `students` | Student records linked to users |
| `subjects` | Subject names in EN/KU/AR |
| `curriculum_subjects` | Subject assignments to grade/branch |
| `teacher_subject_class` | Teacher-class-subject assignments |
| `class_monitors` | Class monitor assignments |
| `attendance` | Daily attendance records |
| `grade_entries` | Marks by semester/component |
| `announcements` | School announcements |
| `activity_log` | System activity tracking |
| `chat_messages` | Chat messages between users |

## Default Data Included

- 1 Admin, 2 Teachers, 2 Students, 1 Class Monitor
- 9 Classes (Grades 10-12, Scientific & Literary)
- 14 Subjects (Kurdish, Arabic, English, Religion, Math, Physics, Chemistry, Biology, History, Geography, Economics, Philosophy, Computer Science, PE)
- Sample attendance records
- Sample grades for 2 students across both semesters
- 3 Announcements
- 1 Class Monitor assignment

## Language System

The platform supports three languages:
- **English** (ENG) - LTR layout
- **Kurdish Sorani** (KU) - RTL layout
- **Arabic** (AR) - RTL layout

Language preference is saved in:
1. Session (persists during browsing)
2. Cookie (persists across visits)

## Security Features

- Password hashing with `password_hash()` (bcrypt)
- Prepared SQL statements throughout
- Session-based authentication
- Role-based access control
- Output escaping with `htmlspecialchars()`
- CSRF protection via session tokens (addable)

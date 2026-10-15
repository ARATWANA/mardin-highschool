# Mardin High School

A complete PHP + MySQL school management platform designed for XAMPP and optimized for English, Kurdish Sorani (RTL), and Arabic (RTL).

School location: Sulaymaniyah, Chwar Chra.

## Adding the school logo

1. Create the folder `assets/images` if it does not exist.
2. Rename the logo image to `school-logo.jpg`.
3. Copy it to `C:/xampp/htdocs/mardin-highschool/assets/images/school-logo.jpg`.
4. Refresh the dashboard with `Ctrl + F5`.

The sidebar loads this image automatically. If it is missing, the `MHS` fallback mark is shown. For a PNG file, change `school-logo.jpg` to `school-logo.png` in `includes/header.php`.

## Project structure

- `admin/` – administrative management panels
- `teacher/` – teacher dashboards and actions
- `student/` – student portal
- `monitor/` – class monitor area
- `includes/` – config, DB, auth, and reusable layout helpers
- `assets/` – CSS and JavaScript
- `database.sql` – full schema and seed data

## First administrator setup

Use the secure bootstrap page to create the first admin account before you log in:

1. Copy the project into `C:/xampp/htdocs/mardin-highschool`.
2. Start Apache and MySQL in XAMPP.
3. Import `database.sql` into MySQL using phpMyAdmin or the MySQL shell.
4. Open `http://localhost/mardin-highschool/setup_admin.php`.
5. Create a strong admin email and password, then sign in at `http://localhost/mardin-highschool/login.php`.

Do not commit or expose production credentials in source code. Keep the admin password unique and strong for your local environment.

## XAMPP setup

1. Place this project inside `C:/xampp/htdocs/mardin-highschool`.
2. Start Apache and MySQL in XAMPP.
3. Import `database.sql` into MySQL using phpMyAdmin or the MySQL shell.
4. Open `http://localhost/mardin-highschool/setup_admin.php`.
5. Create the first admin account securely.
6. Log in at `http://localhost/mardin-highschool/login.php` with the newly created admin account.

The supplied `database.sql` creates and uses the database `MARDIN_HIGHSCHOOL`. It drops and recreates the application tables when imported, so export any existing school data before re-importing it.

## Changing the default password

After logging in as admin:

1. Go to the admin users page.
2. Click Edit on the admin user.
3. Update the password field.
4. Save.

You can also manually update the password in MySQL with:

```sql
UPDATE users SET password_hash = '$2y$10$6PqM2G3jFGQZRocrt0S3zO9yXh8YIKwWg2B4lCxv0JQb1DlKX7fSC' WHERE email = 'admin@mardin.edu';
```

Replace the hash with one generated via PHP:

```php
<?php echo password_hash('YourNewPassword', PASSWORD_DEFAULT); ?>
```

## Database structure

The database `MARDIN_HIGHSCHOOL` includes tables for:

- roles
- users
- school_settings
- classes
- sections
- students
- teachers
- subjects
- curriculum_subjects (grade and Scientific/Literary assignment)
- teacher_subject_class
- class_monitors
- attendance
- grades
- grade_entries (semester/component marks)
- announcements
- activity_log
- notes

This supports school structure, user access, student records, attendance, grades, notes, and announcements.

## Kurdistan grading model

`grade_entries` keeps each semester and component separate:

- `daily_1`: 10
- `midterm`: 20
- `daily_2`: 10
- `final`: 60

Each semester totals 100. The `student_subject_totals` view calculates totals without mixing first and second semester entries.

## Curriculum note

The seed catalog contains the requested Kurdish, Arabic, English, Religion, Mathematics, Physics, Chemistry, Biology, History, Geography, Economics, Philosophy/Logic, Computer Science, and Physical Education subjects with separate grade/branch rows. The Ministry website was not machine-readable in this environment, so the seed is intentionally editable in the database and must be confirmed against the current Ministry textbook list before production use.

## Adding more languages later

The platform already supports language switching with the `lang` session variable and the translation map in `includes/functions.php`.

To add a language:

1. Add the language code to `SUPPORTED_LANGUAGES` in `includes/config.php`.
2. Add the key translations in the `translate()` function in `includes/functions.php`.
3. Add the language label in the UI language switcher.
4. Use `dir="rtl"` for right-to-left languages.

## Security notes

- Passwords are hashed with `password_hash()` and verified with `password_verify()`.
- Queries use prepared statements.
- Sessions are used for login state.
- Only authorized roles can access protected page groups.

## Main design features

- Responsive Bootstrap layout
- Sidebar navigation
- Card-based dashboard tiles
- Table-based management screens
- Search and filters
- RTL support for Kurdish and Arabic
- XAMPP-friendly PHP/MySQL setup

## License

This project is provided as a working foundation for a school management system and can be extended for production deployment.

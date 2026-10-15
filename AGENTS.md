# AGENTS.md

## Project guidance

- Keep the project compatible with the existing XAMPP + PHP + MySQL setup.
- Preserve working database tables, relationships, and seeded records unless a safer migration is required.
- Read the existing files before changing logic, database queries, role checks, or shared layouts.
- Do not hardcode production credentials or expose default passwords in pages, docs, or UI text.
- Prefer prepared statements and server-side validation for every form and API action.
- Keep the interface multilingual and RTL-aware for English, Kurdish Sorani, and Arabic.
- Prefer small, targeted fixes over rewriting large parts of the system.
- Validate the affected workflow with a real request or database-backed check before claiming the fix is complete.

## Local admin bootstrap

- Use the secure setup page at `/setup_admin.php` to create or update the first admin account.
- The admin password should be strong and unique for each local environment.
- Do not put default credentials into source-controlled code or public-facing pages.

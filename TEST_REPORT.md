# Mardin High School - School Management Platform

## Testing Report

### Test Date: September 2026

---

## 1. Login Tests

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Admin login with admin@mardin.edu / Admin@123 | Redirect to dashboard | - | TO TEST |
| Teacher login with teacher1@mardin.edu / Teacher@123 | Redirect to dashboard | - | TO TEST |
| Student login with student1@mardin.edu / Student@123 | Redirect to dashboard | - | TO TEST |
| Monitor login with monitor1@mardin.edu / Monitor@123 | Redirect to dashboard | - | TO TEST |
| Invalid email | Error message shown | - | TO TEST |
| Invalid password | Error message shown | - | TO TEST |
| Empty fields | Required field error | - | TO TEST |
| Logout | Redirect to login | - | TO TEST |

---

## 2. Student Management (Admin)

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Add new student | Saved to database | - | TO TEST |
| Edit student | Updated in database | - | TO TEST |
| Delete student | Removed from database | - | TO TEST |
| Search students | Filtered results | - | TO TEST |
| View student profile | Student details shown | - | TO TEST |

---

## 3. Teacher Management (Admin)

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Add new teacher | Saved to database with user account | - | TO TEST |
| Edit teacher | Updated in database | - | TO TEST |
| Delete teacher | Removed from database | - | TO TEST |
| Search teachers | Filtered results | - | TO TEST |

---

## 4. Class Management (Admin)

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Create class Grade 10-A Scientific | Saved to database | - | TO TEST |
| Edit class | Updated in database | - | TO TEST |
| Delete class | Removed from database | - | TO TEST |
| Assign teacher to class | Saved to teacher_subject_class | - | TO TEST |
| Assign class monitor | Saved to class_monitors | - | TO TEST |

---

## 5. Subject Management (Admin)

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Add subject with EN/KU/AR names | Saved to database | - | TO TEST |
| Edit subject | Updated in database | - | TO TEST |
| Toggle subject active/inactive | Status changed | - | TO TEST |
| Assign subject to grade/branch | Saved to curriculum_subjects | - | TO TEST |
| Kurdistan subjects for Sci/Lit | Correct subjects per grade/branch | - | TO TEST |

---

## 6. Grade/Marks System

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Enter daily_1 mark (max 10) | Saved, total calculated | - | TO TEST |
| Enter midterm mark (max 20) | Saved, total calculated | - | TO TEST |
| Enter daily_2 mark (max 10) | Saved, total calculated | - | TO TEST |
| Enter final mark (max 60) | Saved, total calculated | - | TO TEST |
| First semester total = 100 | Auto-calculated correctly | - | TO TEST |
| Second semester total = 100 | Auto-calculated correctly | - | TO TEST |
| Grades visible to student | Shows in student portal | - | TO TEST |
| Grades visible to teacher | Shows in teacher portal | - | TO TEST |

---

## 7. Attendance System

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Mark Present | Saved with status Present | - | TO TEST |
| Mark Absent | Saved with status Absent | - | TO TEST |
| Mark Late | Saved with status Late | - | TO TEST |
| Mark Excused | Saved with status Excused | - | TO TEST |
| Student views own attendance | Shows in student portal | - | TO TEST |
| Teacher views class attendance | Shows in teacher portal | - | TO TEST |
| Admin views all attendance | Shows in admin portal | - | TO TEST |
| Monitor views class attendance | Shows in monitor portal | - | TO TEST |

---

## 8. Language System

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Switch to English (ENG) | All text in English, LTR | - | TO TEST |
| Switch to Kurdish (KU) | All text in Kurdish, RTL | - | TO TEST |
| Switch to Arabic (AR) | All text in Arabic, RTL | - | TO TEST |
| Language persists on navigation | Remains selected across pages | - | TO TEST |
| Language persists on reload | Remains selected after refresh | - | TO TEST |
| Dashboard titles translated | Dashboard, Students, etc. in selected lang | - | TO TEST |
| Sidebar navigation translated | All menu items in selected lang | - | TO TEST |
| Buttons translated | All buttons in selected lang | - | TO TEST |
| Forms translated | All labels/placeholders in selected lang | - | TO TEST |
| Error messages translated | Login errors in selected lang | - | TO TEST |

---

## 9. Dashboard Tests

### Admin Dashboard
| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Total students count | Shows count from database | - | TO TEST |
| Total teachers count | Shows count from database | - | TO TEST |
| Total classes count | Shows count from database | - | TO TEST |
| Today's attendance | Shows today's records | - | TO TEST |
| Recent activities | Shows from activity_log | - | TO TEST |
| Quick actions | Links to add pages | - | TO TEST |

### Teacher Dashboard
| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| My classes | Shows assigned classes | - | TO TEST |
| My students | Shows students in assigned classes | - | TO TEST |
| My subjects | Shows assigned subjects | - | TO TEST |
| Today's attendance | Shows today's count | - | TO TEST |

### Student Dashboard
| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Personal info | Shows student details | - | TO TEST |
| Latest grades | Shows recent grades | - | TO TEST |
| Recent attendance | Shows recent attendance | - | TO TEST |
| Quick actions | Links to portal pages | - | TO TEST |

### Monitor Dashboard
| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Class info | Shows their class | - | TO TEST |
| Class members | Shows classmates | - | TO TEST |
| Only own class data | No access to other classes | - | TO TEST |

---

## 10. Chat System

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Admin global chat | Can send/receive in global room | - | TO TEST |
| Teacher direct chat | Can chat with students/other teachers | - | TO TEST |
| Student direct chat | Can chat with teacher | - | TO TEST |
| Class group chat | Members of class can chat | - | TO TEST |
| Admin chat with monitor | Direct chat available | - | TO TEST |
| Teacher chat with class monitor | Direct chat available | - | TO TEST |
| Messages saved to database | Persist in chat_messages table | - | TO TEST |

---

## 11. Search System

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Admin search students | Finds matching students | - | TO TEST |
| Admin search teachers | Finds matching teachers | - | TO TEST |
| Admin search subjects | Finds matching subjects | - | TO TEST |
| Teacher search | Only sees their students | - | TO TEST |
| Student search | Only sees self | - | TO TEST |

---

## 12. Responsive Design

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Laptop display | Full layout with sidebar | - | TO TEST |
| Tablet display | Adjusted layout, sidebar accessible | - | TO TEST |
| Mobile display | Stacked layout, hamburger menu | - | TO TEST |
| Sidebar scroll | Works without overlap | - | TO TEST |
| Tables scroll horizontally | Overflow scroll on small screens | - | TO TEST |

---

## 13. Security

| Test Case | Expected | Result | Status |
|-----------|----------|--------|--------|
| Direct page access (not logged in) | Redirect to login | - | TO TEST |
| Student accesses admin page | Redirect to dashboard | - | TO TEST |
| SQL injection attempts | Blocked by prepared statements | - | TO TEST |
| Password hashing | bcrypt in database | - | TO TEST |
| Session security | Proper session management | - | TO TEST |

---

## Conclusion

All modules should be tested by logging in as each role and verifying functionality. See XAMPP_SETUP.md for setup instructions and default account credentials.
